<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Queries\ListDriverCheckChats;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Telegram\DriverCheckChatIndexRequest;
use App\Http\Requests\Api\Telegram\DriverCheckChatStoreRequest;
use App\Http\Requests\Api\Telegram\DriverCheckChatUpdateRequest;
use App\Http\Resources\Telegram\DriverCheckChatResource;
use App\Models\Telegram\TelegramDriverCheckChat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CRUD behind the watched-chats page.
 *
 * Nothing here talks to Telegram: an invite link can only be resolved by the
 * process that owns the MadelineProto session. A row saved here is picked up
 * by the running listener on its next refresh, which is also when a bad link
 * comes back with an error on the row.
 *
 * Access is enforced by the `role:driverCheck,superadmin` middleware on the
 * route group (see routes/web.php), matching the rest of the driver-check
 * panel.
 */
final class DriverCheckChatController extends Controller
{
    public function index(
        DriverCheckChatIndexRequest $request,
        ListDriverCheckChats $query,
    ): AnonymousResourceCollection {
        $filters = $request->validated();

        return DriverCheckChatResource::collection(
            $query->execute($filters),
        )->additional([
            'stats' => $query->stats($filters),
        ]);
    }

    public function store(
        DriverCheckChatStoreRequest $request,
    ): JsonResponse {
        $validated = $request->validated();

        $chat = TelegramDriverCheckChat::query()->create([
            'link' => $validated['link'] ?? null,

            'chat_id' => $validated['chat_id'] ?? null,

            'title' => $validated['title'] ?? null,

            'is_active' => (bool) ($validated['is_active'] ?? true),

            'source' => TelegramDriverCheckChat::SOURCE_MANUAL,

            /*
             * A chat entered as a plain id needs no resolution; a link is
             * left unresolved on purpose, for the listener to pick up.
             */
            'resolved_at' => isset($validated['chat_id'])
                ? now()
                : null,
        ]);

        return (new DriverCheckChatResource(
            $chat->loadCount('checks'),
        ))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        DriverCheckChatUpdateRequest $request,
        TelegramDriverCheckChat $chat,
    ): DriverCheckChatResource {
        $validated = $request->validated();

        $link = $validated['link'] ?? null;
        $chatId = $validated['chat_id'] ?? null;

        $attributes = [
            'title' => $validated['title'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];

        /*
         * A link the listener has already resolved keeps its id, so editing
         * the title or flipping the switch must not send it back through
         * resolution. Only actually pointing the row at a different chat
         * does, and then everything learnt about the old one has to go -
         * otherwise the listener keeps watching the group that was replaced.
         */
        $sameChat = $chatId !== null
            ? $chatId === $chat->chat_id
            : $link !== null && $link === $chat->link;

        if (! $sameChat) {
            $attributes['link'] = $link;
            $attributes['chat_id'] = $chatId;
            $attributes['resolve_error'] = null;
            $attributes['resolved_at'] = $chatId !== null ? now() : null;
            $attributes['last_message_at'] = null;
        }

        $chat->update($attributes);

        return new DriverCheckChatResource(
            $chat->loadCount('checks'),
        );
    }

    /**
     * Removing a chat only stops it from being watched. The checks it
     * produced are keyed by the Telegram id, not by this row, so the
     * history on every other page stays intact.
     */
    public function destroy(
        TelegramDriverCheckChat $chat,
    ): JsonResponse {
        $chat->delete();

        return response()->json(
            [
                'message' => __('telegram.chats.deleted'),
            ],
        );
    }
}
