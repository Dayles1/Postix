<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Console\Commands\Telegram\TelegramGroupJoinWatchdogCommand;
use App\Telegram\TelegramProcessLock;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * The panel's hand on the group join watchdog. Like ListenersControl the
 * panel does not start processes itself (PHP-FPM is another user): the
 * queue worker spawns the watchdog (StartGroupJoinWatchdogJob), and the
 * scheduler asks for it every minute anyway. Stopping is switching the join
 * off: the watchdog reads that and takes its workers down.
 */
class GroupJoinControl
{
    public function running(): bool
    {
        return TelegramProcessLock::isHeld(TelegramGroupJoinWatchdogCommand::LOCK_NAME);
    }

    public function pid(): ?int
    {
        return $this->running() ? TelegramProcessLock::holderPid(TelegramGroupJoinWatchdogCommand::LOCK_NAME) : null;
    }

    /**
     * Starts the watchdog in the background, detached from this process.
     * Called in the queue worker, the user the sessions belong to.
     */
    public function spawnWatchdog(): void
    {
        if ($this->running()) {
            return;
        }

        $command = sprintf(
            'cd %s && nohup %s %s telegram:group-join-watchdog >> %s 2>&1 &',
            escapeshellarg(base_path()),
            escapeshellarg($this->phpBinary()),
            escapeshellarg(base_path('artisan')),
            escapeshellarg(storage_path('logs/group-join-watchdog.log')),
        );

        Process::fromShellCommandline($command)->setTimeout(10)->run();

        Log::info('Group join watchdog start command executed', ['command' => $command]);
    }

    /**
     * Under PHP-FPM PHP_BINARY is php-fpm itself: the CLI one is looked for then.
     */
    private function phpBinary(): string
    {
        if (PHP_SAPI === 'cli') {
            return PHP_BINARY;
        }

        return (new PhpExecutableFinder())->find() ?: (string) config('runtime.php_binary', 'php');
    }
}
