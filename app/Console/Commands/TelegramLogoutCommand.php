<?php

namespace App\Console\Commands;

use App\Models\UserPhone;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;

class TelegramLogoutCommand extends Command
{
    protected $signature = 'telegram:logout {userPhoneId}';
    protected $description = 'Logout Telegram for a given phone ID and clean worker/session artifacts';

    protected function madeline($sessionPath)
    {
        $settings = new \danog\MadelineProto\Settings();

        $appInfo = new AppInfo();
        $apiId = (int) env('TELEGRAM_API_ID', 0);
        $apiHash = env('TELEGRAM_API_HASH', '');
        if (!$apiId || !$apiHash) {
            // throw new \RuntimeException("TELEGRAM_API_ID or TELEGRAM_API_HASH not set");
            Log::warning("TELEGRAM_API_ID or TELEGRAM_API_HASH not set, assuming bot token session");
        }
        $appInfo->setApiId($apiId);
        $appInfo->setApiHash($apiHash);
        $settings->setAppInfo($appInfo);

        $loggerSettings = (new LoggerSettings())->setType(Logger::ERROR);
        $settings->setLogger($loggerSettings);

        return new API($sessionPath, $settings);
    }
    public function handle()
    {
        $userPhoneId = (int) $this->argument('userPhoneId');

        $userPhone = UserPhone::find($userPhoneId);
        $this->failAllPendingMessages($userPhone);

        Log::info("TelegramLogoutCommand started for phone ID {$userPhoneId}");

        if (!$userPhone) {
            $this->error("❌ UserPhone with ID {$userPhoneId} not found");
            return;
        }

        $sessionPath = $userPhone->session_path;

        if ($sessionPath && file_exists($sessionPath)) {
            try {
                $Madeline = $this->madeline($sessionPath);
                $Madeline->logOut();
                Log::info("Logged out from Telegram for phone ID {$userPhoneId} (direct attempt)");
            } catch (\Throwable $e) {
                Log::warning("Madeline logOut failed for userPhoneId={$userPhoneId}: " . $e->getMessage());
            }
        } else {
            Log::info("Session file for phone ID {$userPhoneId} not found or already removed");
        }

        try {
            if ($sessionPath && File::exists($sessionPath)) {
                if (File::isDirectory($sessionPath)) {
                    File::deleteDirectory($sessionPath);
                    Log::info("Deleted session directory {$sessionPath}");
                } else {
                    File::delete($sessionPath);
                    Log::info("Deleted session file {$sessionPath}");
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to delete session at {$sessionPath}: " . $e->getMessage());
        }

        try {
            $userPhone->update(['session_path' => null, 'is_active' => false]);
        } catch (\Throwable $e) {
            Log::warning("Failed to update UserPhone DB for id={$userPhoneId}: " . $e->getMessage());
        }

        $this->info("✅ Telegram logged out and session cleared for phone ID {$userPhoneId}");
    }
    protected function failAllPendingMessages($userPhone)
    {
        $messageGroups = $userPhone->messageGroups()->whereIn('status', ['pending'])->get();
        foreach ($messageGroups as $group) {
            $messages= $group->messages()->whereIn('status', ['pending'])->get();
            foreach($messages as $message){
                $message->status = 'failed';
                $message->save();
            }
            $group->status = 'failed';
            $group->save();
        }
        Log::info("Marked all pending messages as failed for phone ID {$userPhone->id}");
    }

}
