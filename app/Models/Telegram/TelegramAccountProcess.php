<?php
namespace App\Models\Telegram;

use App\Enums\Telegram\TelegramAccountProcess as TelegramAccountProcessEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramAccountProcess extends Model
{
    protected $fillable = [
        'telegram_account_id',
        'process',

        'successes',
        'failures',
        'consecutive_failures',

        'is_available',
        'is_busy',

        'busy_at',

        'disabled_at',
        'disabled_reason',

        'meta',
    ];

    protected $casts = [
        'process' =>
            TelegramAccountProcessEnum::class,

        'is_available' =>
            'boolean',

        'is_busy' =>
            'boolean',

        'busy_at' =>
            'datetime',

        'disabled_at' =>
            'datetime',

        'meta' =>
            'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            TelegramAccount::class,
            'telegram_account_id',
        );
    }

    /**
     * Successful resolver operation.
     */
    public function registerSuccess(): void
    {
        $this->increment(
            'successes',
        );

        $this->update([
            'consecutive_failures' => 0,
        ]);
    }

    /**
     * Real account failure.
     */
    public function registerFailure(
        ?string $reason = null,
        int $maxConsecutiveFailures = 7,
    ): void {
        $this->increment(
            'failures',
        );

        $this->increment(
            'consecutive_failures',
        );

        $this->refresh();

        if (
            $this->consecutive_failures >=
            $maxConsecutiveFailures
        ) {
            $this->disable(
                $reason,
            );
        }
    }

    /**
     * Phone is simply not registered.
     *
     * This does NOT hurt the account.
     */
    public function registerNotFound(
        ?string $reason = null,
    ): void {
        $this->increment(
            'failures',
        );

        $this->refresh();

        $meta = $this->meta;

        if (!is_array($meta)) {
            $meta = [];
        }

        $meta[
            'last_not_found_reason'
        ] = $reason;

        $meta[
            'last_not_found_at'
        ] =
            now()->toISOString();

        $this->update([
            'meta' => $meta,
        ]);
    }

    public function disable(
        ?string $reason = null,
    ): void {
        $this->update([
            'is_available' => false,

            'is_busy' => false,
            'busy_at' => null,

            'disabled_at' => now(),
            'disabled_reason' => $reason,
        ]);
    }

    public function enable(): void
    {
        $this->update([
            'is_available' => true,

            'is_busy' => false,
            'busy_at' => null,

            'disabled_at' => null,
            'disabled_reason' => null,

            'consecutive_failures' => 0,
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->is_available
            && !$this->is_busy
            && $this->consecutive_failures < 7;
    }
}