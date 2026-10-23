<?php

declare(strict_types=1);

namespace Tests\Unit\Telegram;

use App\Application\Telegram\Services\GroupTopic;
use PHPUnit\Framework\TestCase;

/**
 * How Join group finds groups without anybody giving them: the topic of a
 * group, the groups its messages lead to, the phrases it learns.
 */
final class GroupTopicTest extends TestCase
{
    private const ROOTS = ['yuk', 'юк', 'gruz', 'груз', 'fura', 'фура', 'logist', 'perevoz'];

    public function test_a_word_counts_when_it_starts_with_a_root(): void
    {
        $this->assertTrue(GroupTopic::mentions('Toshkentdan yuklar bor', self::ROOTS));
        $this->assertTrue(GroupTopic::mentions('Нужна ФУРА до Москвы', self::ROOTS));
        $this->assertTrue(GroupTopic::mentions('Грузоперевозки по СНГ', self::ROOTS));
        $this->assertFalse(GroupTopic::mentions('Продам iPhone 13, недорого', self::ROOTS));
        /* Inside a word is not enough: "кюк" is not "юк". */
        $this->assertFalse(GroupTopic::mentions('кюкюк', self::ROOTS));
    }

    public function test_a_group_is_on_topic_by_its_title_or_by_enough_messages(): void
    {
        $this->assertTrue(GroupTopic::onTopic('Yuk tashish Toshkent', 0, 100, self::ROOTS));
        $this->assertTrue(GroupTopic::onTopic('Чат водителей', 10, 100, self::ROOTS));
        $this->assertFalse(GroupTopic::onTopic('Чат водителей', 2, 100, self::ROOTS));
        $this->assertFalse(GroupTopic::onTopic('Барахолка', 4, 100, self::ROOTS));
    }

    public function test_links_mentions_and_forwards_lead_to_new_groups(): void
    {
        $messages = [
            ['_' => 'message', 'message' => 'Bizning guruhlar: https://t.me/yuk_markazi va t.me/Gruz_UZ/123'],
            ['_' => 'message', 'message' => 'Yopiq guruh: https://t.me/+AbCdEfGhIjKl'],
            ['_' => 'message', 'message' => 'Admin @ali_aka, kanal @yuk_bor_uz, bot @yuk_helper_bot'],
            ['_' => 'message', 'message' => 'Sticker t.me/addstickers/abcd, share t.me/share/url'],
            ['_' => 'message', 'message' => 'Batafsil', 'entities' => [['_' => 'messageEntityTextUrl', 'url' => 'https://t.me/fura_logist']]],
            /* The group itself is not new to it. */
            ['_' => 'message', 'message' => 'https://t.me/self_group'],
        ];

        $chats = [
            ['_' => 'channel', 'megagroup' => true, 'username' => 'Perevozki_Moskva', 'title' => 'Перевозки Москва', 'participants_count' => 5000, 'id' => 77],
            ['_' => 'channel', 'broadcast' => true, 'username' => 'news_channel', 'title' => 'News'],
        ];

        $found = GroupTopic::links($messages, $chats, self::ROOTS, 'self_group');

        $this->assertEqualsCanonicalizing(
            ['perevozki_moskva', 'yuk_markazi', 'gruz_uz', '+AbCdEfGhIjKl', 'yuk_bor_uz', 'fura_logist'],
            array_keys($found),
        );

        $this->assertSame('forward', $found['perevozki_moskva']['source']);
        $this->assertSame(5000, $found['perevozki_moskva']['members']);
        $this->assertSame(-1000000000077, $found['perevozki_moskva']['chat_id']);
        $this->assertSame('link', $found['yuk_markazi']['source']);
    }

    public function test_phrases_that_keep_coming_back_in_good_titles_are_learned(): void
    {
        $titles = [
            'Yuk tashish Toshkent',
            'YUK TASHISH Samarqand 24/7',
            'Yuk tashish Andijon',
            'Фура Москва Ташкент',
            'Фура Москва грузы',
            'Барахолка Ташкент',
        ];

        $learned = GroupTopic::learn($titles, self::ROOTS, ['yuk']);

        $this->assertContains('yuk tashish', $learned);
        /* Twice is not enough. */
        $this->assertNotContains('фура москва', $learned);

        /* Known already: not learned again. */
        $this->assertNotContains('yuk tashish', GroupTopic::learn($titles, self::ROOTS, ['yuk tashish']));
    }
}
