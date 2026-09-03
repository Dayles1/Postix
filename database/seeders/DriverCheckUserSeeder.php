<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DriverCheckUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed Driver Check user and role.
     */
    public function run(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'driverCheck',
        ]);

        User::updateOrCreate(
            [
                'email' => 'driverCheck@gmail.com',
            ],
            [
                'name' => 'Driver Check',
                'password' => Hash::make('Driver123!'),
                'role_id' => $role->id,
                'oferta_read' => true,
            ],
        );
    }
}