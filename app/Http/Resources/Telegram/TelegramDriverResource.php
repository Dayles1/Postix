<?php

declare(strict_types=1);

namespace App\Http\Resources\Telegram;

use App\Http\Resources\Telegram\TelegramResolvedPhoneResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TelegramDriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_normalized' => $this->name_normalized,
            'status' => $this->status,
            'operation_user' => $this->whenLoaded('operationUser', function () {
                return [
                    'id' => $this->operationUser->id,
                    'name' => $this->operationUser->name,
                    'telegram_username' => $this->operationUser->telegram_username,
                    'telegram_id' => $this->operationUser->telegram_id,
                ];
            }),
            'resolved_phones' => TelegramResolvedPhoneResource::collection(
                $this->whenLoaded('resolvedPhones'),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'stats' => [
                'checks' => (int) ($this->checks_count ?? 0),
                'confirmed' => (int) ($this->confirmed_count ?? 0),
                'not_confirmed' => (int) ($this->not_confirmed_count ?? 0),
                'pending' => (int) ($this->pending_count ?? 0),
                'processing' => (int) ($this->processing_count ?? 0),
                'avg_match_score' => $this->nullableNumber($this->avg_match_score),
                'best_match_score' => $this->nullableNumber($this->best_match_score),
                'last_check_at' => $this->last_check_at,
            ],
        ];
    }

    private function nullableNumber(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }
}
