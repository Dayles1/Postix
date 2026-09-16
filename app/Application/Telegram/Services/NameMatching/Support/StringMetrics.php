<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Support;

use App\Application\Telegram\Services\NameMatching\NameNormalizer;

/**
 * Small, dependency-free string similarity primitives.
 *
 * These operate on already-normalized ASCII-ish tokens (see
 * {@see NameNormalizer}),
 * so plain byte-based algorithms are sufficient and fast.
 */
final class StringMetrics
{
    /**
     * Damerau-Levenshtein distance (optimal string alignment variant):
     * insertions, deletions, substitutions and adjacent transpositions
     * all cost 1. Transposition awareness matters for names because
     * swapped-adjacent-letter typos ("IKARMZHON" for "IKRAMZHON") are
     * common and a plain Levenshtein distance scores them worse than a
     * human would.
     */
    public static function damerauLevenshtein(string $a, string $b): int
    {
        $lenA = strlen($a);
        $lenB = strlen($b);

        if ($lenA === 0) {
            return $lenB;
        }

        if ($lenB === 0) {
            return $lenA;
        }

        $distance = [];

        for ($i = 0; $i <= $lenA; $i++) {
            $distance[$i][0] = $i;
        }

        for ($j = 0; $j <= $lenB; $j++) {
            $distance[0][$j] = $j;
        }

        for ($i = 1; $i <= $lenA; $i++) {
            for ($j = 1; $j <= $lenB; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;

                $distance[$i][$j] = min(
                    $distance[$i - 1][$j] + 1,
                    $distance[$i][$j - 1] + 1,
                    $distance[$i - 1][$j - 1] + $cost,
                );

                if (
                    $i > 1
                    && $j > 1
                    && $a[$i - 1] === $b[$j - 2]
                    && $a[$i - 2] === $b[$j - 1]
                ) {
                    $distance[$i][$j] = min(
                        $distance[$i][$j],
                        $distance[$i - 2][$j - 2] + 1,
                    );
                }
            }
        }

        return $distance[$lenA][$lenB];
    }

    /**
     * Jaro-Winkler similarity (0..1). Rewards a shared prefix, which
     * fits names well (the start of a name is usually the stable part),
     * but is used here only as a *blending* signal inside an already
     * distance-gated comparison, never as an independent match trigger
     * -- on its own it can assign a deceptively high score to unrelated
     * strings that merely share a short prefix.
     */
    public static function jaroWinkler(string $a, string $b): float
    {
        $jaro = self::jaro($a, $b);

        if ($jaro <= 0.0) {
            return 0.0;
        }

        $prefixLength = 0;
        $maxPrefix = min(4, strlen($a), strlen($b));

        for ($i = 0; $i < $maxPrefix; $i++) {
            if ($a[$i] !== $b[$i]) {
                break;
            }

            $prefixLength++;
        }

        return $jaro + ($prefixLength * 0.1 * (1.0 - $jaro));
    }

    private static function jaro(string $a, string $b): float
    {
        $lenA = strlen($a);
        $lenB = strlen($b);

        if ($lenA === 0 && $lenB === 0) {
            return 1.0;
        }

        if ($lenA === 0 || $lenB === 0) {
            return 0.0;
        }

        $matchDistance = max(0, intdiv(max($lenA, $lenB), 2) - 1);

        $aMatches = array_fill(0, $lenA, false);
        $bMatches = array_fill(0, $lenB, false);

        $matches = 0;

        for ($i = 0; $i < $lenA; $i++) {
            $start = max(0, $i - $matchDistance);
            $end = min($i + $matchDistance + 1, $lenB);

            for ($j = $start; $j < $end; $j++) {
                if ($bMatches[$j] || $a[$i] !== $b[$j]) {
                    continue;
                }

                $aMatches[$i] = true;
                $bMatches[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        $transpositions = 0;
        $k = 0;

        for ($i = 0; $i < $lenA; $i++) {
            if (! $aMatches[$i]) {
                continue;
            }

            while (! $bMatches[$k]) {
                $k++;
            }

            if ($a[$i] !== $b[$k]) {
                $transpositions++;
            }

            $k++;
        }

        $transpositions = intdiv($transpositions, 2);

        return (
            ($matches / $lenA)
            + ($matches / $lenB)
            + (($matches - $transpositions) / $matches)
        ) / 3.0;
    }
}
