<?php

namespace App\Models\Driver;

use App\Enums\Drivers\TelegramDriverCheckReason;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use Illuminate\Database\Eloquent\Model;

class TelegramDriverCheck extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'telegram_message_id',

        'message_text',

        'phone_raw',
        'phone_normalized',

        'driver_name',

        'telegram_user_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',

        'status',
        'reason',

        'attempts',
        'error_message',

        'telegram_raw',

        'checked_at',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TelegramDriverCheckStatus::class,
            'reason' => TelegramDriverCheckReason::class,

            'telegram_raw' => 'array',

            'checked_at' => 'datetime',
            'reported_at' => 'datetime',
        ];
    }
}