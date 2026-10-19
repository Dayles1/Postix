<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Services\ListenersControl;
use App\Http\Controllers\Controller;
use App\Jobs\Telegram\StartListenersWatchdogJob;
use App\Models\Telegram\TelegramListener;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The buttons of the Listeners page for the processes: start everything,
 * restart one listener or all of them, stop everything. They all go through
 * the one watchdog (telegram:listeners-watchdog), see ListenersControl.
 */
final class ListenerProcessController extends Controller
{
    public function __construct(private readonly ListenersControl $control) {}

    public function start(Request $request): JsonResponse
    {
        if ($this->control->running()) {
            return response()->json([
                'message' => __('telegram.listeners.processes.messages.already_running'),
                'status' => $this->control->status(),
            ], 409);
        }

        StartListenersWatchdogJob::dispatch()->onQueue('telegram');

        Log::info('Listeners watchdog start requested from the panel', ['user_id' => $request->user()?->id]);

        return response()->json([
            'message' => __('telegram.listeners.processes.messages.start_queued'),
            'status' => $this->control->status(),
        ]);
    }

    /**
     * One listener (key) or all of them (no key).
     */
    public function restart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:32'],
        ]);

        $key = $data['key'] ?? null;

        if ($key !== null && $key !== 'driver-check' && ! TelegramListener::query()->where('key', $key)->exists()) {
            return response()->json(['message' => __('telegram.listeners.processes.messages.unknown')], 422);
        }

        if (! $this->control->running()) {
            return $this->notRunning();
        }

        $this->control->request(ListenersControl::RESTART, $key ?? ListenersControl::ALL, $request->user()?->id);

        Log::warning('Listeners restart requested from the panel', ['key' => $key ?? 'all', 'user_id' => $request->user()?->id]);

        return response()->json([
            'message' => __('telegram.listeners.processes.messages.restart_requested'),
            'status' => $this->control->status(),
        ]);
    }

    /**
     * The watchdog stops every process it runs, then itself.
     */
    public function stop(Request $request): JsonResponse
    {
        if (! $this->control->running()) {
            return $this->notRunning();
        }

        $this->control->request(ListenersControl::STOP, null, $request->user()?->id);

        Log::warning('Listeners stop requested from the panel', ['user_id' => $request->user()?->id]);

        return response()->json([
            'message' => __('telegram.listeners.processes.messages.stop_requested'),
            'status' => $this->control->status(),
        ]);
    }

    private function notRunning(): JsonResponse
    {
        return response()->json([
            'message' => __('telegram.listeners.processes.messages.not_running'),
            'status' => $this->control->status(),
        ], 409);
    }
}
