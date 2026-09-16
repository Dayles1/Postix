<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Support;

/**
 * Cyrillic (Russian + Uzbek Cyrillic extensions) -> Latin transliteration.
 *
 * This produces one *display* Latin spelling per Cyrillic letter. It is
 * intentionally a straight letter-by-letter map (not a smart, context
 * aware transliteration scheme) -- the job of reconciling the several
 * valid Latin spellings that a single Cyrillic name can map to (e.g.
 * "Ж" -> both "zh" and "j" are legitimate) belongs to
 * {@see OrthographicVariantFolder}, which runs afterwards on the
 * canonical comparison form. Keeping these two concerns separate is
 * what avoids a single giant, hard to extend replacement table.
 */
final class CyrillicTransliterator
{
    private const MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sh', 'ы' => 'i', 'э' => 'e', 'ю' => 'yu',
        'я' => 'ya', 'ъ' => '', 'ь' => '',

        // Uzbek Cyrillic extensions.
        'ў' => 'u', 'қ' => 'q', 'ғ' => 'g', 'ҳ' => 'h',
    ];

    public function transliterate(string $value): string
    {
        return strtr($value, self::MAP);
    }
}
