<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use App\Application\Telegram\Queries\ListTelegramSessions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TelegramSessionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', Rule::in(ListTelegramSessions::STATES)],
            'sort' => ['nullable', 'string', Rule::in(ListTelegramSessions::SORTS)],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
