<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Ban extends Model
{
    // use LogsActivity;

    protected $fillable = [
        'bannable_type',
        'bannable_id',
        'reason',
        'active',
        'until',
        'starts_at',
    ];

    protected $casts = [
        'active'    => 'boolean',
        'until'     => 'datetime',
        'starts_at' => 'datetime',
    ];

    public function bannable()
    {
        return $this->morphTo();
    }

    public function ensureActive()
    {
        if (!$this->active && $this->starts_at && $this->starts_at <= now()) {
            $this->active = true;
            $this->save();
        }
    }

    // public function getActivitylogOptions(): LogOptions
    // {
    //     return LogOptions::defaults()
    //         ->useLogName('ban')
    //         ->logFillable()
    //         ->logOnlyDirty()
    //         ->dontSubmitEmptyLogs(); 
    // }

    // ✅ Accessorlar timestamps uchun
    public function getStartsAtFormattedAttribute(): ?string
    {
        return $this->starts_at ? $this->starts_at->format('d.m.Y H:i:s') : null;
    }

    public function getUntilFormattedAttribute(): ?string
    {
        return $this->until ? $this->until->format('d.m.Y H:i:s') : null;
    }
    protected function startsAt(): Attribute
{
    return Attribute::make(
        set: fn ($value) => $value ? Carbon::parse($value)->setTimezone('Asia/Tashkent') : null,
    );
}

protected function until(): Attribute
{
    return Attribute::make(
        set: fn ($value) => $value ? Carbon::parse($value)->setTimezone('Asia/Tashkent') : null,
    );
}
}