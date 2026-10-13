<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use Illuminate\Support\Collection;

/**
 * "Это тоже": the person got this level's comment a moment ago - "обновите
 * статус" - so the next one is a short follow-up rather than the same
 * phrase again, as a person would write (auto replies file, follow_up).
 * "Вот эти тоже не забудьте" for a burst.
 *
 * A follow-up counts as that level's comment too: "это тоже", then "и
 * это" keep going while the penalties keep coming within the window.
 */
final class PenaltyFollowUp
{
    public function __construct(
        private readonly AutoReplyStore $store,
        private readonly ClientCheckEscalation $escalation,
    ) {
    }

    /**
     * The follow-up for this batch, stored on the penalty it speaks for,
     * or null when the full comment should go.
     *
     * @param Collection<int, TelegramClientCheck> $batch oldest first
     */
    public function comment(Collection $batch, OperationUser $person): ?string
    {
        $followUp = $this->store->current()->followUp;

        if (! $followUp['enabled']) {
            return null;
        }

        $last = $this->escalation->strongest($batch, $person);

        if ($last === null) {
            return null;
        }

        $level = $this->escalation->rules()->levelFor(
            $person->roleOrDefault(),
            max(1, (int) $last->repeat_number),
        );

        $previous = TelegramClientCheck::query()
            ->where('operation_user_id', $person->id)
            ->whereNotIn('id', $batch->pluck('id')->all())
            ->whereNotNull('comment')
            ->where('sent_at', '>=', now()->subMinutes($followUp['window_minutes']))
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first(['id', 'comment', 'comment_level']);

        /*
         * Nothing lately, or something else was said: the full phrase.
         */
        if ($previous === null || (int) $previous->comment_level !== $level) {
            return null;
        }

        $texts = $this->store->current()->followUpAnswers(
            $batch->count() > 1,
            $person->messageLanguage(),
            $person->respectful ? ClientCheckRules::TONE_RESPECTFUL : ClientCheckRules::TONE_PLAIN,
        );

        if ($texts['answers'] === []) {
            return null;
        }

        $rendered = array_map(
            fn (string $text): string => $this->escalation->render($text, $last, $person, $texts['language']),
            $texts['answers'],
        );

        /*
         * Not "это тоже" right after "это тоже".
         */
        $fresh = array_values(array_diff($rendered, [(string) $previous->comment]));

        $choices = $fresh !== [] ? $fresh : $rendered;

        $comment = $choices[array_rand($choices)];

        $last->update([
            'phrase_index' => null,
            'comment_level' => $level,
            'comment' => $comment,
            'batch_count' => $batch->count(),
            'metrics' => ['follow_up' => true, 'language' => $texts['language'], 'tone' => $texts['tone']],
        ]);

        return $comment;
    }
}
