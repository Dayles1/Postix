<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperatorIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'dm_enabled' => ['nullable', 'boolean'],
            'linked' => ['nullable', 'boolean'],
            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'name',
                    'created_at',
                    'updated_at',
                    'dm_last_sent_at',
                    'drivers',
                    'checks',
                ]),
            ],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
