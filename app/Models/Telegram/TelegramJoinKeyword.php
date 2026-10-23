<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A word the group join searches public groups by - alone, and with every
 * city of config('group_join.places') when `expand` is on.
 *
 * @property int         $id
 * @property string      $keyword
 * @property string|null $topic
 * @property string      $source seed | manual | learned
 * @property bool        $expand
 * @property bool        $is_active
 */
class TelegramJoinKeyword extends Model
{
    protected $fillable = [
        'keyword',
        'topic',
        'source',
        'expand',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expand' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TelegramScrapeGroup::class, 'keyword_id');
    }

    public function searches(): HasMany
    {
        return $this->hasMany(TelegramJoinSearch::class, 'keyword_id');
    }

    /**
     * What Telegram is asked for this word.
     *
     * @return list<string>
     */
    public function queries(): array
    {
        $queries = [$this->keyword];

        if ($this->expand) {
            foreach ((array) config('group_join.places', []) as $place) {
                $queries[] = mb_substr($this->keyword . ' ' . $place, 0, 150);
            }
        }

        return $queries;
    }
}
