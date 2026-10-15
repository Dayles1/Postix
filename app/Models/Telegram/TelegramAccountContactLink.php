<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An account has this person in its Telegram contacts. Many accounts may
 * hold the same person (unlike TelegramAccountContact, the one-owner claim).
 */
class TelegramAccountContactLink extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'telegram_account_id',
        'scraped_user_id',
        'sync_token',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class, 'telegram_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TelegramScrapedUser::class, 'scraped_user_id');
    }
}
