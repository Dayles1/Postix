<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Support;

use Normalizer;

/**
 * Decodes "fancy font" Unicode text (bold, italic, script, fraktur,
 * double-struck, monospace, fullwidth, circled, small-caps, superscript
 * Latin letters, ...) back to plain ASCII letters.
 *
 * Telegram display names very commonly use these -- people copy-paste
 * styled text from "fancy font" generators.
 *
 * Two layers are used:
 *
 *  1. Unicode NFKC compatibility normalization, which already has a
 *     built-in decomposition mapping for most of these blocks:
 *     Mathematical Alphanumeric Symbols (bold/italic/script/fraktur/
 *     double-struck/sans-serif/monospace), Fullwidth Forms, and
 *     Enclosed Alphanumerics (circled/parenthesized). This covers the
 *     large majority of "fancy font" styles with zero custom tables.
 *
 *  2. A small supplementary code-point map for the styles that do NOT
 *     have a Unicode compatibility decomposition -- small-caps Latin
 *     (Phonetic Extensions, e.g. "ᴋᴀʙɪʀ") and superscript/subscript
 *     Latin letters (Spacing Modifier Letters / Phonetic Extensions,
 *     e.g. "ᵏᵃᵇᶦʳ"). NFKC leaves these untouched because, unicode-wise,
 *     they are not "compatibility variants" of ASCII letters -- they
 *     are distinct phonetic symbols that happen to look like styled
 *     letters. This map is a deliberately small, easily extensible
 *     safety net; add an entry here if a new styled block turns up.
 */
final class UnicodeStyledTextDecoder
{
    /**
     * Small-caps / superscript / subscript Latin -> plain lowercase
     * Latin. Keyed by Unicode code point (int).
     *
     * @var array<int, string>
     */
    private const SUPPLEMENTARY_MAP = [
        // Latin superscript modifier letters (Spacing Modifier Letters block).
        0x02B0 => 'h', 0x02B2 => 'j', 0x02B3 => 'r', 0x02B7 => 'w',
        0x02B8 => 'y', 0x02E1 => 'l', 0x02E2 => 's', 0x02E3 => 'x',

        // Superscript Latin (Phonetic Extensions / Phonetic Extensions Supplement / Latin Extended-D).
        0x1D2C => 'a', 0x1D2E => 'b', 0x1D30 => 'd', 0x1D31 => 'e',
        0x1D33 => 'g', 0x1D34 => 'h', 0x1D35 => 'i', 0x1D36 => 'j',
        0x1D37 => 'k', 0x1D38 => 'l', 0x1D39 => 'm', 0x1D3A => 'n',
        0x1D3C => 'o', 0x1D3E => 'p', 0x1D40 => 'r', 0x1D41 => 's',
        0x1D42 => 't', 0x1D43 => 'a', 0x1D47 => 'b', 0x1D48 => 'd',
        0x1D49 => 'e', 0x1D4B => 'e', 0x1D4D => 'g', 0x1D4F => 'k',
        0x1D50 => 'm', 0x1D52 => 'o', 0x1D56 => 'p', 0x1D57 => 't',
        0x1D58 => 'u', 0x1D5B => 'v', 0x1D5D => 'b', 0x1D5E => 'd',
        0x1D5F => 'f', 0x1D60 => 'm', 0x1D61 => 'x', 0x1D62 => 'i',
        0x1D63 => 'r', 0x1D64 => 'u', 0x1D65 => 'v', 0x1D66 => 'b',
        0x1D67 => 'd', 0x1D68 => 'f', 0x1D69 => 'm', 0x1D6A => 'x',

        // Latin-letter small capitals (Phonetic Extensions block), used
        // decoratively as an all-caps look, e.g. "ᴋᴀʙɪʀ".
        0x1D00 => 'a', 0x1D01 => 'ae', 0x1D03 => 'b', 0x1D04 => 'c',
        0x1D05 => 'd', 0x1D07 => 'e', 0x1D0A => 'j', 0x1D0B => 'k',
        0x1D0C => 'l', 0x1D0D => 'm', 0x1D0E => 'n', 0x1D0F => 'o',
        0x1D18 => 'p', 0x1D1B => 't', 0x1D1C => 'u', 0x1D20 => 'v',
        0x1D21 => 'w', 0x1D22 => 'z', 0x0280 => 'r', 0x029F => 'l',
        0x0299 => 'b', 0x0262 => 'g', 0x029C => 'h', 0x026A => 'i',
        0x1D26 => 'g',

        // Subscript Latin (rarely used decoratively, still harmless to map).
        0x2090 => 'a', 0x2091 => 'e', 0x2092 => 'o', 0x2093 => 'x',
        0x2095 => 'h', 0x2096 => 'k', 0x2097 => 'l', 0x2098 => 'm',
        0x2099 => 'n', 0x209A => 'p', 0x209B => 's', 0x209C => 't',
    ];

    public function decode(string $value): string
    {
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_KC);

            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        return $this->applySupplementaryMap($value);
    }

    private function applySupplementaryMap(string $value): string
    {
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($characters)) {
            return $value;
        }

        $result = '';

        foreach ($characters as $character) {
            $codePoint = $this->codePoint($character);

            $result .= $codePoint !== null
                ? (self::SUPPLEMENTARY_MAP[$codePoint] ?? $character)
                : $character;
        }

        return $result;
    }

    private function codePoint(string $character): ?int
    {
        $bytes = unpack('C*', $character);

        if (! is_array($bytes)) {
            return null;
        }

        $bytes = array_values($bytes);
        $first = $bytes[0] ?? null;

        if ($first === null) {
            return null;
        }

        if ($first <= 0x7F) {
            return $first;
        }

        if (($first & 0xE0) === 0xC0 && isset($bytes[1])) {
            return (($first & 0x1F) << 6) | ($bytes[1] & 0x3F);
        }

        if (($first & 0xF0) === 0xE0 && isset($bytes[1], $bytes[2])) {
            return (($first & 0x0F) << 12)
                | (($bytes[1] & 0x3F) << 6)
                | ($bytes[2] & 0x3F);
        }

        if (($first & 0xF8) === 0xF0 && isset($bytes[1], $bytes[2], $bytes[3])) {
            return (($first & 0x07) << 18)
                | (($bytes[1] & 0x3F) << 12)
                | (($bytes[2] & 0x3F) << 6)
                | ($bytes[3] & 0x3F);
        }

        return null;
    }
}
