<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use App\Models\Driver\TelegramDriver;
use App\Models\Driver\TelegramDriverCheck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationUser extends Model
{
    protected $fillable = [
        'name',
        'name_normalized',
        'telegram_username',
        'telegram_id',
    ];

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
}