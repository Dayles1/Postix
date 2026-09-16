<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\DTO\TelegramListFilters;
use App\Application\Telegram\Queries\ListOperationUsers;
use App\Application\Telegram\Queries\ListTelegramDrivers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\OperationUserIndexRequest;
use App\Http\Requests\Api\Telegram\TelegramDriverIndexRequest;
use App\Http\Resources\Telegram\OperationUserResource;
use App\Http\Resources\Telegram\TelegramDriverResource;
use App\Models\Telegram\OperationUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OperationUserController extends Controller
{
    public function index(
        OperationUserIndexRequest $request,
        ListOperationUsers $query,
    ): AnonymousResourceCollection {
        $filters = TelegramListFilters::fromArray(
            $request->validated(),
        );

        $paginator = $query->execute(
            $filters,
        );

        return OperationUserResource::collection(
            $paginator,
        )->additional([
            'stats' => $query->stats(
                $filters,
            ),
        ]);
    }

    public function show(
        OperationUser $operationUser,
        ListOperationUsers $query,
    ): OperationUserResource {
        return new OperationUserResource(
            $query->find($operationUser),
        );
    }

    /**
     * Drivers belonging to a single operator.
     *
     * Reuses the exact same filter set/query builder as the standalone
     * "Drivers" list page (see {@see TelegramDriverController}), simply
     * scoped to this operator -- so date range, status and match-score
     * filtering behave identically everywhere in the panel instead of
     * duplicating a second, ad hoc date-filter implementation here.
     */
    public function drivers(
        TelegramDriverIndexRequest $request,
        OperationUser $operationUser,
        ListTelegramDrivers $query,
    ): AnonymousResourceCollection {
        $filters = TelegramListFilters::fromArray([
            ...$request->validated(),
            'operation_user_id' => $operationUser->id,
        ]);

        return TelegramDriverResource::collection(
            $query->execute($filters)
                ->loadMissing([
                    'resolvedPhones' => function ($query) {
                        $query
                            ->select([
                                'id',
                                'driver_id',
                                'phone_normalized',
                                'telegram_user_id',
                                'telegram_username',
                                'telegram_first_name',
                                'telegram_last_name',
                                'telegram_account_id',
                                'resolved_at',
                            ])
                            ->latest('resolved_at');
                    },
                ]),
        );
    }
}
