<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use Illuminate\Validation\Rule;

class DriverCheckChatUpdateRequest extends DriverCheckChatStoreRequest
{
    public function rules(): array
    {
        $ignore = $this->route('chat')?->getKey();

        return [
            ...parent::rules(),

            'link' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('telegram_driver_check_chats', 'link')
                    ->ignore($ignore),
            ],

            'chat_id' => [
                'nullable',
                'integer',
                Rule::unique('telegram_driver_check_chats', 'chat_id')
                    ->ignore($ignore),
            ],
        ];
    }
}
