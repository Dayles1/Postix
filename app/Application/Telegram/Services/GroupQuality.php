<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

/**
 * What a group's last messages say about it, and whether it is worth an
 * account: alive (written in lately), busy (many messages, many people), of
 * our topic (GroupTopic) and written in Cyrillic or Latin letters - Russian,
 * Uzbek either way. Which language exactly does not matter.
 *
 * Plain arrays in and out, no Telegram here: the join service hands in what
 * messages.getHistory returned.
 */
final class GroupQuality
{
    /** Below this many letters the script is not judged: photos, numbers, stickers. */
    public const MIN_SCRIPT_LETTERS = 100;

    /** At least this percent of the letters are Cyrillic or Latin. */
    public const MIN_SCRIPT_SHARE = 70;

    /**
     * @param list<array<string, mixed>> $messages messages.getHistory()['messages']
     * @param list<string>               $roots    GroupTopic::roots()
     *
     * @return array{last_message_at: int|null, messages_day: int, messages_week: int, senders_week: int, per_day: float, script_share: int|null, topic_messages: int, text_messages: int}
     */
    public static function measure(array $messages, int $now, array $roots = []): array
    {
        $dates = [];
        $senders = [];

        $dayAgo = $now - 86400;
        $weekAgo = $now - 7 * 86400;

        $day = 0;
        $week = 0;

        $letters = 0;
        $ours = 0;

        $texts = 0;
        $topic = 0;

        foreach ($messages as $message) {
            /* Joins, pins, title changes: not something anybody wrote. */
            if (($message['_'] ?? null) !== 'message') {
                continue;
            }

            $date = (int) ($message['date'] ?? 0);

            if ($date <= 0) {
                continue;
            }

            $dates[] = $date;

            $text = (string) ($message['message'] ?? '');

            if (trim($text) !== '') {
                $texts++;

                if ($roots !== [] && GroupTopic::mentions($text, $roots)) {
                    $topic++;
                }

                /* Links are Latin whatever the group writes in. */
                $text = (string) preg_replace('~(https?://|www\.|t\.me/)\S+|[@#][\w.]+~u', ' ', $text);

                $letters += (int) preg_match_all('/\p{L}/u', $text);
                $ours += (int) preg_match_all('/[\p{Cyrillic}\p{Latin}]/u', $text);
            }

            if ($date >= $dayAgo) {
                $day++;
            }

            if ($date >= $weekAgo) {
                $week++;
                $senders[self::sender($message['from_id'] ?? null)] = true;
            }
        }

        return [
            'last_message_at' => $dates === [] ? null : max($dates),
            'messages_day' => $day,
            'messages_week' => $week,
            'senders_week' => count($senders),
            'per_day' => self::perDay($dates, $now),
            'script_share' => $letters >= self::MIN_SCRIPT_LETTERS ? (int) round($ours * 100 / $letters) : null,
            'topic_messages' => $topic,
            'text_messages' => $texts,
        ];
    }

    /**
     * Why the group is not worth joining, or null when it is.
     *
     * @param array<string, mixed> $metrics what measure() returned
     * @param array<string, mixed> $config  GroupJoinSettings::current()
     * @param list<string>         $roots   GroupTopic::roots(); none: the topic is not judged
     */
    public static function verdict(array $metrics, int $members, array $config, int $now, ?string $title = null, array $roots = []): ?string
    {
        if ($members < (int) $config['min_members']) {
            return 'too_small';
        }

        $last = $metrics['last_message_at'];

        if ($last === null || $last < $now - (int) $config['max_silence_hours'] * 3600) {
            return 'inactive';
        }

        if ((int) $metrics['messages_week'] < (int) $config['min_messages_week']) {
            return 'quiet';
        }

        if ($roots !== [] && ! GroupTopic::onTopic($title, (int) $metrics['topic_messages'], (int) $metrics['text_messages'], $roots)) {
            return 'off_topic';
        }

        /* Too little text to tell is no reason to turn the group down. */
        if ($metrics['script_share'] !== null && $metrics['script_share'] < self::MIN_SCRIPT_SHARE) {
            return 'script';
        }

        return null;
    }

    /**
     * The order the groups are joined in: the more written lately, and by
     * the more people, the sooner.
     *
     * @param array<string, mixed> $metrics
     */
    public static function score(array $metrics): int
    {
        return (int) round(
            (float) $metrics['per_day'] * 10
            + (int) $metrics['senders_week'] * 5
            + (int) $metrics['messages_day'] * 2,
        );
    }

    /**
     * Messages a day over the span the sample covers. A sample of 100 read
     * in one afternoon is counted as one day, not blown up to thousands.
     *
     * @param list<int> $dates
     */
    private static function perDay(array $dates, int $now): float
    {
        if ($dates === []) {
            return 0.0;
        }

        $days = max(1.0, ($now - min($dates)) / 86400);

        return round(count($dates) / $days, 1);
    }

    /**
     * from_id comes as a bot-API id from MadelineProto, as a Peer array from
     * a raw call, or not at all for a post written as the group itself.
     */
    private static function sender(mixed $from): string
    {
        if (is_array($from)) {
            $from = $from['user_id'] ?? $from['channel_id'] ?? $from['chat_id'] ?? null;
        }

        return $from === null ? 'chat' : (string) $from;
    }
}
