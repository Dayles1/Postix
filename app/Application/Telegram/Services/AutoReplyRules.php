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

    public const MAX_GREETINGS = 20;

    public const MAX_FILLERS = 100;

    /**
     * GIFs of a kind, and voice messages per language.
     */
    public const MAX_MEDIA = 20;

    public const MEDIA_GIF = 'gif';

    public const MEDIA_VOICE = 'voice';

    /**
     * What an answer may contain; filled in by ClientCheckEscalation::render().
     */
    public const PLACEHOLDERS = ['address', 'name', 'request'];

    /**
     * @param list<array{
     *     id: string,
     *     name: string|null,
     *     keywords: list<string>,
     *     max_words: int|null,
     *     answers: array<string, array<string, list<string>>>,
     *     media: array{gifs: list<array{file: string, name: string, telegram?: array{id: string, access_hash: string, file_reference: string}}>, voices: array<string, list<array{file: string, name: string}>>},
     * }> $replies  tried top to bottom, the first match wins; max_words
     *              null is the file's own
     * @param array{
     *     enabled: bool,
     *     after_penalties: int,
     *     answers: array<string, array<string, list<string>>>,
     *     media: array{gifs: list<array{file: string, name: string}>, voices: array<string, list<array{file: string, name: string}>>},
     * } $silence  the nudge after this many penalties in a row nobody
     *             answered
     * @param array{
     *     enabled: bool,
     *     fillers: list<string>,
     *     list: list<array{id: string, name: string|null, keywords: list<string>, answers: array<string, array<string, list<string>>>}>,
     * } $greetings  "Доброе утро" answered on its own, before the kind's
     *               answer; the first of the list found picks the answer
     */
    public function __construct(
        public bool $enabled,
        public bool $onlyAfterPenalty,
        public int $penaltyWindowMinutes,
        public int $cooldownMinutes,
        public int $maxWords,
        public array $replies,
        public array $silence = ['enabled' => false, 'after_penalties' => 5, 'answers' => [], 'media' => ['gifs' => [], 'voices' => []]],
        public array $greetings = ['enabled' => false, 'fillers' => [], 'list' => []],
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
                'id' => self::id($reply['id'] ?? null, 'r', count($replies), array_column($replies, 'id')),
                'name' => $name !== '' ? mb_substr($name, 0, 60) : null,
                'keywords' => array_slice($keywords, 0, self::MAX_KEYWORDS),
                'max_words' => is_numeric($maxWords) ? self::clamp($maxWords, 1, 50) : null,
                'answers' => self::sets($reply['answers'] ?? []),
                'media' => self::media($reply['media'] ?? []),
            ];
        }

        $silence = (array) ($data['silence'] ?? []);

        /*
         * Files saved before there were greetings get the config's.
         */
        $greetings = array_key_exists('greetings', $data)
            ? (array) $data['greetings']
            : (array) config('auto_replies.defaults.greetings', []);

        return new self(
            enabled: (bool) ($data['enabled'] ?? true),
            onlyAfterPenalty: (bool) ($data['only_after_penalty'] ?? false),
            penaltyWindowMinutes: self::clamp($data['penalty_window_minutes'] ?? 60, 1, 1440),
            cooldownMinutes: self::clamp($data['cooldown_minutes'] ?? 10, 0, 1440),
            maxWords: self::clamp($data['max_words'] ?? 5, 1, 50),
            replies: $replies,
            silence: [
                'enabled' => (bool) ($silence['enabled'] ?? false),
                /*
                 * Files from before it counted penalties had after_minutes:
                 * they get the default.
                 */
                'after_penalties' => self::clamp($silence['after_penalties'] ?? 5, 1, 50),
                'answers' => self::sets($silence['answers'] ?? []),
                'media' => self::media($silence['media'] ?? []),
            ],
            greetings: self::greetings($greetings),
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
            'greetings' => $this->greetings,
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
     * The answers of greeting $index, the same way.
     *
     * @return array{answers: list<string>, language: string, tone: string}
     */
    public function greetingAnswers(int $index, string $language, string $tone): array
    {
        return self::pick($this->greetings['list'][$index]['answers'] ?? [], $language, $tone);
    }

    /**
     * Everything a kind (or the nudge, $kind = 'silence') may answer this
     * person with, one to be picked at random: the texts as answers()
     * finds them, the GIFs, and the voice messages in the person's own
     * language - a voice in the other language is not understood, so
     * there is no falling back for those.
     *
     * @return array{
     *     language: string,
     *     items: list<array{type: 'text', text: string}|array{type: 'gif'|'voice', file: string, name: string, telegram?: array{id: string, access_hash: string, file_reference: string}}>,
     * }
     */
    public function choices(int|string $kind, string $language, string $tone): array
    {
        $owner = $kind === 'silence' ? $this->silence : ($this->replies[$kind] ?? null);

        if ($owner === null) {
            return ['language' => $language, 'items' => []];
        }

        $texts = self::pick($owner['answers'], $language, $tone);

        $items = array_map(static fn (string $text): array => ['type' => 'text', 'text' => $text], $texts['answers']);

        foreach ($owner['media']['gifs'] ?? [] as $gif) {
            $items[] = ['type' => self::MEDIA_GIF, ...$gif];
        }

        foreach ($owner['media']['voices'][$language] ?? [] as $voice) {
            $items[] = ['type' => self::MEDIA_VOICE, ...$voice];
        }

        return ['language' => $texts['answers'] !== [] ? $texts['language'] : $language, 'items' => $items];
    }

    /**
     * Every media file the rules use, to tell the ones left behind.
     *
     * @return list<string>
     */
    public function mediaFiles(): array
    {
        $files = [];

        foreach ([...$this->replies, $this->silence] as $owner) {
            foreach ($owner['media']['gifs'] ?? [] as $gif) {
                $files[] = $gif['file'];
            }

            foreach ($owner['media']['voices'] ?? [] as $voices) {
                foreach ($voices as $voice) {
                    $files[] = $voice['file'];
                }
            }
        }

        return array_values(array_unique($files));
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
     * @param array<string, mixed> $greetings
     * @return array{
     *     enabled: bool,
     *     fillers: list<string>,
     *     list: list<array{name: string|null, keywords: list<string>, answers: array<string, array<string, list<string>>>}>,
     * }
     */
    private static function greetings(array $greetings): array
    {
        $list = [];

        foreach (array_values((array) ($greetings['list'] ?? [])) as $greeting) {
            if (count($list) >= self::MAX_GREETINGS) {
                break;
            }

            $greeting = (array) $greeting;

            $name = is_string($greeting['name'] ?? null) ? trim($greeting['name']) : '';

            $keywords = array_values(array_unique(array_map(
                static fn (string $keyword): string => mb_substr($keyword, 0, 60),
                self::texts($greeting['keywords'] ?? []),
            )));

            $list[] = [
                'id' => self::id($greeting['id'] ?? null, 'g', count($list), array_column($list, 'id')),
                'name' => $name !== '' ? mb_substr($name, 0, 60) : null,
                'keywords' => array_slice($keywords, 0, self::MAX_KEYWORDS),
                'answers' => self::sets($greeting['answers'] ?? []),
            ];
        }

        $fillers = array_values(array_unique(array_map(
            static fn (string $filler): string => mb_substr($filler, 0, 60),
            self::texts($greetings['fillers'] ?? []),
        )));

        return [
            'enabled' => (bool) ($greetings['enabled'] ?? false),
            'fillers' => array_slice($fillers, 0, self::MAX_FILLERS),
            'list' => $list,
        ];
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

    /**
     * Only names AutoReplyMedia could have given: a hand edit cannot point
     * the listener at any other file.
     *
     * @return array{gifs: list<array{file: string, name: string}>, voices: array<string, list<array{file: string, name: string}>>}
     */
    private static function media(mixed $media): array
    {
        $media = (array) $media;

        $voices = [];

        foreach (OperationUser::LANGUAGES as $language) {
            $voices[$language] = self::files(((array) ($media['voices'] ?? []))[$language] ?? [], AutoReplyMedia::VOICE_EXTENSIONS);
        }

        return [
            'gifs' => self::files($media['gifs'] ?? [], AutoReplyMedia::GIF_EXTENSIONS),
            'voices' => $voices,
        ];
    }

    /**
     * @param list<string> $extensions
     * @return list<array{file: string, name: string, telegram?: array{id: string, access_hash: string, file_reference: string}}>
     */
    private static function files(mixed $items, array $extensions): array
    {
        $list = [];

        foreach ((array) $items as $item) {
            $item = (array) $item;
            $file = is_string($item['file'] ?? null) ? $item['file'] : '';

            if (! AutoReplyMedia::validName($file, $extensions)) {
                continue;
            }

            $name = is_string($item['name'] ?? null) && trim($item['name']) !== '' ? trim($item['name']) : $file;

            $telegram = self::telegram($item['telegram'] ?? null);

            $list[] = [
                'file' => $file,
                'name' => mb_substr($name, 0, 120),
                ...($telegram !== null ? ['telegram' => $telegram] : []),
            ];

            if (count($list) >= self::MAX_MEDIA) {
                break;
            }
        }

        return $list;
    }

    /**
     * What a kind or a greeting is known by when it is renamed or moved:
     * personal answers (PersonalAnswers) point at it. Files from before
     * there were ids get one by their place ("r0", "g2"), kept from the
     * first save on.
     *
     * @param list<string> $taken
     */
    private static function id(mixed $id, string $prefix, int $index, array $taken): string
    {
        if (is_string($id) && preg_match('/^[a-z0-9]{1,24}$/', $id) === 1 && ! in_array($id, $taken, true)) {
            return $id;
        }

        $id = $prefix . $index;

        while (in_array($id, $taken, true)) {
            $id .= 'x';
        }

        return $id;
    }

    /**
     * The kind with this id, null when there is none any more.
     */
    public function kindById(string $id): ?int
    {
        foreach ($this->replies as $index => $reply) {
            if ($reply['id'] === $id) {
                return $index;
            }
        }

        return null;
    }

    /**
     * A GIF found in Telegram (AutoReplyTelegramGifs): the document it is
     * sent as. Ids as strings - the panel's JavaScript would round them.
     *
     * @return array{id: string, access_hash: string, file_reference: string}|null
     */
    public static function telegram(mixed $telegram): ?array
    {
        $telegram = (array) $telegram;

        foreach (['id', 'access_hash'] as $key) {
            if (! is_string($telegram[$key] ?? null) || preg_match('/^-?\d{1,20}$/', $telegram[$key]) !== 1) {
                return null;
            }
        }

        $reference = $telegram['file_reference'] ?? '';

        if (! is_string($reference) || preg_match('#^[A-Za-z0-9+/=]{0,400}$#', $reference) !== 1) {
            return null;
        }

        return ['id' => $telegram['id'], 'access_hash' => $telegram['access_hash'], 'file_reference' => $reference];
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
