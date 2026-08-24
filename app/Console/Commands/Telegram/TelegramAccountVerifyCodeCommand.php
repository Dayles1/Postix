<?php

namespace App\Console\Commands\Telegram;

use App\Models\Telegram\TelegramAccount;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo as MadelineAppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramAccountVerifyCodeCommand extends Command
{
    protected $signature = 'tc {phone} {code}';
    protected $description = 'Verify Telegram login code for a phone number';

    public function handle(): int
    {
        $phone = $this->normalizePhone((string) $this->argument('phone'));
        $code = (string) $this->argument('code');

        $account = TelegramAccount::where('phone', $phone)->first();

        if (! $account) {
            $this->error("Account not found for {$phone}");
            return self::FAILURE;
        }

        $sessionPath = $account->session_path;

        if (! file_exists($sessionPath) && ! is_dir($sessionPath)) {
            $account->update([
                'status' => 'failed',
            ]);

            $this->error("Session not found: {$sessionPath}");
            return self::FAILURE;
        }

        try {
            $Madeline = new API($sessionPath, $this->buildSettings());

            $authorization = $Madeline->completePhoneLogin($code);

            if (isset($authorization['_']) && $authorization['_'] === 'account.password') {
                $account->update([
                    'status' => 'need_password',
                ]);

                $this->info("🔐 2FA password required for {$phone}");
                return self::SUCCESS;
            }

            if (isset($authorization['_']) && $authorization['_'] === 'account.needSignup') {
                throw new \Exception('ACCOUNT_NOT_REGISTERED');
            }

            $self = $Madeline->getSelf();

            $account->update([
                'is_authorized' => true,
                'status' => 'success',
            ]);

            $this->info("✅ Code verified for {$phone}");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 1000);

            $account->increment('error_count');
            $account->update([
                'status' => 'failed',
            ]);

            Log::error('telegram:verify-code failed', [
                'phone' => $phone,
                'error' => $message,
            ]);

            $this->error("❌ {$message}");
            return self::FAILURE;
        }
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        if ($phone !== '' && $phone[0] !== '+') {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    protected function buildSettings(): Settings
    {
        $settings = new Settings();

        $appInfo = new MadelineAppInfo();
        $appInfo->setApiId((int) env('TELEGRAM_API_ID'));
        $appInfo->setApiHash((string) env('TELEGRAM_API_HASH'));

        $appInfo
            ->setDeviceModel('Server')
            ->setLangCode(config('app.locale', 'en'))
            ->setSystemLangCode('en')
            ->setShowPrompt(false);

        $settings->setAppInfo($appInfo);

        $loggerSettings = (new LoggerSettings())->setType(Logger::ERROR);
        $settings->setLogger($loggerSettings);

        return $settings;
    }
}