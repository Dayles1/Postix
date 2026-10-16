<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Application\Telegram\Services\ContactSyncService;
use App\Telegram\TelegramProcessLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Revolt\EventLoop;
use Throwable;

final class TelegramSyncContactsCommand extends Command
{
    /**
     * Held for as long as a sync runs: the panel reads it to say "running",
     * and a second run (cron plus the Run button) is refused.
     */
    public const LOCK_NAME = 'contact-sync';

    protected $signature = 'telegram:sync-contacts {--group= : Only this group id} {--trigger=manual : Who started it: manual or schedule}';

    protected $description = 'Save the members of the watched groups into the accounts\' contacts';

    public function handle(ContactSyncService $sync): int
    {
        /*
         * An exception thrown in a MadelineProto callback ("The endpoint does
         * not exist!" from its IPC client) would take the whole run down in the
         * middle of an account. It is logged, and the account's own call fails
         * or times out on its own.
         */
        EventLoop::setErrorHandler(static function (Throwable $e): void {
            Log::error('Contact sync: an exception in the event loop', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        });

        if (TelegramProcessLock::attempt(self::LOCK_NAME) === TelegramProcessLock::HELD) {
            $this->warn('Another contact sync is already running.');

            return self::SUCCESS;
        }

        try {
            $totals = $sync->run(
                $this->option('group') ? (int) $this->option('group') : null,
                fn (string $line) => $this->line($line),
                $this->option('trigger') === 'schedule' ? 'schedule' : 'manual',
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            TelegramProcessLock::release(self::LOCK_NAME);
        }

        $this->info(sprintf(
            'Accounts: %d, members read: %d, added: %d, failed: %d',
            $totals['accounts'],
            $totals['members'],
            $totals['added'],
            $totals['failed'],
        ));

        return self::SUCCESS;
    }
}
