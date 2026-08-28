<?php

declare(strict_types=1);

namespace App\Http\Resources\Telegram;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ResolvedPhoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone_normalized' => $this->phone_normalized,
            'telegram_user_id' => $this->telegram_user_id,
            'telegram_username' => $this->telegram_username,
            'telegram_first_name' => $this->telegram_first_name,
            'telegram_last_name' => $this->telegram_last_name,
            'telegram_account' => $this->whenLoaded('telegramAccount', function () {
                return [
                    'id' => $this->telegramAccount->id,
                    'phone' => $this->telegramAccount->phone,
                ];
            }),
            'driver' => $this->whenLoaded('driver', function () {
                if (!$this->driver) {
                    return null;
                }
                return [
                    'id' => $this->driver->id,
                    'name' => $this->driver->name,
                    'status' => $this->driver->status,
                    'operation_user' => $this->driver->relationLoaded('operationUser') && $this->driver->operationUser
                        ? [
                            'id' => $this->driver->operationUser->id,
                            'name' => $this->driver->operationUser->name,
                        ]
                        : null,
                ];
            }),
            'resolved_at' => $this->resolved_at,
            'stats' => [
                'checks' => (int) ($this->checks_count ?? 0),
                'confirmed' => (int) ($this->confirmed_count ?? 0),
                'not_confirmed' => (int) ($this->not_confirmed_count ?? 0),
                'pending' => (int) ($this->pending_count ?? 0),
                'processing' => (int) ($this->processing_count ?? 0),
                'last_check_at' => $this->last_check_at,
            ],
        ];
    }
}
