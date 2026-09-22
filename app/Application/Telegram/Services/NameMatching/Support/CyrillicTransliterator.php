<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Support;

/**
 * Cyrillic (Russian + Uzbek/Karakalpak Cyrillic extensions) -> Latin
 * transliteration.
 *
 * This produces one *display* Latin spelling per Cyrillic letter. It is
 * intentionally a straight letter-by-letter map (not a smart, context
 * aware transliteration scheme) -- the job of reconciling the several
 * valid Latin spellings that a single Cyrillic name can map to (e.g.
 * "Ж" -> both "zh" and "j" are legitimate) belongs to
 * {@see OrthographicVariantFolder}, which runs afterwards on the
 * canonical comparison form. Keeping these two concerns separate is
 * what avoids a single giant, hard to extend replacement table.
 *
 * ORDER MATTERS, and it is the caller's responsibility: this map must be
 * applied BEFORE combining marks are stripped. Several Cyrillic letters
 * are, in Unicode terms, a base letter plus a combining mark --
 * "ё" = е + ◌̈, "й" = и + ◌̆, "ў" = у + ◌̆ -- so a diacritic strip that
 * runs first silently turns them into their unmarked base letter and the
 * name loses exactly the sound that distinguishes it ("Ёодгор" -> "eodgor"
 * instead of "yodgor", "Элёрбек" -> "elerbek" instead of "elyorbek").
 * {@see NameNormalizer} composes to NFC and transliterates first for this
 * reason.
 */
final class CyrillicTransliterator
{
    private const MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sh', 'ы' => 'i', 'э' => 'e', 'ю' => 'yu',
        'я' => 'ya', 'ъ' => '', 'ь' => '',

        // Uzbek Cyrillic extensions.
        'ў' => 'u', 'қ' => 'q', 'ғ' => 'g', 'ҳ' => 'h',

        /*
         * Neighbouring Cyrillic alphabets that turn up in driver names
         * copied from other documents (Karakalpak, Kazakh, Tajik,
         * Ukrainian). Mapped to the closest Uzbek/Russian sound rather
         * than to a scholarly romanization: the comparison form only has
         * to line up with how the same person types the name in Latin.
         */
        'ң' => 'n', 'ҷ' => 'j', 'җ' => 'j', 'ә' => 'a', 'ө' => 'o',
        'ү' => 'u', 'ұ' => 'u', 'һ' => 'h', 'і' => 'i', 'ї' => 'i',
        'є' => 'e', 'ќ' => 'k', 'ѓ' => 'g',
    ];

    public function transliterate(string $value): string
    {
        return strtr($value, self::MAP);
    }
}
