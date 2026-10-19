<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Services\DutyOwners;
use App\Http\Controllers\Controller;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramFileRoute;
use App\Models\Telegram\TelegramListener;
use App\Telegram\TelegramProcessLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The Listeners page: the accounts that run their own event loop next to
 * Driver Check (CEO, Head Operation ...), what each does, and whether its
 * process is up. Starting the processes is the supervisor's job
 * (telegram:listener-watchdog <key>); a row switched on here is picked up
 * by its waiting watchdog within seconds.
 */
final class ListenerController extends Controller
{
    public function __construct(private readonly DutyOwners $owners) {}

    public function index(): JsonResponse
    {
        $listeners = TelegramListener::query()->with('account')->orderBy('name')->get();

        $owners = $this->owners->current();

        return response()->json([
            'listeners' => $listeners->map(fn (TelegramListener $l): array => $this->row($l, $owners))->all(),
            'accounts' => $this->accounts($listeners),
            'duties' => TelegramListener::DUTIES,
            'owners' => $this->ownersPayload($listeners),
        ]);
    }

    /**
     * Who does what: the owner of each duty that can change hands.
     */
    public function updateOwners(Request $request): JsonResponse
    {
        $usable = TelegramListener::query()
            ->where('is_enabled', true)
            ->whereNotNull('telegram_account_id')
            ->pluck('key')
            ->all();

        $rules = [
            'owners' => ['required', 'array'],
            'roles' => ['sometimes', 'array'],
        ];

        foreach (TelegramListener::EXCLUSIVE_DUTIES as $duty) {
            $rules["owners.{$duty}"] = ['nullable', 'string', Rule::in($usable)];
        }

        /* Penalties by role: a listener, Driver Check, or (empty) the owner of "penalties" above. */
        foreach (OperationUser::ROLES as $role) {
            $rules["roles.{$role}"] = ['nullable', 'string', Rule::in([...$usable, DutyOwners::DRIVER_CHECK])];
        }

        $data = $request->validate($rules);

        $this->owners->save($data['owners']);

        if (isset($data['roles'])) {
            $this->owners->saveRoles($data['roles']);
        }

        $listeners = TelegramListener::query()->with('account')->orderBy('name')->get();

        return response()->json([
            'owners' => $this->ownersPayload($listeners),
            'listeners' => $listeners->map(fn (TelegramListener $l): array => $this->row($l, $this->owners->current()))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $listener = new TelegramListener();

        $this->fill($listener, $request, creating: true);

        return response()->json(['data' => $this->row($listener->load('account'), $this->owners->current())], 201);
    }

    public function update(Request $request, TelegramListener $listener): JsonResponse
    {
        $this->fill($listener, $request, creating: false);

        return response()->json(['data' => $this->row($listener->load('account'), $this->owners->current())]);
    }

    /**
     * Its scheduled messages go back to the Driver Check account.
     */
    public function destroy(TelegramListener $listener): JsonResponse
    {
        \App\Models\Telegram\TelegramScheduledMessage::query()
            ->where('listener_key', $listener->key)
            ->update(['listener_key' => null]);

        /* Its duties go back to Driver Check. */
        $this->owners->save(array_map(
            fn (?string $owner): ?string => $owner === $listener->key ? null : $owner,
            $this->owners->saved(),
        ));

        /* Its roles and its kinds of files follow the general owner again. */
        $this->owners->saveRoles(array_map(
            fn (?string $owner): ?string => $owner === $listener->key ? null : $owner,
            $this->owners->savedRoles(),
        ));

        TelegramFileRoute::query()
            ->where('listener_key', $listener->key)
            ->update(['listener_key' => null]);

        $listener->delete();

        return response()->json(['message' => __('telegram.listeners.messages.deleted')]);
    }

    private function fill(TelegramListener $listener, Request $request, bool $creating): void
    {
        $data = $request->validate([
            'key' => $creating
                ? ['required', 'regex:/^[a-z0-9_]{2,32}$/', Rule::unique('telegram_listeners', 'key')]
                : ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'telegram_account_id' => [
                'nullable',
                'integer',
                Rule::exists('telegram_accounts', 'id'),
                Rule::unique('telegram_listeners', 'telegram_account_id')->ignore($listener->id),
            ],
            'is_enabled' => ['required', 'boolean'],
            'duties' => ['present', 'array'],
            'duties.*' => ['string', Rule::in(TelegramListener::DUTIES)],
        ]);

        $accountId = $data['telegram_account_id'] ?? null;

        /* Driver Check's own account runs its own process. */
        if ($accountId !== null && (int) $accountId === (int) config('services.telegram.driver_check_account_id')) {
            abort(response()->json([
                'message' => __('telegram.listeners.errors.driver_check_account'),
                'errors' => ['telegram_account_id' => [__('telegram.listeners.errors.driver_check_account')]],
            ], 422));
        }

        if ($creating) {
            $listener->key = $data['key'];
        }

        $listener->fill([
            'name' => trim($data['name']),
            'telegram_account_id' => $accountId,
            'is_enabled' => (bool) $data['is_enabled'],
            'duties' => array_values(array_unique($data['duties'])),
        ])->save();
    }

    /**
     * Each duty that can change hands with whose it is now, and whom it can
     * be given to: Driver Check and the listeners that are on and have an
     * account. What the panel saved can name a listener that is off at the
     * moment (`saved`); the duty is then Driver Check's meanwhile (`owner`).
     *
     * @param iterable<TelegramListener> $listeners
     *
     * @return array{duties: list<array<string, mixed>>, options: list<array{key: string|null, name: string, running: bool}>}
     */
    private function ownersPayload(iterable $listeners): array
    {
        $current = $this->owners->current();
        $saved = $this->owners->saved();

        $options = [['key' => null, 'name' => 'Driver Check', 'running' => true]];

        foreach ($listeners as $listener) {
            if ($listener->is_enabled && $listener->telegram_account_id !== null) {
                $options[] = [
                    'key' => $listener->key,
                    'name' => $listener->name,
                    'running' => TelegramProcessLock::isHeld($listener->lockName()),
                ];
            }
        }

        $savedRoles = $this->owners->savedRoles();

        return [
            'duties' => array_map(fn (string $duty): array => [
                'duty' => $duty,
                'owner' => $current[$duty],
                'saved' => $saved[$duty],
            ], TelegramListener::EXCLUSIVE_DUTIES),
            /* Penalties by role: who delivers them now, and what the panel saved ('' follows "penalties"). */
            'roles' => array_map(fn (string $role): array => [
                'role' => $role,
                'owner' => $this->owners->penaltyOwner($role),
                'saved' => $savedRoles[$role],
            ], OperationUser::ROLES),
            'options' => $options,
        ];
    }

    /**
     * What is this listener's by DutyOwners: the penalties of a role
     * ("penalties:sales"), the files ("file_routes").
     *
     * @return list<string>
     */
    private function ownedDuties(TelegramListener $l): array
    {
        $roles = $this->owners->penaltyRoles($l->key);

        /* All the roles: just "penalties"; some of them: one entry each. */
        $duties = count($roles) === count(OperationUser::ROLES)
            ? [TelegramListener::DUTY_PENALTIES]
            : array_map(
                fn (string $role): string => TelegramListener::DUTY_PENALTIES . ':' . $role,
                $roles,
            );

        if ($this->owners->ownedBy(TelegramListener::DUTY_PENALTY_INTAKE, $l->key)) {
            array_unshift($duties, TelegramListener::DUTY_PENALTY_INTAKE);
        }

        if ($this->owners->ownsFiles($l->key)) {
            $duties[] = TelegramListener::DUTY_FILE_ROUTES;
        }

        return $duties;
    }

    /**
     * @param array<string, string|null> $owners
     *
     * @return array<string, mixed>
     */
    private function row(TelegramListener $l, array $owners): array
    {
        $account = $l->account;

        return [
            'id' => $l->id,
            'key' => $l->key,
            'name' => $l->name,
            'telegram_account_id' => $l->telegram_account_id,
            'account' => $account === null ? null : [
                'id' => $account->id,
                'label' => $this->accountLabel($account),
                'status' => $account->status,
                'is_authorized' => (bool) $account->is_authorized,
                'seen_at' => $account->last_checked_at?->toIso8601String(),
            ],
            'is_enabled' => (bool) $l->is_enabled,
            'duties' => $l->duties(),
            /* What it does besides: the duties that are its by DutyOwners. */
            'owned_duties' => $l->is_enabled && $l->telegram_account_id !== null
                ? $this->ownedDuties($l)
                : [],
            /* The process holds its lock while the event loop runs. */
            'running' => TelegramProcessLock::isHeld($l->lockName()),
            /* Someone will start it: the common watchdog, or one kept for this listener alone. */
            'supervised' => app(\App\Application\Telegram\Services\ListenersControl::class)->running()
                || TelegramProcessLock::isHeld($l->watchdogLockName()),
        ];
    }

    /**
     * The accounts to pick from: authorized, with a session, not Driver
     * Check's and not taken by another listener (this row's own stays).
     *
     * @param iterable<TelegramListener> $listeners
     *
     * @return list<array{id: int, label: string, taken_by: string|null}>
     */
    private function accounts(iterable $listeners): array
    {
        $taken = [];

        foreach ($listeners as $listener) {
            if ($listener->telegram_account_id !== null) {
                $taken[$listener->telegram_account_id] = $listener->name;
            }
        }

        $primary = (int) config('services.telegram.driver_check_account_id');

        return TelegramAccount::query()
            ->where('is_authorized', true)
            ->whereNotNull('session_path')
            ->where('session_path', '!=', '')
            ->when($primary > 0, fn ($q) => $q->where('id', '!=', $primary))
            ->orderBy('phone')
            ->get()
            ->map(fn (TelegramAccount $a): array => [
                'id' => (int) $a->id,
                'label' => $this->accountLabel($a),
                'taken_by' => $taken[$a->id] ?? null,
            ])
            ->all();
    }

    private function accountLabel(TelegramAccount $a): string
    {
        $name = trim(($a->first_name ?? '') . ' ' . ($a->last_name ?? ''));

        return trim($a->phone . ($name !== '' ? ' · ' . $name : ''));
    }
}
