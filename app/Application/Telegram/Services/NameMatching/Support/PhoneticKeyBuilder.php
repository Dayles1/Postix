<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Support;

/**
 * Reduces a canonical token to a *sound* key: the spelling stripped of
 * the vowel choices that carry no meaning in this dataset.
 *
 * {@see OrthographicVariantFolder} already reconciles the consonants
 * people disagree about (zh/j, kh/x/h, q/k, gh/g, w/v). What it cannot
 * reconcile is the vowel: the same Uzbek name written by a Russian
 * speaker and by an Uzbek speaker differs in vowels and nothing else,
 * systematically, along one axis -- the Uzbek "o" is the Russian "a".
 *
 *   ODIL    / ADIL       ANVAR   / ANVOR
 *   YODGOR  / YADGOR     RUSTAM  / RUSTOM
 *   BAHODIR / BAHADIR    SOBIR   / SABIR
 *
 * The same holds, more weakly, for e/i/y in an unstressed position
 * (ELYOR / ILYOR, KARIM / KERIM), so those collapse too.
 *
 * This is the lossiest form the engine produces and it is treated
 * accordingly: {@see PhoneticKeyComparator} scores an equality here
 * below a canonical equality, because "the consonant skeleton and the
 * vowel pattern agree" is weaker evidence than "the spelling agrees".
 * It is never used for display.
 */
final class PhoneticKeyBuilder
{
    /**
     * Vowel equivalence classes, applied to an already canonical token.
     *
     * 'a' absorbs o (Uzbek/Russian axis), 'i' absorbs e and y (an
     * unstressed front vowel is written all three ways), 'u' stands
     * alone because o'/u is a genuine distinction people do keep.
     */
    private const VOWEL_CLASSES = [
        'a' => 'a', 'o' => 'a',
        'e' => 'i', 'i' => 'i', 'y' => 'i',
        'u' => 'u',
    ];

    public function build(string $canonical): string
    {
        if ($canonical === '') {
            return '';
        }

        $key = strtr($canonical, self::VOWEL_CLASSES);

        /*
         * Two vowels that collapsed onto the same class now sit next to
         * each other ("yo" -> "ia" -> ... ), and a doubled letter never
         * distinguishes two spellings of one name, so runs collapse.
         */
        return (string) (preg_replace('/(.)\1+/', '$1', $key) ?? $key);
    }
}
