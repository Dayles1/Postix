<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\TelegramNameMatcher;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Models\Driver\TelegramDriverCheck;
use App\Models\Telegram\TelegramResolvedPhone;

final class ApplyResolvedTelegramPhone
{
    public function __construct(
        private readonly TelegramNameMatcher $nameMatcher,
    ) {
    }

    public function execute(
        TelegramDriverCheck $check,
        TelegramResolvedPhone $resolvedPhone,
    ): void {
        $telegramRaw =
            $resolvedPhone->telegram_raw ?? [];

        if (! is_array($telegramRaw)) {
            $telegramRaw = [];
        }

        $telegramRaw['resolved_from_cache'] = true;

        $telegramRaw['resolved_phone_id'] =
            $resolvedPhone->id;

        $match = $this->nameMatcher->match(
            $check->driver_name,
            $resolvedPhone->telegram_first_name,
            $resolvedPhone->telegram_last_name,
        );

        $telegramRaw['name_match'] = $match;

        $matched = (bool) (
            $match['matched'] ?? false
        );

        $status = $matched
            ? TelegramDriverCheckStatus::Confirmed
            : TelegramDriverCheckStatus::NotConfirmed;

        $check->update([
            'telegram_resolved_phone_id' =>
                $resolvedPhone->id,

            'telegram_user_id' =>
                $resolvedPhone->telegram_user_id,

            'telegram_username' =>
                $resolvedPhone->telegram_username,

            'telegram_first_name' =>
                $resolvedPhone->telegram_first_name,

            'telegram_last_name' =>
                $resolvedPhone->telegram_last_name,

            'telegram_raw' =>
                $telegramRaw,

            'status' =>
                $status,

            'error_message' =>
                null,

            'checked_at' =>
                now(),
        ]);

        /*
         * Current driver state.
         */
        if ($check->driver) {
            $check->driver->update([
                'status' => $matched
                    ? 'confirmed'
                    : 'not_confirmed',
            ]);
        }
    }
}