<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\OperationUser;

/**
 * What a person is told about a penalty, by how many times the bot has
 * sent it - kept apart for operators and for sales managers.
 *
 * The count is the bot's own: "⚠️ Штраф по запросу #…" is the first time,
 * "🆘 Повторное отправление штрафа №N по запросу #…" the N-th. Each role has
 * its own ladder of levels. A level starts at a repeat number (`from`),
 * says what goes out at it (`mode`: the forward and a comment, the forward
 * only, or nothing) and holds four sets of phrases - Uzbek and Russian,
 * each plain and respectful (for people older than the one writing). The
 * person's role picks the ladder; their language and respect setting pick
 * the set.
 *
 * Written in the panel (stored by ClientCheckRulesStore);
 * config/client_checks.php is only the starting point.
 */
final readonly class ClientCheckRules
{
    public const MAX_LEVELS = 10;

    public const TONE_PLAIN = 'plain';

    public const TONE_RESPECTFUL = 'respectful';

    public const TONES = [self::TONE_PLAIN, self::TONE_RESPECTFUL];

    /**
     * The bot's message forwarded, then the comment.
     */
    public const MODE_ALL = 'all';

    /**
     * The bot's message forwarded, no comment after it.
     */
    public const MODE_FORWARD = 'forward';

    /**
     * Nothing goes out at this level - for this role.
     */
    public const MODE_OFF = 'off';

    public const MODES = [self::MODE_ALL, self::MODE_FORWARD, self::MODE_OFF];

    /**
     * What a phrase may contain; filled in by ClientCheckEscalation. The
     * durations come out in the phrase's language ("2 ч" / "2 soat").
     */
    public const PLACEHOLDERS = [
        'name',
        'request',
        'repeat_number',
        'status_limit',
        'time_in_status',
        'crm_status',
    ];

    /**
     * @param array<string, list<array{
     *     name: string|null,
     *     from: int,
     *     mode: string,
     *     phrases: array<string, array<string, list<string>>>,
     * }>> $ladders  per role, each ordered by `from`, the first one from 1
     */
    public function __construct(
        public array $ladders,
        public int $batchQuietSeconds,
        public int $maxAttempts,
        public int $retryMinutes,
    ) {
    }

    /**
     * From what the panel saves (toArray()'s shape). Anything missing or
     * malformed falls back to a safe value rather than failing the
     * listener.
     *
     * Two older shapes are read too: one `levels` list shared by both roles
     * (before the roles were split - both get a copy), and inside it one
     * flat list of Russian phrases per level with `repeat_from` (before
     * languages existed).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $shared = $data['levels'] ?? null;

        $ladders = [];

        foreach (OperationUser::ROLES as $role) {
            $levels = $data['roles'][$role]['levels'] ?? $shared ?? [];

            $ladders[$role] = self::ladder((array) $levels);
        }

        return new self(
            ladders: $ladders,
            /* Same default as config/client_checks.php: 5 seconds. */
            batchQuietSeconds: self::clamp($data['batch_quiet_seconds'] ?? 5, 1, 3600),
            maxAttempts: self::clamp($data['max_attempts'] ?? 3, 1, 20),
            retryMinutes: self::clamp($data['retry_minutes'] ?? 30, 1, 1440),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromConfig(array $config): self
    {
        return self::fromArray($config);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $roles = [];

        foreach ($this->ladders as $role => $levels) {
            $roles[$role] = ['levels' => $levels];
        }

        return [
            'roles' => $roles,
            'batch_quiet_seconds' => $this->batchQuietSeconds,
            'max_attempts' => $this->maxAttempts,
            'retry_minutes' => $this->retryMinutes,
        ];
    }

    /**
     * @return list<array{name: string|null, from: int, mode: string, phrases: array<string, array<string, list<string>>>}>
     */
    public function levels(string $role): array
    {
        return $this->ladders[self::role($role)];
    }

    public function topLevel(string $role): int
    {
        return count($this->levels($role)) - 1;
    }

    /**
     * The level the bot's repeat number lands on, on this role's ladder.
     */
    public function levelFor(string $role, int $repeat): int
    {
        $levels = $this->levels($role);

        for ($level = count($levels) - 1; $level >= 1; $level--) {
            if ($repeat >= $levels[$level]['from']) {
                return $level;
            }
        }

        return 0;
    }

    /**
     * What goes out at a level for this role (MODE_*).
     */
    public function mode(string $role, int $level): string
    {
        $levels = $this->levels($role);

        return $levels[max(0, min($level, count($levels) - 1))]['mode'];
    }

    /**
     * The phrases for a role, a level, a language and a tone, and which set
     * they actually came from.
     *
     * A missing set is filled in from the nearest one that says the same
     * thing: the other tone of the same language, then the levels below in
     * that language, and only when the language has nothing at all, the
     * other language - a plain phrase in the right language beats a
     * polite one nobody can read. Always within the role's own ladder.
     *
     * @return array{phrases: list<string>, language: string, tone: string, level: int}
     */
    public function variant(string $role, int $level, string $language, string $tone): array
    {
        $levels = $this->levels($role);

        $level = max(0, min($level, count($levels) - 1));

        $languages = [
            $language,
            ...array_values(array_diff(OperationUser::LANGUAGES, [$language])),
        ];

        $tones = [
            $tone,
            ...array_values(array_diff(self::TONES, [$tone])),
        ];

        foreach ($languages as $lang) {
            for ($l = $level; $l >= 0; $l--) {
                foreach ($tones as $t) {
                    $phrases = $levels[$l]['phrases'][$lang][$t] ?? [];

                    if ($phrases !== []) {
                        return ['phrases' => $phrases, 'language' => $lang, 'tone' => $t, 'level' => $l];
                    }
                }
            }
        }

        return ['phrases' => [], 'language' => $language, 'tone' => $tone, 'level' => $level];
    }

    /**
     * Anything that is not sales reads as an operator, the same as
     * OperationUser::roleOrDefault().
     */
    public static function role(?string $role): string
    {
        return OperationUser::isRole($role) ? $role : OperationUser::ROLE_OPERATION;
    }

    /**
     * @param array<int, mixed> $levels
     * @return list<array{name: string|null, from: int, mode: string, phrases: array<string, array<string, list<string>>>}>
     */
    private static function ladder(array $levels): array
    {
        $ladder = [];
        $previous = 0;

        foreach (array_values($levels) as $index => $level) {
            if ($index >= self::MAX_LEVELS) {
                break;
            }

            $level = (array) $level;

            /*
             * The first level is the first penalty; every next one starts
             * after the one before it.
             */
            $from = $index === 0
                ? 1
                : max($previous + 1, self::positive($level['from'] ?? $level['repeat_from'] ?? null) ?? $previous + 1);

            $ladder[] = [
                'name' => self::text($level['name'] ?? null),
                'from' => $from,
                'mode' => in_array($level['mode'] ?? null, self::MODES, true) ? $level['mode'] : self::MODE_ALL,
                'phrases' => self::phraseSets($level['phrases'] ?? []),
            ];

            $previous = $from;
        }

        if ($ladder === []) {
            $ladder[] = ['name' => null, 'from' => 1, 'mode' => self::MODE_ALL, 'phrases' => self::phraseSets([])];
        }

        return $ladder;
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    private static function phraseSets(mixed $phrases): array
    {
        $phrases = (array) $phrases;

        /*
         * The old shape: one list, written in Russian.
         */
        if (array_is_list($phrases)) {
            $phrases = [OperationUser::LANGUAGE_RU => [self::TONE_PLAIN => $phrases]];
        }

        $sets = [];

        foreach (OperationUser::LANGUAGES as $language) {
            foreach (self::TONES as $tone) {
                $sets[$language][$tone] = self::phraseList($phrases[$language][$tone] ?? []);
            }
        }

        return $sets;
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
