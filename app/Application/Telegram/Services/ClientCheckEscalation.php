<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use Illuminate\Support\Collection;

/**
 * What to tell someone about a penalty (ClientCheckRules, edited in the
 * panel).
 *
 * The level is the bot's own count - the first penalty, the second
 * ("Повторное отправление штрафа №2"), and so on - nothing is counted
 * here - read on the ladder of the person's role: operators and sales
 * managers have their own. The phrase set is the person's too: their
 * language, and the respectful tone for people older than the one
 * writing.
 */
final class ClientCheckEscalation
{
    public function __construct(
        private readonly ClientCheckRulesStore $store,
    ) {
    }

    public function rules(): ClientCheckRules
    {
        return $this->store->current();
    }

    /**
     * Works out the level of a freshly recorded penalty and stores it.
     */
    public function apply(TelegramClientCheck $check, OperationUser $person): void
    {
        $check->update([
            'level' => $this->rules()->levelFor(
                $person->roleOrDefault(),
                max(1, (int) $check->repeat_number),
            ),
        ]);
    }

    /**
     * What goes out for this penalty to this person (ClientCheckRules::MODE_*),
     * as the rules say right now.
     */
    public function mode(TelegramClientCheck $check, OperationUser $person): string
    {
        $rules = $this->rules();
        $role = $person->roleOrDefault();

        return $rules->mode($role, $rules->levelFor($role, max(1, (int) $check->repeat_number)));
    }

    /**
     * The one comment for a batch - every penalty of one person in a
     * burst, whatever the request - chosen and stored on the penalty it
     * speaks for: the strongest one whose level has a comment, the latest
     * of them on a tie.
     *
     * @param Collection<int, TelegramClientCheck> $batch oldest first
     */
    public function comment(Collection $batch, OperationUser $person): ?string
    {
        $last = $this->strongest($batch, $person);

        if ($last === null) {
            return null;
        }

        $rules = $this->rules();
        $role = $person->roleOrDefault();

        /*
         * Read again rather than taken from the row: levels may have been
         * removed since the penalty came in.
         */
        $level = $rules->levelFor($role, max(1, (int) $last->repeat_number));

        $variant = $rules->variant(
            $role,
            $level,
            $person->messageLanguage(),
            $person->respectful ? ClientCheckRules::TONE_RESPECTFUL : ClientCheckRules::TONE_PLAIN,
        );

        if ($variant['phrases'] === []) {
            return null;
        }

        $index = $this->pick($last, $person, $variant);

        $comment = $this->render($variant['phrases'][$index], $last, $person, $variant['language']);

        $last->update([
            'phrase_index' => $index,
            'comment_level' => $level,
            'comment' => $comment,
            'batch_count' => $batch->count(),
            /*
             * Which set the phrase came from, for the panel and so the next
             * pick can tell "the same phrase" from "the same index".
             */
            'metrics' => [
                'language' => $variant['language'],
                'tone' => $variant['tone'],
                'phrase_level' => $variant['level'],
            ],
        ]);

        return $comment;
    }

    /**
     * Whether a comment follows this batch at all: every level in it may
     * say "forward only" (or nothing) for the role.
     *
     * @param Collection<int, TelegramClientCheck> $batch
     */
    public function commentAllowed(Collection $batch, OperationUser $person): bool
    {
        return $this->strongest($batch, $person) !== null;
    }

    /**
     * The penalty the batch's comment speaks for: the highest repeat among
     * those whose level has a comment - a "forward only" level does not
     * silence another request's comment - and the latest on a tie.
     *
     * @param Collection<int, TelegramClientCheck> $batch oldest first
     */
    private function strongest(Collection $batch, OperationUser $person): ?TelegramClientCheck
    {
        return $batch
            ->filter(fn (TelegramClientCheck $check): bool => $this->mode($check, $person) === ClientCheckRules::MODE_ALL)
            ->sortBy([['repeat_number', 'asc'], ['id', 'asc']])
            ->last();
    }

    public function quietSeconds(): int
    {
        return $this->rules()->batchQuietSeconds;
    }

    /**
     * Random, but not the phrase this person got last time from the same
     * set.
     *
     * @param array{phrases: list<string>, language: string, tone: string, level: int} $variant
     */
    private function pick(TelegramClientCheck $last, OperationUser $person, array $variant): int
    {
        $count = count($variant['phrases']);

        if ($count === 1) {
            return 0;
        }

        $previous = TelegramClientCheck::query()
            ->where('operation_user_id', $person->id)
            ->where('id', '<', $last->id)
            ->whereNotNull('phrase_index')
            ->whereNotNull('comment')
            ->orderByDesc('id')
            ->first(['phrase_index', 'metrics']);

        $metrics = (array) ($previous?->metrics ?? []);

        $sameSet = $previous !== null
            && ($metrics['language'] ?? null) === $variant['language']
            && ($metrics['tone'] ?? null) === $variant['tone']
            && (int) ($metrics['phrase_level'] ?? -1) === $variant['level'];

        $choices = array_values(array_diff(
            range(0, $count - 1),
            $sameSet ? [(int) $previous->phrase_index] : [],
        ));

        return $choices[array_rand($choices)];
    }

    private function render(
        string $template,
        TelegramClientCheck $last,
        OperationUser $person,
        string $language,
    ): string {
        $parsed = (array) ($last->parsed ?? []);

        $e = static fn (?string $v): string => htmlspecialchars(
            (string) $v,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );

        $name = trim((string) $person->name);

        return strtr($template, [
            '{name}' => $e($name !== '' ? $name : ($language === OperationUser::LANGUAGE_UZ ? 'Hamkasb' : 'Коллега')),
            '{request}' => $e($last->request_number ?? '—'),
            '{repeat_number}' => (string) $last->repeat_number,
            '{status_limit}' => $e(self::duration($parsed['status_limit'] ?? null, $language)),
            '{time_in_status}' => $e(self::duration($parsed['time_in_status'] ?? null, $language)),
            '{crm_status}' => $e($last->crm_status ?? '—'),
        ]);
    }

    /**
     * The bot writes durations in Russian ("2 ч 3 мин"); an Uzbek phrase
     * gets them in Uzbek ("2 soat 3 daqiqa").
     */
    public static function duration(?string $value, string $language): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        if ($language !== OperationUser::LANGUAGE_UZ) {
            return $value;
        }

        return (string) preg_replace_callback(
            '/(\d+)\s*(дн(?:ей|я)?|д|час(?:а|ов)?|ч|мин(?:ут[аы]?)?|сек(?:унд[аы]?)?)\.?/u',
            static function (array $m): string {
                $unit = mb_substr($m[2], 0, 1);

                return $m[1] . ' ' . match (true) {
                    $unit === 'д' => 'kun',
                    $unit === 'ч' => 'soat',
                    str_starts_with($m[2], 'мин') => 'daqiqa',
                    default => 'soniya',
                };
            },
            $value,
        );
    }
}
