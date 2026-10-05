<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Queries\ListClientChecks;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\ClientCheckIndexRequest;
use App\Http\Resources\Telegram\ClientCheckResource;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Behind the CRM penalties page. The list is read only: the listener owns
 * the flow, and retries what failed by itself while the penalty is fresh.
 * What the page does control are the two switches of that flow.
 *
 * Access is enforced by the `role:driverCheck,superadmin` middleware on the
 * route group (see routes/web.php).
 */
final class ClientCheckController extends Controller
{
    public function index(
        ClientCheckIndexRequest $request,
        ListClientChecks $query,
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        return ClientCheckResource::collection(
            $query->execute($filters),
        )->additional([
            'stats' => $query->stats($filters),
            'settings' => $this->currentSettings(),
        ]);
    }

    public function settings(): JsonResponse
    {
        return response()->json([
            'data' => $this->currentSettings(),
        ]);
    }

    /**
     * Either switch alone may be sent. The listener reads them on every
     * penalty, so the change applies from the next one on.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['sometimes', 'required', 'boolean'],
            'comments_enabled' => ['sometimes', 'required', 'boolean'],
            'operation_enabled' => ['sometimes', 'required', 'boolean'],
            'sales_enabled' => ['sometimes', 'required', 'boolean'],
        ]);

        if (array_key_exists('enabled', $validated)) {
            TelegramSetting::set(
                TelegramSetting::CLIENT_CHECKS_ENABLED,
                (bool) $validated['enabled'],
            );
        }

        if (array_key_exists('comments_enabled', $validated)) {
            TelegramSetting::set(
                TelegramSetting::CLIENT_CHECK_COMMENTS_ENABLED,
                (bool) $validated['comments_enabled'],
            );
        }

        foreach (OperationUser::ROLES as $role) {
            if (array_key_exists("{$role}_enabled", $validated)) {
                TelegramSetting::set(
                    TelegramSetting::roleKey($role),
                    (bool) $validated["{$role}_enabled"],
                );
            }
        }

        return response()->json([
            'data' => $this->currentSettings(),
            'message' => __('telegram.penalties.settings.saved'),
        ]);
    }

    /**
     * @return array{enabled: bool, comments_enabled: bool, operation_enabled: bool, sales_enabled: bool}
     */
    private function currentSettings(): array
    {
        return [
            'enabled' => TelegramSetting::clientChecksEnabled(),
            'comments_enabled' => TelegramSetting::clientCheckCommentsEnabled(),
            'operation_enabled' => TelegramSetting::clientChecksEnabledFor(OperationUser::ROLE_OPERATION),
            'sales_enabled' => TelegramSetting::clientChecksEnabledFor(OperationUser::ROLE_SALES),
        ];
    }
}
