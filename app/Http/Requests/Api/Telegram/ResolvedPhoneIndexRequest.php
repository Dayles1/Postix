<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

class ResolvedPhoneIndexRequest extends TelegramListRequest
{
    public function rules(): array
    {
        return $this->commonRules();
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'updated_at', 'resolved_at', 'phone_normalized', 'telegram_user_id', 'checks', 'confirmed', 'not_confirmed'];
    }
}
