<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Enums\Drivers\TelegramDriverMessageType;
use App\Models\Driver\TelegramDriverCheck;
use danog\MadelineProto\EventHandler\Message as TelegramIncomingMessage;
use Illuminate\Database\QueryException;

final class TelegramDriverCheckRecorder
{
    public function record(
        TelegramIncomingMessage $message,
        string $text,
        TelegramDriverMessageType $type,
    ): ?TelegramDriverCheck {
        $chatId = (int) ($message->chatId ?? 0);
        $messageId = (int) ($message->id ?? 0);

        if ($messageId <= 0 || $chatId === 0) {
            return null;
        }

        /*
         * Only driver messages are worth a row. Everything else in the
         * group (transport, chatter) used to be attempted with a null
         * status and bounced off the NOT NULL column - and the unique
         * guard below read that as a duplicate.
         */
        if (
            $type !== TelegramDriverMessageType::CREATED_DRIVER
            && $type !== TelegramDriverMessageType::UPDATED_DRIVER
        ) {
            return null;
        }

        /*
         * Application-level duplicate protection.
         */
        $existing = TelegramDriverCheck::query()
            ->where(
                'telegram_chat_id',
                $chatId,
            )
            ->where(
                'telegram_message_id',
                $messageId,
            )
            ->first();

        if ($existing) {
            return null;
        }

        try {
            return TelegramDriverCheck::query()->create([
                'telegram_chat_id' => $chatId,

                'telegram_message_id' => $messageId,

                'type' => $type,

                'message_text' => $text,

                /*
                 * An update is nothing to check until
                 * ProcessUpdatedDriverMessage finds a phone change in it.
                 */
                'status' => $type === TelegramDriverMessageType::CREATED_DRIVER
                    ? TelegramDriverCheckStatus::Pending
                    : TelegramDriverCheckStatus::Skipped,

                'attempts' => 0,

                'telegram_raw' => [
                    'class' => $message::class,
                    'id' => $message->id ?? null,
                    'chat_id' => $message->chatId ?? null,
                    'sender_id' => $message->senderId ?? null,
                    'message' => $message->message ?? null,
                    'date' => $message->date ?? null,
                    'reply_to_msg_id' => $message->replyToMsgId ?? null,
                    'reply_to_top_id' => $message->replyToTopId ?? null,
                    'message_type' => $type->value,
                ],
            ]);
        } catch (QueryException $e) {
            /*
             * If DB unique constraint caught a duplicate,
             * do not break the Telegram listener.
             */
            if ($this->isDuplicateException($e)) {
                return null;
            }

            throw $e;
        }
    }

    private function isDuplicateException(
        QueryException $e,
    ): bool {
        return $e->getCode() === '23000';
    }
}