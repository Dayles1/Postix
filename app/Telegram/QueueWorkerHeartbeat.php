<?php

declare(strict_types=1);

namespace App\Telegram;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * What the queue page knows about the worker.
 *
 * The worker is a plain `queue:work` process nobody else can see into, so
 * it leaves a trace in the cache from its own events: a pulse on every loop
 * (even an idle worker loops every few seconds) and the last job it
 * finished or lost. Nothing here may ever fail a job, hence the swallowed
 * exceptions.
 */
final class QueueWorkerHeartbeat
{
    private const PULSE_KEY = 'queue-worker:pulse';

    private const PROCESSED_KEY = 'queue-worker:last-processed';

    private const FAILED_KEY = 'queue-worker:last-failed';

    /**
     * An idle worker loops every few seconds; the pulse is written at most
     * this often so the cache is not hit on every iteration.
     */
    private const PULSE_EVERY_SECONDS = 10;

    /**
     * A worker that has not pulsed for this long is shown as not running.
     */
    public const ALIVE_WITHIN_SECONDS = 60;

    private const TTL_SECONDS = 7 * 24 * 3600;

    private static int $lastPulseAt = 0;

    public static function looping(Looping $event): void
    {
        $now = time();

        if ($now - self::$lastPulseAt < self::PULSE_EVERY_SECONDS) {
            return;
        }

        self::$lastPulseAt = $now;

        self::put(self::PULSE_KEY, [
            'at' => $now,
            'pid' => getmypid(),
            'connection' => $event->connectionName,
            'queues' => array_values(array_filter(explode(',', (string) $event->queue))),
        ]);
    }

    public static function processed(JobProcessed $event): void
    {
        self::put(self::PROCESSED_KEY, [
            'at' => time(),
            'job' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
        ]);
    }

    public static function failed(JobFailed $event): void
    {
        self::put(self::FAILED_KEY, [
            'at' => time(),
            'job' => $event->job->resolveName(),
            'queue' => $event->job->getQueue(),
            'error' => mb_substr($event->exception->getMessage(), 0, 300),
        ]);
    }

    /**
     * @return array{pulse: ?array, last_processed: ?array, last_failed: ?array}
     */
    public static function read(): array
    {
        return [
            'pulse' => self::get(self::PULSE_KEY),
            'last_processed' => self::get(self::PROCESSED_KEY),
            'last_failed' => self::get(self::FAILED_KEY),
        ];
    }

    private static function put(string $key, array $value): void
    {
        try {
            Cache::put($key, $value, self::TTL_SECONDS);
        } catch (Throwable) {
            //
        }
    }

    private static function get(string $key): ?array
    {
        try {
            $value = Cache::get($key);

            return is_array($value) ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }
}
