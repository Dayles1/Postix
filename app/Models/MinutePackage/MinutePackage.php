<?php

namespace App\Models\MinutePackage;

use Illuminate\Database\Eloquent\Model;

class MinutePackage extends Model
{
    protected $fillable = ['minutes'];

    public function users()
{
    return $this->belongsToMany(\App\Models\User::class, 'user_minute_package')
                ->withTimestamps();
}
}
