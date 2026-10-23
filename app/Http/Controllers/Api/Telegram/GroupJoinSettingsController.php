<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Telegram;

use App\Application\Telegram\Services\GroupJoinSettings;
use App\Http\Controllers\Controller;
use App\Jobs\Telegram\StartGroupJoinWatchdogJob;
use App\Models\Telegram\TelegramAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The group join's settings page: on or off, which accounts join, what a
 * group has to be, how fast, how long after a flood, how many people are
 * read. Access is the `role:driverCheck,superadmin` middleware of the route
 * group.
 */
final class GroupJoinSettingsController extends Controller
{
    public function __construct(private readonly GroupJoinSettings $settings) {}

    public function show(): JsonResponse
    {
        return response()->json($this->payload($this->settings->current()));
    }

    public function update(Request $request): JsonResponse
    {
        $rules = [
            'enabled' => ['required', 'boolean'],
            'read_members' => ['required', 'boolean'],
            'accounts' => ['nullable', 'array'],
            'accounts.*' => ['integer', 'exists:telegram_accounts,id'],
        ];

        foreach (GroupJoinSettings::LIMITS as $key => [$min, $max]) {
            $rules[$key] = ['required', 'integer', "min:{$min}", "max:{$max}"];
        }

        $rules['join_delay_max'][] = 'gte:join_delay_min';
        $rules['flood_extra_max'][] = 'gte:flood_extra_min';

        $data = $request->validate($rules);

        $data['accounts'] = $data['accounts'] ?? [];

        $values = $this->settings->save($data);

        /* Switched on: the watchdog now, not at the scheduler's next minute. */
        if ($values['enabled']) {
            try {
                StartGroupJoinWatchdogJob::dispatch()->onQueue('telegram');
            } catch (Throwable) {
                // The scheduler starts it within a minute anyway.
            }
        }

        return response()->json($this->payload($values) + [
            'message' => __('driver_check.join.messages.settings_saved'),
        ]);
    }

    public function reset(): JsonResponse
    {
        return response()->json($this->payload($this->settings->reset()) + [
            'message' => __('driver_check.join.messages.settings_reset'),
        ]);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function payload(array $values): array
    {
        return [
            'data' => $values,
            'defaults' => $this->settings->defaults(),
            'saved' => $this->settings->isSaved(),
            'limits' => GroupJoinSettings::LIMITS,
            'accounts' => TelegramAccount::query()
                ->where('is_authorized', true)
                ->orderBy('id')
                ->get(['id', 'phone', 'username'])
                ->map(fn (TelegramAccount $account) => [
                    'id' => $account->id,
                    'phone' => $account->phone,
                    'username' => $account->username,
                    /* A listener's account: its session is the listener's, it cannot be marked. */
                    'is_primary' => $account->isPrimary(),
                ])
                ->values()
                ->all(),
        ];
    }
}
