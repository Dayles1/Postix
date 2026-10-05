<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use App\Application\Telegram\Services\ClientCheckRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ClientCheckRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $count = ['nullable', 'integer', 'min:1', 'max:1000'];

        return [
            'levels' => ['required', 'array', 'min:1', 'max:' . ClientCheckRules::MAX_LEVELS],
            'levels.*.name' => ['nullable', 'string', 'max:60'],
            'levels.*.repeat_from' => $count,
            'levels.*.hour' => $count,
            'levels.*.today' => $count,
            'levels.*.week' => $count,
            'levels.*.repeat_within' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'levels.*.phrases' => ['present', 'array', 'max:30'],
            'levels.*.phrases.*' => ['nullable', 'string', 'max:1000'],

            'batch_quiet_seconds' => ['required', 'integer', 'min:5', 'max:600'],
            'history_days' => ['required', 'integer', 'min:1', 'max:90'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'retry_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'batch_line' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Two mistakes the field rules cannot see: a first level with nothing
     * to say, and a higher one nobody can ever reach.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $levels = (array) $this->input('levels', []);

                $first = array_filter(
                    (array) ($levels[0]['phrases'] ?? []),
                    static fn ($p) => is_string($p) && trim($p) !== '',
                );

                if ($first === []) {
                    $validator->errors()->add(
                        'levels.0.phrases',
                        __('telegram.penalty_settings.validation.first_level_phrase'),
                    );
                }

                foreach (array_values($levels) as $index => $level) {
                    if ($index === 0) {
                        continue;
                    }

                    $reachable = false;

                    foreach (ClientCheckRules::CONDITIONS as $condition) {
                        $value = $level[$condition] ?? null;

                        if ($value !== null && $value !== '') {
                            $reachable = true;
                        }
                    }

                    if (! $reachable) {
                        $validator->errors()->add(
                            "levels.{$index}.conditions",
                            __('telegram.penalty_settings.validation.unreachable', ['level' => $index]),
                        );
                    }
                }
            },
        ];
    }
}
