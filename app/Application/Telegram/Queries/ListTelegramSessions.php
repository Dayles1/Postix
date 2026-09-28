<?php

declare(strict_types=1);

namespace App\Application\Telegram\Queries;

use App\Models\Telegram\TelegramAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Listing behind the Telegram sessions page.
 */
final class ListTelegramSessions
{
    public const STATE_AUTHORIZED = 'authorized';
    public const STATE_PENDING = 'pending';
    public const STATE_PROBLEM = 'problem';
    public const STATE_LOGGED_OUT = 'logged_out';

    public const STATES = [
        self::STATE_AUTHORIZED,
        self::STATE_PENDING,
        self::STATE_PROBLEM,
        self::STATE_LOGGED_OUT,
    ];

    public const SORTS = [
        'created_at',
        'phone',
        'authorized_at',
        'last_checked_at',
    ];

    /**
     * Statuses of a login that has not reached an answer yet.
     */
    private const PENDING_STATUSES = [
        TelegramAccount::STATUS_CREATED,
        TelegramAccount::STATUS_PROCESSING,
        TelegramAccount::STATUS_CODE_SENT,
        TelegramAccount::STATUS_VERIFYING,
        TelegramAccount::STATUS_NEED_PASSWORD,
        TelegramAccount::STATUS_PASSWORD_INVALID,
    ];

    private const FAILED_STATUSES = [
        TelegramAccount::STATUS_FAILED,
        TelegramAccount::STATUS_CODE_INVALID,
        TelegramAccount::STATUS_REVOKED,
    ];

    /**
     * @param array<string, mixed> $filters
     */
    public function execute(
        array $filters,
    ): LengthAwarePaginator {
        $query = $this->buildQuery($filters)
            ->with([
                'processes' => fn ($q) => $q->orderBy('process'),
            ]);

        $this->applySorting($query, $filters);

        return $query
            ->paginate(
                (int) ($filters['per_page'] ?? 20),
            )
            ->withQueryString();
    }

    /**
     * Counters for the page header, over the searched set. The state
     * filter is left out, so picking one does not zero the other tiles.
     *
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    public function stats(
        array $filters,
    ): array {
        $base = $this->buildQuery(
            array_diff_key($filters, ['state' => true]),
        );

        $stats = [];

        foreach (self::STATES as $state) {
            $query = clone $base;

            $this->applyState($query, $state);

            $stats[$state] = $query->count();
        }

        return [
            'total' => (clone $base)->count(),
            ...$stats,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function buildQuery(
        array $filters,
    ): Builder {
        $query = TelegramAccount::query();

        $search = trim(
            (string) ($filters['search'] ?? ''),
        );

        if ($search !== '') {
            $like = '%' . ltrim($search, '@') . '%';

            $digits = preg_replace('/\D+/', '', $search);

            $query->where(function (Builder $q) use ($like, $digits): void {
                $q->where('phone', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like);

                /*
                 * "+998 90 123" is how a phone gets typed, and how it is
                 * never stored.
                 */
                if ($digits !== '') {
                    $q->orWhere('phone', 'like', '%' . $digits . '%')
                        ->orWhereRaw(
                            'CAST(telegram_user_id AS CHAR) LIKE ?',
                            ['%' . $digits . '%'],
                        );
                }
            });
        }

        if (in_array($filters['state'] ?? null, self::STATES, true)) {
            $this->applyState($query, $filters['state']);
        }

        return $query;
    }

    private function applyState(
        Builder $query,
        string $state,
    ): void {
        match ($state) {
            /*
             * Authorized, and not a command in the middle of logging it out.
             */
            self::STATE_AUTHORIZED => $query
                ->where('is_authorized', true)
                ->where(function (Builder $q): void {
                    $q->whereNull('status')
                        ->orWhere('status', '!=', TelegramAccount::STATUS_LOGGING_OUT);
                }),

            self::STATE_PENDING => $query
                ->where('is_authorized', false)
                ->whereIn('status', self::PENDING_STATUSES),

            /*
             * Broken login or dead session - or a working session some
             * process has switched off.
             */
            self::STATE_PROBLEM => $query->where(function (Builder $q): void {
                $q->where(function (Builder $q): void {
                    $q->where('is_authorized', false)
                        ->whereIn('status', self::FAILED_STATUSES);
                })->orWhere(function (Builder $q): void {
                    $q->where('is_authorized', true)
                        ->where(function (Builder $q): void {
                            $q->whereNotNull('last_error')
                                ->orWhereHas(
                                    'processes',
                                    fn (Builder $p) => $p->where('is_available', false),
                                );
                        });
                });
            }),

            self::STATE_LOGGED_OUT => $query
                ->whereIn('status', [
                    TelegramAccount::STATUS_LOGGED_OUT,
                    TelegramAccount::STATUS_LOGGING_OUT,
                ]),
        };
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applySorting(
        Builder $query,
        array $filters,
    ): void {
        $sort = (string) ($filters['sort'] ?? 'created_at');

        if (! in_array($sort, self::SORTS, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower(
            (string) ($filters['direction'] ?? 'desc'),
        ) === 'asc'
            ? 'asc'
            : 'desc';

        $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }
}
