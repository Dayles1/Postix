<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

use App\Models\Telegram\TelegramDriverCheckChat;
use danog\MadelineProto\SimpleEventHandler;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The watch list of the driver-check listener.
 *
 * The listener used to follow a single group pinned in the environment. It
 * now follows every active row of telegram_driver_check_chats - none, one or
 * many - and re-reads that table while it runs, so the panel can add or
 * remove a group without a restart.
 *
 * Resolution (invite link or @username -> numeric peer) can only happen
 * inside the process that owns the MadelineProto session, which is why it
 * lives here and not in the HTTP layer.
 */
final class TelegramDriverCheckChats
{
    /**
     * How long a chat that failed to resolve is left alone before the next
     * attempt. Without it a revoked invite link would cost an API call on
     * every refresh, forever.
     */
    private const RETRY_AFTER_MINUTES = 5;

    /**
     * Copies TELEGRAM_DRIVER_CHECK_CHAT_LINKS into the table.
     *
     * Idempotent, and it never touches a row that already exists: the
     * environment seeds the list, the panel owns it afterwards.
     */
    public function syncFromConfig(): void
    {
        $links = config('services.telegram.driver_check_chat_links', []);

        if (! is_array($links) || $links === []) {
            return;
        }

        foreach ($links as $raw) {
            $parsed = TelegramDriverCheckChat::parseInput((string) $raw);

            if ($parsed['link'] === null && $parsed['chat_id'] === null) {
                continue;
            }

            $exists = TelegramDriverCheckChat::query()
                ->when(
                    $parsed['link'] !== null,
                    fn (Builder $q) => $q->where('link', $parsed['link']),
                    fn (Builder $q) => $q->where('chat_id', $parsed['chat_id']),
                )
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                TelegramDriverCheckChat::query()->create([
                    'link' => $parsed['link'],
                    'chat_id' => $parsed['chat_id'],
                    'is_active' => true,
                    'source' => TelegramDriverCheckChat::SOURCE_ENV,
                ]);

                Log::info(
                    'Driver check chat imported from configuration',
                    [
                        'link' => $parsed['link'],
                        'chat_id' => $parsed['chat_id'],
                    ],
                );
            } catch (Throwable $e) {
                /*
                 * A race with the panel adding the same chat, or a unique
                 * clash on chat_id. Either way the chat is already watched.
                 */
                Log::warning(
                    'Driver check chat could not be imported',
                    [
                        'link' => $parsed['link'],
                        'chat_id' => $parsed['chat_id'],
                        'error' => $e->getMessage(),
                    ],
                );
            }
        }
    }

    /**
     * Numeric ids of every active chat, resolving the ones that need it.
     *
     * An unresolvable chat is recorded on its own row and left out; it never
     * stops the other chats from being watched.
     *
     * @return list<int>
     */
    public function resolve(SimpleEventHandler $telegram): array
    {
        $chats = TelegramDriverCheckChat::query()
            ->active()
            ->orderBy('id')
            ->get();

        /** @var array<int, true> $ids */
        $ids = [];

        foreach ($chats as $chat) {
            $id = $this->resolveChat($telegram, $chat);

            if ($id === null) {
                continue;
            }

            $ids[$id] = true;
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * Marks the moment a watched chat last produced a message, which is what
     * tells the panel a group is alive rather than merely configured.
     */
    public function touch(int $chatId): void
    {
        try {
            TelegramDriverCheckChat::query()
                ->where('chat_id', $chatId)
                ->update(['last_message_at' => now()]);
        } catch (Throwable $e) {
            Log::warning(
                'Driver check chat activity could not be recorded',
                [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ],
            );
        }
    }

    private function resolveChat(
        SimpleEventHandler $telegram,
        TelegramDriverCheckChat $chat,
    ): ?int {
        if ($chat->chat_id !== null) {
            return (int) $chat->chat_id;
        }

        $link = trim((string) $chat->link);

        if ($link === '') {
            return null;
        }

        if ($this->isCoolingDown($chat)) {
            return null;
        }

        try {
            $chatId = (int) $telegram->getId($link);
        } catch (Throwable $e) {
            $this->fail($chat, $e->getMessage());

            return null;
        }

        if ($chatId === 0) {
            $this->fail($chat, 'Telegram returned no id for this link.');

            return null;
        }

        $taken = TelegramDriverCheckChat::query()
            ->where('chat_id', $chatId)
            ->whereKeyNot($chat->getKey())
            ->exists();

        if ($taken) {
            $this->fail(
                $chat,
                "This link points at chat {$chatId}, which is already on the list.",
            );

            return null;
        }

        $chat->update([
            'chat_id' => $chatId,
            'title' => $chat->title ?? $this->title($telegram, $link),
            'resolved_at' => now(),
            'resolve_error' => null,
        ]);

        Log::info(
            'Driver check chat resolved',
            [
                'chat_row_id' => $chat->id,
                'link' => $link,
                'chat_id' => $chatId,
            ],
        );

        return $chatId;
    }

    /**
     * A failing row is retried on a timer, not on every refresh.
     */
    private function isCoolingDown(TelegramDriverCheckChat $chat): bool
    {
        return $chat->resolve_error !== null
            && $chat->updated_at !== null
            && $chat->updated_at->gt(
                now()->subMinutes(self::RETRY_AFTER_MINUTES),
            );
    }

    private function fail(
        TelegramDriverCheckChat $chat,
        string $message,
    ): void {
        $message = mb_substr(trim($message), 0, 500);

        Log::warning(
            'Driver check chat could not be resolved',
            [
                'chat_row_id' => $chat->id,
                'link' => $chat->link,
                'error' => $message,
            ],
        );

        /*
         * updated_at is the retry clock, so it has to move even when the
         * error text is the same as last time.
         */
        $chat->forceFill([
            'resolve_error' => $message,
            'updated_at' => now(),
        ])->save();
    }

    /**
     * Best-effort chat title: nice to have in the panel, never worth failing
     * a resolution over.
     */
    private function title(
        SimpleEventHandler $telegram,
        string $link,
    ): ?string {
        try {
            $info = $telegram->getInfo($link);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($info)) {
            return null;
        }

        $title = $info['Chat']['title']
            ?? $info['Chat']['first_name']
            ?? $info['User']['first_name']
            ?? null;

        $title = trim((string) $title);

        return $title !== ''
            ? mb_substr($title, 0, 255)
            : null;
    }
}
