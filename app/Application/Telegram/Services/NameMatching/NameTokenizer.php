<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching;

/**
 * Splits a normalized name or username into meaningful {@see Token}s.
 *
 * Responsibilities kept deliberately narrow:
 *  - word splitting (whitespace + letter/digit boundaries, so
 *    "ikramjon1999" and "007bekzod" expose their letter part as its own
 *    token -- numbers are otherwise dropped, see item 18/20 of the spec);
 *  - dropping pure-digit tokens (birth years, decoration numbers carry
 *    no identity information);
 *  - dropping patronymic/lineage markers (ogli/ogly/oglu/uly/ugli/qizi/
 *    kizi/qizy) as *standalone* tokens -- they are structure, not
 *    identity, and essentially never appear on a Telegram profile.
 *
 * Everything else (transliteration, styled-text decoding, emoji/symbol
 * removal) already happened in {@see NameNormalizer} before this class
 * ever sees the string.
 */
final class NameTokenizer
{
    /**
     * Lineage/patronymic markers. Matched against the *display* token
     * because they are plain Latin words in every spelling variant that
     * matters here; no orthographic folding is needed to recognize them.
     */
    private const PATRONYMIC_MARKERS = [
        'ogli', 'ogly', 'oglu', 'uly', 'ugli',
        'qizi', 'kizi', 'qizy',
    ];

    /**
     * What one Cyrillic letter can become once transliterated: a single
     * Latin letter, or one of these pairs (Ш -> sh, Ё -> yo, ...).
     */
    private const LETTER_DIGRAPHS = [
        'sh', 'ch', 'zh', 'kh', 'yo', 'yu', 'ya', 'ts', 'gh',
    ];

    /**
     * A word typed with a space after every letter needs at least this
     * many letters before it is read as a word. Two single letters are
     * initials ("I N"), and those are read elsewhere.
     */
    private const MIN_SPACED_LETTERS = 3;

    public function __construct(
        private readonly NameNormalizer $normalizer = new NameNormalizer,
    ) {}

    /**
     * @return list<Token>
     */
    public function tokenize(string $rawName): array
    {
        $normalized = $this->normalizer->normalize($rawName);

        return $this->tokensFromNormalized($normalized);
    }

    /**
     * @return array{tokens: list<Token>, compact: string}
     */
    public function tokenizeUsername(?string $rawUsername): array
    {
        if ($rawUsername === null || trim($rawUsername) === '') {
            return ['tokens' => [], 'compact' => ''];
        }

        $username = ltrim(trim($rawUsername), '@');
        $normalized = $this->normalizer->normalize($username);

        return [
            'tokens' => $this->tokensFromNormalized($normalized),
            'compact' => $this->normalizer->normalizeUsername($username),
        ];
    }

    /**
     * @return list<Token>
     */
    private function tokensFromNormalized(string $normalized): array
    {
        if ($normalized === '') {
            return [];
        }

        $words = $this->joinSpacedLetters(
            preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        );

        $tokens = [];
        $seen = [];

        foreach ($words as $word) {
            foreach ($this->splitLetterDigitBoundaries($word) as $fragment) {
                if ($fragment === '' || $this->length($fragment) < 2) {
                    continue;
                }

                if (ctype_digit($fragment)) {
                    continue;
                }

                if (in_array($fragment, self::PATRONYMIC_MARKERS, true)) {
                    continue;
                }

                if (isset($seen[$fragment])) {
                    continue;
                }

                $seen[$fragment] = true;
                $tokens[] = new Token($fragment);
            }
        }

        return $tokens;
    }

    /**
     * Glues a word written one letter at a time back together.
     *
     * "М У Р О Д" is a name, spelled out for decoration; split on
     * whitespace it is five single letters, and single letters are
     * dropped below, so the profile used to come back as having no name
     * at all. A run of at least MIN_SPACED_LETTERS single letters is
     * therefore read as one word. Shorter runs are left alone: "I N" is
     * a pair of initials, and InitialsMatcher reads those from the
     * display name itself.
     *
     * @param  list<string>  $words
     * @return list<string>
     */
    private function joinSpacedLetters(array $words): array
    {
        $result = [];
        $run = [];

        $flush = function () use (&$result, &$run): void {
            if (count($run) >= self::MIN_SPACED_LETTERS) {
                $result[] = implode('', $run);
            } else {
                array_push($result, ...$run);
            }

            $run = [];
        };

        foreach ($words as $word) {
            if ($this->isSingleLetter($word)) {
                $run[] = $word;

                continue;
            }

            $flush();
            $result[] = $word;
        }

        $flush();

        return $result;
    }

    private function isSingleLetter(string $word): bool
    {
        return preg_match('/^[a-z]$/', $word) === 1
            || in_array($word, self::LETTER_DIGRAPHS, true);
    }

    /**
     * Splits "ikramjon1999" -> ["ikramjon", "1999"] and
     * "007bekzod" -> ["007", "bekzod"] on letter/digit transitions, so a
     * name glued to a number in a username never hides the name part.
     *
     * @return list<string>
     */
    private function splitLetterDigitBoundaries(string $word): array
    {
        $parts = preg_split('/(?<=[a-z])(?=[0-9])|(?<=[0-9])(?=[a-z])/', $word) ?: [$word];

        return array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
