<?php

namespace App\Jobs\Telegram;

use App\Application\Telegram\Services\GroupJoinControl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Starts the group join watchdog when the join is switched on in the panel,
 * so the workers do not wait for the scheduler's next minute. Runs in the
 * queue worker, the user the processes should belong to.
 */
class StartGroupJoinWatchdogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function handle(GroupJoinControl $control): void
    {
        try {
            $control->spawnWatchdog();
        } catch (Throwable $e) {
            Log::critical('Failed to start the group join watchdog', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }
}
