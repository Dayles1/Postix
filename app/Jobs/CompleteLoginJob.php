<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CompleteLoginJob implements ShouldQueue
{
    use Queueable;

    
    public function __construct(
    protected string $phone,
    protected int $userId,
    protected string $sessionid,
    protected string $password
) {}

    

    public function handle(): void
    {
        $phone    = $this->phone;
        $userId   = $this->userId;
        $sessionid     = $this->sessionid;
        $password = $this->password;


        $phoneNumber = $phone;
        $sessionid = $sessionid;
        $php = config('runtime.php_binary');
        $artisan = base_path('artisan');
        $command = "nohup {$php} {$artisan} app:complete-login {$phoneNumber} {$userId} {$sessionid} {$password} >/dev/null 2>&1 &";
        
        exec($command);
       
    }
}
