<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Commonness;

/**
 * Default {@see TokenCommonnessRater}: distinctiveness derived primarily
 * from token length (a 3-letter fragment has far fewer possible distinct
 * values than an 8-letter one, so it is statistically far more likely to
 * coincidentally recur), with a small supplementary override list for
 * regionally very common short given names/nicknames.
 *
 * Deliberately NOT a large curated dictionary -- per-name frequency
 * statistics are a data problem, not an algorithm problem. If/when real
 * usage data becomes available, swap this class for one that looks
 * frequency up in a table; nothing else in the engine needs to change.
 */
final class LengthBasedCommonnessRater implements TokenCommonnessRater
{
    /**
     * @var array<int, float>
     */
    private const LENGTH_DISTINCTIVENESS = [
        3 => 0.35,
        4 => 0.55,
        5 => 0.75,
        6 => 0.90,
    ];

    private const DEFAULT_DISTINCTIVENESS = 1.0;

    /**
     * Small supplementary list of regionally very common short given
     * names / nicknames. These get an extra penalty on top of the
     * length curve because they are common even relative to their
     * length.
     */
    private const KNOWN_GENERIC_TOKENS = [
        'ali', 'anna', 'max', 'jon', 'bek', 'aka', 'opa', 'uka',
        'bro', 'sis', 'boss', 'king', 'mr', 'hon', 'xon', 'boy',
        'sam', 'jan', 'john',
    ];

    private const KNOWN_GENERIC_PENALTY = 0.7;

    public function distinctiveness(string $canonicalToken): float
    {
        $length = strlen($canonicalToken);

        $distinctiveness = self::LENGTH_DISTINCTIVENESS[$length] ?? (
            $length < 3 ? 0.2 : self::DEFAULT_DISTINCTIVENESS
        );

        if (in_array($canonicalToken, self::KNOWN_GENERIC_TOKENS, true)) {
            $distinctiveness *= self::KNOWN_GENERIC_PENALTY;
        }

        return max(0.0, min(1.0, $distinctiveness));
    }
}
