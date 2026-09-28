<?php

namespace App\Console\Commands\Telegram\Concerns;

use App\Models\Telegram\TelegramAccount;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo as MadelineAppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;

/**
 * Opening an existing TelegramAccount session from a CLI command
 * (tc, tp, telegram:account-check). MadelineProto only runs on the CLI,
 * so this never belongs in a web request.
 */
trait OpensTelegramAccountSession
{
    protected function openSession(TelegramAccount $account): API
    {
        return new API($account->session_path, $this->sessionSettings());
    }

    protected function sessionSettings(): Settings
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

    /**
     * The login went through: remember who the session belongs to.
     */
    protected function markAuthorized(TelegramAccount $account, API $madeline): void
    {
        $self = $madeline->getSelf();

        $account->update([
            ...(is_array($self) ? TelegramAccount::profileFrom($self) : []),
            'is_authorized' => true,
            'authorized_at' => now(),
            'last_checked_at' => now(),
            'status' => TelegramAccount::STATUS_SUCCESS,
            'password_hint' => null,
            'last_error' => null,
        ]);
    }
}
