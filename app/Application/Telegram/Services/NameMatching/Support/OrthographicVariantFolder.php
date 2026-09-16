<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Support;

use App\Application\Telegram\Services\NameMatching\NameNormalizer;

/**
 * Folds a *display* name token onto a canonical *comparison* form by
 * collapsing well-known regional spelling/transliteration variance.
 *
 * This is deliberately a separate step from {@see CyrillicTransliterator}
 * and from {@see NameNormalizer}:
 * the normalizer produces the spelling a human would actually recognize
 * ("IKRAMZHON"), while this class produces a throwaway form used only to
 * decide whether two *different* spellings represent the same sound
 * ("ikramjon" for both "ikramzhon" and "ikramjon").
 *
 * Rules are table-driven and applied as ordered, whole-pattern
 * replacements (not a single blind global str_replace pass) so adding a
 * new regional variant is a one-line change here, never a rewrite of the
 * comparison logic.
 *
 * IMPORTANT: this folding is intentionally lossy and must only be used
 * for *comparison*, never for display -- folding "kh" -> "h" would be a
 * confusing thing to show an operator.
 */
final class OrthographicVariantFolder
{
    /**
     * Ordered digraph/spelling folds. Order matters: longer patterns are
     * listed before the shorter patterns they could otherwise shadow.
     *
     * @var array<string, string>
     */
    private const DIGRAPH_FOLDS = [
        // Uzbek/Russian "Ж" romanizes as both "zh" and "j" -- this is the
        // single most important fold for this dataset (IKRAMZHON <-> IKRAMJON).
        'zh' => 'j',

        // "Х" romanizes as "kh", "x" or (informally) "h".
        'kh' => 'h',
        'xh' => 'h',
        'x' => 'h',

        // "Қ"/"Q" vs "K" (Uzbek Latin "Q" has no distinct Cyrillic/Russian
        // counterpart in casual typing, people alternate freely).
        'q' => 'k',

        // "Ғ" romanizes as "gh" or "g".
        'gh' => 'g',

        // Consonant cluster simplifications seen in casual spelling.
        'shch' => 'sh',
        'sch' => 'sh',
        'ts' => 'c',
        'tz' => 'c',
        'ph' => 'f',

        // "W" is not a native Uzbek/Russian letter; casual typists use it
        // interchangeably with "V" when transliterating.
        'w' => 'v',

        // "Е"/"Ye" at a word boundary vs plain "E".
        'ye' => 'e',

        // Patronymic/surname suffix spelling variance.
        'yev' => 'ev',
        'yova' => 'ova',
        'yovna' => 'ovna',
        'evich' => 'ovich',
        'yevich' => 'ovich',
        'evna' => 'ovna',
        'yevna' => 'ovna',
    ];

    /**
     * Patronymic suffixes stripped only from the *canonical* comparison
     * form, so a Telegram fragment of the surname root (rare, but
     * possible) can still line up with the driver's full patronymic.
     * The display form keeps the suffix untouched.
     */
    private const PATRONYMIC_SUFFIXES = [
        'ovich', 'evich', 'ovna', 'evna',
    ];

    public function fold(string $token): string
    {
        $token = $this->applyDigraphFolds($token);
        $token = $this->collapseVowelRuns($token);

        return $token;
    }

    /**
     * Folds a token AND strips a trailing patronymic suffix, if present,
     * returning the suffix-free canonical core. Used only where the
     * caller explicitly wants to compare "identity roots" rather than
     * full patronymic forms.
     */
    public function foldCore(string $token): string
    {
        $folded = $this->fold($token);

        foreach (self::PATRONYMIC_SUFFIXES as $suffix) {
            if (
                strlen($folded) > strlen($suffix) + 2
                && str_ends_with($folded, $suffix)
            ) {
                return substr($folded, 0, -strlen($suffix));
            }
        }

        return $folded;
    }

    private function applyDigraphFolds(string $token): string
    {
        foreach (self::DIGRAPH_FOLDS as $from => $to) {
            $token = str_replace($from, $to, $token);
        }

        return $token;
    }

    /**
     * Collapses any run of the same vowel (aa, ooo, ...) to a single
     * occurrence. This is intentionally more aggressive than the
     * consonant-preserving repeated-letter normalization in
     * {@see NameNormalizer}:
     * stretching vowels for emphasis ("IKRAAAMJOOON") is extremely common
     * in casual Telegram typing, and Uzbek/Russian names essentially
     * never rely on a genuine doubled vowel for identity, so folding it
     * away here carries very little false-positive risk.
     */
    private function collapseVowelRuns(string $token): string
    {
        return preg_replace('/([aeiou])\1+/', '$1', $token) ?? $token;
    }
}
