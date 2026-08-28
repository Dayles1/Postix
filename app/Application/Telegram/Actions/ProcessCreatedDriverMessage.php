<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\TelegramDriverMessageParser;
use App\Application\Telegram\Services\TelegramOperationUserParser;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Jobs\Telegram\ResolveTelegramPhoneJob;
use App\Models\Driver\TelegramDriverCheck;
use App\Models\Telegram\TelegramResolvedPhone;
use Illuminate\Support\Facades\Log;

final class ProcessCreatedDriverMessage
{
    public function __construct(
        private readonly TelegramOperationUserParser $operationUserParser,
        private readonly TelegramDriverMessageParser $driverParser,
        private readonly ResolveOperationUser $resolveOperationUser,
        private readonly ResolveTelegramDriver $resolveTelegramDriver,
        private readonly ApplyResolvedTelegramPhone $applyResolvedTelegramPhone,
    ) {
    }

    public function execute(
        TelegramDriverCheck $check,
        string $text,
    ): void {
        /*
         * ---------------------------------------------------------
         * 1. Parse OperationUser
         * ---------------------------------------------------------
         */
        $operationUserName = $this->operationUserParser->parse(
            $text,
        );

        if ($operationUserName === null) {
            $check->update([
                'status' => TelegramDriverCheckStatus::NotConfirmed,

                'reason' => null,

                'error_message' =>
                    'Operation user is missing.',

                'checked_at' => now(),
            ]);

            Log::warning(
                'Operation user not found in created driver message',
                [
                    'check_id' => $check->id,
                ],
            );

            return;
        }

        /*
         * ---------------------------------------------------------
         * 2. Find / create OperationUser
         * ---------------------------------------------------------
         */
        $operationUser = $this->resolveOperationUser->execute(
            $operationUserName,
        );

        /*
         * ---------------------------------------------------------
         * 3. Parse driver
         * ---------------------------------------------------------
         *
         * Parser has only:
         *
         * parse(string $messageText)
         *
         * so we use parse().
         */
        $driverData = $this->driverParser->parse(
            $text,
        );

        /*
         * Save parsed data into check.
         */
        $check->update([
            'phone_raw' =>
                $driverData['phone_raw'] ?? null,

            'phone_normalized' =>
                $driverData['phone_normalized'] ?? null,

            'driver_name' =>
                $driverData['driver_name'] ?? null,

            'operation_user_id' =>
                $operationUser->id,
        ]);

        /*
         * ---------------------------------------------------------
         * 4. Driver name is required
         * ---------------------------------------------------------
         */
        $driverName =
            $driverData['driver_name'] ?? null;

        if (
            ! is_string($driverName)
            || trim($driverName) === ''
        ) {
            $check->update([
                'status' =>
                    TelegramDriverCheckStatus::NotConfirmed,

                'error_message' =>
                    'Driver name is missing.',

                'checked_at' => now(),
            ]);

            return;
        }

        /*
         * ---------------------------------------------------------
         * 5. Find / create TelegramDriver
         * ---------------------------------------------------------
         */
        $driver = $this->resolveTelegramDriver->execute(
            $operationUser,
            $driverData,
        );

        $check->update([
            'driver_id' => $driver->id,
        ]);

        /*
         * ---------------------------------------------------------
         * 6. Phone is required
         * ---------------------------------------------------------
         */
        $phoneNormalized =
            $driverData['phone_normalized'] ?? null;

        if (
            ! is_string($phoneNormalized)
            || trim($phoneNormalized) === ''
        ) {
            $check->update([
                'status' =>
                    TelegramDriverCheckStatus::NotConfirmed,

                'error_message' =>
                    'Phone number is missing.',

                'checked_at' => now(),
            ]);

            $driver->update([
                'status' => 'not_confirmed',
            ]);

            return;
        }

        /*
         * ---------------------------------------------------------
         * 7. CACHE FIRST
         * ---------------------------------------------------------
         */
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
         * ---------------------------------------------------------
         * 8. Dispatch Job
         * ---------------------------------------------------------
         *
         * ONLY CREATED_DRIVER reaches this action.
         */
        ResolveTelegramPhoneJob::dispatch(
            $check->id,
        )->onQueue('telegram');

        Log::info(
            'ResolveTelegramPhoneJob dispatched',
            [
                'check_id' => $check->id,
                'phone' => $phoneNormalized,
            ],
        );
    }
}