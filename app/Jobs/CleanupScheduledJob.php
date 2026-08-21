<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class CleanupScheduledJob implements ShouldQueue
{
    use Queueable;

    protected int $groupId;

    public function __construct(int $groupId)
    {
        $this->groupId = $groupId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $php = '/opt/php83/bin/php';
            $artisan = base_path('artisan');
            $command = "nohup {$php} {$artisan} telegram:cleanup-group {$this->groupId} > /dev/null 2>&1 &";
            exec($command);
    }
}
