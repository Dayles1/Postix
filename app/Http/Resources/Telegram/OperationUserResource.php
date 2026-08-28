<?php

declare(strict_types=1);

namespace App\Http\Resources\Telegram;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class OperationUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $checks = (int) ($this->checks_count ?? 0);
        $confirmed = (int) ($this->confirmed_count ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_normalized' => $this->name_normalized,
            'telegram_username' => $this->telegram_username,
            'telegram_id' => $this->telegram_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'stats' => [
                'drivers' => (int) ($this->drivers_count ?? 0),
                'checks' => $checks,
                'confirmed' => $confirmed,
                'not_confirmed' => (int) ($this->not_confirmed_count ?? 0),
                'pending' => (int) ($this->pending_count ?? 0),
                'processing' => (int) ($this->processing_count ?? 0),
                'match_rate' => $checks > 0
                    ? round(($confirmed / $checks) * 100, 2)
                    : 0.0,
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
