<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Evidence;

use App\Application\Telegram\Services\NameMatching\Comparison\ComparisonResult;
use App\Application\Telegram\Services\NameMatching\NameNormalizer;
use App\Application\Telegram\Services\NameMatching\Scoring\MatchDecision;
use App\Application\Telegram\Services\NameMatching\Token;

/**
 * Reads a Telegram display name that is nothing but initials.
 *
 * A driver whose profile says "К.Б.А" or "А,А,А," has written down his
 * whole name -- one letter per part, in whatever order he thinks of
 * them:
 *
 *   BOBOJONOV KUDRAT ABDULLAEVICH  ->  К.Б.А  (given, surname, patronymic)
 *   АҲМАДИЁВ АНВАР АЛИ ЎҒЛИ        ->  А,А,А,
 *
 * The token pipeline cannot see any of this: single letters are dropped
 * before comparison (rightly -- one letter is not a name), so these
 * profiles scored a flat zero and every driver behind one came back
 * unconfirmed.
 *
 * The evidence here is of a different kind. It is not "this word is that
 * name" but "every letter of this profile lines up with a distinct part
 * of that driver's name", which is why it is matched as a whole and
 * scored as a whole, and why it is all-or-nothing: if a single initial
 * has no part to belong to, this is not that driver's monogram and the
 * evidence is worth nothing.
 *
 * Order is not required. The two examples above put the given name
 * first and the surname first respectively; demanding document order
 * would throw away most real cases. Agreement in order is worth a small
 * bonus, no more.
 */
final class InitialsMatcher
{
    /**
     * Two initials are a coincidence waiting to happen, three are not
     * (a pair of letters collides across a driver list of any size; a
     * triple, ordered or not, effectively does not). Two are therefore
     * reported as real but sub-threshold evidence, three as a match.
     *
     * @var array<int, float>
     */
    private const SCORE_BY_COUNT = [
        2 => 62.0,
        3 => 86.0,
    ];

    private const SCORE_FOUR_OR_MORE = 90.0;

    /**
     * Initials that also happen to be in document order are marginally
     * better evidence than the same letters shuffled.
     */
    private const IN_ORDER_BONUS = 2.0;

    private const MAX_SCORE = 90.0;

    /**
     * Multi-letter initials: a Cyrillic letter that romanizes to two
     * Latin ones, and the Latin digraphs people use for the same sounds.
     *
     * @var list<string>
     */
    private const DIGRAPH_INITIALS = [
        'sh', 'ch', 'zh', 'kh', 'yo', 'yu', 'ya', 'ts', 'gh',
    ];

    public function __construct(
        private readonly NameNormalizer $normalizer = new NameNormalizer,
    ) {}

    /**
     * @param  list<Token>  $driverTokens
     */
    public function match(array $driverTokens, string $telegramDisplayName): ?InitialsEvidence
    {
        $initials = $this->extract($telegramDisplayName);

        if ($initials === null || count($initials) > count($driverTokens)) {
            return null;
        }

        $assignments = $this->assign($initials, $driverTokens);

        if ($assignments === null) {
            return null;
        }

        $score = $this->score($initials, $assignments, $driverTokens);

        if ($score <= 0.0) {
            return null;
        }

        return new InitialsEvidence(
            new MatchDecision(
                $score,
                'initials_match',
                'Initials match the driver name',
            ),
            $assignments,
        );
    }

    /**
     * The initials of a name that is *only* initials, or null when the
     * display name is anything else.
     *
     * Normalization does the hard part: styled letters are decoded,
     * emoji and every separator people put between initials become
     * whitespace, and Cyrillic is transliterated -- so "🇺🇿🚛 К.Б.А"
     * arrives here as "k b a". What is left to decide is whether every
     * piece of it is a single letter.
     *
     * @return list<string>|null
     */
    private function extract(string $telegramDisplayName): ?array
    {
        $normalized = $this->normalizer->normalize($telegramDisplayName);

        if ($normalized === '') {
            return null;
        }

        $parts = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) < 2) {
            return null;
        }

        $initials = [];

        foreach ($parts as $part) {
            if (ctype_digit($part)) {
                return null;
            }

            if (strlen($part) === 1) {
                $initials[] = $part;

                continue;
            }

            if (in_array($part, self::DIGRAPH_INITIALS, true)) {
                $initials[] = $part;

                continue;
            }

            /*
             * A real word: this is a name, not a monogram, and the token
             * pipeline is the right place to read it.
             */
            return null;
        }

        return $initials;
    }

    /**
     * Pairs every initial with a distinct driver token it could stand
     * for, or returns null if even one initial cannot be placed.
     *
     * The pass is greedy and prefers the token at the same position, so
     * a profile written in document order produces the assignment an
     * operator would expect to read.
     *
     * @param  list<string>  $initials
     * @param  list<Token>  $driverTokens
     * @return list<TokenAssignment>|null
     */
    private function assign(array $initials, array $driverTokens): ?array
    {
        $taken = [];
        $assignments = [];

        foreach ($initials as $position => $initial) {
            $matchedIndex = null;

            foreach ($this->candidateOrder($position, count($driverTokens)) as $index) {
                if (isset($taken[$index])) {
                    continue;
                }

                if (! $this->stands($initial, $driverTokens[$index])) {
                    continue;
                }

                $matchedIndex = $index;

                break;
            }

            if ($matchedIndex === null) {
                return null;
            }

            $taken[$matchedIndex] = true;

            $assignments[] = new TokenAssignment(
                $driverTokens[$matchedIndex],
                new EvidenceToken(
                    new Token($initial),
                    EvidenceSource::DisplayName,
                ),
                ComparisonResult::make(
                    100.0,
                    'initial_letter',
                    'Initial of ' . $driverTokens[$matchedIndex]->displayUpper(),
                ),
            );
        }

        return $assignments;
    }

    /**
     * Driver token indexes to try for the initial at $position: its own
     * position first, then the rest left to right.
     *
     * @return list<int>
     */
    private function candidateOrder(int $position, int $tokenCount): array
    {
        $order = $position < $tokenCount ? [$position] : [];

        for ($index = 0; $index < $tokenCount; $index++) {
            if ($index !== $position) {
                $order[] = $index;
            }
        }

        return $order;
    }

    /**
     * Whether this initial can stand for this name part.
     *
     * Both sides are compared in canonical form, which is what makes the
     * letters people write interchangeably interchangeable here too:
     * Q and K are one letter, as are X, KH and H, and ZH and J. Folding
     * only the driver's side -- as this did at first -- makes the
     * equivalence work in one direction and one direction only, so
     * QODIROV matched a Cyrillic "К." while KODIROV did not match the
     * Uzbek Latin "Q." of the very same person.
     *
     * The display form is still accepted as a fallback for the letters
     * folding leaves alone.
     */
    private function stands(string $initial, Token $driverToken): bool
    {
        if ($driverToken->canonical === '') {
            return false;
        }

        $canonicalInitial = (new Token($initial))->canonical;

        return ($canonicalInitial !== '' && str_starts_with($driverToken->canonical, $canonicalInitial))
            || str_starts_with($driverToken->canonical, $initial)
            || str_starts_with($driverToken->display, $initial);
    }

    /**
     * @param  list<string>  $initials
     * @param  list<TokenAssignment>  $assignments
     * @param  list<Token>  $driverTokens
     */
    private function score(array $initials, array $assignments, array $driverTokens): float
    {
        $count = count($initials);

        $score = $count >= 4
            ? self::SCORE_FOUR_OR_MORE
            : (self::SCORE_BY_COUNT[$count] ?? 0.0);

        if ($score === 0.0) {
            return 0.0;
        }

        if ($this->isInDocumentOrder($assignments, $driverTokens)) {
            $score += self::IN_ORDER_BONUS;
        }

        return min(self::MAX_SCORE, $score);
    }

    /**
     * @param  list<TokenAssignment>  $assignments
     * @param  list<Token>  $driverTokens
     */
    private function isInDocumentOrder(array $assignments, array $driverTokens): bool
    {
        $previous = -1;

        foreach ($assignments as $assignment) {
            $index = array_search($assignment->driverToken, $driverTokens, true);

            if ($index === false || $index <= $previous) {
                return false;
            }

            $previous = (int) $index;
        }

        return true;
    }
}
