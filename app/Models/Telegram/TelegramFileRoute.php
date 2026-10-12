<?php

declare(strict_types=1);

namespace App\Models\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One kind of the notifications bot's morning files and the groups it is
 * forwarded to.
 *
 * @property int            $id
 * @property string         $name
 * @property string         $keywords
 * @property list<int>|null $target_chat_ids
 * @property bool           $is_active
 * @property int            $sort
 */
class TelegramFileRoute extends Model
{
    protected $fillable = [
        'name',
        'keywords',
        'target_chat_ids',
        'is_active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'target_chat_ids' => 'array',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function forwards(): HasMany
    {
        return $this->hasMany(
            TelegramFileForward::class,
            'file_route_id',
        );
    }

    /**
     * @return list<string>
     */
    public function keywordList(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $keyword): string => self::normalize($keyword),
            explode(',', $this->keywords),
        )));
    }

    /**
     * @return list<int>
     */
    public function targets(): array
    {
        return array_values(array_unique(array_map(
            'intval',
            array_filter(
                (array) ($this->target_chat_ids ?? []),
                static fn (mixed $id): bool => is_numeric($id) && (int) $id !== 0,
            ),
        )));
    }

    /**
     * Whether one of the keywords is in the caption or the file name.
     */
    public function matches(string $text): bool
    {
        $text = self::normalize($text);

        foreach ($this->keywordList() as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * "Declarant (Kamennii Log) — 2026-10-09" and "kamennii_log" both
     * become "... kamennii log ...": case, dashes, brackets and
     * underscores do not matter.
     */
    public static function normalize(string $value): string
    {
        $value = Str::lower(trim($value));

        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
