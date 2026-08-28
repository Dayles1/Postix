<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class DriverCheckDetailExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithStyles,
    ShouldAutoSize
{
    public function __construct(
        private readonly array $filters,
    ) {
    }

    public function query(): Builder
    {
        $from = $this->filters['from'];
        $to = $this->filters['to'];
        $operationUserId = $this->filters['operation_user_id'] ?? null;
        $status = $this->filters['status'] ?? null;
        $search = $this->filters['search'] ?? null;

        return DB::table('telegram_driver_checks as c')
            ->leftJoin('operation_users as ou', 'ou.id', '=', 'c.operation_user_id')
            ->leftJoin('telegram_drivers as d', 'd.id', '=', 'c.driver_id')
            ->leftJoin('telegram_resolved_phones as rp', 'rp.id', '=', 'c.telegram_resolved_phone_id')
            ->whereBetween('c.created_at', [$from, $to])
            ->when($operationUserId, fn ($q) =>
                $q->where('c.operation_user_id', $operationUserId)
            )
            ->when($status, fn ($q) =>
                $q->where('c.status', $status)
            )
            ->when($search, function ($q) use ($search) {
                $like = '%' . $search . '%';

                $q->where(function ($sub) use ($like) {
                    $sub->where('ou.name', 'like', $like)
                        ->orWhere('d.name', 'like', $like)
                        ->orWhere('c.driver_name', 'like', $like)
                        ->orWhere('c.phone_normalized', 'like', $like)
                        ->orWhere('c.telegram_username', 'like', $like)
                        ->orWhere('c.telegram_first_name', 'like', $like)
                        ->orWhere('c.telegram_last_name', 'like', $like);
                });
            })
            ->select([
                'c.id',
                'c.telegram_message_id',
                'c.phone_normalized',
                'c.driver_name',
                'c.status',
                'c.checked_at',
                'c.created_at',
                'ou.id as operation_user_id',
                'ou.name as operation_user_name',
                'ou.telegram_username as operation_user_telegram_username',
                'd.id as driver_id',
                'd.name as driver_db_name',
                'd.status as driver_status',
                'c.telegram_user_id',
                'c.telegram_username',
                'c.telegram_first_name',
                'c.telegram_last_name',
                'rp.telegram_account_id',
                'c.telegram_raw',
            ])
            ->orderByDesc('c.created_at')
            ->orderByDesc('c.id');
    }

    public function headings(): array
    {
        return [
            'ID проверки',
            'ID сообщения',
            'Дата создания проверки',
            'Дата завершения проверки',
            'Оператор ID',
            'Оператор',
            'Telegram оператора',
            'Водитель ID',
            'Водитель',
            'Статус водителя',
            'Телефон',
            'Telegram User ID',
            'Telegram username',
            'Telegram имя',
            'Telegram фамилия',
            'Статус проверки',
            'Оценка совпадения',
            'Уровень совпадения',
            'Решение',
            'Уверенность',
            'Telegram аккаунт resolver',
        ];
    }

    public function map($row): array
    {
        $raw = is_string($row->telegram_raw)
            ? json_decode($row->telegram_raw, true)
            : $row->telegram_raw;

        if (!is_array($raw)) {
            $raw = [];
        }

        $match = $raw['name_match'] ?? [];
        if (!is_array($match)) {
            $match = [];
        }

        return [
            $row->id,
            $row->telegram_message_id,
            $row->created_at,
            $row->checked_at,
            $row->operation_user_id,
            $row->operation_user_name,
            $row->operation_user_telegram_username
                ? '@' . ltrim($row->operation_user_telegram_username, '@')
                : null,
            $row->driver_id,
            $row->driver_name ?: $row->driver_db_name,
            $row->driver_status,
            $row->phone_normalized,
            $row->telegram_user_id,
            $row->telegram_username
                ? '@' . ltrim($row->telegram_username, '@')
                : null,
            $row->telegram_first_name,
            $row->telegram_last_name,
            $row->status,
            isset($match['score']) && is_numeric($match['score'])
                ? (float) $match['score']
                : null,
            $match['level'] ?? null,
            $match['decision'] ?? null,
            $match['confidence'] ?? null,
            $row->telegram_account_id,
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
