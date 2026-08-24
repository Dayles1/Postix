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
use Illuminate\Support\Str;
use Throwable;

class TelegramAccountAuthCommand extends Command
{
    protected $signature = 'ta {phone}';
    protected $description = 'Send Telegram auth code for a phone number';

    public function handle(): int
    {
        $phone = $this->normalizePhone((string) $this->argument('phone'));
        $sessionPath = $this->sessionPath($phone);

        if (! is_dir(dirname($sessionPath))) {
            mkdir(dirname($sessionPath), 0775, true);
        }

        $account = TelegramAccount::firstOrCreate(
            ['phone' => $phone],
            [
                'session_path' => $sessionPath,
                'status' => 'created',
                'is_authorized' => false,
            ]
        );

        if ($account->session_path !== $sessionPath) {
            $account->update(['session_path' => $sessionPath]);
        }

        $account->update([
            'status' => 'processing',
        ]);

        try {
            $settings = new Settings();
            $settings->setAppInfo($this->buildAppInfo($phone));

            $loggerSettings = (new LoggerSettings())->setType(Logger::ERROR);
            $settings->setLogger($loggerSettings);

            $madeline = new API($sessionPath, $settings);

            $madeline->phoneLogin($phone);

            $account->update([
                'status' => 'code_sent',
            ]);

            $this->info("✅ SMS code sent to {$phone}");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $message = Str::limit($e->getMessage(), 1000);

            $account->update([
                'status' => 'failed',
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

    protected function sessionPath(string $phone): string
    {
        $safePhone = preg_replace('/\D+/', '', $phone);
        return storage_path("app/telegraSessions/telegram_{$safePhone}.madeline");
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        if ($phone !== '' && $phone[0] !== '+') {
            $phone = '+' . $phone;
        }

        return $phone;
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