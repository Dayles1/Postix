<?php

namespace App\Helpers;

use App\Models\Ban;
use App\Models\User;
use App\Models\Department;
use Carbon\Carbon;

class BanHelper
{
    /**
     * Tekshiradi: user yoki uning departmenti bannedmi.
     * Agar ban vaqti boshlangan bo‘lsa active=true qilinadi.
     *
     * @param User|null $user
     * @return bool true = banned, false = ok
     */
    public static function isBanned(?User $user): bool
    {
        if (!$user) return false;

        $now = Carbon::now();

        // User banini tekshirish
        $userBan = Ban::where('bannable_type', User::class)
            ->where('bannable_id', $user->id)
            ->where(function ($q) use ($now) {
                $q->where('active', true)
                  ->orWhere(function ($q2) use ($now) {
                      $q2->whereNotNull('starts_at')
                         ->where('starts_at', '<=', $now);
                  });
            })
            ->first();

        if ($userBan) {
            if (!$userBan->active && $userBan->starts_at && $userBan->starts_at <= $now) {
                $userBan->active = true;
                $userBan->save();
            }
            return true;
        }

        // Department banini tekshirish
        if ($user->department_id) {
            $department = Department::find($user->department_id);
            if ($department) {
                // Trial muddati tugagan
                if ($department->plan === 'trial' && $department->trial_expires_at && $department->trial_expires_at < $now) {
                    return true;
                }

                $deptBan = Ban::where('bannable_type', Department::class)
                    ->where('bannable_id', $department->id)
                    ->where(function ($q) use ($now) {
                        $q->where('active', true)
                          ->orWhere(function ($q2) use ($now) {
                              $q2->whereNotNull('starts_at')
                                 ->where('starts_at', '<=', $now);
                          });
                    })
                    ->first();

                if ($deptBan) {
                    if (!$deptBan->active && $deptBan->starts_at && $deptBan->starts_at <= $now) {
                        $deptBan->active = true;
                        $deptBan->save();
                    }
                    return true;
                }
            }
        }

        return false;
    }
}
