<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Services\MadelineService;
use App\Application\Telegram\Services\TelegramContactResolver;
use App\Enums\Drivers\TelegramDriverCheckStatus;
use App\Enums\Drivers\TelegramDriverMessageType;
use App\Models\Driver\TelegramDriver;
use App\Models\Driver\TelegramDriverCheck;
use App\Models\Telegram\TelegramAccount;
use danog\MadelineProto\API;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * The resolver command decides whether a driver is confirmed, and until
 * now nothing tested it. These cover the two ways it used to throw away
 * work it had already done: spending the account pool on a wedged
 * connection, and overwriting a finished verdict with whatever failed
 * afterwards.
 */
final class ResolveTelegramPhoneCommandTest extends TestCase
{
    private TelegramDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The project migrations are MySQL-only (duplicate index names
         * across tables), so only the tables this command touches are
         * created here -- same approach as the other Telegram tests.
         */
        $this->createSchema();

        /*
         * Six resolver accounts: five ordinary attempts plus the
         * cancellation retries, each of which claims one.
         */
        for ($i = 1; $i <= 6; $i++) {
            TelegramAccount::query()->create([
                'phone' => '+99890000000' . $i,
                'session_path' => 'session/resolver-' . $i,
                'is_authorized' => true,
            ]);
        }

        $this->driver = TelegramDriver::query()->create([
            'name' => 'SAYFULLAYEV IKRAMZHON',
            'status' => 'pending',
        ]);
    }

    /* =====================================================================
     | A cancelled operation
     |==================================================================== */

    public function test_a_cancelled_resolve_is_retried_without_spending_an_attempt(): void
    {
        $check = $this->pendingCheck();

        $this->fakeMadeline();

        $this->fakeResolver([
            $this->cancelledResult(),
            $this->resolvedResult('Ikramjon'),
        ]);

        $this->artisan('telegram:resolve-phone', ['checkId' => $check->id])
            ->assertSuccessful();

        $check->refresh();

        $this->assertSame(TelegramDriverCheckStatus::Confirmed, $check->status);

        // The wedged connection cost a session, not an attempt.
        $this->assertSame(1, $check->attempts);
    }

    public function test_repeated_cancellations_stop_instead_of_looping_forever(): void
    {
        $check = $this->pendingCheck();

        $this->fakeMadeline();

        $resolver = $this->fakeResolver(array_fill(0, 20, $this->cancelledResult()));

        $this->artisan('telegram:resolve-phone', ['checkId' => $check->id]);

        $check->refresh();

        $this->assertSame(TelegramDriverCheckStatus::NotConfirmed, $check->status);
        $this->assertNotNull($check->error_message);

        /*
         * Two free retries on a fresh session, then the five ordinary
         * attempts. Anything unbounded here would spin the check
         * forever while Telegram is unreachable.
         */
        $this->assertSame(7, $resolver->calls);
    }

    /* =====================================================================
     | A failure after the verdict
     |==================================================================== */

    public function test_a_failure_after_the_verdict_does_not_overwrite_it(): void
    {
        $check = $this->pendingCheck();

        $this->fakeMadeline();
        $this->fakeResolver([$this->resolvedResult('Ikramjon')]);

        /*
         * The driver row is written after the verdict row, inside the
         * same attempt. Before the guard, a throw there unwound into
         * the command's failure branch, which spent the rest of the
         * account pool and then rewrote the finished check as "not
         * confirmed" with a PHP message for a reason.
         */
        TelegramDriver::updating(static function (): void {
            throw new RuntimeException('lock wait timeout');
        });

        $this->artisan('telegram:resolve-phone', ['checkId' => $check->id]);

        $check->refresh();

        $this->assertSame(TelegramDriverCheckStatus::Confirmed, $check->status);
        $this->assertNull($check->error_message);
        $this->assertNotNull($check->telegram_raw['name_match'] ?? null);

        // ...and the pool was not spent looking for a better answer.
        $this->assertSame(1, $check->attempts);
    }

    /* =====================================================================
     | Fixtures
     |==================================================================== */

    private function pendingCheck(): TelegramDriverCheck
    {
        return TelegramDriverCheck::query()->create([
            'telegram_chat_id' => -100123,
            'telegram_message_id' => 10,
            'type' => TelegramDriverMessageType::CREATED_DRIVER,
            'phone_normalized' => '998901112233',
            'driver_name' => 'SAYFULLAYEV IKRAMZHON',
            'driver_id' => $this->driver->id,
            'status' => TelegramDriverCheckStatus::Pending,
            'attempts' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cancelledResult(): array
    {
        return [
            'success' => false,
            'reason' => 'telegram_error',
            'cancelled' => true,
            'user' => null,
            'raw' => null,
            'error_message' => 'The operation was cancelled',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedResult(string $firstName): array
    {
        return [
            'success' => true,
            'reason' => null,
            'user' => [
                'id' => 777,
                'username' => null,
                'first_name' => $firstName,
                'last_name' => null,
            ],
            'raw' => ['users' => []],
            'error_message' => null,
        ];
    }

    /**
     * A session stand-in. The command only ever asks it to stop, and it
     * already treats a failing stop as a logged warning, so an
     * unconstructed instance is enough and keeps the test away from
     * MadelineProto's internals (the class is final, so it cannot be
     * mocked).
     */
    private function fakeMadeline(): void
    {
        $api = (new ReflectionClass(API::class))->newInstanceWithoutConstructor();

        $madeline = Mockery::mock(MadelineService::class);
        $madeline->shouldReceive('for')->andReturn($api);

        $this->app->instance(MadelineService::class, $madeline);
    }

    /**
     * @param  list<array<string, mixed>>  $results  one per resolve() call
     */
    private function fakeResolver(array $results): object
    {
        $resolver = new class($results) extends TelegramContactResolver
        {
            public int $calls = 0;

            /**
             * @param  list<array<string, mixed>>  $results
             */
            public function __construct(private array $results) {}

            public function resolve(API $api, string $phone, array $context = []): array
            {
                $this->calls++;

                return array_shift($this->results)
                    ?? [
                        'success' => false,
                        'reason' => 'telegram_error',
                        'user' => null,
                        'raw' => null,
                        'error_message' => 'no result queued',
                    ];
            }
        };

        $this->app->instance(TelegramContactResolver::class, $resolver);

        return $resolver;
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('telegram_driver_checks');
        Schema::dropIfExists('telegram_resolved_phones');
        Schema::dropIfExists('telegram_drivers');
        Schema::dropIfExists('operation_users');
        Schema::dropIfExists('telegram_accounts');
        Schema::dropIfExists('telegram_account_processes');

        Schema::create('telegram_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('phone')->nullable();
            $table->string('session_path')->nullable();
            $table->boolean('is_authorized')->default(false);
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_account_processes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telegram_account_id');
            $table->string('process');
            $table->integer('successes')->default(0);
            $table->integer('failures')->default(0);
            $table->integer('consecutive_failures')->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_busy')->default(false);
            $table->timestamp('busy_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->string('disabled_reason')->nullable();
            $table->text('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('operation_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_drivers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_resolved_phones', function (Blueprint $table): void {
            $table->id();
            $table->string('phone_normalized')->nullable();
            $table->bigInteger('telegram_user_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->string('telegram_last_name')->nullable();
            $table->text('telegram_raw')->nullable();
            $table->unsignedBigInteger('telegram_account_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_driver_checks', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->bigInteger('telegram_message_id')->nullable();
            $table->string('type')->nullable();
            $table->text('message_text')->nullable();
            $table->string('phone_raw')->nullable();
            $table->string('phone_normalized')->nullable();
            $table->string('driver_name')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('operation_user_id')->nullable();
            $table->unsignedBigInteger('telegram_resolved_phone_id')->nullable();
            $table->bigInteger('telegram_user_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->string('telegram_last_name')->nullable();
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
}
