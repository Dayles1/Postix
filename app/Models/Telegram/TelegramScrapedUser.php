<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One Telegram user found in a watched group. Unique by telegram_user_id.
 *
 * @property int         $id
 * @property int         $telegram_user_id
 */
class TelegramScrapedUser extends Model
{
    protected $fillable = [
        'telegram_user_id',
        'username',
        'first_name',
        'last_name',
        'phone',
        'is_bot',
    ];

    protected function casts(): array
    {
        return [
            'telegram_user_id' => 'integer',
            'is_bot' => 'boolean',
        ];
    }

    protected function phone(): Attribute
    {
        return Attribute::set(function (?string $value) {
            $normalised = $value === null ? '' : TelegramAccount::normalizePhone($value);

            return $normalised === '' ? null : $normalised;
        });
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            TelegramScrapeGroup::class,
            'telegram_scrape_group_user',
            'scraped_user_id',
            'group_id',
        )->withPivot('seen_at');
    }

    /**
     * The accounts that have this person in their Telegram contacts.
     */
    public function accountLinks(): HasMany
    {
        return $this->hasMany(TelegramAccountContactLink::class, 'scraped_user_id');
    }

    public function contact(): HasOne
    {
        return $this->hasOne(TelegramAccountContact::class, 'scraped_user_id');
    }
}
