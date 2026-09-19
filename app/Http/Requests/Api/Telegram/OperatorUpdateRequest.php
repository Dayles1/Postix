<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use Illuminate\Validation\Rule;

class OperatorUpdateRequest extends OperatorStoreRequest
{
    public function rules(): array
    {
        $operator = $this->route('operationUser');

        $ignore = $operator?->getKey();

        return [
            ...parent::rules(),

            'name_normalized' => [
                'required',
                'string',
                'max:255',
                Rule::unique('operation_users', 'name_normalized')
                    ->ignore($ignore),
            ],

            'telegram_username' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^[A-Za-z][A-Za-z0-9_]{3,31}$/',
                Rule::unique('operation_users', 'telegram_username')
                    ->ignore($ignore),
            ],

            'telegram_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('operation_users', 'telegram_id')
                    ->ignore($ignore),
            ],
        ];
    }
}
