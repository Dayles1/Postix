<?php

namespace App\Console\Commands\Telegram;

use App\Telegram\TelegramListenerHealth;
use App\Telegram\TelegramProcessLock;
use App\Telegram\TelegramRestartNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class TelegramWatchdogCommand extends Command
{
    /**
     * Single-instance guard name, see TelegramProcessLock.
     *
     * Separate from the listener's own lock: this one answers "is anybody
     * already supervising?", the listener's answers "does anybody already own
     * the session?".
     */
    public const LOCK_NAME = 'driver-check-watchdog';

    /**
     * Held by the one watchdog allowed to wait as a hot spare.
     *
     * The start endpoint spawns a watchdog on every call, and a spare that
     * waits forever is a PHP process that is never reclaimed - a few hundred
     * clicks would quietly eat the server. One supervisor plus one spare is
     * all the redundancy this is worth, so any further copy exits at once.
     */
    public const SPARE_LOCK_NAME = 'driver-check-watchdog-spare';

    /**
     * A listener that dies faster than this did not really run: it failed to
     * start. Restarting it every $delay seconds forever only hides the problem,
     * so the delay is backed off instead.
     */
    private const FAST_FAILURE_SECONDS = 60;

    /**
     * How often a standing-by watchdog looks again.
     *
     * Standby is a wait, not a restart: nothing is spawned, nothing is counted
     * and nothing is logged until the situation actually changes.
     */
    private const STANDBY_SECONDS = 15;

    /**
     * How much of the listener output is kept for the crash report.
     */
    private const OUTPUT_TAIL_BYTES = 4000;

    /**
     * How long the listener is given to shut down when the watchdog is asked
     * to stop. MadelineProto has to unwind its event loop and write its own
     * termination log, which is the whole point of not just killing it.
     */
    private const SHUTDOWN_GRACE_SECONDS = 15;

    protected $signature = 'telegram:watchdog
                            {--delay=10 : Delay before restart in seconds}
                            {--max-delay=300 : Maximum backoff delay in seconds}';

    protected $description = 'Watch Telegram driver check listener and restart it when stopped';

    /**
     * The listener process currently being supervised, so a signal handler can
     * shut it down instead of orphaning it.
     */
    private ?Process $listener = null;

    /**
     * Set once a stop signal has been handled: a second SIGTERM arriving while
     * the listener is still winding down must not start the whole sequence
     * again.
     */
    private bool $stopping = false;

    /**
     * What the watchdog is currently standing by for, so the same reason is
     * logged once per transition instead of on every poll.
     */
    private ?string $standbyReason = null;

    public function handle(): int
    {
        $delay = max(
            1,
            (int) $this->option('delay')
        );

        $maxDelay = max(
            $delay,
            (int) $this->option('max-delay')
        );

        $restartCount = 0;
        $fastFailures = 0;

        $this->trapStopSignals();

        $this->info(
            'Telegram watchdog started (pid ' . getmypid() . ').'
        );

        Log::info(
            'Telegram watchdog started',
            [
                'pid' => getmypid(),
                'delay' => $delay,
                'max_delay' => $maxDelay,
            ]
        );

        /*
         * A supervising watchdog never gives up: there is no restart budget
         * and no way out of this loop, because a supervisor that stops
         * supervising leaves the listener down for good. The one process that
         * does return is a third copy with nothing left to do, below.
         */
        while (true) {
            /*
             * Every call to the start endpoint dispatches
             * StartTelegramWatchdogJob, which nohups a fresh watchdog. The
             * second one waits here as a hot spare and takes over if the
             * supervisor ever dies; a third would be an idle PHP process kept
             * alive forever, so it leaves.
             */
            if (!$this->isSupervisor()) {
                if (!$this->claimSpareSlot()) {
                    $this->warn(
                        'A telegram:watchdog and a spare are already running, nothing to do.'
                    );

                    Log::info(
                        'Telegram watchdog exiting, supervisor and spare already running',
                        [
                            'pid' => getmypid(),
                        ]
                    );

                    return self::SUCCESS;
                }

                $this->standby(
                    'another_watchdog_supervising',
                    'Another telegram:watchdog is supervising, standing by as spare'
                );

                continue;
            }

            /*
             * Somebody already owns the session - normally a listener this
             * watchdog started earlier and has since lost track of, or one
             * started by hand. Spawning another one now can only produce a
             * process that exits with EXIT_ALREADY_RUNNING, which is exactly
             * the pointless restart this check exists to prevent.
             */
            if (TelegramProcessLock::isHeldByOtherProcess(TelegramDriverCheckCommand::LOCK_NAME)) {
                $this->standby(
                    'listener_already_running',
                    'Listener is already running (pid: '
                        . (TelegramProcessLock::holderPid(TelegramDriverCheckCommand::LOCK_NAME) ?? 'unknown')
                        . '), standing by'
                );

                continue;
            }

            /*
             * Whatever the backoff had climbed to belongs to the situation
             * before the wait: a listener that has just become startable again
             * deserves an immediate first try.
             */
            if ($this->clearStandby()) {
                $fastFailures = 0;

                /*
                 * Supervising now, so the spare slot belongs to somebody else.
                 */
                TelegramProcessLock::release(self::SPARE_LOCK_NAME);
            }

            $restartCount++;

            $this->info(
                "Starting telegram:start-loop (attempt #{$restartCount})"
            );

            Log::info(
                'Telegram watchdog starting listener',
                [
                    'restart_count' => $restartCount,
                    'watchdog_pid' => getmypid(),
                ]
            );

            $result = $this->runListener();

            /*
             * Written by the listener itself when it detected a dead update
             * loop or a failed start, so the watchdog can log the real reason.
             */
            $marker = TelegramListenerHealth::take();

            $kind = $this->classifyTermination($result, $marker);

            /*
             * The pre-flight check above makes this rare, but two watchdogs can
             * still reach the spawn at the same moment. Nothing was supervised,
             * so it is not a failed restart.
             */
            if ($kind === 'session_already_owned') {
                $restartCount--;
                $fastFailures = 0;

                $this->reportTermination($result, $marker, $kind, $restartCount, $fastFailures);

                $this->standby(
                    'listener_already_running',
                    'Listener is already running, standing by'
                );

                continue;
            }

            $fastFailures = $result['duration'] < self::FAST_FAILURE_SECONDS
                ? $fastFailures + 1
                : 0;

            $this->reportTermination(
                $result,
                $marker,
                $kind,
                $restartCount,
                $fastFailures
            );

            /*
             * Hand the reason to the listener that replaces this one: the
             * restart notification goes to Saved Messages, and only a process
             * holding the session can send it.
             */
            if ($kind !== 'graceful_stop') {
                TelegramRestartNotice::record([
                    'reason' => $kind,
                    'exit_code' => $result['exit_code'],
                    'uptime_seconds' => $result['duration'],
                    'restart_count' => $restartCount,
                    'listener_pid' => $result['pid'],
                    'watchdog_pid' => getmypid(),
                    'detail' => $this->failureDetail($result, $marker),
                ]);
            }

            $currentDelay = $this->backoffDelay(
                $delay,
                $maxDelay,
                $fastFailures
            );

            $this->info(
                "Restarting in {$currentDelay} seconds..."
            );

            sleep($currentDelay);
        }
    }

    /**
     * Say so in the log when the watchdog is asked to stop, and take the
     * listener down with it.
     *
     * Without a handler PHP dies on the spot: no log line, and the listener is
     * orphaned with nothing supervising it. Only SIGTERM and SIGINT are
     * trapped - SIGHUP is deliberately left alone, because the watchdog is
     * started under nohup and trapping it would make a terminal hangup kill
     * the process that nohup exists to protect.
     *
     * SIGKILL cannot be trapped by anyone; that case is covered from the other
     * side, by whoever is watching this process.
     */
    private function trapStopSignals(): void
    {
        /*
         * Not defined without ext-pcntl, which is normal on Windows. Laravel's
         * trap() is a no-op there too, so there is nothing to register.
         */
        if (!\defined('SIGTERM') || !\defined('SIGINT')) {
            return;
        }

        $this->trap(
            [\SIGTERM, \SIGINT],
            function (int $signal): void {
                if ($this->stopping) {
                    return;
                }

                $this->stopping = true;

                $listenerPid = $this->listener?->isRunning()
                    ? $this->listener->getPid()
                    : null;

                Log::warning(
                    'Telegram watchdog terminated by signal',
                    [
                        'signal' => $signal,
                        'pid' => getmypid(),
                        'listener_pid' => $listenerPid,
                        'stopping_listener' => $listenerPid !== null,
                    ]
                );

                $this->warn(
                    "Telegram watchdog received signal {$signal}, shutting down."
                );

                if ($listenerPid !== null) {
                    /*
                     * SIGTERM first with a grace period, so MadelineProto can
                     * unwind and log its own stop. Symfony escalates to SIGKILL
                     * if it does not.
                     */
                    try {
                        $this->listener?->stop(
                            self::SHUTDOWN_GRACE_SECONDS,
                            \SIGTERM
                        );
                    } catch (Throwable $e) {
                        Log::warning(
                            'Telegram watchdog could not stop the listener cleanly',
                            [
                                'listener_pid' => $listenerPid,
                                'error' => $e->getMessage(),
                            ]
                        );
                    }
                }

                exit(self::SUCCESS);
            }
        );
    }

    /**
     * Is this process the active supervisor?
     *
     * The lock is taken once and then held for the rest of the process, so a
     * spare that wins it here keeps it. A lock file that cannot be used at all
     * must never leave the listener unsupervised, so it counts as "yes".
     */
    private function isSupervisor(): bool
    {
        if (TelegramProcessLock::holds(self::LOCK_NAME)) {
            return true;
        }

        $state = TelegramProcessLock::attempt(self::LOCK_NAME);

        if ($state === TelegramProcessLock::UNAVAILABLE) {
            Log::warning(
                'Telegram watchdog single-instance guard unavailable, supervising anyway',
                [
                    'pid' => getmypid(),
                    'lock_file' => TelegramProcessLock::path(self::LOCK_NAME),
                    'error' => TelegramProcessLock::lastError(),
                ]
            );

            return true;
        }

        return $state === TelegramProcessLock::ACQUIRED;
    }

    /**
     * Take the one spare slot, if it is still free.
     *
     * A lock file that cannot be used must never turn into a refusal to run,
     * so an unusable guard answers "yes" here as it does everywhere else.
     */
    private function claimSpareSlot(): bool
    {
        if (TelegramProcessLock::holds(self::SPARE_LOCK_NAME)) {
            return true;
        }

        $state = TelegramProcessLock::attempt(self::SPARE_LOCK_NAME);

        return $state !== TelegramProcessLock::HELD;
    }

    /**
     * Wait without restarting anything, logging the reason once per change.
     */
    private function standby(
        string $reason,
        string $message
    ): void {
        if ($this->standbyReason !== $reason) {
            $this->standbyReason = $reason;

            $this->info($message . '.');

            Log::info(
                'Telegram watchdog standing by',
                [
                    'reason' => $reason,
                    'pid' => getmypid(),
                    'recheck_seconds' => self::STANDBY_SECONDS,
                ]
            );
        }

        sleep(self::STANDBY_SECONDS);
    }

    /**
     * @return bool whether the watchdog was standing by until now
     */
    private function clearStandby(): bool
    {
        if ($this->standbyReason === null) {
            return false;
        }

        $this->standbyReason = null;

        $this->info('Taking over supervision.');

        Log::info(
            'Telegram watchdog resumed supervision',
            [
                'pid' => getmypid(),
            ]
        );

        return true;
    }

    /**
     * One short line explaining the failure, for the Saved Messages notice.
     *
     * @param array{exit_code: int|null, signal: int|null, duration: float, pid: int|null, stdout: string, stderr: string, spawn_error: ?Throwable} $result
     * @param array<string, mixed>|null $marker
     */
    private function failureDetail(
        array $result,
        ?array $marker
    ): ?string {
        if ($result['spawn_error'] !== null) {
            return $result['spawn_error']->getMessage();
        }

        /*
         * The listener prints its own verdict as the last thing it does
         * ("Event loop terminated (exception), exit code 1. AssertionError:
         * ..."), which says more than anything the watchdog can see.
         */
        foreach ([$result['stderr'], $result['stdout']] as $output) {
            /*
             * The listener writes through Artisan's styled output, so its
             * lines arrive wrapped in ANSI colour codes. Those would be pasted
             * verbatim into a Telegram message.
             */
            $plain = (string) preg_replace(
                '/\x1b\[[0-9;?]*[ -\/]*[@-~]/',
                '',
                (string) $output
            );

            $lines = preg_split('/\R/', trim($plain)) ?: [];

            $lines = array_values(
                array_filter(
                    array_map('trim', $lines),
                    static fn (string $line): bool => $line !== '',
                )
            );

            if ($lines !== []) {
                return end($lines);
            }
        }

        $reason = $marker['reason'] ?? null;

        return is_string($reason)
            ? $reason
            : null;
    }

    /**
     * Run one listener process to completion.
     *
     * @return array{exit_code: int|null, signal: int|null, duration: float, pid: int|null, stdout: string, stderr: string, spawn_error: ?Throwable}
     */
    private function runListener(): array
    {
        /*
         * Absolute artisan path + explicit working directory: the watchdog is
         * usually spawned with nohup from a queue worker, and a relative
         * "artisan" silently resolves against whatever CWD it inherited.
         */
        $process = new Process(
            [
                PHP_BINARY,
                base_path('artisan'),
                'telegram:start-loop',
            ],
            base_path()
        );

        /*
         * The listener is meant to run forever, so no wall-clock timeout.
         */
        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        $startedAt = microtime(true);
        $spawnError = null;
        $pid = null;

        $stdoutTail = '';
        $stderrTail = '';

        $onOutput = function (
            string $type,
            string $buffer
        ) use (&$stdoutTail, &$stderrTail): void {
            $this->output->write($buffer);

            if ($type === Process::ERR) {
                $stderrTail = mb_substr(
                    $stderrTail . $buffer,
                    -self::OUTPUT_TAIL_BYTES
                );

                return;
            }

            $stdoutTail = mb_substr(
                $stdoutTail . $buffer,
                -self::OUTPUT_TAIL_BYTES
            );
        };

        try {
            $process->start($onOutput);

            /*
             * Published for the signal handler: when the watchdog is asked to
             * stop, the listener has to be stopped with it rather than left
             * running with nothing watching it.
             */
            $this->listener = $process;

            $pid = $process->getPid();

            Log::info(
                'Telegram watchdog listener process spawned',
                [
                    'listener_pid' => $pid,
                    'watchdog_pid' => getmypid(),
                    'command' => $process->getCommandLine(),
                ]
            );

            /*
             * isRunning() pumps the pipes and invokes $onOutput, after which
             * Symfony's internal buffers are dropped. Without this the
             * watchdog would accumulate days worth of listener stdout in
             * memory (MadelineProto dumps full stack traces there).
             */
            while ($process->isRunning()) {
                $process->clearOutput();
                $process->clearErrorOutput();

                usleep(200_000);
            }

            $process->wait();
        } catch (Throwable $e) {
            $spawnError = $e;

            Log::critical(
                'Telegram watchdog failed to execute listener process',
                [
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]
            );

            $this->error(
                "Failed to execute listener: {$e->getMessage()}"
            );
        }

        $this->listener = null;

        return [
            'exit_code' => $this->exitCode($process),
            'signal' => $this->termSignal($process),
            'duration' => round(microtime(true) - $startedAt, 3),
            'pid' => $pid,
            'stdout' => $stdoutTail,
            'stderr' => $stderrTail,
            'spawn_error' => $spawnError,
        ];
    }

    /**
     * Why the listener process ended.
     *
     * @param array{exit_code: int|null, signal: int|null, duration: float, pid: int|null, stdout: string, stderr: string, spawn_error: ?Throwable} $result
     * @param array<string, mixed>|null $marker
     */
    private function classifyTermination(
        array $result,
        ?array $marker
    ): string {
        $exitCode = $result['exit_code'];

        return match (true) {
            $result['spawn_error'] !== null => 'spawn_failed',
            $result['signal'] !== null => 'killed_by_signal',
            $exitCode === null => 'unknown',
            $exitCode === 0 => 'graceful_stop',
            $exitCode === TelegramDriverCheckCommand::EXIT_ALREADY_RUNNING
                => 'session_already_owned',
            $exitCode === self::INVALID => 'misconfigured',
            default => $marker['reason'] ?? 'crashed',
        };
    }

    /**
     * Turn the raw process result into one explicit "why did it stop" record.
     *
     * @param array{exit_code: int|null, signal: int|null, duration: float, pid: int|null, stdout: string, stderr: string, spawn_error: ?Throwable} $result
     * @param array<string, mixed>|null $marker
     */
    private function reportTermination(
        array $result,
        ?array $marker,
        string $kind,
        int $restartCount,
        int $fastFailures
    ): void {
        $exitCode = $result['exit_code'];

        $payload = [
            'termination' => $kind,
            'exit_code' => $exitCode,
            'term_signal' => $result['signal'],
            'listener_pid' => $result['pid'],
            'watchdog_pid' => getmypid(),
            'uptime_seconds' => $result['duration'],
            'restart_count' => $restartCount,
            'consecutive_fast_failures' => $fastFailures,
            'health_marker' => $marker,
            'stdout_tail' => $result['stdout'],
            'stderr_tail' => $result['stderr'],
        ];

        $this->warn(
            "telegram:start-loop stopped ({$kind}). Exit code: "
                . ($exitCode ?? 'null')
                . ', uptime: ' . $result['duration'] . 's'
        );

        /*
         * A refused start is not an incident: some other process is doing the
         * work. Logging it as critical every 30 seconds buries the crash that
         * actually needs looking at.
         */
        if ($kind === 'session_already_owned') {
            Log::info(
                'Telegram driver check listener not started, session owned by another process',
                $payload
            );

            return;
        }

        if ($kind === 'graceful_stop') {
            Log::warning(
                'Telegram driver check listener stopped gracefully',
                $payload
            );

            return;
        }

        Log::critical(
            'Telegram driver check listener stopped abnormally',
            $payload
        );
    }

    /**
     * Linear backoff on consecutive fast failures, capped at --max-delay.
     *
     * A listener that keeps dying within a minute is a structural problem
     * (misconfiguration, another owner of the session, no authorization);
     * hammering it every 10 seconds only floods the logs.
     */
    private function backoffDelay(
        int $delay,
        int $maxDelay,
        int $fastFailures
    ): int {
        if ($fastFailures <= 1) {
            return $delay;
        }

        return (int) min(
            $maxDelay,
            $delay * $fastFailures
        );
    }

    /**
     * Signal that killed the listener, or null when it exited on its own.
     *
     * Symfony throws when the process was never started and reports 0/-1 for a
     * normal exit, so both cases are normalised to null.
     */
    private function termSignal(Process $process): ?int
    {
        try {
            if (!$process->hasBeenSignaled()) {
                return null;
            }

            $signal = $process->getTermSignal();

            return $signal > 0
                ? $signal
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function exitCode(Process $process): ?int
    {
        try {
            return $process->getExitCode();
        } catch (Throwable) {
            return null;
        }
    }
}
