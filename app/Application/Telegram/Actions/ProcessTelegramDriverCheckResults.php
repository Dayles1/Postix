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

    /**
     * @param list<int> $targetChatIds
     */
    public function execute(
        SimpleEventHandler $telegram,
        array $targetChatIds,
    ): void {
        if ($targetChatIds === []) {
            return;
        }

        $checks = TelegramDriverCheck::query()
            ->whereIn(
                'telegram_chat_id',
                $targetChatIds,
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