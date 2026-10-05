<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Enums\Telegram\TelegramClientCheckStatus;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use App\Models\Telegram\TelegramSetting;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers penalties in two steps.
 *
 * 1. forward() - the bot's message, as is, the moment it arrives.
 * 2. flush()   - one comment per request, once the bot has stopped
 *                re-sending it for a moment, to the peer the last forward
 *                reached.
 *
 * flush() also retries what Telegram refused, while it is still fresh.
 */
final class ClientCheckSender
{
    private const ERROR_LIMIT = 500;

    public function __construct(
        private readonly ClientCheckEscalation $escalation,
    ) {
    }

    /**
     * Same peer order as the operator DMs: username first, id as fallback.
     */
    public function forward(SimpleEventHandler $telegram, TelegramClientCheck $check): bool
    {
        $person = $check->operationUser;

        if (! $person instanceof OperationUser) {
            return false;
        }

        $check->increment('attempts');

        $errors = [];

        foreach ($person->telegramPeerCandidates() as $peer) {
            try {
                $telegram->messages->forwardMessages([
                    'from_peer' => $check->telegram_chat_id,
                    'id' => [$check->telegram_message_id],
                    'random_id' => [random_int(PHP_INT_MIN, PHP_INT_MAX)],
                    'to_peer' => $peer,
                ]);

                $check->update([
                    'status' => TelegramClientCheckStatus::Forwarded,
                    'reason' => null,
                    'error' => null,
                    'peer' => (string) $peer,
                    'forwarded_at' => now(),
                ]);

                $this->recordDelivery($person, null);

                return true;
            } catch (Throwable $e) {
                $errors[] = is_int($peer)
                    ? "id {$peer}: " . $e->getMessage()
                    : "{$peer}: " . $e->getMessage();
            }
        }

        $error = $errors !== [] ? implode(' | ', $errors) : 'no reachable Telegram peer';

        $this->fail(
            collect([$check]),
            TelegramClientCheck::REASON_FORWARD_FAILED,
            $error,
        );

        $this->recordDelivery($person, $error);

        return false;
    }

    /**
     * The person's row says how the last private message went, the same as
     * for the operator report copies: the people pages show it.
     */
    private function recordDelivery(OperationUser $person, ?string $error): void
    {
        try {
            $person->forceFill($error === null
                ? ['dm_last_sent_at' => now(), 'dm_last_error' => null]
                : ['dm_last_error' => mb_substr($error, 0, self::ERROR_LIMIT)])
                ->save();
        } catch (Throwable $e) {
            Log::warning(
                'Client check delivery could not be recorded on the person',
                [
                    'operation_user_id' => $person->id,
                    'error' => $e->getMessage(),
                ],
            );
        }
    }

    /**
     * Runs on the listener's cron.
     */
    public function flush(SimpleEventHandler $telegram): void
    {
        /*
         * Switched off: nothing more goes out - no retry, no comment. A
         * forward already delivered stays delivered; the comment it was
         * waiting for is called off rather than left to time out as an
         * error.
         */
        if (! TelegramSetting::clientChecksEnabled()) {
            TelegramClientCheck::query()
                ->where('status', TelegramClientCheckStatus::Forwarded)
                ->update([
                    'status' => TelegramClientCheckStatus::Sent,
                    'reason' => TelegramClientCheck::REASON_DISABLED,
                ]);

            return;
        }

        $this->retryForwards($telegram);

        $this->expireStale();

        $this->sendComments($telegram);
    }

    private function retryForwards(SimpleEventHandler $telegram): void
    {
        $checks = $this->retryable(TelegramClientCheck::REASON_FORWARD_FAILED)
            ->with('operationUser')
            ->orderBy('id')
            ->limit(20)
            ->get();

        foreach ($checks as $check) {
            $this->forward($telegram, $check);
        }
    }

    /**
     * A comment more than retry_minutes late is worse than none.
     */
    private function expireStale(): void
    {
        TelegramClientCheck::query()
            ->where('status', TelegramClientCheckStatus::Forwarded)
            ->where('forwarded_at', '<', now()->subMinutes($this->retryMinutes()))
            ->update([
                'status' => TelegramClientCheckStatus::Failed,
                'reason' => TelegramClientCheck::REASON_COMMENT_FAILED,
                'error' => 'Batch comment was not sent in time.',
            ]);
    }

    private function sendComments(SimpleEventHandler $telegram): void
    {
        $pending = TelegramClientCheck::query()
            ->with('operationUser')
            ->whereNotNull('operation_user_id')
            ->whereNotNull('forwarded_at')
            ->where(function (Builder $q): void {
                $q->where('status', TelegramClientCheckStatus::Forwarded)
                    ->orWhere(function (Builder $retry): void {
                        $retry->whereIn('id', $this->retryable(TelegramClientCheck::REASON_COMMENT_FAILED)->select('id'));
                    });
            })
            ->orderBy('id')
            ->get();

        $quietFrom = now()->subSeconds($this->escalation->quietSeconds());

        /*
         * Only the same request is ever stacked: the bot's repeat number
         * already says how many times it was sent, so two different
         * requests are two comments, never one.
         */
        $batches = $pending->groupBy(
            static fn (TelegramClientCheck $check): string => $check->operation_user_id
                . '|'
                . ($check->request_number ?? '#' . $check->id),
        );

        foreach ($batches as $batch) {
            /** @var TelegramClientCheck $last */
            $last = $batch->last();

            /*
             * The bot may still be re-sending this request.
             */
            if ($last->created_at > $quietFrom) {
                continue;
            }

            $this->comment($telegram, $batch->values());
        }
    }

    /**
     * @param Collection<int, TelegramClientCheck> $batch oldest first
     */
    private function comment(SimpleEventHandler $telegram, Collection $batch): void
    {
        /** @var TelegramClientCheck $last */
        $last = $batch->last();

        $person = $last->operationUser;

        if (! $person instanceof OperationUser) {
            return;
        }

        $ids = $batch->pluck('id')->all();

        /*
         * Comments switched off: the forward was all this batch gets.
         */
        if (! TelegramSetting::clientCheckCommentsEnabled()) {
            TelegramClientCheck::query()->whereIn('id', $ids)->update([
                'status' => TelegramClientCheckStatus::Sent,
                'reason' => TelegramClientCheck::REASON_COMMENT_DISABLED,
                'error' => null,
                'sent_at' => now(),
            ]);

            return;
        }

        TelegramClientCheck::query()->whereIn('id', $ids)->increment('attempts');

        try {
            $comment = $this->escalation->comment($batch, $person);

            /*
             * No comment at this level for this role: the forward was the
             * whole message. Done, and said so on the row.
             */
            if ($comment === null && ! $this->escalation->commentAllowed($batch, $person)) {
                TelegramClientCheck::query()->whereIn('id', $ids)->update([
                    'status' => TelegramClientCheckStatus::Sent,
                    'reason' => TelegramClientCheck::REASON_FORWARD_ONLY,
                    'error' => null,
                    'sent_at' => now(),
                ]);

                return;
            }

            if ($comment !== null) {
                $telegram->messages->sendMessage([
                    'peer' => $this->peer($last),
                    'message' => $comment,
                    'parse_mode' => 'html',
                    'no_webpage' => true,
                ]);
            }

            TelegramClientCheck::query()->whereIn('id', $ids)->update([
                'status' => TelegramClientCheckStatus::Sent,
                'reason' => null,
                'error' => null,
                'sent_at' => now(),
            ]);

            Log::info(
                'Client check comment sent',
                [
                    'operation_user_id' => $person->id,
                    'check_ids' => $ids,
                    'level' => $last->refresh()->comment_level,
                ],
            );
        } catch (Throwable $e) {
            $this->fail(
                $batch,
                TelegramClientCheck::REASON_COMMENT_FAILED,
                $e->getMessage(),
            );
        }
    }

    /**
     * Failed for $reason, attempts left, still fresh.
     */
    private function retryable(string $reason): Builder
    {
        return TelegramClientCheck::query()
            ->where('status', TelegramClientCheckStatus::Failed)
            ->where('reason', $reason)
            ->where('attempts', '<', $this->escalation->rules()->maxAttempts)
            ->where('created_at', '>=', now()->subMinutes($this->retryMinutes()));
    }

    private function retryMinutes(): int
    {
        return $this->escalation->rules()->retryMinutes;
    }

    /**
     * The peer column is a string; a numeric one was a Telegram id.
     */
    private function peer(TelegramClientCheck $check): int|string
    {
        $peer = (string) $check->peer;

        return preg_match('/^-?\d+$/', $peer) === 1
            ? (int) $peer
            : $peer;
    }

    /**
     * @param Collection<int, TelegramClientCheck> $checks
     */
    private function fail(Collection $checks, string $reason, string $error): void
    {
        $ids = $checks->pluck('id')->all();

        TelegramClientCheck::query()->whereIn('id', $ids)->update([
            'status' => TelegramClientCheckStatus::Failed,
            'reason' => $reason,
            'error' => mb_substr($error, 0, self::ERROR_LIMIT),
        ]);

        foreach ($checks as $check) {
            $check->refresh();
        }

        Log::warning(
            'Client check not delivered',
            [
                'check_ids' => $ids,
                'reason' => $reason,
                'error' => $error,
            ],
        );
    }
}
