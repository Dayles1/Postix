<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\DriverCheckBot;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Models\Driver\TelegramDriverCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A button under a driver check was pressed in the group.
 *
 * Anyone in the group may press them: the group is the operators' own
 * room, and a wrong verdict is fixed fastest by whoever notices it. What
 * is recorded is who did it, next to the system's verdict rather than in
 * place of it (see TelegramDriverCheck::recordManualDecision()).
 *
 * The bot message is refreshed right here, so the person who pressed
 * sees the result at once. The group report belongs to the MadelineProto
 * account and is edited by the listener, which learns about the change
 * from report_dirty_at.
 */
final class HandleDriverCheckBotCallback
{
    public function __construct(
        private readonly DriverCheckBot $bot,
        private readonly RerunTelegramDriverCheck $rerun,
    ) {
    }

    /**
     * @param  array<string, mixed>  $callback  the Bot API CallbackQuery
     */
    public function execute(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $parsed = DriverCheckBot::parseCallbackData((string) ($callback['data'] ?? ''));

        if ($callbackId === '' || $parsed === null) {
            return;
        }

        $chatId = (int) data_get($callback, 'message.chat.id', 0);
        $messageId = (int) data_get($callback, 'message.message_id', 0);
        $by = $this->presserName((array) ($callback['from'] ?? []));
        $byId = isset($callback['from']['id']) ? (int) $callback['from']['id'] : null;

        [$answer, $check] = DB::transaction(function () use ($parsed, $chatId, $messageId, $by, $byId): array {
            $check = TelegramDriverCheck::query()
                ->lockForUpdate()
                ->find($parsed['check_id']);

            if (
                $check === null
                || (int) $check->telegram_chat_id !== $chatId
                || ($check->bot_message_id !== null && (int) $check->bot_message_id !== $messageId)
            ) {
                return ['Сообщение устарело', null];
            }

            return [
                match ($parsed['action']) {
                    DriverCheckBot::ACTION_CONFIRM => $this->decide($check, TelegramDriverCheckStatus::Confirmed, $by, $byId),
                    DriverCheckBot::ACTION_REJECT => $this->decide($check, TelegramDriverCheckStatus::NotConfirmed, $by, $byId),
                    DriverCheckBot::ACTION_RECHECK => $this->recheck($check, $by),
                },
                $check,
            ];
        });

        $this->bot->answer($callbackId, $answer);

        if ($check === null) {
            return;
        }

        Log::info(
            'Driver check button pressed',
            [
                'check_id' => $check->id,
                'action' => $parsed['action'],
                'by' => $by,
                'by_telegram_id' => $byId,
                'status' => $check->status?->value,
                'answer' => $answer,
            ],
        );

        $this->bot->sync($check->fresh() ?? $check);
    }

    private function decide(
        TelegramDriverCheck $check,
        TelegramDriverCheckStatus $status,
        string $by,
        ?int $byId,
    ): string {
        if (! $check->status?->isFinal()) {
            return 'Проверка ещё идёт';
        }

        if ($check->status === $status) {
            return $status === TelegramDriverCheckStatus::Confirmed
                ? 'Уже подтверждено'
                : 'Уже отмечено как не подтверждено';
        }

        $raw = is_array($check->telegram_raw) ? $check->telegram_raw : [];
        $raw['manual_log'][] = [
            'at' => now()->toISOString(),
            'status' => $status->value,
            'from' => $check->status->value,
            'by' => $by,
            'by_telegram_id' => $byId,
        ];

        $check->telegram_raw = $raw;
        $check->recordManualDecision($status, $byId, $by);

        $check->driver?->update([
            'status' => $status->value,
        ]);

        return $status === TelegramDriverCheckStatus::Confirmed
            ? '✅ Подтверждено'
            : '❌ Отмечено как не подтверждено';
    }

    private function recheck(TelegramDriverCheck $check, string $by): string
    {
        if (! $check->status?->isFinal()) {
            return 'Проверка уже идёт';
        }

        if (! $check->hasTechnicalFailure()) {
            return 'Перепроверка нужна только после технической ошибки';
        }

        $this->rerun->execute(
            check: $check,
            trigger: RerunTelegramDriverCheck::TRIGGER_RECHECK,
            by: $by,
        );

        return '🔄 Повторная проверка запущена';
    }

    /**
     * "Иван Петров (@ivan)", or whatever part of that the user has.
     *
     * @param  array<string, mixed>  $from
     */
    private function presserName(array $from): string
    {
        $name = trim(
            ((string) ($from['first_name'] ?? '')) . ' ' . ((string) ($from['last_name'] ?? '')),
        );

        $username = trim((string) ($from['username'] ?? ''));

        if ($username !== '') {
            $name = $name !== '' ? "{$name} (@{$username})" : "@{$username}";
        }

        if ($name === '') {
            $name = isset($from['id']) ? 'id ' . $from['id'] : 'неизвестный пользователь';
        }

        return $name;
    }
}
