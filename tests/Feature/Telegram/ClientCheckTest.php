<?php

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Actions\ProcessClientCheckMessage;
use App\Application\Telegram\Actions\NudgeSilentClientChecks;
use App\Application\Telegram\Actions\ProcessAutoReply;
use App\Application\Telegram\Services\ClientCheckEscalation;
use App\Application\Telegram\Services\AutoReplyMatcher;
use App\Application\Telegram\Services\AutoReplyRules;
use App\Application\Telegram\Services\AutoReplyStore;
use App\Application\Telegram\Services\ClientCheckRules;
use App\Application\Telegram\Services\ClientCheckRulesStore;
use App\Application\Telegram\Services\ClientCheckSender;
use App\Application\Telegram\Services\CrmApiClient;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    public function sendMedia(array $params): array
    {
        $this->calls[] = ['media', $params];

        return ['id' => count($this->calls)];
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

        /*
         * The auto replies file: a fresh one per test, and one kind apart
         * from the config's own.
         */
        config()->set('auto_replies.path', storage_path('framework/testing/auto-replies-' . bin2hex(random_bytes(4)) . '.json'));
        config()->set('auto_replies.defaults', [
            'enabled' => true,
            'only_after_penalty' => false,
            'penalty_window_minutes' => 60,
            'cooldown_minutes' => 10,
            'max_words' => 5,
            'replies' => [
                ['name' => 'Agreed', 'keywords' => ['+', 'ok', 'хоп', "bo'ldi", '👍', 'hop aka'], 'answers' => [
                    'uz' => ['plain' => ['Rahmat, {address}'], 'respectful' => ['Rahmat, {address}!']],
                    'ru' => ['plain' => ['Спасибо, {address}'], 'respectful' => ['Спасибо большое, {address}!']],
                ]],
            ],
            'silence' => [
                'enabled' => true,
                'after_penalties' => 3,
                'answers' => ['uz' => ['plain' => ['Javob kutyapman, {address}'], 'respectful' => ['Iltimos, {address}']]],
            ],
        ]);

        /*
         * No CRM unless a test says so: credentials in .env must not
         * send the tests to the real one.
         */
        config()->set('services.crm', ['api_url' => null, 'email' => null, 'password' => null]);
        Http::preventStrayRequests();

        Carbon::setTestNow('2026-10-02 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        app(AutoReplyStore::class)->reset();

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
            $table->json('address')->nullable();
            $table->string('telegram_username')->nullable();
            $table->unsignedBigInteger('telegram_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('dm_enabled')->default(true);
            $table->timestamp('dm_last_sent_at')->nullable();
            $table->text('dm_last_error')->nullable();
            $table->timestamp('last_private_message_at')->nullable();
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
     * Several penalties of one person in a burst, different requests too:
     * every one is forwarded, one comment follows - the strongest one's.
     */
    public function test_a_burst_gets_one_comment_the_strongest(): void
    {
        $this->person();

        $first = $this->penalty(repeat: 1, request: 'EGS1');
        Carbon::setTestNow(now()->addSecond());
        $strongest = $this->penalty(repeat: 5, request: 'EGS2');
        Carbon::setTestNow(now()->addSecond());
        $again = $this->penalty(repeat: 2, request: 'EGS1');

        $this->flushAfterQuiet();

        $this->assertSame(['forward', 'forward', 'forward', 'send'], $this->messages->kinds());
        $this->assertSame('U4+ №5', $this->messages->sent()[0]['message']);

        $this->assertSame('U4+ №5', $strongest->refresh()->comment);
        $this->assertSame(3, $strongest->batch_count);
        $this->assertNull($first->refresh()->comment);
        $this->assertNull($again->refresh()->comment);
        $this->assertSame(
            3,
            TelegramClientCheck::query()->where('status', TelegramClientCheckStatus::Sent)->count(),
        );
    }

    /**
     * The case from 2026-10-06: two requests on the top level, the same
     * text twice in a row. Now one comment, the latest one's.
     */
    public function test_on_the_same_level_the_latest_speaks(): void
    {
        $this->person();

        $this->penalty(repeat: 4, request: 'EGS19496');
        Carbon::setTestNow(now()->addSecond());
        $latest = $this->penalty(repeat: 4, request: 'EGS19709');

        $this->flushAfterQuiet();

        $this->assertSame(['forward', 'forward', 'send'], $this->messages->kinds());
        $this->assertSame('U4+ №4', $latest->refresh()->comment);
    }

    /**
     * A penalty on a "forward only" level does not silence another
     * request's comment in the same burst.
     */
    public function test_a_forward_only_level_does_not_silence_the_burst(): void
    {
        $this->person();

        $this->saveRules([], ['roles' => [
            'operation' => ['levels' => [
                ['mode' => 'all', 'phrases' => ['uz' => ['plain' => ['ONE #{request}']]]],
                ['from' => 2, 'mode' => 'forward', 'phrases' => []],
            ]],
            'sales' => ['levels' => [['phrases' => ['ru' => ['plain' => ['x']]]]]],
        ]]);

        $this->penalty(repeat: 1, request: 'R1');
        $this->penalty(repeat: 7, request: 'R7');

        $this->flushAfterQuiet();

        $this->assertSame(['forward', 'forward', 'send'], $this->messages->kinds());
        $this->assertSame('ONE #R1', $this->messages->sent()[0]['message']);
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

    /*
    |--------------------------------------------------------------------------
    | Auto replies: what the person writes in private
    |--------------------------------------------------------------------------
    */

    private function reply(string $text, int $from = 555): ?string
    {
        return app(ProcessAutoReply::class)->execute(
            telegram: $this->telegram,
            senderId: $from,
            messageId: ++$this->messageId,
            text: $text,
        );
    }

    /** A penalty forwarded and commented, the messages cleared. */
    private function penaltySent(int $repeat = 1): TelegramClientCheck
    {
        $check = $this->penalty(repeat: $repeat);
        $this->flushAfterQuiet();
        $this->messages->calls = [];

        return $check;
    }

    /**
     * @param array<string, mixed> $override
     */
    private function saveAutoReplies(array $override): void
    {
        $store = app(AutoReplyStore::class);

        $store->save(AutoReplyRules::fromArray([...$store->defaults()->toArray(), ...$override]));
    }

    public function test_a_plus_after_a_penalty_is_thanked_by_how_the_person_is_called(): void
    {
        $this->person(attributes: ['address' => ['uz' => 'Afzal aka']]);
        $check = $this->penaltySent();

        $this->assertSame('Rahmat, Afzal aka', $this->reply('+'));

        $this->assertSame(['send'], $this->messages->kinds());
        $sent = $this->messages->sent()[0];
        $this->assertSame(555, $sent['peer']);
        $this->assertSame('Rahmat, Afzal aka', $sent['message']);
        /* in answer to their message */
        $this->assertSame($this->messageId, $sent['reply_to']['reply_to_msg_id']);

        /* kept on the penalty for the journal */
        $check->refresh();
        $this->assertSame('+', $check->reply_text);
        $this->assertSame('Agreed', $check->reply_kind);
        $this->assertSame('Rahmat, Afzal aka', $check->reply_answer);
        $this->assertNotNull($check->reply_answered_at);
    }

    public function test_without_an_address_it_is_left_out_with_its_comma(): void
    {
        $this->person();
        $this->penaltySent();

        $this->assertSame('Rahmat', $this->reply('ok'));
    }

    public function test_the_answer_comes_in_the_person_language_and_tone(): void
    {
        $this->anna()->update(['respectful' => true, 'address' => ['ru' => 'Анна Владимировна']]);

        $this->assertSame('Спасибо большое, Анна Владимировна!', $this->reply('Ок👍🏻', from: 777));
    }

    /**
     * Not only after a penalty: any private message of an operator or a
     * sales manager is answered.
     */
    public function test_any_message_of_a_known_person_is_answered(): void
    {
        $this->person(attributes: ['address' => ['uz' => 'Afzal aka']]);

        $this->assertSame('Rahmat, Afzal aka', $this->reply('hop aka'));

        /* Strangers are not. */
        $this->assertNull($this->reply('+', from: 999));

        $this->assertSame(['send'], $this->messages->kinds());
    }

    public function test_the_cooldown_keeps_ok_ok_ok_to_one_thanks(): void
    {
        $this->person();

        $this->reply('+');
        $this->reply('ok');
        $this->assertSame(['send'], $this->messages->kinds());

        Carbon::setTestNow(now()->addMinutes(11));
        $this->reply('ok');
        $this->assertSame(['send', 'send'], $this->messages->kinds());
    }

    public function test_a_penalty_reply_is_answered_once_even_inside_the_cooldown(): void
    {
        $this->person();

        /* A "thanks" just now, then a penalty: its reply is still answered. */
        $this->reply('+');
        $check = $this->penaltySent();

        $this->reply('+');
        $this->assertSame(['send'], $this->messages->kinds());
        $this->assertNotNull($check->refresh()->reply_answered_at);

        /* Answered: the next "ok" falls under the cooldown. */
        $this->reply('ok');
        $this->assertSame(['send'], $this->messages->kinds());
        $this->assertSame('+', $check->refresh()->reply_text);
    }

    public function test_only_after_a_penalty_when_the_file_says_so(): void
    {
        $this->saveAutoReplies(['only_after_penalty' => true]);
        $this->person();

        $this->assertNull($this->reply('+'));

        $this->penaltySent();
        $this->assertSame('Rahmat', $this->reply('+'));

        /* Too late after it. */
        $this->penaltySent();
        Carbon::setTestNow(now()->addMinutes(61));
        $this->assertNull($this->reply('+'));
    }

    public function test_anything_else_is_kept_on_the_penalty_but_not_answered(): void
    {
        $this->person();
        $check = $this->penaltySent();

        $this->assertNull($this->reply('Mashina hali topilmadi'));
        $check->refresh();
        $this->assertSame('Mashina hali topilmadi', $check->reply_text);
        $this->assertNull($check->reply_kind);
        $this->assertNull($check->reply_answer);

        /* A long message is a conversation, even with an "ok" in it. */
        $this->assertNull($this->reply('ok lekin mashina hali ham topilmadi'));

        $this->assertSame('Rahmat', $this->reply('Bo‘ldi'));
        $this->assertSame('Agreed', $check->refresh()->reply_kind);
    }

    public function test_switched_off_or_muted_nothing_is_answered(): void
    {
        $person = $this->person();

        $this->saveAutoReplies(['enabled' => false]);
        $this->assertNull($this->reply('+'));

        $this->saveAutoReplies(['enabled' => true]);
        $person->update(['dm_enabled' => false]);
        $this->assertNull($this->reply('+'));

        $this->assertSame([], $this->messages->kinds());
    }

    public function test_a_failed_answer_is_tried_again_on_the_next_message(): void
    {
        $this->person();

        $this->messages->commentFails = true;
        $this->assertNull($this->reply('+'));

        $this->messages->commentFails = false;
        $this->assertSame('Rahmat', $this->reply('ok'));
    }

    public function test_the_file_is_what_the_listener_reads(): void
    {
        $this->person();

        $this->saveAutoReplies(['replies' => [
            ['name' => 'Done', 'keywords' => ['qildim'], 'answers' => ['uz' => ['plain' => ['Zo\'r, {request}']]]],
        ]]);

        $file = json_decode((string) file_get_contents(config('auto_replies.path')), true);
        $this->assertSame(['qildim'], $file['replies'][0]['keywords']);

        /* "+" is the defaults' keyword, not the file's. */
        $this->assertNull($this->reply('+'));

        /* Without a penalty, {request} reads "—". */
        $this->assertSame('Zo\'r, —', $this->reply('Qildim'));

        /* Edited by hand, it applies to the next message. */
        Carbon::setTestNow(now()->addHour());
        $file['replies'][0]['answers']['uz']['plain'] = ['Barakalla'];
        file_put_contents(config('auto_replies.path'), json_encode($file));
        $this->assertSame('Barakalla', $this->reply('qildim'));

        /* Broken by hand: nothing is answered, rather than the defaults. */
        Carbon::setTestNow(now()->addHour());
        file_put_contents(config('auto_replies.path'), '{"replies": [');
        $this->assertNotNull(app(AutoReplyStore::class)->error());
        $this->assertNull($this->reply('+'));
    }

    public function test_replies_are_matched_loosely(): void
    {
        $rules = app(AutoReplyStore::class)->current();
        $matcher = new AutoReplyMatcher();

        foreach (['+', '++', '+1', 'OK', 'okkk', 'Ok👍🏻', '👍', 'Хоп', 'ok!', "bo'ldi", 'bo‘ldi', 'boʻldi', 'Hop aka'] as $text) {
            $this->assertSame(0, $matcher->match($rules, $text), $text);
        }

        foreach (['', 'hop', 'okay then', 'nima?', 'kok', 'ok bir ikki uch tort besh'] as $text) {
            $this->assertNull($matcher->match($rules, $text), $text);
        }

        $this->assertSame(['ok', '👍'], AutoReplyMatcher::words('Ok👍🏻!'));
    }

    public function test_the_address_goes_into_penalty_comments_too(): void
    {
        $this->saveRules([['phrases' => ['uz' => ['plain' => ['{address}, narx bering']]]]]);

        $this->person(attributes: ['address' => ['uz' => 'Afzal aka']]);
        $this->penalty();
        $this->flushAfterQuiet();

        $this->assertSame('Afzal aka, narx bering', $this->messages->sent()[0]['message']);
    }

    public function test_an_address_in_one_language_serves_the_other(): void
    {
        $person = $this->person(attributes: ['address' => ['ru' => 'Афзал']]);

        $this->assertSame('Афзал', $person->addressFor('uz'));
        $this->assertNull($this->person('SOMEONE ELSE', ['telegram_username' => 'x', 'telegram_id' => 1])->addressFor('uz'));
    }

    /**
     * Real answers to "обновите статус" / "Narx berib yubor" (2026-10-06),
     * against the one kind config/auto_replies.php ships with: anything
     * that is an "ok", a "done" or a "will do" is thanked.
     */
    public function test_the_shipped_kinds_read_real_answers(): void
    {
        $rules = AutoReplyRules::fromArray((require config_path('auto_replies.php'))['defaults']);
        $matcher = new AutoReplyMatcher();

        $cases = [
            'обновила' => 'Благодарность',
            'Обновила' => 'Благодарность',
            'Done ✅' => 'Благодарность',
            'Ассалому алайкум Ёпаман акажон узим' => 'Благодарность',
            "Assalomu aleykum xo'p bo'ladi" => 'Благодарность',
            '+' => 'Благодарность',
            /* For a person to read: no answer. */
            'хали клент билан гаплашолмадим телефонни кутармади .' => null,
            'Ещё ждем ответ от клиента' => null,
            'Aka narx berdimku' => null,
            'буни системада бошка нарх беришди' => null,
            '66 берганди' => null,
            '66 млн перечисления хисобини олинг' => null,
            'Да' => null,
        ];

        foreach ($cases as $text => $kind) {
            $index = $matcher->match($rules, $text);

            $this->assertSame($kind, $index !== null ? $rules->name($index) : null, $text);
        }
    }

    public function test_a_star_takes_any_ending_and_a_kind_may_allow_longer_messages(): void
    {
        $rules = AutoReplyRules::fromArray([
            'max_words' => 3,
            'replies' => [
                ['name' => 'Client', 'max_words' => 8, 'keywords' => ['клиент*'], 'answers' => ['uz' => ['plain' => ['x']]]],
                ['name' => 'Ok', 'keywords' => ['ok'], 'answers' => ['uz' => ['plain' => ['x']]]],
            ],
        ]);
        $matcher = new AutoReplyMatcher();

        $this->assertSame(0, $matcher->match($rules, 'клиент трубку не берет пока что'));
        $this->assertSame(0, $matcher->match($rules, 'Ждём клиента'));
        /* the start of a word, not the middle */
        $this->assertNull($matcher->match($rules, 'суперклиент'));
        /* "ok" keeps the file's 3 words */
        $this->assertSame(1, $matcher->match($rules, 'ok aka'));
        $this->assertNull($matcher->match($rules, 'ok aka hozir qilaman'));
        /* ё/е and Uzbek Cyrillic marks do not count */
        $this->assertSame(['еще', 'кутаман'], AutoReplyMatcher::words('Ещё қутаман'));
    }

    /*
    |--------------------------------------------------------------------------
    | The nudge when nobody answers
    |--------------------------------------------------------------------------
    */

    private function nudge(): void
    {
        app(NudgeSilentClientChecks::class)->execute($this->telegram);
    }

    /**
     * $count penalties to the same person, each forwarded and commented a
     * few minutes apart; the messages cleared.
     */
    private function ignoredPenalties(int $count): TelegramClientCheck
    {
        for ($i = 1; $i <= $count; $i++) {
            $check = $this->penaltySent(repeat: $i);
            Carbon::setTestNow(now()->addMinutes(3));
        }

        return $check;
    }

    public function test_the_third_ignored_penalty_is_nudged(): void
    {
        $this->person(attributes: ['address' => ['uz' => 'Afzal aka']]);

        /* Two ignored: not yet. */
        $this->ignoredPenalties(2);
        $this->nudge();
        $this->assertSame([], $this->messages->kinds());

        $third = $this->penaltySent(repeat: 3);

        /* Not glued to the comment. */
        $this->nudge();
        $this->assertSame([], $this->messages->kinds());

        Carbon::setTestNow(now()->addMinutes(3));
        $this->nudge();
        $this->nudge();

        $this->assertSame(['send'], $this->messages->kinds());
        $sent = $this->messages->sent()[0];
        $this->assertSame('@afzal', $sent['peer']);
        $this->assertSame('Javob kutyapman, Afzal aka', $sent['message']);

        $third->refresh();
        $this->assertNotNull($third->nudged_at);
        $this->assertSame('Javob kutyapman, Afzal aka', $third->nudge_text);

        /* The count starts over: two more are not enough. */
        $this->messages->calls = [];
        Carbon::setTestNow(now()->addMinute());
        $this->ignoredPenalties(2);
        $this->nudge();
        $this->assertSame([], $this->messages->kinds());

        $this->penaltySent();
        Carbon::setTestNow(now()->addMinutes(3));
        $this->nudge();
        $this->assertSame(['send'], $this->messages->kinds());
    }

    public function test_any_message_of_theirs_starts_the_count_over(): void
    {
        $this->person();
        $this->ignoredPenalties(2);

        /* Not something we answer - still not silence. */
        $this->reply('mashina hali topilmadi, kutyapmiz, keyin aytaman albatta sizga');
        $this->messages->calls = [];
        Carbon::setTestNow(now()->addMinute());

        $this->ignoredPenalties(2);
        $this->nudge();
        $this->assertSame([], $this->messages->kinds());

        $this->ignoredPenalties(1);
        $this->nudge();
        $this->assertSame(['send'], $this->messages->kinds());
    }

    public function test_a_backlog_is_not_nudged(): void
    {
        $this->person();
        $this->ignoredPenalties(3);

        /* The listener was down: the comment is long past. */
        Carbon::setTestNow(now()->addHours(2));
        $this->nudge();

        $this->assertSame([], $this->messages->kinds());
    }

    public function test_no_nudge_when_switched_off_or_after_hours(): void
    {
        $this->person();

        $this->saveAutoReplies(['silence' => ['enabled' => false, 'after_penalties' => 3, 'answers' => []]]);
        $this->ignoredPenalties(3);
        $this->nudge();
        $this->assertSame([], $this->messages->kinds());

        /* On again, but past 18:00. */
        app(AutoReplyStore::class)->reset();
        $this->at('17:50:00');
        $this->ignoredPenalties(3);
        Carbon::setTestNow(now()->addMinutes(2));
        $this->nudge();
        $this->assertSame([], $this->messages->kinds());
    }

    /*
     * ------------------------------------------------------------------
     * GIFs and voice messages
     * ------------------------------------------------------------------
     */

    /**
     * A file in the media folder, as AutoReplyMedia::store() names it.
     */
    private function mediaFile(string $extension): string
    {
        $directory = storage_path('framework/testing/auto-replies-media');
        config()->set('auto_replies.media_path', $directory);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $file = bin2hex(random_bytes(12)) . '.' . $extension;
        file_put_contents($directory . DIRECTORY_SEPARATOR . $file, 'x');

        return $file;
    }

    public function test_a_gif_may_be_the_answer_but_not_a_voice_in_another_language(): void
    {
        $this->person();
        $gif = $this->mediaFile('mp4');
        $voiceRu = $this->mediaFile('ogg');

        $this->saveAutoReplies(['replies' => [[
            'name' => 'Thanks',
            'keywords' => ['ok'],
            'answers' => [],
            'media' => ['gifs' => [['file' => $gif, 'name' => 'clap.mp4']], 'voices' => ['ru' => [['file' => $voiceRu, 'name' => 'spasibo.ogg']]]],
        ]]]);

        /* An Uzbek operator: no Russian voice, the GIF is all there is. */
        $this->assertSame('GIF · clap.mp4', $this->reply('ok'));

        [$kind, $params] = $this->messages->calls[0];
        $this->assertSame('media', $kind);
        $this->assertSame(555, $params['peer']);
        $this->assertSame('inputMediaUploadedDocument', $params['media']['_']);
        $this->assertSame('video/mp4', $params['media']['mime_type']);
        $this->assertSame('documentAttributeAnimated', $params['media']['attributes'][1]['_']);
    }

    public function test_a_voice_goes_as_a_voice_message(): void
    {
        $this->person(attributes: ['language' => 'ru']);
        $voice = $this->mediaFile('ogg');

        $this->saveAutoReplies(['replies' => [[
            'name' => 'Thanks',
            'keywords' => ['ok'],
            'answers' => [],
            'media' => ['gifs' => [], 'voices' => ['ru' => [['file' => $voice, 'name' => 'spasibo.ogg']]]],
        ]]]);

        $this->assertSame('🎤 spasibo.ogg', $this->reply('ok'));

        $media = $this->messages->calls[0][1]['media'];
        $this->assertSame('audio/ogg', $media['mime_type']);
        $this->assertTrue($media['attributes'][0]['voice']);
    }

    public function test_media_names_from_a_hand_edit_are_not_trusted(): void
    {
        $rules = AutoReplyRules::fromArray(['replies' => [[
            'keywords' => ['ok'],
            'media' => ['gifs' => [['file' => '../../.env'], ['file' => 'abc.gif']], 'voices' => ['uz' => [['file' => str_repeat('a', 24) . '.mp3']]]],
        ]]]);

        $this->assertSame([], $rules->mediaFiles());
    }

    /*
     * ------------------------------------------------------------------
     * "Актуальный" with a carrier price: the sales manager's turn
     * ------------------------------------------------------------------
     */

    private const ACTUAL = <<<'TXT'
        ⚠️ Штраф по запросу #LOG00665
        Статус: Актуальный
        Время на статус: 3 ч
        В статусе с: 02.10.2026 08:53
        Стоит в статусе: 1 ч 1 мин
        Ответственный (Operation): PULATOV AFZAL AHMADJON O'G'LI

        PULATOV AFZAL AHMADJON O'G'LI: 331 / 623

        Открыть запрос (https://crm.zanjeer.uz/queries/queries?filter[search]=LOG00665)
        TXT;

    /**
     * The CRM API answering a search for LOG00665.
     *
     * @param list<array<string, mixed>> $payments
     */
    private function crm(array $payments, string $customId = 'LOG00665', int $loginStatus = 200): void
    {
        Cache::forget('crm_api_token');

        config()->set('services.crm', [
            'api_url' => 'https://crm.test/api',
            'email' => 'bot@test',
            'password' => 'secret',
        ]);

        Http::fake([
            'crm.test/api/v1/login' => Http::response(['token' => 'T1'], $loginStatus),
            'crm.test/api/v1/queries*' => Http::response(['success' => true, 'data' => ['data' => [[
                'custom_id' => $customId,
                'sales' => ['id' => 25, 'name' => 'BELYAKOVA ANNA VLADIMIROVNA'],
                'payments' => $payments,
            ]]]]),
        ]);
    }

    private function carrierPrice(string $price = '56000000.00'): array
    {
        return ['payment_type' => 'carrier', 'price' => $price, 'currency' => ['name' => 'UZS']];
    }

    public function test_actual_with_a_carrier_price_goes_to_the_sales_manager(): void
    {
        $this->person();
        $anna = $this->anna();
        $this->crm([['payment_type' => 'customer', 'price' => '60000000.00'], $this->carrierPrice()]);

        $check = $this->penalty(text: self::ACTUAL);

        $this->assertSame($anna->id, $check->refresh()->operation_user_id);
        $this->assertSame('@anna', $this->messages->calls[0][1]['to_peer']);
        $this->assertSame("PULATOV AFZAL AHMADJON O'G'LI", $check->responsible_name);
        $this->assertSame('56000000.00', $check->parsed['sales_turn']['carrier_price']);
        $this->assertSame('UZS', $check->parsed['sales_turn']['currency']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'search=LOG00665')
            && str_contains(urldecode($request->url()), 'include=operations,sales,payments.currency')
            && $request->hasHeader('Authorization', 'Bearer T1'));
    }

    public function test_an_unknown_sales_manager_is_created_as_sales(): void
    {
        $this->person();
        $this->crm([$this->carrierPrice()]);

        $check = $this->penalty(text: self::ACTUAL);

        $this->assertSame('BELYAKOVA ANNA VLADIMIROVNA', $check->refresh()->operationUser->name);
        $this->assertSame(OperationUser::ROLE_SALES, $check->operationUser->role);
    }

    public function test_without_a_carrier_price_it_stays_with_the_operator(): void
    {
        $person = $this->person();
        $this->anna();
        $this->crm([$this->carrierPrice('0.00'), ['payment_type' => 'customer', 'price' => '60000000.00']]);

        $check = $this->penalty(text: self::ACTUAL);

        $this->assertSame($person->id, $check->refresh()->operation_user_id);
        $this->assertArrayNotHasKey('sales_turn', $check->parsed);
    }

    public function test_another_request_in_the_search_is_not_taken(): void
    {
        $person = $this->person();
        $this->anna();
        $this->crm([$this->carrierPrice()], customId: 'LOG006650');

        $this->assertSame($person->id, $this->penalty(text: self::ACTUAL)->refresh()->operation_user_id);
    }

    public function test_other_statuses_are_not_looked_up(): void
    {
        $person = $this->person();
        $this->anna();
        $this->crm([$this->carrierPrice()]);

        $this->assertSame($person->id, $this->penalty()->refresh()->operation_user_id);
        Http::assertNothingSent();
    }

    public function test_a_crm_that_fails_leaves_it_with_the_operator(): void
    {
        $person = $this->person();
        $this->crm([$this->carrierPrice()], loginStatus: 500);

        $check = $this->penalty(text: self::ACTUAL);

        $this->assertSame($person->id, $check->refresh()->operation_user_id);
        $this->assertSame(['forward'], $this->messages->kinds());
    }

    public function test_the_token_is_cached_for_the_next_penalty(): void
    {
        $this->person();
        $this->anna();
        $this->crm([$this->carrierPrice()]);

        $this->penalty(text: self::ACTUAL);
        $this->penalty(text: self::ACTUAL);

        /* One login, two searches. */
        Http::assertSentCount(3);
        $this->assertSame('T1', Cache::get('crm_api_token'));
    }

    public function test_a_dropped_token_is_logged_in_again_once(): void
    {
        config()->set('services.crm', [
            'api_url' => 'https://crm.test/api',
            'email' => 'bot@test',
            'password' => 'secret',
        ]);
        Cache::put('crm_api_token', 'OLD', now()->addDay());

        Http::fake([
            'crm.test/api/v1/login' => Http::response(['data' => ['token' => 'T2']]),
            'crm.test/api/v1/queries*' => fn ($request) => $request->hasHeader('Authorization', 'Bearer OLD')
                ? Http::response([], 401)
                : Http::response(['data' => ['data' => []]]),
        ]);

        app(CrmApiClient::class)->searchQueries('LOG00665');

        Http::assertSentCount(3);
        $this->assertSame('T2', Cache::get('crm_api_token'));
    }
}
