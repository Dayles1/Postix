<?php

declare(strict_types=1);

namespace App\Http\Resources\Telegram;

use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramAccountProcess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TelegramAccount
 */
final class TelegramSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $name = trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
        ])));

        return [
            'id' => $this->id,

            'phone' => $this->phone,

            'telegram_user_id' => $this->telegram_user_id,

            'first_name' => $this->first_name,

            'last_name' => $this->last_name,

            'name' => $name !== '' ? $name : null,

            'username' => $this->username,

            'status' => $this->status,

            'is_authorized' => (bool) $this->is_authorized,

            'authorized_at' => $this->authorized_at,

            'last_checked_at' => $this->last_checked_at,

            'last_error' => $this->last_error,

            'password_hint' => $this->isAwaitingPassword()
                ? $this->password_hint
                : null,

            /*
             * Authorized in the database but with nothing on disk: every
             * process picking this account will fail on it.
             */
            'session_exists' => $this->hasSessionFile(),

            'is_primary' => $this->isPrimary(),

            'is_listening' => $this->isListening(),

            'is_in_flight' => $this->isInFlight(),

            'is_stale' => $this->isStale(),

            'awaiting_code' => $this->isAwaitingCode(),

            'awaiting_password' => $this->isAwaitingPassword(),

            'processes' => $this->whenLoaded(
                'processes',
                fn () => $this->processes
                    ->map(fn (TelegramAccountProcess $process): array => [
                        'id' => $process->id,
                        'process' => $process->process?->value,
                        'successes' => (int) $process->successes,
                        'failures' => (int) $process->failures,
                        'consecutive_failures' => (int) $process->consecutive_failures,
                        'is_available' => (bool) $process->is_available,
                        'is_busy' => (bool) $process->is_busy,
                        'busy_at' => $process->busy_at,
                        'is_stale_busy' => $process->is_busy
                            && $process->busy_at !== null
                            && $process->busy_at->lt(now()->subMinutes(TelegramAccount::STALE_BUSY_MINUTES)),
                        'disabled_at' => $process->disabled_at,
                        'disabled_reason' => $process->disabled_reason,
                    ])
                    ->values()
                    ->all(),
            ),

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
