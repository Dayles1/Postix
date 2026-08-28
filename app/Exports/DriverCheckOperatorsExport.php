<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class DriverCheckOperatorsExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize
{
    public function __construct(
        private readonly array $filters,
    ) {
    }

    public function collection(): Enumerable
    {
        $from = $this->filters['from'];
        $to = $this->filters['to'];
        $operationUserId = $this->filters['operation_user_id'] ?? null;
        $status = $this->filters['status'] ?? null;
        $search = $this->filters['search'] ?? null;

        $checks = DB::table('telegram_driver_checks as c')
            ->whereBetween('c.created_at', [$from, $to])
            ->whereNotNull('c.operation_user_id')
            ->when($operationUserId, fn ($q) =>
                $q->where('c.operation_user_id', $operationUserId)
            )
            ->when($status, fn ($q) =>
                $q->where('c.status', $status)
            );

        $operatorQuery = DB::table('operation_users as ou')
            ->leftJoinSub(
                $checks
                    ->select(
                        'c.operation_user_id',
                        DB::raw('COUNT(*) as checks_count'),
                        DB::raw("SUM(CASE WHEN c.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count"),
                        DB::raw("SUM(CASE WHEN c.status = 'not_confirmed' THEN 1 ELSE 0 END) as not_confirmed_count"),
                        DB::raw("SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending_count"),
                        DB::raw("SUM(CASE WHEN c.status = 'processing' THEN 1 ELSE 0 END) as processing_count"),
                        DB::raw('AVG(CASE WHEN JSON_VALID(c.telegram_raw) THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(c.telegram_raw, \'$.name_match.score\')) AS DECIMAL(10,2)) END) as average_score'),
                        DB::raw('MAX(CASE WHEN JSON_VALID(c.telegram_raw) THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(c.telegram_raw, \'$.name_match.score\')) AS DECIMAL(10,2)) END) as best_score'),
                        DB::raw('MAX(c.created_at) as last_check_at'),
                    )
                    ->groupBy('c.operation_user_id'),
                'stats',
                'stats.operation_user_id',
                '=',
                'ou.id',
            )
            ->leftJoinSub(
                DB::table('telegram_driver_checks as c2')
                    ->whereBetween('c2.created_at', [$from, $to])
                    ->whereNotNull('c2.operation_user_id')
                    ->select(
                        'c2.operation_user_id',
                        DB::raw('COUNT(DISTINCT c2.driver_id) as drivers_count'),
                    )
                    ->groupBy('c2.operation_user_id'),
                'dstats',
                'dstats.operation_user_id',
                '=',
                'ou.id',
            )
            ->select([
                'ou.id',
                'ou.name',
                'ou.name_normalized',
                'ou.telegram_username',
                'ou.telegram_id',
                DB::raw('COALESCE(dstats.drivers_count, 0) as drivers_count'),
                DB::raw('COALESCE(stats.checks_count, 0) as checks_count'),
                DB::raw('COALESCE(stats.confirmed_count, 0) as confirmed_count'),
                DB::raw('COALESCE(stats.not_confirmed_count, 0) as not_confirmed_count'),
                DB::raw('COALESCE(stats.pending_count, 0) as pending_count'),
                DB::raw('COALESCE(stats.processing_count, 0) as processing_count'),
                'stats.average_score',
                'stats.best_score',
                'stats.last_check_at',
            ])
            ->when($search, function ($q) use ($search) {
                $like = '%' . $search . '%';

                $q->where(function ($sub) use ($like) {
                    $sub->where('ou.name', 'like', $like)
                        ->orWhere('ou.name_normalized', 'like', $like)
                        ->orWhere('ou.telegram_username', 'like', $like)
                        ->orWhere('ou.telegram_id', 'like', $like);
                });
            })
            ->orderByDesc('confirmed_count')
            ->orderByDesc('checks_count')
            ->orderBy('ou.name');

        return $operatorQuery->get();
    }

    public function headings(): array
    {
        return [
            'ID оператора',
            'Оператор',
            'Нормализованное имя',
            'Telegram username',
            'Telegram ID',
            'Водителей за период',
            'Проверок за период',
            'Подтверждено',
            'Не подтверждено',
            'Ожидает',
            'Проверяется',
            'Процент подтверждения',
            'Средняя оценка',
            'Лучшая оценка',
            'Последняя проверка',
        ];
    }

    public function map($row): array
    {
        $checks = (int) $row->checks_count;
        $confirmed = (int) $row->confirmed_count;
        $rate = $checks > 0
            ? round(($confirmed / $checks) * 100, 2)
            : 0;

        return [
            $row->id,
            $row->name,
            $row->name_normalized,
            $row->telegram_username ? '@' . ltrim($row->telegram_username, '@') : null,
            $row->telegram_id,
            (int) $row->drivers_count,
            $checks,
            $confirmed,
            (int) $row->not_confirmed_count,
            (int) $row->pending_count,
            (int) $row->processing_count,
            $rate,
            $row->average_score !== null ? (float) $row->average_score : null,
            $row->best_score !== null ? (float) $row->best_score : null,
            $row->last_check_at,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                ],
            ],
        ];
    }
}
