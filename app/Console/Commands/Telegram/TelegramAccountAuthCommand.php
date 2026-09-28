<?php

namespace App\Console\Commands\Telegram;

use App\Models\Telegram\TelegramAccount;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo as MadelineAppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TelegramAccountAuthCommand extends Command
{
    protected $signature = 'ta {phone}';
    protected $description = 'Send Telegram auth code for a phone number';

    public function handle(): int
    {
        $phone = TelegramAccount::normalizePhone((string) $this->argument('phone'));
        $sessionPath = TelegramAccount::sessionPathFor($phone);

        $account = TelegramAccount::firstOrCreate(
            ['phone' => $phone],
            [
                'session_path' => $sessionPath,
                'status' => TelegramAccount::STATUS_CREATED,
                'is_authorized' => false,
            ]
        );

        if ($account->is_authorized) {
            $this->error("Account {$phone} is already authorized");
            return self::FAILURE;
        }

        /*
         * A session left behind by an earlier, unfinished login still
         * carries its old phone_code_hash, and phoneLogin() on top of it
         * fails. Nothing authorized lives in it, so it is thrown away.
         */
        $this->deleteSession($sessionPath);

        if (! is_dir(dirname($sessionPath))) {
            mkdir(dirname($sessionPath), 0775, true);
        }

        $account->update([
            'session_path' => $sessionPath,
            'status' => TelegramAccount::STATUS_PROCESSING,
            'password_hint' => null,
            'last_error' => null,
        ]);

        try {
            $settings = new Settings();
            $settings->setAppInfo($this->buildAppInfo($phone));

            $loggerSettings = (new LoggerSettings())->setType(Logger::ERROR);
            $settings->setLogger($loggerSettings);

            $madeline = new API($sessionPath, $settings);

            $madeline->phoneLogin($phone);

            $account->update([
                'status' => TelegramAccount::STATUS_CODE_SENT,
            ]);

            $this->info("✅ SMS code sent to {$phone}");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $message = Str::limit($e->getMessage(), 1000);

            $account->update([
                'status' => TelegramAccount::STATUS_FAILED,
                'last_error' => $message,
            ]);

            Log::error('telegram:auth failed', [
                'phone' => $phone,
                'error' => $message,
                'exception' => $e,
            ]);

            $this->error("❌ {$message}");
            return self::FAILURE;
        }
    }

    protected function buildAppInfo(string $phone = ''): MadelineAppInfo
    {
        $apiId = (int) env('TELEGRAM_API_ID', 0);
        $apiHash = (string) env('TELEGRAM_API_HASH', '');

        if ($apiId <= 0 || $apiHash === '') {
            throw new \RuntimeException('TELEGRAM_API_ID yoki TELEGRAM_API_HASH topilmadi.');
        }

        $deviceModel = $this->detectDeviceModel();
        $systemVersion = $this->detectSystemVersion();
        $appVersion = $this->detectAppVersion();
        $langCode = config('app.locale', 'en');
        $systemLangCode = $this->selectSystemLangByPhone($phone);

        $appInfo = new MadelineAppInfo();
        $appInfo
            ->setApiId($apiId)
            ->setApiHash($apiHash)
            ->setDeviceModel($deviceModel)
            ->setSystemVersion($systemVersion)
            ->setAppVersion($appVersion)
            ->setLangCode($langCode)
            ->setSystemLangCode($systemLangCode)
            ->setShowPrompt(false);

        Log::info('Madeline AppInfo built', [
            'api_id' => $apiId,
            'api_hash' => '***hidden***',
            'device_model' => $deviceModel,
            'system_version' => $systemVersion,
            'app_version' => $appVersion,
            'lang_code' => $langCode,
            'system_lang_code' => $systemLangCode,
        ]);

        return $appInfo;
    }

    protected function selectSystemLangByPhone(string $phoneNormalized): string
    {
        $map = [
            '998' => 'uz',
            '992' => 'ru',
            '7'   => 'ru',
            '1'   => 'en',
            '44'  => 'en',
        ];

        $num = preg_replace('/[^\d]/', '', $phoneNormalized);

        $prefixes = array_keys($map);
        usort($prefixes, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($prefixes as $prefix) {
            if (str_starts_with($num, $prefix)) {
                return $map[$prefix];
            }
        }

        return 'en';
    }

    protected function deleteSession(string $sessionPath): void
    {
        if (is_dir($sessionPath)) {
            File::deleteDirectory($sessionPath);
        } elseif (file_exists($sessionPath)) {
            @unlink($sessionPath);
        }
    }

    protected function detectDeviceModel(): string
    {
        if ($val = env('MADLINE_DEVICE_MODEL')) {
            return $val;
        }

        try {
            return php_uname('n') . ' ' . php_uname('s');
        } catch (Throwable) {
            return 'Server';
        }
    }

    protected function detectSystemVersion(): string
    {
        if ($val = env('MADLINE_SYSTEM_VERSION')) {
            return $val;
        }

        return 'PHP/' . phpversion() . ' ' . php_uname('v');
    }

    protected function detectAppVersion(): string
    {
        if ($val = env('MADLINE_APP_VERSION')) {
            return $val;
        }

        try {
            $laravelVer = app()->version();
        } catch (Throwable) {
            $laravelVer = 'Laravel';
        }

        $appName = env('APP_NAME', 'MyApp');

        return "{$appName} {$laravelVer} (PHP " . phpversion() . ")";
    }
}