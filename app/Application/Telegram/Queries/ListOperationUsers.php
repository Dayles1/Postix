<?php

declare(strict_types=1);

namespace App\Application\Telegram\Queries;

use App\Application\Telegram\DTO\TelegramListFilters;
use App\Models\Telegram\OperationUser;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListOperationUsers
{
    use TelegramQuerySupport;

    public function execute(TelegramListFilters $filters): LengthAwarePaginator
    {
        $query = OperationUser::query()
            ->select('operation_users.*');

        if ($filters->search) {
            $search = '%' . $filters->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('name_normalized', 'like', $search)
                    ->orWhere('telegram_username', 'like', $search)
                    ->orWhereRaw('CAST(telegram_id AS CHAR) LIKE ?', [$search]);
            });
        }

        if ($filters->operationUserId !== null) {
            $query->whereKey($filters->operationUserId);
        }

        if ($filters->status) {
            $query->whereHas('checks', fn ($q) => $q->where('status', $filters->status));
        }

        if ($filters->checkStatus) {
            $query->whereHas('checks', fn ($q) => $q->where('status', $filters->checkStatus));
        }

        if ($filters->minMatchScore !== null || $filters->maxMatchScore !== null) {
            $query->whereHas('checks', function ($q) use ($filters) {
                if ($filters->minMatchScore !== null) {
                    $q->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(telegram_raw, '$.name_match.score')) AS DECIMAL(10,2)) >= ?", [$filters->minMatchScore]);
                }
                if ($filters->maxMatchScore !== null) {
                    $q->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(telegram_raw, '$.name_match.score')) AS DECIMAL(10,2)) <= ?", [$filters->maxMatchScore]);
                }
            });
        }

        if ($filters->periodFrom || $filters->periodTo) {
            $query->whereHas('checks', function ($q) use ($filters) {
                if ($filters->periodFrom) $q->where('created_at', '>=', $filters->periodFrom);
                if ($filters->periodTo) $q->where('created_at', '<=', $filters->periodTo);
            });
        }

        if ($filters->telegramAccountId !== null) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')
                    ->from('telegram_driver_checks as c')
                    ->join('telegram_resolved_phones as rp', 'rp.id', '=', 'c.telegram_resolved_phone_id')
                    ->whereColumn('c.operation_user_id', 'operation_users.id')
                    ->where('rp.telegram_account_id', $filters->telegramAccountId);
            });
        }

        $this->addCheckAggregates(
            query: $query,
            filters: $filters,
            foreignColumn: 'telegram_driver_checks.operation_user_id',
            parentQualifiedKey: 'operation_users.id',
        );

        if ($filters->checksFrom !== null) {
            $query->having('checks_count', '>=', $filters->checksFrom);
        }
        if ($filters->checksTo !== null) {
            $query->having('checks_count', '<=', $filters->checksTo);
        }

        if ($filters->sort === 'match_rate') {
            $query->orderByRaw("CASE WHEN checks_count > 0 THEN (confirmed_count / checks_count) ELSE 0 END {$filters->direction}");
        } elseif ($filters->sort === 'drivers') {
            $query->withCount('drivers')->orderBy('drivers_count', $filters->direction);
        } else {
            $allowed = ['created_at', 'updated_at', 'name', 'checks', 'confirmed', 'not_confirmed', 'pending', 'avg_match_score', 'last_check_at'];
            $sort = in_array($filters->sort, $allowed, true) ? $filters->sort : 'created_at';
            $map = [
                'checks' => 'checks_count',
                'confirmed' => 'confirmed_count',
                'not_confirmed' => 'not_confirmed_count',
                'pending' => 'pending_count',
                'avg_match_score' => 'avg_match_score',
                'last_check_at' => 'last_check_at',
            ];
            $query->orderBy($map[$sort] ?? $sort, $filters->direction);
        }

        return $query->paginate($filters->perPage)->withQueryString();
    }
}
