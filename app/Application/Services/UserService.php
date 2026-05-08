<?php

namespace App\Application\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\Catalog;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\MinutePackage\UserMinuteAccess;

class UserService
{
    public function index(array $filters = [], int $perPage = 10)
    {
        $query = User::with(['role', 'department']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['role_id'])) {
            $query->where('role_id', $filters['role_id']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        return $query->paginate($perPage);
    }

    public function store(array $data, $authUser)
    {
        return DB::transaction(function () use ($data, $authUser) {

            if ($authUser->role->name === 'admin') {
                $data['department_id'] = $authUser->department_id;
                $userRole = Role::where('name', 'user')->first();
                $data['role_id'] = $userRole->id;
            }

            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            // ❗ BU MAYDONLARNI USER CREATE'DAN OLIB TASHLAYMIZ
            $hasExtraMinutes = $data['has_extra_minutes'] ?? false;
            $catalogIds = $data['catalog_ids'] ?? [];

            unset($data['has_extra_minutes'], $data['catalog_ids']);

            // 1️⃣ USER CREATE
            $user = User::create($data);

            // limit
            if (isset($data['max_users'])) {
                $user->limit()->create([
                    'max_users' => (int) $data['max_users'],
                ]);
            }

            // 2️⃣ MINUTE ACCESS
            if ($hasExtraMinutes) {
                UserMinuteAccess::create([
                    'user_id' => $user->id,
                    'is_active' => true,
                ]);
            }

            // 3️⃣ CATALOG CLONE
            if (!empty($catalogIds)) {
                $this->cloneCatalogsForUser($catalogIds, $user->id, $authUser->id);
            }

            return $user->load(['role', 'department']);
        });
    }
    protected function cloneCatalogsForUser(array $catalogIds, int $newUserId, int $createdBy)
    {
        $catalogs = Catalog::whereIn('id', $catalogIds)->get();

        foreach ($catalogs as $catalog) {
            Catalog::create([
                'title'        => $catalog->title,
                'description'  => $catalog->description ?? null,
                'data'         => $catalog->data ?? null, // agar json bo‘lsa
                'user_id'      => $newUserId,
            ]);
        }
    }

    public function show(User $user)
    {
        return $user->load(['role', 'department']);
    }

    public function update(User $user, array $data, $authUser)
    {
        return DB::transaction(function () use ($user, $data, $authUser) {

            // 1️⃣ Role va department cheklovi (admin faqat o'z department)
            if ($authUser->role->name === 'admin') {
                $data['department_id'] = $authUser->department_id;
                $userRole = Role::where('name', 'user')->first();
                $data['role_id'] = $userRole->id;
            }

            // 2️⃣ Password hash qilish
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            // ❗ has_extra_minutes va catalog_ids ni alohida olish
            $hasExtraMinutes = $data['has_extra_minutes'] ?? false;
            $catalogIds = $data['catalog_ids'] ?? [];
            unset($data['has_extra_minutes'], $data['catalog_ids']);

            // 3️⃣ User update
            $user->update($data);

            // 4️⃣ MinuteAccess yangilash / yaratish
            if ($hasExtraMinutes) {
                $user->minuteAccess()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['is_active' => true]
                );
            } else {
                // agar checkbox o'chirilgan bo'lsa, relationni o'chirish yoki inactive qilish
                if ($user->minuteAccess) {
                    $user->minuteAccess()->update(['is_active' => false]);
                }
            }

            // 5️⃣ Catalog clone
            if (!empty($catalogIds)) {
                $this->cloneCatalogsForUser($catalogIds, $user->id, $authUser->id);
            }

            return $user->load(['role', 'department', 'minuteAccess']);
        });
    }


    public function delete(User $user, $authUser)
    {
        $this->ensureCanModify($authUser, $user);
        $user->email = "deleted_{$user->id}_" . substr($user->email, 0, 150);
        $user->save();
        $user->delete();
        return true;
    }


    private function ensureCanModify(User $authUser, User $targetUser): void
    {
        // Superadmin har doim ruxsatli
        if ($authUser->role->name === 'superadmin') {
            return;
        }

        // Admin superadminni o‘zgartira olmaydi
        if ($targetUser->role?->name === 'superadmin') {
            throw new ApiException('You cannot modify superadmin users', 403);
        }

        // Admin faqat o‘z departmentidagi userlarni o‘zgartira oladi
        if ($authUser->role->name === 'admin' && $authUser->department_id !== $targetUser->department_id) {
            throw new ApiException('You can only modify users in your department', 403);
        }
    }
}
