<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        
        $roles=['user','admin','superadmin'];
        foreach($roles as $role)
        {
            Role::firstOrCreate(['name' => $role]);
        }
        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@postix.ai'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('2030%E@stline'),
                'role_id' => Role::where('name', 'superadmin')->value('id'),
                'oferta_read' => true,
            ]
        );
        \App\Services\PermissionService::sync(
            $superadmin,
            \App\Services\PermissionService::all()
        );
    }
}
