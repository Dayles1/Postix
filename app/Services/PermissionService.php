<?php

namespace App\Services;

use App\Models\User;

class PermissionService
{
    public function __construct()
    {
        //
    }

    public static function options(): array
    {
        return [
            ['key' => 'nav:import',  'text' => __('messages.import')],
            ['key' => 'nav:export',  'text' => __('messages.export')],
            ['key' => 'nav:turkey',  'text' => __('messages.turkey')],
            ['key' => 'nav:logs', 'text' => __('messages.logs.title')],
            ['key' => 'nav:catalogs',    'text' => __('messages.table.catalogs')],
            ['key' => 'nav:users',    'text' => __('messages.admin.users')],
        ];
    }
    public static function all(): array
    {
        return array_column(self::options(), 'key');
    }
    public static function label(string $key): string
    {
        foreach (self::options() as $permission) {
            if ($permission['key'] === $key) {
                return $permission['text'];
            }
        }

        return $key;
    }
    public static function sync(User $user, array $permissions = [])
    {
        $allPermissions = self::all();
        $permissions = array_intersect($permissions, $allPermissions);

        $user->permissions()->delete();

        $insert = [];

        foreach ($permissions as $key) {
            $insert[] = [
                'user_id' => $user->id,
                'key' => $key,
                'allowed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (!empty($insert)) {
            $user->permissions()->insert($insert);
        }
    }
}
