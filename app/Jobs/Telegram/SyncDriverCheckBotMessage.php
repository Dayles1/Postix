<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Application\Telegram\Services\DriverCheckBot;
use App\Models\Driver\TelegramDriverCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Posts or refreshes the bot's button message for one check.
 *
 * Runs on the queue rather than in the listener: the Bot API is a
 * blocking HTTP call, and the listener's event loop is not the place
 * for one.
 */
class SyncDriverCheckBotMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public readonly int $checkId,
    ) {
    }

    /**
     * Two syncs of one check at once would both see no bot message and
     * both post one.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('driver-check-bot:' . $this->checkId))
                ->releaseAfter(5)
                ->expireAfter(60),
        ];
    }

    public function handle(DriverCheckBot $bot): void
    {
        $check = TelegramDriverCheck::query()->find($this->checkId);

        if ($check !== null) {
            $bot->sync($check);
        }
    }
}
