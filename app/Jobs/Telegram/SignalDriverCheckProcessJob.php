<?php

namespace App\Jobs\Telegram;

use App\Application\Telegram\Exceptions\DriverCheckProcessException;
use App\Application\Telegram\Services\DriverCheckProcessMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Restarts the listener or stops the watchdog from inside the queue
 * worker.
 *
 * The web server usually runs as another user than the watchdog, and the
 * kernel refuses its signals. The worker is the one that started the
 * watchdog (StartTelegramWatchdogJob), so it runs as the same user and
 * its signal gets through.
 */
class SignalDriverCheckProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const RESTART = 'restart';

    public const STOP = 'stop';

    /**
     * A signal is only worth sending about the processes of this moment;
     * retrying it later would hit whatever runs by then.
     */
    public int $tries = 1;

    public function __construct(
        public readonly string $action,
        public readonly ?int $byUserId = null,
    ) {
    }

    public function handle(DriverCheckProcessMonitor $monitor): void
    {
        try {
            match ($this->action) {
                self::RESTART => $monitor->restartListener($this->byUserId, fromWorker: true),
                self::STOP => $monitor->stop($this->byUserId, fromWorker: true),
            };
        } catch (DriverCheckProcessException $e) {
            Log::error('Driver check process signal from the queue worker failed', [
                'action' => $this->action,
                'reason' => $e->reason,
                'user_id' => $this->byUserId,
            ]);
        }
    }
}
