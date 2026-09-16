<?php

namespace App\Application\Telegram\Services;

use App\Applications\Telegram\Services\MadelineService;
use App\Applications\Telegram\Services\TelegramContactResolver;
use App\Applications\Telegram\Services\TelegramDriverCheckLogger;
use App\Applications\Telegram\Services\TelegramDriverCheckReporter;
use App\Applications\Telegram\Services\TelegramDriverMessageParser;
use App\Applications\Telegram\Services\TelegramNameMatcher;
use App\Enums\TelegramDriverCheckReason;
use App\Enums\TelegramDriverCheckStatus;
use App\Models\Telegram\TelegramAccount;
use App\Models\Driver\TelegramDriverCheck;
use Throwable;

class TelegramDriverCheckService
{
    public function __construct(
        private readonly MadelineService $madelineService,
        private readonly TelegramDriverMessageParser $parser,
        private readonly TelegramContactResolver $resolver,
        private readonly TelegramNameMatcher $nameMatcher,
        private readonly TelegramDriverCheckLogger $logger,
        private readonly TelegramDriverCheckReporter $reporter,
    ) {
    }

    public function process(
        array $message,
        TelegramAccount $account,
        ?TelegramDriverCheck $existingCheck = null,
    ): TelegramDriverCheck {
        $messageText = (string) (
            $message['message']
            ?? ''
        );

        $messageId = (int) (
            $message['id']
            ?? $message['message_id']
            ?? 0
        );

        $chatId = $this->extractChatId(
            $message
        );

        /*
         * Parse source message.
         */
        $parsed = $this->parser->parse(
            $messageText
        );

        /*
         * Existing pending check?
         *
         * Reuse the same database record.
         */
        $check = $existingCheck;

        if (! $check) {
            $check = TelegramDriverCheck::create([
                'telegram_chat_id' => $chatId,
                'telegram_message_id' => $messageId,

                'message_text' => $messageText,

                'phone_raw' => $parsed['phone_raw'],
                'phone_normalized' => $parsed['phone_normalized'],

                'driver_name' => $parsed['driver_name'],

                'status' =>
                    TelegramDriverCheckStatus::PENDING,

                'attempts' => 0,
            ]);

            $this->logger->created($check);
        } else {
            $check->update([
                'message_text' => $messageText,

                'phone_raw' => $parsed['phone_raw'],
                'phone_normalized' =>
                    $parsed['phone_normalized'],

                'driver_name' =>
                    $parsed['driver_name'],

                'status' =>
                    TelegramDriverCheckStatus::PENDING,

                'error_message' => null,
            ]);

            $check->refresh();
        }

        /*
         * No phone.
         */
        if (! $check->phone_normalized) {
    $check->update([
        'status' =>
            TelegramDriverCheckStatus::NOT_CONFIRMED,

        'reason' =>
            TelegramDriverCheckReason::PHONE_NOT_FOUND_IN_MESSAGE,

        'attempts' =>
            $check->attempts + 1,

        'checked_at' => now(),
    ]);

    $check->refresh();

    return $check;
}

        try {
            $check->increment('attempts');
            $check->refresh();

            /*
             * Start Madeline.
             */
            $api = $this->madelineService->for(
                $account
            );

            if (! $api) {
                $check->update([
                    'status' =>
                        TelegramDriverCheckStatus::NOT_CONFIRMED,

                    'reason' =>
                        TelegramDriverCheckReason::TELEGRAM_ERROR,

                    'error_message' =>
                        'Unable to start MadelineProto.',

                    'checked_at' => now(),
                ]);

                $check->refresh();

                return $check;
            }

            /*
             * Resolve phone.
             */
            $this->logger->resolveStarted(
                $check
            );

            $resolved = $this->resolver->resolve(
                $api,
                $check->phone_normalized
            );

            if (! $resolved['success']) {
                $this->applyResolveFailure(
                    $check,
                    $resolved
                );

                $check->refresh();

                $this->reporter->send(
                    $api,
                    $check,
                    null,
                );

                return $check;
            }

            $user = $resolved['user'];

            $this->logger->resolved(
                $check,
                $user
            );

            /*
             * Save Telegram user.
             */
            $check->update([
                'telegram_user_id' =>
                    $user['id'] ?? null,

                'telegram_username' =>
                    $user['username'] ?? null,

                'telegram_first_name' =>
                    $user['first_name'] ?? null,

                'telegram_last_name' =>
                    $user['last_name'] ?? null,

                'telegram_raw' =>
                    $resolved['raw'] ?? null,

                'error_message' => null,
            ]);

            /*
             * Name comparison.
             */
            $match = $this->nameMatcher->match(
                $check->driver_name,
                $user['first_name'] ?? null,
                $user['last_name'] ?? null,
            );

            if ($match['matched']) {
                $this->logger->nameMatched(
                    $check,
                    $match
                );

                $check->update([
                    'status' =>
                        TelegramDriverCheckStatus::CONFIRMED,

                    'reason' => null,

                    'checked_at' => now(),
                ]);
            } else {
                $this->logger->nameMismatch(
                    $check,
                    $match
                );

                $check->update([
                    'status' =>
                        TelegramDriverCheckStatus::NOT_CONFIRMED,

                    'reason' =>
                        TelegramDriverCheckReason::NAME_MISMATCH,

                    'checked_at' => now(),
                ]);
            }

            $check->refresh();

            /*
             * Send result to your Telegram.
             */
            $this->reporter->send(
                $api,
                $check,
                $match,
            );

            return $check;
        } catch (Throwable $e) {
            $this->logger->failed(
                $check,
                $e
            );

            $check->update([
                'status' =>
                    TelegramDriverCheckStatus::NOT_CONFIRMED,

                'reason' =>
                    TelegramDriverCheckReason::TELEGRAM_ERROR,

                'error_message' =>
                    $e->getMessage(),

                'checked_at' => now(),
            ]);

            $check->refresh();

            return $check;
        }
    }

    private function applyResolveFailure(
        TelegramDriverCheck $check,
        array $resolved,
    ): void {
        $reason = match (
            $resolved['reason'] ?? null
        ) {
            'telegram_not_registered'
                => TelegramDriverCheckReason::TELEGRAM_NOT_REGISTERED,

            'telegram_resolve_flood'
                => TelegramDriverCheckReason::TELEGRAM_RESOLVE_FLOOD,

            default
                => TelegramDriverCheckReason::TELEGRAM_ERROR,
        };

        $check->update([
            'status' =>
                TelegramDriverCheckStatus::NOT_CONFIRMED,

            'reason' => $reason,

            'telegram_raw' =>
                $resolved['raw'] ?? null,

            'error_message' =>
                $resolved['error_message'] ?? null,

            'checked_at' => now(),
        ]);
    }

    private function extractChatId(
        array $message
    ): int {
        return (int) (
            $message['chat_id']
            ?? $message['peer_id']['channel_id']
            ?? $message['peer_id']['chat_id']
            ?? $message['peer_id']['user_id']
            ?? 0
        );
    }
}