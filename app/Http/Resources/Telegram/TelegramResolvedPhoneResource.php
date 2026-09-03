<?php

declare(strict_types=1);

namespace App\Http\Resources\Telegram;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TelegramResolvedPhoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'phone' => $this->phone_normalized,

            'telegram_user_id' => $this->telegram_user_id,

            'telegram_username' => $this->telegram_username,

            'telegram_first_name' => $this->telegram_first_name,

            'telegram_last_name' => $this->telegram_last_name,

            'telegram_account_id' => $this->telegram_account_id,

            'resolved_at' => $this->resolved_at?->toISOString(),
        ];
    }
}