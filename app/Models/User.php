<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Limit;
use App\Models\MinutePackage\UserMinuteAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use SoftDeletes;
    // use LogsActivity;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'telegram_id',
        'department_id',
        'oferta_read',
        'role_id',
        'email',
        'password',
        'state',
        'value',
        'created_by',

        'trial_started_at',
        'trial_expires_at',
        'has_used_trial',
        'trial_source',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    // public function getActivitylogOptions(): LogOptions
    // {
    //     return LogOptions::defaults()
    //         ->useLogName('user')
    //         ->logFillable()
    //         ->logOnlyDirty()
    //         ->dontSubmitEmptyLogs(); 
    // }
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function phones()
    {
        return $this->hasMany(UserPhone::class);
    }
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
    public function ban()
    {
        return $this->morphOne(Ban::class, 'bannable');
    }
    public function catalogs()
    {
        return $this->hasMany(Catalog::class);
    }
    public function avatar()
    {
        return $this->morphOne(Image::class, 'imageable');
    }
    public function getAvatarUrlAttribute()
    {
        try {
            $av = $this->avatar;
            if ($av instanceof \Illuminate\Support\Collection) {
                $av = $av->first();
            }
            if ($av && isset($av->path) && $av->path) {
                return asset('storage/' . ltrim($av->path, '/'));
            }
        } catch (\Throwable $e) {
        }
        return null;
    }
    public function getAvatarLetterAttribute()
    {
        $name = $this->name ?? $this->username ?? 'U';
        return mb_strtoupper(mb_substr($name, 0, 1));
    }
    public function limit()
    {
        return $this->morphOne(Limit::class, 'limitable');
    }
    public function minuteAccess()
    {
        return $this->hasOne(UserMinuteAccess::class, 'user_id', 'id');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by');
    }
    public function hasRole(string $role): bool
{
    return $this->role && $this->role->name === $role;
}

    public function isTrialActive(): bool
    {
        if (!$this->trial_started_at || !$this->trial_expires_at) return false;
        return Carbon::now()->lessThanOrEqualTo($this->trial_expires_at);
    }
    public function trialDaysLeft(): int
    {
        if (!$this->isTrialActive()) return 0;
        return Carbon::now()->diffInDays(Carbon::parse($this->trial_expires_at)) + 1;
    }
    public function hasEverUsedTrial(): bool
    {
        return (bool) $this->has_used_trial;
    }
    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }
    public function hasPermission(string $key): bool
    {
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains('key', $key);
        }
        return $this->permissions()->where('key', $key)->exists();
    }
}
