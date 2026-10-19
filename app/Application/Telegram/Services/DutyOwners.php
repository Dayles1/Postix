<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramFileRoute;
use App\Models\Telegram\TelegramListener;
use App\Models\Telegram\TelegramSetting;
use Throwable;

/**
 * Who does what: for each duty that can change hands (TelegramListener::EXCLUSIVE_DUTIES)
 * the one listener whose account does it - Driver Check, or one of the
 * listeners of telegram_listeners. Set in the panel (Listeners -> Who does what).
 *
 * Exclusive on purpose: a penalty is forwarded once and answered from one
 * account, a file is forwarded once. What was never set is Driver Check's, as
 * it was before listeners existed. An owner that is switched off, deleted or
 * left without an account gives the duty back to Driver Check by itself, so
 * nothing stops because of a click in the panel.
 *
 * Every listener process asks here before it acts, on every tick and every
 * message; no cache, so a change in the panel applies within seconds.
 */
final class DutyOwners
{
    public const KEY = 'listener.duty_owners';

    /**
     * The owner of a duty: a listener key, or null for Driver Check.
     */
    public function owner(string $duty): ?string
    {
        $key = $this->saved()[$duty] ?? null;

        if ($key === null) {
            return null;
        }

        return $this->usable($key) ? $key : null;
    }

    /**
     * Whether the listener ($key; null is Driver Check) does this duty.
     */
    public function ownedBy(string $duty, ?string $key): bool
    {
        return $this->owner($duty) === $key;
    }

    /**
     * The owner of every duty as it works right now.
     *
     * @return array<string, string|null>
     */
    public function current(): array
    {
        $owners = [];

        foreach (TelegramListener::EXCLUSIVE_DUTIES as $duty) {
            $owners[$duty] = $this->owner($duty);
        }

        return $owners;
    }

    /**
     * What the panel saved, which may name a listener that is off at the moment.
     *
     * @return array<string, string|null>
     */
    public function saved(): array
    {
        $saved = TelegramSetting::get(self::KEY);

        $owners = [];

        foreach (TelegramListener::EXCLUSIVE_DUTIES as $duty) {
            $key = is_array($saved) ? ($saved[$duty] ?? null) : null;

            $owners[$duty] = is_string($key) && $key !== '' ? $key : null;
        }

        return $owners;
    }

    /**
     * @param array<string, string|null> $owners
     *
     * @return array<string, string|null>
     */
    public function save(array $owners): array
    {
        $values = [];

        foreach (TelegramListener::EXCLUSIVE_DUTIES as $duty) {
            $key = $owners[$duty] ?? null;

            $values[$duty] = is_string($key) && $key !== '' ? $key : null;
        }

        TelegramSetting::set(self::KEY, $values);

        return $values;
    }

    /*
    |--------------------------------------------------------------------------
    | Penalties by role, files by route
    |--------------------------------------------------------------------------
    |
    | On top of the one owner above: the penalties of the sales managers and
    | those of the operators may be delivered from different accounts (the
    | head of sales writes to sales, the head of operations to operators), and
    | each kind of file may go out from its own account. A choice is a
    | listener key, DRIVER_CHECK, or nothing - which follows the general owner
    | of the duty. One that is off or without an account follows it too.
    |
    */

    public const ROLES_KEY = 'listener.penalty_role_owners';

    /**
     * "Driver Check does it", whoever the general owner is.
     */
    public const DRIVER_CHECK = '_dc';

    /**
     * Who delivers the penalties of this role: a listener key, null for
     * Driver Check.
     */
    public function penaltyOwner(string $role): ?string
    {
        return $this->resolve(
            $this->savedRoles()[$role] ?? null,
            $this->owner(TelegramListener::DUTY_PENALTIES),
        );
    }

    /**
     * The roles whose penalties the listener ($key; null is Driver Check) delivers.
     *
     * @return list<string>
     */
    public function penaltyRoles(?string $key): array
    {
        return array_values(array_filter(
            OperationUser::ROLES,
            fn (string $role): bool => $this->penaltyOwner($role) === $key,
        ));
    }

    public function ownsPenalties(?string $key): bool
    {
        return $this->penaltyRoles($key) !== [];
    }

    /**
     * What the panel saved for each role: a key, DRIVER_CHECK or null.
     *
     * @return array<string, string|null>
     */
    public function savedRoles(): array
    {
        $saved = TelegramSetting::get(self::ROLES_KEY);

        $roles = [];

        foreach (OperationUser::ROLES as $role) {
            $key = is_array($saved) ? ($saved[$role] ?? null) : null;

            $roles[$role] = is_string($key) && $key !== '' ? $key : null;
        }

        return $roles;
    }

    /**
     * @param array<string, string|null> $roles
     *
     * @return array<string, string|null>
     */
    public function saveRoles(array $roles): array
    {
        $values = [];

        foreach (OperationUser::ROLES as $role) {
            $key = $roles[$role] ?? null;

            $values[$role] = is_string($key) && $key !== '' ? $key : null;
        }

        TelegramSetting::set(self::ROLES_KEY, $values);

        return $values;
    }

    /**
     * Who forwards the files of a route, $routeKey being what the route
     * saved (TelegramFileRoute::$listener_key).
     */
    public function fileOwner(?string $routeKey): ?string
    {
        return $this->resolve($routeKey, $this->owner(TelegramListener::DUTY_FILE_ROUTES));
    }

    /**
     * Whether the listener ($key; null is Driver Check) forwards files of
     * any route: as the general owner, or by a route's own choice.
     */
    public function ownsFiles(?string $key): bool
    {
        if ($this->ownedBy(TelegramListener::DUTY_FILE_ROUTES, $key)) {
            return true;
        }

        try {
            return TelegramFileRoute::query()
                ->where('is_active', true)
                ->whereNotNull('listener_key')
                ->pluck('listener_key')
                ->contains(fn (string $routeKey): bool => $this->fileOwner($routeKey) === $key);
        } catch (Throwable) {
            /* The column is not there yet (migration pending): the general owner only. */
            return false;
        }
    }

    /**
     * A choice ('' / null: follow $general) as the key that does it now.
     */
    private function resolve(?string $choice, ?string $general): ?string
    {
        if ($choice === null || $choice === '') {
            return $general;
        }

        if ($choice === self::DRIVER_CHECK) {
            return null;
        }

        return $this->usable($choice) ? $choice : $general;
    }

    /**
     * A listener that is on and has an account to do the duty with.
     */
    private function usable(string $key): bool
    {
        return TelegramListener::query()
            ->where('key', $key)
            ->where('is_enabled', true)
            ->whereNotNull('telegram_account_id')
            ->exists();
    }
}
