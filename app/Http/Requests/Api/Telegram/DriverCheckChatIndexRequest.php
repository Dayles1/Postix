<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DriverCheckChatIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'resolved' => ['nullable', 'boolean'],
            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'created_at',
                    'updated_at',
                    'title',
                    'last_message_at',
                    'checks',
                ]),
            ],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
