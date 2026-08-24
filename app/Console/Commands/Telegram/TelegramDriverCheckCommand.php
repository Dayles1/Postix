<?php

namespace App\Console\Commands\Telegram;

use App\Models\Telegram\TelegramAccount;
use App\Telegram\TelegramDriverCheckHandler;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramDriverCheckCommand extends Command
{
    protected $signature = 'telegram:start-loop';

    protected $description = 'Start Telegram driver check listener';

    public function handle(): int
    {
        $accountId = config(
            'services.telegram.driver_check_account_id'
        );

        if (!$accountId) {
            $this->error(
                'TELEGRAM_DRIVER_CHECK_ACCOUNT_ID is not configured.'
            );

            return self::FAILURE;
        }

        // $chatLink = trim(
        //     (string) config(
        //         'services.telegram.driver_check_chat_link'
        //     )
        // );

        $chatLink = 'https://t.me/+HFNjpKIyW-owYTJi'; 
        if ($chatLink === '') {
            $this->error(
                'TELEGRAM_DRIVER_CHECK_CHAT_LINK is not configured.'
            );

            return self::FAILURE;
        }

        $account = TelegramAccount::query()
            ->whereKey((int) $accountId)
            ->first();

        if (!$account) {
            $this->error(
                "Telegram account #{$accountId} not found."
            );

            return self::FAILURE;
        }

        if (!$account->is_authorized) {
            $this->error(
                "Telegram account #{$account->id} is not authorized."
            );

            return self::FAILURE;
        }

        if (!$account->session_path) {
            $this->error(
                "Session path is empty for account #{$account->id}."
            );

            return self::FAILURE;
        }

        if (!File::exists($account->session_path)) {
            $this->error(
                "Session path not found: {$account->session_path}"
            );

            return self::FAILURE;
        }

        $account->update([
            'status' => 'running',
        ]);

        Log::info(
            'Telegram driver check listener starting',
            [
                'account_id' => $account->id,
                'phone' => $account->phone,
                'chat_link' => $chatLink,
                'session_path' => $account->session_path,
            ]
        );

        try {
            TelegramDriverCheckHandler::startAndLoop(
                $account->session_path,
                $this->buildSettings()
            );

            $account->update([
                'status' => 'stopped',
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $error = mb_substr(
                $e->getMessage(),
                0,
                1000
            );

            $account->update([
                'status' => 'stopped',
            ]);

            Log::critical(
                'Telegram driver check listener crashed',
                [
                    'account_id' => $account->id,
                    'phone' => $account->phone,
                    'error' => $error,
                    'exception' => $e::class,
                ]
            );

            $this->error(
                "Telegram driver check listener crashed: {$error}"
            );

            return self::FAILURE;
        }
    }


    private function buildSettings(): Settings
    {
        $settings = new Settings();

        $appInfo = new AppInfo();
        $appInfo->setApiId(
            (int) config('services.telegram.api_id')
        );
        $appInfo->setApiHash(
            (string) config('services.telegram.api_hash')
        );
        $appInfo->setLangCode(config('app.locale', 'en'));
        $appInfo->setSystemLangCode('en');
        $appInfo->setShowPrompt(false);

        $settings->setAppInfo($appInfo);

        $loggerSettings = (new LoggerSettings())
            ->setType(Logger::FILE_LOGGER)
            ->setExtra(storage_path('logs/madeline.log'));
        $loggerSettings->setMaxSize(50 * 1024 * 1024);
        $settings->setLogger($loggerSettings);

        return $settings;
    }
}