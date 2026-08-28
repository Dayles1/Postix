<?php

declare(strict_types=1);

namespace App\Application\Telegram\Queries;

use App\Application\Telegram\DTO\TelegramListFilters;
use App\Models\Driver\TelegramDriver;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListTelegramDrivers
{
    use TelegramQuerySupport;

    public function execute(TelegramListFilters $filters): LengthAwarePaginator
    {
        $query = TelegramDriver::query()
            ->with([
                'operationUser:id,name,telegram_username,telegram_id',
            ])
            ->select('telegram_drivers.*');

        if ($filters->search) {
            $search = '%' . $filters->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('name_normalized', 'like', $search);
            });
        }

        if ($filters->operationUserId !== null) {
            $query->where('operation_user_id', $filters->operationUserId);
        }

        if ($filters->driverId !== null) {
            $query->whereKey($filters->driverId);
        }

        if ($filters->status) {
            $query->where('status', $filters->status);
        }

        if ($filters->phone) {
            $query->whereHas('resolvedPhones', fn ($q) => $q->where('phone_normalized', 'like', '%' . $filters->phone . '%'));
        }

        if ($filters->telegramUserId !== null) {
            $query->whereHas('resolvedPhones', fn ($q) => $q->where('telegram_user_id', $filters->telegramUserId));
        }

        if ($filters->telegramUsername) {
            $query->whereHas('resolvedPhones', fn ($q) => $q->where('telegram_username', 'like', '%' . $filters->telegramUsername . '%'));
        }

        if ($filters->checkStatus) {
            $query->whereHas('checks', fn ($q) => $q->where('status', $filters->checkStatus));
        }

        $this->applyCheckConstraints(
            query: $query,
            filters: $filters,
            qualifiedForeignKey: 'telegram_driver_checks.driver_id',
        );

        $this->addCheckAggregates(
            query: $query,
            filters: $filters,
            foreignColumn: 'telegram_driver_checks.driver_id',
            parentQualifiedKey: 'telegram_drivers.id',
        );

        if ($filters->hasTelegram !== null) {
            if ($filters->hasTelegram) {
                $query->whereHas('resolvedPhones');
            } else {
                $query->whereDoesntHave('resolvedPhones');
            }
        }

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
            'pending' => 'pending_count',
            'avg_match_score' => 'avg_match_score',
            'best_match_score' => 'best_match_score',
            'last_check_at' => 'last_check_at',
        ];

        $sort = $sortMap[$filters->sort] ?? $filters->sort;
        $allowed = ['created_at', 'updated_at', 'name', 'status', ...array_values($sortMap)];
        if (!in_array($sort, $allowed, true)) {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $filters->direction);

        return $query->paginate($filters->perPage)->withQueryString();
    }
}
