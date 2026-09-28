<?php

namespace App\Console\Commands\Telegram;

use App\Console\Commands\Telegram\Concerns\OpensTelegramAccountSession;
use App\Models\Telegram\TelegramAccount;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Last step of a TelegramAccount login: the 2FA password.
 *
 * The password is not an argument - anything on the command line is
 * readable by every user of the box through `ps`. The web side leaves it
 * encrypted in the cache under TelegramAccount::passwordCacheKey(), and
 * this command takes it out exactly once.
 */
class TelegramAccountPasswordCommand extends Command
{
    use OpensTelegramAccountSession;

    protected $signature = 'tp {accountId}';
    protected $description = 'Complete Telegram 2FA login for a TelegramAccount';

    public function handle(): int
    {
        $account = TelegramAccount::find((int) $this->argument('accountId'));

        if (! $account) {
            $this->error('Account not found');
            return self::FAILURE;
        }

        $password = $this->pullPassword($account);

        if ($password === null) {
            $account->update([
                'status' => TelegramAccount::STATUS_PASSWORD_INVALID,
                'last_error' => 'PASSWORD_EXPIRED',
            ]);

            $this->error('No password waiting for this account (expired?)');
            return self::FAILURE;
        }

        if (! $account->hasSessionFile()) {
            $account->update([
                'status' => TelegramAccount::STATUS_FAILED,
                'last_error' => 'SESSION_NOT_FOUND',
            ]);

            $this->error("Session not found: {$account->session_path}");
            return self::FAILURE;
        }

        try {
            $Madeline = $this->openSession($account);

            $authorization = $Madeline->complete2faLogin($password);

            if (isset($authorization['_']) && $authorization['_'] === 'account.needSignup') {
                throw new \Exception('ACCOUNT_NOT_REGISTERED');
            }

            $this->markAuthorized($account, $Madeline);

            $this->info("✅ 2FA passed for {$account->phone}");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 1000);

            /*
             * Unlike a wrong code, a wrong password leaves MadelineProto
             * waiting for the password, so another try is possible.
             */
            $status = str_contains($message, 'PASSWORD_HASH_INVALID')
                ? TelegramAccount::STATUS_PASSWORD_INVALID
                : TelegramAccount::STATUS_FAILED;

            $account->update([
                'status' => $status,
                'last_error' => $message,
            ]);

            Log::error('telegram:2fa failed', [
                'account_id' => $account->id,
                'phone' => $account->phone,
                'error' => $message,
            ]);

            $this->error("❌ {$message}");
            return self::FAILURE;
        }
    }

    private function pullPassword(TelegramAccount $account): ?string
    {
        $encrypted = Cache::pull(TelegramAccount::passwordCacheKey($account->id));

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }
}
