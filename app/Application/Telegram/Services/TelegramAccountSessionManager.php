<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Application\Telegram\Exceptions\TelegramAccountStateException;
use App\Jobs\Telegram\AuthTelegramAccountJob;
use App\Jobs\Telegram\CheckTelegramAccountJob;
use App\Jobs\Telegram\CompleteTelegramAccountLoginJob;
use App\Jobs\Telegram\LogoutTelegramAccountJob;
use App\Jobs\Telegram\VerifyTelegramAccountCodeJob;
use App\Models\Telegram\TelegramAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Everything the web side may do to a TelegramAccount session.
 *
 * MadelineProto only runs on the CLI, so none of this touches Telegram:
 * each step checks that the account is in a state where the step makes
 * sense, moves it to the matching in-flight status and hands the real
 * work to an artisan command through a job. The command writes the
 * outcome back onto the row, and the panel polls for it.
 */
final class TelegramAccountSessionManager
{
    /**
     * How long a 2FA password waits in the cache for `tp` to pick it up.
     */
    private const PASSWORD_TTL_SECONDS = 300;

    /**
     * Sends the login code. Starting over is allowed from any state
     * except "authorized" and "a command is working on it right now".
     */
    public function start(string $phone): TelegramAccount
    {
        $phone = TelegramAccount::normalizePhone($phone);

        return DB::transaction(function () use ($phone): TelegramAccount {
            $account = TelegramAccount::query()
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            if ($account?->is_authorized) {
                throw new TelegramAccountStateException('already_authorized');
            }

            if ($account && $account->isInFlight() && ! $account->isStale()) {
                throw new TelegramAccountStateException('busy');
            }

            $account ??= new TelegramAccount(['phone' => $phone]);

            $account->fill([
                'session_path' => TelegramAccount::sessionPathFor($phone),
                'is_authorized' => false,
                'status' => TelegramAccount::STATUS_PROCESSING,
                'password_hint' => null,
                'last_error' => null,
            ])->save();

            AuthTelegramAccountJob::dispatch($phone)
                ->onQueue('telegram')
                ->afterCommit();

            return $account;
        });
    }

    public function submitCode(TelegramAccount $account, string $code): TelegramAccount
    {
        $code = preg_replace('/\D+/', '', $code);

        if (! $account->isAwaitingCode()) {
            throw new TelegramAccountStateException('not_waiting_code');
        }

        $account->update([
            'status' => TelegramAccount::STATUS_VERIFYING,
            'last_error' => null,
        ]);

        VerifyTelegramAccountCodeJob::dispatch($account->phone, $code)
            ->onQueue('telegram');

        return $account;
    }

    public function submitPassword(TelegramAccount $account, string $password): TelegramAccount
    {
        if (! $account->isAwaitingPassword()) {
            throw new TelegramAccountStateException('not_waiting_password');
        }

        Cache::put(
            TelegramAccount::passwordCacheKey($account->id),
            Crypt::encryptString($password),
            self::PASSWORD_TTL_SECONDS,
        );

        $account->update([
            'status' => TelegramAccount::STATUS_VERIFYING,
            'last_error' => null,
        ]);

        CompleteTelegramAccountLoginJob::dispatch($account->id)
            ->onQueue('telegram');

        return $account;
    }

    public function check(TelegramAccount $account): TelegramAccount
    {
        if (! $account->is_authorized) {
            throw new TelegramAccountStateException('not_authorized');
        }

        $this->guardIdle($account);

        /*
         * Opening a session from a second process while its owner is
         * using it is asking for trouble. The listener refreshes its own
         * profile on start (TelegramDriverCheckStarter); a resolver
         * account is simply checked once it is free again.
         */
        if ($account->isListening()) {
            throw new TelegramAccountStateException('listener_running');
        }

        if ($account->hasBusyProcess()) {
            throw new TelegramAccountStateException('process_busy');
        }

        Cache::put(
            TelegramAccount::statusBeforeCheckCacheKey($account->id),
            $account->status,
            self::PASSWORD_TTL_SECONDS,
        );

        $account->update([
            'status' => TelegramAccount::STATUS_CHECKING,
        ]);

        CheckTelegramAccountJob::dispatch($account->id)
            ->onQueue('telegram');

        return $account;
    }

    public function logout(TelegramAccount $account): TelegramAccount
    {
        if (! $account->is_authorized && ! $account->hasSessionFile()) {
            throw new TelegramAccountStateException('not_authorized');
        }

        $this->guardIdle($account);

        $account->update([
            'status' => TelegramAccount::STATUS_LOGGING_OUT,
        ]);

        LogoutTelegramAccountJob::dispatch($account->id)
            ->onQueue('telegram');

        return $account;
    }

    /**
     * Forgets an account that holds no live session: an abandoned login,
     * a revoked or logged-out session. A working one is logged out first,
     * otherwise it would stay listed under "Active sessions" in Telegram
     * with nobody left to end it.
     */
    public function delete(TelegramAccount $account): void
    {
        if ($account->is_authorized) {
            throw new TelegramAccountStateException('still_authorized');
        }

        $this->guardIdle($account);

        $path = $account->session_path;

        if ($path && File::isDirectory($path)) {
            File::deleteDirectory($path);
        } elseif ($path && File::exists($path)) {
            File::delete($path);
        }

        Cache::forget(TelegramAccount::passwordCacheKey($account->id));

        $account->delete();
    }

    private function guardIdle(TelegramAccount $account): void
    {
        if ($account->isInFlight() && ! $account->isStale()) {
            throw new TelegramAccountStateException('busy');
        }
    }
}
