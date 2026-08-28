<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Application\Telegram\Actions\NotifyTelegramResolverExhaustion;
use App\Application\Telegram\Actions\ProcessCreatedDriverMessage;
use App\Application\Telegram\Actions\ProcessTelegramDriverCheckResults;
use App\Application\Telegram\Actions\TelegramDriverCheckStarter;
use App\Application\Telegram\Services\TelegramDriverCheckRecorder;
use App\Application\Telegram\Services\TelegramMessageTypeDetector;
use App\Enums\Drivers\TelegramDriverMessageType;
use danog\MadelineProto\EventHandler\Attributes\Cron;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Message as TelegramIncomingMessage;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramDriverCheckHandler extends SimpleEventHandler
{
    private ?int $targetChatId = null;

    public function onStart(): void
{
    Log::info('TelegramDriverCheckHandler: onStart');

    try {
        $this->targetChatId = app(
            TelegramDriverCheckStarter::class,
        )->execute($this);

        Log::info(
            'TelegramDriverCheckHandler: started',
            [
                'target_chat_id' => $this->targetChatId,
            ],
        );
    } catch (Throwable $e) {
        Log::error(
            'TelegramDriverCheckHandler: onStart failed',
            [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ],
        );

        throw $e;
    }
}

#[Handler]
public function handleIncomingMessage(
    Incoming&TelegramIncomingMessage $message,
): void {
    try {
        if ($this->targetChatId === null) {
            return;
        }

        $chatId = $message->chatId ?? null;

        if ($chatId === null) {
            return;
        }

        // Ignore all other chats silently.
        if ((int) $chatId !== $this->targetChatId) {
            return;
        }

        Log::info(
            'TelegramDriverCheckHandler: incoming message',
            [
                'chat_id' => $chatId,
                'message_id' => $message->id ?? null,
                'text' => $message->message ?? null,
            ],
        );

        $messageId = (int) ($message->id ?? 0);

        if ($messageId <= 0) {
            Log::warning(
                'TelegramDriverCheckHandler: invalid message id',
            );

            return;
        }

        $text = trim(
            (string) ($message->message ?? ''),
        );

        if ($text === '') {
            Log::info(
                'TelegramDriverCheckHandler: empty text',
            );

            return;
        }

        $type = app(
            TelegramMessageTypeDetector::class,
        )->detect($text);

        Log::info(
            'TelegramDriverCheckHandler: detected type',
            [
                'type' => $type?->value ?? null,
            ],
        );

        $check = app(
            TelegramDriverCheckRecorder::class,
        )->record(
            message: $message,
            text: $text,
            type: $type,
        );

        

        if ($check === null) {
            return;
        }

        if (
            $type !==
            TelegramDriverMessageType::CREATED_DRIVER
        ) {
            Log::info(
                'TelegramDriverCheckHandler: message ignored by type',
                [
                    'type' => $type?->value ?? null,
                ],
            );

            return;
        }


        app(
            ProcessCreatedDriverMessage::class,
        )->execute(
            check: $check,
            text: $text,
        );

        
    } catch (Throwable $e) {
        Log::error(
            'TelegramDriverCheckHandler: handle failed',
            [
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'chat_id' => $message->chatId ?? null,
                'message_id' => $message->id ?? null,
            ],
        );
    }
}

#[Cron(period: 1.0)]
public function cron(): void
{
    

    try {
        if ($this->targetChatId === null) {
            Log::warning(
                'TelegramDriverCheckHandler: cron targetChatId is null',
            );

            return;
        }

        app(
            ProcessTelegramDriverCheckResults::class,
        )->execute(
            $this,
            $this->targetChatId,
        );

        Log::info(
            'TelegramDriverCheckHandler: ProcessTelegramDriverCheckResults done',
        );

        app(
            NotifyTelegramResolverExhaustion::class,
        )->execute(
            $this,
            $this->targetChatId,
        );

        
    } catch (Throwable $e) {
        Log::error(
            'TelegramDriverCheckHandler: cron failed',
            [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ],
        );
    }
}
}