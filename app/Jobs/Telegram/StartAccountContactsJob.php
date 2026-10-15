<?php

namespace App\Jobs\Telegram;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;

/**
 * The Sync / Clear buttons of the contact accounts page: nohups
 * telegram:account-contacts so the queue worker is not held while a long read
 * or clean-up of the accounts goes on.
 */
class StartAccountContactsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param string   $action    import | clear
     * @param int|null $accountId one account, or null for all of them (import only)
     */
    public function __construct(
        public readonly string $action,
        public readonly ?int $accountId = null,
    ) {}

    public function handle(): void
    {
        $command = sprintf(
            'cd %s && nohup %s %s telegram:account-contacts %s %s >> %s 2>&1 &',
            escapeshellarg(base_path()),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(base_path('artisan')),
            escapeshellarg($this->action === 'clear' ? 'clear' : 'import'),
            $this->accountId ? '--account=' . (int) $this->accountId : '--all',
            escapeshellarg(storage_path('logs/account-contacts.log')),
        );

        Process::fromShellCommandline($command)->setTimeout(10)->run();
    }
}
