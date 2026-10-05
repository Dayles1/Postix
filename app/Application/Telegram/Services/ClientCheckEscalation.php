<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How hard to lean on someone for a penalty (ClientCheckRules, edited in
 * the panel).
 *
 * Two signals, the higher wins: the bot's own repeat number, and the
 * person's history - how many penalties in the last hour, today and this
 * week, and how soon after the previous batch. No counters are stored: the
 * history is the state, so it cools down by itself.
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
        $rules = $this->rules();

        $metrics = $this->metrics($check, $person, $rules);

        $check->update([
            'level' => max(
                $rules->repeatLevel($check->repeat_number),
                $rules->historyLevel($metrics),
            ),
            'metrics' => $metrics,
        ]);
    }

    /**
     * The one comment for a batch, chosen and stored on its last penalty.
     *
     * @param Collection<int, TelegramClientCheck> $batch oldest first
     */
    public function comment(Collection $batch, OperationUser $person): ?string
    {
        /** @var TelegramClientCheck $last */
        $last = $batch->last();

        $rules = $this->rules();

        /*
         * Levels may have been removed since these penalties came in.
         */
        $level = min((int) $batch->max('level'), $rules->topLevel());

        $phrases = $rules->phrases($level);

        if ($phrases === []) {
            return null;
        }

        $index = $this->pick($last, $person, $level, count($phrases));

        $comment = $this->render($phrases[$index], $last, $person, $batch->count());

        if ($batch->count() > 1) {
            $line = $rules->batchLine;

            if ($line !== '') {
                $comment .= "\n\n" . $this->render($line, $last, $person, $batch->count());
            }
        }

        $last->update([
            'phrase_index' => $index,
            'comment_level' => $level,
            'comment' => $comment,
            'batch_count' => $batch->count(),
        ]);

        return $comment;
    }

    /**
     * Counts include the penalty itself.
     *
     * @return array{hour_count: int, today_count: int, week_count: int, minutes_since_last: int|null}
     */
    public function metrics(
        TelegramClientCheck $check,
        OperationUser $person,
        ?ClientCheckRules $rules = null,
    ): array {
        $rules ??= $this->rules();

        $at = $check->created_at ?? now();

        $days = $rules->historyDays;

        /** @var list<Carbon> $history newest first */
        $history = TelegramClientCheck::query()
            ->where('operation_user_id', $person->id)
            ->where('id', '<=', $check->id)
            ->where('created_at', '>=', $at->copy()->subDays($days))
            ->orderByDesc('id')
            ->pluck('created_at')
            ->all();

        $hourFrom = $at->copy()->subHour();
        $todayFrom = $at->copy()->startOfDay();

        /*
         * "How soon did it happen again" is measured against the previous
         * batch: penalties the bot sent in the same burst are one event.
         */
        $batchFrom = $at->copy()->subSeconds($rules->batchQuietSeconds);

        $hour = 0;
        $today = 0;
        $previous = null;

        foreach ($history as $createdAt) {
            if ($createdAt >= $hourFrom) {
                $hour++;
            }

            if ($createdAt >= $todayFrom) {
                $today++;
            }

            if ($previous === null && $createdAt < $batchFrom) {
                $previous = $createdAt;
            }
        }

        return [
            'hour_count' => $hour,
            'today_count' => $today,
            'week_count' => count($history),
            'minutes_since_last' => $previous !== null
                ? (int) $previous->diffInMinutes($at, true)
                : null,
        ];
    }

    public function repeatLevel(int $repeat): int
    {
        return $this->rules()->repeatLevel($repeat);
    }

    /**
     * @param array{hour_count: int, today_count: int, week_count: int, minutes_since_last: int|null} $metrics
     */
    public function historyLevel(array $metrics): int
    {
        return $this->rules()->historyLevel($metrics);
    }

    public function quietSeconds(): int
    {
        return $this->rules()->batchQuietSeconds;
    }

    /**
     * Random, but not the phrase this person got last time on this level.
     */
    private function pick(
        TelegramClientCheck $last,
        OperationUser $person,
        int $level,
        int $count,
    ): int {
        if ($count === 1) {
            return 0;
        }

        $previous = TelegramClientCheck::query()
            ->where('operation_user_id', $person->id)
            ->where('id', '<', $last->id)
            ->whereNotNull('phrase_index')
            ->whereNotNull('comment')
            ->orderByDesc('id')
            ->first(['comment_level', 'phrase_index']);

        $exclude = $previous !== null && (int) $previous->comment_level === $level
            ? [(int) $previous->phrase_index]
            : [];

        $choices = array_values(array_diff(range(0, $count - 1), $exclude));

        return $choices[array_rand($choices)];
    }

    private function render(
        string $template,
        TelegramClientCheck $last,
        OperationUser $person,
        int $batchCount,
    ): string {
        $metrics = (array) ($last->metrics ?? []);

        $e = static fn (?string $v): string => htmlspecialchars(
            (string) $v,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );

        $name = trim((string) $person->name);

        return strtr($template, [
            '{name}' => $e($name !== '' ? $name : 'Коллега'),
            '{request}' => $e($last->request_number ?? '—'),
            '{repeat_number}' => (string) $last->repeat_number,
            '{batch_count}' => (string) $batchCount,
            '{hour_count}' => (string) ($metrics['hour_count'] ?? 0),
            '{today_count}' => (string) ($metrics['today_count'] ?? 0),
            '{week_count}' => (string) ($metrics['week_count'] ?? 0),
        ]);
    }
}
