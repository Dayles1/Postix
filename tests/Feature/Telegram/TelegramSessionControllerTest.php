<?php

namespace Tests\Feature\Telegram;

use App\Jobs\Telegram\AuthTelegramAccountJob;
use App\Jobs\Telegram\CheckTelegramAccountJob;
use App\Jobs\Telegram\CompleteTelegramAccountLoginJob;
use App\Jobs\Telegram\LogoutTelegramAccountJob;
use App\Jobs\Telegram\VerifyTelegramAccountCodeJob;
use App\Models\Role;
use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramAccountProcess;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TelegramSessionControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The project migrations are MySQL-only (duplicate index names across
         * tables), so only the tables these endpoints touch are created here.
         */
        foreach (['telegram_account_processes', 'telegram_accounts', 'users', 'roles'] as $table) {
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

        Schema::create('telegram_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('phone')->unique();
            $table->unsignedBigInteger('telegram_user_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('session_path')->nullable();
            $table->boolean('is_authorized')->default(false);
            $table->timestamp('authorized_at')->nullable();
            $table->string('status')->nullable();
            $table->string('password_hint')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_account_processes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telegram_account_id');
            $table->string('process', 100);
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

        Queue::fake();

        $role = Role::query()->create(['name' => 'driverCheck']);

        $this->actingAs(
            User::query()->create([
                'name' => 'Checker',
                'email' => 'checker@example.test',
                'password' => 'secret',
                'role_id' => $role->id,
            ])
        );
    }

    private function account(array $attributes = []): TelegramAccount
    {
        return TelegramAccount::query()->create([
            'phone' => '+998900000001',
            'session_path' => null,
            'is_authorized' => false,
            'status' => TelegramAccount::STATUS_CREATED,
            ...$attributes,
        ]);
    }

    public function test_other_roles_are_turned_away(): void
    {
        $role = Role::query()->create(['name' => 'user']);

        $this->actingAs(User::query()->create(['name' => 'U', 'role_id' => $role->id]));

        $this->getJson('/api/telegram/sessions')->assertForbidden();
    }

    public function test_index_lists_accounts_with_state_counters(): void
    {
        $this->account([
            'phone' => '+998900000001',
            'is_authorized' => true,
            'status' => TelegramAccount::STATUS_SUCCESS,
            'username' => 'alpha',
        ]);
        $this->account(['phone' => '+998900000002', 'status' => TelegramAccount::STATUS_CODE_SENT]);
        $this->account(['phone' => '+998900000003', 'status' => TelegramAccount::STATUS_REVOKED]);
        $this->account(['phone' => '+998900000004', 'status' => TelegramAccount::STATUS_LOGGED_OUT]);

        $this->getJson('/api/telegram/sessions')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('stats.total', 4)
            ->assertJsonPath('stats.authorized', 1)
            ->assertJsonPath('stats.pending', 1)
            ->assertJsonPath('stats.problem', 1)
            ->assertJsonPath('stats.logged_out', 1);

        $this->getJson('/api/telegram/sessions?state=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.phone', '+998900000002')
            ->assertJsonPath('data.0.awaiting_code', true)
            /* The counters ignore the state filter. */
            ->assertJsonPath('stats.total', 4);

        $this->getJson('/api/telegram/sessions?search=@alpha')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.username', 'alpha');
    }

    public function test_an_authorized_account_with_a_disabled_process_is_a_problem(): void
    {
        $account = $this->account(['is_authorized' => true, 'status' => TelegramAccount::STATUS_SUCCESS]);

        TelegramAccountProcess::query()->create([
            'telegram_account_id' => $account->id,
            'process' => 'resolver_phone',
            'is_available' => false,
        ]);

        $this->getJson('/api/telegram/sessions?state=problem')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.processes.0.process', 'resolver_phone');
    }

    public function test_store_starts_a_login_and_queues_the_cli_command(): void
    {
        $this->postJson('/api/telegram/sessions', ['phone' => '998 90 123-45-67'])
            ->assertCreated()
            ->assertJsonPath('data.phone', '+998901234567')
            ->assertJsonPath('data.status', TelegramAccount::STATUS_PROCESSING)
            ->assertJsonPath('data.is_in_flight', true);

        Queue::assertPushedOn('telegram', AuthTelegramAccountJob::class, function (AuthTelegramAccountJob $job): bool {
            return $job->phone === '+998901234567';
        });
    }

    public function test_store_refuses_an_authorized_or_busy_account(): void
    {
        $this->account(['phone' => '+998900000001', 'is_authorized' => true]);
        $this->account(['phone' => '+998900000002', 'status' => TelegramAccount::STATUS_PROCESSING]);

        $this->postJson('/api/telegram/sessions', ['phone' => '+998900000001'])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'already_authorized');

        $this->postJson('/api/telegram/sessions', ['phone' => '+998900000002'])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'busy');

        Queue::assertNothingPushed();
    }

    public function test_a_stale_in_flight_login_may_be_started_over(): void
    {
        $account = $this->account(['status' => TelegramAccount::STATUS_PROCESSING]);

        TelegramAccount::query()->whereKey($account->id)->update([
            'updated_at' => now()->subSeconds(TelegramAccount::STALE_AFTER_SECONDS + 5),
        ]);

        $this->getJson("/api/telegram/sessions/{$account->id}")
            ->assertJsonPath('data.is_stale', true);

        $this->postJson('/api/telegram/sessions', ['phone' => $account->phone])
            ->assertCreated();

        Queue::assertPushed(AuthTelegramAccountJob::class);
    }

    public function test_code_is_only_taken_while_one_is_awaited(): void
    {
        $account = $this->account(['status' => TelegramAccount::STATUS_PROCESSING]);

        $this->postJson("/api/telegram/sessions/{$account->id}/code", ['code' => '12345'])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'not_waiting_code');

        $account->update(['status' => TelegramAccount::STATUS_CODE_SENT]);

        $this->postJson("/api/telegram/sessions/{$account->id}/code", ['code' => '12 345'])
            ->assertOk()
            ->assertJsonPath('data.status', TelegramAccount::STATUS_VERIFYING);

        Queue::assertPushed(VerifyTelegramAccountCodeJob::class, function (VerifyTelegramAccountCodeJob $job): bool {
            return $job->code === '12345';
        });
    }

    public function test_password_waits_encrypted_in_the_cache_not_in_the_job(): void
    {
        $account = $this->account(['status' => TelegramAccount::STATUS_NEED_PASSWORD, 'password_hint' => 'cat']);

        $this->getJson("/api/telegram/sessions/{$account->id}")
            ->assertJsonPath('data.awaiting_password', true)
            ->assertJsonPath('data.password_hint', 'cat');

        $this->postJson("/api/telegram/sessions/{$account->id}/password", ['password' => 's3cret'])
            ->assertOk()
            ->assertJsonPath('data.status', TelegramAccount::STATUS_VERIFYING);

        Queue::assertPushed(CompleteTelegramAccountLoginJob::class, function (CompleteTelegramAccountLoginJob $job) use ($account): bool {
            return $job->accountId === $account->id
                && ! str_contains(serialize($job), 's3cret');
        });

        $cached = Cache::get(TelegramAccount::passwordCacheKey($account->id));

        $this->assertNotSame('s3cret', $cached);
        $this->assertSame('s3cret', Crypt::decryptString($cached));
    }

    public function test_a_wrong_password_can_be_retried(): void
    {
        $account = $this->account(['status' => TelegramAccount::STATUS_PASSWORD_INVALID]);

        $this->postJson("/api/telegram/sessions/{$account->id}/password", ['password' => 'again'])
            ->assertOk();

        Queue::assertPushed(CompleteTelegramAccountLoginJob::class);
    }

    public function test_check_and_logout_need_an_idle_authorized_account(): void
    {
        $account = $this->account();

        $this->postJson("/api/telegram/sessions/{$account->id}/check")
            ->assertStatus(409)
            ->assertJsonPath('reason', 'not_authorized');

        $account->update(['is_authorized' => true, 'status' => TelegramAccount::STATUS_SUCCESS]);

        $this->postJson("/api/telegram/sessions/{$account->id}/check")
            ->assertOk()
            ->assertJsonPath('data.status', TelegramAccount::STATUS_CHECKING);

        Queue::assertPushed(CheckTelegramAccountJob::class);

        /* The check is still running. */
        $this->postJson("/api/telegram/sessions/{$account->id}/logout")
            ->assertStatus(409)
            ->assertJsonPath('reason', 'busy');

        $account->refresh()->update(['status' => TelegramAccount::STATUS_SUCCESS]);

        $this->postJson("/api/telegram/sessions/{$account->id}/logout")
            ->assertOk()
            ->assertJsonPath('data.status', TelegramAccount::STATUS_LOGGING_OUT);

        Queue::assertPushed(LogoutTelegramAccountJob::class, function (LogoutTelegramAccountJob $job) use ($account): bool {
            return $job->accountId === $account->id;
        });
    }

    public function test_the_running_listener_and_busy_accounts_are_not_checked(): void
    {
        $listener = $this->account([
            'phone' => '+998900000001',
            'is_authorized' => true,
            'status' => 'running',
        ]);

        config(['services.telegram.driver_check_account_id' => $listener->id]);

        $this->getJson("/api/telegram/sessions/{$listener->id}")
            ->assertJsonPath('data.is_listening', true);

        $this->postJson("/api/telegram/sessions/{$listener->id}/check")
            ->assertStatus(409)
            ->assertJsonPath('reason', 'listener_running');

        $resolver = $this->account([
            'phone' => '+998900000002',
            'is_authorized' => true,
            'status' => TelegramAccount::STATUS_SUCCESS,
        ]);

        $process = TelegramAccountProcess::query()->create([
            'telegram_account_id' => $resolver->id,
            'process' => 'resolver_phone',
            'is_busy' => true,
            'busy_at' => now(),
        ]);

        $this->postJson("/api/telegram/sessions/{$resolver->id}/check")
            ->assertStatus(409)
            ->assertJsonPath('reason', 'process_busy');

        /* A busy flag nobody released for ages no longer blocks. */
        $process->update(['busy_at' => now()->subHour()]);

        $this->postJson("/api/telegram/sessions/{$resolver->id}/check")->assertOk();

        Queue::assertPushed(CheckTelegramAccountJob::class, 1);
    }

    public function test_a_check_remembers_the_status_the_listener_wrote(): void
    {
        $account = $this->account(['is_authorized' => true, 'status' => 'stopped']);

        $this->postJson("/api/telegram/sessions/{$account->id}/check")->assertOk();

        $this->assertSame(
            'stopped',
            Cache::get(TelegramAccount::statusBeforeCheckCacheKey($account->id)),
        );
    }

    public function test_only_an_account_without_a_live_session_can_be_deleted(): void
    {
        $account = $this->account(['is_authorized' => true, 'status' => TelegramAccount::STATUS_SUCCESS]);

        $this->deleteJson("/api/telegram/sessions/{$account->id}")
            ->assertStatus(409)
            ->assertJsonPath('reason', 'still_authorized');

        $account->update(['is_authorized' => false, 'status' => TelegramAccount::STATUS_LOGGED_OUT]);

        $this->deleteJson("/api/telegram/sessions/{$account->id}")->assertOk();

        $this->assertDatabaseMissing('telegram_accounts', ['id' => $account->id]);
    }

    public function test_a_process_can_be_switched_off_and_back_on(): void
    {
        $account = $this->account(['is_authorized' => true, 'status' => TelegramAccount::STATUS_SUCCESS]);

        $this->putJson("/api/telegram/sessions/{$account->id}/processes/resolver_phone", ['is_available' => false])
            ->assertOk()
            ->assertJsonPath('data.processes.0.is_available', false);

        TelegramAccountProcess::query()->update([
            'is_busy' => true,
            'busy_at' => now()->subHour(),
            'consecutive_failures' => 5,
        ]);

        $this->putJson("/api/telegram/sessions/{$account->id}/processes/resolver_phone", ['is_available' => true])
            ->assertOk()
            ->assertJsonPath('data.processes.0.is_available', true)
            ->assertJsonPath('data.processes.0.is_busy', false)
            ->assertJsonPath('data.processes.0.consecutive_failures', 0);

        $this->putJson("/api/telegram/sessions/{$account->id}/processes/nope", ['is_available' => true])
            ->assertNotFound();
    }
}
