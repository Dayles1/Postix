<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\TelegramAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells the developer that an account could not do something.
 *
 * A penalty that reached nobody (no public username, an id the account has
 * never seen), a file that was not forwarded, a listener that keeps dying:
 * each is written down (telegram_alerts, shown on the "How it works" page)
 * and sent to the developer's Telegram chat through a bot
 * (services.telegram.alerts), so nobody has to read the logs to find out.
 *
 * The same problem is told once per window; coming again it only raises
 * the count. Never throws and never waits long: a flow that failed must not
 * fail a second time because the report did.
 */
final class DeveloperAlerts
{
    private const TEXT_LIMIT = 3500;

    /* The call blocks the event loop of the process that reports, so it is kept short. */
    private const HTTP_TIMEOUT_SECONDS = 4;

    private const HTTP_CONNECT_TIMEOUT_SECONDS = 2;

    /**
     * @param array<string, mixed> $context  shown under the text, one "key: value" a line
     * @param string|null          $about    what it is about (a person id, a file name): the same one is the same problem
     */
    public function report(
        string $kind,
        string $title,
        string $details = '',
        array $context = [],
        ?string $listenerKey = null,
        ?string $about = null,
    ): void {
        try {
            $fingerprint = mb_substr($kind . ':' . ($about ?? $title), 0, 190);

            $existing = TelegramAlert::query()
                ->where('fingerprint', $fingerprint)
                ->where('last_seen_at', '>=', now()->subMinutes($this->windowMinutes()))
                ->latest('id')
                ->first();

            if ($existing instanceof TelegramAlert) {
                $existing->update([
                    'occurrences' => $existing->occurrences + 1,
                    'last_seen_at' => now(),
                    'details' => $details !== '' ? $details : $existing->details,
                ]);

                return;
            }

            $alert = TelegramAlert::query()->create([
                'kind' => $kind,
                'fingerprint' => $fingerprint,
                'title' => mb_substr($title, 0, 255),
                'details' => $details !== '' ? $details : null,
                'context' => $context !== [] ? $context : null,
                'listener_key' => $listenerKey,
                'occurrences' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);

            Log::warning('Developer alert: ' . $title, ['kind' => $kind, 'listener' => $listenerKey] + $context);

            $this->send($alert);
        } catch (Throwable $e) {
            Log::error('Developer alert could not be recorded', [
                'kind' => $kind,
                'title' => $title,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Whether the bot and the chat are set, i.e. whether alerts leave the server.
     */
    public function configured(): bool
    {
        return filled(config('services.telegram.alerts.bot_token')) && filled(config('services.telegram.alerts.chat_id'));
    }

    /**
     * Sends one text to the developer's chat; the error, when it fails.
     */
    public function sendText(string $text): ?string
    {
        if (! $this->configured()) {
            return 'Alert bot is not configured (TELEGRAM_ALERT_BOT_TOKEN / TELEGRAM_ALERT_CHAT_ID).';
        }

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT_SECONDS)
                ->connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
                ->asForm()
                ->post('https://api.telegram.org/bot' . config('services.telegram.alerts.bot_token') . '/sendMessage', [
                    'chat_id' => config('services.telegram.alerts.chat_id'),
                    'text' => mb_substr($text, 0, self::TEXT_LIMIT),
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            return $response->successful()
                ? null
                : 'HTTP ' . $response->status() . ': ' . mb_substr((string) $response->body(), 0, 300);
        } catch (Throwable $e) {
            /* The text of a failed request carries the URL, which carries the token. */
            return mb_substr(preg_replace('#bot\d+:[\w-]+#', 'bot***', $e->getMessage()) ?? '', 0, 300);
        }
    }

    private function send(TelegramAlert $alert): void
    {
        $lines = ['⚠️ <b>' . e($alert->title) . '</b>'];

        if ($alert->listener_key !== null) {
            $lines[] = '🤖 ' . e($alert->listener_key);
        }

        if ($alert->details !== null) {
            $lines[] = '';
            $lines[] = e($alert->details);
        }

        foreach ((array) $alert->context as $key => $value) {
            $lines[] = e((string) $key) . ': ' . e(is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        $lines[] = '';
        $lines[] = '#' . e($alert->kind) . ' · ' . now()->format('Y-m-d H:i:s');

        $error = $this->sendText(implode("\n", $lines));

        $alert->update($error === null
            ? ['notified_at' => now(), 'notify_error' => null]
            : ['notify_error' => $error]);
    }

    private function windowMinutes(): int
    {
        return max(1, (int) config('services.telegram.alerts.window_minutes', 30));
    }
}
