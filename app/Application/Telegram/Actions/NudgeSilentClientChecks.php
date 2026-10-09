<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\AutoReplyDelivery;
use App\Application\Telegram\Services\AutoReplyRules;
use App\Application\Telegram\Services\AutoReplyStore;
use App\Application\Telegram\Services\ClientCheckEscalation;
use App\Application\Telegram\Services\ClientCheckRules;
use App\Application\Telegram\Services\PersonalAnswers;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use App\Models\Telegram\TelegramSetting;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Someone who ignores the penalties: after `silence.after_penalties` of
 * them in a row with not a word back, a nudge from the auto replies file -
 * what was done by hand on 2026-10-06 ("Рушана апа, илтимос").
 *
 * Counted from their last private message, their last nudge, or
 * COUNT_DAYS back, whichever is latest; so after a nudge it takes as many
 * penalties again for the next one. It goes a little after the comment of
 * the penalty that made the count (DELAY_MINUTES), not glued to it.
 *
 * Runs on the listener's cron, after the comments. Not outside the
 * penalties' working hours, and only for a fresh comment: a backlog after
 * a restart is not nudged.
 */
final class NudgeSilentClientChecks
{
    /**
     * Between the comment and the nudge: a quick "ok" still spares it.
     */
    private const DELAY_MINUTES = 2;

    /**
     * How late a nudge may still go out, past its time.
     */
    private const GRACE_MINUTES = 30;

    /**
     * Older penalties do not count: silence a week ago is not today's.
     */
    private const COUNT_DAYS = 7;

    public function __construct(
        private readonly AutoReplyStore $store,
        private readonly ClientCheckEscalation $escalation,
        private readonly AutoReplyDelivery $delivery,
        private readonly PersonalAnswers $personal,
    ) {
    }

    public function execute(SimpleEventHandler $telegram): void
    {
        $rules = $this->store->current();

        if (
            ! $rules->enabled
            || ! $rules->silence['enabled']
            || ! TelegramSetting::clientChecksEnabled()
            || ! $this->escalation->rules()->withinWorkingHours()
        ) {
            return;
        }

        $due = now()->subMinutes(self::DELAY_MINUTES);

        /*
         * Each person's latest comment, if it is fresh: the one a nudge
         * would follow.
         */
        $checks = TelegramClientCheck::query()
            ->with('operationUser')
            ->whereNotNull('operation_user_id')
            ->whereNotNull('comment')
            ->whereNotNull('peer')
            ->whereNull('nudged_at')
            ->whereBetween('sent_at', [$due->copy()->subMinutes(self::GRACE_MINUTES), $due])
            ->whereNotExists(function (QueryBuilder $later): void {
                $later->selectRaw('1')
                    ->from('telegram_client_checks as later')
                    ->whereColumn('later.operation_user_id', 'telegram_client_checks.operation_user_id')
                    ->whereNotNull('later.comment')
                    ->whereColumn('later.sent_at', '>', 'telegram_client_checks.sent_at');
            })
            ->orderBy('id')
            ->limit(20)
            ->get();

        foreach ($checks as $check) {
            $person = $check->operationUser;

            if (! $person instanceof OperationUser) {
                continue;
            }

            if ($this->ignored($person, $check) >= $rules->silence['after_penalties']) {
                $this->nudge($telegram, $check, $person, $rules);
            }
        }
    }

    /**
     * Penalties forwarded to them since they last wrote or were nudged, up
     * to and including $check.
     */
    private function ignored(OperationUser $person, TelegramClientCheck $check): int
    {
        $since = collect([
            now()->subDays(self::COUNT_DAYS),
            $person->last_private_message_at,
            TelegramClientCheck::query()->where('operation_user_id', $person->id)->max('nudged_at'),
            TelegramClientCheck::query()->where('operation_user_id', $person->id)->max('replied_at'),
        ])
            ->filter()
            ->map(fn (mixed $at): Carbon => Carbon::parse($at))
            ->max();

        return TelegramClientCheck::query()
            ->where('operation_user_id', $person->id)
            ->whereNotNull('forwarded_at')
            ->where('forwarded_at', '>', $since)
            ->where('forwarded_at', '<=', $check->forwarded_at ?? $check->sent_at)
            ->count();
    }

    private function nudge(
        SimpleEventHandler $telegram,
        TelegramClientCheck $check,
        OperationUser $person,
        AutoReplyRules $rules,
    ): void {
        /*
         * Set first: whatever happens below, this penalty is not nudged
         * again, and the count starts over.
         */
        $check->update(['nudged_at' => now()]);

        if (! $person->dm_enabled) {
            return;
        }

        $choices = $this->personal->merge(
            $rules->choices(
                'silence',
                $person->messageLanguage(),
                $person->respectful ? ClientCheckRules::TONE_RESPECTFUL : ClientCheckRules::TONE_PLAIN,
            ),
            $person,
            PersonalAnswers::SILENCE,
        );

        if ($choices['items'] === []) {
            return;
        }

        $peer = (string) $check->peer;

        try {
            $text = $this->delivery->send(
                $telegram,
                preg_match('/^-?\d+$/', $peer) === 1 ? (int) $peer : $peer,
                $choices,
                $check,
                $person,
            );
        } catch (Throwable $e) {
            Log::warning(
                'Client check nudge was not sent',
                [
                    'check_id' => $check->id,
                    'operation_user_id' => $person->id,
                    'error' => $e->getMessage(),
                ],
            );

            return;
        }

        $check->update(['nudge_text' => $text]);

        Log::info(
            'Client check nudged',
            [
                'check_id' => $check->id,
                'operation_user_id' => $person->id,
            ],
        );
    }
}
