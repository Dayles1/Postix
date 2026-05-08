<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class UserPhone extends Model
{
    use SoftDeletes;

    // use LogsActivity;
    
    protected $fillable = [
        'user_id',
        'phone',
        'telegram_user_id',
        'session_path',
        'state',
        'code',
        'is_active'
    ];
    // public function getActivitylogOptions(): LogOptions
    // {
    //     return LogOptions::defaults()
    //         ->useLogName('user')
    //         ->logFillable()
    //         ->logOnlyDirty()
    //         ->dontSubmitEmptyLogs(); 
    // }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messageGroups()
    {
        return $this->hasMany(MessageGroup::class, 'user_phone_id');
    }

    public function catalogs()
    {
        return $this->hasMany(Catalog::class);
    }
    public function ban()
    {
        return $this->morphOne(Ban::class, 'bannable');
    }
}
