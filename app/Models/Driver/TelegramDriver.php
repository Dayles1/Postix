<?php


namespace App\Models\Driver;

use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramResolvedPhone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramDriver extends Model
{
    protected $fillable = [
        'name',
        'name_normalized',
        'operation_user_id',
        'status',
    ];

    public function operationUser(): BelongsTo
    {
        return $this->belongsTo(
            OperationUser::class,
            'operation_user_id',
        );
    }

    public function resolvedPhones(): HasMany
    {
        return $this->hasMany(
            TelegramResolvedPhone::class,
            'driver_id',
        );
    }

    public function checks(): HasMany
    {
        return $this->hasMany(
            TelegramDriverCheck::class,
            'driver_id',
        );
    }
}