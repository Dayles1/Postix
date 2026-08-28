<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

final class TelegramOperationUserParser
{
    public function parse(
        string $text,
    ): ?string {
        if (
            ! preg_match(
                '/(?:^|\R)\s*Пользователь\s*:\s*([^\r\n]+)/iu',
                $text,
                $matches,
            )
        ) {
            return null;
        }

        $name = trim($matches[1]);

        return $name !== ''
            ? $name
            : null;
    }
}