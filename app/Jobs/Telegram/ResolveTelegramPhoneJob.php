<?php

namespace App\Jobs\Telegram;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResolveTelegramPhoneJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $checkId,
    ) {
    }

    public function handle(): void
    {
        Log::info('ResolveTelegramPhoneJob started', [
            'check_id' => $this->checkId,
        ]);

        $php = config('runtime.php_binary', PHP_BINARY);
        $artisan = base_path('artisan');

        $command = sprintf(
            'nohup %s %s telegram:resolve-phone %d > /dev/null 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            $this->checkId
        );

        try {
            exec($command);

            Log::info('ResolveTelegramPhoneJob command dispatched', [
                'check_id' => $this->checkId,
                'command' => 'telegram:resolve-phone',
            ]);
        } catch (Throwable $e) {
            Log::error('ResolveTelegramPhoneJob failed to dispatch command', [
                'check_id' => $this->checkId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }
}