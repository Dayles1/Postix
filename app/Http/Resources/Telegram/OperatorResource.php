<?php

declare(strict_types=1);

namespace App\Http\Resources\Telegram;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OperatorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'name' => $this->name,

            'name_normalized' => $this->name_normalized,

            'telegram_username' => $this->telegramUsername(),

            'telegram_id' => $this->telegram_id,

            'dm_enabled' => (bool) $this->dm_enabled,

            /*
             * What the page actually needs to answer "will this operator get
             * the next report?" - both switches plus a reachable peer.
             */
            'can_receive_dm' => $this->canReceiveDirectMessages(),

            'has_telegram_peer' => $this->hasTelegramPeer(),

            'dm_last_sent_at' => $this->dm_last_sent_at,

            'dm_last_error' => $this->dm_last_error,

            'drivers_count' => (int) ($this->drivers_count ?? 0),

            'checks_count' => (int) ($this->checks_count ?? 0),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
