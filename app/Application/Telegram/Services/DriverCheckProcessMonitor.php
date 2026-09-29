<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Application\Telegram\Exceptions\DriverCheckProcessException;
use App\Console\Commands\Telegram\TelegramDriverCheckCommand;
use App\Console\Commands\Telegram\TelegramWatchdogCommand;
use App\Jobs\Telegram\SignalDriverCheckProcessJob;
use App\Jobs\Telegram\StartTelegramWatchdogJob;
use App\Models\Driver\TelegramDriverCheck;
use App\Models\Telegram\TelegramAccount;
use App\Telegram\QueueWorkerHeartbeat;
use App\Telegram\TelegramListenerHealth;
use App\Telegram\TelegramProcessLock;
use App\Telegram\TelegramRestartNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * What the watchdog page shows, and the three things it can do.
 *
 * Nothing here opens a MadelineProto session. Whether a process runs is
 * answered by the same flock locks the processes take themselves - the
 * only authoritative answer, a PID file can be stale - and control is
 * plain POSIX signals, which both commands already handle gracefully:
 *
 *   listener  SIGTERM -> MadelineProto unwinds, the watchdog starts a new one
 *   watchdog  SIGTERM -> it stops its listener, then itself
 */
final class DriverCheckProcessMonitor
{
    public const WATCHDOG = 'watchdog';

    public const SPARE = 'spare';

    public const LISTENER = 'listener';

    /**
     * What each process looks like on its command line, so a PID read from
     * a lock file is only signalled while it still belongs to that process
     * and not to whatever the kernel handed the number to afterwards.
     */
    private const COMMANDS = [
        self::WATCHDOG => 'telegram:watchdog',
        self::SPARE => 'telegram:watchdog',
        self::LISTENER => 'telegram:start-loop',
    ];

    private const LOCKS = [
        self::WATCHDOG => TelegramWatchdogCommand::LOCK_NAME,
        self::SPARE => TelegramWatchdogCommand::SPARE_LOCK_NAME,
        self::LISTENER => TelegramDriverCheckCommand::LOCK_NAME,
    ];

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $processes = [];

        foreach (array_keys(self::LOCKS) as $role) {
            $processes[$role] = $this->process($role);
        }

        $account = $this->account();

        $worker = QueueWorkerHeartbeat::read()['pulse'];

        return [
            'state' => $this->overallState($processes, $account),
            'processes' => $processes,
            'account' => $account,
            'account_configured' => $this->accountId() !== null,
            'activity' => $this->activity(),
            'health_marker' => TelegramListenerHealth::read(),
            'restart_notice' => $this->readJson(TelegramRestartNotice::path()),
            'start_queued' => $this->startJobsQueued(),
            'queue_worker_alive' => $worker !== null
                && time() - (int) ($worker['at'] ?? 0) <= QueueWorkerHeartbeat::ALIVE_WITHIN_SECONDS,
            'can_signal' => $this->canSignal(),
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Queues StartTelegramWatchdogJob. Extra watchdogs are harmless: the
     * second one waits as a spare and any further copy exits at once.
     */
    public function start(?int $byUserId): void
    {
        StartTelegramWatchdogJob::dispatch()->onQueue('telegram');

        Log::info('Driver check watchdog start requested from the panel', [
            'user_id' => $byUserId,
        ]);
    }

    /**
     * The signal went out from this process.
     */
    public const SENT = 'sent';

    /**
     * This process may not signal the target, so the queue worker will.
     */
    public const QUEUED = 'queued';

    /**
     * SIGTERM to the listener. The watchdog sees a graceful stop and starts
     * a fresh one after its --delay; without a watchdog nothing would.
     *
     * @return self::SENT|self::QUEUED
     */
    public function restartListener(?int $byUserId, bool $fromWorker = false): string
    {
        if ($this->process(self::WATCHDOG)['running'] !== true) {
            throw new DriverCheckProcessException('no_watchdog');
        }

        return $this->signalOrQueue(
            SignalDriverCheckProcessJob::RESTART,
            [self::LISTENER],
            $byUserId,
            $fromWorker,
        );
    }

    /**
     * Stops the whole chain. The spare goes first: stopped after the
     * supervisor, it would take over and start the listener again.
     *
     * @return self::SENT|self::QUEUED
     */
    public function stop(?int $byUserId, bool $fromWorker = false): string
    {
        $roles = array_values(array_filter(
            [self::SPARE, self::WATCHDOG],
            fn (string $role): bool => $this->process($role)['running'] === true,
        ));

        if ($roles === []) {
            throw new DriverCheckProcessException('not_running');
        }

        return $this->signalOrQueue(
            SignalDriverCheckProcessJob::STOP,
            $roles,
            $byUserId,
            $fromWorker,
        );
    }

    /**
     * Signals the processes from here when the kernel lets this process
     * do it, and hands the whole action to the queue worker when it does
     * not: PHP-FPM normally runs as another user than the watchdog, while
     * the worker started the watchdog and runs as its user.
     *
     * @param list<string> $roles
     * @return self::SENT|self::QUEUED
     */
    private function signalOrQueue(
        string $action,
        array $roles,
        ?int $byUserId,
        bool $fromWorker,
    ): string {
        $context = [
            'action' => $action,
            'user_id' => $byUserId,
            'from_worker' => $fromWorker,
        ];

        try {
            foreach ($roles as $role) {
                $this->terminate($role);
            }
        } catch (DriverCheckProcessException $e) {
            if ($e->reason !== 'signal_failed' || $fromWorker) {
                throw $e;
            }

            SignalDriverCheckProcessJob::dispatch($action, $byUserId)->onQueue('telegram');

            Log::warning('Driver check process signal handed to the queue worker', $context);

            return self::QUEUED;
        }

        Log::warning('Driver check process signal sent', $context);

        return self::SENT;
    }

    /**
     * running is null when the lock file exists but cannot be opened -
     * "unknown" must not be shown as "down".
     *
     * @return array{running: ?bool, pid: ?int}
     */
    private function process(string $role): array
    {
        $lock = self::LOCKS[$role];

        $running = $this->isLocked(TelegramProcessLock::path($lock));

        return [
            'running' => $running,
            'pid' => $running ? TelegramProcessLock::holderPid($lock) : null,
        ];
    }

    /**
     * The same probe as TelegramProcessLock::isHeldByOtherProcess(), but
     * read-only: PHP-FPM usually runs as another user than the worker that
     * created the lock file, and 'c+' would need write access to it. A
     * shared lock is refused exactly while the owner holds its exclusive one.
     */
    private function isLocked(string $path): ?bool
    {
        if (! is_file($path)) {
            return false;
        }

        $handle = @fopen($path, 'r');

        if ($handle === false) {
            return null;
        }

        try {
            $free = flock($handle, LOCK_SH | LOCK_NB);

            if ($free) {
                flock($handle, LOCK_UN);
            }

            return ! $free;
        } finally {
            fclose($handle);
        }
    }

    private function terminate(string $role): void
    {
        $process = $this->process($role);

        if ($process['running'] !== true) {
            throw new DriverCheckProcessException('not_running');
        }

        $pid = $process['pid'];

        if ($pid === null || ! $this->isOwnProcess($pid, self::COMMANDS[$role])) {
            throw new DriverCheckProcessException('pid_unknown');
        }

        if (! $this->sendTerm($pid)) {
            throw new DriverCheckProcessException('signal_failed');
        }
    }

    /**
     * Only a Linux box can answer this, and only a Linux box runs the
     * watchdog; anywhere else the answer is "don't".
     */
    private function isOwnProcess(int $pid, string $command): bool
    {
        $path = "/proc/{$pid}/cmdline";

        if (! is_readable($path)) {
            return false;
        }

        $cmdline = str_replace("\0", ' ', (string) @file_get_contents($path));

        return str_contains($cmdline, 'artisan') && str_contains($cmdline, $command);
    }

    private function sendTerm(int $pid): bool
    {
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 15);
        }

        try {
            $process = new Process(['kill', '-TERM', (string) $pid]);
            $process->setTimeout(5)->run();

            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    private function canSignal(): bool
    {
        return PHP_OS_FAMILY === 'Linux' && is_dir('/proc');
    }

    /**
     * @param array<string, array{running: ?bool, pid: ?int}> $processes
     * @param array<string, mixed>|null $account
     */
    private function overallState(array $processes, ?array $account): string
    {
        if ($account === null || ! $account['is_authorized']) {
            return 'misconfigured';
        }

        $watchdog = $processes[self::WATCHDOG]['running'];
        $listener = $processes[self::LISTENER]['running'];

        if ($watchdog === null || $listener === null) {
            return 'unknown';
        }

        return match (true) {
            $watchdog && $listener => 'ok',
            $listener => 'unsupervised',
            $watchdog => 'restarting',
            default => 'down',
        };
    }

    private function accountId(): ?int
    {
        $id = config('services.telegram.driver_check_account_id');

        return $id === null || $id === '' ? null : (int) $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function account(): ?array
    {
        $id = $this->accountId();

        $account = $id === null ? null : TelegramAccount::query()->find($id);

        if ($account === null) {
            return null;
        }

        $name = trim(implode(' ', array_filter([$account->first_name, $account->last_name])));

        return [
            'id' => $account->id,
            'phone' => $account->phone,
            'name' => $name !== '' ? $name : null,
            'username' => $account->username,
            'status' => $account->status,
            'is_authorized' => (bool) $account->is_authorized,
            'session_exists' => $account->hasSessionFile(),
            'last_checked_at' => $account->last_checked_at?->toIso8601String(),
            'updated_at' => $account->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The listener's work as seen from the outside - the surest sign that
     * it is not only running but actually receiving messages.
     *
     * @return array<string, mixed>
     */
    private function activity(): array
    {
        try {
            $latest = TelegramDriverCheck::query()->latest('id')->first(['id', 'created_at']);

            return [
                'last_check_at' => $latest?->created_at?->toIso8601String(),
                'last_report_at' => TelegramDriverCheck::query()->max('reported_at'),
                'checks_today' => TelegramDriverCheck::query()
                    ->where('created_at', '>=', now()->startOfDay())
                    ->count(),
                'pending' => TelegramDriverCheck::query()
                    ->whereIn('status', ['pending', 'processing'])
                    ->count(),
            ];
        } catch (Throwable) {
            return [
                'last_check_at' => null,
                'last_report_at' => null,
                'checks_today' => 0,
                'pending' => 0,
            ];
        }
    }

    private function startJobsQueued(): int
    {
        try {
            return DB::table(config('queue.connections.database.table', 'jobs'))
                ->where('payload', 'like', '%StartTelegramWatchdogJob%')
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
