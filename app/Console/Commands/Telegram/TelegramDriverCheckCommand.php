<?php

namespace App\Console\Commands\Telegram;

use Amp\SignalException;
use App\Models\Telegram\TelegramAccount;
use App\Telegram\TelegramDriverCheckHandler;
use App\Telegram\TelegramListenerHealth;
use App\Telegram\TelegramProcessLock;
use danog\MadelineProto\API;
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
    /**
     * Another full instance already owns the MadelineProto session.
     *
     * Kept distinct from FAILURE so the watchdog can back off instead of
     * hammering a session it will never be able to open.
     */
    public const EXIT_ALREADY_RUNNING = 3;

    /**
     * Single-instance guard name, see TelegramProcessLock.
     */
    public const LOCK_NAME = 'driver-check-listener';

    protected $signature = 'telegram:start-loop';

    protected $description = 'Start Telegram driver check listener';

    public function handle(): int
    {
        /*
         * A marker left behind by a previous run must never be attributed to
         * this process, otherwise the watchdog reports the wrong reason.
         */
        TelegramListenerHealth::clear();

        $accountId = config(
            'services.telegram.driver_check_account_id'
        );

        if (!$accountId) {
            $this->error(
                'TELEGRAM_DRIVER_CHECK_ACCOUNT_ID is not configured.'
            );

            return self::INVALID;
        }

        $chatLink = trim(
            (string) config(
                'services.telegram.driver_check_chat_link'
            )
        );

        if ($chatLink === '') {
            $this->error(
                'TELEGRAM_DRIVER_CHECK_CHAT_LINK is not configured.'
            );

            return self::INVALID;
        }

        $account = TelegramAccount::query()
            ->whereKey((int) $accountId)
            ->first();

        if (!$account) {
            $this->error(
                "Telegram account #{$accountId} not found."
            );

            return self::INVALID;
        }

        if (!$account->is_authorized) {
            $this->error(
                "Telegram account #{$account->id} is not authorized."
            );

            return self::INVALID;
        }

        if (!$account->session_path) {
            $this->error(
                "Session path is empty for account #{$account->id}."
            );

            return self::INVALID;
        }

        if (!File::exists($account->session_path)) {
            $this->error(
                "Session path not found: {$account->session_path}"
            );

            return self::INVALID;
        }

        /*
         * A MadelineProto session tolerates exactly one full instance.
         * A second one degrades to an IPC client and API::reconnectFull()
         * returns false, which used to surface as a clean exit code 0.
         */
        $lockState = TelegramProcessLock::attempt(self::LOCK_NAME);

        if ($lockState === TelegramProcessLock::UNAVAILABLE) {
            /*
             * The lock file itself is broken (permissions, read-only storage).
             * That must never block a restart - log it loudly and carry on
             * without the guard.
             */
            Log::warning(
                'Telegram listener single-instance guard unavailable, continuing without it',
                [
                    'pid' => getmypid(),
                    'lock_file' => TelegramProcessLock::path(self::LOCK_NAME),
                    'error' => TelegramProcessLock::lastError(),
                ]
            );

            $this->warn(
                'Single-instance guard unavailable ('
                    . (TelegramProcessLock::lastError() ?? 'unknown')
                    . '), starting anyway.'
            );
        }

        if ($lockState === TelegramProcessLock::HELD) {
            $holderPid = TelegramProcessLock::holderPid(
                self::LOCK_NAME
            );

            Log::critical(
                'Telegram driver check listener already running',
                [
                    'account_id' => $account->id,
                    'pid' => getmypid(),
                    'holder_pid' => $holderPid,
                    'lock_file' => TelegramProcessLock::path(
                        self::LOCK_NAME
                    ),
                ]
            );

            $this->error(
                'Another telegram:start-loop instance is already running'
                    . ' (pid: ' . ($holderPid ?? 'unknown') . ').'
            );

            return self::EXIT_ALREADY_RUNNING;
        }

        $settings = $this->buildSettings();

        $startedAt = microtime(true);

        $context = [
            'pid' => getmypid(),
            'account_id' => $account->id,
            'phone' => $account->phone,
            'chat_link' => $chatLink,
            'session_path' => $account->session_path,
            'madelineproto_version' => API::RELEASE,
            'proxy_enabled' => $settings
                ->getConnection()
                ->getProxies() !== [],
            'started_at' => date('c'),
        ];

        $account->update([
            'status' => 'running',
        ]);

        Log::info(
            'Telegram driver check listener starting',
            $context
        );

        $this->info(
            '▶️ telegram:start-loop pid ' . getmypid()
                . ' account #' . $account->id
                . ' (MadelineProto ' . API::RELEASE . ')'
        );

        try {
            TelegramDriverCheckHandler::startAndLoop(
                $account->session_path,
                $settings
            );
        } catch (SignalException $e) {
            /*
             * SIGINT/SIGTERM/SIGQUIT: MadelineProto rethrows these through the
             * event loop error handler on purpose. This is the only exit that
             * is genuinely graceful.
             */
            return $this->finish(
                account: $account,
                context: $context,
                startedAt: $startedAt,
                kind: 'graceful_stop',
                exitCode: self::SUCCESS,
                exception: $e
            );
        } catch (Throwable $e) {
            return $this->finish(
                account: $account,
                context: $context,
                startedAt: $startedAt,
                kind: 'exception',
                exitCode: self::FAILURE,
                exception: $e
            );
        }

        /*
         * startAndLoop() returned without throwing.
         *
         * For a long-running listener this is never normal. Known causes:
         *  - the in-process health probe stopped a dead update loop
         *    (see TelegramDriverCheckHandler::healthCheck);
         *  - API::reconnectFull() bailed out with "the bot is already running";
         *  - Wrappers\Loop::loop() found the session unauthorized.
         *
         * All of them require a brand new process, so the exit code must be
         * non-zero for the watchdog to do its job.
         */
        /*
         * read(), not take(): the watchdog consumes the marker afterwards to
         * log the reason. Stale markers are impossible because handle() clears
         * the file before anything else.
         */
        $marker = TelegramListenerHealth::read();

        return $this->finish(
            account: $account,
            context: $context,
            startedAt: $startedAt,
            kind: $marker['reason'] ?? 'unexpected_return',
            exitCode: self::FAILURE,
            marker: $marker
        );
    }

    /**
     * Log the event loop termination in a single, greppable place.
     *
     * @param array<string, mixed>      $context
     * @param array<string, mixed>|null $marker
     */
    private function finish(
        TelegramAccount $account,
        array $context,
        float $startedAt,
        string $kind,
        int $exitCode,
        ?Throwable $exception = null,
        ?array $marker = null
    ): int {
        $account->update([
            'status' => 'stopped',
        ]);

        $payload = $context + [
            'termination' => $kind,
            'exit_code' => $exitCode,
            'uptime_seconds' => round(
                microtime(true) - $startedAt,
                3
            ),
            'stopped_at' => date('c'),
        ];

        if ($marker !== null) {
            $payload['health_marker'] = $marker;
        }

        if ($exception !== null) {
            $payload['exception'] = $exception::class;
            $payload['message'] = mb_substr(
                $exception->getMessage(),
                0,
                1000
            );
            $payload['file'] = $exception->getFile()
                . ':' . $exception->getLine();
            $payload['previous'] = $this->previousChain($exception);
        }

        if ($exitCode === self::SUCCESS) {
            Log::warning(
                'Telegram driver check event loop finished',
                $payload
            );

            $this->warn(
                "Event loop finished ({$kind}), exit code {$exitCode}."
            );
        } else {
            Log::critical(
                'Telegram driver check event loop terminated abnormally',
                $payload
            );

            $this->error(
                "Event loop terminated ({$kind}), exit code {$exitCode}."
                    . ($exception !== null
                        ? ' ' . $exception::class . ': '
                            . mb_substr($exception->getMessage(), 0, 300)
                        : '')
            );
        }

        return $exitCode;
    }

    /**
     * Full previous-exception chain.
     *
     * The real cause of an Amp cancellation always lives in getPrevious():
     * CancelledException("The operation was cancelled") wraps
     * TimeoutException("Timeout while waiting for updates.getDifference").
     *
     * @return list<array<string, string>>
     */
    private function previousChain(Throwable $e): array
    {
        $chain = [];
        $previous = $e->getPrevious();

        while ($previous !== null && count($chain) < 5) {
            $chain[] = [
                'exception' => $previous::class,
                'message' => mb_substr(
                    $previous->getMessage(),
                    0,
                    500
                ),
                'file' => $previous->getFile()
                    . ':' . $previous->getLine(),
            ];

            $previous = $previous->getPrevious();
        }

        return $chain;
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
