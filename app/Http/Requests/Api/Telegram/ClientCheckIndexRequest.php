<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use App\Application\Telegram\Queries\ListClientChecks;
use App\Application\Telegram\Services\ClientCheckRules;
use App\Enums\Telegram\TelegramClientCheckStatus;
use App\Models\Telegram\OperationUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ClientCheckIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', Rule::in(OperationUser::ROLES)],
            'status' => ['nullable', 'string', Rule::enum(TelegramClientCheckStatus::class)],
            'level' => ['nullable', 'integer', 'min:0', 'max:' . (ClientCheckRules::MAX_LEVELS - 1)],
            'operation_user_id' => ['nullable', 'integer', 'min:1'],
            'period_from' => ['nullable', 'date_format:Y-m-d'],
            'period_to' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', 'string', Rule::in(ListClientChecks::SORTS)],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
