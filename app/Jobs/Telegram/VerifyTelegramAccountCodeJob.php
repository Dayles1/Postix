<?php

namespace App\Jobs\Telegram;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VerifyTelegramAccountCodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $phone;
    public string $code;

    public function __construct(string $phone, string $code)
    {
        $this->phone = $phone;
        $this->code = $code;
    }

    public function handle(): void
    {
        Log::info('VerifyTelegramAccountCodeJob started', [
            'phone' => $this->phone,
            'code' => $this->code,
        ]);

        $php = config('runtime.php_binary');
        $artisan = base_path('artisan');

        $command = sprintf(
            'nohup %s %s tc %s %s > /dev/null 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            escapeshellarg($this->phone),
            escapeshellarg($this->code)
        );

        exec($command);
    }
}