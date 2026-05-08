<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TelegramAuthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $phone;
    public int $userId;
    public int $sessionId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $phone, int $userId, int $sessionId)
    {
        $this->phone = $phone;
        $this->userId = $userId;
        $this->sessionId = $sessionId;
    }

    public function handle(): void
    {
        Log::info('TelegramAuthJob started', [
            'phone' => $this->phone,
            'userId' => $this->userId,
            'sessionId' => $this->sessionId
        ]);

        $php = config('runtime.php_binary');
        
        $artisan = base_path('artisan');

        $command = sprintf(
            'nohup %s %s telegram:auth %s %d %d > /dev/null 2>&1 &',
            $php,
            $artisan,
            escapeshellarg($this->phone),
            $this->userId,
            $this->sessionId
        );

        exec($command);
    }
}
