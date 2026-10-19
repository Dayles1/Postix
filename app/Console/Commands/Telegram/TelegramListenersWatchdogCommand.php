<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Application\Telegram\Services\DeveloperAlerts;
use App\Application\Telegram\Services\ListenersControl;
use App\Models\Telegram\TelegramListener;
use App\Telegram\TelegramProcessLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * One supervisor entry for every listener there is:
 *
 *     php artisan telegram:listeners-watchdog [--with-driver-check]
 *
 * Starts telegram:listener for each row of telegram_listeners that is on and
 * has an account, starts it again when it dies (backing off when it dies
 * right away) and picks up a row switched on in the panel within seconds.
 * With --with-driver-check it keeps Driver Check's own watchdog
 * (telegram:watchdog) running too, so a single process covers all accounts.
 *
 * Nothing is started twice: every listener process holds its own lock, so a
 * listener that is already running - under this command, under
 * telegram:listener-watchdog <key> or by hand - is left alone.
 *
 * A listener that keeps dying is told to the developer (DeveloperAlerts).
 */
final class TelegramListenersWatchdogCommand extends Command
{
    public const LOCK_NAME = 'listeners-watchdog-all';

    /** Where the last exit of each process is kept for the panel: this + the key. */
    public const LAST_EXIT_CACHE = 'listeners-watchdog:last-exit:';

    /** Where the last start attempt of each process is kept for the panel: this + the key. */
    public const LAST_START_CACHE = 'listeners-watchdog:last-start:';

    /** How long it is kept, seconds. */
    private const LAST_EXIT_TTL = 3600;

    /** A listener that dies faster than this did not really run. */
    private const FAST_FAILURE_SECONDS = 60;

    /** How often the rows and the children are looked at, seconds. */
    private const TICK_SECONDS = 2;

    /** Not startable (off, no session) or owned by another process: look again after this. */
    private const STANDBY_SECONDS = 15;

    /** Dead this many times in a row, quickly: the developer is told. */
    private const ALERT_AFTER_FAILURES = 3;

    protected $signature = 'telegram:listeners-watchdog
                            {--with-driver-check : Keep the Driver Check watchdog (telegram:watchdog) running too}
                            {--delay=10 : Delay before a restart, seconds}
                            {--max-delay=300 : Longest back-off, seconds}';

    protected $description = 'Keep every listener account running (one supervisor for all of them) and restart the ones that stop';

    /** @var array<string, Process> */
    private array $children = [];

    /** @var array<string, float> */
    private array $startedAt = [];

    /** @var array<string, float> key => unix time it may be started again */
    private array $notBefore = [];

    /** @var array<string, int> */
    private array $delays = [];

    /** @var array<string, int> */
    private array $failures = [];

    /** @var array<string, string> the end of what each child printed, for the panel when it dies */
    private array $tails = [];

    /** @var array<string, true> asked to restart from the panel: their exit is not a crash */
    private array $restarting = [];

    /** The newest panel request already seen (ListenersControl). */
    private int $seen = 0;

    private bool $stopping = false;

    public function handle(): int
    {
        if (TelegramProcessLock::attempt(self::LOCK_NAME) === TelegramProcessLock::HELD) {
            $this->error('A listeners watchdog is already running.');

            return self::FAILURE;
        }

        $this->trapStopSignals();

        $baseDelay = max(1, (int) $this->option('delay'));
        $maxDelay = max($baseDelay, (int) $this->option('max-delay'));

        $this->info('Listeners watchdog, pid ' . getmypid());

        /* Requests from before this start are history: only the ones after it count. */
        $this->seen = $this->latestRequest();

        while (! $this->stopping) {
            $this->reap($baseDelay, $maxDelay);

            $this->handleRequests();

            foreach ($this->wanted() as $key => $spec) {
                if (isset($this->children[$key]) || ($this->notBefore[$key] ?? 0) > microtime(true)) {
                    continue;
                }

                /* Somebody else (a per-key watchdog, a hand-started process) already holds it. */
                if (TelegramProcessLock::isHeldByOtherProcess($spec['lock'])) {
                    continue;
                }

                $this->start($key, $spec['command']);
            }

            $this->sleep(self::TICK_SECONDS);
        }

        $this->stopChildren();

        return self::SUCCESS;
    }

    /**
     * What should be running: key => [command, lock].
     *
     * @return array<string, array{command: list<string>, lock: string}>
     */
    private function wanted(): array
    {
        $wanted = [];

        if ($this->option('with-driver-check')) {
            $wanted['driver-check'] = [
                'command' => ['telegram:watchdog'],
                'lock' => TelegramWatchdogCommand::LOCK_NAME,
            ];
        }

        try {
            $listeners = TelegramListener::query()
                ->where('is_enabled', true)
                ->whereNotNull('telegram_account_id')
                ->get();
        } catch (Throwable $e) {
            Log::error('Listeners watchdog: the listeners could not be read', ['error' => $e->getMessage()]);

            return $wanted;
        }

        foreach ($listeners as $listener) {
            $wanted[$listener->key] = [
                'command' => ['telegram:listener', $listener->key],
                'lock' => $listener->lockName(),
            ];
        }

        return $wanted;
    }

    /**
     * @param list<string> $command artisan command and its arguments
     */
    private function start(string $key, array $command): void
    {
        /* Absolute artisan path and working directory: the watchdog is usually spawned from elsewhere. */
        $process = new Process([PHP_BINARY, base_path('artisan'), ...$command], base_path());
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->start();

        $this->children[$key] = $process;
        $this->startedAt[$key] = microtime(true);

        $this->info("Started {$key}, pid " . $process->getPid());

        Log::info('Listeners watchdog: started', ['key' => $key, 'pid' => $process->getPid(), 'command' => implode(' ', $command)]);

        $this->remember(self::LAST_START_CACHE . $key, ['at' => now()->toIso8601String(), 'pid' => $process->getPid()]);
    }

    /**
     * Looks at the children that stopped: when to start each again, and
     * whether the developer has to hear of it.
     */
    private function reap(int $baseDelay, int $maxDelay): void
    {
        foreach ($this->children as $key => $process) {
            /* isRunning() pumps the pipes; the buffers are dropped so days of output are not kept. */
            $running = $process->isRunning();

            /* What it printed, kept short: the reason it gives when it refuses to start. */
            $this->tails[$key] = mb_substr(($this->tails[$key] ?? '') . $process->getIncrementalOutput() . $process->getIncrementalErrorOutput(), -600);

            $process->clearOutput();
            $process->clearErrorOutput();

            if ($running) {
                continue;
            }

            $exit = $process->getExitCode();
            $seconds = microtime(true) - ($this->startedAt[$key] ?? microtime(true));

            unset($this->children[$key], $this->startedAt[$key]);

            $this->rememberExit($key, $exit);

            /* Restarted from the panel: not a crash, back in a moment, nothing counted. */
            if (isset($this->restarting[$key])) {
                unset($this->restarting[$key]);

                $this->failures[$key] = 0;
                $this->delays[$key] = $baseDelay;
                $this->notBefore[$key] = microtime(true) + 3;

                continue;
            }

            /* Not startable, or another process owns it: not a crash, wait and look again. */
            if ($exit === self::INVALID || $exit === TelegramListenerCommand::EXIT_ALREADY_RUNNING) {
                $this->notBefore[$key] = microtime(true) + self::STANDBY_SECONDS;

                continue;
            }

            $fast = $seconds < self::FAST_FAILURE_SECONDS;

            $this->delays[$key] = $fast ? min($maxDelay, ($this->delays[$key] ?? $baseDelay) * 2) : $baseDelay;
            $this->failures[$key] = $fast ? ($this->failures[$key] ?? 0) + 1 : 1;
            $this->notBefore[$key] = microtime(true) + $this->delays[$key];

            Log::warning('Listeners watchdog: a process stopped', [
                'key' => $key,
                'exit_code' => $exit,
                'seconds' => round($seconds, 1),
                'next_delay' => $this->delays[$key],
                'failures_in_a_row' => $this->failures[$key],
            ]);

            if ($this->failures[$key] >= self::ALERT_AFTER_FAILURES) {
                app(DeveloperAlerts::class)->report(
                    kind: 'listener_down',
                    title: 'Листенер «' . $key . '» постоянно падает',
                    details: 'Процесс завершился ' . $this->failures[$key] . ' раз подряд в течение минуты после запуска (код ' . $exit . '). '
                        . 'Следующий запуск через ' . $this->delays[$key] . ' с. Подробности: storage/logs и madeline.log.',
                    context: ['Код выхода' => $exit, 'Работал, с' => round($seconds, 1)],
                    listenerKey: $key === 'driver-check' ? null : $key,
                    about: $key,
                );
            }
        }
    }

    private function latestRequest(): int
    {
        try {
            return app(ListenersControl::class)->latestId();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * What the panel asked for (ListenersControl): restart one process or
     * all of them, or stop everything. The watchdog owns the processes, so
     * it is the one to signal them.
     */
    private function handleRequests(): void
    {
        try {
            $requests = app(ListenersControl::class)->after($this->seen);
        } catch (Throwable $e) {
            Log::error('Listeners watchdog: the panel requests could not be read', ['error' => $e->getMessage()]);

            return;
        }

        foreach ($requests as $request) {
            $this->seen = max($this->seen, (int) ($request['id'] ?? 0));

            if (($request['action'] ?? null) === ListenersControl::STOP) {
                Log::warning('Listeners watchdog: stop asked from the panel');

                $this->stopping = true;

                return;
            }

            if (($request['action'] ?? null) !== ListenersControl::RESTART) {
                continue;
            }

            $key = (string) ($request['key'] ?? ListenersControl::ALL);

            foreach ($key === ListenersControl::ALL ? array_keys($this->children) : [$key] as $child) {
                /* "All" is the listeners: Driver Check is restarted by its own signal, never by terminating its watchdog. */
                if ($key === ListenersControl::ALL && $child === 'driver-check') {
                    continue;
                }

                if (! isset($this->children[$child]) || ! $this->children[$child]->isRunning()) {
                    continue;
                }

                $this->restarting[$child] = true;

                Log::warning('Listeners watchdog: restart asked from the panel', ['key' => $child]);

                /* The process unwinds its event loop and saves its session; the loop above starts it again. */
                $this->children[$child]->signal(SIGTERM);
            }
        }
    }

    /**
     * A process that ended other than on purpose: what it printed, for the panel
     * to show next to the account ("Session path not found ...").
     */
    private function rememberExit(string $key, ?int $exit): void
    {
        $message = trim((string) preg_replace('/\e\[[0-9;]*m/', '', $this->tails[$key] ?? ''));

        unset($this->tails[$key]);

        if ($exit === self::SUCCESS) {
            return;
        }

        /* In the log whatever the code: a process that refuses to start used to leave no trace at all. */
        Log::warning('Listeners watchdog: a process ended', [
            'key' => $key,
            'exit_code' => $exit,
            'said' => mb_substr($message, -300),
        ]);

        $this->remember(self::LAST_EXIT_CACHE . $key, [
            'message' => mb_substr($message, -300),
            'exit' => $exit,
            'at' => now()->toIso8601String(),
        ]);
    }

    /**
     * What the panel shows next to an account.
     *
     * @param array<string, mixed> $value
     */
    private function remember(string $key, array $value): void
    {
        try {
            \Illuminate\Support\Facades\Cache::put($key, $value, self::LAST_EXIT_TTL);
        } catch (Throwable) {
            /* The panel just shows nothing. */
        }
    }

    private function stopChildren(): void
    {
        foreach ($this->children as $process) {
            if ($process->isRunning()) {
                $process->stop(15, SIGTERM);
            }
        }
    }

    /**
     * In one-second steps so a stop signal is not slept through.
     */
    private function sleep(int $seconds): void
    {
        for ($i = 0; $i < $seconds && ! $this->stopping; $i++) {
            sleep(1);
        }
    }

    private function trapStopSignals(): void
    {
        if (! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        $stop = function (): void {
            $this->stopping = true;

            /* Each listener gets the chance to unwind its event loop and save its session. */
            foreach ($this->children as $process) {
                $process->signal(SIGTERM);
            }
        };

        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }
}
