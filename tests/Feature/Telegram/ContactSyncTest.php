<?php

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Services\ContactSyncService;
use App\Models\Telegram\TelegramAccountContact;
use App\Models\Telegram\TelegramScrapedUser;
use App\Models\Telegram\TelegramScrapeGroup;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The guarantee the contact sync is built on: a Telegram user is stored once
 * and is saved by one account only, however many groups and accounts see them.
 */
final class ContactSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('telegram_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->nullable();
            $table->bigInteger('telegram_user_id')->nullable();
            $table->timestamps();
        });

        (include database_path('migrations/2026_10_14_100000_create_telegram_contact_sync_tables.php'))->up();
        /* The group types and Join group's columns: the contact sync reads only its own groups. */
        (include database_path('migrations/2026_10_20_100000_create_telegram_group_join_tables.php'))->up();

        DB::table('telegram_accounts')->insert([
            ['id' => 1, 'phone' => '+998900000001'],
            ['id' => 2, 'phone' => '+998900000002'],
        ]);
    }

    private function invokeService(string $method, array $args)
    {
        $service = $this->app->make(ContactSyncService::class);

        $reflection = new ReflectionMethod($service, $method);

        return $reflection->invoke($service, ...$args);
    }

    public function test_a_user_in_two_groups_is_stored_once(): void
    {
        $first = TelegramScrapeGroup::create(['link' => 'one']);
        $second = TelegramScrapeGroup::create(['link' => 'two']);

        $member = ['id' => 555, 'first_name' => 'Ali', 'username' => 'ali', 'type' => 'user'];

        $this->invokeService('storeMembers', [$first, [$member], []]);
        $this->invokeService('storeMembers', [$second, [$member], []]);
        $this->invokeService('storeMembers', [$second, [$member], []]);

        $this->assertSame(1, TelegramScrapedUser::query()->count());
        $this->assertSame(2, DB::table('telegram_scrape_group_user')->count());
    }

    public function test_our_own_accounts_and_nobody_else_are_skipped(): void
    {
        $group = TelegramScrapeGroup::create(['link' => 'one']);

        $this->invokeService('storeMembers', [$group, [
            ['id' => 1, 'first_name' => 'Own'],
            ['id' => 2, 'first_name' => 'Other', 'type' => 'bot'],
        ], [1]]);

        $this->assertSame([2], TelegramScrapedUser::query()->pluck('telegram_user_id')->all());
        $this->assertTrue(TelegramScrapedUser::first()->is_bot);
    }

    public function test_only_one_account_can_claim_a_person(): void
    {
        $user = TelegramScrapedUser::create(['telegram_user_id' => 777]);

        $a = \App\Models\Telegram\TelegramAccount::query()->find(1) ?? $this->account(1);
        $b = $this->account(2);

        $this->assertTrue($this->invokeService('claim', [$a, $user, false]));
        $this->assertFalse($this->invokeService('claim', [$b, $user, false]));

        $this->assertSame(1, TelegramAccountContact::query()->count());
        $this->assertSame(1, TelegramAccountContact::query()->value('telegram_account_id'));
    }

    public function test_a_person_already_in_contacts_is_marked_not_added(): void
    {
        $user = TelegramScrapedUser::create(['telegram_user_id' => 888]);

        $this->assertTrue($this->invokeService('claim', [$this->account(1), $user, true]));

        $this->assertSame(
            TelegramAccountContact::STATUS_SKIPPED,
            TelegramAccountContact::query()->value('status'),
        );
    }

    public function test_links_are_normalised(): void
    {
        foreach (['https://t.me/taxi_group', 't.me/taxi_group', '@taxi_group', ' taxi_group '] as $input) {
            $this->assertSame('taxi_group', TelegramScrapeGroup::normalizeLink($input));
        }

        $this->assertSame('+AbCdEf', TelegramScrapeGroup::normalizeLink('https://t.me/+AbCdEf'));
    }

    private function account(int $id): \App\Models\Telegram\TelegramAccount
    {
        return \App\Models\Telegram\TelegramAccount::query()->findOrFail($id);
    }
}
