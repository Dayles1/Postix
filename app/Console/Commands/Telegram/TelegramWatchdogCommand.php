<?php

namespace App\Console\Commands\Telegram;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class TelegramWatchdogCommand extends Command
{
    protected $signature = 'telegram:watchdog
                            {--delay=10 : Delay before restart in seconds}
                            {--max-restarts=5 : Maximum restarts, 0 = unlimited}';

    protected $description = 'Watch Telegram driver check listener and restart it when stopped';

    public function handle(): int
    {
        $delay = max(
            1,
            (int) $this->option('delay')
        );

        $maxRestarts = max(
            0,
            (int) $this->option('max-restarts')
        );

        $restartCount = 0;

        $this->info('🚀 Telegram watchdog started.');

        Log::info(
            'Telegram watchdog started',
            [
                'delay' => $delay,
                'max_restarts' => $maxRestarts,
            ]
        );

        while (true) {
            $restartCount++;

            /*
             * Проверяем лимит рестартов.
             * 0 = unlimited.
             */
            if (
                $maxRestarts > 0 &&
                $restartCount > $maxRestarts
            ) {
                $this->error(
                    "❌ Maximum restart count reached: {$maxRestarts}"
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
                "▶️ Starting telegram:start-loop (attempt #{$restartCount})"
            );

            Log::info(
                'Telegram watchdog starting listener',
                [
                    'restart_count' => $restartCount,
                ]
            );

            $process = new Process([
                PHP_BINARY,
                'artisan',
                'telegram:start-loop',
            ]);

            /*
             * Listener должен работать постоянно,
             * поэтому timeout отключаем.
             */
            $process->setTimeout(null);

            try {
                $process->run(
                    function (
                        string $type,
                        string $buffer
                    ): void {
                        $this->output->write($buffer);
                    }
                );
            } catch (Throwable $e) {
                Log::critical(
                    'Telegram watchdog failed to execute listener process',
                    [
                        'error' => $e->getMessage(),
                        'exception' => $e::class,
                    ]
                );

                $this->error(
                    "❌ Failed to execute listener: {$e->getMessage()}"
                );
            }

            $exitCode = $process->getExitCode();

            $this->warn(
                "⚠️ telegram:start-loop stopped. Exit code: "
                . ($exitCode ?? 'null')
            );

            Log::warning(
                'Telegram driver check listener stopped',
                [
                    'exit_code' => $exitCode,
                    'restart_count' => $restartCount,
                    'stdout' => mb_substr(
                        $process->getOutput(),
                        0,
                        2000
                    ),
                    'stderr' => mb_substr(
                        $process->getErrorOutput(),
                        0,
                        2000
                    ),
                ]
            );

            /*
             * Ждём перед повторным запуском.
             */
            $this->info(
                "⏳ Restarting in {$delay} seconds..."
            );

            sleep($delay);
        }
    }
}