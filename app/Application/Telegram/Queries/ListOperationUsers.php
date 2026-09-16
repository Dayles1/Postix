<?php

declare(strict_types=1);

namespace App\Application\Telegram\Queries;

use App\Application\Telegram\DTO\TelegramListFilters;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Models\Telegram\OperationUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListOperationUsers
{
    use TelegramQuerySupport;

    /**
     * =============================================================
     * LIST
     * =============================================================
     */
    public function execute(
        TelegramListFilters $filters,
    ): LengthAwarePaginator {
        $query = $this->buildQuery($filters);

        $this->applySorting(
            query: $query,
            filters: $filters,
        );

        return $query
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    /**
     * =============================================================
     * GLOBAL STATISTICS
     * =============================================================
     *
     * Muhim:
     *
     * Bu statistic current page bo'yicha emas.
     *
     * Masalan:
     *
     * total users = 250
     * per_page = 10
     *
     * statistic 250 ta user ichidagi barcha driver/checklarni
     * hisoblaydi.
     *
     * Filter ishlatilsa, faqat filterga mos kelgan barcha userlar
     * hisoblanadi.
     */
    public function stats(
        TelegramListFilters $filters,
    ): array {
        $rows = $this->buildQuery($filters)
            ->get();

        $drivers = (int) $rows->sum(
            fn (OperationUser $user): int => (int) (
                $user->drivers_count ?? 0
            ),
        );

        $checks = (int) $rows->sum(
            fn (OperationUser $user): int => (int) (
                $user->checks_count ?? 0
            ),
        );

        $confirmed = (int) $rows->sum(
            fn (OperationUser $user): int => (int) (
                $user->confirmed_count ?? 0
            ),
        );

        $notConfirmed = (int) $rows->sum(
            fn (OperationUser $user): int => (int) (
                $user->not_confirmed_count ?? 0
            ),
        );

        $pending = (int) $rows->sum(
            fn (OperationUser $user): int => (int) (
                $user->pending_count ?? 0
            ),
        );

        $processing = (int) $rows->sum(
            fn (OperationUser $user): int => (int) (
                $user->processing_count ?? 0
            ),
        );

        return [
            'drivers' => $drivers,

            'checks' => $checks,

            'confirmed' => $confirmed,

            'not_confirmed' => $notConfirmed,

            'pending' => $pending,

            'processing' => $processing,

            'match_rate' => $checks > 0
                ? round(
                    ($confirmed / $checks) * 100,
                    2,
                )
                : 0.0,

            // 'avg_match_score' => $this->averageNullable(
            //     $rows->pluck('avg_match_score')->all(),
            // ),

            'best_match_score' => $this->bestNullable(
                $rows->pluck('best_match_score')->all(),
            ),
        ];
    }

    /**
     * =============================================================
     * BUILD QUERY
     * =============================================================
     */
    private function buildQuery(
        TelegramListFilters $filters,
    ): Builder {
        $query = OperationUser::query()
            ->select('operation_users.*')

            /*
             * =====================================================
             * DRIVERS
             * =====================================================
             */

            ->withCount('drivers')

            ->withCount([
                'drivers as driver_confirmed_count' => function (Builder $q): void {
                    $q->where(
                        'status',
                        TelegramDriverCheckStatus::Confirmed->value,
                    );
                },

                'drivers as driver_not_confirmed_count' => function (Builder $q): void {
                    $q->where(
                        'status',
                        TelegramDriverCheckStatus::NotConfirmed->value,
                    );
                },

                'drivers as driver_pending_count' => function (Builder $q): void {
                    $q->where(
                        'status',
                        TelegramDriverCheckStatus::Pending->value,
                    );
                },

                'drivers as driver_processing_count' => function (Builder $q): void {
                    $q->where(
                        'status',
                        TelegramDriverCheckStatus::Processing->value,
                    );
                },
            ])

            /*
             * =====================================================
             * CHECKS
             * =====================================================
             */

            ->withCount('checks');

        /*
         * =========================================================
         * SEARCH
         * =========================================================
         */
        if ($filters->search) {
            $search = '%' . $filters->search . '%';

            $query->where(function (Builder $q) use ($search): void {
                $q->where(
                    'name',
                    'like',
                    $search,
                )
                    ->orWhere(
                        'name_normalized',
                        'like',
                        $search,
                    )
                    ->orWhere(
                        'telegram_username',
                        'like',
                        $search,
                    )
                    ->orWhereRaw(
                        'CAST(telegram_id AS CHAR) LIKE ?',
                        [$search],
                    );
            });
        }

        /*
         * =========================================================
         * OPERATION USER ID
         * =========================================================
         */
        if ($filters->operationUserId !== null) {
            $query->whereKey(
                $filters->operationUserId,
            );
        }

        /*
         * =========================================================
         * CHECK STATUS FILTER
         * =========================================================
         *
         * Bu filter telegram_driver_checks.status ga tegishli.
         */
        if ($filters->status) {
            $query->whereHas(
                'checks',
                fn (Builder $q) => $q->where(
                    'status',
                    $filters->status,
                ),
            );
        }

        if ($filters->checkStatus) {
            $query->whereHas(
                'checks',
                fn (Builder $q) => $q->where(
                    'status',
                    $filters->checkStatus,
                ),
            );
        }

        /*
         * =========================================================
         * MATCH SCORE FILTER
         * =========================================================
         */
        if (
            $filters->minMatchScore !== null
            || $filters->maxMatchScore !== null
        ) {
            $query->whereHas(
                'checks',
                function (Builder $q) use ($filters): void {

                    if ($filters->minMatchScore !== null) {
                        $q->whereRaw(
                            "CAST(
                                JSON_UNQUOTE(
                                    JSON_EXTRACT(
                                        telegram_raw,
                                        '$.name_match.score'
                                    )
                                ) AS DECIMAL(10,2)
                            ) >= ?",
                            [$filters->minMatchScore],
                        );
                    }

                    if ($filters->maxMatchScore !== null) {
                        $q->whereRaw(
                            "CAST(
                                JSON_UNQUOTE(
                                    JSON_EXTRACT(
                                        telegram_raw,
                                        '$.name_match.score'
                                    )
                                ) AS DECIMAL(10,2)
                            ) <= ?",
                            [$filters->maxMatchScore],
                        );
                    }
                },
            );
        }

        /*
         * =========================================================
         * PERIOD
         * =========================================================
         */
        if (
            $filters->periodFrom
            || $filters->periodTo
        ) {
            $query->whereHas(
                'checks',
                function (Builder $q) use ($filters): void {

                    if ($filters->periodFrom) {
                        $q->where(
                            'created_at',
                            '>=',
                            $filters->periodFrom,
                        );
                    }

                    if ($filters->periodTo) {
                        $q->where(
                            'created_at',
                            '<=',
                            $filters->periodTo,
                        );
                    }
                },
            );
        }

        /*
         * =========================================================
         * TELEGRAM ACCOUNT
         * =========================================================
         */
        if ($filters->telegramAccountId !== null) {
            $query->whereExists(
                function ($sub) use ($filters): void {

                    $sub->selectRaw('1')
                        ->from(
                            'telegram_driver_checks as c',
                        )
                        ->join(
                            'telegram_resolved_phones as rp',
                            'rp.id',
                            '=',
                            'c.telegram_resolved_phone_id',
                        )
                        ->whereColumn(
                            'c.operation_user_id',
                            'operation_users.id',
                        )
                        ->where(
                            'rp.telegram_account_id',
                            $filters->telegramAccountId,
                        );
                },
            );
        }

        /*
         * =========================================================
         * CHECK AGGREGATES
         * =========================================================
         */
        $this->addCheckAggregates(
            query: $query,
            filters: $filters,
            foreignColumn:
                'telegram_driver_checks.operation_user_id',
            parentQualifiedKey:
                'operation_users.id',
        );

        /*
         * =========================================================
         * CHECK COUNT FILTER
         * =========================================================
         */
        if ($filters->checksFrom !== null) {
            $query->having(
                'checks_count',
                '>=',
                $filters->checksFrom,
            );
        }

        if ($filters->checksTo !== null) {
            $query->having(
                'checks_count',
                '<=',
                $filters->checksTo,
            );
        }

        return $query;
    }

    /**
     * =============================================================
     * SORT
     * =============================================================
     */
    private function applySorting(
        Builder $query,
        TelegramListFilters $filters,
    ): void {
        if ($filters->sort === 'drivers') {
            $query->orderBy(
                'drivers_count',
                $filters->direction,
            );

            return;
        }

        if ($filters->sort === 'confirmed') {
            $query->orderBy(
                'driver_confirmed_count',
                $filters->direction,
            );

            return;
        }

        if ($filters->sort === 'not_confirmed') {
            $query->orderBy(
                'driver_not_confirmed_count',
                $filters->direction,
            );

            return;
        }

        if ($filters->sort === 'pending') {
            $query->orderBy(
                'driver_pending_count',
                $filters->direction,
            );

            return;
        }

        if ($filters->sort === 'processing') {
            $query->orderBy(
                'driver_processing_count',
                $filters->direction,
            );

            return;
        }

        if ($filters->sort === 'match_rate') {
            $direction = $filters->direction === 'asc'
                ? 'asc'
                : 'desc';

            $query->orderByRaw(
                "CASE
                    WHEN checks_count > 0
                    THEN (
                        confirmed_count / checks_count
                    )
                    ELSE 0
                END {$direction}",
            );

            return;
        }

        $allowed = [
            'created_at',
            'updated_at',
            'name',
            'checks',
            'confirmed',
            'not_confirmed',
            'pending',
            'processing',
            // 'avg_match_score',
            'last_check_at',
        ];

        $sort = in_array(
            $filters->sort,
            $allowed,
            true,
        )
            ? $filters->sort
            : 'created_at';

        $map = [
            'checks' =>
                'checks_count',

            'confirmed' =>
                'driver_confirmed_count',

            'not_confirmed' =>
                'driver_not_confirmed_count',

            'pending' =>
                'driver_pending_count',

            'processing' =>
                'driver_processing_count',

            // 'avg_match_score' =>
            //     'avg_match_score',

            'last_check_at' =>
                'last_check_at',
        ];

        $query->orderBy(
            $map[$sort] ?? $sort,
            $filters->direction,
        );
    }

    /**
     * =============================================================
     * AVERAGE NULLABLE
     * =============================================================
     */
    private function averageNullable(
        array $values,
    ): ?float {
        $values = array_values(
            array_filter(
                $values,
                static fn ($value): bool =>
                    $value !== null,
            ),
        );

        if ($values === []) {
            return null;
        }

        return round(
            array_sum(
                array_map(
                    'floatval',
                    $values,
                ),
            ) / count($values),
            2,
        );
    }

    /**
     * =============================================================
     * MAX NULLABLE
     * =============================================================
     */
    private function bestNullable(
        array $values,
    ): ?float {
        $values = array_values(
            array_filter(
                $values,
                static fn ($value): bool =>
                    $value !== null,
            ),
        );

        if ($values === []) {
            return null;
        }

        return round(
            max(
                array_map(
                    'floatval',
                    $values,
                ),
            ),
            2,
        );
    }

    /**
     * =============================================================
     * FIND
     * =============================================================
     */
    public function find(
        OperationUser $operationUser,
    ): OperationUser {
        $query = OperationUser::query()
            ->select('operation_users.*')

            ->whereKey(
                $operationUser->id,
            )

            /*
             * =====================================================
             * DRIVER COUNTS
             * =====================================================
             */

            ->withCount('drivers')

            ->withCount([
                'drivers as driver_confirmed_count' => function (Builder $q): void {
                    $q->where(
                        'status',
                        TelegramDriverCheckStatus::Confirmed->value,
                    );
                },

                'drivers as driver_not_confirmed_count' => function (Builder $q): void {
                    $q->where(
                        'status',
                        TelegramDriverCheckStatus::NotConfirmed->value,
                    );
                },

                'drivers as driver_pending_count' => function (Builder $q): void {
                    $q->where(
                        'status',
                        TelegramDriverCheckStatus::Pending->value,
                    );
                },

                'drivers as driver_processing_count' => function (Builder $q): void {
                    $q->where(
                        'status',
                        TelegramDriverCheckStatus::Processing->value,
                    );
                },
            ])

            ->withCount('checks');

        /*
         * =========================================================
         * CHECK AGGREGATES
         * =========================================================
         */
        $this->addCheckAggregates(
            query: $query,
            filters: TelegramListFilters::fromArray([]),
            foreignColumn:
                'telegram_driver_checks.operation_user_id',
            parentQualifiedKey:
                'operation_users.id',
        );

        return $query->firstOrFail();
    }
}