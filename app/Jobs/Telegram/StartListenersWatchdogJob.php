<?php

namespace App\Jobs\Telegram;

use App\Application\Telegram\Services\ListenersControl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Starts the one watchdog that runs every listener and Driver Check, from
 * the panel's "Start all". Like StartTelegramWatchdogJob it runs in the
 * queue worker, which is the user the processes should belong to.
 *
 * Nothing happens when the watchdog is already up; a second copy would
 * exit at once on its own lock anyway.
 */
class StartListenersWatchdogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function handle(ListenersControl $control): void
    {
        try {
            $control->spawnWatchdog();
        } catch (Throwable $e) {
            Log::critical('Failed to start the listeners watchdog', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }
}
