<?php

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramAccount extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Statuses
    |--------------------------------------------------------------------------
    |
    | Login:   processing -> code_sent -> verifying -> [need_password ->
    |          verifying ->] success.
    |          A wrong password lands on password_invalid, which accepts
    |          another try. A wrong code lands on code_invalid, which does
    |          not: completePhoneLogin() drops the login state before it
    |          asks Telegram, so only a fresh code can follow.
    | Later:   checking -> success | revoked, logging_out -> logged_out.
    |
    */

    public const STATUS_CREATED = 'created';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_CODE_SENT = 'code_sent';
    public const STATUS_CODE_INVALID = 'code_invalid';
    public const STATUS_VERIFYING = 'verifying';
    public const STATUS_NEED_PASSWORD = 'need_password';
    public const STATUS_PASSWORD_INVALID = 'password_invalid';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CHECKING = 'checking';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_LOGGING_OUT = 'logging_out';
    public const STATUS_LOGGED_OUT = 'logged_out';

    /**
     * A CLI process is working on the account right now.
     */
    public const IN_FLIGHT_STATUSES = [
        self::STATUS_PROCESSING,
        self::STATUS_VERIFYING,
        self::STATUS_CHECKING,
        self::STATUS_LOGGING_OUT,
    ];

    public const AWAITING_CODE_STATUSES = [
        self::STATUS_CODE_SENT,
    ];

    public const AWAITING_PASSWORD_STATUSES = [
        self::STATUS_NEED_PASSWORD,
        self::STATUS_PASSWORD_INVALID,
    ];

    /**
     * An in-flight status older than this means the CLI process died
     * without writing its result, and the account may be started over.
     */
    public const STALE_AFTER_SECONDS = 180;

    /**
     * Nothing releases a busy flag a crashed worker left behind. Past this
     * age it no longer counts as busy; switching the process back on
     * (TelegramAccountProcess::enable()) clears it.
     */
    public const STALE_BUSY_MINUTES = 15;

    protected $fillable = [
        'phone',
        'telegram_user_id',
        'first_name',
        'last_name',
        'username',
        'session_path',
        'is_authorized',
        'authorized_at',
        'status',
        'password_hint',
        'last_error',
        'last_checked_at',
    ];

    protected $casts = [
        'is_authorized' => 'boolean',
        'authorized_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'telegram_user_id' => 'integer',
    ];

    public function processes(): HasMany
    {
        return $this->hasMany(TelegramAccountProcess::class);
    }

    public static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', trim($phone));

        if ($phone !== '' && $phone[0] !== '+') {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    public static function sessionPathFor(string $phone): string
    {
        $safePhone = preg_replace('/\D+/', '', $phone);

        return storage_path("app/telegraSessions/telegram_{$safePhone}.madeline");
    }

    /**
     * Profile fields from a MadelineProto user object (getSelf() and
     * friends).
     */
    public static function profileFrom(array $self): array
    {
        return [
            'telegram_user_id' => $self['id'] ?? null,
            'first_name' => $self['first_name'] ?? null,
            'last_name' => $self['last_name'] ?? null,
            'username' => $self['username'] ?? null,
        ];
    }

    public function hasSessionFile(): bool
    {
        return $this->session_path !== null
            && $this->session_path !== ''
            && file_exists($this->session_path);
    }

    /**
     * The listener account (TELEGRAM_DRIVER_CHECK_ACCOUNT_ID).
     */
    public function isPrimary(): bool
    {
        $primaryId = config('services.telegram.driver_check_account_id');

        return $primaryId !== null
            && $primaryId !== ''
            && (int) $primaryId === (int) $this->id;
    }

    public function isInFlight(): bool
    {
        return in_array($this->status, self::IN_FLIGHT_STATUSES, true);
    }

    /**
     * In flight for so long that nothing is going to answer any more.
     */
    public function isStale(): bool
    {
        return $this->isInFlight()
            && $this->updated_at !== null
            && $this->updated_at->lt(now()->subSeconds(self::STALE_AFTER_SECONDS));
    }

    public function isAwaitingCode(): bool
    {
        return in_array($this->status, self::AWAITING_CODE_STATUSES, true);
    }

    public function isAwaitingPassword(): bool
    {
        return in_array($this->status, self::AWAITING_PASSWORD_STATUSES, true);
    }

    /**
     * The listener (telegram:start-loop) is holding this session right now.
     */
    public function isListening(): bool
    {
        return $this->isPrimary() && $this->status === 'running';
    }

    /**
     * A process claimed the account and has not let go yet. A busy flag
     * older than STALE_BUSY_MINUTES is a crashed worker, not a user.
     */
    public function hasBusyProcess(): bool
    {
        return $this->processes()
            ->where('is_busy', true)
            ->where(function ($query): void {
                $query->whereNull('busy_at')
                    ->orWhere('busy_at', '>=', now()->subMinutes(self::STALE_BUSY_MINUTES));
            })
            ->exists();
    }

    /**
     * Where telegram:account-check finds the status to put back: a check
     * must not wipe what the listener wrote (running / stopped).
     */
    public static function statusBeforeCheckCacheKey(int $accountId): string
    {
        return "telegram-account:{$accountId}:status-before-check";
    }

    /**
     * Cache key the 2FA password waits under for `tp` - see
     * CompleteTelegramAccountLoginJob.
     */
    public static function passwordCacheKey(int $accountId): string
    {
        return "telegram-account:{$accountId}:2fa-password";
    }
}
