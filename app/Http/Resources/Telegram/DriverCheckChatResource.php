<?php

declare(strict_types=1);

namespace App\Http\Resources\Telegram;

use App\Models\Telegram\TelegramDriverCheckChat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TelegramDriverCheckChat
 */
final class DriverCheckChatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'link' => $this->link,

            'chat_id' => $this->chat_id,

            'title' => $this->title,

            'label' => $this->label(),

            'is_active' => (bool) $this->is_active,

            /*
             * Where the row came from. An 'env' row is still fully editable -
             * the import only happens once - but the panel says so, because
             * deleting one and leaving the variable in place brings it back
             * on the next listener start.
             */
            'source' => $this->source,

            /*
             * Both switches at once: the listener compares incoming messages
             * against this chat only when it is active AND resolved.
             */
            'is_watching' => (bool) $this->is_active
                && $this->chat_id !== null,

            'is_resolved' => $this->chat_id !== null,

            'resolved_at' => $this->resolved_at,

            'resolve_error' => $this->resolve_error,

            'last_message_at' => $this->last_message_at,

            'checks_count' => (int) ($this->checks_count ?? 0),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
