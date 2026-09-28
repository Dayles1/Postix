<?php

namespace App\Jobs\Telegram;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs `tp` for the account. The password itself never travels through
 * the queue: it waits encrypted in the cache (see
 * TelegramAccountPasswordCommand).
 */
class CompleteTelegramAccountLoginJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $accountId;

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
    }

    public function handle(): void
    {
        Log::info('CompleteTelegramAccountLoginJob started', [
            'accountId' => $this->accountId,
        ]);

        $php = config('runtime.php_binary');
        $artisan = base_path('artisan');

        $command = sprintf(
            'nohup %s %s tp %d > /dev/null 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            $this->accountId
        );

        exec($command);
    }
}
