<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Models\Driver\TelegramDriverCheck;
use Illuminate\Support\Facades\Log;

/**
 * Runs an existing check again, on the same record.
 *
 * A check is re-run when its first answer cannot be trusted: the
 * resolver failed on its own account (the recheck button), or the
 * operator fixed the phone after the check had flagged it (a
 * "Изменены данные водителя" message). Either way the driver is the
 * same driver, so the record is too - it always carries the latest
 * verdict, and the driver's history of error, error, success reads as
 * success everywhere the panel counts. What each earlier run said is
 * kept in telegram_raw.history.
 */
final class RerunTelegramDriverCheck
{
    public const TRIGGER_RECHECK = 'recheck';

    public const TRIGGER_PHONE_CHANGED = 'phone_changed';

    /**
     * telegram_raw keys that belong to one run and must not leak into
     * the next: a stale name_match would read as a verdict.
     */
    private const RUN_KEYS = [
        'name_match',
        'resolved_from_cache',
        'resolved_phone_id',
        'post_verdict_error',
        'report_failures',
        'report_last_error',
        'report_abandoned',
        'report_edit_failures',
    ];

    public function __construct(
        private readonly StartTelegramPhoneResolve $startPhoneResolve,
    ) {
    }

    /**
     * @param  string|null  $phoneNormalized  the new phone, when it changed
     * @param  int|null  $newReportUnder  post a fresh report under this
     *                                    message instead of editing the old one
     */
    public function execute(
        TelegramDriverCheck $check,
        string $trigger,
        ?string $by = null,
        ?string $phoneRaw = null,
        ?string $phoneNormalized = null,
        ?int $newReportUnder = null,
    ): void {
        $raw = is_array($check->telegram_raw) ? $check->telegram_raw : [];

        $history = $check->history();

        $history[] = [
            'at' => now()->toISOString(),
            'trigger' => $trigger,
            'by' => $by,
            'status' => $check->status?->value,
            'system_status' => $check->system_status?->value,
            'manual_by' => $check->manual_by_name,
            'phone' => $check->phone_normalized,
            'error_message' => $check->error_message,
            'resolver_result' => $raw['resolver_result'] ?? null,
            'score' => $raw['name_match']['score'] ?? null,
            'telegram_user_id' => $check->telegram_user_id,
            'report_message_id' => $check->report_message_id,
        ];

        foreach (array_keys($raw) as $key) {
            if (str_starts_with((string) $key, 'resolver_') || in_array($key, self::RUN_KEYS, true)) {
                unset($raw[$key]);
            }
        }

        $raw['history'] = $history;

        $attributes = [
            'status' => TelegramDriverCheckStatus::Pending,
            'reason' => null,
            'error_message' => null,
            'attempts' => 0,
            'checked_at' => null,

            'telegram_resolved_phone_id' => null,
            'telegram_user_id' => null,
            'telegram_username' => null,
            'telegram_first_name' => null,
            'telegram_last_name' => null,

            'telegram_raw' => $raw,
        ];

        if ($phoneNormalized !== null) {
            $attributes['phone_raw'] = $phoneRaw ?? $phoneNormalized;
            $attributes['phone_normalized'] = $phoneNormalized;
        }

        if ($newReportUnder !== null) {
            /*
             * The old report and bot message have already been retired
             * by the caller; the next report is a new message.
             */
            $attributes['reported_at'] = null;
            $attributes['report_message_id'] = null;
            $attributes['report_reply_to_message_id'] = $newReportUnder;
            $attributes['report_dirty_at'] = null;
            $attributes['bot_message_id'] = null;
        }

        $check->forceFill($attributes)->save();

        $check->driver?->update([
            'status' => TelegramDriverCheckStatus::Pending->value,
        ]);

        Log::info(
            'Telegram driver check re-run',
            [
                'check_id' => $check->id,
                'trigger' => $trigger,
                'by' => $by,
                'phone' => $check->phone_normalized,
                'previous_status' => $history[array_key_last($history)]['status'],
                'run' => count($history) + 1,
            ],
        );

        $this->startPhoneResolve->execute($check);
    }
}
