<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Evidence;

use App\Application\Telegram\Services\NameMatching\Comparison\TokenComparatorPipeline;
use App\Application\Telegram\Services\NameMatching\Token;

/**
 * Finds the best one-to-one pairing between driver tokens and Telegram
 * evidence tokens.
 *
 * A one-to-one (bipartite) assignment -- rather than letting every
 * driver token independently pick its own best match -- matters for two
 * reasons:
 *
 *  - it makes word order irrelevant "for free": "SAYFULLAYEV IKRAMZHON"
 *    vs "IKRAMJON SAYFULLAYEV" assigns each side correctly regardless
 *    of which token appears first;
 *  - it prevents double-counting a single piece of evidence: once a
 *    Telegram token has been claimed by the driver token it best
 *    supports, it cannot *also* inflate a second, unrelated driver
 *    token's score (e.g. a driver's surname must not be allowed to
 *    "borrow" the same username fragment that already matched the
 *    first name).
 *
 * The assignment itself is a simple greedy algorithm (sort all
 * candidate pairs by score, assign highest first, skip anything whose
 * driver token or evidence token is already taken). This is not
 * globally optimal in the general bipartite-matching sense, but for the
 * small token counts involved here (a handful of tokens per side) it
 * reliably picks the same result an optimal algorithm would, while
 * staying trivially fast and easy to reason about.
 */
final class TokenSetMatcher
{
    /**
     * Shared "slot" key for the raw, separator-free username string:
     * only one driver token may ever claim credit for containment
     * inside the whole agglutinated username, since it is a single
     * piece of evidence no matter how many driver tokens could
     * theoretically be found inside it.
     */
    private const RAW_USERNAME_SLOT = '__raw_username__';

    public function __construct(
        private readonly TokenComparatorPipeline $pipeline = new TokenComparatorPipeline([]),
    ) {}

    public static function withDefaultComparators(): self
    {
        return new self(TokenComparatorPipeline::default());
    }

    /**
     * @param  list<Token>  $driverTokens
     * @param  list<EvidenceToken>  $evidenceTokens
     * @return list<TokenAssignment>
     */
    public function match(array $driverTokens, array $evidenceTokens, string $usernameCompact): array
    {
        $pipeline = $this->pipeline;
        $candidates = [];

        foreach ($driverTokens as $driverIndex => $driverToken) {
            foreach ($evidenceTokens as $evidenceIndex => $evidenceToken) {
                $result = $pipeline->compare($driverToken, $evidenceToken->token);

                if (! $result->isMeaningful()) {
                    continue;
                }

                $candidates[] = [
                    'driver' => $driverIndex,
                    'evidence' => (string) $evidenceIndex,
                    'assignment' => new TokenAssignment($driverToken, $evidenceToken, $result),
                ];
            }
        }

        if ($usernameCompact !== '') {
            $compactToken = new Token($usernameCompact);

            foreach ($driverTokens as $driverIndex => $driverToken) {
                if ($driverToken->length() < 3) {
                    continue;
                }

                $result = $pipeline->compare($driverToken, $compactToken);

                if (! $result->isMeaningful()) {
                    continue;
                }

                $candidates[] = [
                    'driver' => $driverIndex,
                    'evidence' => self::RAW_USERNAME_SLOT,
                    'assignment' => new TokenAssignment(
                        $driverToken,
                        new EvidenceToken($compactToken, EvidenceSource::Username),
                        $result,
                    ),
                ];
            }
        }

        usort(
            $candidates,
            static fn (array $a, array $b): int => $b['assignment']->score() <=> $a['assignment']->score(),
        );

        $usedDriverSlots = [];
        $usedEvidenceSlots = [];
        $assignments = [];

        foreach ($candidates as $candidate) {
            if (
                isset($usedDriverSlots[$candidate['driver']])
                || isset($usedEvidenceSlots[$candidate['evidence']])
            ) {
                continue;
            }

            $usedDriverSlots[$candidate['driver']] = true;
            $usedEvidenceSlots[$candidate['evidence']] = true;
            $assignments[] = $candidate['assignment'];
        }

        usort(
            $assignments,
            static fn (TokenAssignment $a, TokenAssignment $b): int => $b->score() <=> $a->score(),
        );

        return $assignments;
    }
}
