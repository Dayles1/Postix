<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Queries\ListOperators;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\OperatorIndexRequest;
use App\Http\Requests\Api\Telegram\OperatorStoreRequest;
use App\Http\Requests\Api\Telegram\OperatorUpdateRequest;
use App\Http\Resources\Telegram\OperatorResource;
use App\Models\Telegram\OperationUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CRUD behind the operators management page.
 *
 * Access is enforced by the `role:driverCheck,superadmin` middleware on the
 * route group (see routes/web.php), matching the rest of the driver-check
 * panel.
 */
final class OperatorController extends Controller
{
    public function index(
        OperatorIndexRequest $request,
        ListOperators $query,
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        return OperatorResource::collection(
            $query->execute($filters),
        )->additional([
            'stats' => $query->stats($filters),
        ]);
    }

    public function store(
        OperatorStoreRequest $request,
    ): JsonResponse {
        $operator = OperationUser::query()->create(
            $this->attributes($request->validated()),
        );

        return (new OperatorResource(
            $operator->loadCount(['drivers', 'checks']),
        ))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        OperatorUpdateRequest $request,
        OperationUser $operationUser,
    ): OperatorResource {
        $attributes = $this->attributes(
            $request->validated(),
        );

        /*
         * Changing the contact details invalidates the previous delivery
         * failure: keeping it would keep showing a red row for a problem that
         * has just been fixed.
         */
        if (
            $attributes['telegram_username'] !== $operationUser->telegram_username
            || $attributes['telegram_id'] !== $operationUser->telegram_id
        ) {
            $attributes['dm_last_error'] = null;
        }

        $operationUser->update($attributes);

        return new OperatorResource(
            $operationUser->loadCount(['drivers', 'checks']),
        );
    }

    /**
     * Operators accumulate history, and the driver/check foreign keys are
     * nullOnDelete - deleting one would silently detach every driver and check
     * it ever produced. So a used operator can only be deactivated.
     */
    public function destroy(
        OperationUser $operationUser,
    ): JsonResponse {
        $operationUser->loadCount(['drivers', 'checks']);

        if (
            $operationUser->drivers_count > 0
            || $operationUser->checks_count > 0
        ) {
            return response()->json(
                [
                    'message' => __('telegram.operators.errors.has_history'),
                ],
                422,
            );
        }

        $operationUser->delete();

        return response()->json(
            [
                'message' => __('telegram.operators.deleted'),
            ],
        );
    }

    /**
     * @param  array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'name' => $validated['name'],

            'name_normalized' => $validated['name_normalized'],

            'telegram_username' => $validated['telegram_username'] ?? null,

            'telegram_id' => $validated['telegram_id'] ?? null,

            'is_active' => (bool) ($validated['is_active'] ?? true),

            'dm_enabled' => (bool) ($validated['dm_enabled'] ?? true),
        ];
    }
}
