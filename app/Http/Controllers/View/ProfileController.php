<?php

namespace App\Http\Controllers\View;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    private function syncBanState(string $type, int $id): void
    {
        // starts_at o'tgan → active = true
        \App\Models\Ban::query()
            ->where('bannable_type', $type)
            ->where('bannable_id', $id)
            ->where('active', false)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->update([
                'active' => true,
            ]);

        // until o'tgan → active = false
        \App\Models\Ban::query()
            ->where('bannable_type', $type)
            ->where('bannable_id', $id)
            ->where('active', true)
            ->whereNotNull('until')
            ->where('until', '<=', now())
            ->update([
                'active' => false,
            ]);
    }
    public function profile(Request $request, $id)
    {
        $auth = $request->user();

        $user = User::with([
            'phones',
            'ban',
            'avatar',
            'department',
            'role',
            'limit',
            'phones.messageGroups' => function ($q) {
                $q->withCount('messages');
            },
        ])->findOrFail($id);

        if ($auth->role->name !== 'superadmin' && $user->department_id !== $auth->department_id) {
            abort(403, __('messages.users.access_denied'));
        }

        $departmentBan = false;
        $department = $user->department;

        $this->syncBanState(User::class, $user->id);
        $user->load('ban');
        
        if ($department) {
            $departmentBan = DB::table('bans')
                ->where('bannable_type', Department::class)
                ->where('bannable_id', $department->id)
                ->where(function ($q) {
                    $q->where('active', true)
                        ->orWhere('starts_at', '<', now());
                })
                ->exists();
        }

        $canLogout = in_array($auth->role->name, ['superadmin', 'admin']);

        $operationsCount = $user->phones->sum(function ($phone) {
            return $phone->messageGroups->count();
        });

        $messagesCount = $user->phones->sum(function ($phone) {
            return $phone->messageGroups->sum('messages_count');
        });

        $canBan = false;
        $canEditRole = false;
        $canEdit = false;

        if ($auth->role->name === 'superadmin') {
            $canEdit = true;

            if ($user->role->name !== 'superadmin') {
                $canBan = true;
                $canEditRole = true;
            }
        } elseif ($auth->role->name === 'admin') {
            if ($auth->id == $user->id) {
                $canEdit = true;
            } else {
                $canEdit = true;

                if ($user->role->name === 'admin') {
                    $canEditRole = ($user->created_by == $auth->id);
                    $canBan = ($user->created_by == $auth->id);

                    if (!$canEditRole) {
                        $canEdit = false;
                    }
                } else {
                    $canBan = true;
                }

                $canEditRole = false;
            }
        } elseif ($auth->role->name === 'user') {
            if ($auth->id == $user->id) {
                $canEdit = true;
                $canBan = false;
                $canEditRole = false;
            }
        }

        // LIMIT: faqat admin user uchun limit bor, yo'q bo'lsa 0
        $userLimit = $user->role?->name === 'admin'
            ? (int) ($user->limit?->max_users ?? 0)
            : 0;

        // faqat superadmin limit boshqaradi, va faqat admin user uchun
        $canEditLimit = $auth->role->name === 'superadmin' && $user->role?->name === 'admin';

        $minuteAccess = $user->minuteAccess;

        $roles = Role::whereNotIn('name', ['superadmin'])->get();

        return view('pages.general.profile', compact(
            'user',
            'department',
            'operationsCount',
            'messagesCount',
            'minuteAccess',
            'canEdit',
            'canBan',
            'canEditRole',
            'roles',
            'canLogout',
            'departmentBan',
            'userLimit',
            'canEditLimit'
        ));
    }
    public function update(Request $request, $id)
    {
        $auth = $request->user();

        $user = User::with(['avatar', 'role', 'limit', 'minuteAccess'])->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'max:255', Rule::unique('users')->ignore($user->id)],
            'telegram_id' => ['nullable', 'string', 'max:255'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'password' => ['nullable', 'min:6'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'active_phone_id' => ['nullable', 'integer', 'exists:user_phones,id'],
            'user_limit' => ['nullable', 'integer', 'min:0'],
            'minute_package' => ['nullable', 'boolean'],
        ]);

        // OLD VALUES
        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'telegram_id' => $user->telegram_id,
            'role_id' => $user->role_id,
            'role' => $user->role?->name,
            'avatar_path' => $user->avatar?->path,
            'minute_package' => $user->minuteAccess?->is_active ?? false,
            'limit' => $user->limit?->max_users ?? 0,
            'password' => null, // faqat comparison uchun
        ];
        $passwordChanged = !empty($data['password']);


        // UPDATE FIELDS
        $user->name = $data['name'];
        $user->email = $data['email'] ?? $user->email;
        $user->telegram_id = $data['telegram_id'] ?? $user->telegram_id;

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        // avatar upload
        if ($request->hasFile('avatar')) {
            $f = $request->file('avatar');
            $path = $f->store('avatars', 'public');

            try {
                $old = $user->avatar;
                if ($old && $old->path) {
                    Storage::disk('public')->delete($old->path);
                }
            } catch (\Throwable $e) {
            }

            $user->avatar()->updateOrCreate([], ['path' => $path]);
            Cache::forget('header_html_user_' . $user->id . '_' . app()->getLocale());
        } elseif ($request->boolean('remove_avatar')) {
            try {
                $old = $user->avatar;
                if ($old && $old->path) {
                    Storage::disk('public')->delete($old->path);
                }
                $user->avatar()->delete();
            } catch (\Throwable $e) {
            }
        }

        if (isset($data['role_id'])) {
            $user->role_id = $data['role_id'];
        }

        // minute access faqat superadmin
        if ($auth->role->name === 'superadmin') {
            $user->minuteAccess()->updateOrCreate([], [
                'is_active' => (bool) $request->minute_package
            ]);
        }

        $user->save();

        $user->refresh();
        $user->load('role');
        // active phone
        if (!empty($data['active_phone_id'])) {
            DB::transaction(function () use ($user, $data) {
                DB::table('user_phones')->where('user_id', $user->id)->update(['is_active' => 0]);
                DB::table('user_phones')->where('id', $data['active_phone_id'])->update(['is_active' => 1]);
            });
        }

        // LIMIT: faqat superadmin + target user admin bo'lsa
        $targetRoleName = $user->role?->name ?? 'user';
        $canManageLimit = $auth->role->name === 'superadmin' && $targetRoleName === 'admin';

        $limitValue = 0;
        if ($canManageLimit) {
            $limitValue = isset($data['user_limit']) ? max(0, (int) $data['user_limit']) : 0;
        }

        // admin emas bo'lsa ham 0 qilib yuboramiz
        $user->limit()->updateOrCreate([], [
            'max_users' => $canManageLimit ? $limitValue : 0,
        ]);

        // NEW VALUES
        $newValues = [
            'name' => $user->name,
            'email' => $user->email,
            'telegram_id' => $user->telegram_id,
            'role_id' => $user->role_id,
            'role' => $user->role?->name,
            'avatar_path' => $user->avatar?->path,
            'minute_package' => $user->minuteAccess?->is_active ?? false,
            'limit' => $user->limit?->max_users ?? 0,
            'password' => $passwordChanged ? '***' : null,
        ];


        // AUDIT LOG
        $changes = [];
        if ($passwordChanged) {
            $changes['password'] = [
                'old' => '***',
                'new' => '***',
            ];
        }
        foreach ($oldValues as $key => $oldVal) {
            if ($oldVal !== $newValues[$key]) {
                $changes[$key] = [
                    'old' => $oldVal,
                    'new' => $newValues[$key],
                ];
            }
        }

        if (!empty($changes)) {
            AuditLog::create([
                'type' => $user->department?->plan,
                'action' => 'user_update',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'causer_type' => User::class,
                'causer_id' => $auth->id,
                'changes' => $changes,
            ]);
        }

        return redirect()->back()
            ->with('success', __('messages.users.user_updated') ?? 'User updated');
    }
}
