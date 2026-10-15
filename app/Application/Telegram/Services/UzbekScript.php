<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

/**
 * Uzbek written in Latin, written again in Cyrillic: "Rahmat, Ali aka" ->
 * "Рахмат, Али ака".
 *
 * The bot's Uzbek texts are kept in Latin (what the panel's phrases are
 * written in); what goes out to people is Cyrillic, which is what they write
 * and read ("салом"). Plain letters only, the way it is typed on a Russian
 * keyboard: no ў, ғ, ҳ, қ (zo'r is зур, rahmat is рахмат, qildim is килдим). Done here, on the way out, so the phrases stay as they
 * are and nothing has to be typed twice.
 *
 * What is left alone: placeholders ({address}), HTML tags and entities,
 * digits, punctuation, emoji and anything already Cyrillic.
 */
final class UzbekScript
{
    private const APOSTROPHES = ["'", '’', 'ʻ', 'ʼ', '‘', '`'];

    private const LETTERS = [
        'a' => 'а', 'b' => 'б', 'c' => 'ц', 'd' => 'д', 'f' => 'ф', 'g' => 'г',
        'h' => 'х', 'i' => 'и', 'j' => 'ж', 'k' => 'к', 'l' => 'л', 'm' => 'м',
        'n' => 'н', 'o' => 'о', 'p' => 'п', 'q' => 'к', 'r' => 'р', 's' => 'с',
        't' => 'т', 'u' => 'у', 'v' => 'в', 'w' => 'в', 'x' => 'х', 'y' => 'й',
        'z' => 'з',
    ];

    /** Two letters that are one: the second is what follows the first. */
    private const PAIRS = [
        's' => ['h' => 'ш'],
        'c' => ['h' => 'ч'],
        'y' => ['o' => 'ё', 'u' => 'ю', 'a' => 'я', 'e' => 'е'],
    ];

    /** o' and g' are letters of their own; written as у and г, without the special Uzbek ў and ғ. */
    private const MARKED = ['o' => 'у', 'g' => 'г'];

    /**
     * Whether Uzbek goes out in Cyrillic (config auto_replies.uz_script).
     */
    public static function enabled(): bool
    {
        return config('auto_replies.uz_script', 'cyrillic') === 'cyrillic';
    }

    /**
     * The Uzbek texts of a phrase set (language -> tone -> texts), written
     * in Cyrillic; the other languages are left as they are.
     *
     * @param array<string, array<string, list<string>>> $sets
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function sets(array $sets): array
    {
        foreach ((array) ($sets['uz'] ?? []) as $tone => $texts) {
            $sets['uz'][$tone] = array_map(
                static fn ($text) => is_string($text) ? self::toCyrillic($text) : $text,
                (array) $texts,
            );
        }

        return $sets;
    }

    /**
     * AutoReplyRules::toArray() with every Uzbek answer in Cyrillic. The
     * phrases are kept that way, so the panel shows what the person gets.
     * Keywords are not touched: they match what people type, in any script.
     *
     * @param array<string, mixed> $rules
     *
     * @return array<string, mixed>
     */
    public static function autoReplies(array $rules): array
    {
        foreach ((array) ($rules['replies'] ?? []) as $i => $reply) {
            $rules['replies'][$i]['answers'] = self::sets((array) ($reply['answers'] ?? []));
        }

        foreach ((array) ($rules['greetings']['list'] ?? []) as $i => $greeting) {
            $rules['greetings']['list'][$i]['answers'] = self::sets((array) ($greeting['answers'] ?? []));
        }

        foreach ([['silence', 'answers'], ['opening', 'answers'], ['follow_up', 'answers'], ['follow_up', 'answers_many']] as [$owner, $field]) {
            if (isset($rules[$owner][$field])) {
                $rules[$owner][$field] = self::sets((array) $rules[$owner][$field]);
            }
        }

        return $rules;
    }

    /**
     * ClientCheckRules::toArray() with every Uzbek phrase in Cyrillic.
     *
     * @param array<string, mixed> $rules
     *
     * @return array<string, mixed>
     */
    public static function penalties(array $rules): array
    {
        foreach ((array) ($rules['roles'] ?? []) as $role => $ladder) {
            foreach ((array) ($ladder['levels'] ?? []) as $i => $level) {
                $rules['roles'][$role]['levels'][$i]['phrases'] = self::sets((array) ($level['phrases'] ?? []));
            }
        }

        return $rules;
    }

    public static function toCyrillic(string $text): string
    {
        $chars = mb_str_split($text);
        $count = count($chars);
        $out = '';

        for ($i = 0; $i < $count; $i++) {
            $char = $chars[$i];

            /* {placeholder}, <tag>, &entity; - copied as they are. */
            $closing = ['{' => '}', '<' => '>', '&' => ';'][$char] ?? null;

            if ($closing !== null) {
                $end = array_search($closing, array_slice($chars, $i + 1, 40, true), true);

                $segment = $end === false ? '' : implode('', array_slice($chars, $i, $end - $i + 1));

                /* An "&" is only an entity when it looks like one ("Tom & Jerry" is not). */
                if ($segment !== '' && ($char !== '&' || preg_match('/^&#?\w+;$/', $segment) === 1)) {
                    $out .= $segment;
                    $i = $end;

                    continue;
                }
            }

            $lower = mb_strtolower($char);
            $upper = $char !== $lower;
            $next = $chars[$i + 1] ?? '';
            $nextLower = mb_strtolower($next);

            if (isset(self::MARKED[$lower]) && in_array($next, self::APOSTROPHES, true)) {
                $out .= self::cased(self::MARKED[$lower], $upper);
                $i++;

                continue;
            }

            /* yo'q is йук: the o belongs to the apostrophe after it, not to the y. */
            $yoMarked = $lower === 'y' && $nextLower === 'o' && in_array($chars[$i + 2] ?? '', self::APOSTROPHES, true);

            if (! $yoMarked && isset(self::PAIRS[$lower][$nextLower])) {
                $out .= self::cased(self::PAIRS[$lower][$nextLower], $upper);
                $i++;

                continue;
            }

            if ($lower === 'e') {
                /* "Ertalab" is Эрталаб, "kelishdik" is келишдик. */
                $out .= self::cased(self::isLetter($chars[$i - 1] ?? '') ? 'е' : 'э', $upper);

                continue;
            }

            /* a'lo, ma'lumot: the hamza between two letters is ъ. */
            if (in_array($char, self::APOSTROPHES, true)) {
                $out .= self::isLetter($chars[$i - 1] ?? '') && self::isLetter($next) ? 'ъ' : $char;

                continue;
            }

            $out .= isset(self::LETTERS[$lower]) ? self::cased(self::LETTERS[$lower], $upper) : $char;
        }

        return $out;
    }

    private static function cased(string $letter, bool $upper): string
    {
        return $upper ? mb_strtoupper($letter) : $letter;
    }

    private static function isLetter(string $char): bool
    {
        return $char !== '' && preg_match('/^\p{L}$/u', $char) === 1;
    }
}
