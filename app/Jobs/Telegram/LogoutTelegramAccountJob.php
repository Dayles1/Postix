<?php

namespace App\Jobs\Telegram;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LogoutTelegramAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $accountId;

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
    }

    public function handle(): void
    {
        Log::info('LogoutTelegramAccountJob started', [
            'accountId' => $this->accountId,
        ]);

        $php = config('runtime.php_binary');
        $artisan = base_path('artisan');

        $command = sprintf(
            'nohup %s %s telegram:logout %d > /dev/null 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            $this->accountId
        );

        exec($command);
    }
}