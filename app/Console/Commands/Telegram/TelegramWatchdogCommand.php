<?php

namespace App\Console\Commands\Telegram;

use App\Telegram\TelegramListenerHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class TelegramWatchdogCommand extends Command
{
    /**
     * A listener that dies faster than this did not really run: it failed to
     * start. Restarting it every $delay seconds forever only hides the problem,
     * so the delay is backed off instead.
     */
    private const FAST_FAILURE_SECONDS = 60;

    /**
     * How much of the listener output is kept for the crash report.
     */
    private const OUTPUT_TAIL_BYTES = 4000;

    protected $signature = 'telegram:watchdog
                            {--delay=10 : Delay before restart in seconds}
                            {--max-delay=300 : Maximum backoff delay in seconds}
                            {--max-restarts=500 : Maximum restarts, 0 = unlimited}';

    protected $description = 'Watch Telegram driver check listener and restart it when stopped';

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

        $maxRestarts = max(
            0,
            (int) $this->option('max-restarts')
        );

        $restartCount = 0;
        $fastFailures = 0;

        $this->info(
            'Telegram watchdog started (pid ' . getmypid() . ').'
        );

        Log::info(
            'Telegram watchdog started',
            [
                'pid' => getmypid(),
                'delay' => $delay,
                'max_delay' => $maxDelay,
                'max_restarts' => $maxRestarts,
            ]
        );

        while (true) {
            $restartCount++;

            if (
                $maxRestarts > 0 &&
                $restartCount > $maxRestarts
            ) {
                $this->error(
                    "Maximum restart count reached: {$maxRestarts}"
                );

                Log::critical(
                    'Telegram watchdog reached maximum restart count',
                    [
                        'restart_count' => $restartCount - 1,
                        'max_restarts' => $maxRestarts,
                    ]
                );

                return self::FAILURE;
            }

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

            $fastFailures = $result['duration'] < self::FAST_FAILURE_SECONDS
                ? $fastFailures + 1
                : 0;

            $this->reportTermination($result, $restartCount, $fastFailures);

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
     * Turn the raw process result into one explicit "why did it stop" record.
     *
     * @param array{exit_code: int|null, signal: int|null, duration: float, pid: int|null, stdout: string, stderr: string, spawn_error: ?Throwable} $result
     */
    private function reportTermination(
        array $result,
        int $restartCount,
        int $fastFailures
    ): void {
        $exitCode = $result['exit_code'];

        /*
         * Written by the listener itself when it detected a dead update loop
         * or a failed start, so the watchdog can log the real reason.
         */
        $marker = TelegramListenerHealth::take();

        $kind = match (true) {
            $result['spawn_error'] !== null => 'spawn_failed',
            $result['signal'] !== null => 'killed_by_signal',
            $exitCode === null => 'unknown',
            $exitCode === 0 => 'graceful_stop',
            $exitCode === TelegramDriverCheckCommand::EXIT_ALREADY_RUNNING
                => 'session_already_owned',
            $exitCode === self::INVALID => 'misconfigured',
            default => $marker['reason'] ?? 'crashed',
        };

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
