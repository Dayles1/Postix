<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Jobs\Telegram\SyncDriverCheckBotMessage;
use App\Models\Driver\TelegramDriverCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\BotsManager;
use Throwable;

/**
 * The bot's side of a driver check: one message under the report, with
 * the buttons that let the group overrule the system.
 *
 * The report itself belongs to the MadelineProto account and stays the
 * record of what the system found. The bot exists because a user
 * account cannot carry inline buttons: under each report it posts the
 * status once more, with
 *
 *   ✅ Подтвердить     when the check did not confirm the driver,
 *   ❌ Отклонить       when it did,
 *   🔄 Перепроверить   when it failed for a technical reason
 *                      (a cancelled operation, no free account, ...).
 *
 * Anyone in the group may press them - see HandleDriverCheckBotCallback.
 * The message is edited in place whenever the check changes, so the
 * buttons always match the verdict they would change.
 */
final class DriverCheckBot
{
    public const ACTION_CONFIRM = 'confirm';

    public const ACTION_REJECT = 'reject';

    public const ACTION_RECHECK = 'recheck';

    private const CALLBACK_PREFIX = 'dc';

    private ?Api $api = null;

    private ?BotsManager $bots = null;

    private bool $missingSdkReported = false;

    /**
     * Without a token the bot is simply absent: reports go out as they
     * always did, only without buttons.
     */
    public function enabled(): bool
    {
        $bots = $this->bots();

        if ($bots === null) {
            return false;
        }

        $token = $bots->getBotConfig()['token'] ?? null;

        return is_string($token)
            && trim($token) !== ''
            && $token !== 'YOUR-BOT-TOKEN';
    }

    public function queueSync(TelegramDriverCheck $check): void
    {
        if (! $this->enabled()) {
            return;
        }

        SyncDriverCheckBotMessage::dispatch($check->id)
            ->onQueue('telegram');
    }

    /**
     * Posts or edits the bot message right away, from whoever just
     * changed the check - normally the listener, straight after it sent
     * the report, so the buttons appear under it at once instead of
     * whenever the queue worker gets to it.
     *
     * The report is already in the group by then and must never be undone
     * by the bot: any failure here (Telegram down, lock busy) hands the
     * message to the queue, which retries it.
     */
    public function syncNow(TelegramDriverCheck $check): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $this->sync($check);
        } catch (Throwable $e) {
            Log::warning(
                'Driver check bot message not sent right away, queued instead',
                [
                    'check_id' => $check->id,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ],
            );

            $this->queueSync($check);
        }
    }

    /**
     * Posts the bot message under the report, or edits it to match the
     * check.
     */
    public function sync(TelegramDriverCheck $check): void
    {
        if (! $this->enabled()) {
            return;
        }

        /*
         * The listener, the queue and a button press can all reach one
         * check at the same moment; two of them seeing no bot message yet
         * would both post one. The row is re-read inside the lock, so the
         * second one finds the first one's message and edits it.
         */
        Cache::lock('driver-check-bot:' . $check->id, 30)->block(10, function () use ($check): void {
            $fresh = $check->fresh() ?? $check;

            $this->syncLocked($fresh);

            // The caller keeps working with its own copy of the row.
            if ($fresh !== $check) {
                $check->forceFill(['bot_message_id' => $fresh->bot_message_id]);
                $check->syncOriginalAttribute('bot_message_id');
            }
        });
    }

    private function syncLocked(TelegramDriverCheck $check): void
    {
        if ($check->reported_at === null && $check->bot_message_id === null) {
            // Nothing in the group to put buttons under yet.
            return;
        }

        $params = [
            'chat_id' => $check->telegram_chat_id,
            'text' => $this->text($check),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => json_encode(
                ['inline_keyboard' => $this->keyboard($check)],
                JSON_THROW_ON_ERROR,
            ),
        ];

        if ($check->bot_message_id !== null) {
            try {
                $this->api()->editMessageText($params + [
                    'message_id' => $check->bot_message_id,
                ]);

                return;
            } catch (Throwable $e) {
                if ($this->isNotModified($e)) {
                    return;
                }

                if (! $this->isGone($e)) {
                    throw $e;
                }

                Log::warning(
                    'Driver check bot message is gone, posting a new one',
                    [
                        'check_id' => $check->id,
                        'bot_message_id' => $check->bot_message_id,
                    ],
                );
            }
        }

        $sent = $this->api()->sendMessage($params + [
            'reply_parameters' => json_encode([
                'message_id' => $check->report_message_id
                    ?? $check->report_reply_to_message_id
                    ?? $check->telegram_message_id,
                /*
                 * A report id the bot cannot see (a basic group numbers
                 * messages per member) must not cost the buttons.
                 */
                'allow_sending_without_reply' => true,
            ], JSON_THROW_ON_ERROR),
        ]);

        $check->forceFill([
            'bot_message_id' => (int) $sent->get('message_id'),
        ])->save();
    }

    /**
     * Turns a bot message that no longer speaks for its check into a
     * plain note, buttons removed.
     */
    public function retire(int $chatId, int $messageId, string $note): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $this->api()->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => []], JSON_THROW_ON_ERROR),
            ]);
        } catch (Throwable $e) {
            if (! $this->isNotModified($e) && ! $this->isGone($e)) {
                throw $e;
            }
        }
    }

    /**
     * Replies to someone who wrote to the bot directly. Nothing to do
     * with driver checks: it is how anyone can see at a glance that the
     * webhook reaches the server.
     */
    public function greet(int $chatId, string $firstName): void
    {
        if (! $this->enabled()) {
            return;
        }

        $name = trim($firstName) !== '' ? ', ' . $this->escape(trim($firstName)) : '';

        $this->api()->sendMessage([
            'chat_id' => $chatId,
            'text' => "Salom{$name}! 👋\nBot ishlayapti.",
            'parse_mode' => 'HTML',
        ]);
    }

    public function answer(string $callbackQueryId, string $text, bool $alert = false): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $this->api()->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => $alert,
            ]);
        } catch (Throwable $e) {
            // An unanswered button only spins a little longer.
            Log::warning(
                'Driver check bot could not answer a button press',
                ['error' => $e->getMessage()],
            );
        }
    }

    public static function callbackData(string $action, int $checkId): string
    {
        return self::CALLBACK_PREFIX . ':' . $action . ':' . $checkId;
    }

    /**
     * @return array{action: string, check_id: int}|null
     */
    public static function parseCallbackData(string $data): ?array
    {
        $actions = implode('|', [
            self::ACTION_CONFIRM,
            self::ACTION_REJECT,
            self::ACTION_RECHECK,
        ]);

        if (! preg_match('/^' . self::CALLBACK_PREFIX . ':(' . $actions . '):(\d+)$/', $data, $matches)) {
            return null;
        }

        return [
            'action' => $matches[1],
            'check_id' => (int) $matches[2],
        ];
    }

    public function text(TelegramDriverCheck $check): string
    {
        $lines = [
            '🤖 <b>Проверка #' . $check->id . '</b>'
                . ($check->driver_name ? ' · ' . $this->escape($check->driver_name) : ''),
            '<b>Статус:</b> ' . $this->statusLabel($check->status),
        ];

        if ($check->isManuallyDecided()) {
            $lines[] = ($check->status === TelegramDriverCheckStatus::Confirmed
                    ? 'Подтвердил: '
                    : 'Отклонил: ')
                . $this->escape((string) $check->manual_by_name)
                . ' · ' . $check->manual_at?->format('d.m.Y H:i');
        }

        if ($check->hasTechnicalFailure() && $check->error_message) {
            $lines[] = '⚠️ Ошибка при проверке: '
                . $this->escape(mb_substr($check->error_message, 0, 300));
        }

        if (! $check->status?->isFinal()) {
            $lines[] = '⏳ Идёт повторная проверка…';
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<list<array{text: string, callback_data: string}>>
     */
    public function keyboard(TelegramDriverCheck $check): array
    {
        $row = [];

        if ($check->status === TelegramDriverCheckStatus::NotConfirmed) {
            $row[] = $this->button('✅ Подтвердить', self::ACTION_CONFIRM, $check);

            if ($check->hasTechnicalFailure()) {
                $row[] = $this->button('🔄 Перепроверить', self::ACTION_RECHECK, $check);
            }
        }

        if ($check->status === TelegramDriverCheckStatus::Confirmed) {
            $row[] = $this->button('❌ Отклонить', self::ACTION_REJECT, $check);
        }

        return $row === [] ? [] : [$row];
    }

    /**
     * @return array{text: string, callback_data: string}
     */
    private function button(string $text, string $action, TelegramDriverCheck $check): array
    {
        return [
            'text' => $text,
            'callback_data' => self::callbackData($action, $check->id),
        ];
    }

    private function statusLabel(?TelegramDriverCheckStatus $status): string
    {
        return match ($status) {
            TelegramDriverCheckStatus::Confirmed => '✅ ПОДТВЕРЖДЕНО',
            TelegramDriverCheckStatus::NotConfirmed => '❌ НЕ ПОДТВЕРЖДЕНО',
            TelegramDriverCheckStatus::Pending => '⏳ ОЖИДАЕТ ПРОВЕРКИ',
            TelegramDriverCheckStatus::Processing => '🔄 ПРОВЕРЯЕТСЯ',
            default => '❓ НЕИЗВЕСТНЫЙ СТАТУС',
        };
    }

    private function isNotModified(Throwable $e): bool
    {
        return str_contains(mb_strtolower($e->getMessage()), 'message is not modified');
    }

    private function isGone(Throwable $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return str_contains($message, 'message to edit not found')
            || str_contains($message, "message can't be edited");
    }

    private function api(): Api
    {
        return $this->api ??= $this->bots()->bot();
    }

    /**
     * The SDK, resolved only when the bot is actually used.
     *
     * The reporter depends on this class, so resolving the SDK up front
     * meant a server without irazasyed/telegram-bot-sdk installed could
     * not report a single check. Without it the bot is simply off.
     */
    private function bots(): ?BotsManager
    {
        if ($this->bots !== null) {
            return $this->bots;
        }

        try {
            return $this->bots = app(BotsManager::class);
        } catch (Throwable $e) {
            if (! $this->missingSdkReported) {
                $this->missingSdkReported = true;

                Log::error(
                    'Driver check bot is off: the Telegram Bot SDK is not available (run composer install)',
                    [
                        'error' => $e->getMessage(),
                        'exception' => $e::class,
                    ],
                );
            }

            return null;
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
