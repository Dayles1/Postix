<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Application\Telegram\Services\AccountContactsService;
use App\Enums\Telegram\TelegramAccountProcess as ProcessEnum;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramAccountContactState;
use App\Models\Telegram\TelegramAccountProcess;
use App\Telegram\TelegramProcessLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Revolt\EventLoop;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Read an account's Telegram contacts into the database, or delete them from
 * the account - one account, or every account one after the other.
 *
 * Two roles in one command:
 *
 *  - the supervisor (what the panel starts through StartAccountContactsJob):
 *    holds the lock (LOCK_NAME) the panel reads to say "running", and hands
 *    each account to a worker process of its own with a time limit. MadelineProto
 *    now and then stops answering ("The endpoint does not exist!" from its IPC
 *    client, a session that never comes back); a worker that hangs is killed at
 *    the limit, its account is marked as failed, and the line moves on.
 *
 *  - the worker (--child): one account, no lock, and an error handler on the
 *    event loop so an exception thrown in a MadelineProto callback is logged
 *    instead of taking the process down in the middle of a call.
 */
final class TelegramAccountContactsCommand extends Command
{
    public const LOCK_NAME = 'account-contacts';

    /** How long a worker may take for one account, minutes. */
    private const LIMIT_MINUTES = [
        'import' => 20,
        'clear' => 60,
    ];

    protected $signature = 'telegram:account-contacts
                            {action : import (read the account into the database) or clear (delete from the account)}
                            {--account= : One account id}
                            {--all : Every account that can be used, one after the other}
                            {--child : Internal: one account, run by the supervisor}';

    protected $description = 'Read accounts\' Telegram contacts into the database, or delete them from the accounts';

    public function handle(AccountContactsService $contacts): int
    {
        $action = $this->argument('action');

        if (! in_array($action, ['import', 'clear'], true)) {
            $this->error('The action is "import" or "clear".');

            return self::FAILURE;
        }

        if ($action === 'clear' && $this->option('all')) {
            $this->error('Deleting from every account at once is not offered: name the account.');

            return self::FAILURE;
        }

        $accounts = $this->accounts($contacts);

        if ($accounts->isEmpty()) {
            $this->warn('No account to work on.');

            return self::SUCCESS;
        }

        return $this->option('child')
            ? $this->work($contacts, $action, $accounts->first())
            : $this->supervise($action, $accounts);
    }

    /**
     * The worker: one account, with the event loop's errors logged.
     */
    private function work(AccountContactsService $contacts, string $action, TelegramAccount $account): int
    {
        EventLoop::setErrorHandler(function (Throwable $e) use ($account): void {
            Log::error('Account contacts: an exception in the event loop', [
                'account_id' => $account->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            fwrite(STDERR, '  event loop: ' . $e->getMessage() . PHP_EOL);
        });

        try {
            $say = fn (string $line) => $this->line($line);

            $result = $action === 'import' ? $contacts->import($account, $say) : $contacts->clearAccount($account, $say);

            $this->info((string) json_encode($result));

            return self::SUCCESS;
        } catch (Throwable $e) {
            /* The state row already says "failed", with the reason. */
            $this->error('  failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * The supervisor: the lock, the line, a worker for each account.
     *
     * @param \Illuminate\Support\Collection<int, TelegramAccount> $accounts
     */
    private function supervise(string $action, $accounts): int
    {
        if (TelegramProcessLock::attempt(self::LOCK_NAME) === TelegramProcessLock::HELD) {
            $this->warn('Another contacts run is already going.');

            return self::SUCCESS;
        }

        $name = $action === 'import' ? TelegramAccountContactState::IMPORT : TelegramAccountContactState::CLEAR_ACCOUNT;
        $minutes = self::LIMIT_MINUTES[$action];

        try {
            /* The whole line is shown as waiting, and each account takes its turn. */
            foreach ($accounts as $account) {
                TelegramAccountContactState::of($account->id)->update(['status' => TelegramAccountContactState::QUEUED, 'action' => $name]);
            }

            foreach ($accounts as $i => $account) {
                $this->line("Account #{$account->id} {$account->phone}");

                $this->runWorker($action, $account, $minutes);

                if ($i < $accounts->count() - 1) {
                    sleep(random_int(3, 6));
                }
            }
        } finally {
            /* Whatever is still waiting in the line goes back to rest. */
            TelegramAccountContactState::query()
                ->where('status', TelegramAccountContactState::QUEUED)
                ->update(['status' => TelegramAccountContactState::IDLE, 'action' => null]);

            TelegramProcessLock::release(self::LOCK_NAME);
        }

        return self::SUCCESS;
    }

    /**
     * One account in a process of its own, killed at the limit.
     */
    private function runWorker(string $action, TelegramAccount $account, int $minutes): void
    {
        $process = new Process(
            [PHP_BINARY, base_path('artisan'), 'telegram:account-contacts', $action, '--account=' . $account->id, '--child'],
            base_path(),
        );

        $process->setTimeout($minutes * 60);

        try {
            $process->run(function (string $type, string $buffer): void {
                $this->output->write($buffer);
            });
        } catch (ProcessTimedOutException) {
            $this->error("  stopped: no end after {$minutes} minutes");

            $this->failed($account, "timed out after {$minutes} min");

            return;
        }

        if (! $process->isSuccessful()) {
            /* A worker that died on a fatal error never got to write its own state. */
            $this->failed($account, 'the worker stopped (exit ' . $process->getExitCode() . ')', onlyIfUnfinished: true);
        }
    }

    /**
     * An account whose worker did not finish: marked as failed, let go.
     */
    private function failed(TelegramAccount $account, string $reason, bool $onlyIfUnfinished = false): void
    {
        $state = TelegramAccountContactState::of($account->id);

        if (! $onlyIfUnfinished || in_array($state->status, [TelegramAccountContactState::QUEUED, TelegramAccountContactState::RUNNING], true)) {
            $state->update(['status' => TelegramAccountContactState::FAILED, 'action' => null, 'last_error' => $reason]);
        }

        /* The dead worker could not give the account back. */
        TelegramAccountProcess::query()
            ->where('telegram_account_id', $account->id)
            ->where('process', ProcessEnum::ContactSync->value)
            ->update(['is_busy' => false, 'busy_at' => null]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, TelegramAccount>
     */
    private function accounts(AccountContactsService $contacts)
    {
        $query = TelegramAccount::query()->where('is_authorized', true)->orderBy('id');

        if ($this->option('account')) {
            $query->whereKey((int) $this->option('account'));
        } elseif (! $this->option('all')) {
            return collect();
        }

        return $query->get()->filter(fn (TelegramAccount $account) => $contacts->usable($account))->values();
    }
}
