<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\TelegramDriverCheckChats;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramDriverCheckChat;
use App\Telegram\TelegramRestartNotice;
use danog\MadelineProto\SimpleEventHandler;
use danog\MadelineProto\ParseMode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class TelegramDriverCheckStarter
{
    public function __construct(
        private readonly TelegramDriverCheckChats $chats,
    ) {
    }

    /**
     * Ids of the chats to watch, or null when the listener must not run at
     * all.
     *
     * An empty array is a valid, healthy result: the watch list is simply
     * empty, and the refresh cron will pick up the first chat added from the
     * panel. Only a broken account is fatal.
     *
     * @return list<int>|null
     */
    public function execute(
        SimpleEventHandler $telegram,
    ): ?array {
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

            /*
             * The environment only seeds the list; from here on the table is
             * the source of truth, for this start and for every refresh.
             */
            $this->chats->syncFromConfig();

            $targetChatIds = $this->chats->resolve($telegram);

            if ($targetChatIds === []) {
                Log::warning(
                    'Telegram driver listener has no chats to watch',
                );
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
                $targetChatIds,
            );

            Log::info(
                'Telegram driver listener started',
                [
                    'account_id' => $account->id,
                    'phone' => $account->phone,
                    'chat_count' => count($targetChatIds),
                    'target_chat_ids' => $targetChatIds,
                ],
            );

            return $targetChatIds;
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

    /**
     * @param list<int> $targetChatIds
     */
    private function notifyStarted(
        SimpleEventHandler $telegram,
        TelegramAccount $account,
        array $targetChatIds,
    ): void {
        /*
         * Left behind by the watchdog when the previous listener died. Only a
         * process holding the session can write to Saved Messages, so this is
         * where a restart gets reported - together with the start it caused.
         */
        $restart = TelegramRestartNotice::take();

        try {
            $telegram->messages->sendMessage([
                'peer' => 'me',

                'message' =>
                    (
                        $restart === null
                            ? "✅ <b>Telegram Driver Check Listener запущен</b>\n\n"
                            : "♻️ <b>Telegram Driver Check Listener перезапущен</b>\n\n"
                    )
                    . "📱 Аккаунт: "
                    . $this->escape(
                        (string) $account->phone,
                    )
                    . "\n"
                    . "🆔 Account ID: "
                    . $account->id
                    . "\n"
                    . $this->chatLines($targetChatIds)
                    . "📡 Статус: <b>RUNNING</b>\n"
                    . "🕐 Время: "
                    . now()->format('Y-m-d H:i:s')
                    . $this->restartDetails($restart),

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

    /**
     * The watched chats, as message lines.
     *
     * An empty watch list is reported rather than hidden: a listener running
     * against no chats looks identical to a broken one from the outside.
     *
     * @param list<int> $targetChatIds
     */
    private function chatLines(array $targetChatIds): string
    {
        if ($targetChatIds === []) {
            return "💬 Чаты: <b>не настроены</b>\n";
        }

        $chats = TelegramDriverCheckChat::query()
            ->whereIn('chat_id', $targetChatIds)
            ->orderBy('id')
            ->get();

        $lines = [
            '💬 Чаты (' . $chats->count() . '):',
        ];

        foreach ($chats as $chat) {
            $label = $this->escape($chat->label());
            $url = $chat->url();

            $lines[] = '• '
                . (
                    $url !== null
                        ? '<a href="' . $this->escape($url) . '">' . $label . '</a>'
                        : $label
                )
                . ' <code>'
                . (int) $chat->chat_id
                . '</code>';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Why the previous listener had to be replaced, as message lines.
     *
     * @param array<string, mixed>|null $restart
     */
    private function restartDetails(
        ?array $restart,
    ): string {
        if ($restart === null) {
            return '';
        }

        $lines = [
            '',
            '',
            '⚠️ <b>Причина перезапуска</b>',
            '• Событие: '
                . $this->escape(
                    (string) ($restart['reason'] ?? 'unknown'),
                ),
        ];

        if (isset($restart['exit_code'])) {
            $lines[] = '• Код выхода: '
                . (int) $restart['exit_code'];
        }

        if (isset($restart['uptime_seconds'])) {
            $lines[] = '• Проработал: '
                . $this->escape(
                    $this->humanUptime(
                        (float) $restart['uptime_seconds'],
                    ),
                );
        }

        if (isset($restart['restart_count'])) {
            $lines[] = '• Перезапуск №'
                . (int) $restart['restart_count'];
        }

        $detail = trim(
            (string) ($restart['detail'] ?? ''),
        );

        if ($detail !== '') {
            $lines[] = '• '
                . $this->escape(
                    Str::limit($detail, 300),
                );
        }

        return implode("\n", $lines);
    }

    private function humanUptime(
        float $seconds,
    ): string {
        if ($seconds < 60) {
            return round($seconds, 1) . ' сек';
        }

        if ($seconds < 3600) {
            return round($seconds / 60) . ' мин';
        }

        return round($seconds / 3600, 1) . ' ч';
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