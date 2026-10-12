<?php

declare(strict_types=1);

namespace App\Application\Telegram\Actions;

use App\Models\Telegram\TelegramFileForward;
use App\Models\Telegram\TelegramFileRoute;
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
    ): bool {
        if (! $this->isSource($chatId) || $messageId <= 0) {
            return false;
        }

        $route = $this->match($caption . ' ' . $fileName);

        $context = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'file_name' => $fileName,
            'caption' => $caption,
        ];

        if ($route === null || $route->targets() === []) {
            Log::info('RouteTelegramFile: no route', $context + ['route_id' => $route?->id]);

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
    public function retry(SimpleEventHandler $telegram): void
    {
        TelegramFileForward::query()
            ->where('status', TelegramFileForward::STATUS_FAILED)
            ->whereNotNull('target_chat_id')
            ->where('attempts', '<', TelegramFileForward::MAX_ATTEMPTS)
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('id')
            ->limit(20)
            ->get()
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
        }
    }

    private function limit(string $value, int $length): ?string
    {
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
