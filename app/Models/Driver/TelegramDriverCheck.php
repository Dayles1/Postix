<?php

declare(strict_types=1);

namespace App\Models\Driver;

use App\Enums\Drivers\TelegramDriverCheckReason;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Enums\Drivers\TelegramDriverCheckType;
use App\Enums\Drivers\TelegramDriverMessageType;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramResolvedPhone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramDriverCheck extends Model
{
    /**
     * resolver_result values that mean the resolver never got an answer
     * out of Telegram - a cancelled operation, no free account, a crash -
     * as opposed to an answer the driver did not like. Only these are
     * worth checking again.
     */
    public const TECHNICAL_FAILURES = [
        'resolver_unavailable',
        'resolver_failed_without_match',
        'command_failed',
    ];

    protected $fillable = [
        'telegram_chat_id',
        'telegram_message_id',

        'type',

        'message_text',

        'phone_raw',
        'phone_normalized',

        'driver_name',

        'driver_id',
        'operation_user_id',
        'telegram_resolved_phone_id',

        'telegram_user_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',

        'status',
        'system_status',
        'reason',

        'manual_by_telegram_id',
        'manual_by_name',
        'manual_at',

        'attempts',
        'error_message',

        'telegram_raw',

        'checked_at',
        'reported_at',

        'report_message_id',
        'report_reply_to_message_id',
        'report_dirty_at',
        'bot_message_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TelegramDriverMessageType::class,

            'status' =>
                TelegramDriverCheckStatus::class,

            'system_status' =>
                TelegramDriverCheckStatus::class,

            'reason' =>
                TelegramDriverCheckReason::class,

            'telegram_raw' => 'array',

            'checked_at' => 'datetime',

            'reported_at' => 'datetime',

            'manual_at' => 'datetime',

            'report_dirty_at' => 'datetime',
        ];
    }

    /**
     * Set only for the duration of recordManualDecision()'s save, so the
     * saving hook can tell a click from a system write. Comparing
     * manual_at is not enough: two clicks within one second leave it
     * unchanged.
     */
    private bool $recordingManualDecision = false;

    protected static function booted(): void
    {
        static::saving(static function (self $check): void {
            $check->trackVerdictChange();
        });
    }

    /**
     * Keeps the bookkeeping around `status` consistent, whichever of the
     * many places that write it did the writing.
     *
     *  - A status written without manual_at is the system speaking: it
     *    becomes the system verdict, and it ends any manual override
     *    (a re-run's fresh verdict is worth more than yesterday's click).
     *  - Once a check is reported, any change to what the report shows
     *    marks the report for an edit - that is how the listener, which
     *    owns the group report, learns that the bot changed something.
     */
    private function trackVerdictChange(): void
    {
        $manual = $this->recordingManualDecision;

        if ($this->isDirty('status') && ! $manual) {
            if ($this->status?->isFinal()) {
                $this->system_status = $this->status;
            }

            if ($this->getOriginal('manual_at') !== null) {
                $this->manual_at = null;
                $this->manual_by_name = null;
                $this->manual_by_telegram_id = null;
            }
        }

        if (
            $this->exists
            && $this->reported_at !== null
            && ! $this->isDirty('reported_at')
            && ($this->isDirty('status') || $manual)
        ) {
            $this->report_dirty_at = now();
        }
    }

    /**
     * A verdict set by hand, from the group, overriding the system's.
     */
    public function recordManualDecision(
        TelegramDriverCheckStatus $status,
        ?int $byTelegramId,
        string $byName,
    ): void {
        $this->recordingManualDecision = true;

        try {
            $this->forceFill([
                'status' => $status,
                'manual_by_telegram_id' => $byTelegramId,
                'manual_by_name' => mb_substr($byName, 0, 255),
                'manual_at' => now(),
            ])->save();
        } finally {
            $this->recordingManualDecision = false;
        }
    }

    public function isManuallyDecided(): bool
    {
        return $this->manual_at !== null;
    }

    /**
     * The resolver failed on its own account - Telegram, the session or
     * the account pool - so running it again may well give an answer.
     */
    public function hasTechnicalFailure(): bool
    {
        if ($this->status !== TelegramDriverCheckStatus::NotConfirmed) {
            return false;
        }

        $raw = is_array($this->telegram_raw) ? $this->telegram_raw : [];

        if (isset($raw['name_match'])) {
            return false;
        }

        return in_array(
            $raw['resolver_result'] ?? null,
            self::TECHNICAL_FAILURES,
            true,
        );
    }

    /**
     * Earlier runs of this check, oldest first. See RerunTelegramDriverCheck.
     *
     * @return list<array<string, mixed>>
     */
    public function history(): array
    {
        $history = is_array($this->telegram_raw)
            ? ($this->telegram_raw['history'] ?? [])
            : [];

        return is_array($history) ? array_values($history) : [];
    }

    /**
     * The status this check had before its latest run or manual decision.
     */
    public function previousStatus(): ?TelegramDriverCheckStatus
    {
        $history = $this->history();

        $last = $history === [] ? null : $history[array_key_last($history)];

        return is_array($last)
            ? TelegramDriverCheckStatus::tryFrom((string) ($last['status'] ?? ''))
            : null;
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(
            TelegramDriver::class,
            'driver_id',
        );
    }

    public function operationUser(): BelongsTo
    {
        return $this->belongsTo(
            OperationUser::class,
            'operation_user_id',
        );
    }

    public function resolvedPhone(): BelongsTo
    {
        return $this->belongsTo(
            TelegramResolvedPhone::class,
            'telegram_resolved_phone_id',
        );
    }
}
