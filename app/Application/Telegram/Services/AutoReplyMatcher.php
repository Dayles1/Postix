<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

/**
 * Reads a person's private message as one of the kinds of AutoReplyRules -
 * "+", "ok", "хоп", "bo'ldi" as "agreed" - by its keywords, the first kind
 * that has one winning.
 *
 * Both sides are compared loosely: case, Uzbek apostrophes (o' o‘ oʻ),
 * stretched letters ("okkk", "++"), ё/е and the Uzbek Cyrillic letters
 * typed without their marks (ў/у, қ/к, ғ/г, ҳ/х) do not count, and an
 * emoji stuck to a word ("ok👍") is a word of its own.
 *
 * A keyword ending in "*" matches the start of a word: "клиент*" takes
 * "клиента", "berdim*" takes "berdimku" - Uzbek and Russian add endings.
 */
final class AutoReplyMatcher
{
    /**
     * The index of the kind in $rules->replies, or null. A message longer
     * than the kind's max_words is a conversation, not an "ok": "ok, lekin
     * mashina hali topilmadi" is not something to thank for. Kinds that
     * expect an explanation ("клиент трубку не берёт") allow more.
     */
    public function match(AutoReplyRules $rules, string $text): ?int
    {
        $words = self::words($text);

        if ($words === []) {
            return null;
        }

        foreach ($rules->replies as $index => $kind) {
            if (count($words) > ($kind['max_words'] ?? $rules->maxWords)) {
                continue;
            }

            foreach ($kind['keywords'] as $keyword) {
                $prefix = str_ends_with(trim($keyword), '*');
                $needle = self::words(rtrim(trim($keyword), '*'));

                if ($needle !== [] && self::contains($words, $needle, $prefix)) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * Letters and digits make a word; any other symbol ("+", "👍") is a
     * word on its own; punctuation is dropped.
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');

        /*
         * Skin tones, the emoji variation selector and the joiner; letters
         * people type either way.
         */
        $text = (string) preg_replace('/[\x{1F3FB}-\x{1F3FF}\x{FE0F}\x{200D}]/u', '', $text);
        $text = strtr($text, ['ё' => 'е', 'ў' => 'у', 'қ' => 'к', 'ғ' => 'г', 'ҳ' => 'х']);

        preg_match_all(
            "/[\\p{L}\\p{N}\\p{M}'‘’ʻʼ`]+|[^\\s\\p{L}\\p{N}\\p{M}\\p{P}]/u",
            $text,
            $matches,
        );

        $words = [];

        foreach ($matches[0] as $word) {
            /*
             * bo'ldi, bo‘ldi, boldi - one word.
             */
            $word = str_replace(["'", '‘', '’', 'ʻ', 'ʼ', '`'], '', $word);

            /*
             * okkk -> ok, хоппп -> хоп, ++ -> +; done to the keyword as
             * well, so a real double letter still matches itself. Digits
             * stay as they are: 66 is not 6.
             */
            $word = (string) preg_replace('/([^\p{N}])\1+/u', '$1', $word);

            if ($word !== '') {
                $words[] = $word;
            }
        }

        return $words;
    }

    /**
     * Whether $needle occurs in $words, in order and side by side; with
     * $prefix, its last word only has to start a word.
     *
     * @param list<string> $words
     * @param list<string> $needle
     */
    private static function contains(array $words, array $needle, bool $prefix): bool
    {
        $length = count($needle);
        $last = $length - 1;

        for ($i = 0, $end = count($words) - $length; $i <= $end; $i++) {
            for ($j = 0; $j < $length; $j++) {
                $word = $words[$i + $j];

                $same = $prefix && $j === $last
                    ? str_starts_with($word, $needle[$j])
                    : $word === $needle[$j];

                if (! $same) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }
}
