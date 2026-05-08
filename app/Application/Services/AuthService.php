<?php

namespace App\Application\Services;

use App\Models\Ban;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        
    }


    public function createDepartmentAndAdminForTrial(?string $adminName, ?string $adminTelegramId, ?int $createdBy = null): array
    {
        return DB::transaction(function () use ($adminName, $adminTelegramId, $createdBy) {

            // 1) department name — unique-ish: free trial #<last id + 1>
            $lastId = Department::withTrashed()->max('id') ?? 0;
            $deptName = 'free trial #' . ($lastId + 1);

            $department = Department::create([
                'name' => $deptName,
            ]);

            // 2) role id for admin
            $roleAdminId = Role::where('name', 'admin')->value('id');
            if (!$roleAdminId) {
                // fallback: try to create or throw
                throw new \RuntimeException('Admin role not found. Iltimos Role jadvalidagi admin yozuvini tekshiring.');
            }

            // 3) trial dates
            $now = Carbon::now();
            $expires = $now->copy()->addDays(3)->endOfDay();

            // 4) create admin user for this department
            $adminUser = User::create([
                'name' => $adminName ?? ('admin_' . Str::random(6)),
                'telegram_id' => $adminTelegramId,
                'department_id' => $department->id,
                'oferta_read' => false,
                'role_id' => $roleAdminId,
                'email' => null,
                'password' => null,
                'state' => null,
                'value' => null,
                'created_by' => $createdBy,
                'trial_started_at' => $now,
                'trial_expires_at' => $expires,
                'has_used_trial' => true,
                'trial_source' => 'bot',
            ]);

            // 5) create Ban for department (scheduled to start at trial_expires_at)
            $deptBan = Ban::create([
                'bannable_type' => Department::class,
                'bannable_id'   => $department->id,
                'reason'        => 'Auto-ban after 7-day trial',
                'active'        => false, // hanuz aktiv emas
                'starts_at'     => $expires, // 7 kundan keyin boshlanadi
                'until'         => null,
            ]);

            

            return [$department, $adminUser, $deptBan,];
        });
    }
}
