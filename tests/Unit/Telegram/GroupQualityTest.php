<?php

declare(strict_types=1);

namespace Tests\Unit\Telegram;

use App\Application\Telegram\Services\GroupQuality;
use PHPUnit\Framework\TestCase;

/**
 * The group join turns groups down when they are quiet, small or not
 * written in Cyrillic or Latin letters; which language exactly does not
 * matter.
 */
final class GroupQualityTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function test_a_lively_group_is_approved_whatever_the_language(): void
    {
        $texts = [
            'Toshkentdan Samarqandga yuk bor, mashina kerak',
            'Нужна машина до Москвы, груз 20 тонн',
            'Тошкентдан Андижонга юк бор, 10 тонна',
            'We need a truck from Tashkent to Almaty',
        ];

        $messages = [];

        for ($i = 0; $i < 60; $i++) {
            $messages[] = [
                '_' => 'message',
                'date' => self::NOW - $i * 1800,
                'from_id' => 100 + $i % 12,
                'message' => $texts[$i % 4],
            ];
        }

        $messages[] = ['_' => 'messageService', 'date' => self::NOW];

        $metrics = GroupQuality::measure($messages, self::NOW);

        $this->assertSame(self::NOW, $metrics['last_message_at']);
        $this->assertSame(49, $metrics['messages_day']);
        $this->assertSame(60, $metrics['messages_week']);
        $this->assertSame(12, $metrics['senders_week']);
        $this->assertSame(48.8, $metrics['per_day']);
        $this->assertSame(100, $metrics['script_share']);

        $this->assertNull(GroupQuality::verdict($metrics, 500, self::config(), self::NOW));
        $this->assertGreaterThan(500, GroupQuality::score($metrics));
    }

    public function test_a_group_is_turned_down_for_the_first_thing_wrong_with_it(): void
    {
        $config = self::config();

        $one = GroupQuality::measure([
            ['_' => 'message', 'date' => self::NOW - 3600, 'from_id' => 1, 'message' => 'Нужна машина до Москвы'],
        ], self::NOW);

        $this->assertSame('too_small', GroupQuality::verdict($one, 10, $config, self::NOW));
        $this->assertSame('quiet', GroupQuality::verdict($one, 500, $config, self::NOW));

        $old = GroupQuality::measure([
            ['_' => 'message', 'date' => self::NOW - 10 * 86400, 'from_id' => 1, 'message' => 'Нужна машина до Москвы'],
        ], self::NOW);

        $this->assertSame('inactive', GroupQuality::verdict($old, 500, $config, self::NOW));
        $this->assertSame('inactive', GroupQuality::verdict(GroupQuality::measure([], self::NOW), 500, $config, self::NOW));
    }

    public function test_a_group_in_other_letters_is_turned_down(): void
    {
        $messages = [];

        for ($i = 0; $i < 30; $i++) {
            $messages[] = ['_' => 'message', 'date' => self::NOW - $i * 600, 'from_id' => $i, 'message' => 'مرحبا، هل يوجد شحن إلى دبي اليوم؟ نحتاج شاحنة'];
        }

        $metrics = GroupQuality::measure($messages, self::NOW);

        $this->assertSame(0, $metrics['script_share']);
        $this->assertSame('script', GroupQuality::verdict($metrics, 500, self::config(), self::NOW));
    }

    public function test_numbers_links_and_photos_are_no_reason_to_turn_a_group_down(): void
    {
        $messages = [];

        for ($i = 0; $i < 30; $i++) {
            $messages[] = ['_' => 'message', 'date' => self::NOW - $i * 600, 'from_id' => $i, 'message' => '+998 90 123 45 67 https://t.me/yuk_bor @admin'];
            $messages[] = ['_' => 'message', 'date' => self::NOW - $i * 600, 'from_id' => $i, 'message' => ''];
        }

        $metrics = GroupQuality::measure($messages, self::NOW);

        $this->assertNull($metrics['script_share']);
        $this->assertNull(GroupQuality::verdict($metrics, 500, self::config(), self::NOW));
    }

    private static function config(): array
    {
        return [
            'min_members' => 100,
            'min_messages_week' => 20,
            'max_silence_hours' => 72,
        ];
    }
}
