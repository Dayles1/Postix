<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\AutoReplyDelivery;
use App\Application\Telegram\Services\AutoReplyMatcher;
use App\Application\Telegram\Services\AutoReplyStore;
use App\Application\Telegram\Services\ClientCheckRules;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A new private message from an operator or a sales manager: "+", "ok",
 * "хоп".
 *
 * Read as one of the kinds of the auto replies file (AutoReplyMatcher) and,
 * when it is one, answered with that kind's answer - a text in the person's
 * language and tone, calling them as their card says ("Rahmat, Ali aka"),
 * or one of the kind's GIFs or voice messages. Strangers are not answered.
 *
 * Any message of theirs is noted on the card (last_private_message_at):
 * the nudge counts the penalties nobody answered since.
 *
 * A message soon after a penalty is that penalty's reply: kept on it for
 * the journal, matched or not, and answered once. Any other message is
 * answered too, unless the file says "only after a penalty", at most once
 * per cooldown.
 */
final class ProcessAutoReply
{
    private const TEXT_LIMIT = 2000;

    public function __construct(
        private readonly AutoReplyStore $store,
        private readonly AutoReplyMatcher $matcher,
        private readonly AutoReplyDelivery $delivery,
    ) {
    }

    /**
     * The answer sent, or null when none was.
     */
    public function execute(
        SimpleEventHandler $telegram,
        int $senderId,
        int $messageId,
        string $text,
    ): ?string {
        $text = trim($text);

        if ($senderId <= 0 || $text === '') {
            return null;
        }

        $rules = $this->store->current();

        if (! $rules->enabled) {
            return null;
        }

        $person = $this->person($telegram, $senderId);

        if ($person === null) {
            return null;
        }

        $person->update(['last_private_message_at' => now()]);

        $check = TelegramClientCheck::query()
            ->where('operation_user_id', $person->id)
            ->whereNotNull('forwarded_at')
            ->where('forwarded_at', '>=', now()->subMinutes($rules->penaltyWindowMinutes))
            ->orderByDesc('forwarded_at')
            ->orderByDesc('id')
            ->first();

        /*
         * A penalty still waiting for its answer: this message is its
         * reply. Answered already, the chat goes on as any other.
         */
        $open = $check !== null && $check->reply_answered_at === null;

        $kind = $this->matcher->match($rules, $text);

        if ($open) {
            $check->update([
                'reply_text' => mb_substr($text, 0, self::TEXT_LIMIT),
                'replied_at' => now(),
                'reply_kind' => $kind !== null ? $rules->name($kind) : null,
            ]);
        }

        Log::info(
            'Auto reply: private message',
            [
                'operation_user_id' => $person->id,
                'check_id' => $open ? $check->id : null,
                'kind' => $kind !== null ? $rules->name($kind) : null,
            ],
        );

        /*
         * Muted on the card: nothing is written to them, an answer neither.
         */
        if ($kind === null || ! $person->dm_enabled) {
            return null;
        }

        if (! $open && ($rules->onlyAfterPenalty || Cache::has($this->cooldownKey($person)))) {
            return null;
        }

        $choices = $rules->choices(
            $kind,
            $person->messageLanguage(),
            $person->respectful ? ClientCheckRules::TONE_RESPECTFUL : ClientCheckRules::TONE_PLAIN,
        );

        if ($choices['items'] === []) {
            return null;
        }

        try {
            /*
             * Without a penalty, {request} reads "—".
             */
            $answer = $this->delivery->send(
                $telegram,
                $senderId,
                $choices,
                $check ?? new TelegramClientCheck(),
                $person,
                replyTo: $messageId,
            );
        } catch (Throwable $e) {
            /*
             * Left unanswered: their next message gets another go.
             */
            Log::warning(
                'Auto reply was not sent',
                [
                    'operation_user_id' => $person->id,
                    'error' => $e->getMessage(),
                ],
            );

            return null;
        }

        if ($rules->cooldownMinutes > 0) {
            Cache::put($this->cooldownKey($person), true, now()->addMinutes($rules->cooldownMinutes));
        }

        if ($open) {
            $check->update([
                'reply_answer' => $answer,
                'reply_answered_at' => now(),
            ]);
        }

        return $answer;
    }

    /**
     * By Telegram id; someone stored by username only is found by it once
     * and gets the id written down, so the next message skips the lookup.
     */
    private function person(SimpleEventHandler $telegram, int $senderId): ?OperationUser
    {
        $person = OperationUser::query()->where('telegram_id', $senderId)->first();

        if ($person !== null) {
            return $person;
        }

        try {
            $info = $telegram->getInfo($senderId);
        } catch (Throwable) {
            return null;
        }

        $username = is_array($info) ? ($info['User']['username'] ?? null) : null;

        if (! is_string($username) || $username === '') {
            return null;
        }

        $person = OperationUser::query()
            ->whereNull('telegram_id')
            ->whereRaw('LOWER(telegram_username) = ?', [mb_strtolower($username)])
            ->first();

        $person?->update(['telegram_id' => $senderId]);

        return $person;
    }

    private function cooldownKey(OperationUser $person): string
    {
        return 'auto-replies:cooldown:' . $person->id;
    }
}
