<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Comparison;

use App\Application\Telegram\Services\NameMatching\Token;

/**
 * Tier 5: the two names are the same name written by two people who
 * disagree about the vowels.
 *
 * Uzbek names romanize along a vowel axis that nobody applies
 * consistently -- the Uzbek "o" is the Russian "a", and an unstressed
 * front vowel is written e, i or y by taste:
 *
 *   ADILKHAN  <->  Odilxon      YADGOR  <->  Yodgor
 *   ANVAR     <->  Anvor        RUSTAM  <->  Rustom
 *
 * {@see \App\Application\Telegram\Services\NameMatching\Support\PhoneticKeyBuilder}
 * folds that axis away; this tier compares what is left, first on the
 * whole name and then, one step looser, on the affix-free root -- which
 * is what finally reconciles a passport ADILKHAN with a Telegram
 * Odilxon, where the affix AND the vowels both differ.
 *
 * It is the loosest tier in the engine and is priced as such. Two
 * guards keep it from inventing matches:
 *
 *  - equality only, never a distance: a phonetic key that is merely
 *    *close* to another is not evidence of anything, and the tiers above
 *    already own real spelling variation;
 *  - a minimum key length, because with the vowels folded away a short
 *    key stops being distinctive (three-letter names would start pairing
 *    off with each other).
 */
final class PhoneticKeyComparator implements TokenComparator
{
    /**
     * Whole names agree once the vowel axis is folded away.
     */
    private const SCORE_PHONETIC_EQUAL = 90.0;

    /**
     * Only the roots agree, and only phonetically: a different affix on
     * top of a different vowel. Still the same name, two steps removed
     * from the spelling, and scored two steps down.
     */
    private const SCORE_ROOT_PHONETIC_EQUAL = 84.0;

    /**
     * Below this a key is too generic to carry identity: with a/o and
     * e/i/y collapsed, three-letter keys coincide across unrelated
     * names.
     */
    private const MIN_KEY_LENGTH = 4;

    public function compare(Token $driverToken, Token $telegramToken): ComparisonResult
    {
        /*
         * Identical canonical spellings are tier 1's business; this tier
         * exists only for the pairs that disagree on paper.
         */
        if ($driverToken->canonical === $telegramToken->canonical) {
            return ComparisonResult::none();
        }

        if (
            strlen($driverToken->phonetic) >= self::MIN_KEY_LENGTH
            && $driverToken->phonetic === $telegramToken->phonetic
        ) {
            return ComparisonResult::make(
                self::SCORE_PHONETIC_EQUAL,
                'phonetic_variant',
                'Same name, different vowel spelling',
            );
        }

        if (
            strlen($driverToken->coreKey) >= self::MIN_KEY_LENGTH
            && $driverToken->coreKey === $telegramToken->coreKey
        ) {
            return ComparisonResult::make(
                self::SCORE_ROOT_PHONETIC_EQUAL,
                'phonetic_root_variant',
                'Same name root, different vowel spelling',
            );
        }

        return ComparisonResult::none();
    }
}
