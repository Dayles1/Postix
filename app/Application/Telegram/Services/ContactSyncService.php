<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Enums\Telegram\TelegramAccountProcess as ProcessEnum;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramAccountContact;
use App\Models\Telegram\TelegramContactSyncLog;
use App\Models\Telegram\TelegramScrapedUser;
use App\Models\Telegram\TelegramScrapeGroup;
use danog\MadelineProto\API;
use danog\MadelineProto\RPCErrorException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the members of the watched groups and saves each of them into the
 * contacts of exactly one account.
 *
 * Why the account that reads is the account that saves: an access hash is
 * only valid for the session that saw the user, so a member found by one
 * account cannot be added by another.
 *
 * Why a person lands in one account only: the claim is a row in
 * telegram_account_contacts, unique by scraped_user_id, taken before the
 * contact is added. Two processes racing for the same person cannot both win.
 */
final class ContactSyncService
{
    /**
     * Errors that say the account itself is unusable, not the one member.
     */
    private const ACCOUNT_ERRORS = [
        'AUTH_KEY_UNREGISTERED',
        'AUTH_KEY_INVALID',
        'AUTH_KEY_DUPLICATED',
        'SESSION_REVOKED',
        'SESSION_EXPIRED',
        'USER_DEACTIVATED',
        'USER_DEACTIVATED_BAN',
        'PHONE_NUMBER_BANNED',
    ];

    /**
     * This many members in a row refused is not bad luck: the account is
     * put on hold instead of working through the rest of the list.
     */
    private const MAX_FAILURE_STREAK = 5;

    private string $runId = '';

    public function __construct(
        private readonly MadelineService $madeline,
        private readonly TelegramAccountProcessService $pool,
        private readonly ContactSyncSettings $settings,
    ) {}

    /**
     * @param callable(string): void|null $output console echo, optional
     *
     * @param string $trigger who started it, kept on the run: "manual" (the
     *                        button, the console) or "schedule" (the daily one)
     *
     * @return array{accounts: int, added: int, failed: int, members: int}
     */
    public function run(?int $groupId = null, ?callable $output = null, string $trigger = 'manual'): array
    {
        $this->runId = (string) Str::uuid();

        $say = $output ?? static function (string $line): void {};

        /* The panel's settings, read once: a run is not changed half way. */
        $config = $this->settings->current();
        $maxAccounts = max(1, (int) $config['max_accounts']);

        $totals = ['accounts' => 0, 'added' => 0, 'failed' => 0, 'members' => 0];

        $groups = TelegramScrapeGroup::query()
            ->where('is_active', true)
            ->when($groupId, fn ($q) => $q->whereKey($groupId))
            ->get();

        $this->log('started', 'Sync started', null, null, [
            'trigger' => $trigger,
            'groups' => $groups->pluck('link')->all(),
            'max_accounts' => $maxAccounts,
            'daily_limit' => (int) $config['daily_limit'],
            'nick_base' => $config['nick_base'] !== '' ? $config['nick_base'] : null,
            'excluded_accounts' => $config['excluded_accounts'],
        ]);

        if ($groups->isEmpty()) {
            $this->log('finished', 'No active groups, nothing to do');

            return $totals;
        }

        /* Accounts left out in the panel are taken for used from the start. */
        $usedAccountIds = array_map('intval', (array) $config['excluded_accounts']);

        for ($i = 0; $i < $maxAccounts; $i++) {
            /*
             * The first pass has to read the groups to learn who is in them;
             * after that, stop as soon as nobody is left without an owner.
             */
            if ($i > 0 && ! $this->hasUnclaimed($groups)) {
                break;
            }

            $account = $this->pool->claimRandomAvailableAccount(
                ProcessEnum::ContactSync,
                $usedAccountIds,
            );

            if (! $account) {
                $this->log('skipped', 'No more accounts available');

                break;
            }

            $usedAccountIds[] = $account->id;
            $totals['accounts']++;

            $say("Account #{$account->id} {$account->phone}");

            $api = null;

            try {
                $api = $this->madeline->for($account);

                if (! $api) {
                    throw $this->madeline->lastError
                        ?? new \RuntimeException('Failed to start MadelineProto.');
                }

                $result = $this->runAccount($api, $account, $groups, $config, $say);

                $totals['added'] += $result['added'];
                $totals['failed'] += $result['failed'];
                $totals['members'] += $result['members'];

                $this->pool->registerSuccess($account, ProcessEnum::ContactSync);
            } catch (Throwable $e) {
                if ($this->isAccountError($e) || $this->isFlood($e)) {
                    $this->hold($account, $e);
                } else {
                    $this->pool->registerFailure(
                        $account,
                        ProcessEnum::ContactSync,
                        mb_substr($e->getMessage(), 0, 200),
                    );
                }

                $this->log('failed', mb_substr($e->getMessage(), 0, 500), $account->id, null, [
                    'exception' => $e::class,
                ]);

                $say("  failed: {$e->getMessage()}");
            } finally {
                if ($api) {
                    try {
                        $api->stop();
                    } catch (Throwable) {
                        // The session is being dropped anyway.
                    }
                }

                $this->pool->release($account, ProcessEnum::ContactSync);
            }
        }

        $this->log('finished', 'Sync finished', null, null, $totals);

        return $totals;
    }

    /**
     * @param \Illuminate\Support\Collection<int, TelegramScrapeGroup> $groups
     * @param array<string, mixed>                                      $config
     * @param callable(string): void                                    $say
     *
     * @return array{added: int, failed: int, members: int}
     */
    private function runAccount(
        API $api,
        TelegramAccount $account,
        $groups,
        array $config,
        callable $say,
    ): array {
        $limit = max(0, (int) ($config['daily_limit'] ?? 100));
        $remaining = $limit - $this->addedToday($account);

        $result = ['added' => 0, 'failed' => 0, 'members' => 0];

        /* Our own accounts are never somebody's contact to collect. */
        $own = TelegramAccount::query()
            ->whereNotNull('telegram_user_id')
            ->pluck('telegram_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $existing = null;
        $streak = 0;

        /*
         * With a base nick set, people are saved as "base", "base-1",
         * "base-2" ... counted per account and carried on from run to run.
         */
        $nickBase = (string) ($config['nick_base'] ?? '');
        $nickNext = $nickBase !== '' ? $this->nickCount($account, $nickBase) : 0;

        foreach ($groups as $group) {
            try {
                $members = $this->readMembers($api, $group, $account, $config);
            } catch (Throwable $e) {
                $group->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);

                $this->log('failed', 'Cannot read group: ' . mb_substr($e->getMessage(), 0, 300), $account->id, $group->id);

                $say("  {$group->link}: {$e->getMessage()}");

                if ($this->isFlood($e)) {
                    $this->hold($account, $e, $group->id);

                    return $result;
                }

                if ($this->isAccountError($e)) {
                    throw $e;
                }

                continue;
            }

            $result['members'] += count($members);

            $this->storeMembers($group, $members, $own);

            $this->log('members', count($members) . ' members read', $account->id, $group->id);

            $say("  {$group->link}: " . count($members) . ' members');

            if ($remaining <= 0) {
                continue;
            }

            /*
             * People this account already has in its contacts are not added
             * again; they are marked and left alone.
             */
            $existing ??= $this->existingContactIds($api);

            $ids = $group->users()
                ->whereDoesntHave('contact')
                ->where('is_bot', false)
                ->orderBy('telegram_scraped_users.id')
                ->get();

            foreach ($ids as $user) {
                if ($remaining <= 0) {
                    break;
                }

                if (! $this->claim($account, $user, isset($existing[$user->telegram_user_id]))) {
                    continue;
                }

                if (isset($existing[$user->telegram_user_id])) {
                    $this->log('skipped', 'Already in contacts', $account->id, $group->id, [
                        'telegram_user_id' => $user->telegram_user_id,
                    ]);

                    continue;
                }

                try {
                    $nick = $nickBase !== '' ? ContactSyncSettings::nick($nickBase, $nickNext) : null;

                    $this->addContact($api, $user, $nick);

                    $this->finishClaim($account, $user, TelegramAccountContact::STATUS_ADDED, null, $nick);

                    $result['added']++;
                    $remaining--;
                    $streak = 0;

                    if ($nick !== null) {
                        $nickNext++;
                    }

                    $this->log('added', 'Contact added', $account->id, $group->id, [
                        'telegram_user_id' => $user->telegram_user_id,
                        'username' => $user->username,
                        'contact_name' => $nick,
                    ]);

                    $this->pause($config);
                } catch (RPCErrorException $e) {
                    if ($this->isFlood($e)) {
                        /*
                         * Not this person's fault: give the claim back so
                         * another account can take them, and take this
                         * account out of the sync until somebody switches
                         * it back on.
                         */
                        TelegramAccountContact::query()
                            ->where('scraped_user_id', $user->id)
                            ->where('telegram_account_id', $account->id)
                            ->where('status', TelegramAccountContact::STATUS_PENDING)
                            ->delete();

                        $this->hold($account, $e, $group->id);

                        $say("  flood: {$e->rpc}, account put on hold");

                        return $result;
                    }

                    if ($this->isAccountError($e)) {
                        TelegramAccountContact::query()
                            ->where('scraped_user_id', $user->id)
                            ->where('telegram_account_id', $account->id)
                            ->where('status', TelegramAccountContact::STATUS_PENDING)
                            ->delete();

                        throw $e;
                    }

                    $this->finishClaim($account, $user, TelegramAccountContact::STATUS_FAILED, $e->rpc);

                    $result['failed']++;
                    $streak++;

                    $this->log('failed', $e->rpc, $account->id, $group->id, [
                        'telegram_user_id' => $user->telegram_user_id,
                    ]);

                    if ($streak >= self::MAX_FAILURE_STREAK) {
                        $this->hold($account, $e, $group->id, 'too_many_failures');

                        return $result;
                    }
                } catch (Throwable $e) {
                    $this->finishClaim(
                        $account,
                        $user,
                        TelegramAccountContact::STATUS_FAILED,
                        mb_substr($e->getMessage(), 0, 300),
                    );

                    $result['failed']++;
                    $streak++;

                    $this->log('failed', mb_substr($e->getMessage(), 0, 300), $account->id, $group->id, [
                        'telegram_user_id' => $user->telegram_user_id,
                    ]);

                    if ($streak >= self::MAX_FAILURE_STREAK) {
                        $this->hold($account, $e, $group->id, 'too_many_failures');

                        return $result;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>> the "user" part of every participant
     */
    private function readMembers(
        API $api,
        TelegramScrapeGroup $group,
        TelegramAccount $account,
        array $config,
    ): array {
        try {
            $chat = $api->getPwrChat($group->link);
        } catch (Throwable $e) {
            /*
             * Flood and dead-account errors are not "not a member yet".
             */
            if ($this->isFlood($e) || $this->isAccountError($e)) {
                throw $e;
            }

            $this->join($api, $group, $account, $config);

            $chat = $api->getPwrChat($group->link);
        }

        /*
         * A public group opens for a non-member without listing anybody.
         */
        if (($chat['participants'] ?? []) === []) {
            if ($this->join($api, $group, $account, $config)) {
                $chat = $api->getPwrChat($group->link);
            }
        }

        $group->update([
            'title' => $chat['title'] ?? $group->title,
            'telegram_chat_id' => $chat['id'] ?? $group->telegram_chat_id,
            'members_count' => (int) ($chat['participants_count'] ?? count($chat['participants'] ?? [])),
            'last_synced_at' => now(),
            'last_error' => null,
        ]);

        $members = [];

        foreach ($chat['participants'] ?? [] as $participant) {
            $user = $participant['user'] ?? null;

            if (is_array($user) && isset($user['id'])) {
                $members[] = $user;
            }
        }

        return $members;
    }

    /**
     * The account joins the group when it is not in it yet.
     *
     * @param array<string, mixed> $config
     *
     * @return bool whether a join was actually made
     */
    private function join(
        API $api,
        TelegramScrapeGroup $group,
        TelegramAccount $account,
        array $config,
    ): bool {
        $link = $group->link;

        try {
            if (str_starts_with($link, '+') || str_starts_with($link, 'joinchat/')) {
                $api->messages->importChatInvite(
                    hash: ltrim((string) preg_replace('#^joinchat/#', '', $link), '+'),
                );
            } else {
                $api->channels->joinChannel(channel: '@' . $link);
            }
        } catch (RPCErrorException $e) {
            if (str_contains($e->rpc, 'USER_ALREADY_PARTICIPANT')) {
                return false;
            }

            throw $e;
        }

        $this->log('joined', 'Joined the group', $account->id, $group->id);

        $this->pause($config);

        return true;
    }

    /**
     * One row per Telegram user, whatever the number of groups; the pivot
     * only records where they were seen.
     *
     * @param list<array<string, mixed>> $members
     * @param list<int>                  $ownIds
     */
    private function storeMembers(TelegramScrapeGroup $group, array $members, array $ownIds): void
    {
        foreach (array_chunk($members, 200) as $chunk) {
            DB::transaction(function () use ($chunk, $group, $ownIds): void {
                foreach ($chunk as $member) {
                    $id = (int) $member['id'];

                    if (in_array($id, $ownIds, true)) {
                        continue;
                    }

                    $user = TelegramScrapedUser::query()->updateOrCreate(
                        ['telegram_user_id' => $id],
                        [
                            'username' => $member['username'] ?? null,
                            'first_name' => $member['first_name'] ?? null,
                            'last_name' => $member['last_name'] ?? null,
                            'phone' => $member['phone'] ?? null,
                            'is_bot' => ($member['type'] ?? 'user') === 'bot' || ($member['bot'] ?? false),
                        ],
                    );

                    DB::table('telegram_scrape_group_user')->insertOrIgnore([
                        'group_id' => $group->id,
                        'scraped_user_id' => $user->id,
                        'seen_at' => now(),
                    ]);
                }
            });
        }
    }

    /**
     * @return array<int, true> telegram user ids already in the contacts
     */
    private function existingContactIds(API $api): array
    {
        try {
            $contacts = $api->contacts->getContacts();
        } catch (Throwable) {
            return [];
        }

        $ids = [];

        foreach ($contacts['contacts'] ?? [] as $contact) {
            if (isset($contact['user_id'])) {
                $ids[(int) $contact['user_id']] = true;
            }
        }

        return $ids;
    }

    /**
     * Take the person for this account, or fail because somebody else did.
     */
    private function claim(TelegramAccount $account, TelegramScrapedUser $user, bool $alreadyContact): bool
    {
        $inserted = DB::table('telegram_account_contacts')->insertOrIgnore([
            'telegram_account_id' => $account->id,
            'scraped_user_id' => $user->id,
            'status' => $alreadyContact
                ? TelegramAccountContact::STATUS_SKIPPED
                : TelegramAccountContact::STATUS_PENDING,
            'added_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $inserted === 1;
    }

    private function finishClaim(
        TelegramAccount $account,
        TelegramScrapedUser $user,
        string $status,
        ?string $error = null,
        ?string $contactName = null,
    ): void {
        TelegramAccountContact::query()
            ->where('scraped_user_id', $user->id)
            ->where('telegram_account_id', $account->id)
            ->update([
                'status' => $status,
                'error' => $error,
                'contact_name' => $contactName,
                'added_at' => $status === TelegramAccountContact::STATUS_ADDED ? now() : null,
                'updated_at' => now(),
            ]);
    }

    /**
     * How many people this account has saved under the base nick so far -
     * the number the next one gets.
     */
    private function nickCount(TelegramAccount $account, string $base): int
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $base);

        return TelegramAccountContact::query()
            ->where('telegram_account_id', $account->id)
            ->where(fn ($q) => $q->where('contact_name', $base)->orWhere('contact_name', 'like', $escaped . '-%'))
            ->count();
    }

    /**
     * @param string|null $nick the name to save them under; null keeps their own
     */
    private function addContact(API $api, TelegramScrapedUser $user, ?string $nick = null): void
    {
        if ($nick !== null) {
            $api->contacts->addContact(
                id: $user->telegram_user_id,
                first_name: $nick,
                last_name: '',
                add_phone_privacy_exception: false,
            );

            return;
        }

        $first = trim((string) $user->first_name);

        if ($first === '') {
            $first = $user->username ?: 'User ' . $user->telegram_user_id;
        }

        $api->contacts->addContact(
            id: $user->telegram_user_id,
            first_name: $first,
            last_name: (string) $user->last_name,
            add_phone_privacy_exception: false,
        );
    }

    private function addedToday(TelegramAccount $account): int
    {
        return TelegramAccountContact::query()
            ->where('telegram_account_id', $account->id)
            ->where('status', TelegramAccountContact::STATUS_ADDED)
            ->where('added_at', '>=', now()->startOfDay())
            ->count();
    }

    /**
     * @param \Illuminate\Support\Collection<int, TelegramScrapeGroup> $groups
     */
    private function hasUnclaimed($groups): bool
    {
        return TelegramScrapedUser::query()
            ->where('is_bot', false)
            ->whereDoesntHave('contact')
            ->whereHas('groups', fn ($q) => $q->whereIn('telegram_scrape_groups.id', $groups->pluck('id')))
            ->exists();
    }

    private function isFlood(Throwable $e): bool
    {
        return $e instanceof RPCErrorException
            && (str_contains($e->rpc, 'FLOOD_WAIT') || str_contains($e->rpc, 'PEER_FLOOD'));
    }

    /**
     * Take the account out of the contact sync so no later run starts it by
     * accident. Only this process: the account keeps working for the rest.
     * It comes back when somebody enables it again in the panel.
     */
    private function hold(
        TelegramAccount $account,
        Throwable $e,
        ?int $groupId = null,
        ?string $reason = null,
    ): void {
        $reason ??= $e instanceof RPCErrorException ? $e->rpc : $e::class;

        $this->pool->disable($account, ProcessEnum::ContactSync, $reason);

        /*
         * A dead session is dead for every process, not just this one: the
         * same state telegram:account-check writes, so no pool picks it up
         * and the sessions page shows it as revoked.
         */
        if ($this->isAccountError($e)) {
            $account->update([
                'is_authorized' => false,
                'status' => TelegramAccount::STATUS_REVOKED,
                'last_checked_at' => now(),
                'last_error' => mb_substr($reason, 0, 500),
            ]);
        }

        $this->log(
            $this->isFlood($e) ? 'flood' : 'disabled',
            mb_substr($reason, 0, 300),
            $account->id,
            $groupId,
            ['message' => mb_substr($e->getMessage(), 0, 300)],
        );
    }

    private function isAccountError(Throwable $e): bool
    {
        /*
         * MadelineProto often wraps the RPC error (a failed start, an Amp
         * cancellation), so the whole chain is searched, not just the class.
         */
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $text = $current instanceof RPCErrorException
                ? $current->rpc
                : $current->getMessage();

            foreach (self::ACCOUNT_ERRORS as $code) {
                if (str_contains($text, $code)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function pause(array $config): void
    {
        $min = max(0, (int) ($config['delay_min'] ?? 3));
        $max = max($min, (int) ($config['delay_max'] ?? 5));

        if ($max > 0) {
            sleep(random_int($min, $max));
        }
    }

    /**
     * @param array<string, mixed>|null $context
     */
    private function log(
        string $event,
        string $message,
        ?int $accountId = null,
        ?int $groupId = null,
        ?array $context = null,
    ): void {
        TelegramContactSyncLog::query()->create([
            'run_id' => $this->runId,
            'telegram_account_id' => $accountId,
            'group_id' => $groupId,
            'event' => $event,
            'message' => mb_substr($message, 0, 500),
            'context' => $context,
        ]);
    }
}
