<?php

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Actions\RouteTelegramFile;
use App\Models\Role;
use App\Models\Telegram\TelegramFileForward;
use App\Models\Telegram\TelegramFileRoute;
use App\Models\User;
use danog\MadelineProto\SimpleEventHandler;
use Database\Seeders\TelegramFileRouteSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Records the forwards of the routed files.
 */
final class FakeFileRouteMessages
{
    /** @var list<array<string, mixed>> */
    public array $forwards = [];

    /** @var list<int> */
    public array $failFor = [];

    public function forwardMessages(array $params): array
    {
        if (in_array($params['to_peer'], $this->failFor, true)) {
            throw new RuntimeException('CHAT_WRITE_FORBIDDEN');
        }

        $this->forwards[] = $params;

        return [];
    }

    /**
     * @return list<int>
     */
    public function targets(): array
    {
        return array_map(static fn (array $call): int => $call['to_peer'], $this->forwards);
    }
}

final class FakeFileRouteHandler extends SimpleEventHandler
{
}

class FileRouteTest extends TestCase
{
    private const BOT = 7465833893;

    private const BENYAKONI_GROUP = -5524265426;

    private const ZITIC_GROUP = -1004431008937;

    private FakeFileRouteMessages $messages;

    private SimpleEventHandler $telegram;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('telegram_file_forwards');
        Schema::dropIfExists('telegram_file_routes');

        (require base_path('database/migrations/2026_10_12_110000_create_telegram_file_routes_tables.php'))->up();

        config()->set('file_routes.source_chat_ids', [self::BOT]);

        (new TelegramFileRouteSeeder())->run();

        $this->messages = new FakeFileRouteMessages();

        $handler = (new ReflectionClass(FakeFileRouteHandler::class))
            ->newInstanceWithoutConstructor();

        $handler->messages = $this->messages;

        $this->telegram = $handler;
    }

    private function route(int $messageId, string $caption, string $fileName = 'report.xlsx', int $chatId = self::BOT): bool
    {
        return app(RouteTelegramFile::class)->execute(
            telegram: $this->telegram,
            chatId: $chatId,
            messageId: $messageId,
            fileName: $fileName,
            caption: $caption,
        );
    }

    public function test_a_file_goes_to_the_groups_of_its_kind(): void
    {
        $this->assertTrue($this->route(10, 'Declarant (Benyakoni) — 2026-10-09'));
        $this->assertTrue($this->route(11, 'Declarant (Kamennii Log) — 2026-10-09'));
        $this->assertTrue($this->route(12, 'Zitic — 2026-10-09'));

        $this->assertSame(
            [self::BENYAKONI_GROUP, self::BENYAKONI_GROUP, self::ZITIC_GROUP],
            $this->messages->targets(),
        );

        $this->assertSame(self::BOT, $this->messages->forwards[0]['from_peer']);
        $this->assertSame([10], $this->messages->forwards[0]['id']);

        $this->assertSame(3, TelegramFileForward::query()->where('status', TelegramFileForward::STATUS_SENT)->count());
    }

    public function test_without_a_caption_the_file_name_decides(): void
    {
        TelegramFileRoute::query()->where('name', 'Qozoq (tekshirilgan)')->update([
            'target_chat_ids' => [self::ZITIC_GROUP],
        ]);

        $this->route(20, '', 'qozoq_2026-10-09_00-00-00_tekshirilgan.xlsx');

        $this->assertSame([self::ZITIC_GROUP], $this->messages->targets());
    }

    public function test_other_chats_are_left_to_the_driver_check_flow(): void
    {
        $this->assertFalse($this->route(30, 'Zitic — 2026-10-09', chatId: -1004378045506));

        $this->assertSame([], $this->messages->forwards);
        $this->assertSame(0, TelegramFileForward::query()->count());
    }

    public function test_a_file_sent_by_hand_in_the_test_group_is_routed_too(): void
    {
        /* the shipped default, not the one set in setUp */
        config()->set('file_routes', require base_path('config/file_routes.php'));

        $this->assertTrue($this->route(80, 'Zitic — 2026-10-09', chatId: -1003659088185));

        $this->assertSame([self::ZITIC_GROUP], $this->messages->targets());
        $this->assertSame(-1003659088185, $this->messages->forwards[0]['from_peer']);
    }

    public function test_an_unknown_file_or_a_kind_without_groups_is_only_journaled(): void
    {
        /* false: the usual flow still gets the message */
        $this->assertFalse($this->route(40, 'Something else'));
        $this->assertFalse($this->route(41, 'Turkey — 2026-10-09'));

        $this->assertSame([], $this->messages->forwards);

        $this->assertSame(
            [TelegramFileForward::STATUS_UNMATCHED, TelegramFileForward::STATUS_UNMATCHED],
            TelegramFileForward::query()->orderBy('id')->pluck('status')->all(),
        );
    }

    public function test_the_same_update_twice_is_forwarded_once(): void
    {
        $this->route(50, 'Zitic — 2026-10-09');
        $this->route(50, 'Zitic — 2026-10-09');

        $this->assertCount(1, $this->messages->forwards);
    }

    public function test_a_refused_forward_is_retried(): void
    {
        $this->messages->failFor = [self::ZITIC_GROUP];

        $this->route(60, 'Zitic — 2026-10-09');

        $forward = TelegramFileForward::query()->sole();

        $this->assertSame(TelegramFileForward::STATUS_FAILED, $forward->status);
        $this->assertStringContainsString('CHAT_WRITE_FORBIDDEN', (string) $forward->error);

        $this->messages->failFor = [];

        app(RouteTelegramFile::class)->retry($this->telegram);

        $this->assertSame(TelegramFileForward::STATUS_SENT, $forward->fresh()->status);
        $this->assertSame(2, $forward->fresh()->attempts);
        $this->assertSame([self::ZITIC_GROUP], $this->messages->targets());
    }

    public function test_the_seeder_keeps_the_groups_chosen_in_the_panel(): void
    {
        TelegramFileRoute::query()->where('name', 'Turkey')->update(['target_chat_ids' => [-100123456789]]);

        (new TelegramFileRouteSeeder())->run();

        $this->assertSame(6, TelegramFileRoute::query()->count());
        $this->assertSame([-100123456789], TelegramFileRoute::query()->where('name', 'Turkey')->sole()->targets());
    }

    public function test_the_panel_saves_groups_typed_one_per_line(): void
    {
        $this->signIn();

        $route = TelegramFileRoute::query()->where('name', 'Turkey')->sole();

        $this->putJson("/api/telegram/file-routes/{$route->id}", [
            'name' => 'Turkey',
            'keywords' => 'turkey, turkiya',
            'target_chat_ids' => "-5524265426\n-1004431008937, -5524265426",
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.target_chat_ids', [self::BENYAKONI_GROUP, self::ZITIC_GROUP]);

        $this->putJson("/api/telegram/file-routes/{$route->id}", [
            'name' => 'Turkey',
            'keywords' => 'turkey',
            'target_chat_ids' => 'guruh',
        ])->assertUnprocessable();

        $this->getJson('/api/telegram/file-routes')
            ->assertOk()
            ->assertJsonCount(6, 'routes')
            ->assertJsonPath('source_chat_ids', [self::BOT]);
    }

    public function test_the_chats_page_counts_penalties_and_files_too(): void
    {
        $this->signIn();

        foreach (['telegram_driver_check_chats', 'telegram_driver_checks', 'telegram_client_checks'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('telegram_driver_check_chats', function (Blueprint $table): void {
            $table->id();
            $table->string('link')->nullable();
            $table->bigInteger('chat_id')->nullable();
            $table->string('title')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('source', 16)->default('manual');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolve_error')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        foreach (['telegram_driver_checks', 'telegram_client_checks'] as $table) {
            Schema::create($table, function (Blueprint $table): void {
                $table->id();
                $table->bigInteger('telegram_chat_id');
                $table->timestamps();
            });
        }

        DB::table('telegram_driver_check_chats')->insert([
            ['chat_id' => self::BOT, 'title' => 'Bot', 'created_at' => now(), 'updated_at' => now()],
            ['chat_id' => -1004378045506, 'title' => 'Drivers', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('telegram_driver_checks')->insert(array_fill(0, 3, ['telegram_chat_id' => -1004378045506]));
        DB::table('telegram_client_checks')->insert(array_fill(0, 2, ['telegram_chat_id' => -1004378045506]));

        TelegramFileRoute::query()->where('name', 'Zitic')->update([
            'target_chat_ids' => [self::ZITIC_GROUP, self::BENYAKONI_GROUP],
        ]);

        /* one file to two groups is one file */
        $this->route(70, 'Zitic — 2026-10-09');
        $this->route(71, 'Something else');

        $rows = collect($this->getJson('/api/telegram/chats?sort=title&direction=asc')->assertOk()->json('data'))
            ->keyBy('title');

        $this->assertSame([0, 0, 2], [
            $rows['Bot']['checks_count'],
            $rows['Bot']['penalties_count'],
            $rows['Bot']['files_count'],
        ]);

        $this->assertSame([3, 2, 0], [
            $rows['Drivers']['checks_count'],
            $rows['Drivers']['penalties_count'],
            $rows['Drivers']['files_count'],
        ]);
    }

    private function signIn(): void
    {
        foreach (['users', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }

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

        $this->actingAs(User::query()->create([
            'name' => 'Checker',
            'role_id' => $role->id,
        ]));
    }
}
