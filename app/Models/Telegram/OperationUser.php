<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use App\Models\Driver\TelegramDriver;
use App\Models\Driver\TelegramDriverCheck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OperationUser extends Model
{
    protected $fillable = [
        'name',
        'name_normalized',
        'telegram_username',
        'telegram_id',
        'is_active',
        'dm_enabled',
        'dm_last_sent_at',
        'dm_last_error',
    ];

    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
            'is_active' => 'boolean',
            'dm_enabled' => 'boolean',
            'dm_last_sent_at' => 'datetime',
        ];
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(
            TelegramDriver::class,
            'operation_user_id',
        );
    }

    public function checks(): HasMany
    {
        return $this->hasMany(
            TelegramDriverCheck::class,
            'operation_user_id',
        );
    }

    /**
     * The lookup key that ties a manually created operator to the
     * "Пользователь:" line of an incoming Telegram message.
     *
     * Both the parser ({@see \App\Application\Telegram\Actions\ResolveOperationUser})
     * and the operators page go through here, otherwise an operator entered by
     * hand would never be matched to the messages it is supposed to receive.
     */
    public static function normalizeName(
        string $name,
    ): string {
        $name = trim($name);

        $name = preg_replace(
            '/\s+/u',
            ' ',
            $name,
        ) ?? $name;

        return Str::upper($name);
    }

    /**
     * Username without the leading "@", or null when none is stored.
     */
    public function telegramUsername(): ?string
    {
        $username = ltrim(
            trim(
                (string) $this->telegram_username,
            ),
            '@',
        );

        return $username !== ''
            ? $username
            : null;
    }

    /**
     * Whether a report may be copied into this operator's private chat.
     *
     * The switch must be on AND the operator must be reachable: without a
     * username or an id there is no peer to send to.
     */
    public function canReceiveDirectMessages(): bool
    {
        return $this->dm_enabled
            && $this->hasTelegramPeer();
    }

    public function hasTelegramPeer(): bool
    {
        return $this->telegramUsername() !== null
            || ($this->telegram_id ?? 0) > 0;
    }

    /**
     * Peers to try, in order of reliability.
     *
     * A username is resolved by Telegram itself, so it works even when the
     * account has never met the operator before. A bare user id only resolves
     * when the peer is already known to the session, which makes it the
     * fallback rather than the first choice.
     *
     * @return list<string|int>
     */
    public function telegramPeerCandidates(): array
    {
        $candidates = [];

        $username = $this->telegramUsername();

        if ($username !== null) {
            $candidates[] = '@' . $username;
        }

        if (($this->telegram_id ?? 0) > 0) {
            $candidates[] = (int) $this->telegram_id;
        }

        return $candidates;
    }
}
