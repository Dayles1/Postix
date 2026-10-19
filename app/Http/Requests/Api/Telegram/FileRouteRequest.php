<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Telegram;

use App\Application\Telegram\Services\DutyOwners;
use App\Models\Telegram\TelegramListener;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FileRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The groups come as one text field: "-5524265426, -1004431008937",
     * one per line, or already as a list.
     */
    protected function prepareForValidation(): void
    {
        $targets = $this->input('target_chat_ids', []);

        if (is_string($targets)) {
            $targets = preg_split('/[\s,;]+/', $targets) ?: [];
        }

        $this->merge([
            'target_chat_ids' => array_values(array_filter(
                array_map(
                    static fn (mixed $id): string => trim((string) $id),
                    (array) $targets,
                ),
                static fn (string $id): bool => $id !== '',
            )),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'keywords' => ['required', 'string', 'max:500'],
            'target_chat_ids' => ['present', 'array', 'max:20'],
            'target_chat_ids.*' => ['regex:/^-?\d{5,20}$/'],
            /* The account the file goes out from: a listener, Driver Check, or empty to follow "Files from bots". */
            'listener_key' => [
                'nullable',
                'string',
                Rule::in([DutyOwners::DRIVER_CHECK, ...$this->listenerKeys()]),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ];
    }

    /**
     * The listeners a kind may be given to; none while the table is not there yet.
     *
     * @return list<string>
     */
    private function listenerKeys(): array
    {
        try {
            return TelegramListener::query()
                ->where('is_enabled', true)
                ->whereNotNull('telegram_account_id')
                ->pluck('key')
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function messages(): array
    {
        return [
            'target_chat_ids.*.regex' => __('telegram.file_routes.validation.chat_id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesToSave(): array
    {
        $validated = $this->validated();

        return [
            'name' => trim($validated['name']),
            'keywords' => trim($validated['keywords']),
            'target_chat_ids' => array_values(array_unique(array_map(
                'intval',
                $validated['target_chat_ids'],
            ))),
            'listener_key' => ($validated['listener_key'] ?? '') === '' ? null : $validated['listener_key'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ] + (isset($validated['sort']) ? ['sort' => (int) $validated['sort']] : []);
    }
}
