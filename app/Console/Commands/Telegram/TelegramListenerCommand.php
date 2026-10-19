<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use Amp\SignalException;
use App\Models\Telegram\TelegramListener;
use App\Telegram\TelegramListenerHandler;
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

/**
 * The listener of one row of telegram_listeners (CEO, Head Operation ...):
 * its account's MadelineProto event loop, until it is stopped. Driver Check
 * keeps its own command (telegram:start-loop).
 *
 * Exit codes tell telegram:listener-watchdog what to do: SUCCESS - stopped
 * on purpose; INVALID - not startable (off, no account, no session), wait
 * and look again; EXIT_ALREADY_RUNNING - another process owns the session;
 * FAILURE - the loop died, start a new process.
 */
final class TelegramListenerCommand extends Command
{
    public const EXIT_ALREADY_RUNNING = 3;

    protected $signature = 'telegram:listener {key : The listener key, e.g. ceo or head_operation}';

    protected $description = 'Run the event loop of one extra listener account';

    public function handle(): int
    {
        $listener = TelegramListener::query()
            ->where('key', (string) $this->argument('key'))
            ->with('account')
            ->first();

        if ($listener === null) {
            $this->error('No such listener.');

            return self::INVALID;
        }

        if (! $listener->is_enabled) {
            $this->warn("Listener {$listener->key} is switched off.");

            return self::INVALID;
        }

        $account = $listener->account;

        if ($account === null || ! $account->is_authorized || ! filled($account->session_path)) {
            $this->error("Listener {$listener->key} has no authorized account with a session.");

            return self::INVALID;
        }

        if (! File::exists($account->session_path)) {
            $this->error("Session path not found: {$account->session_path}");

            return self::INVALID;
        }

        /* One full process per session; a second one would only be an IPC client. */
        if (TelegramProcessLock::attempt($listener->lockName()) === TelegramProcessLock::HELD) {
            $this->error('Another process of this listener is already running.');

            return self::EXIT_ALREADY_RUNNING;
        }

        TelegramListenerHandler::$key = $listener->key;

        $startedAt = microtime(true);

        $context = [
            'key' => $listener->key,
            'account_id' => $account->id,
            'pid' => getmypid(),
        ];

        $account->update(['status' => 'running']);

        Log::info('Telegram listener starting', $context);

        $this->info("▶️ telegram:listener {$listener->key} pid " . getmypid() . " account #{$account->id} (MadelineProto " . API::RELEASE . ')');

        try {
            TelegramListenerHandler::startAndLoop($account->session_path, $this->settings());
        } catch (SignalException) {
            return $this->finish($account, $context, $startedAt, 'graceful_stop', self::SUCCESS);
        } catch (Throwable $e) {
            return $this->finish($account, $context, $startedAt, 'exception', self::FAILURE, $e);
        }

        /* A listener's loop never returns on its own: it was stopped by the health probe or the panel. */
        return $this->finish($account, $context, $startedAt, 'loop_returned', self::FAILURE);
    }

    private function finish(
        \App\Models\Telegram\TelegramAccount $account,
        array $context,
        float $startedAt,
        string $kind,
        int $exitCode,
        ?Throwable $exception = null,
    ): int {
        $account->update(['status' => 'stopped']);

        $payload = $context + [
            'termination' => $kind,
            'exit_code' => $exitCode,
            'uptime_seconds' => round(microtime(true) - $startedAt, 3),
        ];

        if ($exception !== null) {
            $payload['exception'] = $exception::class;
            $payload['message'] = mb_substr($exception->getMessage(), 0, 1000);
        }

        if ($exitCode === self::SUCCESS) {
            Log::warning('Telegram listener finished', $payload);
        } else {
            Log::critical('Telegram listener terminated abnormally', $payload);
        }

        $this->warn("Event loop finished ({$kind}), exit code {$exitCode}.");

        return $exitCode;
    }

    private function settings(): Settings
    {
        $settings = new Settings();

        $appInfo = new AppInfo();
        $appInfo->setApiId((int) config('services.telegram.api_id'));
        $appInfo->setApiHash((string) config('services.telegram.api_hash'));
        $appInfo->setLangCode(config('app.locale', 'en'));
        $appInfo->setSystemLangCode('en');
        $appInfo->setShowPrompt(false);

        $settings->setAppInfo($appInfo);

        $logger = (new LoggerSettings())
            ->setType(Logger::FILE_LOGGER)
            ->setExtra(storage_path('logs/madeline.log'));
        $logger->setMaxSize(50 * 1024 * 1024);
        $settings->setLogger($logger);

        return $settings;
    }
}
