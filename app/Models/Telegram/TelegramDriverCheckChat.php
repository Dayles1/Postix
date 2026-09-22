<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use App\Models\Driver\TelegramDriverCheck;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group the driver-check listener watches.
 *
 * @property int         $id
 * @property string|null $link
 * @property int|null    $chat_id
 * @property string|null $title
 * @property bool        $is_active
 * @property string      $source
 */
class TelegramDriverCheckChat extends Model
{
    public const SOURCE_ENV = 'env';

    public const SOURCE_MANUAL = 'manual';

    /**
     * What the panel accepts in the single "chat" field: a raw chat id, a
     * @username, a t.me link (public or invite), or a tg:// link.
     *
     * Anything else is a typo - and a typo would only surface minutes later
     * as a resolve error on the row, so it is rejected at the form instead.
     */
    public const INPUT_PATTERN = '~^(?:'
        . '-?\d{5,20}'
        . '|@?[A-Za-z0-9_]{4,32}'
        . '|(?:https?://)?(?:www\.)?t\.me/(?:joinchat/|\+)?[A-Za-z0-9_-]{4,}/?'
        . '|tg://resolve\?domain=[A-Za-z0-9_]{4,32}'
        . '|tg://join\?invite=[A-Za-z0-9_-]{4,}'
        . ')$~i';

    protected $fillable = [
        'link',
        'chat_id',
        'title',
        'is_active',
        'source',
        'resolved_at',
        'resolve_error',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'chat_id' => 'integer',
            'is_active' => 'boolean',
            'resolved_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * Checks recorded from this chat.
     *
     * Joined on the Telegram id rather than a foreign key: checks are written
     * by the listener from the raw update, and they must survive the chat row
     * being removed from the watch list.
     */
    public function checks(): HasMany
    {
        return $this->hasMany(
            TelegramDriverCheck::class,
            'telegram_chat_id',
            'chat_id',
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * What MadelineProto should be asked to resolve.
     *
     * A resolved id is preferred: it keeps working after an invite link has
     * been revoked, which a t.me/+hash regularly is.
     */
    public function peer(): int|string|null
    {
        return $this->chat_id ?? $this->link;
    }

    /**
     * Splits raw user input into the two columns.
     *
     * A bare number is a chat id (nothing to resolve), anything else is a
     * link or @username that the listener resolves on its next pass.
     *
     * @return array{link: string|null, chat_id: int|null}
     */
    public static function parseInput(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return ['link' => null, 'chat_id' => null];
        }

        if (preg_match('/^-?\d{5,20}$/', $value) === 1) {
            return ['link' => null, 'chat_id' => (int) $value];
        }

        return ['link' => self::normalizeLink($value), 'chat_id' => null];
    }

    /**
     * One spelling per chat, so the same group cannot be added twice under
     * "t.me/x", "https://t.me/x" and "@x".
     */
    public static function normalizeLink(string $link): string
    {
        $link = trim($link);

        /* tg://resolve?domain=name and tg://join?invite=hash */
        if (preg_match('~^tg://resolve\?domain=([A-Za-z0-9_]+)~i', $link, $m) === 1) {
            return '@' . $m[1];
        }

        if (preg_match('~^tg://join\?invite=([A-Za-z0-9_-]+)~i', $link, $m) === 1) {
            return 'https://t.me/+' . $m[1];
        }

        $link = preg_replace('~^(https?://)?(www\.)?~i', '', $link) ?? $link;
        $link = rtrim($link, '/');

        /* Invite links must keep their host: the hash is not a username. */
        if (preg_match('~^t\.me/(?:joinchat/|\+)([A-Za-z0-9_-]+)$~i', $link, $m) === 1) {
            return 'https://t.me/+' . $m[1];
        }

        if (preg_match('~^t\.me/([A-Za-z0-9_]+)$~i', $link, $m) === 1) {
            return '@' . $m[1];
        }

        if (preg_match('~^@?([A-Za-z0-9_]{4,32})$~', $link, $m) === 1) {
            return '@' . $m[1];
        }

        return $link;
    }

    /**
     * A t.me address for this chat, or null when there is nothing to open.
     *
     * The stored link is already normalised to one of two shapes, so this
     * only has to undo that normalisation. A chat added as a bare id has no
     * address at all.
     */
    public function url(): ?string
    {
        $link = trim((string) $this->link);

        if ($link === '') {
            return null;
        }

        if (str_starts_with($link, '@')) {
            return 'https://t.me/' . substr($link, 1);
        }

        return str_starts_with($link, 'https://t.me/')
            ? $link
            : null;
    }

    /**
     * Human label for logs, notifications and the panel.
     */
    public function label(): string
    {
        $title = trim((string) $this->title);

        if ($title !== '') {
            return $title;
        }

        return $this->link
            ?? (string) ($this->chat_id ?? '#' . $this->id);
    }
}
