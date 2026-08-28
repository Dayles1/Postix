<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\TelegramDriverCheckReporter;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Models\Driver\TelegramDriverCheck;
use danog\MadelineProto\SimpleEventHandler;

final class ProcessTelegramDriverCheckResults
{
    public function __construct(
        private readonly TelegramDriverCheckReporter $reporter,
    ) {
    }

    public function execute(
        SimpleEventHandler $telegram,
        int $targetChatId,
    ): void {
        $checks = TelegramDriverCheck::query()
            ->where(
                'telegram_chat_id',
                $targetChatId,
            )
            ->whereIn(
                'status',
                [
                    TelegramDriverCheckStatus::Confirmed,
                    TelegramDriverCheckStatus::NotConfirmed,
                ],
            )
            ->whereNull('reported_at')
            ->orderBy('id')
            ->limit(10)
            ->get();

        foreach ($checks as $check) {
            $this->reporter->send(
                $telegram,
                $check,
                data_get(
                    $check->telegram_raw,
                    'name_match',
                ),
            );
        }
    }
}