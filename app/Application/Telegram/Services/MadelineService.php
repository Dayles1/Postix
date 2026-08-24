<?php

namespace App\Application\Telegram\Services;

use App\Models\Telegram\TelegramAccount;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use Illuminate\Support\Facades\Log;
use Throwable;

class MadelineService
{
    /**
     * Create and start MadelineProto for the given account.
     *
     * Returns null when the account cannot be started.
     */
    public function for(TelegramAccount $account): ?API
    {
        Log::info('MadelineProto connection starting', [
            'account_id' => $account->id,
            'phone' => $account->phone,
            'session_path' => $account->session_path,
        ]);

        if (! $account->session_path) {
            Log::error('MadelineProto session path is missing', [
                'account_id' => $account->id,
                'phone' => $account->phone,
            ]);

            return null;
        }

        if (! file_exists($account->session_path)) {
            Log::error('MadelineProto session file or directory does not exist', [
                'account_id' => $account->id,
                'phone' => $account->phone,
                'session_path' => $account->session_path,
            ]);

            return null;
        }

        try {
            $api = new API(
                $account->session_path,
                $this->settings()
            );

            Log::debug('MadelineProto API created', [
                'account_id' => $account->id,
                'phone' => $account->phone,
            ]);

            $api->start();

            Log::info('MadelineProto started successfully', [
                'account_id' => $account->id,
                'phone' => $account->phone,
            ]);

            return $api;
        } catch (Throwable $e) {
            Log::error('MadelineProto start failed', [
                'account_id' => $account->id,
                'phone' => $account->phone,
                'session_path' => $account->session_path,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function settings(): Settings
    {
        $settings = new Settings();

        $appInfo = new AppInfo();

        $appInfo->setApiId(
            (int) config('services.telegram.api_id')
        );

        $appInfo->setApiHash(
            (string) config('services.telegram.api_hash')
        );

        $settings->setAppInfo($appInfo);

        $logger = new LoggerSettings();

        $logger->setType(Logger::FILE_LOGGER);
        $logger->setLevel(Logger::ERROR);

        $settings->setLogger($logger);

        return $settings;
    }
}