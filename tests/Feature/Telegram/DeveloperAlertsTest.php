<?php

namespace Tests\Feature\Telegram;

use App\Application\Telegram\Services\DeveloperAlerts;
use App\Models\Telegram\TelegramAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * What the developer is told, and that telling never breaks the flow it
 * reports on.
 */
class DeveloperAlertsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('telegram_alerts');

        (require base_path('database/migrations/2026_10_19_110000_create_telegram_alerts_table.php'))->up();

        config()->set('services.telegram.alerts', [
            'bot_token' => '123:SECRET',
            'chat_id' => '777',
            'window_minutes' => 30,
        ]);
    }

    public function test_a_problem_is_written_down_and_sent_once(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $alerts = app(DeveloperAlerts::class);

        $alerts->report('file_forward_failed', 'Файл не отправлен', 'CHAT_WRITE_FORBIDDEN', ['Аккаунт' => 'head_china'], 'head_china', 'route:1');
        $alerts->report('file_forward_failed', 'Файл не отправлен', 'CHAT_WRITE_FORBIDDEN', [], 'head_china', 'route:1');

        $this->assertSame(1, TelegramAlert::query()->count());
        $this->assertSame(2, TelegramAlert::query()->firstOrFail()->occurrences);
        $this->assertNotNull(TelegramAlert::query()->firstOrFail()->notified_at);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'bot123:SECRET/sendMessage')
            && $request['chat_id'] === '777'
            && str_contains($request['text'], 'head_china'));
    }

    public function test_another_problem_is_another_message(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $alerts = app(DeveloperAlerts::class);

        $alerts->report('penalty_forward_failed', 'A', about: '1');
        $alerts->report('penalty_forward_failed', 'B', about: '2');

        $this->assertSame(2, TelegramAlert::query()->count());
        Http::assertSentCount(2);
    }

    public function test_the_same_problem_after_the_window_is_told_again(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $alerts = app(DeveloperAlerts::class);

        $alerts->report('listener_down', 'Down', about: 'ceo');

        TelegramAlert::query()->update(['last_seen_at' => now()->subHour()]);

        $alerts->report('listener_down', 'Down', about: 'ceo');

        $this->assertSame(2, TelegramAlert::query()->count());
        Http::assertSentCount(2);
    }

    public function test_a_refused_message_does_not_throw_and_is_kept_on_the_row(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400)]);

        app(DeveloperAlerts::class)->report('listener_down', 'Down', about: 'ceo');

        $alert = TelegramAlert::query()->firstOrFail();

        $this->assertNull($alert->notified_at);
        $this->assertStringContainsString('400', (string) $alert->notify_error);
    }

    public function test_without_a_bot_the_problem_is_only_recorded(): void
    {
        Http::fake();

        config()->set('services.telegram.alerts.bot_token', null);

        app(DeveloperAlerts::class)->report('listener_down', 'Down', about: 'ceo');

        $this->assertSame(1, TelegramAlert::query()->count());
        $this->assertStringContainsString('not configured', (string) TelegramAlert::query()->firstOrFail()->notify_error);

        Http::assertNothingSent();
    }

    public function test_a_missing_table_never_breaks_the_flow_that_reports(): void
    {
        Schema::dropIfExists('telegram_alerts');

        app(DeveloperAlerts::class)->report('listener_down', 'Down', about: 'ceo');

        $this->assertTrue(true, 'no exception');
    }
}
