<?php

namespace Tests\Feature\Telegram;

use App\Console\Commands\Telegram\TelegramDriverCheckCommand;
use App\Models\Telegram\TelegramAccount;
use App\Telegram\TelegramListenerHealth;
use App\Telegram\TelegramProcessLock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TelegramListenerLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The project migrations are MySQL-only (duplicate index names across
         * tables), so only the table this command touches is created here.
         */
        Schema::dropIfExists('telegram_accounts');

        Schema::create(
            'telegram_accounts',
            function (Blueprint $table): void {
                $table->id();
                $table->string('phone')->nullable();
                $table->string('session_path')->nullable();
                $table->boolean('is_authorized')->default(false);
                $table->string('status')->nullable();
                $table->timestamp('authorized_at')->nullable();
                $table->timestamps();
            }
        );

        TelegramListenerHealth::clear();
        TelegramProcessLock::release(
            TelegramDriverCheckCommand::LOCK_NAME
        );
    }

    protected function tearDown(): void
    {
        TelegramListenerHealth::clear();
        TelegramProcessLock::release(
            TelegramDriverCheckCommand::LOCK_NAME
        );

        parent::tearDown();
    }

    public function test_health_marker_round_trip(): void
    {
        TelegramListenerHealth::markUnhealthy(
            TelegramListenerHealth::REASON_UPDATE_LOOP_DEAD,
            ['strikes' => 3]
        );

        $marker = TelegramListenerHealth::take();

        $this->assertNotNull($marker);
        $this->assertSame(
            TelegramListenerHealth::REASON_UPDATE_LOOP_DEAD,
            $marker['reason']
        );
        $this->assertSame(3, $marker['context']['strikes']);
        $this->assertSame(getmypid(), $marker['pid']);

        // take() consumes the marker so the next run starts clean.
        $this->assertNull(TelegramListenerHealth::take());
    }

    public function test_health_marker_read_does_not_consume(): void
    {
        TelegramListenerHealth::markUnhealthy(
            TelegramListenerHealth::REASON_START_FAILED
        );

        // The listener reads it to pick its exit code...
        $this->assertSame(
            TelegramListenerHealth::REASON_START_FAILED,
            TelegramListenerHealth::read()['reason'] ?? null
        );

        // ...and the watchdog still finds it to log the reason.
        $this->assertSame(
            TelegramListenerHealth::REASON_START_FAILED,
            TelegramListenerHealth::take()['reason'] ?? null
        );

        $this->assertNull(TelegramListenerHealth::read());
    }

    public function test_missing_configuration_exits_with_invalid(): void
    {
        config([
            'services.telegram.driver_check_account_id' => null,
        ]);

        $this->artisan('telegram:start-loop')
            ->assertExitCode(TelegramDriverCheckCommand::INVALID);
    }

    public function test_missing_chat_link_exits_with_invalid(): void
    {
        config([
            'services.telegram.driver_check_account_id' => 1,
            'services.telegram.driver_check_chat_link' => '',
        ]);

        $this->artisan('telegram:start-loop')
            ->assertExitCode(TelegramDriverCheckCommand::INVALID);
    }

    public function test_unauthorized_account_exits_with_invalid(): void
    {
        $account = TelegramAccount::query()->create([
            'phone' => '+998900000000',
            'session_path' => storage_path('app/telegram'),
            'is_authorized' => false,
            'status' => 'stopped',
        ]);

        config([
            'services.telegram.driver_check_account_id' => $account->id,
            'services.telegram.driver_check_chat_link' => 'https://t.me/+test',
        ]);

        $this->artisan('telegram:start-loop')
            ->assertExitCode(TelegramDriverCheckCommand::INVALID);
    }

    public function test_second_listener_refuses_to_share_the_session(): void
    {
        $account = TelegramAccount::query()->create([
            'phone' => '+998900000000',
            'session_path' => storage_path('app/telegram'),
            'is_authorized' => true,
            'status' => 'stopped',
        ]);

        config([
            'services.telegram.driver_check_account_id' => $account->id,
            'services.telegram.driver_check_chat_link' => 'https://t.me/+test',
        ]);

        // Simulate a listener that already owns the MadelineProto session.
        $this->assertTrue(
            TelegramProcessLock::acquire(
                TelegramDriverCheckCommand::LOCK_NAME
            )
        );

        $this->artisan('telegram:start-loop')
            ->assertExitCode(
                TelegramDriverCheckCommand::EXIT_ALREADY_RUNNING
            );

        $this->assertNotSame(
            0,
            TelegramDriverCheckCommand::EXIT_ALREADY_RUNNING,
            'The watchdog must never see a success code for this case.'
        );
    }

    public function test_unusable_lock_file_does_not_block_startup(): void
    {
        /*
         * A directory in place of the lock file makes fopen() fail, the same
         * way a permission problem would. It must report UNAVAILABLE, never
         * HELD - otherwise a filesystem problem would look like "already
         * running" and block every restart.
         */
        $path = TelegramProcessLock::path('unusable-lock-probe');

        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        try {
            $this->assertSame(
                TelegramProcessLock::UNAVAILABLE,
                TelegramProcessLock::attempt('unusable-lock-probe')
            );

            $this->assertNotNull(TelegramProcessLock::lastError());
        } finally {
            @rmdir($path);
        }
    }
}
