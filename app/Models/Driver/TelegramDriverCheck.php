<?php

declare(strict_types=1);

namespace App\Models\Driver;

use App\Enums\Drivers\TelegramDriverCheckReason;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Enums\Drivers\TelegramDriverCheckType;
use App\Enums\Drivers\TelegramDriverMessageType;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramResolvedPhone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramDriverCheck extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'telegram_message_id',

        'type',

        'message_text',

        'phone_raw',
        'phone_normalized',

        'driver_name',

        'driver_id',
        'operation_user_id',
        'telegram_resolved_phone_id',

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
            'type' => TelegramDriverMessageType::class,

            'status' =>
                TelegramDriverCheckStatus::class,

            'reason' =>
                TelegramDriverCheckReason::class,

            'telegram_raw' => 'array',

            'checked_at' => 'datetime',

            'reported_at' => 'datetime',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(
            TelegramDriver::class,
            'driver_id',
        );
    }

    public function operationUser(): BelongsTo
    {
        return $this->belongsTo(
            OperationUser::class,
            'operation_user_id',
        );
    }

    public function resolvedPhone(): BelongsTo
    {
        return $this->belongsTo(
            TelegramResolvedPhone::class,
            'telegram_resolved_phone_id',
        );
    }
}