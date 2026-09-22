<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Actions\ProcessTelegramDriverCheckResults;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Models\Driver\TelegramDriverCheck;
use App\Telegram\TelegramDriverCheckHandler;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * The listener's cron reports finished checks back into the group.
 *
 * One undeliverable report used to take the whole queue down with it:
 * the reporter rethrows, the batch is ordered by id and only a sent
 * report sets reported_at, so the same check was picked first on every
 * tick and nothing behind it was ever reported.
 */
final class DriverCheckReportQueueTest extends TestCase
{
    private const WATCHED_CHAT = -1001234567890;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The project migrations are MySQL-only (duplicate index names
         * across tables), so only the table this action touches is
         * created here.
         */
        Schema::dropIfExists('telegram_driver_checks');

        Schema::create('telegram_driver_checks', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->bigInteger('telegram_message_id')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('phone_normalized')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->string('status')->nullable();
            $table->string('reason')->nullable();
            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->text('telegram_raw')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_one_undeliverable_report_does_not_block_the_others(): void
    {
        $checks = collect(range(1, 3))->map(
            fn (int $i): TelegramDriverCheck => $this->finishedCheck($i),
        );

        $this->action()->execute($this->deadTelegram(), [self::WATCHED_CHAT]);

        foreach ($checks as $check) {
            $check->refresh();

            $this->assertSame(
                1,
                $check->telegram_raw['report_failures'] ?? 0,
                "Check #{$check->id} was never attempted.",
            );

            // Still queued: one failure is not a reason to give up.
            $this->assertNull($check->reported_at);
        }
    }

    public function test_a_report_nobody_can_receive_is_eventually_given_up_on(): void
    {
        $check = $this->finishedCheck(1);

        for ($tick = 1; $tick <= 6; $tick++) {
            $this->action()->execute($this->deadTelegram(), [self::WATCHED_CHAT]);
        }

        $check->refresh();

        $this->assertNotNull($check->reported_at);
        $this->assertTrue($check->telegram_raw['report_abandoned'] ?? false);
        $this->assertSame(5, $check->telegram_raw['report_failures'] ?? 0);

        // The verdict itself survives being undeliverable.
        $this->assertSame(TelegramDriverCheckStatus::Confirmed, $check->status);
    }

    public function test_reports_for_chats_that_are_no_longer_watched_are_left_alone(): void
    {
        $check = $this->finishedCheck(1);

        $this->action()->execute($this->deadTelegram(), [-100999]);

        $check->refresh();

        $this->assertNull($check->reported_at);
        $this->assertSame([], $check->telegram_raw ?? []);
    }

    private function action(): ProcessTelegramDriverCheckResults
    {
        return app(ProcessTelegramDriverCheckResults::class);
    }

    private function finishedCheck(int $index): TelegramDriverCheck
    {
        return TelegramDriverCheck::query()->create([
            'telegram_chat_id' => self::WATCHED_CHAT,
            'telegram_message_id' => 100 + $index,
            'driver_name' => 'SAYFULLAYEV IKRAMZHON',
            'phone_normalized' => '99890111223' . $index,
            'status' => TelegramDriverCheckStatus::Confirmed,
            'checked_at' => now(),
            'telegram_raw' => [],
        ]);
    }

    /**
     * A listener that cannot send anything.
     *
     * MadelineProto's event handler has a final private constructor, so
     * the only way to hold one without a live session is to skip the
     * constructor -- which is exactly the state under test: every call
     * through it fails, the way it does when the reply target is gone.
     */
    private function deadTelegram(): SimpleEventHandler
    {
        return (new ReflectionClass(TelegramDriverCheckHandler::class))
            ->newInstanceWithoutConstructor();
    }
}
