<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramAccountContact;
use App\Models\Telegram\TelegramAccountContactState;
use App\Models\Telegram\TelegramScrapedUser;
use App\Models\Telegram\TelegramScrapeGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The catalog behind /driver-check/contacts: every person the contact sync
 * found in the watched groups, with the account that saved them (or "not
 * saved yet"). Read only. Access is the `role:driverCheck,superadmin`
 * middleware of the route group.
 */
final class ContactCatalogController extends Controller
{
    /** What the list can be narrowed to besides the contact statuses. */
    private const UNASSIGNED = 'unassigned';

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:added,pending,failed,skipped,' . self::UNASSIGNED],
            'account_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'presence' => ['nullable', 'in:on_account,not_on_account'],
            'bots' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:created_at,added_at,name'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $direction = ($data['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = TelegramScrapedUser::query()
            ->with(['groups:id,title,link', 'contact.account:id,phone,username', 'accountLinks.account:id,phone'])
            ->when(! $request->boolean('bots'), fn ($q) => $q->where('is_bot', false))
            ->when(filled($data['search'] ?? null), function ($q) use ($data) {
                $term = trim((string) $data['search']);
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], ltrim($term, '@')) . '%';

                $q->where(function ($q) use ($like, $term) {
                    $q->where('username', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('phone', 'like', $like);

                    if (ctype_digit($term)) {
                        $q->orWhere('telegram_user_id', (int) $term);
                    }
                });
            })
            ->when(($data['status'] ?? null) === self::UNASSIGNED, fn ($q) => $q->whereDoesntHave('contact'))
            ->when(
                filled($data['status'] ?? null) && $data['status'] !== self::UNASSIGNED,
                fn ($q) => $q->whereHas('contact', fn ($c) => $c->where('status', $data['status'])),
            )
            ->when(
                filled($data['account_id'] ?? null),
                fn ($q) => $q->whereHas('contact', fn ($c) => $c->where('telegram_account_id', (int) $data['account_id'])),
            )
            ->when(($data['presence'] ?? null) === 'on_account', fn ($q) => $q->whereHas('accountLinks'))
            ->when(($data['presence'] ?? null) === 'not_on_account', fn ($q) => $q->whereDoesntHave('accountLinks'))
            ->when(
                filled($data['group_id'] ?? null),
                fn ($q) => $q->whereHas('groups', fn ($g) => $g->where('telegram_scrape_groups.id', (int) $data['group_id'])),
            );

        match ($data['sort'] ?? 'created_at') {
            'name' => $query->orderBy('first_name', $direction)->orderBy('last_name', $direction),
            'added_at' => $query->orderBy(
                TelegramAccountContact::query()
                    ->select('added_at')
                    ->whereColumn('telegram_account_contacts.scraped_user_id', 'telegram_scraped_users.id'),
                $direction,
            ),
            default => $query->orderBy('created_at', $direction),
        };

        /* Accounts that have been read at least once: only for them "not on the account" is a fact. */
        $read = TelegramAccountContactState::query()
            ->where(fn ($q) => $q->whereNotNull('synced_at')->orWhereNotNull('cleared_account_at'))
            ->pluck('telegram_account_id')
            ->all();

        $page = $query->orderBy('id', $direction)->paginate((int) ($data['per_page'] ?? 20), ['*'], 'page', max(1, $request->integer('page', 1)));

        return response()->json([
            'data' => $page->getCollection()->map(fn (TelegramScrapedUser $user) => $this->row($user, $read))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'stats' => $this->stats(),
            'options' => $this->options(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(TelegramScrapedUser $user, array $read): array
    {
        $contact = $user->contact;

        /*
         * Where the person is: always in the database (they are a row of it),
         * and on the account that saved them - yes, no, or not known when that
         * account was never read.
         */
        $owner = $contact !== null && in_array($contact->status, ['added', 'skipped'], true) ? $contact->telegram_account_id : null;
        $holders = $user->accountLinks->map(fn ($link) => ['id' => $link->telegram_account_id, 'phone' => $link->account?->phone])->values();

        $onAccount = match (true) {
            $owner === null => null,
            $holders->contains('id', $owner) => true,
            in_array($owner, $read, true) => false,
            default => null,
        };

        return [
            'id' => $user->id,
            'telegram_user_id' => $user->telegram_user_id,
            'username' => $user->username,
            'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'phone' => $user->phone,
            'is_bot' => $user->is_bot,
            'found_at' => $user->created_at,
            'groups' => $user->groups
                ->map(fn ($group) => ['id' => $group->id, 'title' => $group->title ?: $group->link])
                ->values(),
            'holders' => $holders,
            'on_account' => $onAccount,
            'status' => $contact?->status ?? self::UNASSIGNED,
            'error' => $contact?->error,
            'added_at' => $contact?->added_at,
            'account' => $contact?->account ? [
                'id' => $contact->account->id,
                'phone' => $contact->account->phone,
                'username' => $contact->account->username,
            ] : null,
        ];
    }

    /**
     * What the group and account filters choose from: the groups that are
     * read, and the accounts that saved somebody.
     *
     * @return array{groups: list<array<string, mixed>>, accounts: list<array<string, mixed>>}
     */
    private function options(): array
    {
        $holders = TelegramAccountContact::query()->distinct()->pluck('telegram_account_id');

        return [
            'groups' => TelegramScrapeGroup::query()
                ->orderBy('id')
                ->get(['id', 'title', 'link'])
                ->map(fn ($group) => ['id' => $group->id, 'title' => $group->title ?: $group->link])
                ->values()
                ->all(),
            'accounts' => TelegramAccount::query()
                ->whereIn('id', $holders)
                ->orderBy('id')
                ->get(['id', 'phone'])
                ->map(fn ($account) => ['id' => $account->id, 'phone' => $account->phone])
                ->values()
                ->all(),
        ];
    }

    /**
     * The numbers above the list: people (not bots) and what became of them.
     *
     * @return array<string, int>
     */
    private function stats(): array
    {
        $byStatus = TelegramAccountContact::query()
            ->whereHas('user', fn ($q) => $q->where('is_bot', false))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $people = TelegramScrapedUser::query()->where('is_bot', false)->count();
        $held = (int) $byStatus->sum();

        return [
            'people' => $people,
            'added' => (int) ($byStatus[TelegramAccountContact::STATUS_ADDED] ?? 0),
            'pending' => (int) ($byStatus[TelegramAccountContact::STATUS_PENDING] ?? 0),
            'failed' => (int) ($byStatus[TelegramAccountContact::STATUS_FAILED] ?? 0),
            'skipped' => (int) ($byStatus[TelegramAccountContact::STATUS_SKIPPED] ?? 0),
            'unassigned' => max(0, $people - $held),
            'today' => TelegramAccountContact::query()
                ->where('status', TelegramAccountContact::STATUS_ADDED)
                ->where('added_at', '>=', now()->startOfDay())
                ->count(),
        ];
    }
}
