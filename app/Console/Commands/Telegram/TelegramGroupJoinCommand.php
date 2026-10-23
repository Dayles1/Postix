<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Application\Telegram\Services\GroupJoinService;
use App\Application\Telegram\Services\GroupJoinSettings;
use App\Models\Telegram\TelegramAccount;
use App\Telegram\TelegramProcessLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Revolt\EventLoop;
use Throwable;

/**
 * One marked account's group join worker:
 *
 *     php artisan telegram:group-join <account id>
 *
 * Normally started and kept running by telegram:group-join-watchdog, one per
 * account marked in the panel. It searches, checks, joins and reads members
 * until the account is unmarked or the join switched off, then exits with
 * INVALID so the watchdog does not start it again right away.
 */
final class TelegramGroupJoinCommand extends Command
{
    /** Exit code: another process holds this account's worker. */
    public const EXIT_ALREADY_RUNNING = 3;

    protected $signature = 'telegram:group-join
        {account : The telegram_accounts id}
        {--once : One burst and exit}
        {--minutes=120 : Exit after this long (0: never); the watchdog starts it again}';

    protected $description = 'One marked account finds public groups by the keywords and joins them, minding flood waits';

    public static function lockName(int $accountId): string
    {
        return 'group-join-account-' . $accountId;
    }

    public function handle(GroupJoinService $service, GroupJoinSettings $settings): int
    {
        $account = TelegramAccount::query()->find((int) $this->argument('account'));

        if ($account === null) {
            $this->error('No such account.');

            return self::INVALID;
        }

        if (! $service->mayWork($account, $settings->current())) {
            $this->error("Account #{$account->id} is not marked for the group join, the join is off, or it is not authorized.");

            return self::INVALID;
        }

        if (TelegramProcessLock::attempt(self::lockName($account->id)) === TelegramProcessLock::HELD) {
            $this->error("A group join worker of account #{$account->id} is already running.");

            return self::EXIT_ALREADY_RUNNING;
        }

        /* See TelegramSyncContactsCommand: a callback's exception must not end the worker. */
        EventLoop::setErrorHandler(static function (Throwable $e) use ($account): void {
            Log::error('Group join: an exception in the event loop', [
                'account_id' => $account->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        });

        /* The watchdog's SIGTERM: the burst in hand is finished, its session closed, then out. */
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);

            pcntl_signal(SIGTERM, fn () => $service->stop());
            pcntl_signal(SIGINT, fn () => $service->stop());
        }

        $this->info("▶️ telegram:group-join account #{$account->id} {$account->phone}, pid " . getmypid());

        try {
            $totals = $service->work(
                $account,
                fn (string $line) => $this->line($line),
                max(0, (int) $this->option('minutes')) * 60,
                (bool) $this->option('once'),
            );
        } catch (Throwable $e) {
            Log::error('Group join worker failed', [
                'account_id' => $account->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            TelegramProcessLock::release(self::lockName($account->id));
        }

        $this->info(sprintf(
            'Searched: %d, new groups: %d, checked: %d, joined: %d, people read: %d',
            $totals['searched'],
            $totals['found'],
            $totals['checked'],
            $totals['joined'],
            $totals['members'],
        ));

        return self::SUCCESS;
    }
}
