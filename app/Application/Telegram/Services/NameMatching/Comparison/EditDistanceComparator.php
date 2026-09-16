<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Comparison;

use App\Application\Telegram\Services\NameMatching\Support\StringMetrics;
use App\Application\Telegram\Services\NameMatching\Token;

/**
 * Tier 2: bounded "human spelling variation" similarity -- typos,
 * transposed letters, a missing/extra letter, residual stretched-vowel
 * noise the normalizer didn't fully absorb.
 *
 * The critical design decision here is the *gate*: the edit distance is
 * only ever accepted when it is small **relative to the token length**.
 * This is what keeps two long, unrelated names at a real zero instead of
 * the ~25% Levenshtein-derived score that caused the original bug
 * (two names of very different length and content will always have a
 * "distance" much larger than this bound allows).
 *
 * Damerau-Levenshtein (transposition-aware) is used as the primary
 * distance metric because swapped-adjacent-letter typos are common and
 * plain Levenshtein scores them worse than a human would. Jaro-Winkler
 * is blended in as a secondary signal (never as an independent trigger
 * -- see {@see StringMetrics::jaroWinkler()}) to reward a shared prefix,
 * which fits names well.
 */
final class EditDistanceComparator implements TokenComparator
{
    public function compare(Token $driverToken, Token $telegramToken): ComparisonResult
    {
        $a = $driverToken->canonical;
        $b = $telegramToken->canonical;

        if ($a === '' || $b === '' || $a === $b) {
            return ComparisonResult::none();
        }

        $maxLength = max(strlen($a), strlen($b));
        $distance = StringMetrics::damerauLevenshtein($a, $b);
        $allowed = max(1, intdiv($maxLength, 4));

        if ($distance > $allowed) {
            return ComparisonResult::none();
        }

        $distanceScore = 100.0 * (1.0 - ($distance / $maxLength));
        $jaroWinklerScore = StringMetrics::jaroWinkler($a, $b) * 100.0;

        $blended = ($distanceScore * 0.75) + ($jaroWinklerScore * 0.25);

        /*
         * A single extra/missing/substituted/transposed character on a
         * reasonably long token is a classic human spelling variation
         * (BEKHZOD/BEKZOD, IKRAMZHON/IKRAMJON after folding) -- it
         * should not be scored much lower than an exact match.
         */
        if ($maxLength >= 5 && $distance <= 1) {
            $blended = max(90.0, $blended);
        }

        return ComparisonResult::make(
            min(99.0, $blended),
            'minor_spelling_variant',
            'Minor spelling variation',
        );
    }
}
