<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the contact sync log shown in the admin panel.
 */
class TelegramContactSyncLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'run_id',
        'telegram_account_id',
        'group_id',
        'event',
        'message',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class, 'telegram_account_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TelegramScrapeGroup::class, 'group_id');
    }
}
