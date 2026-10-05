<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use App\Enums\Telegram\TelegramClientCheckStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A CRM penalty forwarded to the person responsible for the request.
 *
 * @property int                       $id
 * @property int                       $telegram_chat_id
 * @property int                       $telegram_message_id
 * @property string|null               $message_text
 * @property array|null                $parsed
 * @property string|null               $request_number
 * @property int                       $repeat_number
 * @property string|null               $responsible_role
 * @property string|null               $responsible_name
 * @property int|null                  $operation_user_id
 * @property int                       $level
 * @property array|null                $metrics
 * @property int|null                  $phrase_index
 * @property string|null               $comment
 * @property int|null                  $batch_count
 * @property TelegramClientCheckStatus $status
 * @property string|null               $reason
 * @property int                       $attempts
 * @property string|null               $peer
 */
class TelegramClientCheck extends Model
{
    public const REASON_RESPONSIBLE_MISSING = 'responsible_missing';

    public const REASON_RESPONSIBLE_UNREACHABLE = 'responsible_unreachable';

    public const REASON_FORWARD_FAILED = 'forward_failed';

    public const REASON_COMMENT_FAILED = 'comment_failed';

    /**
     * Penalties were switched off in the panel when this one arrived.
     */
    public const REASON_DISABLED = 'disabled';

    /**
     * Forwarded, but comments were switched off: nothing followed it.
     */
    public const REASON_COMMENT_DISABLED = 'comment_disabled';

    /**
     * Penalties are switched off for the person's role (operators / sales).
     */
    public const REASON_ROLE_DISABLED = 'role_disabled';

    /**
     * The person's role sends nothing at this penalty's level.
     */
    public const REASON_LEVEL_OFF = 'level_off';

    /**
     * Forwarded; the level says "no comment" for the person's role.
     */
    public const REASON_FORWARD_ONLY = 'forward_only';

    /**
     * Came in outside the working hours (09:00-18:00 by default): ignored.
     */
    public const REASON_OUTSIDE_HOURS = 'outside_hours';

    protected $fillable = [
        'telegram_chat_id',
        'telegram_message_id',
        'message_text',
        'telegram_raw',
        'parsed',
        'request_number',
        'repeat_number',
        'crm_status',
        'status_since',
        'responsible_role',
        'responsible_name',
        'operation_user_id',
        'level',
        'metrics',
        'comment_level',
        'phrase_index',
        'comment',
        'batch_count',
        'status',
        'reason',
        'attempts',
        'error',
        'peer',
        'forwarded_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'telegram_chat_id' => 'integer',
            'telegram_message_id' => 'integer',
            'telegram_raw' => 'array',
            'parsed' => 'array',
            'repeat_number' => 'integer',
            'status_since' => 'datetime',
            'level' => 'integer',
            'metrics' => 'array',
            'comment_level' => 'integer',
            'phrase_index' => 'integer',
            'batch_count' => 'integer',
            'status' => TelegramClientCheckStatus::class,
            'attempts' => 'integer',
            'forwarded_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function operationUser(): BelongsTo
    {
        return $this->belongsTo(
            OperationUser::class,
            'operation_user_id',
        );
    }
}
