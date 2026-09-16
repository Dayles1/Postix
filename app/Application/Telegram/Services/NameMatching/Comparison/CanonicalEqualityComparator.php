<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Comparison;

use App\Application\Telegram\Services\NameMatching\Token;

/**
 * Tier 1: the two tokens are the same identity fragment once spelling
 * variance is folded away.
 *
 * Distinguishes two outcomes that are equally strong evidence but
 * deserve a different explanation:
 *
 *  - the *display* spellings already matched -> "exact";
 *  - only the *canonical* (orthographically folded) forms matched, e.g.
 *    "ikramzhon" vs "ikramjon", or "икрамжон" transliterated to
 *    "ikramzhon" vs "ikramjon" -> "transliteration_variant".
 */
final class CanonicalEqualityComparator implements TokenComparator
{
    private const SCORE_EXACT = 100.0;

    private const SCORE_VARIANT = 96.0;

    public function compare(Token $driverToken, Token $telegramToken): ComparisonResult
    {
        if ($driverToken->canonical === '' || $telegramToken->canonical === '') {
            return ComparisonResult::none();
        }

        if ($driverToken->canonical !== $telegramToken->canonical) {
            return ComparisonResult::none();
        }

        if ($driverToken->display === $telegramToken->display) {
            return ComparisonResult::make(self::SCORE_EXACT, 'exact', 'Exact match');
        }

        return ComparisonResult::make(
            self::SCORE_VARIANT,
            'transliteration_variant',
            'Transliteration variant',
        );
    }
}
