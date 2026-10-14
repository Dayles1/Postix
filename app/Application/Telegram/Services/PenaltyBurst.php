<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Enums\Telegram\TelegramClientCheckStatus;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use App\Models\Telegram\TelegramSetting;
use danog\MadelineProto\SimpleEventHandler;
use Throwable;

use function Amp\delay;

/**
 * A second wave of penalties, said like a person: the penalty, "это тоже",
 * the next penalty, "и это", ... and, when there were three or more, "вот
 * все эти" after the last one - rather than the penalties first and the
 * comments after them all.
 *
 * Only while the person got this level's comment a moment ago (the same
 * condition as PenaltyFollowUp); the first wave keeps its one comment after
 * the burst (ClientCheckSender::flush). Called after each forward, with the
 * person's penalties taken one at a time, so the order in the chat holds.
 */
final class PenaltyBurst
{
    /**
     * Penalties of one burst are at most this many seconds apart.
     */
    private const GAP_SECONDS = 30;

    private const CHAIN_LIMIT = 20;

    /**
     * From this many penalties the burst closes with "вот все эти".
     */
    private const MANY = 3;

    public function __construct(
        private readonly AutoReplyStore $store,
        private readonly ClientCheckEscalation $escalation,
    ) {
    }

    /**
     * Whether the comment for this forwarded penalty went right away; false
     * leaves it to flush().
     */
    public function after(
        SimpleEventHandler $telegram,
        int|string $peer,
        TelegramClientCheck $check,
        OperationUser $person,
    ): bool {
        $rules = $this->store->current();
        $followUp = $rules->followUp;

        if (
            ! $followUp['enabled']
            || ! TelegramSetting::clientCheckCommentsEnabled()
            || ! $person->allows('penalty_comments_enabled')
        ) {
            return false;
        }

        $checkRules = $this->escalation->rules();
        $role = $person->roleOrDefault();
        $level = $checkRules->levelFor($role, max(1, (int) $check->repeat_number));

        if ($checkRules->mode($role, $level) !== ClientCheckRules::MODE_ALL) {
            return false;
        }

        $previous = TelegramClientCheck::query()
            ->where('operation_user_id', $person->id)
            ->where('id', '!=', $check->id)
            ->whereNotNull('comment')
            ->where('sent_at', '>=', now()->subMinutes($followUp['window_minutes']))
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first(['id', 'comment', 'comment_level']);

        if ($previous === null || (int) $previous->comment_level !== $level) {
            return false;
        }

        /*
         * A moment, "typing…" - and the bot's next penalty of this person
         * may be recorded meanwhile: is this one the last of the burst?
         */
        $this->pause($telegram, $peer);

        $last = ! TelegramClientCheck::query()
            ->where('operation_user_id', $person->id)
            ->where('id', '>', $check->id)
            ->where('status', '!=', TelegramClientCheckStatus::Skipped)
            ->exists();

        $count = $this->burstSize($check, $person);

        $texts = $rules->followUpAnswers(
            $last && $count >= self::MANY,
            $person->messageLanguage(),
            $person->respectful ? ClientCheckRules::TONE_RESPECTFUL : ClientCheckRules::TONE_PLAIN,
        );

        if ($texts['answers'] === []) {
            return false;
        }

        $rendered = array_map(
            fn (string $text): string => $this->escalation->render($text, $check, $person, $texts['language']),
            $texts['answers'],
        );

        $fresh = array_values(array_diff($rendered, [(string) $previous->comment]));
        $choices = $fresh !== [] ? $fresh : $rendered;
        $comment = $choices[array_rand($choices)];

        try {
            $telegram->messages->sendMessage([
                'peer' => $peer,
                'message' => $comment,
                'parse_mode' => 'html',
                'no_webpage' => true,
            ]);
        } catch (Throwable) {
            return false;
        }

        $check->update([
            'status' => TelegramClientCheckStatus::Sent,
            'reason' => null,
            'error' => null,
            'sent_at' => now(),
            'phrase_index' => null,
            'comment_level' => $level,
            'comment' => $comment,
            'batch_count' => $count,
            'metrics' => ['follow_up' => true, 'burst' => true, 'language' => $texts['language'], 'tone' => $texts['tone']],
        ]);

        return true;
    }

    /**
     * This penalty and the ones just before it that are one burst with it.
     */
    private function burstSize(TelegramClientCheck $check, OperationUser $person): int
    {
        $earlier = TelegramClientCheck::query()
            ->where('operation_user_id', $person->id)
            ->where('id', '<', $check->id)
            ->orderByDesc('id')
            ->limit(self::CHAIN_LIMIT)
            ->pluck('created_at');

        $count = 1;
        $edge = $check->created_at;

        foreach ($earlier as $createdAt) {
            if ($edge === null || $createdAt === null || $edge->diffInSeconds($createdAt, true) > self::GAP_SECONDS) {
                break;
            }

            $count++;
            $edge = $createdAt;
        }

        return $count;
    }

    private function pause(SimpleEventHandler $telegram, int|string $peer): void
    {
        $min = max(0.0, (float) config('auto_replies.burst_pause.min', 0));
        $max = max($min, (float) config('auto_replies.burst_pause.max', $min));

        if ($max <= 0) {
            return;
        }

        try {
            $telegram->messages->setTyping([
                'peer' => $peer,
                'action' => ['_' => 'sendMessageTypingAction'],
            ]);
        } catch (Throwable) {
            /*
             * Only a nicety.
             */
        }

        delay($min + (mt_rand() / mt_getrandmax()) * ($max - $min));
    }
}
