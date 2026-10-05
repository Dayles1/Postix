<?php

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Actions\ProcessClientCheckMessage;
use App\Application\Telegram\Services\ClientCheckEscalation;
use App\Application\Telegram\Services\ClientCheckRules;
use App\Application\Telegram\Services\ClientCheckRulesStore;
use App\Application\Telegram\Services\ClientCheckSender;
use App\Application\Telegram\Services\TelegramMessageTypeDetector;
use App\Application\Telegram\Services\TelegramPenaltyMessageParser;
use App\Enums\Drivers\TelegramDriverMessageType;
use App\Enums\Telegram\TelegramClientCheckStatus;
use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use App\Models\Telegram\TelegramSetting;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Records the forwards and messages a client check produces.
 */
final class FakeClientCheckMessages
{
    /** @var list<array{0: string, 1: array<string, mixed>}> */
    public array $calls = [];

    /** @var list<string|int> */
    public array $forwardFailsFor = [];

    public bool $commentFails = false;

    public function forwardMessages(array $params): array
    {
        if (in_array($params['to_peer'], $this->forwardFailsFor, true)) {
            throw new RuntimeException('USERNAME_INVALID: ' . $params['to_peer']);
        }

        $this->calls[] = ['forward', $params];

        return [];
    }

    public function sendMessage(array $params): array
    {
        if ($this->commentFails) {
            throw new RuntimeException('FLOOD_WAIT_30');
        }

        $this->calls[] = ['send', $params];

        return ['id' => count($this->calls)];
    }

    /** @return list<string> */
    public function kinds(): array
    {
        return array_map(static fn (array $c) => $c[0], $this->calls);
    }

    /** @return list<array<string, mixed>> */
    public function sent(): array
    {
        return array_values(array_map(
            static fn (array $c) => $c[1],
            array_filter($this->calls, static fn (array $c) => $c[0] === 'send'),
        ));
    }
}

final class FakeClientCheckHandler extends SimpleEventHandler
{
}

class ClientCheckTest extends TestCase
{
    private const BOT_CHAT_ID = 7_000_000_001;

    private const OPERATION = <<<'TXT'
        🆘 Повторное отправление штрафа №3 по запросу #TLS04834
        Статус: В поиске перевозчика
        Время на статус: 2 ч
        В статусе с: 02.10.2026 11:16
        Стоит в статусе: 5 ч 8 мин
        Тип транспорта: Тентованный прицеп
        Кубатура: 96 м³
        Ответственный (Operation): PULATOV AFZAL AHMADJON O'G'LI

        PULATOV AFZAL AHMADJON O'G'LI : 331 / 54

        Открыть запрос (https://crm.zanjeer.uz/queries/queries?filter[search]=TLS04834)
        TXT;

    private const SALES = <<<'TXT'
        🆘 Повторное отправление штрафа №7 по запросу #LOG00663
        Статус: Ставка перевозчика предложена
        Время на статус: 1 ч
        В статусе с: 02.10.2026 09:08
        Стоит в статусе: 7 ч 16 мин
        Тип транспорта: Тентованный прицеп
        Кубатура: 100 м³
        Ответственный (Sales): BELYAKOVA ANNA VLADIMIROVNA

        SHUKURXONOV ISLOMXON NE'MATULLAXON O'G'LI: 331 / 749

        Открыть запрос (https://crm.zanjeer.uz/queries/queries?filter[search]=LOG00663)
        TXT;

    private const NOBODY = <<<'TXT'
        ⚠️ Штраф по запросу #EGS19815
        Статус: Актуальный
        Время на статус: 3 ч
        В статусе с: 02.10.2026 12:18
        Стоит в статусе: 4 ч 1 мин
        Тип транспорта: Тентованный прицеп
        Кубатура: 88 м³
        Ответственный (Operation): —

        Групп / сообщений в Telegram: 0 / 0

        Открыть запрос (https://crm.zanjeer.uz/queries/queries?filter[search]=EGS19815)
        TXT;

    private FakeClientCheckMessages $messages;

    private SimpleEventHandler $telegram;

    private int $messageId = 100;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();

        $this->messages = new FakeClientCheckMessages();

        $handler = (new ReflectionClass(FakeClientCheckHandler::class))
            ->newInstanceWithoutConstructor();

        $handler->messages = $this->messages;

        $this->telegram = $handler;

        /*
         * 1st, 2nd, 3rd and 4+ penalty. Gaps on purpose, to see the
         * fallbacks: no respectful text from the 2nd on, no Russian on
         * the 3rd and 4th.
         */
        /*
         * The config's own per-role ladders would win over this shared
         * list; the tests read theirs from the list below.
         */
        config()->set('client_checks.roles', null);

        config()->set('client_checks.levels', [
            ['from' => 1, 'phrases' => [
                'uz' => ['plain' => ['U1 {name} #{request} {status_limit}'], 'respectful' => ['U1R {name}']],
                'ru' => ['plain' => ['R1 {name} #{request} {status_limit}'], 'respectful' => ['R1R {name}']],
            ]],
            ['from' => 2, 'phrases' => [
                'uz' => ['plain' => ['U2 №{repeat_number}']],
                'ru' => ['plain' => ['R2 №{repeat_number}']],
            ]],
            ['from' => 3, 'phrases' => [
                'uz' => ['plain' => ['U3a №{repeat_number}', 'U3b №{repeat_number}']],
            ]],
            ['from' => 4, 'phrases' => [
                'uz' => ['plain' => ['U4+ №{repeat_number}']],
            ]],
        ]);

        Carbon::setTestNow('2026-10-02 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function createTables(): void
    {
        Schema::dropIfExists('telegram_settings');
        Schema::dropIfExists('telegram_client_checks');
        Schema::dropIfExists('operation_users');

        Schema::create('telegram_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('operation_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('name_normalized')->index();
            $table->string('role', 16)->default('operation');
            $table->string('language', 5)->nullable();
            $table->boolean('respectful')->default(false);
            $table->string('telegram_username')->nullable();
            $table->unsignedBigInteger('telegram_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('dm_enabled')->default(true);
            $table->timestamp('dm_last_sent_at')->nullable();
            $table->text('dm_last_error')->nullable();
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
            $table->timestamps();
            $table->unique(['telegram_chat_id', 'telegram_message_id']);
        });
    }

    private function person(string $name = "PULATOV AFZAL AHMADJON O'G'LI", array $attributes = []): OperationUser
    {
        return OperationUser::query()->create([
            'name' => $name,
            'name_normalized' => OperationUser::normalizeName($name),
            'telegram_username' => 'afzal',
            'telegram_id' => 555,
            'dm_enabled' => true,
            ...$attributes,
        ]);
    }

    /**
     * A penalty for $request with repeat №$repeat, responsible: PULATOV.
     */
    private function penalty(int $repeat = 1, string $request = 'TLS04834', ?string $text = null): ?TelegramClientCheck
    {
        $text ??= str_replace(
            ['№3', 'TLS04834'],
            ['№' . $repeat, $request],
            self::OPERATION,
        );

        return app(ProcessClientCheckMessage::class)->execute(
            telegram: $this->telegram,
            chatId: self::BOT_CHAT_ID,
            messageId: ++$this->messageId,
            text: $text,
        );
    }

    private function flushAfterQuiet(): void
    {
        Carbon::setTestNow(now()->addSeconds(30));

        app(ClientCheckSender::class)->flush($this->telegram);
    }

    private function at(string $time): void
    {
        Carbon::setTestNow('2026-10-02 ' . $time);
    }

    /*
     * ------------------------------------------------------------------
     * Recognising and reading the message
     * ------------------------------------------------------------------
     */

    public function test_both_penalty_kinds_are_detected_and_driver_types_are_not_affected(): void
    {
        $detector = app(TelegramMessageTypeDetector::class);

        $this->assertSame(TelegramDriverMessageType::PENALTY, $detector->detect(self::OPERATION));
        $this->assertSame(TelegramDriverMessageType::PENALTY, $detector->detect(self::NOBODY));
        $this->assertSame(TelegramDriverMessageType::CREATED_DRIVER, $detector->detect("👤 Создан новый водитель\nПользователь: X"));
        $this->assertSame(TelegramDriverMessageType::UNKNOWN, $detector->detect('Просто текст про штраф по запросу'));
    }

    public function test_operation_penalty_is_parsed(): void
    {
        $parsed = app(TelegramPenaltyMessageParser::class)->parse(self::OPERATION);

        $this->assertSame('TLS04834', $parsed['request_number']);
        $this->assertSame(3, $parsed['repeat_number']);
        $this->assertSame('В поиске перевозчика', $parsed['crm_status']);
        $this->assertSame('2026-10-02 11:16:00', $parsed['status_since']);
        $this->assertSame('5 ч 8 мин', $parsed['time_in_status']);
        $this->assertSame('96 м³', $parsed['volume']);
        $this->assertSame(OperationUser::ROLE_OPERATION, $parsed['responsible_role']);
        $this->assertSame("PULATOV AFZAL AHMADJON O'G'LI", $parsed['responsible_name']);
        $this->assertSame("PULATOV AFZAL AHMADJON O'G'LI", $parsed['posted_by_name']);
        $this->assertSame(331, $parsed['telegram_groups']);
        $this->assertSame(54, $parsed['telegram_messages']);
        $this->assertSame('https://crm.zanjeer.uz/queries/queries?filter[search]=TLS04834', $parsed['crm_url']);
    }

    public function test_sales_penalty_names_the_sales_manager_not_the_poster(): void
    {
        $parsed = app(TelegramPenaltyMessageParser::class)->parse(self::SALES);

        $this->assertSame(OperationUser::ROLE_SALES, $parsed['responsible_role']);
        $this->assertSame('BELYAKOVA ANNA VLADIMIROVNA', $parsed['responsible_name']);
        $this->assertSame("SHUKURXONOV ISLOMXON NE'MATULLAXON O'G'LI", $parsed['posted_by_name']);
        $this->assertSame(7, $parsed['repeat_number']);
    }

    public function test_first_penalty_without_anybody_responsible(): void
    {
        $parsed = app(TelegramPenaltyMessageParser::class)->parse(self::NOBODY);

        $this->assertSame('EGS19815', $parsed['request_number']);
        $this->assertSame(1, $parsed['repeat_number']);
        $this->assertNull($parsed['responsible_name']);
        $this->assertNull($parsed['posted_by_name']);
        $this->assertSame(0, $parsed['telegram_groups']);
    }

    /*
     * ------------------------------------------------------------------
     * Who gets it
     * ------------------------------------------------------------------
     */

    public function test_existing_person_is_found_by_name(): void
    {
        $person = $this->person();

        $check = $this->penalty();

        $this->assertSame($person->id, $check->operation_user_id);
        $this->assertSame(1, OperationUser::query()->count());
    }

    public function test_unknown_sales_manager_is_created_as_sales_and_skipped_until_linked(): void
    {
        $check = $this->penalty(text: self::SALES);

        $person = OperationUser::query()->findOrFail($check->operation_user_id);

        $this->assertSame('BELYAKOVA ANNA VLADIMIROVNA', $person->name);
        $this->assertSame(OperationUser::ROLE_SALES, $person->role);
        $this->assertSame(TelegramClientCheckStatus::Skipped, $check->status);
        $this->assertSame(TelegramClientCheck::REASON_RESPONSIBLE_UNREACHABLE, $check->reason);
        $this->assertSame(3, $check->level);
        $this->assertSame([], $this->messages->calls);
    }

    public function test_penalty_without_anybody_responsible_is_kept_and_not_sent(): void
    {
        $check = $this->penalty(text: self::NOBODY);

        $this->assertSame(TelegramClientCheckStatus::Skipped, $check->status);
        $this->assertSame(TelegramClientCheck::REASON_RESPONSIBLE_MISSING, $check->reason);
        $this->assertSame('EGS19815', $check->request_number);
        $this->assertSame(0, OperationUser::query()->count());
        $this->assertSame([], $this->messages->calls);
    }

    /*
     * ------------------------------------------------------------------
     * Delivery
     * ------------------------------------------------------------------
     */

    public function test_forwarded_at_once_commented_after_the_batch(): void
    {
        $this->person();

        $check = $this->penalty(repeat: 1);

        $this->assertSame(['forward'], $this->messages->kinds());
        $this->assertSame(TelegramClientCheckStatus::Forwarded, $check->refresh()->status);

        [, $forward] = $this->messages->calls[0];
        $this->assertSame(self::BOT_CHAT_ID, $forward['from_peer']);
        $this->assertSame([$check->telegram_message_id], $forward['id']);
        $this->assertSame('@afzal', $forward['to_peer']);

        /* the burst may not be over yet */
        app(ClientCheckSender::class)->flush($this->telegram);
        $this->assertSame(['forward'], $this->messages->kinds());

        $this->flushAfterQuiet();

        $this->assertSame(['forward', 'send'], $this->messages->kinds());
        /* An operator: Uzbek, and the bot's "2 ч" in Uzbek too. */
        $this->assertSame(
            "U1 PULATOV AFZAL AHMADJON O&#039;G&#039;LI #TLS04834 2 soat",
            $this->messages->sent()[0]['message'],
        );
        $this->assertSame('@afzal', $this->messages->sent()[0]['peer']);
        $this->assertSame(TelegramClientCheckStatus::Sent, $check->refresh()->status);
    }

    /**
     * The bot's number already says how many times a request was sent:
     * only a re-send of the same request is stacked, never two requests.
     */
    public function test_only_the_same_request_is_stacked(): void
    {
        $this->person();

        $first = $this->penalty(repeat: 1, request: 'EGS1');
        Carbon::setTestNow(now()->addSecond());
        $this->penalty(repeat: 5, request: 'EGS2');
        Carbon::setTestNow(now()->addSecond());
        $again = $this->penalty(repeat: 2, request: 'EGS1');

        $this->flushAfterQuiet();

        $this->assertSame(['forward', 'forward', 'forward', 'send', 'send'], $this->messages->kinds());

        $comments = array_column($this->messages->sent(), 'message');
        sort($comments);

        /* EGS1 once, at its higher number; EGS2 on its own. */
        $this->assertSame(['U2 №2', 'U4+ №5'], $comments);

        $this->assertSame(2, $again->refresh()->batch_count);
        $this->assertNull($first->refresh()->comment);
        $this->assertSame(
            3,
            TelegramClientCheck::query()->where('status', TelegramClientCheckStatus::Sent)->count(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Language and respect
    |--------------------------------------------------------------------------
    */

    public function test_sales_are_written_to_in_russian(): void
    {
        $this->person('BELYAKOVA ANNA VLADIMIROVNA', [
            'telegram_username' => 'anna',
            'telegram_id' => 777,
            'role' => OperationUser::ROLE_SALES,
        ]);

        $check = $this->penalty(text: str_replace('№7', '№1', self::SALES));
        $this->flushAfterQuiet();

        $this->assertSame('R1 BELYAKOVA ANNA VLADIMIROVNA #LOG00663 1 ч', $this->messages->sent()[0]['message']);
        $this->assertSame(['language' => 'ru', 'tone' => 'plain', 'phrase_level' => 0], $check->refresh()->metrics);
    }

    public function test_a_language_picked_on_the_card_beats_the_role(): void
    {
        $this->person(attributes: ['language' => OperationUser::LANGUAGE_RU]);

        $this->penalty();
        $this->flushAfterQuiet();

        $this->assertStringStartsWith('R1 PULATOV', $this->messages->sent()[0]['message']);
    }

    public function test_older_people_get_the_respectful_text(): void
    {
        $this->person(attributes: ['respectful' => true]);

        $this->penalty(repeat: 1, request: 'A1');
        $this->flushAfterQuiet();

        /* From the 2nd on there is no respectful text: the plain one stands in. */
        $this->penalty(repeat: 2, request: 'A2');
        $this->flushAfterQuiet();

        $this->assertSame(
            ["U1R PULATOV AFZAL AHMADJON O&#039;G&#039;LI", 'U2 №2'],
            array_column($this->messages->sent(), 'message'),
        );
    }

    public function test_a_missing_language_borrows_its_own_lower_level_first(): void
    {
        $rules = app(ClientCheckRulesStore::class)->current();

        /* No Russian on the 4th: the 2nd's Russian, not the 4th's Uzbek. */
        $variant = $rules->variant('operation', 3, 'ru', 'plain');
        $this->assertSame(['R2 №{repeat_number}'], $variant['phrases']);
        $this->assertSame(['language' => 'ru', 'tone' => 'plain', 'level' => 1], array_diff_key($variant, ['phrases' => 1]));

        $this->assertSame([0, 1, 2, 3, 3], array_map(fn (int $n) => $rules->levelFor('operation', $n), [1, 2, 3, 4, 9]));
    }

    public function test_the_bot_durations_come_out_in_uzbek(): void
    {
        $this->assertSame('2 soat 3 daqiqa', ClientCheckEscalation::duration('2 ч 3 мин', 'uz'));
        $this->assertSame('1 kun 5 soat', ClientCheckEscalation::duration('1 д 5 ч', 'uz'));
        $this->assertSame('2 ч 3 мин', ClientCheckEscalation::duration('2 ч 3 мин', 'ru'));
        $this->assertSame('—', ClientCheckEscalation::duration(null, 'uz'));
    }

    public function test_different_people_get_separate_comments(): void
    {
        $this->person();
        $this->person('BELYAKOVA ANNA VLADIMIROVNA', [
            'telegram_username' => 'anna',
            'telegram_id' => 777,
            'role' => OperationUser::ROLE_SALES,
        ]);

        $this->penalty();
        $this->penalty(text: self::SALES);

        $this->flushAfterQuiet();

        $peers = array_map(static fn (array $m) => $m['peer'], $this->messages->sent());
        sort($peers);

        $this->assertSame(['@afzal', '@anna'], $peers);
    }

    public function test_falls_back_to_the_telegram_id(): void
    {
        $this->person();

        $this->messages->forwardFailsFor = ['@afzal'];

        $check = $this->penalty();
        $this->flushAfterQuiet();

        $this->assertSame('555', $check->refresh()->peer);
        $this->assertSame(555, $this->messages->sent()[0]['peer']);
    }

    public function test_a_failed_comment_is_retried_without_forwarding_again(): void
    {
        $this->person();

        $this->messages->commentFails = true;

        $check = $this->penalty();
        $this->flushAfterQuiet();

        $this->assertSame(TelegramClientCheckStatus::Failed, $check->refresh()->status);
        $this->assertSame(TelegramClientCheck::REASON_COMMENT_FAILED, $check->reason);

        $this->messages->commentFails = false;

        $this->flushAfterQuiet();

        $this->assertSame(['forward', 'send'], $this->messages->kinds());
        $this->assertSame(TelegramClientCheckStatus::Sent, $check->refresh()->status);
    }

    /**
     * The people pages read how the last private message went from the
     * person's row, for penalties the same as for report copies.
     */
    public function test_the_delivery_is_recorded_on_the_person(): void
    {
        $person = $this->person();

        $this->messages->forwardFailsFor = ['@afzal', 555];
        $this->penalty();

        $person->refresh();
        $this->assertNull($person->dm_last_sent_at);
        $this->assertStringContainsString('USERNAME_INVALID', (string) $person->dm_last_error);

        $this->messages->forwardFailsFor = [];
        $this->penalty(request: 'TLS00001');

        $person->refresh();
        $this->assertNotNull($person->dm_last_sent_at);
        $this->assertNull($person->dm_last_error);
    }

    /*
    |--------------------------------------------------------------------------
    | Switches
    |--------------------------------------------------------------------------
    */

    public function test_switched_off_penalties_send_nothing_but_are_still_counted(): void
    {
        $this->person();

        TelegramSetting::set(TelegramSetting::CLIENT_CHECKS_ENABLED, false);

        $check = $this->penalty(repeat: 7);
        $this->flushAfterQuiet();

        $this->assertSame([], $this->messages->calls);

        $check->refresh();
        $this->assertSame(TelegramClientCheckStatus::Skipped, $check->status);
        $this->assertSame(TelegramClientCheck::REASON_DISABLED, $check->reason);
        $this->assertSame(3, $check->level);
        $this->assertNotNull($check->operation_user_id);

        /* Back on: the next one goes out, the old one does not. */
        TelegramSetting::set(TelegramSetting::CLIENT_CHECKS_ENABLED, true);

        $this->penalty(request: 'TLS00002');
        $this->flushAfterQuiet();

        $this->assertSame(['forward', 'send'], $this->messages->kinds());
        $this->assertSame(TelegramClientCheckStatus::Skipped, $check->refresh()->status);
    }

    public function test_switching_off_calls_off_a_comment_still_waiting(): void
    {
        $this->person();

        $check = $this->penalty();
        $this->assertSame(TelegramClientCheckStatus::Forwarded, $check->refresh()->status);

        TelegramSetting::set(TelegramSetting::CLIENT_CHECKS_ENABLED, false);
        $this->flushAfterQuiet();

        $this->assertSame(['forward'], $this->messages->kinds());

        $check->refresh();
        $this->assertSame(TelegramClientCheckStatus::Sent, $check->status);
        $this->assertSame(TelegramClientCheck::REASON_DISABLED, $check->reason);
    }

    public function test_without_comments_only_the_forward_goes_out(): void
    {
        $this->person();

        TelegramSetting::set(TelegramSetting::CLIENT_CHECK_COMMENTS_ENABLED, false);

        $check = $this->penalty();
        $this->flushAfterQuiet();
        $this->flushAfterQuiet();

        $this->assertSame(['forward'], $this->messages->kinds());

        $check->refresh();
        $this->assertSame(TelegramClientCheckStatus::Sent, $check->status);
        $this->assertSame(TelegramClientCheck::REASON_COMMENT_DISABLED, $check->reason);
        $this->assertNull($check->comment);
    }

    /*
    |--------------------------------------------------------------------------
    | Rules saved from the panel
    |--------------------------------------------------------------------------
    */

    private function saveRules(array $levels, array $extra = []): void
    {
        app(ClientCheckRulesStore::class)->save(ClientCheckRules::fromArray([
            'levels' => $levels,
            'batch_quiet_seconds' => 20,
            'max_attempts' => 3,
            'retry_minutes' => 30,
            ...$extra,
        ]));
    }

    public function test_rules_saved_in_the_panel_replace_the_config(): void
    {
        $this->person();

        $this->saveRules([
            ['name' => 'Calm', 'phrases' => ['uz' => ['plain' => ['DB0 {name}']]]],
            ['name' => 'Loud', 'from' => 2, 'phrases' => ['uz' => ['plain' => ['DB1 №{repeat_number}']]]],
        ]);

        $check = $this->penalty(repeat: 9);
        $this->flushAfterQuiet();

        /* Two levels only: №9 is level 1, whatever the config says. */
        $this->assertSame(1, $check->refresh()->level);
        $this->assertSame('DB1 №9', $this->messages->sent()[0]['message']);
    }

    public function test_a_level_removed_after_the_penalty_borrows_the_top_one(): void
    {
        $this->person();

        $check = $this->penalty(repeat: 7);
        $this->assertSame(3, $check->refresh()->level);

        $this->saveRules([
            ['phrases' => ['uz' => ['plain' => ['ONLY {request}']]]],
        ]);

        $this->flushAfterQuiet();

        $this->assertSame('ONLY TLS04834', $this->messages->sent()[0]['message']);
        $this->assertSame(0, $check->refresh()->comment_level);
    }

    /**
     * Rules saved before languages existed: one list of Russian phrases
     * per level, and repeat_from.
     */
    public function test_rules_saved_before_languages_still_read(): void
    {
        $rules = ClientCheckRules::fromArray([
            'levels' => [
                ['phrases' => ['old a']],
                ['repeat_from' => 3, 'phrases' => ['old b']],
            ],
        ]);

        /* ...and, from before the roles were split, both roles get them. */
        foreach (['operation', 'sales'] as $role) {
            $this->assertSame(3, $rules->levels($role)[1]['from']);
            $this->assertSame(['old b'], $rules->levels($role)[1]['phrases']['ru']['plain']);
            $this->assertSame([], $rules->levels($role)[1]['phrases']['uz']['plain']);
            $this->assertSame('all', $rules->levels($role)[1]['mode']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Operators and sales apart
    |--------------------------------------------------------------------------
    */

    private function anna(): OperationUser
    {
        return $this->person('BELYAKOVA ANNA VLADIMIROVNA', [
            'telegram_username' => 'anna',
            'telegram_id' => 777,
            'role' => OperationUser::ROLE_SALES,
        ]);
    }

    public function test_each_role_has_its_own_texts(): void
    {
        $this->person();
        $this->anna();

        $this->saveRules([], ['roles' => [
            'operation' => ['levels' => [['phrases' => ['uz' => ['plain' => ['OPER {request}']]]]]],
            'sales' => ['levels' => [['phrases' => ['ru' => ['plain' => ['SALES {request}']]]]]],
        ]]);

        $this->penalty(request: 'TLS1');
        $this->penalty(text: str_replace('№7', '№1', self::SALES));
        $this->flushAfterQuiet();

        $comments = array_column($this->messages->sent(), 'message');
        sort($comments);

        $this->assertSame(['OPER TLS1', 'SALES LOG00663'], $comments);
    }

    public function test_a_role_switched_off_gets_nothing_the_other_still_does(): void
    {
        $this->person();
        $this->anna();

        TelegramSetting::set(TelegramSetting::CLIENT_CHECKS_SALES_ENABLED, false);

        $sales = $this->penalty(text: self::SALES);
        $operation = $this->penalty();
        $this->flushAfterQuiet();

        $this->assertSame(TelegramClientCheckStatus::Skipped, $sales->refresh()->status);
        $this->assertSame(TelegramClientCheck::REASON_ROLE_DISABLED, $sales->reason);
        $this->assertSame(TelegramClientCheckStatus::Sent, $operation->refresh()->status);
        $this->assertSame(['@afzal', '@afzal'], array_map(
            static fn (array $call) => $call[1]['to_peer'] ?? $call[1]['peer'],
            $this->messages->calls,
        ));
    }

    public function test_a_level_can_send_nothing_or_only_the_forward(): void
    {
        $this->person();

        $this->saveRules([], ['roles' => [
            'operation' => ['levels' => [
                ['mode' => 'all', 'phrases' => ['uz' => ['plain' => ['ONE']]]],
                ['from' => 2, 'mode' => 'forward', 'phrases' => []],
                ['from' => 3, 'mode' => 'off', 'phrases' => []],
            ]],
            'sales' => ['levels' => [['phrases' => ['ru' => ['plain' => ['x']]]]]],
        ]]);

        $second = $this->penalty(repeat: 2, request: 'R2');
        $third = $this->penalty(repeat: 5, request: 'R5');
        $this->flushAfterQuiet();

        /* №2: forwarded, no comment after it. */
        $this->assertSame(['forward'], $this->messages->kinds());
        $this->assertSame(TelegramClientCheckStatus::Sent, $second->refresh()->status);
        $this->assertSame(TelegramClientCheck::REASON_FORWARD_ONLY, $second->reason);
        $this->assertNull($second->comment);

        /* №5: nothing at all, kept and counted. */
        $this->assertSame(TelegramClientCheckStatus::Skipped, $third->refresh()->status);
        $this->assertSame(TelegramClientCheck::REASON_LEVEL_OFF, $third->reason);
        $this->assertSame(2, $third->level);
    }

    /*
    |--------------------------------------------------------------------------
    | Working hours
    |--------------------------------------------------------------------------
    */

    public function test_penalties_outside_working_hours_are_ignored(): void
    {
        $this->person();

        foreach (['08:59:59' => 'outside', '09:00:00' => 'in', '17:59:59' => 'in', '18:00:00' => 'outside', '23:30:00' => 'outside'] as $time => $expected) {
            Carbon::setTestNow('2026-10-02 ' . $time);

            $check = $this->penalty(request: 'H' . str_replace(':', '', $time));

            if ($expected === 'outside') {
                $this->assertSame(TelegramClientCheckStatus::Skipped, $check->refresh()->status, $time);
                $this->assertSame(TelegramClientCheck::REASON_OUTSIDE_HOURS, $check->reason, $time);
            } else {
                $this->assertSame(TelegramClientCheckStatus::Forwarded, $check->refresh()->status, $time);
            }
        }

        /* Two of five went out. */
        $this->assertSame(2, count(array_filter($this->messages->kinds(), fn ($k) => $k === 'forward')));
    }

    public function test_a_failed_forward_is_not_retried_after_hours(): void
    {
        $this->person();

        Carbon::setTestNow('2026-10-02 17:59:00');
        $this->messages->forwardFailsFor = ['@afzal', 555];
        $check = $this->penalty();

        Carbon::setTestNow('2026-10-02 18:01:00');
        $this->messages->forwardFailsFor = [];
        app(ClientCheckSender::class)->flush($this->telegram);

        $this->assertSame([], $this->messages->calls);
        $this->assertSame(TelegramClientCheckStatus::Failed, $check->refresh()->status);
    }

    public function test_working_hours_read_as_saved(): void
    {
        $at = static fn (string $t) => Carbon::parse('2026-10-02 ' . $t, 'Asia/Tashkent');

        /* Saved before hours existed: the default 09:00-18:00. */
        $old = ClientCheckRules::fromArray(['levels' => []]);
        $this->assertSame(['09:00', '18:00'], [$old->workFrom, $old->workTo]);
        $this->assertFalse($old->withinWorkingHours($at('18:00')));

        /* Saved empty: every hour counts. */
        $always = ClientCheckRules::fromArray(['working_hours' => ['from' => null, 'to' => null]]);
        $this->assertTrue($always->withinWorkingHours($at('03:00')));

        /* A night window. */
        $night = ClientCheckRules::fromArray(['working_hours' => ['from' => '22:00', 'to' => '6:00']]);
        $this->assertSame('06:00', $night->workTo);
        $this->assertTrue($night->withinWorkingHours($at('23:15')));
        $this->assertTrue($night->withinWorkingHours($at('05:59')));
        $this->assertFalse($night->withinWorkingHours($at('12:00')));
    }

    /**
     * The shipped defaults: sales get one respectful text on every penalty.
     */
    public function test_sales_get_their_own_respectful_text_by_default(): void
    {
        config()->set('client_checks', require config_path('client_checks.php'));

        $this->anna();

        $this->penalty(text: self::SALES);
        $this->flushAfterQuiet();

        $this->assertSame('Обновите, пожалуйста, статус', $this->messages->sent()[0]['message']);

        $rules = app(ClientCheckRulesStore::class)->current();
        $this->assertSame(
            $rules->levels('sales')[0]['phrases']['ru']['respectful'],
            $rules->levels('sales')[0]['phrases']['ru']['plain'],
        );
    }

    public function test_failed_forwards_stop_after_the_limit(): void
    {
        $this->person();

        $this->messages->forwardFailsFor = ['@afzal', 555];

        $check = $this->penalty();

        foreach (range(1, 4) as $ignored) {
            $this->flushAfterQuiet();
        }

        $this->assertSame(3, $check->refresh()->attempts);
        $this->assertSame(TelegramClientCheck::REASON_FORWARD_FAILED, $check->reason);
        $this->assertSame([], $this->messages->calls);
    }

    public function test_the_same_message_is_handled_once(): void
    {
        $this->person();

        $make = fn () => app(ProcessClientCheckMessage::class)->execute(
            telegram: $this->telegram,
            chatId: self::BOT_CHAT_ID,
            messageId: 42,
            text: self::OPERATION,
        );

        $this->assertNotNull($make());
        $this->assertNull($make());
        $this->assertSame(1, TelegramClientCheck::query()->count());
    }

    /*
     * ------------------------------------------------------------------
     * Level
     * ------------------------------------------------------------------
     */

    public function test_level_follows_the_repeat_number(): void
    {
        $this->person();

        $levels = [];

        foreach ([1, 2, 3, 4, 7] as $i => $repeat) {
            $levels[] = $this->penalty(repeat: $repeat, request: 'R' . $i)->level;
        }

        /* 4 and everything after it is the last level. */
        $this->assertSame([0, 1, 2, 3, 3], $levels);
    }

    public function test_the_same_phrase_is_not_given_twice_in_a_row(): void
    {
        $this->person();

        $indexes = [];

        foreach (range(0, 3) as $i) {
            Carbon::setTestNow(Carbon::parse('2026-09-28 09:00:00')->addDays($i));
            $this->penalty(repeat: 3, request: 'R' . $i);
            $this->flushAfterQuiet();

            $indexes[] = TelegramClientCheck::query()->latest('id')->value('phrase_index');
        }

        for ($i = 1; $i < count($indexes); $i++) {
            $this->assertNotSame($indexes[$i - 1], $indexes[$i]);
        }
    }
}
