<?php

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Actions\ProcessClientCheckMessage;
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

        config()->set('client_checks.phrases', [
            0 => ['L0 {name} #{request}'],
            1 => ['L1 {today_count}'],
            2 => ['L2a №{repeat_number}', 'L2b №{repeat_number}'],
            3 => ['L3 №{repeat_number} {today_count}'],
        ]);

        config()->set('client_checks.batch_line', 'batch {batch_count}');

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
        $this->assertSame(
            "L0 PULATOV AFZAL AHMADJON O&#039;G&#039;LI #TLS04834",
            $this->messages->sent()[0]['message'],
        );
        $this->assertSame('@afzal', $this->messages->sent()[0]['peer']);
        $this->assertSame(TelegramClientCheckStatus::Sent, $check->refresh()->status);
    }

    public function test_a_burst_gets_one_comment_at_the_highest_level(): void
    {
        $this->person();

        $first = $this->penalty(repeat: 1, request: 'EGS1');
        Carbon::setTestNow(now()->addSecond());
        $this->penalty(repeat: 5, request: 'EGS2');
        Carbon::setTestNow(now()->addSecond());
        $last = $this->penalty(repeat: 2, request: 'EGS3');

        $this->flushAfterQuiet();

        $this->assertSame(['forward', 'forward', 'forward', 'send'], $this->messages->kinds());

        $comment = $this->messages->sent()[0]['message'];
        $this->assertStringStartsWith('L2', $comment);
        $this->assertStringEndsWith('batch 3', $comment);

        $last->refresh();
        $this->assertSame(2, $last->comment_level);
        $this->assertSame(3, $last->batch_count);
        $this->assertSame($comment, $last->comment);
        $this->assertNull($first->refresh()->comment);
        $this->assertSame(
            3,
            TelegramClientCheck::query()->where('status', TelegramClientCheckStatus::Sent)->count(),
        );
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
            'history_days' => 7,
            'max_attempts' => 3,
            'retry_minutes' => 30,
            'batch_line' => '',
            ...$extra,
        ]));
    }

    public function test_rules_saved_in_the_panel_replace_the_config(): void
    {
        $this->person();

        $this->saveRules([
            ['name' => 'Calm', 'phrases' => ['DB0 {name}']],
            ['name' => 'Loud', 'repeat_from' => 2, 'phrases' => ['DB1 №{repeat_number}']],
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
            ['phrases' => ['ONLY {request}']],
        ]);

        $this->flushAfterQuiet();

        $this->assertSame('ONLY TLS04834', $this->messages->sent()[0]['message']);
        $this->assertSame(0, $check->refresh()->comment_level);
    }

    public function test_the_old_config_layout_reads_as_the_same_ladder(): void
    {
        $rules = ClientCheckRules::fromConfig([
            'repeat_levels' => [3 => 7, 2 => 4, 1 => 2],
            'levels' => [3 => ['today' => 8, 'hour' => 5], 2 => ['today' => 5, 'repeat_within' => 30], 1 => ['today' => 3, 'week' => 8]],
            'phrases' => [0 => ['a'], 1 => ['b'], 2 => [], 3 => ['d']],
        ]);

        $this->assertSame(3, $rules->topLevel());
        $this->assertSame(0, $rules->repeatLevel(1));
        $this->assertSame(2, $rules->repeatLevel(5));
        $this->assertSame(3, $rules->repeatLevel(7));
        $this->assertSame(
            2,
            $rules->historyLevel(['hour_count' => 1, 'today_count' => 5, 'week_count' => 5, 'minutes_since_last' => null]),
        );
        /* A level without phrases borrows the one below. */
        $this->assertSame(['b'], $rules->phrases(2));
        $this->assertNull($rules->levels[0]['repeat_from']);
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

        foreach ([1, 2, 4, 7] as $i => $repeat) {
            /* hours apart, so the history alone stays at 0 */
            Carbon::setTestNow(Carbon::parse('2026-09-28 09:00:00')->addDays($i));
            $levels[] = $this->penalty(repeat: $repeat, request: 'R' . $i)->level;
        }

        $this->assertSame([0, 1, 2, 3], $levels);
    }

    public function test_level_grows_with_history_even_on_first_penalties(): void
    {
        $this->person();

        $levels = [];

        foreach (['09:00', '09:45', '10:30', '11:15', '12:00'] as $i => $time) {
            $this->at($time . ':00');
            $levels[] = $this->penalty(repeat: 1, request: 'R' . $i)->level;
        }

        /* 3rd today -> 1, 5th today -> 2 */
        $this->assertSame([0, 0, 1, 1, 2], $levels);
    }

    public function test_a_burst_is_not_a_quick_repeat(): void
    {
        $this->person();

        $this->at('09:00:00');
        $this->penalty(request: 'A');

        $this->at('09:00:01');
        $burst = $this->penalty(request: 'B');

        $this->assertNull($burst->metrics['minutes_since_last']);
        $this->assertSame(0, $burst->level);

        $this->at('09:20:01');
        $again = $this->penalty(request: 'C');

        $this->assertSame(20, $again->metrics['minutes_since_last']);
        $this->assertSame(2, $again->level);
    }

    public function test_old_penalties_cool_down(): void
    {
        $this->person();

        Carbon::setTestNow('2026-09-20 09:00:00');
        foreach (range(1, 6) as $i) {
            $this->penalty(request: 'OLD' . $i);
        }

        Carbon::setTestNow('2026-10-02 09:00:00');
        $check = $this->penalty(request: 'NEW');

        $this->assertSame(0, $check->level);
        $this->assertSame(1, $check->metrics['week_count']);
    }

    public function test_the_same_phrase_is_not_given_twice_in_a_row(): void
    {
        $this->person();

        $indexes = [];

        foreach (range(0, 3) as $i) {
            Carbon::setTestNow(Carbon::parse('2026-09-28 09:00:00')->addDays($i));
            $this->penalty(repeat: 5, request: 'R' . $i);
            $this->flushAfterQuiet();

            $indexes[] = TelegramClientCheck::query()->latest('id')->value('phrase_index');
        }

        for ($i = 1; $i < count($indexes); $i++) {
            $this->assertNotSame($indexes[$i - 1], $indexes[$i]);
        }
    }
}
