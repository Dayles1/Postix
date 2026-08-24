<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Enums\Telegram\TelegramAccountProcess as TelegramAccountProcessEnum;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramAccountProcess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramAccountProcessService
{
    public function getOrCreate(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process
    ): TelegramAccountProcess {
        return TelegramAccountProcess::firstOrCreate(
            [
                'telegram_account_id' => $account->id,
                'process' => $process,
            ],
            [
                'is_available' => true,
                'is_busy' => false,
            ]
        );
    }

    public function registerSuccess(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process
    ): TelegramAccountProcess {
        return DB::transaction(function () use ($account, $process): TelegramAccountProcess {
            $state = TelegramAccountProcess::query()
                ->where('telegram_account_id', $account->id)
                ->where('process', $process)
                ->lockForUpdate()
                ->first();

            if (!$state) {
                $state = TelegramAccountProcess::create([
                    'telegram_account_id' => $account->id,
                    'process' => $process,
                    'successes' => 0,
                    'failures' => 0,
                    'consecutive_failures' => 0,
                    'is_available' => true,
                    'is_busy' => false,
                ]);
            }

            $state->registerSuccess();

            return $state->fresh();
        });
    }

    public function registerFailure(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process,
        ?string $reason = null,
        int $maxConsecutiveFailures = 3
    ): TelegramAccountProcess {
        return DB::transaction(function () use (
            $account,
            $process,
            $reason,
            $maxConsecutiveFailures
        ): TelegramAccountProcess {
            $state = TelegramAccountProcess::query()
                ->where('telegram_account_id', $account->id)
                ->where('process', $process)
                ->lockForUpdate()
                ->first();

            if (!$state) {
                $state = TelegramAccountProcess::create([
                    'telegram_account_id' => $account->id,
                    'process' => $process,
                    'successes' => 0,
                    'failures' => 0,
                    'consecutive_failures' => 0,
                    'is_available' => true,
                    'is_busy' => false,
                ]);
            }

            $state->registerFailure(
                $reason,
                $maxConsecutiveFailures
            );

            return $state->fresh();
        });
    }

    public function acquire(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process
    ): bool {
        return DB::transaction(function () use (
            $account,
            $process
        ): bool {
            $state = TelegramAccountProcess::query()
                ->where('telegram_account_id', $account->id)
                ->where('process', $process)
                ->lockForUpdate()
                ->first();

            if (!$state) {
                $state = TelegramAccountProcess::create([
                    'telegram_account_id' => $account->id,
                    'process' => $process,
                    'successes' => 0,
                    'failures' => 0,
                    'consecutive_failures' => 0,
                    'is_available' => true,
                    'is_busy' => false,
                ]);
            }

            if (!$state->is_available) {
                return false;
            }

            if ($state->is_busy) {
                return false;
            }

            $state->update([
                'is_busy' => true,
                'busy_at' => now(),
            ]);

            return true;
        });
    }

    public function release(
        TelegramAccount $account,
        TelegramAccountProcessEnum $process
    ): void {
        TelegramAccountProcess::query()
            ->where('telegram_account_id', $account->id)
            ->where('process', $process)
            ->update([
                'is_busy' => false,
                'busy_at' => null,
            ]);
    }

    public function availableAccounts(
        TelegramAccountProcessEnum $process
    ): Collection {
        $primaryAccountId = $this->primaryAccountId();

        $accounts = TelegramAccount::query()
            ->where('is_authorized', true)
            ->whereNotNull('session_path')
            ->when(
                $primaryAccountId !== null,
                fn ($query) => $query->where(
                    'id',
                    '!=',
                    $primaryAccountId
                )
            )
            ->orderBy('id')
            ->get();

        $available = $accounts
            ->filter(function (TelegramAccount $account) use ($process): bool {
                $state = TelegramAccountProcess::query()
                    ->where('telegram_account_id', $account->id)
                    ->where('process', $process)
                    ->first();

                if (!$state) {
                    return true;
                }

                return $state->is_available && !$state->is_busy;
            })
            ->values();

        Log::info('Resolver account candidates', [
            'process' => $process->value,
            'primary_account_id' => $primaryAccountId,
            'all_authorized_account_ids' => $accounts
                ->pluck('id')
                ->values()
                ->all(),
            'available_account_ids' => $available
                ->pluck('id')
                ->values()
                ->all(),
        ]);

        return $available;
    }

    public function findAvailableAccount(
        TelegramAccountProcessEnum $process
    ): ?TelegramAccount {
        foreach (
            $this->availableAccounts($process) as $account
        ) {
            if ($this->acquire($account, $process)) {
                Log::info('Resolver account acquired', [
                    'process' => $process->value,
                    'account_id' => $account->id,
                    'phone' => $account->phone,
                ]);

                return $account;
            }
        }

        Log::warning('No available resolver account', [
            'process' => $process->value,
            'primary_account_id' => $this->primaryAccountId(),
        ]);

        return null;
    }

    private function primaryAccountId(): ?int
    {
        $accountId = config(
            'services.telegram.driver_check_account_id'
        );

        if ($accountId === null || $accountId === '') {
            return null;
        }

        return (int) $accountId;
    }
}