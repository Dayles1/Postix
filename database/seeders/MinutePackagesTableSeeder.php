<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MinutePackage\MinutePackage;

class MinutePackagesTableSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['minutes' => 1,  'name' => '1 minut'],
            ['minutes' => 5,  'name' => '5 minut'],
            ['minutes' => 10, 'name' => '10 minut'],
            ['minutes' => 20, 'name' => '20 minut'],
            ['minutes' => 31, 'name' => '30 minut'],
            ['minutes' => 62, 'name' => '60 minut'],
        ];

        foreach ($packages as $pkg) {
            MinutePackage::updateOrCreate(
                ['minutes' => $pkg['minutes']],
            );
        }
    }
}
