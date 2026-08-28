<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Models\Telegram\TelegramAccount;
use danog\MadelineProto\SimpleEventHandler;
use danog\MadelineProto\ParseMode;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramDriverCheckStarter
{
    public function execute(
        SimpleEventHandler $telegram,
    ): ?int {
        try {
            $account = $this->currentAccount();

            if (! $account) {
                Log::critical(
                    'Telegram driver listener account not found',
                );

                return null;
            }

            $configuredAccountId = config(
                'services.telegram.driver_check_account_id',
            );

            if (
                $configuredAccountId === null
                || (int) $account->id !== (int) $configuredAccountId
            ) {
                Log::critical(
                    'Telegram driver listener started with wrong account',
                    [
                        'current_account_id' => $account->id,
                        'expected_account_id' => $configuredAccountId,
                        'phone' => $account->phone,
                    ],
                );

                return null;
            }

            $chatLink = trim(
                (string) config(
                    'services.telegram.driver_check_chat_link',
                ),
            );

            if ($chatLink === '') {
                Log::critical(
                    'Telegram driver listener chat link is not configured',
                );

                return null;
            }

            $targetChatId = (int) $telegram->getId($chatLink);

            if ($targetChatId === 0) {
                Log::critical(
                    'Telegram driver listener failed to resolve chat ID',
                    [
                        'chat_link' => $chatLink,
                    ],
                );

                return null;
            }

            $account->update([
                'status' => 'running',
                'last_ping' => now(),
                'last_activity_at' => now(),
                'last_error' => null,
                'last_error_at' => null,
            ]);

            $this->notifyStarted(
                $telegram,
                $account,
                $chatLink,
            );

            Log::info(
                'Telegram driver listener started',
                [
                    'account_id' => $account->id,
                    'phone' => $account->phone,
                    'chat_link' => $chatLink,
                    'target_chat_id' => $targetChatId,
                ],
            );

            return $targetChatId;
        } catch (Throwable $e) {
            Log::critical(
                'Telegram driver listener start failed',
                [
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ],
            );

            return null;
        }
    }

    private function currentAccount(): ?TelegramAccount
    {
        $accountId = config(
            'services.telegram.driver_check_account_id',
        );

        if (
            $accountId === null
            || $accountId === ''
        ) {
            return null;
        }

        return TelegramAccount::query()
            ->whereKey((int) $accountId)
            ->where(
                'is_authorized',
                true,
            )
            ->first();
    }

    private function notifyStarted(
        SimpleEventHandler $telegram,
        TelegramAccount $account,
        string $chatLink,
    ): void {
        try {
            $telegram->messages->sendMessage([
                'peer' => 'me',

                'message' =>
                    "✅ <b>Telegram Driver Check Listener запущен</b>\n\n"
                    . "📱 Аккаунт: "
                    . $this->escape(
                        (string) $account->phone,
                    )
                    . "\n"
                    . "🆔 Account ID: "
                    . $account->id
                    . "\n"
                    . "💬 Чат: "
                    . $this->escape($chatLink)
                    . "\n"
                    . "📡 Статус: <b>RUNNING</b>\n"
                    . "🕐 Время: "
                    . now()->format('Y-m-d H:i:s'),

                'parse_mode' => ParseMode::HTML,

                'no_webpage' => true,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Telegram driver listener startup notification failed',
                [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ],
            );
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