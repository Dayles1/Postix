<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Exceptions\DriverCheckProcessException;
use App\Application\Telegram\Services\DriverCheckProcessMonitor;
use App\Application\Telegram\Services\ListenersControl;
use App\Console\Commands\Telegram\TelegramDriverCheckCommand;
use App\Console\Commands\Telegram\TelegramListenersWatchdogCommand;
use App\Console\Commands\Telegram\TelegramWatchdogCommand;
use App\Http\Controllers\Controller;
use App\Jobs\Telegram\StartListenersWatchdogJob;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramListener;
use App\Telegram\TelegramProcessLock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes page: every account that runs - Driver Check and all the other
 * listeners are the same kind of thing here - with its state, and the
 * buttons for them: start everything, restart one or all, stop everything.
 *
 * Everything goes through the one watchdog (telegram:listeners-watchdog, see
 * ListenersControl). Driver Check is one of its rows; when it is kept alive by
 * its own watchdog (telegram:watchdog) instead, the same buttons reach it
 * through DriverCheckProcessMonitor.
 */
final class ListenerProcessController extends Controller
{
    /** The row of Driver Check in the list, and its key in a restart request. */
    public const DRIVER_CHECK = 'driver-check';

    public function __construct(
        private readonly ListenersControl $control,
        private readonly DriverCheckProcessMonitor $driverCheck,
    ) {}

    /**
     * The watchdog and every account with its state, Driver Check first.
     */
    public function index(): JsonResponse
    {
        try {
            $listeners = TelegramListener::query()->with('account')->orderBy('name')->get();
        } catch (Throwable) {
            /* The table is not there yet (migration pending): Driver Check alone. */
            $listeners = new Collection();
        }

        $master = $this->control->running();

        return response()->json([
            'status' => $this->status(),
            'listeners' => [
                $this->driverCheckRow(),
                ...$listeners->map(fn (TelegramListener $l): array => [
                    'key' => $l->key,
                    'name' => $l->name,
                    'account' => $l->account?->phone,
                    'is_enabled' => (bool) $l->is_enabled,
                    'is_driver_check' => false,
                    /* The process holds its lock while the event loop runs. */
                    'running' => TelegramProcessLock::isHeld($l->lockName()),
                    /* What stops it from starting at all, when the watchdog last tried and how that ended. */
                    'blocker' => $this->blocker($l->account),
                    'last_start' => $this->remembered(TelegramListenersWatchdogCommand::LAST_START_CACHE, $l->key)['at'] ?? null,
                    'last_exit' => $this->remembered(TelegramListenersWatchdogCommand::LAST_EXIT_CACHE, $l->key),
                    /* A restart is done by the watchdog, so there has to be one. */
                    'can_restart' => $master,
                ])->all(),
            ],
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        if ($this->control->running()) {
            return response()->json([
                'message' => __('telegram.listeners.processes.messages.already_running'),
                'status' => $this->status(),
            ], 409);
        }

        /*
         * Through the queue worker, like Driver Check's own watchdog: it runs
         * as the user the sessions and the lock files belong to. A process
         * spawned from here would belong to PHP-FPM's user instead and could
         * not open them.
         */
        StartListenersWatchdogJob::dispatch()->onQueue('telegram');

        $message = __('telegram.listeners.processes.messages.start_queued');

        Log::info('Listeners watchdog start requested from the panel', ['user_id' => $request->user()?->id]);

        return response()->json([
            'message' => $message,
            'status' => $this->status(),
        ]);
    }

    /**
     * One account (key) or all of them (no key).
     */
    public function restart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:32'],
        ]);

        $key = $data['key'] ?? null;
        $userId = $request->user()?->id;

        if ($key !== null && $key !== self::DRIVER_CHECK && ! $this->listenerExists($key)) {
            return response()->json(['message' => __('telegram.listeners.processes.messages.unknown')], 422);
        }

        if ($key === self::DRIVER_CHECK) {
            return $this->restartDriverCheck($userId);
        }

        $master = $this->control->running();

        if ($key !== null && ! $master) {
            return $this->notRunning();
        }

        if ($key === null && ! $master && ! $this->driverCheckSupervised()) {
            return $this->notRunning();
        }

        if ($master) {
            /* All of them: every listener the watchdog runs; Driver Check is restarted below. */
            $this->control->request(ListenersControl::RESTART, $key ?? ListenersControl::ALL, $userId);
        }

        if ($key === null && $this->driverCheckSupervised()) {
            $this->signalDriverCheck(fn () => $this->driverCheck->restartListener($userId));
        }

        Log::warning('Listeners restart requested from the panel', ['key' => $key ?? 'all', 'user_id' => $userId]);

        return response()->json([
            'message' => __('telegram.listeners.processes.messages.restart_requested'),
            'status' => $this->status(),
        ]);
    }

    /**
     * Everything stops: the watchdog stops every process it runs, then
     * itself; Driver Check under its own watchdog is stopped the same way.
     */
    public function stop(Request $request): JsonResponse
    {
        $master = $this->control->running();
        $supervised = $this->driverCheckSupervised();

        if (! $master && ! $supervised) {
            return $this->notRunning();
        }

        $userId = $request->user()?->id;

        if ($master) {
            $this->control->request(ListenersControl::STOP, null, $userId);
        }

        if ($supervised) {
            $this->signalDriverCheck(fn () => $this->driverCheck->stop($userId));
        }

        Log::warning('Listeners stop requested from the panel', ['user_id' => $userId]);

        return response()->json([
            'message' => __('telegram.listeners.processes.messages.stop_requested'),
            'status' => $this->status(),
        ]);
    }

    private function restartDriverCheck(?int $userId): JsonResponse
    {
        try {
            $this->driverCheck->restartListener($userId);
        } catch (DriverCheckProcessException $e) {
            return response()->json([
                'message' => $e->translated(),
                'status' => $this->status(),
            ], 409);
        }

        return response()->json([
            'message' => __('telegram.listeners.processes.messages.restart_requested'),
            'status' => $this->status(),
        ]);
    }

    /**
     * Driver Check's own chain may already be going down or not be up at
     * all; that is not an error of the button that was pressed.
     *
     * @param callable(): mixed $action
     */
    private function signalDriverCheck(callable $action): void
    {
        try {
            $action();
        } catch (DriverCheckProcessException $e) {
            Log::info('Driver Check was not signalled from the processes page', ['reason' => $e->reason]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function driverCheckRow(): array
    {
        $id = config('services.telegram.driver_check_account_id');

        $account = $id === null || $id === '' ? null : TelegramAccount::query()->find((int) $id);

        return [
            'key' => self::DRIVER_CHECK,
            'name' => 'Driver Check',
            'account' => $account?->phone,
            'is_enabled' => true,
            'is_driver_check' => true,
            'running' => TelegramProcessLock::isHeld(TelegramDriverCheckCommand::LOCK_NAME),
            'blocker' => $this->blocker($account),
            'last_start' => $this->remembered(TelegramListenersWatchdogCommand::LAST_START_CACHE, self::DRIVER_CHECK)['at'] ?? null,
            'last_exit' => $this->remembered(TelegramListenersWatchdogCommand::LAST_EXIT_CACHE, self::DRIVER_CHECK),
            /* Its own watchdog, or the common one, restarts it. */
            'can_restart' => $this->driverCheckSupervised(),
        ];
    }

    /**
     * Driver Check has a watchdog of its own running (the older setup): it
     * is not the common watchdog's child, but the buttons reach it anyway.
     */
    private function driverCheckSupervised(): bool
    {
        return TelegramProcessLock::isHeld(TelegramWatchdogCommand::LOCK_NAME);
    }

    /**
     * @return array<string, mixed>
     */
    private function status(): array
    {
        $status = $this->control->status();

        return $status + [
            'driver_check_supervised' => $this->driverCheckSupervised(),
            /* Restart and stop have something to act on: the common watchdog or Driver Check's own. */
            'can_control' => $status['running'] || $this->driverCheckSupervised(),
        ];
    }

    /**
     * Why an account cannot be started, whatever the watchdog does: no
     * account chosen, not logged in, no session file on this server.
     */
    private function blocker(?TelegramAccount $account): ?string
    {
        return match (true) {
            $account === null => 'no_account',
            ! $account->is_authorized => 'not_authorized',
            ! $account->hasSessionFile() => 'no_session',
            default => null,
        };
    }

    /**
     * What the watchdog kept about an account (the last start, the last exit).
     *
     * @return array<string, mixed>|null
     */
    private function remembered(string $prefix, string $key): ?array
    {
        $value = Cache::get($prefix . $key);

        return is_array($value) ? $value : null;
    }

    private function listenerExists(string $key): bool
    {
        try {
            return TelegramListener::query()->where('key', $key)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function notRunning(): JsonResponse
    {
        return response()->json([
            'message' => __('telegram.listeners.processes.messages.not_running'),
            'status' => $this->status(),
        ], 409);
    }
}
