<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per account: what it held when it was last read, and whether a read or a
 * clean-up is waiting, running or went wrong.
 *
 * @property int         $telegram_account_id
 * @property string      $status
 * @property string|null $action
 * @property int         $in_account
 */
class TelegramAccountContactState extends Model
{
    public const IDLE = 'idle';
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const FAILED = 'failed';

    public const IMPORT = 'import';
    public const CLEAR_ACCOUNT = 'clear_account';

    protected $fillable = [
        'telegram_account_id',
        'status',
        'action',
        'in_account',
        'synced_at',
        'cleared_account_at',
        'cleared_db_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'in_account' => 'integer',
            'synced_at' => 'datetime',
            'cleared_account_at' => 'datetime',
            'cleared_db_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class, 'telegram_account_id');
    }

    /**
     * The row of an account, made on first use.
     */
    public static function of(int $accountId): self
    {
        return self::query()->firstOrCreate(['telegram_account_id' => $accountId]);
    }
}
