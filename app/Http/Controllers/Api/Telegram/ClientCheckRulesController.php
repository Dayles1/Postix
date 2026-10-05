<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Services\ClientCheckRules;
use App\Application\Telegram\Services\ClientCheckRulesStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\ClientCheckRulesRequest;
use Illuminate\Http\JsonResponse;

/**
 * Behind the penalty settings page: the levels, what reaches each one, the
 * phrases they say and the timings around them.
 *
 * The listener reads them on every penalty, so a save applies from the next
 * one on. Access is enforced by the `role:driverCheck,superadmin` middleware
 * on the route group (see routes/web.php).
 */
final class ClientCheckRulesController extends Controller
{
    public function __construct(
        private readonly ClientCheckRulesStore $store,
    ) {
    }

    public function show(): JsonResponse
    {
        return $this->respond();
    }

    public function update(ClientCheckRulesRequest $request): JsonResponse
    {
        $this->store->save(
            ClientCheckRules::fromArray($request->validated()),
        );

        return $this->respond(__('telegram.penalty_settings.messages.saved'));
    }

    /**
     * Back to config/client_checks.php.
     */
    public function destroy(): JsonResponse
    {
        $this->store->reset();

        return $this->respond(__('telegram.penalty_settings.messages.reset'));
    }

    private function respond(?string $message = null): JsonResponse
    {
        return response()->json([
            'data' => $this->store->current()->toArray(),
            'defaults' => $this->store->defaults()->toArray(),
            'customised' => $this->store->isCustomised(),
            ...($message !== null ? ['message' => $message] : []),
        ]);
    }
}
