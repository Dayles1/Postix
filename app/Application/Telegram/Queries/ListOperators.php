<?php

declare(strict_types=1);

namespace App\Application\Telegram\Queries;

use App\Models\Telegram\OperationUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Listing behind the operators management page.
 *
 * Deliberately much lighter than {@see ListOperationUsers}: that query powers
 * the statistics page and aggregates every check, which is wasted work when
 * all this page needs is who the operators are and how to reach them.
 */
final class ListOperators
{
    /**
     * @param array<string, mixed> $filters
     */
    public function execute(
        array $filters,
    ): LengthAwarePaginator {
        $query = $this->buildQuery($filters);

        $this->applySorting($query, $filters);

        return $query
            ->paginate(
                (int) ($filters['per_page'] ?? 20),
            )
            ->withQueryString();
    }

    /**
     * Counters for the page header, over the whole filtered set.
     *
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    public function stats(
        array $filters,
    ): array {
        $base = $this->buildQuery($filters);

        return [
            'total' => (clone $base)->count(),

            'linked' => (clone $base)
                ->where(
                    fn (Builder $q) => $this->whereHasPeer($q),
                )
                ->count(),

            'dm_enabled' => (clone $base)
                ->where('dm_enabled', true)
                ->where(
                    fn (Builder $q) => $this->whereHasPeer($q),
                )
                ->count(),

            'failing' => (clone $base)
                ->whereNotNull('dm_last_error')
                ->count(),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function buildQuery(
        array $filters,
    ): Builder {
        $query = OperationUser::query()
            ->select('operation_users.*')
            ->withCount([
                'drivers',
                'checks',
            ]);

        $search = trim(
            (string) ($filters['search'] ?? ''),
        );

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('name_normalized', 'like', $like)
                    ->orWhere('telegram_username', 'like', $like)
                    ->orWhereRaw(
                        'CAST(telegram_id AS CHAR) LIKE ?',
                        [$like],
                    );
            });
        }

        if (array_key_exists('dm_enabled', $filters)
            && $filters['dm_enabled'] !== null
            && $filters['dm_enabled'] !== ''
        ) {
            $query->where(
                'dm_enabled',
                (bool) $filters['dm_enabled'],
            );
        }

        /*
         * "Linked" means the operator can actually be reached: a username or
         * an id is filled in. This is the filter that answers the question the
         * page exists for - who still needs their Telegram contact entered.
         */
        if (array_key_exists('linked', $filters)
            && $filters['linked'] !== null
            && $filters['linked'] !== ''
        ) {
            $query->where(
                fn (Builder $q) => (bool) $filters['linked']
                    ? $this->whereHasPeer($q)
                    : $this->whereHasNoPeer($q),
            );
        }

        return $query;
    }

    private function whereHasPeer(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q): void {
                $q->whereNotNull('telegram_id')
                    ->orWhere(function (Builder $inner): void {
                        $inner
                            ->whereNotNull('telegram_username')
                            ->where('telegram_username', '!=', '');
                    });
            });
    }

    private function whereHasNoPeer(Builder $query): Builder
    {
        return $query
            ->whereNull('telegram_id')
            ->where(function (Builder $q): void {
                $q->whereNull('telegram_username')
                    ->orWhere('telegram_username', '=', '');
            });
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applySorting(
        Builder $query,
        array $filters,
    ): void {
        $allowed = [
            'name',
            'created_at',
            'updated_at',
            'dm_last_sent_at',
            'drivers',
            'checks',
        ];

        $sort = (string) ($filters['sort'] ?? 'name');

        if (! in_array($sort, $allowed, true)) {
            $sort = 'name';
        }

        $direction = strtolower(
            (string) ($filters['direction'] ?? 'asc'),
        ) === 'desc'
            ? 'desc'
            : 'asc';

        $column = match ($sort) {
            'drivers' => 'drivers_count',
            'checks' => 'checks_count',
            default => $sort,
        };

        $query->orderBy($column, $direction);
    }
}
