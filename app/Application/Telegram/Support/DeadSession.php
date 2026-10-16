<?php

declare(strict_types=1);

namespace App\Application\Telegram\Support;

use App\Models\Telegram\TelegramAccount;
use danog\MadelineProto\RPCErrorException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A Telegram session that is gone: the account logged out of it, was banned
 * or deactivated, or its key was used from two places and dropped. Not "this
 * one call failed" - every call on it will, for every process.
 *
 * The contact sync, the contacts accounts page and the phone resolver all meet
 * it; this is the one place that tells it from other errors and writes it
 * down the way telegram:account-check does (the account is no longer
 * authorized, status revoked), so no pool hands the account out again and the
 * sessions page shows it as revoked, to log in anew.
 */
final class DeadSession
{
    /** @var list<string> */
    public const CODES = [
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
     * The code of a dead-session error anywhere in the chain (MadelineProto
     * often wraps the RPC error in another exception), or null.
     */
    public static function code(Throwable $e): ?string
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $text = $current instanceof RPCErrorException ? $current->rpc : $current->getMessage();

            foreach (self::CODES as $code) {
                if (str_contains($text, $code)) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * Write the account down as revoked. Never throws: it is called on the
     * way out of an error, and must not hide it.
     */
    public static function retire(int|string|null $accountId, string $code): void
    {
        if ($accountId === null || $accountId === '') {
            return;
        }

        try {
            TelegramAccount::query()->whereKey((int) $accountId)->update([
                'is_authorized' => false,
                'status' => TelegramAccount::STATUS_REVOKED,
                'last_checked_at' => now(),
                'last_error' => $code,
            ]);

            Log::warning('Telegram session is dead, the account is marked as revoked', [
                'account_id' => (int) $accountId,
                'code' => $code,
            ]);
        } catch (Throwable $e) {
            Log::warning('A dead session could not be written down', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
