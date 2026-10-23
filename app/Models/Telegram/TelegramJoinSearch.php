<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One query of Telegram's global search, made of a keyword (and a city).
 *
 * @property int    $id
 * @property int    $keyword_id
 * @property string $query
 */
class TelegramJoinSearch extends Model
{
    protected $fillable = [
        'keyword_id',
        'query',
        'last_searched_at',
        'found_count',
        'locked_until',
    ];

    protected function casts(): array
    {
        return [
            'found_count' => 'integer',
            'last_searched_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(TelegramJoinKeyword::class, 'keyword_id');
    }
}
