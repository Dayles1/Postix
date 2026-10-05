<?php

declare(strict_types=1);

namespace App\Application\Telegram\Queries;

use App\Application\Telegram\Services\ClientCheckRulesStore;
use App\Enums\Telegram\TelegramClientCheckStatus;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Listing behind the CRM penalties page: every penalty the listener saw,
 * who it went to, how hard it leaned on them and whether it arrived.
 */
final class ListClientChecks
{
    public function __construct(
        private readonly ClientCheckRulesStore $rules,
    ) {
    }

    public const SORTS = [
        'created_at',
        'level',
        'repeat_number',
        'request_number',
    ];

    /**
     * @param array<string, mixed> $filters
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $query = $this->buildQuery($filters)
            ->with('operationUser:id,name,role,telegram_username,telegram_id,dm_enabled');

        $sort = in_array($filters['sort'] ?? null, self::SORTS, true)
            ? $filters['sort']
            : 'created_at';

        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $query->orderBy($sort, $direction)->orderBy('id', $direction);

        return $query
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();
    }

    /**
     * Counters over the whole filtered set. The status filter is left out
     * of them, so the status cards keep showing the whole picture.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function stats(array $filters): array
    {
        $base = $this->buildQuery([...$filters, 'status' => null]);

        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $status = static fn (TelegramClientCheckStatus $s): int => (int) ($byStatus[$s->value] ?? 0);

        $byRole = $this->buildQuery([...$filters, 'role' => null])
            ->selectRaw('responsible_role, COUNT(*) as aggregate')
            ->groupBy('responsible_role')
            ->pluck('aggregate', 'responsible_role');

        return [
            'total' => (int) $byStatus->sum(),
            'sent' => $status(TelegramClientCheckStatus::Sent),
            'in_progress' => $status(TelegramClientCheckStatus::Pending)
                + $status(TelegramClientCheckStatus::Forwarded),
            'failed' => $status(TelegramClientCheckStatus::Failed),
            'skipped' => $status(TelegramClientCheckStatus::Skipped),
            /*
             * The top level as configured right now - levels are edited
             * in the panel, so "critical" is not a fixed number.
             */
            'critical' => (clone $base)
                ->where('level', '>=', max(1, $this->rules->current()->topLevel()))
                ->count(),
            'people' => (clone $base)->whereNotNull('operation_user_id')
                ->distinct()
                ->count('operation_user_id'),
            'roles' => [
                OperationUser::ROLE_OPERATION => (int) ($byRole[OperationUser::ROLE_OPERATION] ?? 0),
                OperationUser::ROLE_SALES => (int) ($byRole[OperationUser::ROLE_SALES] ?? 0),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function buildQuery(array $filters): Builder
    {
        $query = TelegramClientCheck::query();

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $like = '%' . ltrim($search, '#') . '%';

            $query->where(function (Builder $q) use ($like): void {
                $q->where('request_number', 'like', $like)
                    ->orWhere('responsible_name', 'like', $like)
                    ->orWhereHas(
                        'operationUser',
                        fn (Builder $person) => $person
                            ->where('name', 'like', $like)
                            ->orWhere('telegram_username', 'like', $like),
                    );
            });
        }

        if (OperationUser::isRole($filters['role'] ?? null)) {
            $query->where('responsible_role', $filters['role']);
        }

        $status = TelegramClientCheckStatus::tryFrom((string) ($filters['status'] ?? ''));

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if (isset($filters['level']) && $filters['level'] !== '' && $filters['level'] !== null) {
            $query->where('level', (int) $filters['level']);
        }

        if (! empty($filters['operation_user_id'])) {
            $query->where('operation_user_id', (int) $filters['operation_user_id']);
        }

        if (($from = $this->day($filters['period_from'] ?? null)) !== null) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if (($to = $this->day($filters['period_to'] ?? null)) !== null) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        return $query;
    }

    private function day(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($value));
        } catch (Throwable) {
            return null;
        }
    }
}
