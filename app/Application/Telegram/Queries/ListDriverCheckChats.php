<?php

declare(strict_types=1);

namespace App\Application\Telegram\Queries;

use App\Models\Telegram\TelegramDriverCheckChat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Listing behind the watched-chats management page.
 */
final class ListDriverCheckChats
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

            'active' => (clone $base)
                ->where('is_active', true)
                ->count(),

            /*
             * Watched for real: active, and with a peer the listener can
             * actually compare an incoming message against.
             */
            'watching' => (clone $base)
                ->where('is_active', true)
                ->whereNotNull('chat_id')
                ->count(),

            'failing' => (clone $base)
                ->whereNotNull('resolve_error')
                ->count(),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function buildQuery(
        array $filters,
    ): Builder {
        $query = TelegramDriverCheckChat::query()
            ->select('telegram_driver_check_chats.*')
            ->withCount('checks');

        $search = trim(
            (string) ($filters['search'] ?? ''),
        );

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function (Builder $q) use ($like): void {
                $q->where('link', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhereRaw(
                        'CAST(chat_id AS CHAR) LIKE ?',
                        [$like],
                    );
            });
        }

        if (array_key_exists('is_active', $filters)
            && $filters['is_active'] !== null
            && $filters['is_active'] !== ''
        ) {
            $query->where(
                'is_active',
                (bool) $filters['is_active'],
            );
        }

        /*
         * "Resolved" is the question this page exists for: a chat that has
         * never been turned into a numeric peer is configured but deaf.
         */
        if (array_key_exists('resolved', $filters)
            && $filters['resolved'] !== null
            && $filters['resolved'] !== ''
        ) {
            (bool) $filters['resolved']
                ? $query->whereNotNull('chat_id')
                : $query->whereNull('chat_id');
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applySorting(
        Builder $query,
        array $filters,
    ): void {
        $allowed = [
            'created_at',
            'updated_at',
            'title',
            'last_message_at',
            'checks',
        ];

        $sort = (string) ($filters['sort'] ?? 'created_at');

        if (! in_array($sort, $allowed, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower(
            (string) ($filters['direction'] ?? 'desc'),
        ) === 'asc'
            ? 'asc'
            : 'desc';

        $column = $sort === 'checks'
            ? 'checks_count'
            : $sort;

        $query->orderBy($column, $direction);
    }
}
