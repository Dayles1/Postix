<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

class TelegramDriverIndexRequest extends TelegramListRequest
{
    public function rules(): array
    {
        return $this->commonRules();
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'updated_at', 'name', 'status', 'checks', 'confirmed', 'not_confirmed', 'pending', 'avg_match_score', 'best_match_score', 'last_check_at'];
    }
}
