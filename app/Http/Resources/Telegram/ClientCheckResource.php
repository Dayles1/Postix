<?php

declare(strict_types=1);

namespace App\Http\Resources\Telegram;

use App\Models\Telegram\OperationUser;
use App\Models\Telegram\TelegramClientCheck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TelegramClientCheck
 */
final class ClientCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $person = $this->operationUser;

        $parsed = is_array($this->parsed) ? $this->parsed : [];

        return [
            'id' => $this->id,

            'request_number' => $this->request_number,

            'repeat_number' => (int) $this->repeat_number,

            'crm_status' => $this->crm_status,

            'status_since' => $this->status_since,

            'time_in_status' => $parsed['time_in_status'] ?? null,

            'crm_url' => $parsed['crm_url'] ?? null,

            'responsible_role' => $this->responsible_role,

            'responsible_name' => $this->responsible_name,

            'person' => $person instanceof OperationUser
                ? [
                    'id' => $person->id,
                    'name' => $person->name,
                    'role' => $person->roleOrDefault(),
                    'telegram_username' => $person->telegramUsername(),
                    'has_telegram_peer' => $person->hasTelegramPeer(),
                    'dm_enabled' => (bool) $person->dm_enabled,
                ]
                : null,

            'level' => (int) $this->level,

            'metrics' => $this->metrics,

            /*
             * Only the batch's last penalty carries the comment.
             */
            'comment' => $this->comment,

            'comment_level' => $this->comment_level,

            'batch_count' => $this->batch_count,

            'status' => $this->status?->value,

            'reason' => $this->reason,

            'attempts' => (int) $this->attempts,

            'error' => $this->error,

            'peer' => $this->peer,

            'message_text' => $this->message_text,

            'forwarded_at' => $this->forwarded_at,

            'sent_at' => $this->sent_at,

            'created_at' => $this->created_at,
        ];
    }
}
