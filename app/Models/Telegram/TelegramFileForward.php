<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file of the notifications bot sent to one group - or, with no
 * target, a file no route recognised.
 *
 * @property int                 $id
 * @property int|null            $file_route_id
 * @property int                 $source_chat_id
 * @property int                 $message_id
 * @property string|null         $file_name
 * @property string|null         $caption
 * @property int|null            $target_chat_id
 * @property string              $status
 * @property int                 $attempts
 * @property string|null         $error
 */
class TelegramFileForward extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNMATCHED = 'unmatched';

    /**
     * Tries before a refused forward is left alone.
     */
    public const MAX_ATTEMPTS = 3;

    protected $fillable = [
        'file_route_id',
        'source_chat_id',
        'message_id',
        'file_name',
        'caption',
        'target_chat_id',
        'status',
        'attempts',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'source_chat_id' => 'integer',
            'message_id' => 'integer',
            'target_chat_id' => 'integer',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(
            TelegramFileRoute::class,
            'file_route_id',
        );
    }
}
