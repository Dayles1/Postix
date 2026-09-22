<?php

namespace App\Application\Telegram\Services;

use App\Application\Telegram\Support\VendorNoticeShield;
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
            /*
             * Starting a session walks a lot of library code, and a PHP
             * notice raised anywhere in it would otherwise become an
             * exception and cost us an account for the attempt. See
             * VendorNoticeShield.
             */
            $api = VendorNoticeShield::guard(
                'madeline.start',
                function () use ($account): API {
                    $api = new API(
                        $account->session_path,
                        $this->settings()
                    );

                    Log::debug('MadelineProto API created', [
                        'account_id' => $account->id,
                        'phone' => $account->phone,
                    ]);

                    $api->start();

                    return $api;
                },
                [
                    'account_id' => $account->id,
                    'phone' => $account->phone,
                ],
            );

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

    /**
     * Throw away a session and open a fresh one for the same account.
     *
     * An Amp cancellation ("The operation was cancelled", wrapping a
     * timeout on the MTProto call) leaves the session in a state where
     * the next call through the same instance tends to be cancelled the
     * same way: the connection behind it is the thing that is wedged,
     * not the account. Re-running the resolve on a new instance is what
     * turns that into a second real chance instead of a wasted account.
     *
     * The old instance is released first so its destructor can close
     * the socket and write the session file back; MadelineProto has no
     * synchronous "close" to call here, so dropping the last reference
     * and collecting cycles is the whole of it.
     */
    public function restart(TelegramAccount $account, ?API $api = null): ?API
    {
        Log::warning('MadelineProto session restarting', [
            'account_id' => $account->id,
            'phone' => $account->phone,
        ]);

        if ($api !== null) {
            try {
                unset($api);

                gc_collect_cycles();
            } catch (Throwable $e) {
                Log::warning('MadelineProto session could not be released cleanly', [
                    'account_id' => $account->id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $this->for($account);
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