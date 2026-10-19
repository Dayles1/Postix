<?php

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Services\DutyOwners;
use App\Application\Telegram\Services\ListenerAccounts;
use App\Application\Telegram\Services\ScheduledMessageSender;
use App\Application\Telegram\Services\TelegramAccountProcessService;
use App\Enums\Telegram\TelegramAccountProcess as ProcessEnum;
use App\Models\Role;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramListener;
use App\Models\Telegram\TelegramScheduledMessage;
use App\Models\User;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

final class FakeListenerMessages
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public function sendMessage(array $params): array
    {
        $this->sent[] = $params;

        return [];
    }
}

final class FakeListenerHandler extends SimpleEventHandler
{
}

/**
 * The accounts that run their own listener next to Driver Check.
 */
class ListenerTest extends TestCase
{
    private const DRIVER_CHECK = 1;

    private const CEO = 2;

    private const HEAD = 3;

    private const FREE = 4;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'telegram_account_processes', 'telegram_scheduled_messages', 'telegram_listeners', 'telegram_accounts',
            'telegram_file_forwards', 'telegram_file_routes', 'telegram_driver_check_chats', 'users', 'roles',
            'telegram_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('telegram_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->bigInteger('telegram_user_id')->nullable();
            $table->boolean('is_authorized')->default(true);
            $table->string('session_path')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_account_processes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_account_id');
            $table->string('process');
            $table->unsignedInteger('successes')->default(0);
            $table->unsignedInteger('failures')->default(0);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_busy')->default(false);
            $table->timestamp('busy_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        (require base_path('database/migrations/2026_09_22_090000_create_telegram_driver_check_chats_table.php'))->up();
        (require base_path('database/migrations/2026_10_12_110000_create_telegram_file_routes_tables.php'))->up();
        (require base_path('database/migrations/2026_10_19_100000_add_listener_key_to_telegram_file_routes.php'))->up();
        (require base_path('database/migrations/2026_10_17_130000_create_telegram_listeners_table.php'))->up();
        (include base_path('database/migrations/2026_10_05_100000_create_telegram_settings_table.php'))->up();
        (require base_path('database/migrations/2026_10_17_120000_create_telegram_scheduled_messages_table.php'))->up();

        foreach ([self::DRIVER_CHECK, self::CEO, self::HEAD, self::FREE] as $id) {
            DB::table('telegram_accounts')->insert([
                'id' => $id,
                'phone' => '+99890000000' . $id,
                'is_authorized' => true,
                'session_path' => "sessions/{$id}.madeline",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('telegram_account_processes')->insert([
                'telegram_account_id' => $id,
                'process' => ProcessEnum::ResolverPhone->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        config()->set('services.telegram.driver_check_account_id', self::DRIVER_CHECK);
    }

    private function listener(string $key, int $account, bool $enabled = true, array $duties = []): TelegramListener
    {
        return TelegramListener::query()->create([
            'key' => $key,
            'name' => strtoupper($key),
            'telegram_account_id' => $account,
            'is_enabled' => $enabled,
            'duties' => $duties,
        ]);
    }

    private function signIn(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        $role = Role::query()->create(['name' => 'driverCheck']);

        $this->actingAs(User::query()->create(['name' => 'Checker', 'role_id' => $role->id]));
    }

    public function test_every_enabled_listener_account_is_off_limits_to_the_other_processes(): void
    {
        $this->listener('ceo', self::CEO);
        $this->listener('head_operation', self::HEAD, enabled: false);

        $this->assertEqualsCanonicalizing([self::DRIVER_CHECK, self::CEO], ListenerAccounts::ids());

        $this->assertTrue(TelegramAccount::query()->find(self::CEO)->isPrimary());
        $this->assertTrue(TelegramAccount::query()->find(self::DRIVER_CHECK)->isPrimary());
        $this->assertFalse(TelegramAccount::query()->find(self::HEAD)->isPrimary(), 'a switched-off listener gives its account back');
        $this->assertFalse(TelegramAccount::query()->find(self::FREE)->isPrimary());
    }

    public function test_the_sender_pool_never_hands_out_a_listener_account(): void
    {
        $this->listener('ceo', self::CEO);

        $service = app(TelegramAccountProcessService::class);

        $ids = $service->availableAccounts(ProcessEnum::ResolverPhone)->pluck('id')->sort()->values()->all();

        $this->assertSame([self::HEAD, self::FREE], $ids);

        for ($i = 0; $i < 20; $i++) {
            $claimed = $service->claimRandomAvailableAccount(ProcessEnum::ResolverPhone);

            $this->assertNotNull($claimed);
            $this->assertNotContains($claimed->id, [self::DRIVER_CHECK, self::CEO]);

            $service->release($claimed, ProcessEnum::ResolverPhone);
        }
    }

    public function test_a_message_is_written_by_the_account_it_is_set_to(): void
    {
        $this->travelTo(now()->setTime(17, 0));

        $driver = TelegramScheduledMessage::query()->create([
            'name' => 'Driver', 'text' => 'from driver check', 'send_at' => '17:00', 'chat_ids' => [-1001111111111], 'is_active' => true,
        ]);

        $ceo = TelegramScheduledMessage::query()->create([
            'name' => 'Ceo', 'text' => 'from the ceo', 'send_at' => '17:00', 'chat_ids' => [-1002222222222],
            'listener_key' => 'ceo', 'is_active' => true,
        ]);

        $handler = static function (FakeListenerMessages $messages): SimpleEventHandler {
            $handler = (new ReflectionClass(FakeListenerHandler::class))->newInstanceWithoutConstructor();
            $handler->messages = $messages;

            return $handler;
        };

        $driverMessages = new FakeListenerMessages();
        app(ScheduledMessageSender::class)->flush($handler($driverMessages));

        $ceoMessages = new FakeListenerMessages();
        app(ScheduledMessageSender::class)->flush($handler($ceoMessages), 'ceo');

        $this->assertSame(['from driver check'], array_column($driverMessages->sent, 'message'));
        $this->assertSame(['from the ceo'], array_column($ceoMessages->sent, 'message'));

        $this->assertSame('sent', $driver->refresh()->last_status);
        $this->assertSame('sent', $ceo->refresh()->last_status);

        $this->travelBack();
    }

    public function test_the_panel_adds_edits_and_removes_a_listener(): void
    {
        $this->signIn();

        $this->postJson('/api/telegram/listeners', [
            'key' => 'ceo',
            'name' => 'CEO',
            'telegram_account_id' => self::CEO,
            'is_enabled' => true,
            'duties' => ['scheduled_messages'],
        ])->assertCreated()->assertJsonPath('data.key', 'ceo')->assertJsonPath('data.duties', ['scheduled_messages']);

        /* One account, one listener. */
        $this->postJson('/api/telegram/listeners', [
            'key' => 'head_operation', 'name' => 'Head', 'telegram_account_id' => self::CEO, 'is_enabled' => true, 'duties' => [],
        ])->assertUnprocessable();

        /* Driver Check's account has its own process. */
        $this->postJson('/api/telegram/listeners', [
            'key' => 'head_operation', 'name' => 'Head', 'telegram_account_id' => self::DRIVER_CHECK, 'is_enabled' => true, 'duties' => [],
        ])->assertUnprocessable();

        $this->postJson('/api/telegram/listeners', [
            'key' => 'Bad Key', 'name' => 'x', 'telegram_account_id' => null, 'is_enabled' => true, 'duties' => [],
        ])->assertUnprocessable();

        $this->getJson('/api/telegram/listeners')
            ->assertOk()
            ->assertJsonCount(1, 'listeners')
            ->assertJsonPath('listeners.0.running', false);

        $accounts = collect($this->getJson('/api/telegram/listeners')->json('accounts'))->keyBy('id');
        $this->assertFalse($accounts->has(self::DRIVER_CHECK));
        $this->assertSame('CEO', $accounts[self::CEO]['taken_by']);
        $this->assertNull($accounts[self::HEAD]['taken_by']);

        $listener = TelegramListener::query()->firstOrFail();

        $this->putJson("/api/telegram/listeners/{$listener->id}", [
            'name' => 'Chief', 'telegram_account_id' => self::CEO, 'is_enabled' => false, 'duties' => [],
        ])->assertOk()->assertJsonPath('data.name', 'Chief');

        $this->assertFalse(TelegramAccount::query()->find(self::CEO)->isPrimary());

        TelegramScheduledMessage::query()->create([
            'name' => 'Ceo', 'text' => 'x', 'send_at' => '17:00', 'chat_ids' => [-1002222222222], 'listener_key' => 'ceo',
        ]);

        $this->deleteJson("/api/telegram/listeners/{$listener->id}")->assertOk();

        $this->assertSame(0, TelegramListener::query()->count());
        $this->assertNull(TelegramScheduledMessage::query()->first()->listener_key);
    }

    public function test_a_scheduled_message_can_only_be_set_to_an_existing_listener(): void
    {
        $this->signIn();

        $payload = [
            'name' => 'Report', 'text' => 'x', 'send_at' => '17:00', 'days' => [], 'chat_ids' => [-1002222222222], 'is_active' => true,
        ];

        $this->postJson('/api/telegram/scheduled-messages', $payload + ['listener_key' => 'nobody'])->assertUnprocessable();

        $this->listener('ceo', self::CEO, duties: ['scheduled_messages']);

        $this->postJson('/api/telegram/scheduled-messages', $payload + ['listener_key' => 'ceo'])
            ->assertCreated()
            ->assertJsonPath('data.listener_key', 'ceo');

        $this->getJson('/api/telegram/scheduled-messages')
            ->assertOk()
            ->assertJsonPath('listeners.0.key', null)
            ->assertJsonPath('listeners.1.key', 'ceo');
    }

    public function test_every_duty_is_driver_checks_until_the_panel_gives_it_away(): void
    {
        $owners = app(DutyOwners::class);

        $this->assertSame(['penalty_intake' => null, 'penalties' => null, 'file_routes' => null], $owners->current());
        $this->assertTrue($owners->ownedBy('penalties', null));
        $this->assertFalse($owners->ownedBy('penalties', 'ceo'));

        $this->listener('ceo', self::CEO);
        $owners->save(['penalties' => 'ceo']);

        $this->assertSame(['penalty_intake' => null, 'penalties' => 'ceo', 'file_routes' => null], $owners->current());
        $this->assertTrue($owners->ownedBy('penalties', 'ceo'));
        $this->assertFalse($owners->ownedBy('penalties', null), 'a duty has exactly one owner');
        $this->assertTrue($owners->ownedBy('file_routes', null));
    }

    public function test_a_duty_comes_back_to_driver_check_when_its_owner_cannot_do_it(): void
    {
        $owners = app(DutyOwners::class);

        $ceo = $this->listener('ceo', self::CEO);
        $owners->save(['penalties' => 'ceo', 'file_routes' => 'ceo']);

        $ceo->update(['is_enabled' => false]);
        $this->assertSame(['penalty_intake' => null, 'penalties' => null, 'file_routes' => null], $owners->current());
        $this->assertSame('ceo', $owners->saved()['penalties'], 'the choice is kept for when it is switched on again');

        $ceo->update(['is_enabled' => true]);
        $this->assertSame('ceo', $owners->owner('penalties'));

        $ceo->update(['telegram_account_id' => null]);
        $this->assertNull($owners->owner('penalties'));

        $ceo->update(['telegram_account_id' => self::CEO]);
        $ceo->delete();
        $this->assertNull($owners->owner('file_routes'));
    }

    public function test_the_panel_hands_duties_over_and_takes_them_back(): void
    {
        $this->signIn();

        $this->listener('ceo', self::CEO);
        $this->listener('head_operation', self::HEAD, enabled: false);

        $this->getJson('/api/telegram/listeners')
            ->assertOk()
            ->assertJsonPath('owners.duties.1.duty', 'penalties')
            ->assertJsonPath('owners.duties.1.owner', null)
            ->assertJsonCount(2, 'owners.options');

        $this->putJson('/api/telegram/listener-duties', ['owners' => ['penalties' => 'ceo', 'file_routes' => null]])
            ->assertOk()
            ->assertJsonPath('owners.duties.1.owner', 'ceo')
            ->assertJsonPath('listeners.0.owned_duties', ['penalties']);

        $this->assertTrue(app(DutyOwners::class)->ownedBy('penalties', 'ceo'));

        /* A listener that is off, or one that does not exist, cannot be given a duty. */
        $this->putJson('/api/telegram/listener-duties', ['owners' => ['penalties' => 'head_operation']])->assertUnprocessable();
        $this->putJson('/api/telegram/listener-duties', ['owners' => ['penalties' => 'nobody']])->assertUnprocessable();

        $this->putJson('/api/telegram/listener-duties', ['owners' => ['penalties' => null, 'file_routes' => null]])
            ->assertOk()
            ->assertJsonPath('owners.duties.1.owner', null);

        $this->assertTrue(app(DutyOwners::class)->ownedBy('penalties', null));
    }

    public function test_penalties_of_each_role_can_go_out_from_their_own_account(): void
    {
        $owners = app(DutyOwners::class);

        $this->listener('head_sales', self::CEO);
        $this->listener('head_operation', self::HEAD);

        /* Nothing chosen: both roles follow the general owner, Driver Check. */
        $this->assertNull($owners->penaltyOwner('sales'));
        $this->assertNull($owners->penaltyOwner('operation'));
        $this->assertSame(['operation', 'sales'], $owners->penaltyRoles(null));

        $owners->saveRoles(['sales' => 'head_sales', 'operation' => 'head_operation']);

        $this->assertSame('head_sales', $owners->penaltyOwner('sales'));
        $this->assertSame('head_operation', $owners->penaltyOwner('operation'));
        $this->assertSame(['sales'], $owners->penaltyRoles('head_sales'));
        $this->assertSame(['operation'], $owners->penaltyRoles('head_operation'));
        $this->assertFalse($owners->ownsPenalties(null), 'Driver Check keeps no penalties then');

        /* One role is sent back to Driver Check explicitly, whoever owns "penalties". */
        $owners->save(['penalties' => 'head_operation']);
        $owners->saveRoles(['sales' => DutyOwners::DRIVER_CHECK]);

        $this->assertNull($owners->penaltyOwner('sales'));
        $this->assertSame('head_operation', $owners->penaltyOwner('operation'), 'no choice follows "penalties"');

        /* A head that is switched off gives its role back to the general owner. */
        TelegramListener::query()->where('key', 'head_operation')->update(['is_enabled' => false]);

        $owners->saveRoles(['operation' => 'head_operation']);

        $this->assertNull($owners->penaltyOwner('operation'));
    }

    public function test_the_panel_assigns_the_roles_and_refuses_an_account_that_cannot_do_them(): void
    {
        $this->signIn();

        $this->listener('head_sales', self::CEO);
        $this->listener('head_operation', self::HEAD, enabled: false);

        $this->putJson('/api/telegram/listener-duties', [
            'owners' => ['penalties' => null, 'file_routes' => null],
            'roles' => ['sales' => 'head_sales', 'operation' => DutyOwners::DRIVER_CHECK],
        ])
            ->assertOk()
            ->assertJsonPath('owners.roles.0.role', 'operation')
            ->assertJsonPath('owners.roles.1.owner', 'head_sales')
            ->assertJsonPath('owners.roles.0.owner', null)
            ->assertJsonPath('listeners.1.owned_duties', ['penalties:sales']);

        $this->putJson('/api/telegram/listener-duties', [
            'owners' => ['penalties' => null],
            'roles' => ['sales' => 'head_operation'],
        ])->assertUnprocessable();

        $this->putJson('/api/telegram/listener-duties', [
            'owners' => ['penalties' => null],
            'roles' => ['sales' => 'nobody'],
        ])->assertUnprocessable();
    }

    public function test_each_kind_of_file_goes_out_from_its_own_account(): void
    {
        $owners = app(DutyOwners::class);

        $this->listener('head_sales', self::CEO);

        $route = \App\Models\Telegram\TelegramFileRoute::query()->create([
            'name' => 'Zitic',
            'keywords' => 'zitic',
            'target_chat_ids' => [-1001234567890],
            'listener_key' => 'head_sales',
            'is_active' => true,
            'sort' => 1,
        ]);

        $this->assertSame('head_sales', $owners->fileOwner($route->listener_key));
        $this->assertNull($owners->fileOwner(null), 'no choice follows the general owner, Driver Check');
        $this->assertNull($owners->fileOwner(DutyOwners::DRIVER_CHECK));
        $this->assertTrue($owners->ownsFiles('head_sales'));
        $this->assertTrue($owners->ownsFiles(null), 'Driver Check still owns the files nobody else is set for');

        /* Its account is switched off: the kind goes back to the general owner. */
        TelegramListener::query()->where('key', 'head_sales')->update(['is_enabled' => false]);

        $this->assertNull($owners->fileOwner('head_sales'));
        $this->assertFalse($owners->ownsFiles('head_sales'));
    }

    public function test_the_file_page_picks_the_sender_of_a_kind_and_removing_the_listener_frees_it(): void
    {
        $this->signIn();

        $ceo = $this->listener('ceo', self::CEO);

        $payload = ['name' => 'Zitic', 'keywords' => 'zitic', 'target_chat_ids' => ['-1001234567890']];

        $this->postJson('/api/telegram/file-routes', $payload + ['listener_key' => 'nobody'])->assertUnprocessable();

        $id = $this->postJson('/api/telegram/file-routes', $payload + ['listener_key' => 'ceo'])
            ->assertCreated()
            ->assertJsonPath('data.listener_key', 'ceo')
            ->assertJsonPath('data.sender', 'ceo')
            ->json('data.id');

        $this->getJson('/api/telegram/file-routes')
            ->assertOk()
            ->assertJsonPath('accounts.0.key', DutyOwners::DRIVER_CHECK)
            ->assertJsonPath('accounts.1.key', 'ceo');

        $this->deleteJson("/api/telegram/listeners/{$ceo->id}")->assertOk();

        $this->assertNull(\App\Models\Telegram\TelegramFileRoute::query()->find($id)->listener_key);
    }

    public function test_removing_a_listener_gives_its_duties_back(): void
    {
        $this->signIn();

        $ceo = $this->listener('ceo', self::CEO);
        app(DutyOwners::class)->save(['penalties' => 'ceo', 'file_routes' => 'ceo']);

        $this->deleteJson("/api/telegram/listeners/{$ceo->id}")->assertOk();

        $this->assertSame(['penalty_intake' => null, 'penalties' => null, 'file_routes' => null], app(DutyOwners::class)->saved());
    }

    public function test_the_system_page_lists_every_logic_and_who_does_it(): void
    {
        $this->signIn();

        Schema::dropIfExists('telegram_alerts');
        (require base_path('database/migrations/2026_10_19_110000_create_telegram_alerts_table.php'))->up();

        $this->listener('head_sales', self::CEO);
        app(DutyOwners::class)->saveRoles(['sales' => 'head_sales']);

        $json = $this->getJson('/api/telegram/system')->assertOk()->json();

        $logics = collect($json['logics'])->keyBy('key');

        $this->assertSame(
            ['driver_checks', 'penalty_intake', 'penalties_sales', 'penalties_operation', 'file_routes', 'scheduled_messages', 'contact_sync', 'phone_resolver'],
            $logics->keys()->all(),
        );
        $this->assertSame('HEAD_SALES', $logics['penalties_sales']['accounts'][0]['name']);
        $this->assertSame('Driver Check', $logics['penalties_operation']['accounts'][0]['name']);
        $this->assertFalse($logics['driver_checks']['movable']);
        $this->assertTrue($logics['penalty_intake']['movable']);
        $this->assertSame([], $json['alerts']['recent']);
    }

    public function test_the_intake_of_penalties_can_be_given_to_a_listener(): void
    {
        $this->signIn();

        $this->listener('ceo', self::CEO);

        $this->putJson('/api/telegram/listener-duties', ['owners' => ['penalty_intake' => 'ceo', 'penalties' => null, 'file_routes' => null]])
            ->assertOk()
            ->assertJsonPath('owners.duties.0.duty', 'penalty_intake')
            ->assertJsonPath('owners.duties.0.owner', 'ceo')
            ->assertJsonPath('listeners.0.owned_duties', ['penalty_intake']);

        $owners = app(DutyOwners::class);

        $this->assertTrue($owners->ownedBy('penalty_intake', 'ceo'));
        $this->assertFalse($owners->ownedBy('penalty_intake', null));
        $this->assertTrue($owners->ownedBy('penalties', null), 'what is sent is still Driver Checks');
    }

    public function test_every_active_listener_can_be_picked_as_the_sender_of_a_scheduled_message(): void
    {
        $this->signIn();

        /* No duty ticked, no switch: being on with an account is enough. */
        $this->listener('head_china', self::CEO);
        $this->listener('head_europe', self::HEAD, enabled: false);

        $this->getJson('/api/telegram/scheduled-messages')
            ->assertOk()
            ->assertJsonCount(2, 'listeners')
            ->assertJsonPath('listeners.1.key', 'head_china');
    }

    public function test_the_panel_asks_the_watchdog_by_requests_it_reads_by_number(): void
    {
        $control = app(\App\Application\Telegram\Services\ListenersControl::class);

        $this->assertSame(0, $control->latestId());

        $first = $control->request('restart', 'ceo', 7);
        $second = $control->request('stop');

        $this->assertSame([1, 2], [$first, $second]);
        $this->assertSame(2, $control->latestId());
        $this->assertSame(['stop'], array_column($control->after(1), 'action'), 'only what is newer than the last one seen');
        $this->assertSame([], $control->after(2));
    }

    public function test_start_all_goes_through_the_queue_worker_never_from_the_web_process(): void
    {
        $this->signIn();

        \Illuminate\Support\Facades\Queue::fake();

        $this->listener('ceo', self::CEO);

        $spawned = false;

        $this->partialMock(\App\Application\Telegram\Services\ListenersControl::class, function ($mock) use (&$spawned): void {
            $mock->shouldReceive('running')->andReturn(false);
            $mock->shouldReceive('spawnWatchdog')->andReturnUsing(function () use (&$spawned): void {
                $spawned = true;
            });
        });

        $this->postJson('/api/telegram/listener-processes/start')->assertOk();

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\Telegram\StartListenersWatchdogJob::class);
        $this->assertFalse($spawned, 'a process spawned here would belong to the web user, not to the one that owns the sessions');

        /* Not running: nothing to restart or stop, and the panel is told so. */
        $this->postJson('/api/telegram/listener-processes/restart')->assertStatus(409);
        $this->postJson('/api/telegram/listener-processes/stop')->assertStatus(409);

        $this->postJson('/api/telegram/listener-processes/restart', ['key' => 'nobody'])->assertUnprocessable();
    }

    public function test_restart_and_stop_are_left_as_requests_for_the_running_watchdog(): void
    {
        $this->signIn();

        $this->listener('ceo', self::CEO);

        $this->partialMock(\App\Application\Telegram\Services\ListenersControl::class, function ($mock): void {
            $mock->shouldReceive('running')->andReturn(true);
        });

        \Illuminate\Support\Facades\Queue::fake();

        /* Already up: starting again is refused, nothing is queued. */
        $this->postJson('/api/telegram/listener-processes/start')->assertStatus(409);
        \Illuminate\Support\Facades\Queue::assertNothingPushed();

        $this->postJson('/api/telegram/listener-processes/restart', ['key' => 'ceo'])->assertOk();
        $this->postJson('/api/telegram/listener-processes/restart')->assertOk();
        $this->postJson('/api/telegram/listener-processes/stop')->assertOk();

        $requests = app(\App\Application\Telegram\Services\ListenersControl::class)->after(0);

        $this->assertSame(['restart', 'restart', 'stop'], array_column($requests, 'action'));
        $this->assertSame(['ceo', '*'], array_slice(array_column($requests, 'key'), 0, 2));
    }

    public function test_the_monitoring_page_lists_the_watchdog_and_every_session(): void
    {
        $this->signIn();

        $this->listener('ceo', self::CEO);
        $this->listener('head_china', self::HEAD, enabled: false);

        $this->getJson('/api/telegram/listener-processes')
            ->assertOk()
            ->assertJsonPath('status.running', false)
            ->assertJsonStructure(['status' => ['running', 'pid', 'queue_worker_alive', 'command']])
            /* Driver Check is the first row, the same kind of thing as the rest. */
            ->assertJsonCount(3, 'listeners')
            ->assertJsonPath('listeners.0.key', 'driver-check')
            ->assertJsonPath('listeners.0.is_driver_check', true)
            ->assertJsonPath('listeners.1.key', 'ceo')
            ->assertJsonPath('listeners.1.running', false)
            ->assertJsonPath('listeners.2.is_enabled', false)
            /* No session file on this server for the account: said, so it is clear why it does not start. */
            ->assertJsonPath('listeners.0.blocker', 'no_session')
            ->assertJsonPath('listeners.1.blocker', 'no_session');
    }

    public function test_a_listener_that_died_with_a_reason_shows_it_and_one_without_an_account_says_so(): void
    {
        $this->signIn();

        $this->listener('ceo', self::CEO);
        $this->listener('head_china', self::HEAD);

        \Illuminate\Support\Facades\DB::table('telegram_accounts')->where('id', self::HEAD)->update(['is_authorized' => false]);

        \Illuminate\Support\Facades\Cache::put(
            \App\Console\Commands\Telegram\TelegramListenersWatchdogCommand::LAST_EXIT_CACHE . 'ceo',
            ['message' => 'Session path not found: x', 'exit' => 2, 'at' => now()->toIso8601String()],
            60,
        );

        $rows = collect($this->getJson('/api/telegram/listener-processes')->assertOk()->json('listeners'))->keyBy('key');

        $this->assertSame('Session path not found: x', $rows['ceo']['last_exit']['message']);
        $this->assertSame(2, $rows['ceo']['last_exit']['exit']);
        $this->assertNull($rows['ceo']['last_start'], 'the watchdog never started it: the page says so');
        $this->assertSame('not_authorized', $rows['head_china']['blocker']);
        $this->assertNull($rows['head_china']['last_exit']);

        \Illuminate\Support\Facades\Cache::put(
            \App\Console\Commands\Telegram\TelegramListenersWatchdogCommand::LAST_START_CACHE . 'head_china',
            ['at' => now()->toIso8601String(), 'pid' => 123],
            60,
        );

        $china = collect($this->getJson('/api/telegram/listener-processes')->json('listeners'))->firstWhere('key', 'head_china');

        $this->assertNotNull($china['last_start']);
    }

    public function test_driver_check_is_restarted_by_the_same_buttons_when_it_has_a_watchdog(): void
    {
        $this->signIn();

        /* Nothing keeps it alive: there is nobody to restart it, and the panel says so. */
        $this->postJson('/api/telegram/listener-processes/restart', ['key' => 'driver-check'])
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'status' => ['running', 'can_control', 'driver_check_supervised']]);
    }

    public function test_the_page_sees_a_held_lock_by_reading_it_only(): void
    {
        $name = 'test-held-' . bin2hex(random_bytes(3));

        $this->assertFalse(\App\Telegram\TelegramProcessLock::isHeld($name), 'no file, nobody runs');

        $this->assertSame(\App\Telegram\TelegramProcessLock::ACQUIRED, \App\Telegram\TelegramProcessLock::attempt($name));

        try {
            $this->assertTrue(\App\Telegram\TelegramProcessLock::isHeld($name), 'the owner holds it: running');
        } finally {
            \App\Telegram\TelegramProcessLock::release($name);
        }

        $this->assertFalse(\App\Telegram\TelegramProcessLock::isHeld($name), 'released: not running');

        @unlink(\App\Telegram\TelegramProcessLock::path($name));
    }
}
