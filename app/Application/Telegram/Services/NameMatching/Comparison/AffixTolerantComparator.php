<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Comparison;

use App\Application\Telegram\Services\NameMatching\Support\StringMetrics;
use App\Application\Telegram\Services\NameMatching\Token;

/**
 * Tier 4: the two names share an identity root and differ only in the
 * honorific affixes wrapped around it.
 *
 * This is the tier that answers the largest single class of production
 * misses, all of them the same shape -- a passport name and the name the
 * same person actually goes by:
 *
 *   ABDULKODIR  <->  Qodirali     (prefix dropped, suffix added)
 *   ELYOR       <->  Elyorbek     (suffix added)
 *   MUHAMMAD    <->  Muhammadali  (suffix added)
 *
 * None of the existing tiers can see these. Canonical equality compares
 * whole spellings; the edit distance between ABDULKODIR and QODIRALI is
 * far outside any sane typo bound; and the partial tier only fires when
 * one token is a literal substring of the other, which stops being true
 * as soon as both sides carry a *different* affix.
 *
 * What keeps it honest is {@see \App\Application\Telegram\Services\NameMatching\Support\NameAffixStripper}:
 * only known affixes are removed, only one per side, and never down to a
 * fragment. The score sits below the exact/transliteration tiers -- a
 * shared root is strong evidence, but it is one element of agreement
 * less than a shared spelling, and the cap at
 * {@see self::SCORE_ROOT_EQUAL} is what keeps a stripped match from
 * looking as certain as a literal one.
 */
final class AffixTolerantComparator implements TokenComparator
{
    /**
     * Both sides reduce to the same root.
     */
    private const SCORE_ROOT_EQUAL = 88.0;

    /**
     * The roots differ by a single character -- a shared root plus the
     * ordinary spelling noise the edit-distance tier handles elsewhere.
     */
    private const SCORE_ROOT_NEAR = 80.0;

    /**
     * A root shorter than this is not a root, it is a syllable, and
     * syllables recur across unrelated names.
     */
    private const MIN_ROOT_LENGTH = 4;

    public function compare(Token $driverToken, Token $telegramToken): ComparisonResult
    {
        $driverRoot = $driverToken->core;
        $telegramRoot = $telegramToken->core;

        if (
            strlen($driverRoot) < self::MIN_ROOT_LENGTH
            || strlen($telegramRoot) < self::MIN_ROOT_LENGTH
        ) {
            return ComparisonResult::none();
        }

        /*
         * Nothing was actually stripped on either side: whatever these
         * two tokens are, they are not an affix difference, and the
         * tiers that compare full spellings have already had their say.
         */
        if (! $driverToken->hasAffix() && ! $telegramToken->hasAffix()) {
            return ComparisonResult::none();
        }

        if ($driverRoot === $telegramRoot) {
            return ComparisonResult::make(
                self::SCORE_ROOT_EQUAL,
                'name_root_match',
                'Same name root, different name affix',
            );
        }

        /*
         * One character apart, on roots long enough for that to mean a
         * spelling variation rather than a different name.
         */
        $distance = StringMetrics::damerauLevenshtein($driverRoot, $telegramRoot);

        if (
            $distance === 1
            && min(strlen($driverRoot), strlen($telegramRoot)) >= 5
        ) {
            return ComparisonResult::make(
                self::SCORE_ROOT_NEAR,
                'name_root_match',
                'Same name root, different name affix',
            );
        }

        return ComparisonResult::none();
    }
}
