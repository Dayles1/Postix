<?php

namespace App\Console\Commands;

use App\Models\TelegramAuthSession;
use App\Models\User;
use App\Models\UserPhone;
use Carbon\Carbon;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TelegramVerifyCodeCommand extends Command
{
    protected $signature = 'telegram:verify {phone} {userId} {code} {sessionId}';
    protected $description = 'Verify Telegram login code for a given phone';

    protected function madeline($phone, $userId)
    {
        $sessionPath = storage_path("app/sessions/{$phone}_user_{$userId}.madeline");

        if (!is_dir(dirname($sessionPath))) {
            mkdir(dirname($sessionPath), 0755, true);
        }

        $settings = new Settings();

        $appInfo = new AppInfo();
        $appInfo->setApiId((int) env('TELEGRAM_API_ID'));
        $appInfo->setApiHash(env('TELEGRAM_API_HASH'));
        $settings->setAppInfo($appInfo);

        $loggerSettings = (new LoggerSettings())
            ->setType(Logger::ERROR);

        $settings->setLogger($loggerSettings);

        return new API($sessionPath, $settings);
    }


    public function handle()
    {
        Log::info("TelegramVerifyCodeCommand started");

        $phone    = $this->argument('phone');
        $userId   = $this->argument('userId');
        $code     = $this->argument('code');
        $sessionId =$this->argument('sessionId');
        $session = TelegramAuthSession::find($sessionId);

        $sessionPath = storage_path("app/sessions/{$phone}_user_{$userId}.madeline");

        
        if (!file_exists($sessionPath) && !is_dir($sessionPath)) {
            $session->update([
                'status' => 'failed',
                'message_key' => 'session_not_found',
                'message' => null,
            ]);
            return;
        }

        $Madeline = $this->madeline($phone, $userId);

        try {
            Log::info("Completing login for {$phone} with code {$code}");

            $authorization = $Madeline->completePhoneLogin($code);

            

            if (isset($authorization['_']) && $authorization['_'] === 'account.password') {
                $session->update([
                    'status' => 'need_password',
                    'message_key' => '2fa_password_required',
                    'message' => null,
                ]);
                return;
            }

            if ($authorization['_'] === 'account.noPassword') {
                throw new \Exception('2FA yoqilgan, lekin parol o‘rnatilmagan!');
            }
            if ($authorization['_'] === 'account.needSignup') {
                throw new \Exception("Bu raqam Telegram ro‘yxatidan o‘tmagan!");
            }

            $tgId = $Madeline->getSelf()['id'] ?? null;

            User::where('id', $userId)
                ->whereNull('telegram_id')
                ->update(['telegram_id' => $tgId]);

            $createdPhone=UserPhone::updateOrCreate(
                [
                    'user_id' => $userId,
                    'phone'   => $phone,
                ],
                [
                    'telegram_user_id' => $tgId,
                    'session_path'      => $sessionPath,
                    'is_active'         => true,
                ]
            );
            $session->update([
                'status' => 'success',
                'telegram_user_id' => $tgId,
                'session_path' => $sessionPath,
                'message_key' => 'telegram_verified',
                'message' => null,
            ]);
                // $createdPhoneId = $createdPhone->id;


            // $workerName = "telegram-worker-{$userId}";
            // exec("supervisorctl start {$workerName}");
            // Log::info("Supervisor worker started for {$phone} (id: {$userId})");
            if ($authorization) {
                $this->info("✅ {$phone} verified successfully");

                // try {
                //     if ($createdPhoneId) {
                //         // $pid = $this->startSessionDaemon($createdPhoneId);
                //         if ($pid) {
                //             Log::info("Started session daemon for user_phone_id={$createdPhoneId}, pid={$pid}");
                //         } else {
                //             Log::info("Session daemon already running (or failed to start) for user_phone_id={$createdPhoneId}");
                //         }
                //     } else {
                //         Log::error("CreatedPhoneId not found, cannot start session daemon for phone {$phone}");
                //     }
                // } catch (\Throwable $e) {
                //     Log::error("Failed to start session daemon for user_phone_id={$createdPhoneId}: " . $e->getMessage());
                // }


            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'PHONE_CODE_INVALID')) {
                $session->update([
                    'status' => 'failed',
                    'message' => 'PHONE_CODE_INVALID',
                ]);
                Log::error("PHONE_CODE_INVALID received", ['exception' => $e, 'phone' => $phone]);
                $this->error("❌ PHONE_CODE_INVALID: " . $message);
            } else {
                $session->update([
                    'status' => 'failed',
                    'message' => $message,
                ]);
                Log::error("VERIFY ERROR: " . $message, ['exception' => $e, 'phone' => $phone]);
            }
        }
    }
    private function startSessionDaemon(int $userPhoneId): ?int
{
    // путь до php CLI — используй свой путь, или PHP_BINARY
    $phpBin = defined('PHP_BINARY') ? PHP_BINARY : '/usr/bin/php';
    // путь до artisan
    $artisan = base_path('artisan');

    // подготовка директорий
    $pidDir = storage_path('app/session_pids');
    $logDir = storage_path('logs');
    if (!is_dir($pidDir)) mkdir($pidDir, 0755, true);
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);

    $pidFile = $pidDir . "/tg_{$userPhoneId}.pid";
    $logFile = $logDir . "/telegram/session_{$userPhoneId}.log";

    // если уже запущен — ничего не делаем
    if ($this->isProcessRunning($pidFile)) {
        return null;
    }

    $cmd = sprintf(
        'nohup %s %s telegram:session %d >> %s 2>&1 & echo $!',
        escapeshellcmd($phpBin),
        escapeshellarg($artisan),
        $userPhoneId,
        escapeshellarg($logFile)
    );

    $output = [];
    exec($cmd, $output);
    $pid = (int)($output[0] ?? 0);

    if ($pid > 0) {
        @file_put_contents($pidFile, $pid);
        return $pid;
    }

    return null;
}

/**
 * Проверяет: есть ли pid-файл и жив ли процесс.
 * Возвращает true если процесс активен.
 */
private function isProcessRunning(string $pidFile): bool
{
    if (!file_exists($pidFile)) {
        return false;
    }
    $pid = (int)@file_get_contents($pidFile);
    if ($pid <= 0) {
        @unlink($pidFile);
        return false;
    }

    if (function_exists('posix_kill')) {
        $exists = posix_kill($pid, 0);
        if (!$exists) {
            @unlink($pidFile);
            return false;
        }
        return true;
    }

    if (is_dir("/proc/{$pid}")) {
        return true;
    }

    @unlink($pidFile);
    return false;
}
}
