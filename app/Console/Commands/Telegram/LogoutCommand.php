<?php

namespace App\Console\Commands\Telegram;

use App\Models\Messages\TelegramScheduledMessage;
use App\Models\Telegram\TelegramAccount;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class LogoutCommand extends Command
{
    protected $signature = 'telegram:logout {accountId}';

    protected $description = 'Logout telegram account and cleanup session';

    public function handle(): int
    {
        $accountId = (int) $this->argument('accountId');

        $account = TelegramAccount::find($accountId);

        if (! $account) {
            $this->error("Account {$accountId} not found");

            return self::FAILURE;
        }


        Log::info('Telegram logout started', [
            'account_id' => $account->id,
            'phone' => $account->phone,
        ]);

        $sessionPath = $account->session_path;

        if ($sessionPath && file_exists($sessionPath)) {
            try {
                $api = $this->madeline($sessionPath);

                $api->start();

                $api->logOut();

                Log::info('Telegram logout successful', [
                    'account_id' => $account->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Telegram logout failed', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            if ($sessionPath && File::exists($sessionPath)) {
                if (File::isDirectory($sessionPath)) {
                    File::deleteDirectory($sessionPath);
                } else {
                    File::delete($sessionPath);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Session delete failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }

        $account->update([
            'status' => 'logged_out',
            'is_authorized' => false,
            'session_path' => null,
        ]);

        $this->info("Telegram account {$account->phone} logged out");

        return self::SUCCESS;
    }

    

    private function madeline(string $sessionPath): API
    {
        $settings = new Settings();

        $appInfo = new AppInfo();

        $appInfo->setApiId(
            (int) env('TELEGRAM_API_ID')
        );

        $appInfo->setApiHash(
            env('TELEGRAM_API_HASH')
        );

        $settings->setAppInfo($appInfo);

        $logger = new LoggerSettings();

        $logger->setType(Logger::FILE_LOGGER);
        $logger->setLevel(Logger::ERROR);

        $settings->setLogger($logger);

        return new API($sessionPath, $settings);
    }
}