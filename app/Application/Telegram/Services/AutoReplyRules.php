<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\OperationUser;

/**
 * What a person's private message is read as, and what they are told back
 * (config/auto_replies.php explains every field). Kept in one JSON file by
 * AutoReplyStore; read on every message by ProcessAutoReply.
 */
final readonly class AutoReplyRules
{
    public const MAX_REPLIES = 20;

    public const MAX_KEYWORDS = 200;

    public const MAX_ANSWERS = 30;

    /**
     * What an answer may contain; filled in by ClientCheckEscalation::render().
     */
    public const PLACEHOLDERS = ['address', 'name', 'request'];

    /**
     * @param list<array{
     *     name: string|null,
     *     keywords: list<string>,
     *     max_words: int|null,
     *     answers: array<string, array<string, list<string>>>,
     * }> $replies  tried top to bottom, the first match wins; max_words
     *              null is the file's own
     * @param array{
     *     enabled: bool,
     *     after_minutes: int,
     *     answers: array<string, array<string, list<string>>>,
     * } $silence  the nudge after a penalty nobody answered
     */
    public function __construct(
        public bool $enabled,
        public bool $onlyAfterPenalty,
        public int $penaltyWindowMinutes,
        public int $cooldownMinutes,
        public int $maxWords,
        public array $replies,
        public array $silence = ['enabled' => false, 'after_minutes' => 15, 'answers' => []],
    ) {
    }

    /**
     * Rules that answer nothing: what a broken file reads as.
     */
    public static function off(): self
    {
        return new self(false, true, 60, 0, 5, []);
    }

    /**
     * From the file (toArray()'s shape). Anything missing or malformed falls
     * back to a safe value rather than failing the listener.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $replies = [];

        foreach (array_values((array) ($data['replies'] ?? [])) as $reply) {
            if (count($replies) >= self::MAX_REPLIES) {
                break;
            }

            $reply = (array) $reply;

            $keywords = array_values(array_unique(array_map(
                static fn (string $keyword): string => mb_substr($keyword, 0, 60),
                self::texts($reply['keywords'] ?? []),
            )));

            $name = is_string($reply['name'] ?? null) ? trim($reply['name']) : '';

            $maxWords = $reply['max_words'] ?? null;

            $replies[] = [
                'name' => $name !== '' ? mb_substr($name, 0, 60) : null,
                'keywords' => array_slice($keywords, 0, self::MAX_KEYWORDS),
                'max_words' => is_numeric($maxWords) ? self::clamp($maxWords, 1, 50) : null,
                'answers' => self::sets($reply['answers'] ?? []),
            ];
        }

        $silence = (array) ($data['silence'] ?? []);

        return new self(
            enabled: (bool) ($data['enabled'] ?? true),
            onlyAfterPenalty: (bool) ($data['only_after_penalty'] ?? false),
            penaltyWindowMinutes: self::clamp($data['penalty_window_minutes'] ?? 60, 1, 1440),
            cooldownMinutes: self::clamp($data['cooldown_minutes'] ?? 10, 0, 1440),
            maxWords: self::clamp($data['max_words'] ?? 5, 1, 50),
            replies: $replies,
            silence: [
                'enabled' => (bool) ($silence['enabled'] ?? false),
                'after_minutes' => self::clamp($silence['after_minutes'] ?? 15, 1, 1440),
                'answers' => self::sets($silence['answers'] ?? []),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'only_after_penalty' => $this->onlyAfterPenalty,
            'penalty_window_minutes' => $this->penaltyWindowMinutes,
            'cooldown_minutes' => $this->cooldownMinutes,
            'max_words' => $this->maxWords,
            'replies' => $this->replies,
            'silence' => $this->silence,
        ];
    }

    /**
     * The kind's name, for the journal: renamed and reordered in the panel,
     * so the name of the moment is kept rather than an index.
     */
    public function name(int $kind): string
    {
        return $this->replies[$kind]['name'] ?? '#' . ($kind + 1);
    }

    /**
     * The answers of a kind for a language and a tone, and which set they
     * came from: the other tone first, then the other language - a plain
     * answer in the right language beats a polite one nobody reads.
     *
     * @return array{answers: list<string>, language: string, tone: string}
     */
    public function answers(int $kind, string $language, string $tone): array
    {
        return self::pick($this->replies[$kind]['answers'] ?? [], $language, $tone);
    }

    /**
     * The nudge's answers, the same way.
     *
     * @return array{answers: list<string>, language: string, tone: string}
     */
    public function silenceAnswers(string $language, string $tone): array
    {
        return self::pick($this->silence['answers'], $language, $tone);
    }

    /**
     * @param array<string, array<string, list<string>>> $sets
     * @return array{answers: list<string>, language: string, tone: string}
     */
    private static function pick(array $sets, string $language, string $tone): array
    {
        foreach ([$language, ...array_values(array_diff(OperationUser::LANGUAGES, [$language]))] as $lang) {
            foreach ([$tone, ...array_values(array_diff(ClientCheckRules::TONES, [$tone]))] as $t) {
                $answers = $sets[$lang][$t] ?? [];

                if ($answers !== []) {
                    return ['answers' => $answers, 'language' => $lang, 'tone' => $t];
                }
            }
        }

        return ['answers' => [], 'language' => $language, 'tone' => $tone];
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    private static function sets(mixed $answers): array
    {
        $sets = [];

        foreach (OperationUser::LANGUAGES as $language) {
            foreach (ClientCheckRules::TONES as $tone) {
                $sets[$language][$tone] = array_slice(
                    self::texts(((array) $answers)[$language][$tone] ?? []),
                    0,
                    self::MAX_ANSWERS,
                );
            }
        }

        return $sets;
    }

    private static function clamp(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, is_numeric($value) ? (int) $value : $min));
    }

    /**
     * @return list<string>
     */
    private static function texts(mixed $values): array
    {
        $list = [];

        foreach ((array) $values as $value) {
            if (is_string($value) && trim($value) !== '') {
                $list[] = trim($value);
            }
        }

        return $list;
    }
}
