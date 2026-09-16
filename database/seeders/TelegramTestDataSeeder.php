<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Drivers\TelegramDriverCheckReason;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Enums\Drivers\TelegramDriverMessageType;
use App\Models\Driver\TelegramDriver;
use App\Models\Driver\TelegramDriverCheck;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramResolvedPhone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TelegramTestDataSeeder extends Seeder
{
    private const OPERATORS_COUNT = 25;

    private const MIN_DRIVERS_PER_OPERATOR = 10;

    private const MAX_DRIVERS_PER_OPERATOR = 20;

    /**
     * Driver/check date range:
     *
     * minimum = 4 days ago
     * maximum = 1 month ago
     */
    private const MIN_DAYS_AGO = 4;

    private const MAX_DAYS_AGO = 30;

    public function run(): void
    {
        /*
         * ===============================================================
         * CLEAN OLD TEST DATA
         * ===============================================================
         *
         * IMPORTANT:
         * Do NOT wrap this in DB::transaction().
         *
         * MySQL TRUNCATE causes implicit COMMIT, which can lead to:
         *
         * "There is no active transaction"
         */

        $this->command?->info('Cleaning old Telegram test data...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table('telegram_driver_checks')->truncate();

            DB::table('telegram_resolved_phones')->truncate();

            DB::table('telegram_drivers')->truncate();

            DB::table('operation_users')->truncate();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        /*
         * ===============================================================
         * TELEGRAM ACCOUNTS
         * ===============================================================
         *
         * Existing Telegram accounts are preserved.
         */

        $telegramAccountIds = TelegramAccount::query()
            ->pluck('id')
            ->all();

        /*
         * ===============================================================
         * CREATE TEST DATA
         * ===============================================================
         */

        $totalDrivers = 0;
        $totalChecks = 0;

        for ($operatorIndex = 1; $operatorIndex <= self::OPERATORS_COUNT; $operatorIndex++) {
            $operationUser = $this->createOperationUser(
                index: $operatorIndex,
            );

            /*
             * Every operation gets 10-20 drivers.
             */
            $driversCount = fake()->numberBetween(
                self::MIN_DRIVERS_PER_OPERATOR,
                self::MAX_DRIVERS_PER_OPERATOR,
            );

            $this->command?->line(
                "Operation #{$operatorIndex}: creating {$driversCount} drivers..."
            );

            for ($driverIndex = 1; $driverIndex <= $driversCount; $driverIndex++) {
                $this->createDriverPhoneCheck(
                    operationUser: $operationUser,
                    operatorIndex: $operatorIndex,
                    driverIndex: $driverIndex,
                    telegramAccountIds: $telegramAccountIds,
                );

                $totalDrivers++;
                $totalChecks++;
            }
        }

        /*
         * ===============================================================
         * SUCCESS
         * ===============================================================
         */

        $this->command?->newLine();

        $this->command?->info(
            "Telegram test data created successfully."
        );

        $this->command?->info(
            "Operations: " . self::OPERATORS_COUNT
        );

        $this->command?->info(
            "Drivers: {$totalDrivers}"
        );

        $this->command?->info(
            "Checks: {$totalChecks}"
        );

        $this->command?->info(
            'Driver dates: from 4 days ago to 30 days ago.'
        );
    }

    /**
     * Create operation user.
     */
    private function createOperationUser(
        int $index,
    ): OperationUser {
        $name = fake()->name();

        return OperationUser::create([
            'name' => $name,

            'name_normalized' => $this->normalize(
                $name,
            ),

            'telegram_username' => "operator_{$index}",

            'telegram_id' => 900000000 + $index,
        ]);
    }

    /**
     * Create:
     *
     * TelegramDriver
     * TelegramResolvedPhone
     * TelegramDriverCheck
     */
    private function createDriverPhoneCheck(
        OperationUser $operationUser,
        int $operatorIndex,
        int $driverIndex,
        array $telegramAccountIds,
    ): void {
        /*
         * ===============================================================
         * STATUS
         * ===============================================================
         *
         * ONLY:
         *
         * confirmed
         * not_confirmed
         */

        $status = fake()->randomElement([
            TelegramDriverCheckStatus::Confirmed,
            TelegramDriverCheckStatus::NotConfirmed,
        ]);


        /*
         * ===============================================================
         * DRIVER NAME
         * ===============================================================
         */

        $driverName = fake()->name();


        /*
         * ===============================================================
         * TELEGRAM NAME
         * ===============================================================
         *
         * IMPORTANT:
         *
         * confirmed:
         *     Telegram name is similar to driver name.
         *
         * not_confirmed:
         *     Telegram name is intentionally different.
         */

        $driverNameParts =
            preg_split(
                '/\s+/',
                trim($driverName),
                -1,
                PREG_SPLIT_NO_EMPTY,
            );


        $driverFirstName =
            $driverNameParts[0]
            ?? fake()->firstName();


        $driverLastName =
            count($driverNameParts) > 1
            ? $driverNameParts[array_key_last($driverNameParts)]
            : fake()->lastName();


        if (
            $status === TelegramDriverCheckStatus::Confirmed
        ) {
            /*
             * ==========================================================
             * CONFIRMED
             * ==========================================================
             *
             * Telegram name is close to the driver name.
             *
             * Example:
             *
             * Driver:
             *     Eloy Parker
             *
             * Telegram:
             *     Eloy Parker
             *
             * or:
             *
             *     Eloy Parker
             */

            $firstName = $driverFirstName;

            $lastName = $driverLastName;
        } else {
            /*
             * ==========================================================
             * NOT CONFIRMED
             * ==========================================================
             *
             * Generate completely different Telegram identity.
             */

            do {
                $firstName = fake()->firstName();
                $lastName = fake()->lastName();

                $telegramName =
                    $this->normalize(
                        "{$firstName} {$lastName}",
                    );

                $driverNormalizedName =
                    $this->normalize(
                        $driverName,
                    );

            } while (
                $telegramName === $driverNormalizedName
            );
        }


        /*
         * ===============================================================
         * DATE
         * ===============================================================
         */

        $checkedAt = $this->randomTestDate();


        /*
         * Resolve date slightly before check.
         */

        $resolvedAt = $checkedAt->copy()->subMinutes(
            fake()->numberBetween(
                1,
                180,
            ),
        );


        /*
         * ===============================================================
         * DRIVER
         * ===============================================================
         */

        $driver = TelegramDriver::create([
            'name' => $driverName,

            'name_normalized' => $this->normalize(
                $driverName,
            ),

            'operation_user_id' => $operationUser->id,

            'status' => $status->value,
        ]);


        /*
         * ===============================================================
         * PHONE
         * ===============================================================
         */

        $phone = $this->makePhone(
            operatorIndex: $operatorIndex,
            driverIndex: $driverIndex,
        );


        /*
         * ===============================================================
         * TELEGRAM USER
         * ===============================================================
         */

        $telegramUserId =
            700000000
            + ($operatorIndex * 1000)
            + $driverIndex;

        $telegramUsername =
            "driver_{$telegramUserId}";


        /*
         * ===============================================================
         * TELEGRAM ACCOUNT
         * ===============================================================
         */

        $telegramAccountId = null;

        if (
            $telegramAccountIds !== []
            && fake()->boolean(70)
        ) {
            $telegramAccountId =
                fake()->randomElement(
                    $telegramAccountIds,
                );
        }


        /*
         * ===============================================================
         * RESOLVED PHONE
         * ===============================================================
         */

        $resolvedPhone = TelegramResolvedPhone::create([
            'phone_normalized' => $phone,

            'telegram_user_id' => $telegramUserId,

            'telegram_username' => $telegramUsername,

            'telegram_first_name' => $firstName,

            'telegram_last_name' => $lastName,

            'telegram_raw' => [
                'id' => $telegramUserId,

                'phone' => $phone,

                'username' => $telegramUsername,

                'first_name' => $firstName,

                'last_name' => $lastName,
            ],

            'telegram_account_id' => $telegramAccountId,

            'driver_id' => $driver->id,

            'resolved_at' => $resolvedAt,
        ]);


        /*
         * ===============================================================
         * TELEGRAM CHAT / MESSAGE
         * ===============================================================
         */

        $telegramChatId =
            -1000000000000
            - ($operatorIndex * 1000)
            - $driverIndex;


        $telegramMessageId =
            ($operatorIndex * 100000)
            + $driverIndex;


        /*
         * ===============================================================
         * REPORTED AT
         * ===============================================================
         */

        $reportedAt = null;

        if (
            $status === TelegramDriverCheckStatus::Confirmed
            &&
            fake()->boolean(80)
        ) {
            $reportedAt =
                $checkedAt->copy()->addMinutes(
                    fake()->numberBetween(
                        1,
                        60,
                    ),
                );
        }


        /*
         * ===============================================================
         * CHECK
         * ===============================================================
         */

        TelegramDriverCheck::create([
            'telegram_chat_id' => $telegramChatId,

            'telegram_message_id' => $telegramMessageId,

            'type' => fake()
                ->randomElement(
                    TelegramDriverMessageType::cases(),
                )
                ->value,

            'message_text' =>
                "Driver: {$driverName}, Phone: {$phone}",

            'phone_raw' => $phone,

            'phone_normalized' => $phone,

            'driver_name' => $driverName,

            'driver_id' => $driver->id,

            'operation_user_id' => $operationUser->id,

            'telegram_resolved_phone_id' => $resolvedPhone->id,

            'telegram_user_id' => $telegramUserId,

            'telegram_username' => $telegramUsername,

            'telegram_first_name' => $firstName,

            'telegram_last_name' => $lastName,

            'status' => $status->value,

            'reason' => fake()
                ->randomElement(
                    TelegramDriverCheckReason::cases(),
                )
                ->value,

            'attempts' =>
                $status === TelegramDriverCheckStatus::Confirmed
                ? fake()->numberBetween(0, 2)
                : fake()->numberBetween(1, 3),

            'error_message' => null,

            'telegram_raw' => [
                'id' => $telegramUserId,

                'phone' => $phone,

                'username' => $telegramUsername,

                'first_name' => $firstName,

                'last_name' => $lastName,

                'message_id' => $telegramMessageId,

                'chat_id' => $telegramChatId,
            ],

            'checked_at' => $checkedAt,

            'reported_at' => $reportedAt,
        ]);
    }


    /**
     * Generate a random test date between:
     *
     * 4 days ago
     * and
     * 30 days ago.
     */
    private function randomTestDate(): Carbon
    {
        $daysAgo = fake()->numberBetween(
            self::MIN_DAYS_AGO,
            self::MAX_DAYS_AGO,
        );

        /*
         * Random hour/minute/second
         * makes dates more realistic.
         */
        return Carbon::now()
            ->subDays($daysAgo)
            ->setTime(
                fake()->numberBetween(0, 23),
                fake()->numberBetween(0, 59),
                fake()->numberBetween(0, 59),
            );
    }

    /**
     * Generate unique-ish test phone.
     *
     * Example:
     *
     * +9989001001
     */
    private function makePhone(
        int $operatorIndex,
        int $driverIndex,
    ): string {
        return sprintf(
            '+998900%03d%03d',
            $operatorIndex,
            $driverIndex,
        );
    }

    /**
     * Normalize name.
     */
    private function normalize(
        string $value,
    ): string {
        return Str::lower(
            Str::ascii(
                trim($value),
            ),
        );
    }
}