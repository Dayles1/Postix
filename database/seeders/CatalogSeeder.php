<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Catalog;
use App\Models\User;

class CatalogSeeder extends Seeder
{
    public function run()
    {
        // department_id = 3 bo'lgan userlarni olish
        $users = User::where('department_id', 3)->get();

        if ($users->isEmpty()) {
            $this->command->info('Department 3 uchun user topilmadi.');
            return;
        }

        // cataloglar nomlarini yaratish
        $catalogsData = [
            ['title' => 'Catalog A'],
            ['title' => 'Catalog B'],
        ];

        foreach ($users as $user) {
            foreach ($catalogsData as $catalogData) {
                // peerlar sonini 10 dan 100 gacha tasodifiy
                $peerCount = rand(10, 100);
                $peers = [];
                for ($i = 1; $i <= $peerCount; $i++) {
                    $peers[] = '@peer' . $i;
                }

                Catalog::create([
                    'title' => $catalogData['title'],
                    'user_id' => $user->id,
                    'peers' => $peers,
                ]);
            }
        }

        $this->command->info('Department 3 uchun cataloglar yaratildi (har birida 10-100 peer).');
    }
}