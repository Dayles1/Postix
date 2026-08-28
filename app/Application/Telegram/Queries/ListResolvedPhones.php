<?php

declare(strict_types=1);

namespace App\Application\Telegram\Queries;

use App\Application\Telegram\DTO\TelegramListFilters;
use App\Models\Telegram\TelegramResolvedPhone;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListResolvedPhones
{
    use TelegramQuerySupport;

    public function execute(TelegramListFilters $filters): LengthAwarePaginator
    {
        $query = TelegramResolvedPhone::query()
            ->with([
                'driver:id,name,name_normalized,operation_user_id,status',
                'driver.operationUser:id,name,telegram_username,telegram_id',
                'telegramAccount:id,phone',
            ])
            ->select('telegram_resolved_phones.*');

        if ($filters->search) {
            $search = '%' . $filters->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('phone_normalized', 'like', $search)
                    ->orWhere('telegram_username', 'like', $search)
                    ->orWhere('telegram_first_name', 'like', $search)
                    ->orWhere('telegram_last_name', 'like', $search)
                    ->orWhereRaw('CAST(telegram_user_id AS CHAR) LIKE ?', [$search]);
            });
        }

        if ($filters->operationUserId !== null) {
            $query->whereHas('driver', fn ($q) => $q->where('operation_user_id', $filters->operationUserId));
        }

        if ($filters->driverId !== null) {
            $query->where('driver_id', $filters->driverId);
        }

        if ($filters->telegramAccountId !== null) {
            $query->where('telegram_account_id', $filters->telegramAccountId);
        }

        if ($filters->telegramUserId !== null) {
            $query->where('telegram_user_id', $filters->telegramUserId);
        }

        if ($filters->telegramUsername) {
            $query->where('telegram_username', 'like', '%' . $filters->telegramUsername . '%');
        }

        if ($filters->telegramFirstName) {
            $query->where('telegram_first_name', 'like', '%' . $filters->telegramFirstName . '%');
        }

        if ($filters->telegramLastName) {
            $query->where('telegram_last_name', 'like', '%' . $filters->telegramLastName . '%');
        }

        if ($filters->phone) {
            $query->where('phone_normalized', 'like', '%' . $filters->phone . '%');
        }

        if ($filters->hasUsername !== null) {
            $filters->hasUsername
                ? $query->whereNotNull('telegram_username')->where('telegram_username', '<>', '')
                : $query->where(function ($q) {
                    $q->whereNull('telegram_username')->orWhere('telegram_username', '');
                });
        }

        if ($filters->hasDriver !== null) {
            $filters->hasDriver
                ? $query->whereNotNull('driver_id')
                : $query->whereNull('driver_id');
        }

        if ($filters->stale !== null) {
            $days = $filters->staleDays ?? 7;
            $threshold = now()->subDays($days);
            if ($filters->stale) {
                $query->where('resolved_at', '<', $threshold);
            } else {
                $query->where('resolved_at', '>=', $threshold);
            }
        }

        if ($filters->periodFrom) {
            $query->where('resolved_at', '>=', $filters->periodFrom);
        }
        if ($filters->periodTo) {
            $query->where('resolved_at', '<=', $filters->periodTo);
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

        $this->addCheckAggregates(
            query: $query,
            filters: $filters,
            foreignColumn: 'telegram_driver_checks.telegram_resolved_phone_id',
            parentQualifiedKey: 'telegram_resolved_phones.id',
        );

        if ($filters->checksFrom !== null) {
            $query->having('checks_count', '>=', $filters->checksFrom);
        }
        if ($filters->checksTo !== null) {
            $query->having('checks_count', '<=', $filters->checksTo);
        }

        $sortMap = [
            'checks' => 'checks_count',
            'confirmed' => 'confirmed_count',
            'not_confirmed' => 'not_confirmed_count',
        ];
        $sort = $sortMap[$filters->sort] ?? $filters->sort;
        $allowed = ['created_at', 'updated_at', 'resolved_at', 'phone_normalized', 'telegram_user_id', ...array_values($sortMap)];
        if (!in_array($sort, $allowed, true)) {
            $sort = 'resolved_at';
        }
        $query->orderBy($sort, $filters->direction);

        return $query->paginate($filters->perPage)->withQueryString();
    }
}
