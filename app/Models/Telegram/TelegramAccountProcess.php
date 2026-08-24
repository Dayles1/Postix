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
        'process' => TelegramAccountProcessEnum::class,

        'is_available' => 'boolean',
        'is_busy' => 'boolean',

        'busy_at' => 'datetime',
        'disabled_at' => 'datetime',

        'meta' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            TelegramAccount::class,
            'telegram_account_id'
        );
    }

    public function registerSuccess(): void
    {
        $this->increment('successes');

        $this->update([
            'consecutive_failures' => 0,
        ]);
    }

    public function registerFailure(
        ?string $reason = null,
        int $maxConsecutiveFailures = 3
    ): void {
        $this->increment('failures');
        $this->increment('consecutive_failures');

        $this->refresh();

        if ($this->consecutive_failures >= $maxConsecutiveFailures) {
            $this->disable($reason);
        }
    }

    public function disable(?string $reason = null): void
    {
        $this->update([
            'is_available' => false,
            'disabled_at' => now(),
            'disabled_reason' => $reason,
        ]);
    }

    public function enable(): void
    {
        $this->update([
            'is_available' => true,
            'disabled_at' => null,
            'disabled_reason' => null,
            'consecutive_failures' => 0,
        ]);
    }

    public function acquire(): bool
    {
        if (!$this->is_available || $this->is_busy) {
            return false;
        }

        $this->update([
            'is_busy' => true,
            'busy_at' => now(),
        ]);

        return true;
    }

    public function release(): void
    {
        $this->update([
            'is_busy' => false,
            'busy_at' => null,
        ]);
    }

    public function isAvailable(): bool
    {
        return $this->is_available && !$this->is_busy;
    }
}