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
use danog\MadelineProto\Loop\Update\UpdateLoop;
use danog\MadelineProto\MTProto;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelegramDriverCheckHandler extends SimpleEventHandler
{
    /**
     * Health probe interval, seconds.
     */
    private const HEALTH_CHECK_PERIOD = 15.0;

    /**
     * Consecutive failed probes before the process asks to be restarted.
     *
     * MadelineProto legitimately stops the update loops for a moment during a
     * DC migration or a re-login, so a single failed probe must not trigger a
     * restart.
     */
    private const HEALTH_CHECK_STRIKES = 3;

    private ?int $targetChatId = null;

    private int $unhealthyStrikes = 0;

    private bool $restartRequested = false;

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

/**
 * Liveness probe for MadelineProto's update pipeline.
 *
 * Runs on its own PeriodicLoop, independently of the update loops, so it stays
 * alive exactly in the situation we need to detect: MadelineProto 8.7 catches
 * Amp\TimeoutException in UpdateLoop::loop() but the RPC drop timeout actually
 * throws Amp\CancelledException (with the TimeoutException as previous), so it
 * escapes, danog\Loop\Loop marks the loop as not running and the library's own
 * event loop error handler swallows it. The process then stays alive forever
 * without ever fetching updates.getDifference again.
 *
 * A dead pipeline is unrecoverable inside the same process, so the only
 * correct action is to stop the event loop and let the watchdog start a new
 * one.
 */
#[Cron(period: self::HEALTH_CHECK_PERIOD)]
public function healthCheck(): void
{
    if ($this->restartRequested) {
        return;
    }

    try {
        $problem = $this->detectProblem();

        if ($problem === null) {
            $this->unhealthyStrikes = 0;

            return;
        }

        $this->unhealthyStrikes++;

        Log::warning(
            'TelegramDriverCheckHandler: health probe failed',
            [
                'pid' => getmypid(),
                'reason' => $problem,
                'strikes' => $this->unhealthyStrikes,
                'strikes_required' => self::HEALTH_CHECK_STRIKES,
            ],
        );

        if ($this->unhealthyStrikes < self::HEALTH_CHECK_STRIKES) {
            return;
        }

        $this->requestProcessRestart($problem);
    } catch (Throwable $e) {
        Log::error(
            'TelegramDriverCheckHandler: health probe error',
            [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ],
        );
    }
}

/**
 * Returns a health marker reason, or null when everything is fine.
 */
private function detectProblem(): ?string
{
    if ($this->targetChatId === null) {
        /*
         * onStart() could not resolve the target chat: the listener is up but
         * silently ignores every message. Restarting is the only way out.
         */
        return TelegramListenerHealth::REASON_START_FAILED;
    }

    $api = $this->wrapper->getAPI();

    if (!$api instanceof MTProto) {
        /*
         * IPC client: the update loops live in another process, nothing to
         * probe from here.
         */
        return null;
    }

    $updater = $api->updaters[UpdateLoop::GENERIC] ?? null;

    if ($updater === null) {
        // Not initialised yet.
        return null;
    }

    if (!$updater->isRunning()) {
        return TelegramListenerHealth::REASON_UPDATE_LOOP_DEAD;
    }

    $feeder = $api->feeders[UpdateLoop::GENERIC] ?? null;

    if ($feeder !== null && !$feeder->isRunning()) {
        return TelegramListenerHealth::REASON_UPDATE_LOOP_DEAD;
    }

    return null;
}

/**
 * Ask for a full process restart: write the reason where the command can read
 * it, then stop the event loop gracefully so MadelineProto serializes the
 * session on the way out.
 */
private function requestProcessRestart(string $reason): void
{
    $this->restartRequested = true;

    $context = [
        'pid' => getmypid(),
        'target_chat_id' => $this->targetChatId,
        'strikes' => $this->unhealthyStrikes,
        'probe_period' => self::HEALTH_CHECK_PERIOD,
    ];

    TelegramListenerHealth::markUnhealthy($reason, $context);

    Log::critical(
        'TelegramDriverCheckHandler: requesting full process restart',
        $context + ['reason' => $reason],
    );

    try {
        $this->stop();
    } catch (Throwable $e) {
        Log::critical(
            'TelegramDriverCheckHandler: graceful stop failed, forcing exit',
            [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ],
        );

        /*
         * Last resort: the watchdog must get a non-zero exit code. PHP
         * shutdown handlers still run, so MadelineProto flushes the session.
         */
        exit(1);
    }
}
}
