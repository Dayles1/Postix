<?php

namespace App\Models;

use App\Models\Peer;
use App\Models\TelegramMessage;
use App\Models\UserPhone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MessageGroup extends Model
{
    protected $fillable = [
        'user_phone_id',
        'status',
        'message_text',
        'interval',
        'total_batches',
        'current_batch'
    ];

    public function phone()
    {
        return $this->belongsTo(UserPhone::class, 'user_phone_id');
    }

    public function messages()
    {
        return $this->hasMany(TelegramMessage::class);
    }
    public function catalogs()
    {
        return $this->belongsToMany(Catalog::class, 'catalog_message_group');
    }
        public function countsByStatus(): array
    {
        $rows = $this->messages()
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return $rows;
    }


}

