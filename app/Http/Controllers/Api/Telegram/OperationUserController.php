<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\DTO\TelegramListFilters;
use App\Application\Telegram\Queries\ListOperationUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\OperationUserIndexRequest;
use App\Http\Resources\Telegram\OperationUserResource;
use App\Http\Resources\Telegram\TelegramDriverResource;
use App\Models\Telegram\OperationUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class OperationUserController extends Controller
{
    public function index(
        OperationUserIndexRequest $request,
        ListOperationUsers $query,
    ): AnonymousResourceCollection {
        return OperationUserResource::collection(
            $query->execute(
                TelegramListFilters::fromArray(
                    $request->validated(),
                ),
            ),
        );
    }

    public function show(
        OperationUser $operationUser,
    ): OperationUserResource {
        $operationUser->loadCount([
            'drivers',
            'checks',
        ]);

        return new OperationUserResource(
            $operationUser,
        );
    }

    public function drivers(
        Request $request,
        OperationUser $operationUser,
    ): AnonymousResourceCollection {
        $perPage = min(
            100,
            max(
                1,
                (int) $request->input('per_page', 10),
            ),
        );

        $drivers = $operationUser
            ->drivers()
            ->with([
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
            ])
            ->withCount([
                'resolvedPhones',
                'checks',
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return TelegramDriverResource::collection(
            $drivers,
        );
    }

}