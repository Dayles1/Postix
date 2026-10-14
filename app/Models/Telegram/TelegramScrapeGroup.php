<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A group whose members the accounts save into their contacts.
 *
 * @property int         $id
 * @property string|null $title
 * @property string      $link
 * @property bool        $is_active
 */
class TelegramScrapeGroup extends Model
{
    protected $fillable = [
        'title',
        'link',
        'telegram_chat_id',
        'is_active',
        'members_count',
        'last_synced_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'members_count' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            TelegramScrapedUser::class,
            'telegram_scrape_group_user',
            'group_id',
            'scraped_user_id',
        )->withPivot('seen_at');
    }

    /**
     * t.me/name, https://t.me/name, @name -> name; invite links keep their form.
     */
    public static function normalizeLink(string $link): string
    {
        $link = trim($link);
        $link = preg_replace('#^https?://#i', '', $link);
        $link = preg_replace('#^(www\.)?(t|telegram)\.me/#i', '', $link);

        return ltrim($link, '@/');
    }
}
