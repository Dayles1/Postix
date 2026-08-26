<?php

namespace App\Models\Telegram;

use App\Models\Telegram\TelegramDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationUser extends Model
{
    protected $fillable = [
        'name',
        'name_normalized',
    ];

    public function drivers(): HasMany
    {
        return $this->hasMany(
            TelegramDriver::class,
            'operation_user_id'
        );
    }
}