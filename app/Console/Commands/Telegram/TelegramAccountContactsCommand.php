<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Application\Telegram\Services\AccountContactsService;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramAccountContactState;
use App\Telegram\TelegramProcessLock;
use Illuminate\Console\Command;
use Throwable;

/**
 * Read an account's Telegram contacts into the database, or delete them from
 * the account - one account, or every account one after the other.
 *
 * Held while it runs (LOCK_NAME): the panel reads it to say "running", and a
 * second run is refused. The panel's buttons start it through
 * StartAccountContactsJob, so the queue worker is not held for the hour a
 * long read or clean-up takes.
 */
final class TelegramAccountContactsCommand extends Command
{
    public const LOCK_NAME = 'account-contacts';

    protected $signature = 'telegram:account-contacts
                            {action : import (read the account into the database) or clear (delete from the account)}
                            {--account= : One account id}
                            {--all : Every account that can be used, one after the other}';

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

        if (TelegramProcessLock::attempt(self::LOCK_NAME) === TelegramProcessLock::HELD) {
            $this->warn('Another contacts run is already going.');

            return self::SUCCESS;
        }

        $name = $action === 'import' ? TelegramAccountContactState::IMPORT : TelegramAccountContactState::CLEAR_ACCOUNT;

        try {
            /* The whole line is shown as waiting, and each account takes its turn. */
            foreach ($accounts as $account) {
                TelegramAccountContactState::of($account->id)->update(['status' => TelegramAccountContactState::QUEUED, 'action' => $name]);
            }

            foreach ($accounts as $i => $account) {
                $this->line("Account #{$account->id} {$account->phone}");

                try {
                    $result = $action === 'import'
                        ? $contacts->import($account, fn (string $line) => $this->line($line))
                        : $contacts->clearAccount($account, fn (string $line) => $this->line($line));

                    $this->info(json_encode($result));
                } catch (Throwable $e) {
                    /* The state row already says "failed"; the next account is still worth a try. */
                    $this->error('  failed: ' . $e->getMessage());
                }

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
