<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Driver\TelegramDriverCheck;
use App\Models\Telegram\OperationUser;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers a copy of the group report into the operator's private chat.
 *
 * The group reply stays the source of truth: this is a best-effort copy, so a
 * failure here must never break the reporting flow or leave the check
 * unreported. Every outcome is recorded on the operator row instead, which is
 * what the operators page shows.
 */
final class TelegramOperatorNotifier
{
    /**
     * Keep the stored failure short enough to be readable in a table cell.
     */
    private const ERROR_LIMIT = 500;

    public function notify(
        SimpleEventHandler $telegram,
        TelegramDriverCheck $check,
        string $report,
    ): void {
        try {
            $operator = $check->operationUser;

            if (! $operator instanceof OperationUser) {
                return;
            }

            if (! $operator->canReceiveDirectMessages()) {
                $this->logSkipped($check, $operator);

                return;
            }

            $this->deliver(
                telegram: $telegram,
                check: $check,
                operator: $operator,
                report: $report,
            );
        } catch (Throwable $e) {
            /*
             * The caller has already delivered the group report; a broken
             * copy must not bubble up and mark the check as failed.
             */
            Log::error(
                'Operator direct message failed unexpectedly',
                [
                    'check_id' => $check->id,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ],
            );
        }
    }

    private function deliver(
        SimpleEventHandler $telegram,
        TelegramDriverCheck $check,
        OperationUser $operator,
        string $report,
    ): void {
        $message = $this->buildMessage(
            check: $check,
            report: $report,
        );

        $errors = [];

        foreach ($operator->telegramPeerCandidates() as $peer) {
            try {
                $telegram->messages->sendMessage([
                    'peer' => $peer,
                    'message' => $message,
                    'parse_mode' => 'html',
                    'no_webpage' => true,
                ]);

                $operator->forceFill([
                    'dm_last_sent_at' => now(),
                    'dm_last_error' => null,
                ])->save();

                Log::info(
                    'Operator direct message sent',
                    [
                        'check_id' => $check->id,
                        'operation_user_id' => $operator->id,
                        'peer' => $peer,
                    ],
                );

                return;
            } catch (Throwable $e) {
                /*
                 * A username may be stale while the id still works, and the
                 * other way round, so a failure only counts once every
                 * candidate has been tried.
                 */
                $errors[] = is_int($peer)
                    ? "id {$peer}: " . $e->getMessage()
                    : "{$peer}: " . $e->getMessage();
            }
        }

        $this->recordFailure(
            check: $check,
            operator: $operator,
            errors: $errors,
        );
    }

    /**
     * @param list<string> $errors
     */
    private function recordFailure(
        TelegramDriverCheck $check,
        OperationUser $operator,
        array $errors,
    ): void {
        $reason = $errors !== []
            ? implode(' | ', $errors)
            : 'no reachable Telegram peer';

        $operator->forceFill([
            'dm_last_error' => mb_substr(
                $reason,
                0,
                self::ERROR_LIMIT,
            ),
        ])->save();

        Log::warning(
            'Operator direct message not delivered',
            [
                'check_id' => $check->id,
                'operation_user_id' => $operator->id,
                'operator' => $operator->name,
                'telegram_username' => $operator->telegram_username,
                'telegram_id' => $operator->telegram_id,
                'errors' => $errors,
            ],
        );
    }

    private function logSkipped(
        TelegramDriverCheck $check,
        OperationUser $operator,
    ): void {
        /*
         * Operators are created automatically from message text, so most of
         * them simply have no Telegram contact filled in yet. That is an
         * expected state, not a failure - info level, and no stored error.
         */
        Log::info(
            'Operator direct message skipped',
            [
                'check_id' => $check->id,
                'operation_user_id' => $operator->id,
                'operator' => $operator->name,
                'dm_enabled' => $operator->dm_enabled,
                'has_peer' => $operator->hasTelegramPeer(),
            ],
        );
    }

    /**
     * The group reply is threaded under the original driver message, which
     * does not exist in a private chat, so the copy carries a short header
     * naming the driver it belongs to instead.
     */
    private function buildMessage(
        TelegramDriverCheck $check,
        string $report,
    ): string {
        $driver = trim(
            (string) ($check->driver_name ?? ''),
        );

        $lines = [
            '📨 <b>Копия отчёта из группы</b>',
        ];

        if ($driver !== '') {
            $lines[] = 'Водитель: <b>'
                . htmlspecialchars(
                    $driver,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8',
                )
                . '</b>';
        }

        $lines[] = '';
        $lines[] = $report;

        return implode("\n", $lines);
    }
}
