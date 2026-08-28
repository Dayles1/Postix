<?php

declare(strict_types=1);

namespace App\Application\Telegram\DTO;

use Carbon\CarbonImmutable;

final readonly class TelegramListFilters
{
    public function __construct(
        public ?string $search = null,
        public ?int $operationUserId = null,
        public ?int $driverId = null,
        public ?int $telegramAccountId = null,
        public ?int $telegramUserId = null,
        public ?string $phone = null,
        public ?string $telegramUsername = null,
        public ?string $telegramFirstName = null,
        public ?string $telegramLastName = null,
        public ?string $status = null,
        public ?string $checkStatus = null,
        public ?CarbonImmutable $periodFrom = null,
        public ?CarbonImmutable $periodTo = null,
        public ?float $minMatchScore = null,
        public ?float $maxMatchScore = null,
        public ?int $checksFrom = null,
        public ?int $checksTo = null,
        public ?bool $hasTelegram = null,
        public ?bool $hasUsername = null,
        public ?bool $hasDriver = null,
        public ?bool $stale = null,
        public ?int $staleDays = null,
        public string $sort = 'created_at',
        public string $direction = 'desc',
        public int $perPage = 25,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            search: self::string($data['search'] ?? null),
            operationUserId: self::int($data['operation_user_id'] ?? null),
            driverId: self::int($data['driver_id'] ?? null),
            telegramAccountId: self::int($data['telegram_account_id'] ?? null),
            telegramUserId: self::int($data['telegram_user_id'] ?? null),
            phone: self::string($data['phone'] ?? null),
            telegramUsername: self::string($data['telegram_username'] ?? null),
            telegramFirstName: self::string($data['telegram_first_name'] ?? null),
            telegramLastName: self::string($data['telegram_last_name'] ?? null),
            status: self::string($data['status'] ?? null),
            checkStatus: self::string($data['check_status'] ?? null),
            periodFrom: self::date($data['period_from'] ?? null),
            periodTo: self::date($data['period_to'] ?? null, endOfDay: true),
            minMatchScore: self::float($data['min_match_score'] ?? null),
            maxMatchScore: self::float($data['max_match_score'] ?? null),
            checksFrom: self::int($data['checks_from'] ?? null),
            checksTo: self::int($data['checks_to'] ?? null),
            hasTelegram: self::bool($data['has_telegram'] ?? null),
            hasUsername: self::bool($data['has_username'] ?? null),
            hasDriver: self::bool($data['has_driver'] ?? null),
            stale: self::bool($data['stale'] ?? null),
            staleDays: self::int($data['stale_days'] ?? null),
            sort: (string) ($data['sort'] ?? 'created_at'),
            direction: strtolower((string) ($data['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
            perPage: min(100, max(1, (int) ($data['per_page'] ?? 25))),
        );
    }

    private static function string(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        return trim($value);
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function bool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if ($value === '1' || $value === 1 || $value === 'true') {
            return true;
        }
        if ($value === '0' || $value === 0 || $value === 'false') {
            return false;
        }
        return null;
    }

    private static function date(mixed $value, bool $endOfDay = false): ?CarbonImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value);
            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
