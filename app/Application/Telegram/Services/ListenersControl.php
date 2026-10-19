<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Console\Commands\Telegram\TelegramListenersWatchdogCommand;
use App\Telegram\QueueWorkerHeartbeat;
use App\Telegram\TelegramProcessLock;

/**
 * The panel's hand on the one watchdog that runs all the listeners
 * (telegram:listeners-watchdog).
 *
 * The panel does not signal processes - PHP-FPM usually runs as another user
 * than the watchdog and the kernel would refuse. It writes a request
 * (restart one listener, restart all, stop) and the watchdog, which owns the
 * processes, reads the requests every couple of seconds and acts on them
 * itself. Starting the watchdog is the one thing left to the queue worker
 * (StartListenersWatchdogJob), as for Driver Check's.
 */
class ListenersControl
{
    public const SETTING = 'listeners.control';

    public const RESTART = 'restart';

    public const STOP = 'stop';

    /** For a restart: every process the watchdog runs. */
    public const ALL = '*';

    /** How many past requests are kept; the watchdog only needs the newest. */
    private const KEEP = 30;

    /**
     * Asks the watchdog to act; returns the request's number.
     *
     * @param string|null $key a listener key, "driver-check", or ALL for a restart
     */
    public function request(string $action, ?string $key = null, ?int $userId = null): int
    {
        $requests = $this->all();

        $id = $this->latestId() + 1;

        $requests[] = [
            'id' => $id,
            'action' => $action,
            'key' => $key,
            'user_id' => $userId,
            'at' => now()->toIso8601String(),
        ];

        \App\Models\Telegram\TelegramSetting::set(self::SETTING, array_slice($requests, -self::KEEP));

        return $id;
    }

    public function latestId(): int
    {
        $ids = array_map(fn (array $request): int => (int) ($request['id'] ?? 0), $this->all());

        return $ids === [] ? 0 : max($ids);
    }

    /**
     * The requests newer than $id, oldest first.
     *
     * @return list<array{id: int, action: string, key: string|null}>
     */
    public function after(int $id): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $request): bool => (int) ($request['id'] ?? 0) > $id,
        ));
    }

    /**
     * Whether the watchdog is up: the lock it holds while it runs.
     */
    public function running(): bool
    {
        return TelegramProcessLock::isHeldByOtherProcess(TelegramListenersWatchdogCommand::LOCK_NAME);
    }

    public function pid(): ?int
    {
        return $this->running() ? TelegramProcessLock::holderPid(TelegramListenersWatchdogCommand::LOCK_NAME) : null;
    }

    /**
     * What the panel shows: the watchdog and whether anything can start it.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $pulse = QueueWorkerHeartbeat::read()['pulse'];

        return [
            'running' => $this->running(),
            'pid' => $this->pid(),
            /* Starting it from the panel goes through the queue worker. */
            'queue_worker_alive' => $pulse !== null
                && time() - (int) ($pulse['at'] ?? 0) <= QueueWorkerHeartbeat::ALIVE_WITHIN_SECONDS,
            'command' => 'php artisan telegram:listeners-watchdog --with-driver-check',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function all(): array
    {
        $saved = \App\Models\Telegram\TelegramSetting::get(self::SETTING);

        return is_array($saved) ? array_values(array_filter($saved, 'is_array')) : [];
    }
}
