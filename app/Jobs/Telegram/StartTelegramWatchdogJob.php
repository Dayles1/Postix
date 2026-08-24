<?php

namespace App\Jobs\Telegram;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class StartTelegramWatchdogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/watchdog.log');

        $command = sprintf(
            'nohup %s %s telegram:watchdog >> %s 2>&1 &',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($artisan),
            escapeshellarg($logFile)
        );

        try {
            Process::fromShellCommandline($command)
                ->setTimeout(10)
                ->run();

            Log::info(
                'Telegram watchdog start command executed',
                [
                    'command' => $command,
                    'log_file' => $logFile,
                ]
            );
        } catch (Throwable $e) {
            Log::critical(
                'Failed to start Telegram watchdog',
                [
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]
            );

            throw $e;
        }
    }
}