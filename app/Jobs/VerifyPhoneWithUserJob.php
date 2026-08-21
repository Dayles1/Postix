<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VerifyPhoneWithUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $phone;
    public int $userId;
    public string $code;
    public ?string $password;
    public ?int $departmentId;

    public function __construct(string $phone, int $userId, string $code, ?string $password = null, ?int $departmentId = null)
    {
        $this->phone = $phone;
        $this->userId = $userId;
        $this->code = $code;
        $this->password = $password;
        $this->departmentId = $departmentId;
    }

    public function handle(): void
{
    $php = '/opt/php83/bin/php';
    $artisan = base_path('artisan');

    $command = "nohup {$php} {$artisan} telegram:userWithPhone {$this->phone} {$this->userId} {$this->code}";

    if ($this->departmentId) {
        $command .= " --department={$this->departmentId}";
    }

    if ($this->password) {
        $command .= " --password={$this->password}";
    }

    $command .= " >/dev/null 2>&1 &";

    exec($command);

    Log::info('VerifyPhoneWithUserJob executed', [
        'command' => $command
    ]);
}

}
