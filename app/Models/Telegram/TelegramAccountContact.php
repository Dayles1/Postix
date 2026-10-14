<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who saved whom. scraped_user_id is unique: a person has one owner.
 *
 * @property int         $id
 * @property int         $telegram_account_id
 * @property int         $scraped_user_id
 * @property string      $status
 */
class TelegramAccountContact extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ADDED = 'added';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'telegram_account_id',
        'scraped_user_id',
        'status',
        'error',
        'added_at',
    ];

    protected function casts(): array
    {
        return [
            'added_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class, 'telegram_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(TelegramScrapedUser::class, 'scraped_user_id');
    }
}
