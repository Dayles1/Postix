<?php

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Actions\ResolveOperationUser;
use App\Application\Telegram\Services\TelegramOperatorNotifier;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Enums\Drivers\TelegramDriverMessageType;
use App\Models\Driver\TelegramDriverCheck;
use App\Models\Telegram\OperationUser;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Records every sendMessage() the notifier performs.
 */
final class FakeTelegramMessages
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    /** @var list<string|int> */
    public array $failFor = [];

    public function sendMessage(array $params): array
    {
        $peer = $params['peer'];

        if (in_array($peer, $this->failFor, true)) {
            throw new RuntimeException(
                'PEER_ID_INVALID: ' . $peer,
            );
        }

        $this->sent[] = $params;

        return ['id' => count($this->sent)];
    }

    /** @return list<string|int> */
    public function peers(): array
    {
        return array_map(
            static fn (array $params) => $params['peer'],
            $this->sent,
        );
    }
}

/**
 * SimpleEventHandler is abstract but declares no abstract methods, and
 * `messages` is an untyped public property, so an empty subclass built without
 * its constructor is enough to stand in for a live MadelineProto session.
 */
final class FakeTelegramHandler extends SimpleEventHandler
{
}

class OperatorNotificationTest extends TestCase
{
    private FakeTelegramMessages $messages;

    private SimpleEventHandler $telegram;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The project migrations are MySQL-only (duplicate index names across
         * tables), so only the tables these cases touch are created here.
         */
        $this->createTables();

        $this->messages = new FakeTelegramMessages();

        $handler = (new ReflectionClass(FakeTelegramHandler::class))
            ->newInstanceWithoutConstructor();

        $handler->messages = $this->messages;

        $this->telegram = $handler;
    }

    private function createTables(): void
    {
        Schema::dropIfExists('telegram_driver_checks');
        Schema::dropIfExists('operation_users');

        Schema::create(
            'operation_users',
            function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('name_normalized')->index();
                $table->string('telegram_username')->nullable();
                $table->unsignedBigInteger('telegram_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('dm_enabled')->default(true);
                $table->timestamp('dm_last_sent_at')->nullable();
                $table->text('dm_last_error')->nullable();
                $table->timestamps();
            }
        );

        Schema::create(
            'telegram_driver_checks',
            function (Blueprint $table): void {
                $table->id();
                $table->bigInteger('telegram_chat_id')->nullable();
                $table->bigInteger('telegram_message_id')->nullable();
                $table->string('type')->nullable();
                $table->string('driver_name')->nullable();
                $table->unsignedBigInteger('operation_user_id')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            }
        );
    }

    private function operator(array $attributes = []): OperationUser
    {
        return OperationUser::query()->create([
            'name' => 'SALOHIDDINOV FAZLIDDIN SALOHIDDIN OGLI',
            'name_normalized' => 'SALOHIDDINOV FAZLIDDIN SALOHIDDIN OGLI',
            'telegram_username' => 'fazliddin',
            'telegram_id' => null,
            'is_active' => true,
            'dm_enabled' => true,
            ...$attributes,
        ]);
    }

    private function check(?OperationUser $operator): TelegramDriverCheck
    {
        return TelegramDriverCheck::query()->create([
            'telegram_chat_id' => -100123456789,
            'telegram_message_id' => 42,
            'type' => TelegramDriverMessageType::CREATED_DRIVER,
            'driver_name' => 'ALIMBAEV MEDETBEK MASATBEKOVICH',
            'operation_user_id' => $operator?->id,
            'status' => TelegramDriverCheckStatus::Confirmed,
        ]);
    }

    private function notifier(): TelegramOperatorNotifier
    {
        return new TelegramOperatorNotifier();
    }

    public function test_report_is_copied_to_the_operator_private_chat(): void
    {
        $operator = $this->operator();

        $this->notifier()->notify(
            $this->telegram,
            $this->check($operator),
            'REPORT BODY',
        );

        $this->assertSame(
            ['@fazliddin'],
            $this->messages->peers(),
        );

        $sent = $this->messages->sent[0];

        $this->assertStringContainsString('REPORT BODY', $sent['message']);

        /*
         * The private chat has no original message to reply to, so the copy
         * has to name the driver itself.
         */
        $this->assertStringContainsString(
            'ALIMBAEV MEDETBEK MASATBEKOVICH',
            $sent['message'],
        );

        $this->assertSame('html', $sent['parse_mode']);

        $operator->refresh();

        $this->assertNotNull($operator->dm_last_sent_at);
        $this->assertNull($operator->dm_last_error);
    }

    public function test_telegram_id_is_used_when_username_is_missing(): void
    {
        $operator = $this->operator([
            'telegram_username' => null,
            'telegram_id' => 987654321,
        ]);

        $this->notifier()->notify(
            $this->telegram,
            $this->check($operator),
            'REPORT BODY',
        );

        $this->assertSame(
            [987654321],
            $this->messages->peers(),
        );
    }

    public function test_a_stale_username_falls_back_to_the_telegram_id(): void
    {
        $operator = $this->operator([
            'telegram_username' => 'renamed',
            'telegram_id' => 987654321,
        ]);

        $this->messages->failFor = ['@renamed'];

        $this->notifier()->notify(
            $this->telegram,
            $this->check($operator),
            'REPORT BODY',
        );

        $this->assertSame(
            [987654321],
            $this->messages->peers(),
        );

        $operator->refresh();

        $this->assertNotNull($operator->dm_last_sent_at);
        $this->assertNull($operator->dm_last_error);
    }

    public function test_operator_without_any_telegram_contact_is_skipped(): void
    {
        $operator = $this->operator([
            'telegram_username' => null,
            'telegram_id' => null,
        ]);

        $this->notifier()->notify(
            $this->telegram,
            $this->check($operator),
            'REPORT BODY',
        );

        $this->assertSame([], $this->messages->sent);

        $operator->refresh();

        /*
         * Most operators are auto-created and simply have no contact filled in
         * yet - that is an expected state, so nothing is flagged as an error.
         */
        $this->assertNull($operator->dm_last_error);
        $this->assertNull($operator->dm_last_sent_at);
    }

    public function test_disabled_direct_messages_are_skipped(): void
    {
        $operator = $this->operator([
            'dm_enabled' => false,
        ]);

        $this->notifier()->notify(
            $this->telegram,
            $this->check($operator),
            'REPORT BODY',
        );

        $this->assertSame([], $this->messages->sent);
    }

    public function test_inactive_operator_is_skipped(): void
    {
        $operator = $this->operator([
            'is_active' => false,
        ]);

        $this->notifier()->notify(
            $this->telegram,
            $this->check($operator),
            'REPORT BODY',
        );

        $this->assertSame([], $this->messages->sent);
    }

    public function test_check_without_an_operator_is_skipped(): void
    {
        $this->notifier()->notify(
            $this->telegram,
            $this->check(null),
            'REPORT BODY',
        );

        $this->assertSame([], $this->messages->sent);
    }

    public function test_delivery_failure_is_recorded_on_the_operator(): void
    {
        $operator = $this->operator([
            'telegram_username' => 'gone',
            'telegram_id' => 111222333,
        ]);

        $this->messages->failFor = ['@gone', 111222333];

        $this->notifier()->notify(
            $this->telegram,
            $this->check($operator),
            'REPORT BODY',
        );

        $this->assertSame([], $this->messages->sent);

        $operator->refresh();

        $this->assertNotNull($operator->dm_last_error);
        $this->assertStringContainsString('@gone', $operator->dm_last_error);
        $this->assertStringContainsString('111222333', $operator->dm_last_error);
        $this->assertNull($operator->dm_last_sent_at);
    }

    /**
     * The whole feature hinges on this: an operator typed in by hand must be
     * the very row the message parser later resolves, otherwise reports would
     * silently never reach anybody.
     */
    public function test_manually_created_operator_is_matched_by_the_parser(): void
    {
        $manual = $this->operator([
            'name' => 'Salohiddinov   Fazliddin  Salohiddin Ogli',
            'name_normalized' => OperationUser::normalizeName(
                'Salohiddinov   Fazliddin  Salohiddin Ogli',
            ),
        ]);

        $resolved = (new ResolveOperationUser())->execute(
            'SALOHIDDINOV FAZLIDDIN SALOHIDDIN OGLI',
        );

        $this->assertSame($manual->id, $resolved->id);
        $this->assertSame(1, OperationUser::query()->count());
    }
}
