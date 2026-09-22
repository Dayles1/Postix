<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\TelegramDriverCheckReporter;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Models\Driver\TelegramDriverCheck;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessTelegramDriverCheckResults
{
    /**
     * How many times one report may fail before it is given up on.
     *
     * A report can be undeliverable for good: the message it replies to
     * was deleted, the group was left, the account lost its rights. Such
     * a check would otherwise be retried on every cron tick for as long
     * as the listener lives.
     */
    private const MAX_REPORT_ATTEMPTS = 5;

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
            try {
                $this->reporter->send(
                    $telegram,
                    $check,
                    data_get(
                        $check->telegram_raw,
                        'name_match',
                    ),
                );
            } catch (Throwable $e) {
                /*
                 * One report must never hold up the others.
                 *
                 * The reporter rethrows, the batch is ordered by id and
                 * reported_at is what takes a check out of it - so an
                 * exception escaping this loop meant the same check was
                 * picked first on every tick, and every driver checked
                 * after it went unreported for as long as the listener
                 * ran. The rest of the cron (the resolver-exhaustion
                 * warning) never ran either.
                 */
                $this->recordFailure($check, $e);
            }
        }
    }

    /**
     * Count a failed report on the check, and stop trying once it is
     * clear that nobody is going to receive it.
     */
    private function recordFailure(
        TelegramDriverCheck $check,
        Throwable $e,
    ): void {
        try {
            $raw = is_array($check->telegram_raw)
                ? $check->telegram_raw
                : [];

            $failures = (int) ($raw['report_failures'] ?? 0) + 1;

            $raw['report_failures'] = $failures;
            $raw['report_last_error'] = mb_substr($e->getMessage(), 0, 500);

            $attributes = ['telegram_raw' => $raw];

            $exhausted = $failures >= self::MAX_REPORT_ATTEMPTS;

            if ($exhausted) {
                /*
                 * Marked as reported so the queue moves on. The check
                 * itself keeps its verdict and the panel still shows
                 * it; what is lost is the reply in the group, which was
                 * never going to arrive anyway.
                 */
                $attributes['reported_at'] = now();
                $raw['report_abandoned'] = true;
                $attributes['telegram_raw'] = $raw;
            }

            $check->update($attributes);

            Log::log(
                $exhausted ? 'critical' : 'warning',
                $exhausted
                    ? 'Driver check report abandoned after repeated failures'
                    : 'Driver check report failed, will be retried',
                [
                    'check_id' => $check->id,
                    'chat_id' => $check->telegram_chat_id,
                    'message_id' => $check->telegram_message_id,
                    'failures' => $failures,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ],
            );
        } catch (Throwable $bookkeeping) {
            /*
             * Losing the counter is survivable; losing the loop is not.
             */
            Log::error(
                'Driver check report failure could not be recorded',
                [
                    'check_id' => $check->id,
                    'error' => $bookkeeping->getMessage(),
                ],
            );
        }
    }
}
