<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Application\Telegram\Services\GroupJoinSettings;
use App\Models\Telegram\TelegramAccount;
use App\Telegram\TelegramProcessLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * The group join's own supervisor, apart from the listeners' one:
 *
 *     php artisan telegram:group-join-watchdog
 *
 * Runs telegram:group-join <id> for every account marked in the panel,
 * starts a worker again when it dies (backing off when it dies right away),
 * starts the ones marked later within seconds and stops the ones unmarked.
 * Switched off in the panel, it stops them all and exits.
 *
 * The scheduler asks for it every minute while the join is on, so it comes
 * back by itself after a reboot or a crash; its lock turns a second copy away.
 */
final class TelegramGroupJoinWatchdogCommand extends Command
{
    public const LOCK_NAME = 'group-join-watchdog';

    /** Where the last exit of each worker is kept for the panel: this + the account id. */
    public const LAST_EXIT_CACHE = 'group-join-watchdog:last-exit:';

    /** A worker that dies faster than this did not really run. */
    private const FAST_FAILURE_SECONDS = 60;

    /** How often the settings and the children are looked at, seconds. */
    private const TICK_SECONDS = 3;

    /** Not startable (refused to start, owned by another process): look again after this. */
    private const STANDBY_SECONDS = 60;

    protected $signature = 'telegram:group-join-watchdog
                            {--delay=10 : Delay before a restart, seconds}
                            {--max-delay=300 : Longest back-off, seconds}';

    protected $description = 'Keep a group join worker running for every account marked in the panel';

    /** @var array<int, Process> */
    private array $children = [];

    /** @var array<int, float> */
    private array $startedAt = [];

    /** @var array<int, float> account id => unix time it may be started again */
    private array $notBefore = [];

    /** @var array<int, int> */
    private array $delays = [];

    /** @var array<int, string> the end of what each child printed */
    private array $tails = [];

    private bool $stopping = false;

    public function handle(GroupJoinSettings $settings): int
    {
        if (TelegramProcessLock::attempt(self::LOCK_NAME) === TelegramProcessLock::HELD) {
            return self::SUCCESS;
        }

        $this->trapStopSignals();

        $baseDelay = max(1, (int) $this->option('delay'));
        $maxDelay = max($baseDelay, (int) $this->option('max-delay'));

        $this->info('Group join watchdog, pid ' . getmypid());

        Log::info('Group join watchdog started', ['pid' => getmypid()]);

        while (! $this->stopping) {
            $this->reap($baseDelay, $maxDelay);

            try {
                $config = $settings->current();
            } catch (Throwable $e) {
                Log::error('Group join watchdog: the settings could not be read', ['error' => $e->getMessage()]);

                $this->sleep(self::TICK_SECONDS);

                continue;
            }

            if (! $config['enabled']) {
                $this->info('Switched off in the panel.');

                break;
            }

            $wanted = $this->wanted($config['accounts']);

            /* Unmarked, logged out: its worker ends its burst and goes. */
            foreach (array_diff(array_keys($this->children), $wanted) as $id) {
                $this->stopChild($id);
            }

            foreach ($wanted as $id) {
                if (isset($this->children[$id]) || ($this->notBefore[$id] ?? 0) > microtime(true)) {
                    continue;
                }

                if (TelegramProcessLock::isHeldByOtherProcess(TelegramGroupJoinCommand::lockName($id))) {
                    continue;
                }

                $this->start($id);
            }

            $this->sleep(self::TICK_SECONDS);
        }

        $this->stopChildren();

        Log::info('Group join watchdog finished', ['pid' => getmypid()]);

        return self::SUCCESS;
    }

    /**
     * The marked accounts that can run: authorized, with a session.
     *
     * @param list<int> $marked
     *
     * @return list<int>
     */
    private function wanted(array $marked): array
    {
        if ($marked === []) {
            return [];
        }

        try {
            return TelegramAccount::query()
                ->whereIn('id', $marked)
                ->where('is_authorized', true)
                ->whereNotNull('session_path')
                ->where('session_path', '!=', '')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (Throwable $e) {
            Log::error('Group join watchdog: the accounts could not be read', ['error' => $e->getMessage()]);

            return array_keys($this->children);
        }
    }

    private function start(int $id): void
    {
        /* Absolute artisan path and working directory: the watchdog is usually spawned from elsewhere. */
        $process = new Process([PHP_BINARY, base_path('artisan'), 'telegram:group-join', (string) $id], base_path());
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->start();

        $this->children[$id] = $process;
        $this->startedAt[$id] = microtime(true);

        $this->info("Started account #{$id}, pid " . $process->getPid());

        Log::info('Group join watchdog: worker started', ['account_id' => $id, 'pid' => $process->getPid()]);
    }

    /**
     * Looks at the workers that stopped: when to start each again.
     */
    private function reap(int $baseDelay, int $maxDelay): void
    {
        foreach ($this->children as $id => $process) {
            /* isRunning() pumps the pipes; the buffers are dropped so days of output are not kept. */
            $running = $process->isRunning();

            $this->tails[$id] = mb_substr(($this->tails[$id] ?? '') . $process->getIncrementalOutput() . $process->getIncrementalErrorOutput(), -600);

            $process->clearOutput();
            $process->clearErrorOutput();

            if ($running) {
                continue;
            }

            $exit = $process->getExitCode();
            $seconds = microtime(true) - ($this->startedAt[$id] ?? microtime(true));

            unset($this->children[$id], $this->startedAt[$id]);

            $said = trim((string) preg_replace('/\e\[[0-9;]*m/', '', $this->tails[$id] ?? ''));
            unset($this->tails[$id]);

            try {
                Cache::put(self::LAST_EXIT_CACHE . $id, [
                    'exit' => $exit,
                    'message' => mb_substr($said, -300),
                    'at' => now()->toIso8601String(),
                ], 86400);
            } catch (Throwable) {
                /* The panel just shows nothing. */
            }

            /* Ran its time and ended on purpose: straight back. */
            if ($exit === self::SUCCESS) {
                $this->delays[$id] = $baseDelay;
                $this->notBefore[$id] = microtime(true) + 3;

                continue;
            }

            /* Refused to start (unmarked, logged out) or owned by another process: look again later. */
            if ($exit === self::INVALID || $exit === TelegramGroupJoinCommand::EXIT_ALREADY_RUNNING) {
                $this->notBefore[$id] = microtime(true) + self::STANDBY_SECONDS;

                continue;
            }

            $fast = $seconds < self::FAST_FAILURE_SECONDS;

            $this->delays[$id] = $fast ? min($maxDelay, ($this->delays[$id] ?? $baseDelay) * 2) : $baseDelay;
            $this->notBefore[$id] = microtime(true) + $this->delays[$id];

            Log::warning('Group join watchdog: a worker stopped', [
                'account_id' => $id,
                'exit_code' => $exit,
                'seconds' => round($seconds, 1),
                'next_delay' => $this->delays[$id],
                'said' => mb_substr($said, -300),
            ]);
        }
    }

    private function stopChild(int $id): void
    {
        $process = $this->children[$id] ?? null;

        if ($process && $process->isRunning()) {
            Log::info('Group join watchdog: worker no longer wanted, stopping', ['account_id' => $id]);

            $process->signal(self::term());
        }
    }

    private function stopChildren(): void
    {
        foreach ($this->children as $process) {
            if ($process->isRunning()) {
                /* Up to two minutes: a worker finishes the burst in hand and closes its session. */
                $process->stop(120, self::term());
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
        };

        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }

    private static function term(): int
    {
        return defined('SIGTERM') ? SIGTERM : 15;
    }
}
