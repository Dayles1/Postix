<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Exceptions\DriverCheckProcessException;
use App\Application\Telegram\Services\DriverCheckProcessMonitor;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Behind the watchdog page: is the listener running, under supervision,
 * on the main account - and start / restart / stop.
 *
 * Access is enforced by the `role:driverCheck,superadmin` middleware on the
 * route group (see routes/web.php), matching the rest of the driver-check
 * panel.
 */
final class DriverCheckWatchdogController extends Controller
{
    public function __construct(
        private readonly DriverCheckProcessMonitor $monitor,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->monitor->status()]);
    }

    public function start(Request $request): JsonResponse
    {
        return $this->attempt(
            fn () => $this->monitor->start($request->user()?->id),
            'start',
        );
    }

    public function restartListener(Request $request): JsonResponse
    {
        return $this->attempt(
            fn () => $this->monitor->restartListener($request->user()?->id),
            'restart',
        );
    }

    public function stop(Request $request): JsonResponse
    {
        return $this->attempt(
            fn () => $this->monitor->stop($request->user()?->id),
            'stop',
        );
    }

    /**
     * @param Closure(): mixed $action returns SENT / QUEUED for a signal
     */
    private function attempt(Closure $action, string $name): JsonResponse
    {
        try {
            $outcome = $action();
        } catch (DriverCheckProcessException $e) {
            return response()->json([
                'message' => $e->translated(),
                'reason' => $e->reason,
            ], 409);
        }

        $key = match (true) {
            $name === 'start' => 'start_queued',
            $outcome === DriverCheckProcessMonitor::QUEUED => "{$name}_queued",
            default => "{$name}_sent",
        };

        return response()->json([
            'message' => __("telegram.watchdog.messages.{$key}"),
            'queued' => $outcome === DriverCheckProcessMonitor::QUEUED,
            'data' => $this->monitor->status(),
        ]);
    }
}
