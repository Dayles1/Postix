<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A runtime switch of the Telegram flows, flipped in the panel.
 *
 * Read straight from the database on every use, never cached: the
 * listener is a long-running process, and a switch turned off in the panel
 * has to stop it on the very next message.
 *
 * @property string $key
 * @property mixed  $value
 */
class TelegramSetting extends Model
{
    /**
     * CRM penalties are forwarded to the person responsible at all.
     */
    public const CLIENT_CHECKS_ENABLED = 'client_checks.enabled';

    /**
     * The comment that follows a batch of forwarded penalties.
     */
    public const CLIENT_CHECK_COMMENTS_ENABLED = 'client_checks.comments_enabled';

    /**
     * Levels, conditions, phrases and timings (ClientCheckRules::toArray()).
     */
    public const CLIENT_CHECK_RULES = 'client_checks.rules';

    /**
     * Penalties go out to operators / to sales managers at all. Under the
     * main switch: off there is off for everyone.
     */
    public const CLIENT_CHECKS_OPERATION_ENABLED = 'client_checks.operation_enabled';

    public const CLIENT_CHECKS_SALES_ENABLED = 'client_checks.sales_enabled';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * The stored value, or $default when it was never set - or when the
     * table is not there yet: a missing migration must not take the
     * listener down with it.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $row = self::query()->where('key', $key)->first();
        } catch (Throwable $e) {
            Log::warning('Telegram setting could not be read, using the default', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return $default;
        }

        return $row === null ? $default : $row->value;
    }

    public static function set(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /**
     * Both switches are on until somebody turns one off, which is how the
     * flow behaved before they existed.
     */
    public static function clientChecksEnabled(): bool
    {
        return (bool) self::get(self::CLIENT_CHECKS_ENABLED, true);
    }

    public static function clientCheckCommentsEnabled(): bool
    {
        return (bool) self::get(self::CLIENT_CHECK_COMMENTS_ENABLED, true);
    }

    /**
     * The role's own switch; on until somebody turns it off.
     */
    public static function clientChecksEnabledFor(string $role): bool
    {
        return (bool) self::get(self::roleKey($role), true);
    }

    public static function roleKey(string $role): string
    {
        return $role === OperationUser::ROLE_SALES
            ? self::CLIENT_CHECKS_SALES_ENABLED
            : self::CLIENT_CHECKS_OPERATION_ENABLED;
    }
}
