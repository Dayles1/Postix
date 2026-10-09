<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\AutoReplyDelivery;
use App\Application\Telegram\Services\AutoReplyMatcher;
use App\Application\Telegram\Services\AutoReplyRules;
use App\Application\Telegram\Services\AutoReplyStore;
use App\Application\Telegram\Services\ClientCheckRules;
use App\Application\Telegram\Services\PersonalAnswers;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Amp\delay;

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
        private readonly PersonalAnswers $personal,
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

        /*
         * "Доброе утро. Готово": the greeting is answered on its own, the
         * rest is read as any message. Once a day per person.
         */
        $greeting = $rules->greetings['enabled']
            ? $this->matcher->greeting($rules->greetings['list'], $rules->greetings['fillers'], $text)
            : null;

        $kind = $this->matcher->match($rules, $greeting['rest'] ?? $text);

        $greet = $greeting !== null
            && ($kind !== null || $greeting['alone'])
            && ! Cache::has($this->greetedKey($person));

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
        if (($kind === null && ! $greet) || ! $person->dm_enabled) {
            return null;
        }

        if (! $open && ($rules->onlyAfterPenalty || Cache::has($this->cooldownKey($person)))) {
            return null;
        }

        $language = $person->messageLanguage();
        $tone = $person->respectful ? ClientCheckRules::TONE_RESPECTFUL : ClientCheckRules::TONE_PLAIN;

        /*
         * With the person's own answers to this kind (PersonalAnswers).
         */
        $choices = $kind !== null
            ? $this->personal->merge(
                $rules->choices($kind, $language, $tone),
                $person,
                PersonalAnswers::replySlot($rules->replies[$kind]['id']),
            )
            : ['language' => $language, 'items' => []];

        $hello = $greet ? $this->hello($rules, (int) $greeting['index'], $language, $tone, $person) : null;

        if ($choices['items'] === [] && $hello === null) {
            return null;
        }

        /*
         * One answer on its way per person: a second "+" written during
         * the wait gets none of its own.
         */
        if (! Cache::add($this->pendingKey($person), true, now()->addMinute())) {
            return null;
        }

        /*
         * Picked here rather than by the delivery: the wait shows what is
         * coming - "typing…" for a text, "recording…" for a voice.
         */
        $item = $choices['items'] !== [] ? $choices['items'][array_rand($choices['items'])] : null;

        try {
            $sent = [];

            if ($hello !== null) {
                $this->wait($telegram, $senderId, $hello['items'][0]['type']);

                $sent[] = $this->delivery->send(
                    $telegram,
                    $senderId,
                    $hello,
                    $check ?? new TelegramClientCheck(),
                    $person,
                    replyTo: $messageId,
                );

                Cache::put($this->greetedKey($person), true, now()->endOfDay());
            }

            if ($item !== null) {
                /*
                 * After a greeting the thanks follows sooner, as its own
                 * message rather than a second reply to theirs.
                 */
                $this->wait($telegram, $senderId, $item['type'], $hello !== null ? 'greeting_pause' : 'reply_delay');

                /*
                 * Without a penalty, {request} reads "—".
                 */
                $sent[] = $this->delivery->send(
                    $telegram,
                    $senderId,
                    [...$choices, 'items' => [$item]],
                    $check ?? new TelegramClientCheck(),
                    $person,
                    replyTo: $hello === null ? $messageId : null,
                );
            }

            $answer = implode("\n", $sent);
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
        } finally {
            Cache::forget($this->pendingKey($person));
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

    private function pendingKey(OperationUser $person): string
    {
        return 'auto-replies:pending:' . $person->id;
    }

    private function greetedKey(OperationUser $person): string
    {
        return 'auto-replies:greeted:' . $person->id;
    }

    /**
     * The greeting's answer in the person's language and tone, as the
     * delivery takes it - one of the shared texts or of the person's own
     * answers to it (a voice saying "Доброе утро, Ali aka", say); null when
     * there is none.
     *
     * @return array{language: string, items: list<array<string, mixed>>}|null
     */
    private function hello(AutoReplyRules $rules, int $index, string $language, string $tone, OperationUser $person): ?array
    {
        $texts = $rules->greetingAnswers($index, $language, $tone);

        $choices = $this->personal->merge(
            [
                'language' => $texts['language'],
                'items' => array_map(static fn (string $text): array => ['type' => 'text', 'text' => $text], $texts['answers']),
            ],
            $person,
            PersonalAnswers::greetingSlot($rules->greetings['list'][$index]['id']),
        );

        if ($choices['items'] === []) {
            return null;
        }

        return [...$choices, 'items' => [$choices['items'][array_rand($choices['items'])]]];
    }

    /**
     * A few seconds (auto_replies.reply_delay, or $delay) with "typing…"
     * on, as a person would take. The delay only suspends this message's
     * fiber: the listener goes on with the others meanwhile.
     */
    private function wait(SimpleEventHandler $telegram, int $peer, string $type, string $delay = 'reply_delay'): void
    {
        $min = max(0.0, (float) config("auto_replies.{$delay}.min", 0));
        $max = max($min, (float) config("auto_replies.{$delay}.max", $min));

        if ($max <= 0) {
            return;
        }

        try {
            $telegram->messages->setTyping([
                'peer' => $peer,
                'action' => ['_' => match ($type) {
                    AutoReplyRules::MEDIA_VOICE => 'sendMessageRecordAudioAction',
                    AutoReplyRules::MEDIA_GIF => 'sendMessageChooseStickerAction',
                    default => 'sendMessageTypingAction',
                }],
            ]);
        } catch (Throwable) {
            /*
             * Only a nicety: the answer goes without it.
             */
        }

        delay($min + (mt_rand() / mt_getrandmax()) * ($max - $min));
    }
}
