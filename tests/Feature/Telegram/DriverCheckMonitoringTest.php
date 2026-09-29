<?php

namespace Tests\Feature\Telegram;

use App\Console\Commands\Telegram\TelegramDriverCheckCommand;
use App\Console\Commands\Telegram\TelegramWatchdogCommand;
use App\Jobs\Telegram\StartTelegramWatchdogJob;
use App\Jobs\Telegram\SyncDriverCheckBotMessage;
use App\Models\Role;
use App\Models\Telegram\TelegramAccount;
use App\Models\User;
use App\Telegram\QueueWorkerHeartbeat;
use App\Telegram\TelegramProcessLock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DriverCheckMonitoringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The project migrations are MySQL-only (duplicate index names across
         * tables), so only the tables these pages touch are created here.
         */
        foreach (['jobs', 'failed_jobs', 'telegram_accounts', 'users', 'roles'] as $table) {
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

        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        $role = Role::query()->create(['name' => 'driverCheck']);

        $this->actingAs(User::query()->create(['name' => 'Checker', 'role_id' => $role->id]));
    }

    protected function tearDown(): void
    {
        foreach ([TelegramWatchdogCommand::LOCK_NAME, TelegramDriverCheckCommand::LOCK_NAME] as $lock) {
            TelegramProcessLock::release($lock);
        }

        parent::tearDown();
    }

    private function job(string $queue, string $class, array $attributes = []): int
    {
        return (int) \DB::table('jobs')->insertGetId([
            'queue' => $queue,
            'payload' => json_encode([
                'displayName' => $class,
                'maxTries' => 3,
                'data' => ['command' => 'secret phone +998901234567'],
            ]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time() - 30,
            'created_at' => time() - 30,
            ...$attributes,
        ]);
    }

    private function failedJob(string $uuid, string $queue = 'telegram'): void
    {
        \DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => $queue,
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => 'App\\Jobs\\Telegram\\SyncDriverCheckBotMessage',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'maxTries' => 3,
                'attempts' => 3,
                'data' => [
                    'commandName' => SyncDriverCheckBotMessage::class,
                    'command' => serialize(new SyncDriverCheckBotMessage(5)),
                ],
            ]),
            'exception' => "RuntimeException: Bot is down\n#0 trace line",
            'failed_at' => now(),
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | Queue
    |----------------------------------------------------------------------
    */

    public function test_the_summary_counts_every_queue_and_never_leaks_a_payload(): void
    {
        $this->job('telegram', 'App\\Jobs\\V2\\ExecJob');
        $this->job('telegram', 'App\\Jobs\\V2\\ExecJob', ['available_at' => time() + 600]);
        $this->job('telegram', 'App\\Jobs\\Telegram\\ResolveTelegramPhoneJob', ['reserved_at' => time() - 500]);
        $this->job('default', 'App\\Jobs\\Delete\\TelegramLogoutJob');

        $response = $this->getJson('/api/telegram/queue')
            ->assertOk()
            ->assertJsonPath('data.queues.0.queue', 'telegram')
            ->assertJsonPath('data.queues.0.ready', 1)
            ->assertJsonPath('data.queues.0.delayed', 1)
            ->assertJsonPath('data.queues.0.reserved', 1)
            ->assertJsonPath('data.queues.0.stuck', 1)
            ->assertJsonPath('data.queues.1.queue', 'default')
            ->assertJsonPath('data.worker.alive', false);

        $this->assertStringNotContainsString('+998901234567', $response->getContent());

        $classes = collect($response->json('data.classes'))->keyBy('job');

        $this->assertSame(2, $classes['ExecJob']['total']);

        $this->getJson('/api/telegram/queue/jobs?queue=telegram&state=delayed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.job', 'ExecJob')
            ->assertJsonMissingPath('data.0.payload');

        $this->getJson('/api/telegram/queue/jobs?search=Resolve')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.state', 'stuck');
    }

    public function test_the_worker_pulse_shows_up_in_the_summary(): void
    {
        QueueWorkerHeartbeat::looping(new Looping('database', 'telegram'));

        $this->getJson('/api/telegram/queue')
            ->assertJsonPath('data.worker.alive', true)
            ->assertJsonPath('data.worker.queues', ['telegram'])
            ->assertJsonPath('data.queues.0.listened', true);
    }

    public function test_waiting_jobs_of_another_queue_can_be_moved_to_telegram(): void
    {
        $this->job('default', 'App\\Jobs\\Delete\\TelegramLogoutJob');
        $taken = $this->job('default', 'App\\Jobs\\Delete\\TelegramLogoutJob', ['reserved_at' => time()]);

        $this->postJson('/api/telegram/queue/move', ['queue' => 'default'])
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertSame(1, \DB::table('jobs')->where('queue', 'telegram')->count());
        $this->assertSame('default', \DB::table('jobs')->where('id', $taken)->value('queue'));
    }

    public function test_failed_jobs_are_listed_retried_onto_telegram_and_deleted(): void
    {
        $this->failedJob('11111111-1111-1111-1111-111111111111', 'default');
        $this->failedJob('22222222-2222-2222-2222-222222222222');

        $this->getJson('/api/telegram/queue/failed')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.job', 'SyncDriverCheckBotMessage')
            ->assertJsonPath('data.0.error', 'RuntimeException: Bot is down');

        $this->getJson('/api/telegram/queue/failed/11111111-1111-1111-1111-111111111111')
            ->assertOk()
            ->assertJsonPath('data.exception', "RuntimeException: Bot is down\n#0 trace line");

        $this->postJson('/api/telegram/queue/failed/11111111-1111-1111-1111-111111111111/retry')
            ->assertOk();

        /* Back in the queue the worker listens to, not in 'default'. */
        $this->assertSame(['telegram'], \DB::table('jobs')->pluck('queue')->all());
        $this->assertSame(1, \DB::table('failed_jobs')->count());

        $this->deleteJson('/api/telegram/queue/failed/22222222-2222-2222-2222-222222222222')
            ->assertOk();

        $this->assertSame(0, \DB::table('failed_jobs')->count());

        $this->postJson('/api/telegram/queue/failed/33333333-3333-3333-3333-333333333333/retry')
            ->assertNotFound();
    }

    public function test_a_job_that_cannot_be_rebuilt_is_reported_not_crashed_on(): void
    {
        $this->failedJob('44444444-4444-4444-4444-444444444444');

        \DB::table('failed_jobs')->update([
            'payload' => json_encode([
                'displayName' => 'App\Jobs\Gone',
                'data' => ['commandName' => 'App\Jobs\Gone', 'command' => 'not a serialized job'],
            ]),
        ]);

        $this->postJson('/api/telegram/queue/failed/44444444-4444-4444-4444-444444444444/retry')
            ->assertStatus(422);
    }

    /*
    |----------------------------------------------------------------------
    | Watchdog
    |----------------------------------------------------------------------
    */

    public function test_status_without_an_account_or_processes(): void
    {
        config(['services.telegram.driver_check_account_id' => null]);

        $this->getJson('/api/telegram/watchdog')
            ->assertOk()
            ->assertJsonPath('data.state', 'misconfigured')
            ->assertJsonPath('data.account_configured', false)
            ->assertJsonPath('data.processes.watchdog.running', false)
            ->assertJsonPath('data.processes.listener.running', false);
    }

    public function test_status_reads_the_locks_the_processes_hold(): void
    {
        $account = TelegramAccount::query()->create([
            'phone' => '+998900000001',
            'is_authorized' => true,
            'status' => 'running',
            'first_name' => 'Main',
        ]);

        config(['services.telegram.driver_check_account_id' => $account->id]);

        $this->getJson('/api/telegram/watchdog')
            ->assertJsonPath('data.state', 'down')
            ->assertJsonPath('data.account.name', 'Main');

        TelegramProcessLock::acquire(TelegramWatchdogCommand::LOCK_NAME);

        $this->getJson('/api/telegram/watchdog')
            ->assertJsonPath('data.processes.watchdog.running', true)
            ->assertJsonPath('data.state', 'restarting');

        TelegramProcessLock::acquire(TelegramDriverCheckCommand::LOCK_NAME);

        $this->getJson('/api/telegram/watchdog')
            ->assertJsonPath('data.state', 'ok');
    }

    public function test_start_queues_the_watchdog_on_the_telegram_queue(): void
    {
        Queue::fake();

        $this->postJson('/api/telegram/watchdog/start')->assertOk();

        Queue::assertPushedOn('telegram', StartTelegramWatchdogJob::class);
    }

    public function test_restart_and_stop_refuse_when_nothing_runs(): void
    {
        $this->postJson('/api/telegram/watchdog/restart-listener')
            ->assertStatus(409)
            ->assertJsonPath('reason', 'no_watchdog');

        $this->postJson('/api/telegram/watchdog/stop')
            ->assertStatus(409)
            ->assertJsonPath('reason', 'not_running');
    }

    public function test_a_signal_the_worker_cannot_deliver_is_logged_not_retried(): void
    {
        $job = new \App\Jobs\Telegram\SignalDriverCheckProcessJob(
            \App\Jobs\Telegram\SignalDriverCheckProcessJob::STOP,
            1,
        );

        // Nothing runs: the job reports it and finishes, it does not throw.
        $job->handle(app(\App\Application\Telegram\Services\DriverCheckProcessMonitor::class));

        $this->assertSame(1, $job->tries);
    }

    public function test_other_roles_are_turned_away(): void
    {
        $role = Role::query()->create(['name' => 'user']);

        $this->actingAs(User::query()->create(['name' => 'U', 'role_id' => $role->id]));

        $this->getJson('/api/telegram/watchdog')->assertForbidden();
        $this->getJson('/api/telegram/queue')->assertForbidden();
    }
}
