<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

/**
 * How the penalty flow leans on people: the levels, what lifts someone to
 * each one, the phrases each one says, and the timings around them.
 *
 * Written in the panel (stored by ClientCheckRulesStore); config/client_checks.php
 * is only the starting point until somebody saves them there.
 *
 * Level 0 is where everyone starts and has no conditions. Every higher level
 * is reached when ANY of its conditions holds, checked from the top down:
 *  - repeat_from:   the bot's repeat number ("штрафа №N") is at least this;
 *  - hour / today / week: that many penalties in the last 60 minutes, since
 *                   midnight, or within history_days;
 *  - repeat_within: the previous batch was at most this many minutes ago.
 * An empty condition is simply not checked.
 */
final readonly class ClientCheckRules
{
    public const MAX_LEVELS = 10;

    public const CONDITIONS = ['repeat_from', 'hour', 'today', 'week', 'repeat_within'];

    /**
     * What a phrase may contain; filled in by ClientCheckEscalation.
     */
    public const PLACEHOLDERS = [
        'name',
        'request',
        'repeat_number',
        'batch_count',
        'hour_count',
        'today_count',
        'week_count',
    ];

    /**
     * @param list<array{
     *     name: string|null,
     *     repeat_from: int|null,
     *     hour: int|null,
     *     today: int|null,
     *     week: int|null,
     *     repeat_within: int|null,
     *     phrases: list<string>,
     * }> $levels
     */
    public function __construct(
        public array $levels,
        public int $batchQuietSeconds,
        public int $historyDays,
        public int $maxAttempts,
        public int $retryMinutes,
        public string $batchLine,
    ) {
    }

    /**
     * From what the panel saves (toArray()'s shape). Anything missing or
     * malformed falls back to a safe value rather than failing the
     * listener.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $levels = [];

        foreach (array_values((array) ($data['levels'] ?? [])) as $index => $level) {
            if ($index >= self::MAX_LEVELS) {
                break;
            }

            $level = (array) $level;

            $entry = [
                'name' => self::text($level['name'] ?? null),
            ];

            foreach (self::CONDITIONS as $condition) {
                /* Level 0 is the floor: it has nothing to reach. */
                $entry[$condition] = $index === 0
                    ? null
                    : self::positive($level[$condition] ?? null);
            }

            $entry['phrases'] = self::phraseList($level['phrases'] ?? []);

            $levels[] = $entry;
        }

        if ($levels === []) {
            $levels[] = [
                'name' => null,
                'repeat_from' => null,
                'hour' => null,
                'today' => null,
                'week' => null,
                'repeat_within' => null,
                'phrases' => [],
            ];
        }

        return new self(
            levels: $levels,
            batchQuietSeconds: self::clamp($data['batch_quiet_seconds'] ?? 20, 1, 3600),
            historyDays: self::clamp($data['history_days'] ?? 7, 1, 365),
            maxAttempts: self::clamp($data['max_attempts'] ?? 3, 1, 20),
            retryMinutes: self::clamp($data['retry_minutes'] ?? 30, 1, 1440),
            batchLine: trim((string) ($data['batch_line'] ?? '')),
        );
    }

    /**
     * From the original config/client_checks.php layout, where the
     * conditions, the repeat thresholds and the phrases were three
     * separate maps keyed by level.
     *
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        $repeat = (array) ($config['repeat_levels'] ?? []);
        $history = (array) ($config['levels'] ?? []);
        $phrases = (array) ($config['phrases'] ?? []);

        $keys = array_map('intval', [
            ...array_keys($repeat),
            ...array_keys($history),
            ...array_keys($phrases),
        ]);

        $top = $keys === [] ? 0 : max(0, max($keys));

        $levels = [];

        for ($level = 0; $level <= min($top, self::MAX_LEVELS - 1); $level++) {
            $rules = (array) ($history[$level] ?? []);

            $levels[] = [
                'name' => null,
                'repeat_from' => $repeat[$level] ?? null,
                'hour' => $rules['hour'] ?? null,
                'today' => $rules['today'] ?? null,
                'week' => $rules['week'] ?? null,
                'repeat_within' => $rules['repeat_within'] ?? null,
                'phrases' => (array) ($phrases[$level] ?? []),
            ];
        }

        return self::fromArray([
            'levels' => $levels,
            'batch_quiet_seconds' => $config['batch_quiet_seconds'] ?? 20,
            'history_days' => $config['history_days'] ?? 7,
            'max_attempts' => $config['max_attempts'] ?? 3,
            'retry_minutes' => $config['retry_minutes'] ?? 30,
            'batch_line' => $config['batch_line'] ?? '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'levels' => $this->levels,
            'batch_quiet_seconds' => $this->batchQuietSeconds,
            'history_days' => $this->historyDays,
            'max_attempts' => $this->maxAttempts,
            'retry_minutes' => $this->retryMinutes,
            'batch_line' => $this->batchLine,
        ];
    }

    public function topLevel(): int
    {
        return count($this->levels) - 1;
    }

    /**
     * The highest level the bot's own repeat number reaches.
     */
    public function repeatLevel(int $repeat): int
    {
        for ($level = $this->topLevel(); $level >= 1; $level--) {
            $from = $this->levels[$level]['repeat_from'];

            if ($from !== null && $repeat >= $from) {
                return $level;
            }
        }

        return 0;
    }

    /**
     * The highest level the person's recent history reaches.
     *
     * @param array{hour_count: int, today_count: int, week_count: int, minutes_since_last: int|null} $metrics
     */
    public function historyLevel(array $metrics): int
    {
        for ($level = $this->topLevel(); $level >= 1; $level--) {
            $rules = $this->levels[$level];

            $met = ($rules['hour'] !== null && $metrics['hour_count'] >= $rules['hour'])
                || ($rules['today'] !== null && $metrics['today_count'] >= $rules['today'])
                || ($rules['week'] !== null && $metrics['week_count'] >= $rules['week'])
                || (
                    $rules['repeat_within'] !== null
                    && $metrics['minutes_since_last'] !== null
                    && $metrics['minutes_since_last'] <= $rules['repeat_within']
                );

            if ($met) {
                return $level;
            }
        }

        return 0;
    }

    /**
     * The phrases of a level; one without any borrows the nearest lower
     * level's.
     *
     * @return list<string>
     */
    public function phrases(int $level): array
    {
        for ($l = min($level, $this->topLevel()); $l >= 0; $l--) {
            if ($this->levels[$l]['phrases'] !== []) {
                return $this->levels[$l]['phrases'];
            }
        }

        return [];
    }

    private static function positive(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private static function clamp(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, is_numeric($value) ? (int) $value : $min));
    }

    private static function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? mb_substr($value, 0, 60) : null;
    }

    /**
     * @return list<string>
     */
    private static function phraseList(mixed $phrases): array
    {
        $list = [];

        foreach ((array) $phrases as $phrase) {
            if (is_string($phrase) && trim($phrase) !== '') {
                $list[] = trim($phrase);
            }
        }

        return $list;
    }
}
