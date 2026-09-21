<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use App\Models\Telegram\OperationUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperatorStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            /*
             * Two operators with the same normalised name would both match the
             * "Пользователь:" line and the report would go to whichever row
             * the parser happened to find first, so the key is unique.
             */
            'name_normalized' => [
                'required',
                'string',
                'max:255',
                Rule::unique('operation_users', 'name_normalized'),
            ],

            'telegram_username' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^[A-Za-z][A-Za-z0-9_]{3,31}$/',
                Rule::unique('operation_users', 'telegram_username'),
            ],

            'telegram_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('operation_users', 'telegram_id'),
            ],

            'dm_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The normalised name is derived, never submitted: it must stay byte for
     * byte identical to what the message parser produces.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(
                (string) $this->input('name'),
            ),

            'name_normalized' => OperationUser::normalizeName(
                (string) $this->input('name'),
            ),

            'telegram_username' => $this->normalizedUsername(),
        ]);
    }

    protected function normalizedUsername(): ?string
    {
        $username = ltrim(
            trim(
                (string) $this->input('telegram_username'),
            ),
            '@',
        );

        return $username !== ''
            ? $username
            : null;
    }

    public function messages(): array
    {
        return [
            'name_normalized.unique' => __('telegram.operators.validation.duplicate_name'),
            'telegram_username.regex' => __('telegram.operators.validation.username_format'),
        ];
    }
}
