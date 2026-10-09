<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use danog\MadelineProto\SimpleEventHandler;

/**
 * Sends one of AutoReplyRules::choices() at random - a text, a GIF or a
 * voice message - for an answer (ProcessAutoReply), a nudge
 * (NudgeSilentClientChecks) or a personal penalty comment
 * (ClientCheckSender).
 */
final class AutoReplyDelivery
{
    public function __construct(
        private readonly ClientCheckEscalation $escalation,
        private readonly AutoReplyMedia $media,
    ) {
    }

    /**
     * What was sent, as the journal keeps it: the text with its
     * placeholders filled in, "GIF · name" or "🎤 name". Throws when
     * Telegram refuses.
     *
     * @param array{language: string, items: list<array<string, mixed>>} $choices non-empty items
     */
    public function send(
        SimpleEventHandler $telegram,
        int|string $peer,
        array $choices,
        TelegramClientCheck $check,
        OperationUser $person,
        ?int $replyTo = null,
    ): string {
        $item = $choices['items'][array_rand($choices['items'])];

        if ($item['type'] !== 'text') {
            $this->media->send($telegram, $peer, $item, $replyTo);

            return ($item['type'] === AutoReplyRules::MEDIA_VOICE ? '🎤 ' : 'GIF · ') . $item['name'];
        }

        /*
         * A personal text (PersonalAnswers) is in the person's language,
         * whatever set the shared ones fell back to.
         */
        $text = $this->escalation->render($item['text'], $check, $person, $item['language'] ?? $choices['language']);

        $telegram->messages->sendMessage([
            'peer' => $peer,
            'message' => $text,
            'parse_mode' => 'html',
            'no_webpage' => true,
            ...($replyTo !== null ? ['reply_to' => [
                '_' => 'inputReplyToMessage',
                'reply_to_msg_id' => $replyTo,
            ]] : []),
        ]);

        return $text;
    }
}
