<?php

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramAccount extends Model
{
    protected $fillable = [
        'phone',
        'session_path',
        'is_authorized',
        'authorized_at',
        'status',
    ];

    protected $casts = [
        'is_authorized' => 'boolean',
        'authorized_at' => 'datetime',
    ];

    public function processes(): HasMany
    {
        return $this->hasMany(TelegramAccountProcess::class);
    }
}