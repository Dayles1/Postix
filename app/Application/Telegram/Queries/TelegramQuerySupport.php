<?php

declare(strict_types=1);

namespace App\Application\Telegram\Queries;

use App\Application\Telegram\DTO\TelegramListFilters;
use App\Models\Driver\TelegramDriverCheck;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

trait TelegramQuerySupport
{
    private function applyPeriod(Builder $query, TelegramListFilters $filters, string $column = 'created_at'): Builder
    {
        if ($filters->periodFrom) {
            $query->where($column, '>=', $filters->periodFrom);
        }
        if ($filters->periodTo) {
            $query->where($column, '<=', $filters->periodTo);
        }
        return $query;
    }

    private function applyCheckPeriod(Builder|QueryBuilder $query, TelegramListFilters $filters, string $column = 'telegram_driver_checks.created_at'): Builder|QueryBuilder
    {
        if ($filters->periodFrom) {
            $query->where($column, '>=', $filters->periodFrom);
        }
        if ($filters->periodTo) {
            $query->where($column, '<=', $filters->periodTo);
        }
        return $query;
    }

    private function addCheckAggregates(
        Builder $query,
        TelegramListFilters $filters,
        string $foreignColumn,
        string $parentQualifiedKey,
    ): Builder {
        $statuses = [
            'checks_count' => null,
            'confirmed_count' => 'confirmed',
            'not_confirmed_count' => 'not_confirmed',
            'pending_count' => 'pending',
            'processing_count' => 'processing',
        ];

        foreach ($statuses as $alias => $status) {
            $query->selectSub(function (QueryBuilder $sub) use ($filters, $foreignColumn, $parentQualifiedKey, $status) {
                $sub->from('telegram_driver_checks')
                    ->whereColumn($foreignColumn, $parentQualifiedKey);
                if ($status !== null) {
                    $sub->where('status', $status);
                }
                $this->applySubqueryPeriod($sub, $filters);
                $sub->selectRaw('COUNT(*)');
            }, $alias);
        }

        $query->selectSub(function (QueryBuilder $sub) use ($filters, $foreignColumn, $parentQualifiedKey) {
            $sub->from('telegram_driver_checks')
                ->whereColumn($foreignColumn, $parentQualifiedKey)
                ->whereRaw("JSON_EXTRACT(telegram_raw, '$.name_match.score') IS NOT NULL");
            $this->applySubqueryPeriod($sub, $filters);
            $sub->selectRaw("AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(telegram_raw, '$.name_match.score')) AS DECIMAL(10,2)))");
        }, 'avg_match_score');

        $query->selectSub(function (QueryBuilder $sub) use ($filters, $foreignColumn, $parentQualifiedKey) {
            $sub->from('telegram_driver_checks')
                ->whereColumn($foreignColumn, $parentQualifiedKey)
                ->whereRaw("JSON_EXTRACT(telegram_raw, '$.name_match.score') IS NOT NULL");
            $this->applySubqueryPeriod($sub, $filters);
            $sub->selectRaw("MAX(CAST(JSON_UNQUOTE(JSON_EXTRACT(telegram_raw, '$.name_match.score')) AS DECIMAL(10,2)))");
        }, 'best_match_score');

        $query->selectSub(function (QueryBuilder $sub) use ($filters, $foreignColumn, $parentQualifiedKey) {
            $sub->from('telegram_driver_checks')
                ->whereColumn($foreignColumn, $parentQualifiedKey);
            $this->applySubqueryPeriod($sub, $filters);
            $sub->selectRaw('MAX(created_at)');
        }, 'last_check_at');

        return $query;
    }

    private function applySubqueryPeriod(QueryBuilder $query, TelegramListFilters $filters, string $column = 'created_at'): void
    {
        if ($filters->periodFrom) {
            $query->where($column, '>=', $filters->periodFrom);
        }
        if ($filters->periodTo) {
            $query->where($column, '<=', $filters->periodTo);
        }
    }

    private function applyCheckConstraints(
        Builder $query,
        TelegramListFilters $filters,
        string $qualifiedForeignKey,
        string $relationColumn = 'telegram_driver_checks.id',
    ): Builder {
        if ($filters->checkStatus) {
            $query->whereHas('checks', function (Builder $checkQuery) use ($filters) {
                $checkQuery->where('status', $filters->checkStatus);
            });
        }

        if ($filters->minMatchScore !== null || $filters->maxMatchScore !== null) {
            $query->whereHas('checks', function (Builder $checkQuery) use ($filters) {
                if ($filters->minMatchScore !== null) {
                    $checkQuery->whereRaw(
                        "CAST(JSON_UNQUOTE(JSON_EXTRACT(telegram_raw, '$.name_match.score')) AS DECIMAL(10,2)) >= ?",
                        [$filters->minMatchScore],
                    );
                }
                if ($filters->maxMatchScore !== null) {
                    $checkQuery->whereRaw(
                        "CAST(JSON_UNQUOTE(JSON_EXTRACT(telegram_raw, '$.name_match.score')) AS DECIMAL(10,2)) <= ?",
                        [$filters->maxMatchScore],
                    );
                }
            });
        }

        if ($filters->periodFrom || $filters->periodTo) {
            $query->whereHas('checks', function (Builder $checkQuery) use ($filters) {
                if ($filters->periodFrom) {
                    $checkQuery->where('created_at', '>=', $filters->periodFrom);
                }
                if ($filters->periodTo) {
                    $checkQuery->where('created_at', '<=', $filters->periodTo);
                }
            });
        }

        return $query;
    }

    private function addDateSubquery(Builder $query, TelegramListFilters $filters, string $foreignColumn, string $parentQualifiedKey, string $alias = 'last_check_at'): Builder
    {
        return $query->selectSub(function (QueryBuilder $sub) use ($filters, $foreignColumn, $parentQualifiedKey) {
            $sub->from('telegram_driver_checks')
                ->whereColumn($foreignColumn, $parentQualifiedKey);
            $this->applySubqueryPeriod($sub, $filters);
            $sub->selectRaw('MAX(created_at)');
        }, $alias);
    }

    private function statsMatchRateExpression(): string
    {
        return "CASE WHEN checks_count > 0 THEN ROUND((confirmed_count / checks_count) * 100, 2) ELSE 0 END";
    }
}
