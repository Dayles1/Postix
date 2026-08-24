<?php

namespace App\Jobs\Telegram;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AuthTelegramAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $phone;

    public function __construct(string $phone)
    {
        $this->phone = $phone;
    }

    public function handle(): void
    {
        Log::info('AuthTelegramAccountJob started', [
            'phone' => $this->phone,
        ]);

        $php = config('runtime.php_binary');
        $artisan = base_path('artisan');

        $command = sprintf(
            'nohup %s %s ta %s > /dev/null 2>&1 &',
            $php,
            $artisan,
            escapeshellarg($this->phone)
        );

        exec($command);
    }
}