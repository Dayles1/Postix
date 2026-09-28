<?php

namespace App\Console\Commands\Telegram;

use App\Console\Commands\Telegram\Concerns\OpensTelegramAccountSession;
use App\Models\Telegram\TelegramAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramAccountVerifyCodeCommand extends Command
{
    use OpensTelegramAccountSession;

    protected $signature = 'tc {phone} {code}';
    protected $description = 'Verify Telegram login code for a phone number';

    public function handle(): int
    {
        $phone = TelegramAccount::normalizePhone((string) $this->argument('phone'));
        $code = (string) $this->argument('code');

        $account = TelegramAccount::where('phone', $phone)->first();

        if (! $account) {
            $this->error("Account not found for {$phone}");
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

            $authorization = $Madeline->completePhoneLogin($code);

            if (isset($authorization['_']) && $authorization['_'] === 'account.password') {
                $account->update([
                    'status' => TelegramAccount::STATUS_NEED_PASSWORD,
                    'password_hint' => $authorization['hint'] ?? null,
                    'last_error' => null,
                ]);

                $this->info("🔐 2FA password required for {$phone}");
                return self::SUCCESS;
            }

            if (isset($authorization['_']) && $authorization['_'] === 'account.needSignup') {
                throw new \Exception('ACCOUNT_NOT_REGISTERED');
            }

            $this->markAuthorized($account, $Madeline);

            $this->info("✅ Code verified for {$phone}");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 1000);

            /*
             * Told apart from other failures only for the panel's sake:
             * the login itself is gone either way (see TelegramAccount),
             * and the next step is always a fresh code.
             */
            $status = str_contains($message, 'PHONE_CODE_INVALID')
                ? TelegramAccount::STATUS_CODE_INVALID
                : TelegramAccount::STATUS_FAILED;

            $account->update([
                'status' => $status,
                'last_error' => $message,
            ]);

            Log::error('telegram:verify-code failed', [
                'phone' => $phone,
                'error' => $message,
            ]);

            $this->error("❌ {$message}");
            return self::FAILURE;
        }
    }
}
