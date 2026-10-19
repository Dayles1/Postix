<?php

namespace Database\Seeders;

use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramListener;
use Illuminate\Database\Seeder;

/**
 * The accounts that run next to Driver Check, one row per head: switched
 * off and without an account, so nothing changes until each is given its
 * account on the Listeners page and switched on.
 *
 * php artisan db:seed --class=TelegramListenerSeeder
 *
 * Locally it also makes a dummy account (random +99890 number, no real
 * session) for each head, so the pages can be tried; they are authorized on
 * paper only and the listeners stay off. Never on production.
 *
 * Safe to run again: a row that exists is left as it is.
 */
class TelegramListenerSeeder extends Seeder
{
    public function run(): void
    {
        $listeners = [
            'ceo' => 'CEO',
            'head_sales' => 'Head Sales',
            'head_operation' => 'Head Operation',
            'head_china' => 'Head China',
            'head_europe' => 'Head Europe',
        ];

        foreach ($listeners as $key => $name) {
            $account = app()->environment('local') ? $this->dummyAccount($name) : null;

            TelegramListener::query()->firstOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'telegram_account_id' => $account?->id,
                    'is_enabled' => false,
                    'duties' => [],
                ],
            );
        }
    }

    private function dummyAccount(string $name): TelegramAccount
    {
        do {
            $phone = '+99890' . random_int(1000000, 9999999);
        } while (TelegramAccount::query()->where('phone', $phone)->exists());

        return TelegramAccount::query()->create([
            'phone' => $phone,
            'first_name' => $name,
            'status' => TelegramAccount::STATUS_SUCCESS,
            'is_authorized' => true,
            'authorized_at' => now(),
            'session_path' => TelegramAccount::sessionPathFor($phone),
        ]);
    }
}
