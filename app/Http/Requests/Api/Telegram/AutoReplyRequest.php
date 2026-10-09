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
            /*
             * What personal answers point at; a new kind gets one in the
             * panel, a missing one is made up by AutoReplyRules.
             */
            'replies.*.id' => ['nullable', 'string', 'regex:/^[a-z0-9]{1,24}$/'],
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
            'silence.after_penalties' => ['required', 'integer', 'min:1', 'max:50'],
            'silence.answers' => ['present', 'array'],

            /*
             * Left out: the file keeps the config's greetings.
             */
            'greetings' => ['sometimes', 'array'],
            'greetings.enabled' => ['required_with:greetings', 'boolean'],
            'greetings.fillers' => ['present_with:greetings', 'array', 'max:' . AutoReplyRules::MAX_FILLERS],
            'greetings.fillers.*' => ['nullable', 'string', 'max:60'],
            'greetings.list' => ['present_with:greetings', 'array', 'max:' . AutoReplyRules::MAX_GREETINGS],
            'greetings.list.*.id' => ['nullable', 'string', 'regex:/^[a-z0-9]{1,24}$/'],
            'greetings.list.*.name' => ['nullable', 'string', 'max:60'],
            'greetings.list.*.keywords' => ['present', 'array', 'max:' . AutoReplyRules::MAX_KEYWORDS],
            'greetings.list.*.keywords.*' => ['nullable', 'string', 'max:60'],
            'greetings.list.*.answers' => ['present', 'array'],
        ];

        /*
         * GIFs for every language, voices per language: names
         * AutoReplyMedia::store() gave, checked again by AutoReplyRules.
         */
        foreach (['replies.*.media', 'silence.media'] as $media) {
            $rules["{$media}"] = ['nullable', 'array'];
            $rules["{$media}.gifs"] = ['nullable', 'array', 'max:' . AutoReplyRules::MAX_MEDIA];
            $rules["{$media}.gifs.*.file"] = ['required', 'string', 'regex:/^[a-f0-9]{24}\.(gif|mp4)$/'];
            $rules["{$media}.gifs.*.name"] = ['nullable', 'string', 'max:120'];
            /*
             * Found in Telegram: the document it goes as (AutoReplyTelegramGifs).
             */
            $rules["{$media}.gifs.*.telegram"] = ['nullable', 'array'];
            $rules["{$media}.gifs.*.telegram.id"] = ['required_with:' . "{$media}.gifs.*.telegram", 'string', 'regex:/^-?\d{1,20}$/'];
            $rules["{$media}.gifs.*.telegram.access_hash"] = ['required_with:' . "{$media}.gifs.*.telegram", 'string', 'regex:/^-?\d{1,20}$/'];
            $rules["{$media}.gifs.*.telegram.file_reference"] = ['present_with:' . "{$media}.gifs.*.telegram", 'nullable', 'string', 'max:400'];

            foreach (OperationUser::LANGUAGES as $language) {
                $rules["{$media}.voices.{$language}"] = ['nullable', 'array', 'max:' . AutoReplyRules::MAX_MEDIA];
                $rules["{$media}.voices.{$language}.*.file"] = ['required', 'string', 'regex:/^[a-f0-9]{24}\.(ogg|oga|opus)$/'];
                $rules["{$media}.voices.{$language}.*.name"] = ['nullable', 'string', 'max:120'];
            }
        }

        foreach (OperationUser::LANGUAGES as $language) {
            foreach (ClientCheckRules::TONES as $tone) {
                foreach (['replies.*.answers', 'silence.answers', 'greetings.list.*.answers'] as $answers) {
                    $rules["{$answers}.{$language}.{$tone}"] = ['nullable', 'array', 'max:' . AutoReplyRules::MAX_ANSWERS];
                    $rules["{$answers}.{$language}.{$tone}.*"] = ['nullable', 'string', 'max:1000'];
                }
            }
        }

        return $rules;
    }

    /**
     * A kind without a keyword never matches; without an answer (a text, a
     * GIF or a voice) it matches and says nothing - both are half made.
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

                    if (! $this->hasAnswer($reply['answers'] ?? []) && ! $this->hasMedia($reply['media'] ?? [])) {
                        $validator->errors()->add(
                            "replies.{$index}.answers",
                            __('telegram.auto_replies.validation.answers'),
                        );
                    }
                }

                /*
                 * A greeting answers with a text only: no GIF, no voice.
                 */
                foreach (array_values((array) $this->input('greetings.list', [])) as $index => $greeting) {
                    $greeting = (array) $greeting;

                    if (! $this->hasText($greeting['keywords'] ?? [])) {
                        $validator->errors()->add(
                            "greetings.list.{$index}.keywords",
                            __('telegram.auto_replies.validation.greeting_keywords'),
                        );
                    }

                    if (! $this->hasAnswer($greeting['answers'] ?? [])) {
                        $validator->errors()->add(
                            "greetings.list.{$index}.answers",
                            __('telegram.auto_replies.validation.greeting_answers'),
                        );
                    }
                }

                /*
                 * A nudge switched on with nothing to say - no text, no GIF, no voice.
                 */
                if (
                    $this->boolean('silence.enabled')
                    && ! $this->hasAnswer($this->input('silence.answers', []))
                    && ! $this->hasMedia($this->input('silence.media', []))
                ) {
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

    /**
     * A GIF or a voice message is an answer too.
     */
    private function hasMedia(mixed $media): bool
    {
        $media = (array) $media;

        if ((array) ($media['gifs'] ?? []) !== []) {
            return true;
        }

        foreach ((array) ($media['voices'] ?? []) as $voices) {
            if ((array) $voices !== []) {
                return true;
            }
        }

        return false;
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
