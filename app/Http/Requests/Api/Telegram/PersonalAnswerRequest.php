<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use App\Application\Telegram\Services\AutoReplyRules;
use App\Application\Telegram\Services\PersonalAnswers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One person's own answers, every situation at once, as the page sends
 * them. Slot names and file names are checked again by
 * PersonalAnswers::sanitize().
 */
final class PersonalAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = implode(',', [PersonalAnswers::TYPE_TEXT, AutoReplyRules::MEDIA_GIF, AutoReplyRules::MEDIA_VOICE]);

        return [
            'slots' => ['present', 'array', 'max:' . PersonalAnswers::MAX_SLOTS],
            'slots.*' => ['array'],
            'slots.*.only' => ['boolean'],
            'slots.*.items' => ['present', 'array', 'max:' . PersonalAnswers::MAX_ITEMS],
            'slots.*.items.*.type' => ['required', 'in:' . $types],
            'slots.*.items.*.text' => ['nullable', 'string', 'max:1000'],
            'slots.*.items.*.file' => ['nullable', 'string', 'regex:/^[a-f0-9]{24}\.(gif|mp4|ogg|oga|opus)$/'],
            'slots.*.items.*.name' => ['nullable', 'string', 'max:120'],
            'slots.*.items.*.telegram' => ['nullable', 'array'],
            'slots.*.items.*.telegram.id' => ['required_with:slots.*.items.*.telegram', 'string', 'regex:/^-?\d{1,20}$/'],
            'slots.*.items.*.telegram.access_hash' => ['required_with:slots.*.items.*.telegram', 'string', 'regex:/^-?\d{1,20}$/'],
            'slots.*.items.*.telegram.file_reference' => ['nullable', 'string', 'max:400'],
        ];
    }

    /**
     * An unknown situation is a bug of the page, not something to drop
     * quietly.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_keys((array) $this->input('slots', [])) as $slot) {
                    if (! is_string($slot) || ! PersonalAnswers::validSlot($slot)) {
                        $validator->errors()->add('slots', __('telegram.personal_answers.validation.slot', ['slot' => (string) $slot]));
                    }
                }
            },
        ];
    }
}
