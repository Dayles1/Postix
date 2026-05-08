<?php

namespace App\Jobs;

use App\Models\UserPhone;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Logger as LoggerSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TelegramVerifyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $phone;
    public $userId;
    public $code;
    public $sessionId;

    public function __construct(string $phone, int $userId, string $code, int $sessionId)
    {
        $this->phone = $phone;
        $this->userId = $userId;
        $this->code = $code;
        $this->sessionId = $sessionId;
    }

    

    public function handle(): void
    {
        $phone    = $this->phone;
        $userId   = $this->userId;
        $code     = $this->code;
        $sessionId= $this->sessionId;


        $phoneNumber = $phone;
        $code = $code;
        $php = config('runtime.php_binary');
        $artisan = base_path('artisan');
            $command = "nohup {$php} {$artisan} telegram:verify {$phoneNumber} {$userId} {$code} {$sessionId} >/dev/null 2>&1 &";
        exec($command);
       
    }
}
