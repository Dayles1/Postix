<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Application\Telegram\Services\TelegramAccountProcessService;
use App\Application\Telegram\Services\TelegramDriverMessageParser;
use App\Application\Telegram\Services\TelegramNameMatcher;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Enums\Telegram\TelegramAccountProcess as TelegramAccountProcessEnum;
use App\Jobs\Telegram\ResolveTelegramPhoneJob;
use App\Models\Driver\TelegramDriverCheck;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramResolvedPhone;
use danog\MadelineProto\EventHandler\Attributes\Cron;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Message as TelegramIncomingMessage;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;
use danog\MadelineProto\ParseMode;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramDriverCheckHandler extends SimpleEventHandler
{
    /**
     * ID группы, которую слушаем.
     */
    private ?int $targetChatId = null;

    /**
     * Инициализация listener.
     */
    public function onStart(): void
    {
        try {
            $account = $this->currentAccount();

            if (!$account) {
                Log::critical(
                    'TelegramDriverCheckHandler: listener account not found'
                );

                return;
            }

            $configuredAccountId = config(
                'services.telegram.driver_check_account_id'
            );

            if (
                $configuredAccountId === null
                || (int) $account->id !== (int) $configuredAccountId
            ) {
                Log::critical(
                    'TelegramDriverCheckHandler started with wrong account',
                    [
                        'current_account_id' => $account->id,
                        'expected_account_id' => $configuredAccountId,
                        'phone' => $account->phone,
                    ]
                );

                return;
            }

            $chatLink = trim(
                (string) config(
                    'services.telegram.driver_check_chat_link'
                )
            );

            if ($chatLink === '') {
                Log::critical(
                    'TelegramDriverCheckHandler: chat link is not configured'
                );

                return;
            }

            $this->targetChatId = (int) $this->getId($chatLink);

            if ($this->targetChatId === 0) {
                Log::critical(
                    'TelegramDriverCheckHandler: failed to resolve chat ID',
                    [
                        'chat_link' => $chatLink,
                    ]
                );

                return;
            }

            $account->update([
                'status' => 'running',
                'last_ping' => now(),
                'last_activity_at' => now(),
                'last_error' => null,
                'last_error_at' => null,
            ]);
            $this->notifyListenerStarted($account, $chatLink);

            Log::info(
                'TelegramDriverCheckHandler started',
                [
                    'account_id' => $account->id,
                    'phone' => $account->phone,
                    'chat_link' => $chatLink,
                    'target_chat_id' => $this->targetChatId,
                ]
            );
        } catch (Throwable $e) {
            Log::critical(
                'TelegramDriverCheckHandler onStart failed',
                [
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]
            );
        }
    }

    /**
     * Обработка входящих сообщений.
     */
    #[Handler]
    public function handleIncomingMessage(
        Incoming&TelegramIncomingMessage $message
    ): void {
        try {
            if ($this->targetChatId === null) {
                return;
            }

            $chatId = $message->chatId ?? null;

            if ($chatId === null) {
                return;
            }

            if ((int) $chatId !== $this->targetChatId) {
                return;
            }

            $telegramMessageId = (int) ($message->id ?? 0);

            if ($telegramMessageId <= 0) {
                Log::warning(
                    'Driver check message ignored: invalid Telegram message ID',
                    [
                        'chat_id' => $chatId,
                    ]
                );

                return;
            }

            $text = trim(
                (string) ($message->message ?? '')
            );

            if ($text === '') {
                Log::debug(
                    'Driver check message ignored: empty text',
                    [
                        'chat_id' => $chatId,
                        'telegram_message_id' => $telegramMessageId,
                    ]
                );

                return;
            }

            /*
             * Защита от повторной обработки одного Telegram message.
             */
            $exists = TelegramDriverCheck::query()
                ->where('telegram_chat_id', $chatId)
                ->where(
                    'telegram_message_id',
                    $telegramMessageId
                )
                ->exists();

            if ($exists) {
                Log::debug(
                    'Driver check message already exists',
                    [
                        'chat_id' => $chatId,
                        'telegram_message_id' => $telegramMessageId,
                    ]
                );

                return;
            }

            /*
             * Parse.
             */
            $parsed = app(
                TelegramDriverMessageParser::class
            )->parse($text);

            $phoneRaw = $parsed['phone_raw'] ?? null;
            $phoneNormalized = $parsed['phone_normalized'] ?? null;
            $driverName = $parsed['driver_name'] ?? null;

            /*
             * Создаём check сразу.
             *
             * Он является нашей очередью для pending/processing проверок.
             */
            $check = TelegramDriverCheck::create([
                'telegram_chat_id' => $chatId,
                'telegram_message_id' => $telegramMessageId,

                'message_text' => $text,

                'phone_raw' => $phoneRaw,
                'phone_normalized' => $phoneNormalized,

                'driver_name' => $driverName,

                'status' => TelegramDriverCheckStatus::Pending,

                'attempts' => 0,

                'telegram_raw' => $this->buildRawMessage(
                    $message
                ),
            ]);

            Log::info(
                'Telegram driver check created',
                [
                    'check_id' => $check->id,
                    'telegram_chat_id' => $check->telegram_chat_id,
                    'telegram_message_id' =>
                        $check->telegram_message_id,
                    'phone_raw' => $check->phone_raw,
                    'phone_normalized' =>
                        $check->phone_normalized,
                    'driver_name' => $check->driver_name,
                ]
            );

            /*
             * Нет номера.
             */
            if (!$phoneNormalized) {
                $check->update([
                    'status' => TelegramDriverCheckStatus::NotConfirmed,
                    'error_message' => 'Phone number is missing.',
                    'checked_at' => now(),
                ]);

                Log::warning(
                    'Driver check has no phone',
                    [
                        'check_id' => $check->id,
                        'telegram_message_id' =>
                            $telegramMessageId,
                    ]
                );

                return;
            }

            /*
             * =========================================================
             * 1. Ищем уже успешно resolved телефон в базе.
             * =========================================================
             */
            $resolvedPhone = TelegramResolvedPhone::query()
                ->where(
                    'phone_normalized',
                    $phoneNormalized
                )
                ->first();

            if ($resolvedPhone) {
                Log::info(
                    'Telegram resolved phone found in cache',
                    [
                        'check_id' => $check->id,
                        'phone' => $phoneNormalized,
                        'resolved_phone_id' => $resolvedPhone->id,
                        'telegram_user_id' =>
                            $resolvedPhone->telegram_user_id,
                    ]
                );

                $this->applyResolvedPhone(
                    $check,
                    $resolvedPhone
                );

                return;
            }

            /*
             * =========================================================
             * 2. Проверяем наличие resolver account.
             * =========================================================
             */
            $processService = app(
                TelegramAccountProcessService::class
            );

            $resolverAccount = $processService->findAvailableAccount(
                TelegramAccountProcessEnum::ResolverPhone
            );

            if (!$resolverAccount) {
                Log::warning(
                    'No resolver account available for driver check',
                    [
                        'check_id' => $check->id,
                        'phone' => $phoneNormalized,
                    ]
                );

                $this->notifyResolverAccountsExhausted();

                return;
            }

            /*
             * =========================================================
             * 3. Есть account → запускаем resolve job.
             * =========================================================
             */
            ResolveTelegramPhoneJob::dispatch(
                $check->id
            )->onQueue('telegram');

            Log::info(
                'ResolveTelegramPhoneJob dispatched',
                [
                    'check_id' => $check->id,
                    'phone' => $phoneNormalized,
                ]
            );
        } catch (Throwable $e) {
            Log::error(
                'Telegram driver check handler failed',
                [
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                    'chat_id' => $message->chatId ?? null,
                    'message_id' => $message->id ?? null,
                ]
            );
        }
    }

    /**
     * Apply cached Telegram resolve result to the current check.
     *
     * Telegram API is NOT called here.
     */
    private function applyResolvedPhone(
        TelegramDriverCheck $check,
        TelegramResolvedPhone $resolvedPhone,
    ): void {
        $telegramRaw = $resolvedPhone->telegram_raw ?? [];

        /*
         * Сохраняем name_match отдельно после matcher.
         */
        $telegramRaw['resolved_from_cache'] = true;
        $telegramRaw['resolved_phone_id'] = $resolvedPhone->id;

        $check->update([
            'telegram_user_id' =>
                $resolvedPhone->telegram_user_id,

            'telegram_username' =>
                $resolvedPhone->telegram_username,

            'telegram_first_name' =>
                $resolvedPhone->telegram_first_name,

            'telegram_last_name' =>
                $resolvedPhone->telegram_last_name,

            'telegram_raw' => $telegramRaw,

            'error_message' => null,
        ]);

        $match = app(
            TelegramNameMatcher::class
        )->match(
                $check->driver_name,
                $resolvedPhone->telegram_first_name,
                $resolvedPhone->telegram_last_name,
            );

        $telegramRaw['name_match'] = $match;

        $check->update([
            'telegram_raw' => $telegramRaw,

            'status' => ($match['matched'] ?? false)
                ? TelegramDriverCheckStatus::Confirmed
                : TelegramDriverCheckStatus::NotConfirmed,

            'checked_at' => now(),
        ]);

        Log::info(
            'Driver check completed from resolved phone cache',
            [
                'check_id' => $check->id,
                'phone' => $check->phone_normalized,
                'resolved_phone_id' => $resolvedPhone->id,
                'matched' => $match['matched'] ?? false,
                'score' => $match['score'] ?? 0,
                'level' => $match['level'] ?? null,
            ]
        );
    }

    /**
     * Send notification when resolver accounts are exhausted.
     *
     * Cooldown prevents notification spam when many driver messages
     * arrive while no resolver account is available.
     */
    private function notifyResolverAccountsExhausted(): void
    {
        $chatId = config(
            'services.telegram.driver_check_notification_chat_id'
        );

        if ($chatId === null || $chatId === '') {
            Log::warning(
                'Resolver account exhaustion notification chat is not configured'
            );

            return;
        }

        /*
         * One notification per 10 minutes.
         *
         * The key is shared between all incoming driver messages.
         */
        $lock = Cache::add(
            'telegram:driver-check:resolver-accounts-exhausted-notified',
            true,
            now()->addMinutes(10)
        );

        if (!$lock) {
            return;
        }

        try {
            $this->messages->sendMessage([
                'peer' => (int) $chatId,
                'message' =>
                    "⚠️ <b>АККАУНТЫ ЗАКОНЧИЛИСЬ</b>\n\n"
                    . "Нет доступных Telegram-аккаунтов "
                    . "для проверки водителей.\n\n"
                    . "Необходимо добавить новый "
                    . "resolver-аккаунт.",
                'parse_mode' => ParseMode::HTML,
                'no_webpage' => true,
            ]);

            Log::warning(
                'Resolver account exhaustion notification sent',
                [
                    'notification_chat_id' => $chatId,
                ]
            );
        } catch (Throwable $e) {
            /*
             * Уведомление не должно ломать обработку входящего
             * сообщения.
             */
            Log::error(
                'Failed to send resolver account exhaustion notification',
                [
                    'notification_chat_id' => $chatId,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]
            );
        }
    }

    /**
     * Каждую секунду ищем завершённые проверки,
     * которые ещё не отправили результат в Telegram.
     */
    #[Cron(period: 1.0)]
    public function sendCompletedResults(): void
    {
        try {
            if ($this->targetChatId === null) {
                return;
            }

            $checks = TelegramDriverCheck::query()
                ->where(
                    'telegram_chat_id',
                    $this->targetChatId
                )
                ->whereIn(
                    'status',
                    [
                        TelegramDriverCheckStatus::Confirmed,
                        TelegramDriverCheckStatus::NotConfirmed,
                    ]
                )
                ->whereNull('reported_at')
                ->orderBy('id')
                ->limit(10)
                ->get();

            if ($checks->isEmpty()) {
                return;
            }

            foreach ($checks as $check) {
                $this->sendCheckResult($check);
            }
        } catch (Throwable $e) {
            Log::error(
                'Failed to process completed Telegram driver checks',
                [
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]
            );
        }
    }

    /**
     * Отправить результат reply на исходное сообщение.
     */
    private function sendCheckResult(
        TelegramDriverCheck $check
    ): void {
        try {
            $message = $this->buildCheckResultMessage(
                $check
            );

            $this->messages->sendMessage([
                'peer' => $check->telegram_chat_id,

                'reply_to' => [
                    '_' => 'inputReplyToMessage',
                    'reply_to_msg_id' =>
                        $check->telegram_message_id,
                ],

                'message' => $message,

                'parse_mode' => ParseMode::HTML,

                'no_webpage' => true,
            ]);

            $check->update([
                'reported_at' => now(),
            ]);

            Log::info(
                'Telegram driver check result sent',
                [
                    'check_id' => $check->id,
                    'chat_id' => $check->telegram_chat_id,
                    'reply_to_message_id' =>
                        $check->telegram_message_id,
                    'status' => $check->status?->value,
                ]
            );
        } catch (Throwable $e) {
            Log::error(
                'Telegram driver check result send failed',
                [
                    'check_id' => $check->id,
                    'chat_id' => $check->telegram_chat_id,
                    'message_id' => $check->telegram_message_id,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]
            );
        }
    }

    /**
     * Формирует человекочитаемый результат на узбекском.
     */
    private function buildCheckResultMessage(
        TelegramDriverCheck $check
    ): string {
        $isConfirmed =
            $check->status === TelegramDriverCheckStatus::Confirmed;

        $telegramFullName = trim(
            implode(
                ' ',
                array_filter([
                    $check->telegram_first_name,
                    $check->telegram_last_name,
                ])
            )
        );

        $telegramFullName = $telegramFullName !== ''
            ? $telegramFullName
            : null;

        $match = data_get(
            $check->telegram_raw,
            'name_match'
        );

        $lines = [];

        $lines[] = $isConfirmed
            ? '✅ <b>TAQQOSLASH TASDIQLANDI</b>'
            : '❌ <b>TAQQOSLASH TASDIQLANMADI</b>';

        $lines[] = '';

        $lines[] =
            '<b>📱 Telefon:</b> '
            . $this->escapeHtml(
                $check->phone_normalized ?? '-'
            );

        $lines[] = '';

        $lines[] =
            '<b>👤 Haydovchi maʼlumoti:</b>';

        $lines[] =
            'Berilgan ism: '
            . $this->escapeHtml(
                $check->driver_name ?? '-'
            );

        $lines[] = '';

        $lines[] =
            '<b>📲 Telegram maʼlumoti:</b>';

        $lines[] =
            'Ism: '
            . $this->escapeHtml(
                $telegramFullName ?? '-'
            );

        if ($check->telegram_username) {
            $lines[] =
                'Username: @'
                . $this->escapeHtml(
                    ltrim(
                        $check->telegram_username,
                        '@'
                    )
                );
        }

        if ($check->telegram_user_id) {
            $lines[] =
                'User ID: '
                . $check->telegram_user_id;
        }

        if (is_array($match)) {
            $lines[] = '';

            $lines[] =
                '<b>🔎 Ism taqqoslash:</b>';

            $lines[] =
                'Berilgan: '
                . $this->escapeHtml(
                    (string) (
                        $match['expected_name']
                        ?? $check->driver_name
                        ?? '-'
                    )
                );

            $lines[] =
                'Telegram: '
                . $this->escapeHtml(
                    (string) (
                        $match['telegram_name']
                        ?? $telegramFullName
                        ?? '-'
                    )
                );

            $lines[] = '';

            $score = (float) (
                $match['score'] ?? 0
            );

            $level = (string) (
                $match['level'] ?? 'no_data'
            );

            $matchedTokens = is_array(
                $match['matched_tokens'] ?? null
            )
                ? $match['matched_tokens']
                : [];

            $possibleTokens = is_array(
                $match['possible_tokens'] ?? null
            )
                ? $match['possible_tokens']
                : [];

            if ($matchedTokens !== []) {
                $lines[] =
                    '<b>✅ Mos kelgan qismlar:</b>';

                foreach ($matchedTokens as $token) {
                    $expected = (string) (
                        $token['expected'] ?? ''
                    );

                    $actual = (string) (
                        $token['actual'] ?? ''
                    );

                    $tokenScore = $token['score'] ?? 0;

                    if (
                        $expected === ''
                        && $actual === ''
                    ) {
                        continue;
                    }

                    $lines[] =
                        '• '
                        . $this->escapeHtml(
                            $expected
                        )
                        . ' → '
                        . $this->escapeHtml(
                            $actual
                        )
                        . ' ('
                        . $tokenScore
                        . '%)';
                }

                $lines[] = '';
            }

            $matchedExpected = collect(
                $matchedTokens
            )
                ->pluck('expected')
                ->map(
                    fn($value): string => mb_strtolower(
                        trim((string) $value)
                    )
                )
                ->filter()
                ->values()
                ->all();

            $unmatched = [];

            foreach ($possibleTokens as $token) {
                $expected = trim(
                    (string) (
                        $token['expected'] ?? ''
                    )
                );

                if ($expected === '') {
                    continue;
                }

                $normalized = mb_strtolower(
                    $expected
                );

                if (
                    !in_array(
                        $normalized,
                        $matchedExpected,
                        true
                    )
                ) {
                    $unmatched[] = $expected;
                }
            }

            $unmatched = array_values(
                array_unique($unmatched)
            );

            if ($unmatched !== []) {
                $lines[] =
                    '<b>⚠️ Mos kelmagan qismlar:</b>';

                foreach ($unmatched as $token) {
                    $lines[] =
                        '• '
                        . $this->escapeHtml($token);
                }

                $lines[] = '';
            }

            $lines[] =
                '<b>📊 Yakuniy moslik:</b> '
                . $score
                . '/100';

            $lines[] =
                '<b>🔐 Ishonchlilik:</b> '
                . $this->translateMatchLevel(
                    $level
                );

            $lines[] = '';

            if ($isConfirmed) {
                $lines[] =
                    '✅ <b>Xulosa:</b> Telegramdagi ism '
                    . 'haydovchi xabaridagi ism bilan '
                    . 'yetarli darajada mos keldi.';
            } else {
                $lines[] =
                    '❌ <b>Xulosa:</b> Telegramdagi ism '
                    . 'haydovchi xabaridagi ism bilan '
                    . 'yetarli darajada mos kelmadi.';
            }
        } else {
            $lines[] = '';

            $lines[] =
                'ℹ️ <b>Taqqoslash:</b> '
                . 'ism bo‘yicha yetarli maʼlumot topilmadi.';
        }

        if ($check->error_message) {
            $lines[] = '';

            $lines[] =
                '<b>⚠️ Xatolik:</b>';

            $lines[] =
                $this->escapeHtml(
                    mb_substr(
                        $check->error_message,
                        0,
                        1000
                    )
                );
        }

        return implode(
            "\n",
            $lines
        );
    }

    /**
     * Перевод confidence level на узбекский.
     */
    private function translateMatchLevel(
        string $level
    ): string {
        return match ($level) {
            'exact' =>
            'aniq mos',

            'very_high' =>
            'juda yuqori',

            'high' =>
            'yuqori',

            'medium' =>
            'o‘rtacha',

            'low' =>
            'past',

            'very_low' =>
            'juda past',

            'no_data' =>
            'maʼlumot yetarli emas',

            'none' =>
            'moslik topilmadi',

            default =>
            $level,
        };
    }

    /**
     * Только listener account из ENV.
     */
    private function currentAccount(): ?TelegramAccount
    {
        $accountId = config(
            'services.telegram.driver_check_account_id'
        );

        if (
            $accountId === null
            || $accountId === ''
        ) {
            return null;
        }

        return TelegramAccount::query()
            ->whereKey((int) $accountId)
            ->where('is_authorized', true)
            ->first();
    }

    /**
     * Сохраняем нужные данные входящего Telegram update.
     */
    private function buildRawMessage(
        TelegramIncomingMessage $message
    ): array {
        return [
            'class' => $message::class,

            'id' => $message->id ?? null,

            'chat_id' => $message->chatId ?? null,

            'sender_id' => $message->senderId ?? null,

            'message' => $message->message ?? null,

            'date' => $message->date ?? null,

            'reply_to_msg_id' =>
                $message->replyToMsgId ?? null,

            'reply_to_top_id' =>
                $message->replyToTopId ?? null,
        ];
    }

    /**
     * HTML escaping для Telegram parse_mode=HTML.
     */
    private function escapeHtml(
        string $value
    ): string {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
    /**
     * Уведомляет listener-аккаунт о успешном запуске.
     */
    private function notifyListenerStarted(
        TelegramAccount $account,
        string $chatLink
    ): void {
        try {
            $this->messages->sendMessage([
                'peer' => 'me',
                'message' =>
                    "✅ <b>Telegram Driver Check Listener запущен</b>\n\n"
                    . "📱 Аккаунт: "
                    . $this->escapeHtml(
                        (string) $account->phone
                    )
                    . "\n"
                    . "🆔 Account ID: "
                    . $account->id
                    . "\n"
                    . "💬 Чат: "
                    . $this->escapeHtml($chatLink)
                    . "\n"
                    . "📡 Статус: <b>RUNNING</b>\n"
                    . "🕐 Время: "
                    . now()->format('Y-m-d H:i:s'),
                'parse_mode' => ParseMode::HTML,
                'no_webpage' => true,
            ]);

            Log::info(
                'TelegramDriverCheckHandler startup notification sent',
                [
                    'account_id' => $account->id,
                    'phone' => $account->phone,
                ]
            );
        } catch (Throwable $e) {
            /*
             * Ошибка уведомления не должна ломать listener.
             */
            Log::error(
                'TelegramDriverCheckHandler startup notification failed',
                [
                    'account_id' => $account->id,
                    'phone' => $account->phone,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]
            );
        }
    }
}