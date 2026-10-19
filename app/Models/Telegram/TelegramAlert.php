<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;

/**
 * A problem in the Telegram flows told to the developer (DeveloperAlerts).
 *
 * @property int         $id
 * @property string      $kind
 * @property string      $fingerprint
 * @property string      $title
 * @property string|null $details
 * @property array|null  $context
 * @property string|null $listener_key
 * @property int         $occurrences
 */
class TelegramAlert extends Model
{
    protected $fillable = [
        'kind',
        'fingerprint',
        'title',
        'details',
        'context',
        'listener_key',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
        'notified_at',
        'notify_error',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurrences' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }
}
