<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use App\Application\Telegram\Services\ClientCheckRules;
use App\Models\Telegram\OperationUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The whole rule set: a ladder per role (operators, sales) and the timings
 * they share.
 */
final class ClientCheckRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'roles' => ['required', 'array'],

            'batch_quiet_seconds' => ['required', 'integer', 'min:5', 'max:600'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'retry_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
        ];

        foreach (OperationUser::ROLES as $role) {
            $rules += [
                "roles.{$role}" => ['required', 'array'],
                "roles.{$role}.levels" => ['required', 'array', 'min:1', 'max:' . ClientCheckRules::MAX_LEVELS],
                "roles.{$role}.levels.*.name" => ['nullable', 'string', 'max:60'],
                "roles.{$role}.levels.*.from" => ['nullable', 'integer', 'min:1', 'max:100'],
                "roles.{$role}.levels.*.mode" => ['required', 'string', Rule::in(ClientCheckRules::MODES)],
                "roles.{$role}.levels.*.phrases" => ['present', 'array'],
                "roles.{$role}.levels.*.phrases.*" => ['array'],
                "roles.{$role}.levels.*.phrases.*.*" => ['array', 'max:30'],
                "roles.{$role}.levels.*.phrases.*.*.*" => ['nullable', 'string', 'max:1000'],
            ];
        }

        return $rules;
    }

    /**
     * What the field rules cannot see, per role: a first level that sends a
     * comment with nothing to say, and repeat numbers that do not climb - a
     * level starting where the one before it already did could never be
     * reached.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (OperationUser::ROLES as $role) {
                    $levels = array_values((array) $this->input("roles.{$role}.levels", []));

                    if (
                        $levels !== []
                        && ($levels[0]['mode'] ?? ClientCheckRules::MODE_ALL) === ClientCheckRules::MODE_ALL
                        && ! $this->hasPhrase($levels[0]['phrases'] ?? [])
                    ) {
                        $validator->errors()->add(
                            "roles.{$role}.levels.0.phrases",
                            __('telegram.penalty_settings.validation.first_level_phrase'),
                        );
                    }

                    $previous = 1;

                    foreach ($levels as $index => $level) {
                        if ($index === 0) {
                            continue;
                        }

                        $from = $level['from'] ?? null;

                        if (! is_numeric($from) || (int) $from <= $previous) {
                            $validator->errors()->add(
                                "roles.{$role}.levels.{$index}.from",
                                __('telegram.penalty_settings.validation.from_order', [
                                    'level' => $index + 1,
                                    'min' => $previous + 1,
                                ]),
                            );

                            continue;
                        }

                        $previous = (int) $from;
                    }
                }
            },
        ];
    }

    private function hasPhrase(mixed $sets): bool
    {
        foreach ((array) $sets as $tones) {
            foreach ((array) $tones as $phrases) {
                foreach ((array) $phrases as $phrase) {
                    if (is_string($phrase) && trim($phrase) !== '') {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
