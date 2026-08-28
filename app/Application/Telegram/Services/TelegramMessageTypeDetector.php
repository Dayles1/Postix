<?php

namespace App\Application\Telegram\Services;

use App\Enums\Drivers\TelegramDriverMessageType;

final class TelegramMessageTypeDetector
{
    public function detect(
        string $text,
    ): TelegramDriverMessageType {
        $text = trim($text);

        if (
            str_contains(
                $text,
                '👤 Создан новый водитель',
            )
        ) {
            return TelegramDriverMessageType::CREATED_DRIVER;
        }

        if (
            str_contains(
                $text,
                '👤 Изменён водитель',
            )
            || str_contains(
                $text,
                '👤 Изменен водитель',
            )
        ) {
            return TelegramDriverMessageType::UPDATED_DRIVER;
        }

        if (
            str_contains(
                $text,
                '🚛 Создан новый транспорт',
            )
        ) {
            return TelegramDriverMessageType::CREATED_TRANSPORT;
        }

        if (
            str_contains(
                $text,
                '🚛 Изменены данные транспорта',
            )
        ) {
            return TelegramDriverMessageType::UPDATED_TRANSPORT;
        }

        return TelegramDriverMessageType::UNKNOWN;
    }
}