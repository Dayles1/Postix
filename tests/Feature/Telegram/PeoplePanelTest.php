<?php

namespace Tests\Feature\Telegram;

use App\Application\Telegram\DTO\TelegramListFilters;
use App\Application\Telegram\Queries\ListOperationUsers;
use App\Enums\Telegram\TelegramClientCheckStatus;
use App\Models\Role;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use App\Models\Telegram\TelegramSetting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The people pages (operators / sales) and the CRM penalties page.
 */
class PeoplePanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * The project migrations are MySQL-only, so only the tables these
         * endpoints touch are created here.
         */
        foreach ([
            'telegram_settings',
            'telegram_client_checks',
            'telegram_driver_checks',
            'telegram_drivers',
            'operation_users',
            'images',
            'users',
            'roles',
        ] as $table) {
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

        /*
         * The layout loads the signed-in user's avatar.
         */
        Schema::create('images', function (Blueprint $table): void {
            $table->id();
            $table->string('path')->nullable();
            $table->morphs('imageable');
            $table->timestamps();
        });

        Schema::create('operation_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_normalized')->unique();
            $table->string('role', 16)->default('operation');
            $table->string('language', 5)->nullable();
            $table->boolean('respectful')->default(false);
            $table->json('address')->nullable();
            $table->string('telegram_username')->nullable()->unique();
            $table->unsignedBigInteger('telegram_id')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('dm_enabled')->default(true);
            $table->timestamp('dm_last_sent_at')->nullable();
            $table->text('dm_last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_drivers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('name_normalized')->nullable();
            $table->unsignedBigInteger('operation_user_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('telegram_driver_checks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('operation_user_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->string('status')->nullable();
            $table->json('telegram_raw')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_client_checks', function (Blueprint $table): void {
            $table->id();
            $table->bigInteger('telegram_chat_id');
            $table->bigInteger('telegram_message_id');
            $table->text('message_text')->nullable();
            $table->json('telegram_raw')->nullable();
            $table->json('parsed')->nullable();
            $table->string('request_number', 32)->nullable();
            $table->unsignedSmallInteger('repeat_number')->default(1);
            $table->string('crm_status')->nullable();
            $table->timestamp('status_since')->nullable();
            $table->string('responsible_role', 16)->nullable();
            $table->string('responsible_name')->nullable();
            $table->unsignedBigInteger('operation_user_id')->nullable();
            $table->unsignedTinyInteger('level')->default(0);
            $table->json('metrics')->nullable();
            $table->unsignedTinyInteger('comment_level')->nullable();
            $table->unsignedSmallInteger('phrase_index')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedSmallInteger('batch_count')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('reason', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->string('peer')->nullable();
            $table->timestamp('forwarded_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('reply_text')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('reply_kind', 60)->nullable();
            $table->text('reply_answer')->nullable();
            $table->timestamp('reply_answered_at')->nullable();
            $table->timestamp('nudged_at')->nullable();
            $table->text('nudge_text')->nullable();
            $table->timestamps();
        });

        Schema::create('telegram_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        $role = Role::query()->create(['name' => 'driverCheck']);

        $this->actingAs(User::query()->create([
            'name' => 'Checker',
            'role_id' => $role->id,
        ]));
    }

    private function person(string $name, string $role = OperationUser::ROLE_OPERATION, array $attributes = []): OperationUser
    {
        return OperationUser::query()->create([
            'name' => $name,
            'name_normalized' => OperationUser::normalizeName($name),
            'role' => $role,
            ...$attributes,
        ]);
    }

    private int $messageId = 0;

    private function penalty(?OperationUser $person, array $attributes = []): TelegramClientCheck
    {
        return TelegramClientCheck::query()->create([
            'telegram_chat_id' => -1001,
            'telegram_message_id' => ++$this->messageId,
            'request_number' => 'TLS' . str_pad((string) $this->messageId, 5, '0', STR_PAD_LEFT),
            'repeat_number' => 1,
            'responsible_role' => $person?->role,
            'responsible_name' => $person?->name,
            'operation_user_id' => $person?->id,
            'status' => TelegramClientCheckStatus::Sent,
            ...$attributes,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public function test_operators_sales_and_penalties_pages_open(): void
    {
        $this->get('/driver-check/operators')
            ->assertOk()
            ->assertSee(__('telegram.operators.pages.operation.title'))
            ->assertSee("role: 'operation'", false);

        $this->get('/driver-check/sales')
            ->assertOk()
            ->assertSee(__('telegram.operators.pages.sales.create'))
            ->assertSee("role: 'sales'", false);

        $this->get('/driver-check/penalties')
            ->assertOk()
            ->assertSee(__('telegram.penalties.title'));

        $this->get('/driver-check/penalties/settings')
            ->assertOk()
            ->assertSee(__('telegram.penalty_settings.title'));
    }

    /*
    |--------------------------------------------------------------------------
    | Penalty rules API
    |--------------------------------------------------------------------------
    */

    private function ladder(): array
    {
        return [
            ['name' => 'First', 'mode' => 'all', 'phrases' => [
                'uz' => ['plain' => ['Narx {status_limit}'], 'respectful' => ['Narx bering']],
                'ru' => ['plain' => ['Цену'], 'respectful' => []],
            ]],
            ['name' => null, 'from' => 2, 'mode' => 'forward', 'phrases' => [
                'uz' => ['plain' => ['Ikkinchi', '  '], 'respectful' => []],
                'ru' => ['plain' => [], 'respectful' => []],
            ]],
        ];
    }

    private function validRules(array $override = []): array
    {
        return [
            'roles' => [
                'operation' => ['levels' => $this->ladder()],
                'sales' => ['levels' => [
                    ['mode' => 'all', 'phrases' => ['ru' => ['plain' => ['Только sales']]]],
                ]],
            ],
            'batch_quiet_seconds' => 25,
            'max_attempts' => 2,
            'retry_minutes' => 40,
            'working_hours' => ['from' => '08:30', 'to' => '19:00'],
            ...$override,
        ];
    }

    public function test_rules_start_from_the_config_and_are_saved_and_reset(): void
    {
        config()->set('client_checks.roles', null);
        config()->set('client_checks.levels', [
            ['from' => 1, 'phrases' => ['uz' => ['plain' => ['cfg']]]],
        ]);

        $this->getJson('/api/telegram/client-checks/rules')
            ->assertOk()
            ->assertJsonPath('customised', false)
            /* one list in the config: both roles start from it */
            ->assertJsonPath('data.roles.operation.levels.0.phrases.uz.plain.0', 'cfg')
            ->assertJsonPath('data.roles.sales.levels.0.phrases.uz.plain.0', 'cfg')
            /* every set is there, even an empty one */
            ->assertJsonPath('data.roles.operation.levels.0.phrases.ru.respectful', []);

        $this->putJson('/api/telegram/client-checks/rules', $this->validRules())
            ->assertOk()
            ->assertJsonPath('customised', true)
            ->assertJsonPath('data.roles.operation.levels.0.from', 1)
            ->assertJsonPath('data.roles.operation.levels.1.from', 2)
            ->assertJsonPath('data.roles.operation.levels.1.mode', 'forward')
            /* blanks are dropped, not stored */
            ->assertJsonPath('data.roles.operation.levels.1.phrases.uz.plain', ['Ikkinchi'])
            ->assertJsonPath('data.roles.operation.levels.0.phrases.uz.respectful', ['Narx bering'])
            /* the roles are kept apart */
            ->assertJsonCount(1, 'data.roles.sales.levels')
            ->assertJsonPath('data.roles.sales.levels.0.phrases.ru.plain', ['Только sales'])
            ->assertJsonPath('data.batch_quiet_seconds', 25)
            ->assertJsonPath('data.working_hours', ['from' => '08:30', 'to' => '19:00'])
            ->assertJsonPath('defaults.roles.operation.levels.0.phrases.uz.plain.0', 'cfg');

        /* Both ends empty: no limit. */
        $this->putJson('/api/telegram/client-checks/rules', $this->validRules(['working_hours' => ['from' => null, 'to' => null]]))
            ->assertOk()
            ->assertJsonPath('data.working_hours', ['from' => null, 'to' => null]);

        /* One end alone, or a bad time, is refused. */
        $this->putJson('/api/telegram/client-checks/rules', $this->validRules(['working_hours' => ['from' => '09:00', 'to' => null]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('working_hours.to');

        $this->putJson('/api/telegram/client-checks/rules', $this->validRules(['working_hours' => ['from' => '25:00', 'to' => '18:00']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('working_hours.from');

        $this->deleteJson('/api/telegram/client-checks/rules')
            ->assertOk()
            ->assertJsonPath('customised', false)
            ->assertJsonPath('data.roles.sales.levels.0.phrases.uz.plain.0', 'cfg');
    }

    public function test_rules_that_could_never_work_are_refused(): void
    {
        $this->putJson('/api/telegram/client-checks/rules', $this->validRules([
            'roles' => [
                'operation' => ['levels' => $this->ladder()],
                'sales' => ['levels' => [
                    ['mode' => 'all', 'phrases' => ['uz' => ['plain' => ['  ']]]],
                    ['from' => 3, 'mode' => 'all', 'phrases' => []],
                    /* not after the level before it */
                    ['from' => 3, 'mode' => 'banana', 'phrases' => []],
                ]],
            ],
            'max_attempts' => 0,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'roles.sales.levels.0.phrases',
                'roles.sales.levels.2.from',
                'roles.sales.levels.2.mode',
                'max_attempts',
            ])
            ->assertJsonMissingValidationErrors(['roles.sales.levels.1.from', 'roles.operation.levels.0.phrases']);

        /* A first level that sends no comment needs no phrase. */
        $this->putJson('/api/telegram/client-checks/rules', $this->validRules([
            'roles' => [
                'operation' => ['levels' => $this->ladder()],
                'sales' => ['levels' => [['mode' => 'forward', 'phrases' => []]]],
            ],
        ]))->assertOk();

        $this->putJson('/api/telegram/client-checks/rules', $this->validRules([
            'roles' => [
                'operation' => ['levels' => array_fill(0, 11, ['from' => 1, 'mode' => 'all', 'phrases' => ['uz' => ['plain' => ['x']]]])],
                'sales' => ['levels' => $this->ladder()],
            ],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles.operation.levels');
    }

    /*
    |--------------------------------------------------------------------------
    | How a person is written to
    |--------------------------------------------------------------------------
    */

    public function test_the_language_follows_the_role_until_one_is_picked(): void
    {
        $operator = $this->person('ALPHA OPERATOR');
        $sales = $this->person('BELYAKOVA ANNA', OperationUser::ROLE_SALES);

        $this->getJson('/api/telegram/operators')
            ->assertJsonPath('data.0.language', null)
            ->assertJsonPath('data.0.message_language', 'uz')
            ->assertJsonPath('data.0.respectful', false);

        $this->getJson('/api/telegram/operators?role=sales')
            ->assertJsonPath('data.0.message_language', 'ru');

        $this->putJson("/api/telegram/operators/{$sales->id}", [
            'name' => 'BELYAKOVA ANNA',
            'language' => 'uz',
            'respectful' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.language', 'uz')
            ->assertJsonPath('data.message_language', 'uz')
            ->assertJsonPath('data.respectful', true);

        /* Left out of an edit, both stay as they are. */
        $this->putJson("/api/telegram/operators/{$sales->id}", ['name' => 'BELYAKOVA ANNA'])
            ->assertJsonPath('data.language', 'uz')
            ->assertJsonPath('data.respectful', true);

        /* Back to "by role". */
        $this->putJson("/api/telegram/operators/{$sales->id}", ['name' => 'BELYAKOVA ANNA', 'language' => null])
            ->assertJsonPath('data.language', null)
            ->assertJsonPath('data.message_language', 'ru');

        $this->putJson("/api/telegram/operators/{$operator->id}", ['name' => 'ALPHA OPERATOR', 'language' => 'en'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('language');
    }

    public function test_how_to_call_a_person_is_saved_per_language(): void
    {
        $person = $this->person('ALPHA OPERATOR');

        $this->getJson('/api/telegram/operators')
            ->assertJsonPath('data.0.address', []);

        $this->putJson("/api/telegram/operators/{$person->id}", [
            'name' => 'ALPHA OPERATOR',
            'address' => ['uz' => '  Ali aka ', 'ru' => ''],
        ])
            ->assertOk()
            /* trimmed, and an empty language left out */
            ->assertJsonPath('data.address', ['uz' => 'Ali aka']);

        /* Left out of an edit, it stays. */
        $this->putJson("/api/telegram/operators/{$person->id}", ['name' => 'ALPHA OPERATOR'])
            ->assertJsonPath('data.address', ['uz' => 'Ali aka']);

        $this->putJson("/api/telegram/operators/{$person->id}", [
            'name' => 'ALPHA OPERATOR',
            'address' => ['uz' => null, 'ru' => null],
        ])
            ->assertJsonPath('data.address', []);

        $this->assertNull($person->refresh()->address);

        $this->putJson("/api/telegram/operators/{$person->id}", [
            'name' => 'ALPHA OPERATOR',
            'address' => ['uz' => str_repeat('a', 61)],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('address.uz');
    }

    /*
    |--------------------------------------------------------------------------
    | Auto replies: one JSON file
    |--------------------------------------------------------------------------
    */

    private function autoReplies(array $override = []): array
    {
        return [
            'enabled' => true,
            'only_after_penalty' => false,
            'penalty_window_minutes' => 45,
            'cooldown_minutes' => 0,
            'max_words' => 4,
            'replies' => [
                ['name' => 'Agreed', 'keywords' => ['ok', ' ', '+', 'ok'], 'max_words' => null, 'answers' => [
                    'uz' => ['plain' => ['Rahmat, {address}', ''], 'respectful' => []],
                    'ru' => ['plain' => [], 'respectful' => []],
                ]],
                ['name' => 'Client', 'keywords' => ['клиент*'], 'max_words' => 12, 'answers' => [
                    'ru' => ['plain' => ['Понял']],
                ]],
            ],
            'silence' => [
                'enabled' => true,
                'after_minutes' => 20,
                'answers' => ['uz' => ['plain' => ['Iltimos, {address}'], 'respectful' => []]],
            ],
            ...$override,
        ];
    }

    public function test_auto_replies_are_kept_in_one_file(): void
    {
        $path = storage_path('framework/testing/auto-replies-' . bin2hex(random_bytes(4)) . '.json');
        config()->set('auto_replies.path', $path);

        try {
            $this->get('/driver-check/auto-replies')
                ->assertOk()
                ->assertSee(__('telegram.auto_replies.title'));

            /* No file yet: the defaults. */
            $this->getJson('/api/telegram/auto-replies')
                ->assertOk()
                ->assertJsonPath('customised', false)
                ->assertJsonPath('file_error', null)
                ->assertJsonPath('data.replies.0.name', 'Ждём клиента');

            $this->putJson('/api/telegram/auto-replies', $this->autoReplies())
                ->assertOk()
                ->assertJsonPath('customised', true)
                ->assertJsonPath('data.max_words', 4)
                ->assertJsonPath('data.cooldown_minutes', 0)
                /* blanks and repeats dropped */
                ->assertJsonPath('data.replies.0.keywords', ['ok', '+'])
                ->assertJsonPath('data.replies.0.answers.uz.plain', ['Rahmat, {address}'])
                ->assertJsonPath('data.replies.0.max_words', null)
                ->assertJsonPath('data.replies.1.max_words', 12)
                ->assertJsonPath('data.replies.1.keywords', ['клиент*'])
                ->assertJsonPath('data.silence.after_minutes', 20)
                ->assertJsonPath('data.silence.answers.uz.plain', ['Iltimos, {address}']);

            /* Everything is in the file, readable by hand. */
            $file = json_decode((string) file_get_contents($path), true);
            $this->assertSame('Agreed', $file['replies'][0]['name']);
            $this->assertSame(45, $file['penalty_window_minutes']);
            $this->assertStringContainsString('Rahmat', (string) file_get_contents($path));

            $this->get('/api/telegram/auto-replies/download')
                ->assertOk()
                ->assertHeader('Content-Disposition', 'attachment; filename="' . basename($path) . '"');

            /* A half-made kind is refused, and the file is left alone. */
            $this->putJson('/api/telegram/auto-replies', $this->autoReplies([
                'replies' => [
                    ['name' => null, 'keywords' => ['  '], 'answers' => ['uz' => ['plain' => ['x']]]],
                    ['name' => null, 'keywords' => ['ok'], 'answers' => ['uz' => ['plain' => ['  ']]]],
                ],
                'max_words' => 0,
                'silence' => ['enabled' => true, 'after_minutes' => 15, 'answers' => ['uz' => ['plain' => [' ']]]],
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['replies.0.keywords', 'replies.1.answers', 'max_words', 'silence.answers'])
                ->assertJsonMissingValidationErrors(['replies.0.answers', 'replies.1.keywords']);

            $this->assertSame('Agreed', json_decode((string) file_get_contents($path), true)['replies'][0]['name']);

            /* Broken by hand: said so. */
            file_put_contents($path, '{');
            $this->getJson('/api/telegram/auto-replies')
                ->assertJsonPath('customised', true)
                ->assertJsonPath('data.enabled', false)
                ->assertJsonPath('file_error', 'Syntax error');

            /* Reset: the file goes, the defaults are back. */
            $this->deleteJson('/api/telegram/auto-replies')
                ->assertOk()
                ->assertJsonPath('customised', false)
                ->assertJsonPath('data.replies.0.name', 'Ждём клиента');

            $this->assertFileDoesNotExist($path);
        } finally {
            @unlink($path);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | People API
    |--------------------------------------------------------------------------
    */

    public function test_each_page_lists_its_own_role(): void
    {
        $this->person('ALPHA OPERATOR');
        $anna = $this->person('BELYAKOVA ANNA', OperationUser::ROLE_SALES, ['telegram_username' => 'anna']);

        $this->penalty($anna);
        $this->penalty($anna);

        $this->getJson('/api/telegram/operators')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'ALPHA OPERATOR')
            ->assertJsonPath('stats.roles.operation', 1)
            ->assertJsonPath('stats.roles.sales', 1);

        $this->getJson('/api/telegram/operators?role=sales&sort=penalties&direction=desc')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'BELYAKOVA ANNA')
            ->assertJsonPath('data.0.role', 'sales')
            ->assertJsonPath('data.0.penalties_count', 2)
            ->assertJsonPath('data.0.deletable', false)
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.linked', 1);
    }

    public function test_a_person_is_created_on_the_page_role_and_can_be_moved(): void
    {
        $id = $this->postJson('/api/telegram/operators', [
            'name' => 'karimov sardor',
            'role' => 'sales',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'sales')
            ->assertJsonPath('data.deletable', true)
            ->json('data.id');

        /* An edit that leaves the role out keeps it. */
        $this->putJson("/api/telegram/operators/{$id}", ['name' => 'KARIMOV SARDOR'])
            ->assertOk()
            ->assertJsonPath('data.role', 'sales');

        $this->putJson("/api/telegram/operators/{$id}", ['name' => 'KARIMOV SARDOR', 'role' => 'operation'])
            ->assertOk()
            ->assertJsonPath('data.role', 'operation');

        $this->putJson("/api/telegram/operators/{$id}", ['name' => 'KARIMOV SARDOR', 'role' => 'boss'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    /**
     * One name is one person: the penalties and the driver-check messages
     * are both matched on it, so the other role cannot take it again.
     */
    public function test_a_name_cannot_exist_under_both_roles(): void
    {
        $this->person('BELYAKOVA ANNA', OperationUser::ROLE_SALES);

        $this->postJson('/api/telegram/operators', [
            'name' => 'Belyakova  Anna',
            'role' => 'operation',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name_normalized');
    }

    public function test_someone_with_penalties_cannot_be_deleted(): void
    {
        $anna = $this->person('BELYAKOVA ANNA', OperationUser::ROLE_SALES);
        $this->penalty($anna);

        $this->deleteJson("/api/telegram/operators/{$anna->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', __('telegram.operators.errors.has_history'));

        $this->assertNotNull($anna->fresh());

        $nobody = $this->person('UNUSED SALES', OperationUser::ROLE_SALES);

        $this->deleteJson("/api/telegram/operators/{$nobody->id}")->assertOk();

        $this->assertNull($nobody->fresh());
    }

    /**
     * The driver-check statistics are about operators only. The query
     * itself reads match scores with MySQL's JSON functions, so it is
     * inspected here rather than run on SQLite.
     */
    public function test_the_statistics_page_leaves_sales_out(): void
    {
        $method = new ReflectionMethod(ListOperationUsers::class, 'buildQuery');

        $query = $method->invoke(
            app(ListOperationUsers::class),
            TelegramListFilters::fromArray([]),
        );

        $this->assertStringContainsString('"operation_users"."role" = ?', $query->toSql());
        $this->assertContains(OperationUser::ROLE_OPERATION, $query->getBindings());
    }

    /*
    |--------------------------------------------------------------------------
    | Penalties API
    |--------------------------------------------------------------------------
    */

    public function test_penalties_are_listed_with_the_person_and_counted(): void
    {
        $afzal = $this->person('PULATOV AFZAL', attributes: ['telegram_username' => 'afzal']);
        $anna = $this->person('BELYAKOVA ANNA', OperationUser::ROLE_SALES);

        $this->penalty($afzal, ['level' => 3, 'comment' => 'L3']);
        $this->penalty($afzal, [
            'status' => TelegramClientCheckStatus::Failed,
            'reason' => TelegramClientCheck::REASON_FORWARD_FAILED,
            'error' => 'PEER_ID_INVALID',
        ]);
        $this->penalty($anna, [
            'status' => TelegramClientCheckStatus::Skipped,
            'reason' => TelegramClientCheck::REASON_RESPONSIBLE_UNREACHABLE,
        ]);
        $this->penalty(null, [
            'status' => TelegramClientCheckStatus::Skipped,
            'reason' => TelegramClientCheck::REASON_RESPONSIBLE_MISSING,
        ]);

        $this->getJson('/api/telegram/client-checks')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            /* newest first */
            ->assertJsonPath('data.0.person', null)
            ->assertJsonPath('data.3.person.name', 'PULATOV AFZAL')
            ->assertJsonPath('data.3.person.telegram_username', 'afzal')
            ->assertJsonPath('data.3.comment', 'L3')
            ->assertJsonPath('stats.total', 4)
            ->assertJsonPath('stats.sent', 1)
            ->assertJsonPath('stats.failed', 1)
            ->assertJsonPath('stats.skipped', 2)
            ->assertJsonPath('stats.critical', 1)
            ->assertJsonPath('stats.people', 2)
            ->assertJsonPath('stats.roles.operation', 2)
            ->assertJsonPath('stats.roles.sales', 1);
    }

    public function test_penalties_filter_by_role_status_person_and_search(): void
    {
        $afzal = $this->person('PULATOV AFZAL');
        $anna = $this->person('BELYAKOVA ANNA', OperationUser::ROLE_SALES);

        $this->penalty($afzal, ['request_number' => 'TLS04834']);
        $this->penalty($afzal, ['status' => TelegramClientCheckStatus::Failed]);
        $this->penalty($anna, ['request_number' => 'LOG00663']);

        $this->getJson('/api/telegram/client-checks?role=sales')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.request_number', 'LOG00663')
            /* the tab counters keep counting both roles */
            ->assertJsonPath('stats.roles.operation', 2);

        $this->getJson('/api/telegram/client-checks?status=failed')
            ->assertJsonCount(1, 'data')
            /* the status cards keep the whole picture */
            ->assertJsonPath('stats.total', 3);

        $this->getJson("/api/telegram/client-checks?operation_user_id={$afzal->id}")
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/telegram/client-checks?search=%23TLS04834')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.request_number', 'TLS04834');

        $this->getJson('/api/telegram/client-checks?search=belyakova')
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/telegram/client-checks?status=lost')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_the_switches_are_on_until_turned_off(): void
    {
        $this->getJson('/api/telegram/client-checks/settings')
            ->assertOk()
            ->assertExactJson(['data' => [
                'enabled' => true,
                'comments_enabled' => true,
                'operation_enabled' => true,
                'sales_enabled' => true,
            ]]);

        /* A role alone. */
        $this->putJson('/api/telegram/client-checks/settings', ['sales_enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.sales_enabled', false)
            ->assertJsonPath('data.operation_enabled', true);

        /* Either switch alone. */
        $this->putJson('/api/telegram/client-checks/settings', ['comments_enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.comments_enabled', false);

        $this->putJson('/api/telegram/client-checks/settings', ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.comments_enabled', false);

        /* The list carries them, so the page needs one request. */
        $this->getJson('/api/telegram/client-checks')
            ->assertJsonPath('settings.enabled', false);

        $this->putJson('/api/telegram/client-checks/settings', ['enabled' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('enabled');

        $this->assertFalse(TelegramSetting::clientChecksEnabled());
    }

    public function test_other_roles_are_turned_away(): void
    {
        $role = Role::query()->create(['name' => 'user']);

        $this->actingAs(User::query()->create(['name' => 'U', 'role_id' => $role->id]));

        $this->getJson('/api/telegram/client-checks')->assertForbidden();
        $this->putJson('/api/telegram/client-checks/settings', ['enabled' => false])->assertForbidden();
        $this->putJson('/api/telegram/client-checks/rules', [])->assertForbidden();
        $this->get('/driver-check/penalties/settings')->assertForbidden();
        $this->get('/driver-check/penalties')->assertForbidden();
        $this->get('/driver-check/sales')->assertForbidden();
    }
}
