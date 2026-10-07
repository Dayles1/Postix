<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\AutoReplyStore;
use App\Application\Telegram\Services\ClientCheckEscalation;
use App\Application\Telegram\Services\ClientCheckRules;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use App\Models\Telegram\TelegramSetting;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A penalty nobody answered: once, `silence.after_minutes` after its
 * comment, a nudge from the auto replies file - what was done by hand on
 * 2026-10-06 ("Рушана апа, илтимос").
 *
 * Runs on the listener's cron, after the comments. Not when the person
 * wrote anything since the comment, not when a newer comment went to them
 * (that one is nudged instead), not outside the penalties' working hours,
 * and only for a fresh comment: a backlog after a restart is not nudged.
 */
final class NudgeSilentClientChecks
{
    /**
     * How late a nudge may still go out, past its time.
     */
    private const GRACE_MINUTES = 30;

    public function __construct(
        private readonly AutoReplyStore $store,
        private readonly ClientCheckEscalation $escalation,
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

        $due = now()->subMinutes($rules->silence['after_minutes']);

        $checks = TelegramClientCheck::query()
            ->with('operationUser')
            ->whereNotNull('comment')
            ->whereNotNull('peer')
            ->whereNull('nudged_at')
            ->whereBetween('sent_at', [$due->copy()->subMinutes(self::GRACE_MINUTES), $due])
            ->whereNotExists(function (QueryBuilder $later): void {
                /*
                 * They wrote something since - answered or not, it was not
                 * silence - or a newer comment went to them.
                 */
                $later->selectRaw('1')
                    ->from('telegram_client_checks as later')
                    ->whereColumn('later.operation_user_id', 'telegram_client_checks.operation_user_id')
                    ->where(function (QueryBuilder $q): void {
                        $q->whereColumn('later.replied_at', '>=', 'telegram_client_checks.forwarded_at')
                            ->orWhere(function (QueryBuilder $newer): void {
                                $newer->whereNotNull('later.comment')
                                    ->whereColumn('later.sent_at', '>', 'telegram_client_checks.sent_at');
                            });
                    });
            })
            ->orderBy('id')
            ->limit(20)
            ->get();

        foreach ($checks as $check) {
            $this->nudge($telegram, $check, $rules->silenceAnswers(...));
        }
    }

    /**
     * @param callable(string, string): array{answers: list<string>, language: string, tone: string} $answers
     */
    private function nudge(SimpleEventHandler $telegram, TelegramClientCheck $check, callable $answers): void
    {
        $person = $check->operationUser;

        /*
         * Set first: whatever happens below, this penalty is not nudged
         * again.
         */
        $check->update(['nudged_at' => now()]);

        if (! $person instanceof OperationUser || ! $person->dm_enabled) {
            return;
        }

        $variant = $answers(
            $person->messageLanguage(),
            $person->respectful ? ClientCheckRules::TONE_RESPECTFUL : ClientCheckRules::TONE_PLAIN,
        );

        if ($variant['answers'] === []) {
            return;
        }

        $text = $this->escalation->render(
            $variant['answers'][array_rand($variant['answers'])],
            $check,
            $person,
            $variant['language'],
        );

        $peer = (string) $check->peer;

        try {
            $telegram->messages->sendMessage([
                'peer' => preg_match('/^-?\d+$/', $peer) === 1 ? (int) $peer : $peer,
                'message' => $text,
                'parse_mode' => 'html',
                'no_webpage' => true,
            ]);
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
