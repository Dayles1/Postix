<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching;

use App\Application\Telegram\Services\NameMatching\Support\CyrillicTransliterator;
use App\Application\Telegram\Services\NameMatching\Support\OrthographicVariantFolder;
use App\Application\Telegram\Services\NameMatching\Support\UnicodeStyledTextDecoder;
use Normalizer;

/**
 * Produces the "display" normalized form of a name/username: the
 * spelling an operator would actually recognize, with styling, accents,
 * emoji, punctuation and case differences removed, but without folding
 * away genuine spelling differences (that is
 * {@see OrthographicVariantFolder}'s job).
 *
 * Pipeline (each step documented at its call site below):
 *
 *   1. Decode styled Unicode letters (fancy fonts) to plain Latin.
 *   2. Strip zero-width / variation-selector characters.
 *   3. Lowercase.
 *   4. Compose to NFC, then transliterate Cyrillic to Latin.
 *   5. Strip combining diacritical marks (é -> e).
 *   6. Strip apostrophe variants (oʻ/o' -> o).
 *   7. Strip everything that is not a-z/0-9/space -- this is what
 *      removes emoji, decorative symbols and separators (. _ - | • ⚡).
 *   8. Collapse whitespace.
 *   9. Collapse runs of 3+ identical letters to one occurrence
 *      (KABIIIR -> KABIR) while leaving genuine doubled letters
 *      (Anna, Alla) untouched -- those are absorbed by the bounded
 *      edit-distance comparator instead, see EditDistanceComparator.
 */
final class NameNormalizer
{
    public function __construct(
        private readonly UnicodeStyledTextDecoder $styledTextDecoder = new UnicodeStyledTextDecoder,
        private readonly CyrillicTransliterator $transliterator = new CyrillicTransliterator,
    ) {}

    public function normalize(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = $this->styledTextDecoder->decode($value);

        $value = preg_replace(
            '/[\x{200B}-\x{200D}\x{2060}\x{FE0E}-\x{FE0F}\x{FEFF}\x{00AD}]/u',
            '',
            $value,
        ) ?? $value;

        $value = $this->lower($value);

        /*
         * Transliteration runs BEFORE diacritics are stripped, and on a
         * composed (NFC) string.
         *
         * Half of the Uzbek/Russian Cyrillic letters that carry identity
         * are a base letter plus a combining mark in Unicode terms:
         * "ё" = е + ◌̈, "й" = и + ◌̆, "ў" = у + ◌̆. Stripping marks first
         * turned them into their bare base letter before the map ever saw
         * them, so "Ёодгор" normalized to "eodgor" instead of "yodgor" and
         * "Элёрбек" to "elerbek" instead of "elyorbek" -- neither could
         * then match the Latin spelling of the same name. Composing first
         * also catches names that arrive already decomposed.
         */
        $value = $this->compose($value);
        $value = $this->transliterator->transliterate($value);

        /*
         * Whatever marks are left are genuine Latin accents (José -> jose).
         */
        $value = $this->stripDiacritics($value);

        $value = str_replace(
            ["'", '’', '‘', '′', '`', 'ʻ', 'ʼ', 'ʹ', 'ʺ'],
            '',
            $value,
        );

        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $this->collapseStretchedLetters($value);
    }

    public function normalizeUsername(?string $username): string
    {
        if ($username === null || trim($username) === '') {
            return '';
        }

        return str_replace(' ', '', $this->normalize($username));
    }

    /**
     * Canonical composition (NFC): re-joins a base letter and its
     * combining mark into the single code point the Cyrillic map is
     * keyed by, so an already-decomposed "ё" is transliterated like a
     * precomposed one.
     */
    private function compose(string $value): string
    {
        if (! class_exists(Normalizer::class)) {
            return $value;
        }

        $composed = Normalizer::normalize($value, Normalizer::FORM_C);

        return is_string($composed) ? $composed : $value;
    }

    private function stripDiacritics(string $value): string
    {
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_D);

            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        return preg_replace('/\p{Mn}+/u', '', $value) ?? $value;
    }

    /**
     * Collapses a run of 3+ identical letters to a single occurrence.
     * Deliberately conservative (3+, not 2+) so real doubled-letter
     * names ("Anna", "Alla") are never touched here.
     */
    private function collapseStretchedLetters(string $value): string
    {
        return preg_replace('/([a-z0-9])\1{2,}/', '$1', $value) ?? $value;
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}
