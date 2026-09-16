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
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        $query = $operationUser
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
            ]);

        /*
         |--------------------------------------------------------------------------
         | Date filter
         |--------------------------------------------------------------------------
         |
         | Supported:
         | - date_filter=last_week
         | - date_filter=last_month
         | - from_date=2026-08-01&to_date=2026-08-31
         |
         */

        $dateFilter = $request->input('date_filter');

        if ($dateFilter === 'last_week') {
            $startDate = now()
                ->subWeek()
                ->startOfWeek();

            $endDate = now()
                ->subWeek()
                ->endOfWeek();

            $query->whereBetween('created_at', [
                $startDate,
                $endDate,
            ]);
        }

        if ($dateFilter === 'last_month') {
            $startDate = now()
                ->subMonthNoOverflow()
                ->startOfMonth();

            $endDate = now()
                ->subMonthNoOverflow()
                ->endOfMonth();

            $query->whereBetween('created_at', [
                $startDate,
                $endDate,
            ]);
        }

        /*
         |--------------------------------------------------------------------------
         | Custom date range
         |--------------------------------------------------------------------------
         */

        if ($request->filled('from_date')) {
            $query->where(
                'created_at',
                '>=',
                Carbon::parse($request->input('from_date'))->startOfDay(),
            );
        }

        if ($request->filled('to_date')) {
            $query->where(
                'created_at',
                '<=',
                Carbon::parse($request->input('to_date'))->endOfDay(),
            );
        }

        $drivers = $query
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return TelegramDriverResource::collection(
            $drivers,
        );
    }
}