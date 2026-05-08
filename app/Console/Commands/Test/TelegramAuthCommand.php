<?php

namespace App\Console\Commands\Test;

use App\Models\TelegramAuthSession;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use danog\MadelineProto\Settings\AppInfo as MadelineAppInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramAuthCommand extends Command
{
    // --status option qo'shildi, default add_phone
    protected $signature = 'telegram:test_auth {phone} {userId} {sessionId} {--status=add_phone}';
    protected $description = 'Send Telegram auth code to a phone number directly, without queue';

    public function handle()
    {
        $phone = $this->argument('phone');
        $userId = $this->argument('userId');
        $sessionId = $this->argument('sessionId');
        $status = $this->option('status') ?? 'add_phone';

        $allowed = ['add_phone', 'add_user'];
        if (!in_array($status, $allowed, true)) {
            $this->error("Invalid status: {$status}. Allowed: " . implode(', ', $allowed));
            Log::warning("telegram:auth called with invalid status", compact('phone', 'userId', 'status'));
            return 1;
        }

        Log::info("TelegramAuthCommand started", ['phone' => $phone, 'userId' => $userId, 'status' => $status]);
        $this->info("Starting Telegram auth for {$phone} (status: {$status})");

        $sessionPath = storage_path("app/sessions/{$phone}_user_{$userId}.madeline");

        // old sessionni tozalash
        if (file_exists($sessionPath)) {
            if (is_dir($sessionPath)) {
                \Illuminate\Support\Facades\File::deleteDirectory($sessionPath);
            } else {
                @unlink($sessionPath);
            }
            Log::info("Deleted existing session at {$sessionPath}");
            // biroz kutish, IPC / fayllar tozalanishi uchun
            sleep(1);
        }

        // session papkasi yo'q bo'lsa yaratish
        if (!is_dir(dirname($sessionPath))) {
            mkdir(dirname($sessionPath), 0777, true);
            Log::info("Session directory created at " . dirname($sessionPath));
        }

        // MadelineProto settings tayyorlash
        $settings = new Settings();

        // Logger sozlamalari: faylga yozilsin, joyini belgilaymiz va maksimal hajm
        $loggerSettings = (new LoggerSettings())
            ->setType(Logger::FILE_LOGGER)
            // setExtra qabul qiladi: callable yoki string. Bu yerda log fayl yo'li berildi.
            ->setExtra(storage_path('logs/madelineproto.log'))
            ->setMaxSize(50 * 1024 * 1024); // 50 MB
        $settings->setLogger($loggerSettings);

        // AppInfo to'liq qurdik (API id/hash, device model, system version, app version, til kodi)
        $appInfo = $this->buildAppInfo();
        $settings->setAppInfo($appInfo);

        // update DB session status
        $session = TelegramAuthSession::find($sessionId);
        if ($session) {
            $session->update(['status' => 'processing', 'last_ping' => now()]);
        } else {
            Log::warning("TelegramAuthSession not found", compact('sessionId'));
        }

        // yaratish va login sinovi
        try {
            $Madeline = new API($sessionPath, $settings);

            Log::info("Attempting phone login", ['phone' => $phone, 'status' => $status]);
            $response = $Madeline->phoneLogin($phone);
            Log::info('phoneLogin response', ['response' => $response]);

            if ($session) {
                $session->update(['status' => 'sms_sent', 'message_key' => 'sms_sent']);
            }
            $this->info("SMS code request sent for {$phone}");
        } catch (Throwable $e) {
            Log::error("Error sending code", ['exception' => $e, 'phone' => $phone, 'status' => $status]);
            if ($session) {
                $session->update(['status' => 'failed', 'message' => $e->getMessage(), 'message_key' => null]);
            }
            $this->error("Failed to send SMS code: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * buildAppInfo
     * MadelineProto\AppInfo obyektini to'liq va aniq qiymatlar bilan qaytaradi.
     * (Doc: AppInfo methods: setApiId, setApiHash, setDeviceModel, setSystemVersion, setAppVersion, setLangCode, setSystemLangCode, setShowPrompt). :contentReference[oaicite:1]{index=1}
     *
     * @return MadelineAppInfo
     */
    protected function buildAppInfo(): MadelineAppInfo
    {
        $apiId = intval(env('TELEGRAM_API_ID', 0));
        $apiHash = env('TELEGRAM_API_HASH', '');

        $deviceModel = $this->detectDeviceModel();
        $systemVersion = $this->detectSystemVersion();
        $appVersion = $this->detectAppVersion();
        $langCode = config('app.locale', 'en');

        $appInfo = new MadelineAppInfo();
        if ($apiId) {
            $appInfo->setApiId($apiId);
        }
        if ($apiHash) {
            $appInfo->setApiHash($apiHash);
        }

        $appInfo
            ->setDeviceModel($deviceModel)
            ->setSystemVersion($systemVersion)
            ->setAppVersion($appVersion)
            ->setLangCode($langCode)
            ->setSystemLangCode($langCode)
            ->setShowPrompt(false);


        return $appInfo;
    }

    /**
     * detectDeviceModel
     * Php funksiyalari orqali device modelni aniqlaymiz, kerak bo'lsa env orqali override qilish imkoniyati.
     *
     * @return string
     */
    protected function detectDeviceModel(): string
    {
        if ($val = env('MADLINE_DEVICE_MODEL')) {
            return $val;
        }

        $uname = php_uname();
        return trim(preg_replace('/\s+/', ' ', $uname));
    }

    /**
     * detectSystemVersion
     *
     * @return string
     */
    protected function detectSystemVersion(): string
    {
        if ($val = env('MADLINE_SYSTEM_VERSION')) {
            return $val;
        }

        return 'PHP/' . phpversion() . ' ' . php_uname('v');
    }

    /**
     * detectAppVersion
     *
     * @return string
     */
    protected function detectAppVersion(): string
    {
        if ($val = env('MADLINE_APP_VERSION')) {
            return $val;
        }

        try {
            $laravelVer = app()->version();
        } catch (Throwable $e) {
            $laravelVer = 'Laravel';
        }

        $appName = env('APP_NAME', 'MyApp');

        return "{$appName} {$laravelVer} (PHP " . phpversion() . ")";
    }
}