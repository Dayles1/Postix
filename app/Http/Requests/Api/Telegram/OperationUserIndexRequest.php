<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

class OperationUserIndexRequest extends TelegramListRequest
{
    public function rules(): array
    {
        return $this->commonRules([
            'telegram_id' => ['nullable', 'integer'],
            'telegram_username' => ['nullable', 'string', 'max:255'],
        ]);
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'updated_at', 'name', 'drivers', 'checks', 'confirmed', 'not_confirmed', 'pending', 'match_rate', 'avg_match_score', 'last_check_at'];
    }
}
