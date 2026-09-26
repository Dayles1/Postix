<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Jobs\Telegram\ResolveTelegramPhoneJob;
use App\Models\Driver\TelegramDriverCheck;
use App\Models\Telegram\TelegramResolvedPhone;
use Illuminate\Support\Facades\Log;

/**
 * Answers a check from the resolved-phone cache, or hands it to the
 * resolver when the phone has never been resolved.
 *
 * Shared by the first run of a check and every re-run of it, so both
 * reach a verdict the same way.
 */
final class StartTelegramPhoneResolve
{
    public function __construct(
        private readonly ApplyResolvedTelegramPhone $applyResolvedTelegramPhone,
    ) {
    }

    public function execute(TelegramDriverCheck $check): void
    {
        $phoneNormalized = $check->phone_normalized;

        $resolvedPhone = TelegramResolvedPhone::query()
            ->where(
                'phone_normalized',
                $phoneNormalized,
            )
            ->first();

        if ($resolvedPhone !== null) {
            Log::info(
                'Telegram resolved phone found in cache',
                [
                    'check_id' => $check->id,
                    'phone' => $phoneNormalized,
                    'resolved_phone_id' => $resolvedPhone->id,
                ],
            );

            $this->applyResolvedTelegramPhone->execute(
                check: $check,
                resolvedPhone: $resolvedPhone,
            );

            return;
        }

        /*
         * After commit: a re-run started from a button runs inside a
         * transaction, and a resolver that read the check before it
         * went back to pending would take it for finished.
         */
        ResolveTelegramPhoneJob::dispatch(
            $check->id,
        )->onQueue('telegram')->afterCommit();

        Log::info(
            'ResolveTelegramPhoneJob dispatched',
            [
                'check_id' => $check->id,
                'phone' => $phoneNormalized,
            ],
        );
    }
}
