<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching;

use App\Application\Telegram\Services\NameMatching\Support\OrthographicVariantFolder;

/**
 * One normalized name/username fragment.
 *
 * Carries two representations on purpose:
 *
 *  - {@see self::$display} is the spelling an operator should see
 *    (accents/emoji/styling removed, but real spelling preserved).
 *  - {@see self::$canonical} is a throwaway form, additionally folded
 *    through {@see OrthographicVariantFolder}, used only to decide
 *    whether two differently-spelled tokens represent the same name.
 */
final class Token
{
    public readonly string $canonical;

    public function __construct(
        public readonly string $display,
        ?OrthographicVariantFolder $folder = null,
    ) {
        $this->canonical = ($folder ?? new OrthographicVariantFolder)->foldCore($display);
    }

    public function length(): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($this->display, 'UTF-8')
            : strlen($this->display);
    }

    public function displayUpper(): string
    {
        return function_exists('mb_strtoupper')
            ? mb_strtoupper($this->display, 'UTF-8')
            : strtoupper($this->display);
    }
}
