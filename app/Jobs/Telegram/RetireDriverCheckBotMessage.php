<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Application\Telegram\Services\DriverCheckBot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Strips the buttons off a bot message whose check has moved on to a
 * new report (a phone change), leaving a note in their place.
 */
class RetireDriverCheckBotMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public readonly int $chatId,
        public readonly int $messageId,
        public readonly string $note,
    ) {
    }

    public function handle(DriverCheckBot $bot): void
    {
        $bot->retire($this->chatId, $this->messageId, $this->note);
    }
}
