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

class TelegramAccountProcessService
{
    /**
     * Get existing process state or create a new one.
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
                'is_available' => true,
                'is_busy' => false,
                'busy_at' => null,
            ],
        );
    }

    /**
     * Register successful process execution.
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
                $state = $this->lockOrCreateState(
                    $account,
                    $process,
                );

                $state->registerSuccess();

                return $state->fresh();
            }
        );
    }

    /**
     * Register failed process execution.
     */
    public function registerFailure(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process,
        ?string $reason = null,
        int $maxConsecutiveFailures = 3,
    ): TelegramAccountProcess {
        return DB::transaction(
            function () use (
                $account,
                $process,
                $reason,
                $maxConsecutiveFailures,
            ): TelegramAccountProcess {
                $state = $this->lockOrCreateState(
                    $account,
                    $process,
                );

                $state->registerFailure(
                    $reason,
                    $maxConsecutiveFailures,
                );

                return $state->fresh();
            }
        );
    }

    /**
     * Acquire is intentionally disabled for resolver selection.
     *
     * We do NOT use is_busy as an availability condition.
     */
    public function acquire(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process,
    ): bool {
        return DB::transaction(
            function () use (
                $account,
                $process,
            ): bool {
                $state = $this->lockOrCreateState(
                    $account,
                    $process,
                );

                /*
                 * Only is_available matters.
                 */
                if (!$state->is_available) {
                    return false;
                }

                /*
                 * We deliberately DO NOT set is_busy.
                 */
                return true;
            }
        );
    }

    /**
     * Release is kept for compatibility.
     *
     * is_busy is not used to determine availability anymore.
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
    }

    /**
     * Return available resolver accounts.
     *
     * IMPORTANT:
     * is_busy is completely ignored.
     */
    public function availableAccounts(
        TelegramAccountProcessEnum $process,
    ): Collection {
        return $this->candidateAccounts($process);
    }

    /**
     * Find first available resolver account.
     *
     * IMPORTANT:
     * This method does NOT acquire or modify the account.
     */
    public function findAvailableAccount(
        TelegramAccountProcessEnum $process,
    ): ?TelegramAccount {
        $primaryAccountId = $this->primaryAccountId();

        $accounts = $this->candidateAccounts($process);

        foreach ($accounts as $account) {
            if (
                $primaryAccountId !== null
                && (int) $account->id === $primaryAccountId
            ) {
                continue;
            }

            Log::info(
                'Resolver account selected',
                [
                    'process' => $process->value,
                    'account_id' => $account->id,
                    'phone' => $account->phone,
                    'is_busy_ignored' => true,
                ]
            );

            return $account;
        }

        Log::warning(
            'No available resolver account',
            [
                'process' => $process->value,
                'primary_account_id' => $primaryAccountId,
                'candidate_account_ids' =>
                    $accounts
                        ->pluck('id')
                        ->values()
                        ->all(),
            ]
        );

        return null;
    }

    /**
     * Check whether at least one resolver account exists.
     *
     * is_busy is ignored.
     */
    public function hasAvailableAccount(
        TelegramAccountProcessEnum $process,
    ): bool {
        return $this->candidateAccounts(
            $process
        )->isNotEmpty();
    }

    /**
     * Return candidate resolver accounts.
     *
     * IMPORTANT:
     * - is_authorized must be true;
     * - session_path must exist;
     * - primary listener account is excluded;
     * - process must either not exist or be available;
     * - is_busy is COMPLETELY IGNORED.
     */
    private function candidateAccounts(
        TelegramAccountProcessEnum $process,
    ): Collection {
        $primaryAccountId = $this->primaryAccountId();

        $accounts = TelegramAccount::query()
            ->where('is_authorized', true)
            ->whereNotNull('session_path')
            ->where('session_path', '!=', '')
            ->when(
                $primaryAccountId !== null,
                function (Builder $query) use (
                    $primaryAccountId
                ): void {
                    $query->where(
                        'id',
                        '!=',
                        $primaryAccountId,
                    );
                }
            )
            ->where(function (Builder $query) use (
                $process
            ): void {
                /*
                 * No process row yet -> available.
                 */
                $query
                    ->whereNotExists(
                        TelegramAccountProcess::query()
                            ->selectRaw('1')
                            ->whereColumn(
                                'telegram_account_processes.telegram_account_id',
                                'telegram_accounts.id',
                            )
                            ->where(
                                'telegram_account_processes.process',
                                $process->value,
                            )
                    )

                    /*
                     * Process exists and is_available=true.
                     *
                     * is_busy is intentionally ignored.
                     */
                    ->orWhereExists(
                        TelegramAccountProcess::query()
                            ->selectRaw('1')
                            ->whereColumn(
                                'telegram_account_processes.telegram_account_id',
                                'telegram_accounts.id',
                            )
                            ->where(
                                'telegram_account_processes.process',
                                $process->value,
                            )
                            ->where(
                                'telegram_account_processes.is_available',
                                true,
                            )
                    );
            })
            ->orderBy('id')
            ->get();

        Log::info(
            'Resolver candidates checked',
            [
                'process' => $process->value,
                'primary_account_id' => $primaryAccountId,

                'candidate_account_ids' =>
                    $accounts
                        ->pluck('id')
                        ->values()
                        ->all(),

                'is_busy_ignored' => true,
            ]
        );

        return $accounts;
    }

    /**
     * Lock existing process state or create one.
     */
    private function lockOrCreateState(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process,
    ): TelegramAccountProcess {
        $state = TelegramAccountProcess::query()
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
            'telegram_account_id' => $account->id,
            'process' => $process->value,

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

    /**
     * Main listener account.
     *
     * This account must never be used for resolver work.
     */
    private function primaryAccountId(): ?int
    {
        $accountId = config(
            'services.telegram.driver_check_account_id',
        );

        if (
            $accountId === null
            || $accountId === ''
        ) {
            return null;
        }

        return (int) $accountId;
    }
}