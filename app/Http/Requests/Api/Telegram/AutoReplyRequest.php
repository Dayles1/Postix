<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use App\Application\Telegram\Services\AutoReplyRules;
use App\Application\Telegram\Services\ClientCheckRules;
use App\Models\Telegram\OperationUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The whole auto replies file, as the panel sends it.
 */
final class AutoReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'enabled' => ['required', 'boolean'],
            'only_after_penalty' => ['required', 'boolean'],
            'penalty_window_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'cooldown_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'max_words' => ['required', 'integer', 'min:1', 'max:50'],

            'replies' => ['present', 'array', 'max:' . AutoReplyRules::MAX_REPLIES],
            'replies.*.name' => ['nullable', 'string', 'max:60'],
            'replies.*.keywords' => ['present', 'array', 'max:' . AutoReplyRules::MAX_KEYWORDS],
            'replies.*.keywords.*' => ['nullable', 'string', 'max:60'],
            /*
             * Empty: the file's own max_words.
             */
            'replies.*.max_words' => ['nullable', 'integer', 'min:1', 'max:50'],
            'replies.*.answers' => ['present', 'array'],

            'silence' => ['required', 'array'],
            'silence.enabled' => ['required', 'boolean'],
            'silence.after_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'silence.answers' => ['present', 'array'],
        ];

        foreach (OperationUser::LANGUAGES as $language) {
            foreach (ClientCheckRules::TONES as $tone) {
                foreach (['replies.*.answers', 'silence.answers'] as $answers) {
                    $rules["{$answers}.{$language}.{$tone}"] = ['nullable', 'array', 'max:' . AutoReplyRules::MAX_ANSWERS];
                    $rules["{$answers}.{$language}.{$tone}.*"] = ['nullable', 'string', 'max:1000'];
                }
            }
        }

        return $rules;
    }

    /**
     * A kind without a keyword never matches; without an answer it matches
     * and says nothing - both are half made.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_values((array) $this->input('replies', [])) as $index => $reply) {
                    $reply = (array) $reply;

                    if (! $this->hasText($reply['keywords'] ?? [])) {
                        $validator->errors()->add(
                            "replies.{$index}.keywords",
                            __('telegram.auto_replies.validation.keywords'),
                        );
                    }

                    if (! $this->hasAnswer($reply['answers'] ?? [])) {
                        $validator->errors()->add(
                            "replies.{$index}.answers",
                            __('telegram.auto_replies.validation.answers'),
                        );
                    }
                }

                /*
                 * A nudge switched on with nothing to say.
                 */
                if ($this->boolean('silence.enabled') && ! $this->hasAnswer($this->input('silence.answers', []))) {
                    $validator->errors()->add(
                        'silence.answers',
                        __('telegram.auto_replies.validation.silence'),
                    );
                }
            },
        ];
    }

    private function hasAnswer(mixed $sets): bool
    {
        $answers = [];

        foreach ((array) $sets as $tones) {
            foreach ((array) $tones as $list) {
                $answers = [...$answers, ...array_values((array) $list)];
            }
        }

        return $this->hasText($answers);
    }

    private function hasText(mixed $values): bool
    {
        foreach ((array) $values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
