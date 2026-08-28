<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use App\Models\Driver\TelegramDriver;
use App\Models\Driver\TelegramDriverCheck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

        'driver_id',

        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'telegram_raw' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function telegramAccount(): BelongsTo
    {
        return $this->belongsTo(
            TelegramAccount::class,
            'telegram_account_id',
        );
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(
            TelegramDriver::class,
            'driver_id',
        );
    }

    public function checks(): HasMany
    {
        return $this->hasMany(
            TelegramDriverCheck::class,
            'telegram_resolved_phone_id',
        );
    }
}