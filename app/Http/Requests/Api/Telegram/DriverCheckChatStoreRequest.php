<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use App\Models\Telegram\TelegramDriverCheckChat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DriverCheckChatStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
             * One field for the whole peer, because that is how a chat is
             * shared: you paste what Telegram gave you. Which of the two
             * columns it lands in is the server's problem, not the user's.
             */
            'chat' => [
                'required',
                'string',
                'max:255',
                'regex:' . TelegramDriverCheckChat::INPUT_PATTERN,
            ],

            'link' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('telegram_driver_check_chats', 'link'),
            ],

            'chat_id' => [
                'nullable',
                'integer',
                Rule::unique('telegram_driver_check_chats', 'chat_id'),
            ],

            'title' => ['nullable', 'string', 'max:255'],

            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The two stored columns are derived, never submitted.
     */
    protected function prepareForValidation(): void
    {
        $parsed = TelegramDriverCheckChat::parseInput(
            (string) $this->input('chat'),
        );

        $title = trim((string) $this->input('title'));

        $this->merge([
            'chat' => trim((string) $this->input('chat')),
            'link' => $parsed['link'],
            'chat_id' => $parsed['chat_id'],
            'title' => $title !== '' ? $title : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'chat.regex' => __('telegram.chats.validation.format'),
            'link.unique' => __('telegram.chats.validation.duplicate'),
            'chat_id.unique' => __('telegram.chats.validation.duplicate'),
        ];
    }
}
