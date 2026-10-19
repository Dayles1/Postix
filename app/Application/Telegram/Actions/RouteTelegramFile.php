<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Application\Telegram\Services\DeveloperAlerts;
use App\Application\Telegram\Services\DutyOwners;
use App\Models\Telegram\TelegramFileForward;
use App\Models\Telegram\TelegramFileRoute;
use App\Models\Telegram\TelegramListener;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A file from the notifications bot, forwarded to the groups its kind is
 * set to. The kind is found by name: the caption ("Zitic — 2026-10-09")
 * or, without one, the file name ("qozoq_..._tekshirilgan.xlsx").
 */
final class RouteTelegramFile
{
    /**
     * True when the file was forwarded. False when the chat is not a file
     * source or no kind matched: the message then goes on through the
     * usual flow, so a file posted by hand in the test group with a
     * driver-check caption is still read as one.
     */
    public function execute(
        SimpleEventHandler $telegram,
        int $chatId,
        int $messageId,
        string $fileName,
        string $caption,
        ?string $listenerKey = null,
    ): bool {
        if (! $this->isSource($chatId) || $messageId <= 0) {
            return false;
        }

        $route = $this->match($caption . ' ' . $fileName);

        $owners = app(DutyOwners::class);

        /*
         * Every account in the bot's chat sees the file; the one the route
         * is set to (DutyOwners) forwards it. A file of no kind is the
         * general owner's to record.
         */
        if ($route === null) {
            if (! $owners->ownedBy(TelegramListener::DUTY_FILE_ROUTES, $listenerKey)) {
                return false;
            }
        } elseif ($owners->fileOwner($route->listener_key) !== $listenerKey) {
            return true;
        }

        $context = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'file_name' => $fileName,
            'caption' => $caption,
        ];

        if ($route === null || $route->targets() === []) {
            Log::info('RouteTelegramFile: no route', $context + ['route_id' => $route?->id]);

            /* A file nobody is set to forward: it stays where it is until a kind or a group is added. */
            app(DeveloperAlerts::class)->report(
                kind: 'file_unmatched',
                title: $route === null
                    ? 'Файл не подошёл ни к одному виду: ' . ($caption !== '' ? $caption : $fileName)
                    : 'У вида «' . $route->name . '» нет групп - файл не отправлен',
                details: 'Добавьте вид или группы на странице «Fayllar».',
                context: ['Файл' => $fileName !== '' ? $fileName : '-', 'Подпись' => $caption !== '' ? $caption : '-'],
                listenerKey: $listenerKey,
                about: $route?->id !== null ? 'route:' . $route->id : ($caption . '|' . $fileName),
            );

            TelegramFileForward::query()->firstOrCreate(
                [
                    'source_chat_id' => $chatId,
                    'message_id' => $messageId,
                    'target_chat_id' => null,
                ],
                [
                    'file_route_id' => $route?->id,
                    'file_name' => $this->limit($fileName, 255),
                    'caption' => $this->limit($caption, 1000),
                    'status' => TelegramFileForward::STATUS_UNMATCHED,
                ],
            );

            return false;
        }

        foreach ($route->targets() as $target) {
            $forward = TelegramFileForward::query()->firstOrCreate(
                [
                    'source_chat_id' => $chatId,
                    'message_id' => $messageId,
                    'target_chat_id' => $target,
                ],
                [
                    'file_route_id' => $route->id,
                    'file_name' => $this->limit($fileName, 255),
                    'caption' => $this->limit($caption, 1000),
                    'status' => TelegramFileForward::STATUS_FAILED,
                ],
            );

            /* The same update delivered twice. */
            if ($forward->status === TelegramFileForward::STATUS_SENT) {
                continue;
            }

            $this->send($telegram, $forward);
        }

        Log::info('RouteTelegramFile: routed', $context + [
            'route_id' => $route->id,
            'targets' => $route->targets(),
        ]);

        return true;
    }

    /**
     * Another try at the forwards Telegram refused.
     */
    public function retry(SimpleEventHandler $telegram, ?string $listenerKey = null): void
    {
        $owners = app(DutyOwners::class);

        TelegramFileForward::query()
            ->with('route')
            ->where('status', TelegramFileForward::STATUS_FAILED)
            ->whereNotNull('target_chat_id')
            ->where('attempts', '<', TelegramFileForward::MAX_ATTEMPTS)
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('id')
            ->limit(100)
            ->get()
            /* Only the files this account forwards: a route's own account, or the general owner's. */
            ->filter(fn (TelegramFileForward $forward): bool => $owners->fileOwner($forward->route?->listener_key) === $listenerKey)
            ->take(20)
            ->each(fn (TelegramFileForward $forward) => $this->send($telegram, $forward));
    }

    public function isSource(int $chatId): bool
    {
        return in_array(
            $chatId,
            array_map('intval', (array) config('file_routes.source_chat_ids', [])),
            true,
        );
    }

    /**
     * The first active route, top to bottom, whose keyword is in the name.
     */
    public function match(string $text): ?TelegramFileRoute
    {
        return TelegramFileRoute::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->first(fn (TelegramFileRoute $route): bool => $route->matches($text));
    }

    private function send(SimpleEventHandler $telegram, TelegramFileForward $forward): void
    {
        $forward->increment('attempts');

        try {
            $telegram->messages->forwardMessages([
                'from_peer' => $forward->source_chat_id,
                'id' => [$forward->message_id],
                'random_id' => [random_int(PHP_INT_MIN, PHP_INT_MAX)],
                'to_peer' => $forward->target_chat_id,
            ]);

            $forward->update([
                'status' => TelegramFileForward::STATUS_SENT,
                'error' => null,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            $forward->update([
                'status' => TelegramFileForward::STATUS_FAILED,
                'error' => $this->limit($e->getMessage(), 2000),
            ]);

            Log::warning('RouteTelegramFile: forward failed', [
                'forward_id' => $forward->id,
                'target_chat_id' => $forward->target_chat_id,
                'error' => $e->getMessage(),
            ]);

            $this->tellDeveloper($forward, $e->getMessage());
        }
    }

    /**
     * Which file did not reach which group, and from which account.
     */
    private function tellDeveloper(TelegramFileForward $forward, string $error): void
    {
        $route = $forward->route;
        $owner = app(DutyOwners::class)->fileOwner($route?->listener_key);

        app(DeveloperAlerts::class)->report(
            kind: 'file_forward_failed',
            title: 'Файл не отправлен в группу ' . $forward->target_chat_id . ' (' . ($route?->name ?? '?') . ')',
            details: $error . "\n\n" . 'Аккаунт должен состоять в группе и видеть исходное сообщение бота.',
            context: [
                'Аккаунт' => $owner ?? 'Driver Check',
                'Вид' => $route?->name ?? '-',
                'Файл' => $forward->file_name ?? $forward->caption ?? '-',
                'Попыток' => $forward->attempts,
            ],
            listenerKey: $owner,
            about: ($forward->file_route_id ?? 0) . ':' . $forward->target_chat_id,
        );
    }

    private function limit(string $value, int $length): ?string
    {
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
