<?php

namespace Database\Seeders;

use App\Models\Telegram\TelegramFileRoute;
use Illuminate\Database\Seeder;

/**
 * The kinds of files the notifications bot sends every morning.
 *
 * php artisan db:seed --class=TelegramFileRouteSeeder
 *
 * Safe to run again: a kind that already exists is left as it is, so the
 * groups chosen on the "Fayllar" page are kept.
 */
class TelegramFileRouteSeeder extends Seeder
{
    public function run(): void
    {
        $routes = [
            [
                'name' => 'Zitic',
                'keywords' => 'zitic',
                'target_chat_ids' => [-1004431008937],
            ],
            [
                'name' => 'Turkey',
                'keywords' => 'turkey',
                'target_chat_ids' => [],
            ],
            [
                'name' => 'Declarant (Benyakoni)',
                'keywords' => 'benyakoni',
                'target_chat_ids' => [-5524265426],
            ],
            [
                'name' => 'Declarant (Kamennii Log)',
                'keywords' => 'kamennii log, kamenni log',
                'target_chat_ids' => [-5524265426],
            ],
            [
                'name' => 'Kazakhstan-China',
                'keywords' => 'kazakhstan china',
                'target_chat_ids' => [],
            ],
            [
                'name' => 'Qozoq (tekshirilgan)',
                'keywords' => 'qozoq',
                'target_chat_ids' => [],
            ],
        ];

        foreach ($routes as $sort => $route) {
            TelegramFileRoute::query()->firstOrCreate(
                ['name' => $route['name']],
                $route + [
                    'is_active' => true,
                    'sort' => $sort,
                ],
            );
        }
    }
}
