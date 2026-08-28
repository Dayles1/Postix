<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Models\Driver\TelegramDriverCheck;
use danog\MadelineProto\ParseMode;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NotifyTelegramResolverExhaustion
{
    public function execute(
        SimpleEventHandler $telegram,
        int $targetChatId,
    ): void {
        $checks = TelegramDriverCheck::query()
            ->where(
                'telegram_chat_id',
                $targetChatId,
            )
            ->where(
                'status',
                TelegramDriverCheckStatus::NotConfirmed,
            )
            ->where(
                'error_message',
                'Resolver accounts are unavailable.',
            )
            ->whereNull('reported_at')
            ->orderBy('id')
            ->limit(20)
            ->get();

        foreach ($checks as $check) {
            try {
                $notificationChatId = config(
                    'services.telegram.driver_check_notification_chat_id',
                );

                if (
                    $notificationChatId === null
                    || $notificationChatId === ''
                ) {
                    Log::warning(
                        'Resolver notification chat is not configured',
                        [
                            'check_id' => $check->id,
                        ],
                    );

                    continue;
                }

                $telegram->messages->sendMessage([
                    'peer' => (int) $notificationChatId,

                    'message' =>
                        "⚠️ <b>TELEGRAM ACCOUNTLAR YETARLI EMAS</b>\n\n"
                        . "Check ID: #"
                        . $check->id
                        . "\n"
                        . "📱 Phone: "
                        . $this->escape(
                            (string) (
                                $check->phone_normalized
                                ?? '-'
                            ),
                        )
                        . "\n\n"
                        . "Resolver account mavjud emas.\n"
                        . "Yangi account qo‘shish kerak.",

                    'parse_mode' => ParseMode::HTML,

                    'no_webpage' => true,
                ]);

                $check->update([
                    'reported_at' => now(),
                ]);
            } catch (Throwable $e) {
                Log::error(
                    'Failed to send resolver exhaustion notification',
                    [
                        'check_id' => $check->id,
                        'error' => $e->getMessage(),
                        'exception' => $e::class,
                    ],
                );
            }
        }
    }

    private function escape(
        string $value,
    ): string {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
    }
}