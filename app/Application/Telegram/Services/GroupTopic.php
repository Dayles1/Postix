<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\TelegramJoinKeyword;
use Throwable;

/**
 * Whether a group speaks of our topic, and the new groups and words its
 * messages lead to - the part of Join group that finds groups without
 * anybody giving them:
 *
 *   links()    - the t.me links and @names in a group's messages, and the
 *                public groups its messages were forwarded from: logistics
 *                chats advertise each other all day long;
 *   learn()    - two-word phrases that keep coming back in the titles of
 *                the good groups ("yuk tashish", "фура Москва") become
 *                search keywords of their own.
 *
 * Plain arrays in and out; the roots are config('group_join.topic_roots')
 * and the words of the active keywords.
 */
final class GroupTopic
{
    /** On topic by its messages: at least this many, and this percent, mention it. */
    public const MIN_TOPIC_MESSAGES = 3;
    public const MIN_TOPIC_PERCENT = 5;

    /** A phrase is learned once this many good titles carry it. */
    public const LEARN_MIN_TITLES = 3;

    /** New groups taken from one group's messages at most: a link farm must not flood the queue. */
    public const MAX_LINKS_PER_GROUP = 40;

    /** t.me paths that are not a chat. */
    private const RESERVED = [
        'c', 's', 'share', 'addstickers', 'addemoji', 'addtheme', 'addlist', 'proxy', 'socks', 'iv',
        'setlanguage', 'boost', 'login', 'confirmphone', 'invoice', 'bg', 'm', 'contact', 'giftcode',
    ];

    /**
     * @return list<string> lower-case word beginnings
     */
    public static function roots(): array
    {
        $roots = array_map(fn ($root) => mb_strtolower((string) $root), (array) config('group_join.topic_roots', []));

        try {
            foreach (TelegramJoinKeyword::query()->where('is_active', true)->pluck('keyword') as $keyword) {
                foreach (self::words((string) $keyword) as $word) {
                    if (mb_strlen($word) >= 3) {
                        $roots[] = $word;
                    }
                }
            }
        } catch (Throwable) {
            // Not migrated yet: the config's roots are enough.
        }

        return array_values(array_unique(array_filter($roots, fn ($root) => $root !== '')));
    }

    /**
     * @param list<string> $roots
     */
    public static function mentions(string $text, array $roots): bool
    {
        foreach (self::words($text) as $word) {
            foreach ($roots as $root) {
                if (str_starts_with($word, $root)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * On topic: the title says so, or enough of the messages do.
     *
     * @param list<string> $roots
     */
    public static function onTopic(?string $title, int $topicMessages, int $textMessages, array $roots): bool
    {
        if ($title !== null && self::mentions($title, $roots)) {
            return true;
        }

        return $topicMessages >= self::MIN_TOPIC_MESSAGES
            && $topicMessages * 100 >= self::MIN_TOPIC_PERCENT * max(1, $textMessages);
    }

    /**
     * The groups a group's messages lead to.
     *
     * @param list<array<string, mixed>> $messages messages.getHistory()['messages']
     * @param list<array<string, mixed>> $chats    messages.getHistory()['chats']
     * @param list<string>               $roots
     *
     * @return array<string, array{link: string, source: string, title: string|null, members: int, chat_id: int|null}>
     *               keyed by the lower-case link
     */
    public static function links(array $messages, array $chats, array $roots, ?string $self = null): array
    {
        $found = [];
        $self = $self !== null ? mb_strtolower($self) : null;

        $add = function (string $link, string $source, ?string $title = null, int $members = 0, ?int $chatId = null) use (&$found, $self): void {
            /* A username is the same chat in any case; an invite hash is not. */
            $key = str_starts_with($link, '+') ? $link : mb_strtolower($link);

            if ($key === $self || isset($found[$key]) || count($found) >= self::MAX_LINKS_PER_GROUP) {
                return;
            }

            $found[$key] = ['link' => $key, 'source' => $source, 'title' => $title, 'members' => $members, 'chat_id' => $chatId];
        };

        /* Forwarded from, or mentioned: the public groups among the chats the history came with. */
        foreach ($chats as $chat) {
            if (($chat['_'] ?? null) !== 'channel' || empty($chat['megagroup'])) {
                continue;
            }

            $username = self::username($chat);

            if ($username !== null) {
                $add($username, 'forward', $chat['title'] ?? null, (int) ($chat['participants_count'] ?? 0), self::chatId($chat));
            }
        }

        foreach ($messages as $message) {
            $text = (string) ($message['message'] ?? '');
            $urls = [$text];

            foreach ((array) ($message['entities'] ?? []) as $entity) {
                if (($entity['_'] ?? null) === 'messageEntityTextUrl' && ! empty($entity['url'])) {
                    $urls[] = (string) $entity['url'];
                }
            }

            foreach ($urls as $piece) {
                foreach (self::linksIn($piece) as $link) {
                    $add($link, 'link');
                }
            }

            /* An @name is mostly a person; only names that speak of the topic are worth a look. */
            if (preg_match_all('/(?<![\w@.\/])@([A-Za-z][A-Za-z0-9_]{3,31})\b/u', $text, $m)) {
                foreach ($m[1] as $name) {
                    if (! self::isBotName($name) && self::nameOnTopic($name, $roots)) {
                        $add($name, 'link');
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Two-word phrases that keep coming back in good titles, with one of
     * the topic's words in them.
     *
     * @param list<string> $titles the titles of the approved and joined groups
     * @param list<string> $roots
     * @param list<string> $known  the keywords there are already (lower case)
     *
     * @return list<string>
     */
    public static function learn(array $titles, array $roots, array $known, int $limit = 20): array
    {
        $counts = [];

        foreach ($titles as $title) {
            $words = array_values(array_filter(
                self::words($title),
                fn ($word) => mb_strlen($word) >= 3 && ! preg_match('/\d/u', $word),
            ));

            $seen = [];

            for ($i = 0; $i + 1 < count($words); $i++) {
                $pair = $words[$i] . ' ' . $words[$i + 1];

                if (isset($seen[$pair]) || ! self::mentions($pair, $roots)) {
                    continue;
                }

                $seen[$pair] = true;
                $counts[$pair] = ($counts[$pair] ?? 0) + 1;
            }
        }

        $known = array_flip($known);

        $counts = array_filter(
            $counts,
            fn (int $count, string $pair) => $count >= self::LEARN_MIN_TITLES && ! isset($known[$pair]),
            ARRAY_FILTER_USE_BOTH,
        );

        arsort($counts);

        return array_slice(array_keys($counts), 0, $limit);
    }

    /**
     * @return list<string> usernames and +invite hashes
     */
    private static function linksIn(string $text): array
    {
        $links = [];

        if (preg_match_all('~(?:https?://)?(?:www\.)?(?:t|telegram)\.me/(?:joinchat/|\+)([A-Za-z0-9_-]{10,})~u', $text, $m)) {
            foreach ($m[1] as $hash) {
                $links[] = '+' . $hash;
            }
        }

        if (preg_match_all('~(?:https?://)?(?:www\.)?(?:t|telegram)\.me/([A-Za-z][A-Za-z0-9_]{3,31})(?![A-Za-z0-9_])~u', $text, $m)) {
            foreach ($m[1] as $name) {
                if (! in_array(mb_strtolower($name), self::RESERVED, true) && ! self::isBotName($name)) {
                    $links[] = $name;
                }
            }
        }

        return $links;
    }

    /**
     * @param list<string> $roots
     */
    private static function nameOnTopic(string $name, array $roots): bool
    {
        $name = mb_strtolower($name);

        foreach ($roots as $root) {
            /* A username is Latin: only the Latin roots can be in it, anywhere in it. */
            if (preg_match('/^[a-z]+$/', $root) === 1 && str_contains($name, $root)) {
                return true;
            }
        }

        return false;
    }

    private static function isBotName(string $name): bool
    {
        return str_ends_with(mb_strtolower($name), 'bot');
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $text = str_replace(['’', 'ʻ', 'ʼ', '‘', '`'], "'", mb_strtolower($text));

        preg_match_all("/\p{L}[\p{L}']*/u", $text, $matches);

        return array_map(fn (string $word) => rtrim($word, "'"), $matches[0]);
    }

    /**
     * @param array<string, mixed> $chat
     */
    public static function username(array $chat): ?string
    {
        if (! empty($chat['username'])) {
            return (string) $chat['username'];
        }

        foreach ((array) ($chat['usernames'] ?? []) as $name) {
            if (! empty($name['active']) && ! empty($name['username'])) {
                return (string) $name['username'];
            }
        }

        return null;
    }

    /**
     * The bot-API id (-100...) whichever form MadelineProto handed over.
     *
     * @param array<string, mixed> $chat
     */
    public static function chatId(array $chat): ?int
    {
        if (! isset($chat['id']) || ! is_numeric($chat['id'])) {
            return null;
        }

        $id = (int) $chat['id'];

        return $id > 0 ? -1000000000000 - $id : $id;
    }
}
