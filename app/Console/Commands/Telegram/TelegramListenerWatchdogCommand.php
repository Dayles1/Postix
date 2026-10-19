<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Application\Telegram\Services\DeveloperAlerts;
use App\Models\Telegram\TelegramListener;
use App\Telegram\TelegramProcessLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Keeps one extra listener (telegram:listener) running: starts it, starts
 * it again when it dies, backs off when it dies right away. The one-line
 * supervisor entry for a listener, next to the Driver Check watchdog:
 *
 *     php artisan telegram:listener-watchdog ceo
 *
 * A listener that is off, unknown or missing its account is not restarted
 * in a loop: the watchdog waits and looks again, so switching it on in the
 * panel is enough.
 */
final class TelegramListenerWatchdogCommand extends Command
{
    /** A listener that dies faster than this did not really run. */
    private const FAST_FAILURE_SECONDS = 60;

    /** How often a waiting watchdog looks again, seconds. */
    private const STANDBY_SECONDS = 15;

    protected $signature = 'telegram:listener-watchdog {key : The listener key}
                            {--delay=10 : Delay before a restart, seconds}
                            {--max-delay=300 : Longest back-off, seconds}';

    protected $description = 'Keep one extra listener account running and restart it when it stops';

    private ?Process $child = null;

    private bool $stopping = false;

    public function handle(): int
    {
        $key = (string) $this->argument('key');

        $lock = 'listener-watchdog-' . $key;

        /* One watchdog per listener. */
        if (TelegramProcessLock::attempt($lock) === TelegramProcessLock::HELD) {
            $this->error("A watchdog for {$key} is already running.");

            return self::FAILURE;
        }

        $this->trapStopSignals();

        $baseDelay = max(1, (int) $this->option('delay'));
        $maxDelay = max($baseDelay, (int) $this->option('max-delay'));
        $delay = $baseDelay;
        $fastFailures = 0;

        $this->info("Watchdog of listener {$key}, pid " . getmypid());

        while (! $this->stopping) {
            $listener = TelegramListener::query()->where('key', $key)->first();

            if ($listener === null || ! $listener->is_enabled || $listener->telegram_account_id === null) {
                $this->standby();

                continue;
            }

            if (TelegramProcessLock::isHeldByOtherProcess($listener->lockName())) {
                $this->standby();

                continue;
            }

            [$exit, $seconds] = $this->spawn($key);

            if ($this->stopping) {
                break;
            }

            /* Not startable (off, no session): not a crash, wait. */
            if ($exit === self::INVALID) {
                $this->standby();

                continue;
            }

            Log::warning('Telegram listener watchdog: the listener stopped', [
                'key' => $key,
                'exit_code' => $exit,
                'seconds' => $seconds,
                'next_delay' => $delay,
            ]);

            $fastFailures = $seconds < self::FAST_FAILURE_SECONDS ? $fastFailures + 1 : 0;

            /* Dying right after every start: the developer hears of it (DeveloperAlerts). */
            if ($fastFailures >= 3) {
                app(DeveloperAlerts::class)->report(
                    kind: 'listener_down',
                    title: 'Листенер «' . $key . '» постоянно падает',
                    details: 'Процесс завершился ' . $fastFailures . ' раз подряд в течение минуты после запуска (код ' . $exit . '). '
                        . 'Подробности: storage/logs и madeline.log.',
                    context: ['Код выхода' => $exit, 'Работал, с' => $seconds],
                    listenerKey: $key,
                    about: $key,
                );
            }

            $this->sleep($delay);

            $delay = $seconds < self::FAST_FAILURE_SECONDS
                ? min($maxDelay, $delay * 2)
                : $baseDelay;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int|null, 1: float} exit code and how long it ran
     */
    private function spawn(string $key): array
    {
        /* Absolute artisan path and working directory: the watchdog is usually spawned from elsewhere. */
        $process = new Process([PHP_BINARY, base_path('artisan'), 'telegram:listener', $key], base_path());
        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        $startedAt = microtime(true);

        $process->start(fn (string $type, string $buffer) => $this->output->write($buffer));

        $this->child = $process;

        /* isRunning() pumps the pipes; the buffers are dropped so days of output are not kept. */
        while ($process->isRunning()) {
            $process->clearOutput();
            $process->clearErrorOutput();

            usleep(200_000);
        }

        $process->wait();

        $this->child = null;

        return [$process->getExitCode(), round(microtime(true) - $startedAt, 3)];
    }

    private function standby(): void
    {
        $this->sleep(self::STANDBY_SECONDS);
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

            /* The listener gets the chance to unwind its event loop and save the session. */
            $this->child?->signal(SIGTERM);
        };

        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }
}
