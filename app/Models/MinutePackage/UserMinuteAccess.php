<?php 
namespace App\Models\MinutePackage;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserMinuteAccess extends Model
{
    protected $table = 'user_minute_access';

    protected $fillable = [
        'user_id',
        'is_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}