<?php

namespace App\Application\Services;


use App\Models\User;

class LimitService
{
    /**
     * User (admin) o‘z departmentiga user qo‘sha oladimi
     */
    public function canCreateUser(User $user): bool
{
    if ($user->role->name === 'superadmin') {
        return true;
    }

    if ($user->role->name !== 'admin') {
        return false;
    }

    $maxUsers = $user->limit->max_users ?? 10;

    $currentUsersCount = $user->department->users()->count();

    return $currentUsersCount < $maxUsers;
}

}
