<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramResolvedPhone extends Model
{
    protected $fillable = [
        'phone_normalized',

        'telegram_user_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',

        'telegram_raw',

        'telegram_account_id',

        'resolved_at',
    ];

    protected $casts = [
        'telegram_raw' => 'array',
        'resolved_at' => 'datetime',
    ];

    public function telegramAccount(): BelongsTo
    {
        return $this->belongsTo(
            TelegramAccount::class,
            'telegram_account_id'
        );
    }
}