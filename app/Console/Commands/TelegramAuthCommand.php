<?php

namespace App\Console\Commands;

use App\Models\TelegramAuthSession;
use App\Models\UserPhone;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo as MadelineAppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TelegramAuthCommand extends Command
{
    // --status option qo'shildi, default add_phone
    protected $signature = 'telegram:auth {phone} {userId} {sessionId} {--status=add_phone}';
    protected $description = 'Send Telegram auth code to a phone number directly, without queue';

    public function handle()
    {
        $phone = $this->normalizePhone($this->argument('phone'));
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

        if ($status === 'add_user') {
            $sessionPath = storage_path("app/sessions/{$phone}_add_user_{$userId}.madeline");
        } else {
            $sessionPath = storage_path("app/sessions/{$phone}_user_{$userId}.madeline");
        }

        // Old sessionni tozalash
        if (file_exists($sessionPath)) {
            if (is_dir($sessionPath)) {
                \Illuminate\Support\Facades\File::deleteDirectory($sessionPath);
            } else {
                @unlink($sessionPath);
            }
            Log::info("Deleted existing session at {$sessionPath}");
            // qisqaroq kutish
            sleep(1);
        }

        if (!is_dir(dirname($sessionPath))) {
            mkdir(dirname($sessionPath), 0777, true);
            Log::info("Session directory created at " . dirname($sessionPath));
        }

        // MadelineProto settings tayyorlash
        $settings = new Settings();

        // Logger sozlamalari: faylga yozilsin, joyini belgilaymiz va maksimal hajm (agar klass buni qo'llab-quvvatlasa)
        // $loggerSettings = (new LoggerSettings())
        //     ->setType(Logger::FILE_LOGGER)
        //     ->setExtra(storage_path('logs/madelineproto.log'));
        // // setMaxSize metod mavjud bo'lsa chaqiramiz (ba'zi versiyalarda bo'lishi mumkin)
        // if (method_exists($loggerSettings, 'setMaxSize')) {
        //     $loggerSettings->setMaxSize(50 * 1024 * 1024); // 50 MB
        // }
        // $settings->setLogger($loggerSettings);

        // AppInfo bilan to'ldirish — lekin system_lang_code telefon mamlakatiga moslanadi
        $systemLang = $this->selectSystemLangByPhone($phone);
        $appInfo = $this->buildAppInfo($systemLang);
        $settings->setAppInfo($appInfo);

        // DB sessionni update
        $session = TelegramAuthSession::find($sessionId);
        if (! $session) {
            Log::warning("TelegramAuthSession not found", compact('sessionId'));
            $this->error("Session with id {$sessionId} not found.");
            // davom etmaymiz
            return 1;
        }
        $session->update(['status' => 'processing', 'last_ping' => now()]);

        // Madeline yaratish va login
        try {
            $Madeline = new API($sessionPath, $settings);

            Log::info("Attempting phone login", ['phone' => $phone, 'status' => $status]);
            $response = $Madeline->phoneLogin($phone);
            Log::info('phoneLogin response', ['response' => $response]);

            $session->update(['status' => 'sms_sent', 'message_key' => 'sms_sent']);
            $this->info("SMS code request sent for {$phone}");
        } catch (Throwable $e) {
            // $session->update(['status' => 'failed', 'message' => $e->getMessage(), 'message_key' => null]);
            $message = Str::limit($e->getMessage(), 1000);

            $session->update([
                'status' => 'failed',
                'message' => $message,
            ]);
            Log::error("Error sending code", ['exception' => $e, 'phone' => $phone, 'status' => $status]);
            $this->error("Failed to send SMS code: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Build AppInfo object with sensible defaults and given systemLangCode
     *
     * @param string $systemLangCode
     * @return MadelineAppInfo
     */
    protected function buildAppInfo(string $systemLangCode = 'en'): MadelineAppInfo
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
            // ->setSystemVersion($systemVersion)
            // ->setAppVersion($appVersion)
            ->setLangCode($langCode)
            ->setSystemLangCode($systemLangCode)
            ->setShowPrompt(false);

        Log::info('AppInfo built', [
            'api_id' => $apiId,
            'api_hash' => $apiHash ? '***hidden***' : null,
            'device_model' => $deviceModel,
            'system_version' => $systemVersion,
            'app_version' => $appVersion,
            'lang_code' => $langCode,
            'system_lang_code' => $systemLangCode,
        ]);

        return $appInfo;
    }

    /**
     * Telefon raqamdan country code asosida system_lang_code tanlaydi.
     * Oddiy mapping; kerak bo'lsa kengaytiramiz.
     *
     * @param string $phoneNormalized — +998... yoki 998... ko'rinishida
     * @return string
     */
    protected function selectSystemLangByPhone(string $phoneNormalized): string
    {
        // mapping — kerak bo'lsa qo'shing
        $map = [
            '998' => 'uz', // Uzbekistan: ru ishlaganini chatdan topdik; 'uz'ni ham sinab ko'rish mumkin
            '992' => 'ru', // Tajikistan
            '7'   => 'ru', // Russia / Kazakhstan (bazida +7 ishlaydi)
            '1'   => 'en', // USA/Canada
            '44'  => 'en', // UK
        ];

        // raqamni normalizatsiya: olib tashla barcha not-digits (faqat + dan tashqari)
        $num = preg_replace('/[^\d]/', '', $phoneNormalized);

        // tekshirish: 3,2,1 raqamli prefikslarga qaraymiz (uzoq country codelardan boshlaymiz)
        $prefixes = array_keys($map);
        // sort by length desc to match longer codes first (e.g. 998 before 9)
        usort($prefixes, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($prefixes as $p) {
            if (strpos($num, $p) === 0) {
                return $map[$p];
            }
        }

        // default
        return 'en';
    }

    /**
     * Normalize phone to always include leading + if possible
     *
     * @param string $phone
     * @return string
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        // agar boshlanishi + bo'lmasa va faqat raqamlardan iborat bo'lsa qo'shamiz
        if (preg_match('/^\+?\d+$/', $phone)) {
            if ($phone[0] !== '+') {
                return '+' . $phone;
            }
            return $phone;
        }
        // boshqa holatlarda original qaytadi
        return $phone;
    }

    protected function detectDeviceModel(): string
    {
        if ($val = env('MADLINE_DEVICE_MODEL')) {
            return $val;
        }
        try {
            return php_uname('n') . ' ' . php_uname('s');
        } catch (Throwable $e) {
            return 'VPS Server';
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
        } catch (Throwable $e) {
            $laravelVer = 'Laravel';
        }
        $appName = env('APP_NAME', 'MyApp');

        return "{$appName} {$laravelVer} (PHP " . phpversion() . ")";
    }
}
