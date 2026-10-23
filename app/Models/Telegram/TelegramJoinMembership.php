<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One account in one group. Unique by the pair: an account tries a group once.
 *
 * @property int    $id
 * @property int    $group_id
 * @property int    $telegram_account_id
 * @property string $status
 */
class TelegramJoinMembership extends Model
{
    public const STATUS_JOINED = 'joined';

    /* The group asks its admins first: in once they say yes. */
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'group_id',
        'telegram_account_id',
        'status',
        'error',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TelegramScrapeGroup::class, 'group_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class, 'telegram_account_id');
    }
}
