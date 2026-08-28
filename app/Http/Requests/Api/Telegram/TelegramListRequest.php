<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class TelegramListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function commonRules(array $extra = []): array
    {
        return array_merge([
            'search' => ['nullable', 'string', 'max:255'],
            'operation_user_id' => ['nullable', 'integer', 'min:1'],
            'driver_id' => ['nullable', 'integer', 'min:1'],
            'phone' => ['nullable', 'string', 'max:50'],
            'telegram_user_id' => ['nullable', 'integer'],
            'telegram_username' => ['nullable', 'string', 'max:255'],
            'telegram_first_name' => ['nullable', 'string', 'max:255'],
            'telegram_last_name' => ['nullable', 'string', 'max:255'],
            'telegram_account_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:50'],
            'check_status' => ['nullable', 'string', 'max:50'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
            'min_match_score' => ['nullable', 'numeric', 'between:0,100'],
            'max_match_score' => ['nullable', 'numeric', 'between:0,100', 'gte:min_match_score'],
            'checks_from' => ['nullable', 'integer', 'min:0'],
            'checks_to' => ['nullable', 'integer', 'min:0', 'gte:checks_from'],
            'has_telegram' => ['nullable', 'boolean'],
            'has_username' => ['nullable', 'boolean'],
            'has_driver' => ['nullable', 'boolean'],
            'stale' => ['nullable', 'boolean'],
            'stale_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'sort' => ['nullable', 'string', Rule::in($this->allowedSorts())],
            'direction' => ['nullable', 'in:asc,desc,ASC,DESC'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], $extra);
    }

    protected function allowedSorts(): array
    {
        return ['created_at', 'updated_at', 'name', 'checks', 'confirmed', 'not_confirmed', 'pending', 'match_score', 'last_check_at', 'resolved_at'];
    }
}
