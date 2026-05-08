<?php

namespace App\Jobs\Delete;

use App\Models\UserPhone;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class TelegramLogoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $phoneId;

    public function __construct(int $phoneId)
    {
        $this->phoneId = $phoneId;
    }


    public function handle(): void
    {
            $php = config('runtime.php_binary');
            $artisan = base_path('artisan');
            $command = "nohup {$php} {$artisan} telegram:logout {$this->phoneId} > /dev/null 2>&1 &";
            exec($command);
    }
}
