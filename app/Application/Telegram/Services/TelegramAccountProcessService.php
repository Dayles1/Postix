<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Enums\Telegram\TelegramAccountProcess as TelegramAccountProcessEnum;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramAccountProcess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class TelegramAccountProcessService
{
    public const MAX_CONSECUTIVE_FAILURES = 7;

    /**
     * Get or create process state for an account.
     */
    public function getOrCreate(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process,
    ): TelegramAccountProcess {
        return TelegramAccountProcess::query()->firstOrCreate(
            [
                'telegram_account_id' => $account->id,
                'process' => $process->value,
            ],
            [
                'successes' => 0,
                'failures' => 0,
                'consecutive_failures' => 0,

                'is_available' => true,
                'is_busy' => false,

                'busy_at' => null,

                'disabled_at' => null,
                'disabled_reason' => null,

                'meta' => null,
            ],
        );
    }

    /**
     * Atomically claim one random available account.
     *
     * Rules:
     * - authorized
     * - has session
     * - not primary/listener account
     * - not used in current check
     * - is_available = true
     * - is_busy = false
     * - consecutive_failures < 7
     * - random order
     *
     * As soon as account is selected:
     * is_busy = true
     */
    public function claimRandomAvailableAccount(
        TelegramAccountProcessEnum $process,
        array $exceptAccountIds = [],
    ): ?TelegramAccount {
        return DB::transaction(
            function () use (
                $process,
                $exceptAccountIds,
            ): ?TelegramAccount {
                $primaryAccountId =
                    $this->primaryAccountId();

                $accounts = TelegramAccount::query()
                    ->where(
                        'is_authorized',
                        true,
                    )
                    ->whereNotNull(
                        'session_path',
                    )
                    ->where(
                        'session_path',
                        '!=',
                        '',
                    )

                    /*
                     * Listener account is never used as resolver.
                     */
                    ->when(
                        $primaryAccountId !== null,
                        function (
                            Builder $query,
                        ) use (
                            $primaryAccountId,
                        ): void {
                            $query->where(
                                'id',
                                '!=',
                                $primaryAccountId,
                            );
                        },
                    )

                    /*
                     * Account already used in this check.
                     */
                    ->when(
                        $exceptAccountIds !== [],
                        function (
                            Builder $query,
                        ) use (
                            $exceptAccountIds,
                        ): void {
                            $query->whereNotIn(
                                'id',
                                $exceptAccountIds,
                            );
                        },
                    )

                    ->inRandomOrder()
                    ->get();

                foreach ($accounts as $account) {
                    /*
                     * Lock process row before checking it.
                     */
                    $state =
                        TelegramAccountProcess::query()
                            ->where(
                                'telegram_account_id',
                                $account->id,
                            )
                            ->where(
                                'process',
                                $process->value,
                            )
                            ->lockForUpdate()
                            ->first();

                    /*
                     * State doesn't exist yet.
                     */
                    if (!$state) {
                        $state =
                            TelegramAccountProcess::query()->create([
                                'telegram_account_id' =>
                                    $account->id,

                                'process' =>
                                    $process->value,

                                'successes' => 0,
                                'failures' => 0,
                                'consecutive_failures' => 0,

                                'is_available' => true,
                                'is_busy' => false,

                                'busy_at' => null,

                                'disabled_at' => null,
                                'disabled_reason' => null,

                                'meta' => null,
                            ]);
                    }

                    /*
                     * Re-check after lock.
                     */
                    if (!$state->is_available) {
                        continue;
                    }

                    if ($state->is_busy) {
                        continue;
                    }

                    if (
                        $state->consecutive_failures >=
                        self::MAX_CONSECUTIVE_FAILURES
                    ) {
                        continue;
                    }

                    /*
                     * CLAIM.
                     *
                     * From this point another worker
                     * cannot take this account.
                     */
                    $state->update([
                        'is_busy' => true,
                        'busy_at' => now(),
                    ]);

                    Log::info(
                        'Telegram resolver account claimed',
                        [
                            'process' =>
                                $process->value,

                            'account_id' =>
                                $account->id,

                            'phone' =>
                                $account->phone,

                            'excluded_account_ids' =>
                                $exceptAccountIds,

                            'consecutive_failures' =>
                                $state->consecutive_failures,
                        ],
                    );

                    return $account;
                }

                Log::warning(
                    'No Telegram resolver account available',
                    [
                        'process' =>
                            $process->value,

                        'excluded_account_ids' =>
                            $exceptAccountIds,

                        'primary_account_id' =>
                            $primaryAccountId,
                    ],
                );

                return null;
            },
        );
    }

    /**
     * Successful Telegram operation.
     *
     * consecutive_failures -> 0
     * is_available remains true
     */
    public function registerSuccess(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process,
    ): TelegramAccountProcess {
        return DB::transaction(
            function () use (
                $account,
                $process,
            ): TelegramAccountProcess {
                $state =
                    $this->lockOrCreateState(
                        $account,
                        $process,
                    );

                $state->registerSuccess();

                return $state->fresh();
            },
        );
    }

    /**
     * Real account-level failure.
     *
     * Examples:
     * - Madeline failed to start
     * - session error
     * - Telegram API exception
     * - FloodWait
     * - other account-level error
     *
     * failures + 1
     * consecutive_failures + 1
     *
     * After 7 consecutive failures:
     * is_available = false
     */
    public function registerFailure(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process,
        ?string $reason = null,
    ): TelegramAccountProcess {
        return DB::transaction(
            function () use (
                $account,
                $process,
                $reason,
            ): TelegramAccountProcess {
                $state =
                    $this->lockOrCreateState(
                        $account,
                        $process,
                    );

                $state->registerFailure(
                    $reason,
                    self::MAX_CONSECUTIVE_FAILURES,
                );

                return $state->fresh();
            },
        );
    }

    /**
     * Driver phone is not registered in Telegram.
     *
     * IMPORTANT:
     *
     * This is NOT an account failure.
     *
     * failures + 1
     * consecutive_failures stays unchanged
     * account stays available
     */
    public function registerNotFound(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process,
        ?string $reason = null,
    ): TelegramAccountProcess {
        return DB::transaction(
            function () use (
                $account,
                $process,
                $reason,
            ): TelegramAccountProcess {
                $state =
                    $this->lockOrCreateState(
                        $account,
                        $process,
                    );

                $state->registerNotFound(
                    $reason,
                );

                return $state->fresh();
            },
        );
    }

    /**
     * Release account after every attempt.
     */
    public function release(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process,
    ): void {
        TelegramAccountProcess::query()
            ->where(
                'telegram_account_id',
                $account->id,
            )
            ->where(
                'process',
                $process->value,
            )
            ->update([
                'is_busy' => false,
                'busy_at' => null,
            ]);

        Log::info(
            'Telegram resolver account released',
            [
                'process' =>
                    $process->value,

                'account_id' =>
                    $account->id,

                'phone' =>
                    $account->phone,
            ],
        );
    }

    /**
     * Return random available accounts.
     *
     * Mostly useful for debug/admin.
     */
    public function availableAccounts(
        TelegramAccountProcessEnum $process,
        array $exceptAccountIds = [],
    ): Collection {
        $primaryAccountId =
            $this->primaryAccountId();

        return TelegramAccount::query()
            ->where(
                'is_authorized',
                true,
            )
            ->whereNotNull(
                'session_path',
            )
            ->where(
                'session_path',
                '!=',
                '',
            )

            ->when(
                $primaryAccountId !== null,
                function (
                    Builder $query,
                ) use (
                    $primaryAccountId,
                ): void {
                    $query->where(
                        'id',
                        '!=',
                        $primaryAccountId,
                    );
                },
            )

            ->when(
                $exceptAccountIds !== [],
                function (
                    Builder $query,
                ) use (
                    $exceptAccountIds,
                ): void {
                    $query->whereNotIn(
                        'id',
                        $exceptAccountIds,
                    );
                },
            )

            ->whereHas(
                'processes',
                function (
                    Builder $query,
                ) use (
                    $process,
                ): void {
                    $query
                        ->where(
                            'process',
                            $process->value,
                        )
                        ->where(
                            'is_available',
                            true,
                        )
                        ->where(
                            'is_busy',
                            false,
                        )
                        ->where(
                            'consecutive_failures',
                            '<',
                            self::MAX_CONSECUTIVE_FAILURES,
                        );
                },
            )

            ->inRandomOrder()
            ->get();
    }

    private function lockOrCreateState(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process,
    ): TelegramAccountProcess {
        $state =
            TelegramAccountProcess::query()
                ->where(
                    'telegram_account_id',
                    $account->id,
                )
                ->where(
                    'process',
                    $process->value,
                )
                ->lockForUpdate()
                ->first();

        if ($state) {
            return $state;
        }

        return TelegramAccountProcess::query()->create([
            'telegram_account_id' =>
                $account->id,

            'process' =>
                $process->value,

            'successes' => 0,
            'failures' => 0,
            'consecutive_failures' => 0,

            'is_available' => true,
            'is_busy' => false,

            'busy_at' => null,

            'disabled_at' => null,
            'disabled_reason' => null,

            'meta' => null,
        ]);
    }

    private function primaryAccountId(): ?int
    {
        $accountId = config(
            'services.telegram.driver_check_account_id',
        );

        if (
            $accountId === null ||
            $accountId === ''
        ) {
            return null;
        }

        return (int) $accountId;
    }
}