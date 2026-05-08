<?php

namespace App\Http\Controllers\View\Admin;

use App\Application\Services\LimitService;
use App\Application\Services\TelegramAuthService;
use App\Http\Controllers\Controller;
use App\Jobs\TelegramAuthJob;
use App\Models\AuditLog;
use App\Models\Catalog;
use App\Models\Department;
use App\Models\Limit;
use App\Models\MinutePackage\UserMinuteAccess;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(protected LimitService $limit, protected TelegramAuthService $authService) {}
    public function show(Request $request, $id)
    {
        $user = User::with([
            'avatar',
            'phones.messageGroups.messages',
            'ban',
            'role',
            'department',
        ])
            ->withTrashed()   // oldin
            ->findOrFail($id); // keyin


        $authUser = $request->user();

        if (
            $authUser->role->name !== 'superadmin'
            && $user->department_id !== $authUser->department_id
        ) {
            abort(403, __('messages.users.access_denied'));
        }

        $department = $user->department;

        $operationsCount = $user->phones
            ->pluck('messageGroups')
            ->flatten()
            ->count();

        $messagesCount = $user->phones
            ->pluck('messageGroups')
            ->flatten()
            ->pluck('messages')
            ->flatten()
            ->count();
        $auth = $request->user();

        $canBan = false;
        $canEditRole = false;
        $canEdit = false;
        $deleted = false;

        if (($auth->role->name ?? null) === 'admin') {

            if ((int)$auth->id === (int)$user->id) {
                $canEdit = true;
                $canBan = false;
                $canEditRole = false;
            } else {
                $canEdit = true;

                if (($user->role->name ?? null) === 'admin') {
                    if ((int)$user->created_by === (int)$auth->id) {
                        $canEditRole = true;
                        $canBan = true;
                    } else {
                        $canEditRole = false;
                        $canBan = false;
                        $canEdit = false;
                    }
                } else {
                    $canEditRole = true;
                    $canBan = true;
                }
            }
        }



        $roles = Role::whereNotIn('name', ['superadmin'])->get();
        if (($authUser->role->name ?? null) === 'superadmin') {
            $canBan = true;
            $canEditRole = true;
            $canEdit = true;
        }
        if ($user->deleted_at !== null) {
            $canBan = false;
            $canEditRole = false;
            $canEdit = false;
            $deleted = true;
        }
        if ($authUser->id === $user->id) {
            $canEdit = true;
        }
        if ($authUser->role->name === 'superadmin') {
            return view('admin.users.superadmin', compact(
                'user',
                'department',
                'operationsCount',
                'messagesCount',
                'canBan',
                'deleted',
                'canEditRole',
                'canEdit',
                'roles'
            ));
        }
        return view('admin.users.show', compact(
            'user',
            'department',
            'operationsCount',
            'messagesCount',
            'canBan',
            'deleted',
            'canEditRole',
            'canEdit',
            'roles'
        ));
    }


    public function update(Request $request, $id)
    {
        $user = User::with('avatar')->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'max:255', Rule::unique('users')->ignore($user->id)],
            'telegram_id' => ['nullable', 'string', 'max:255'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'password' => ['nullable', 'min:6'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'active_phone_id' => ['nullable', 'integer', 'exists:user_phones,id'],
        ]);

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
                if ($old && $old->path) Storage::disk('public')->delete($old->path);
            } catch (\Throwable $e) {
            }

            $user->avatar()->updateOrCreate([], ['path' => $path]);
        } elseif ($request->boolean('remove_avatar')) {
            try {
                $old = $user->avatar;
                if ($old && $old->path) Storage::disk('public')->delete($old->path);
                $user->avatar()->delete();
            } catch (\Throwable $e) {
            }
        }
        if (isset($data['role_id'])) {
            $user->role_id = $data['role_id'];
        }
        $user->save();

        // set active phone if requested
        if (!empty($data['active_phone_id'])) {
            DB::transaction(function () use ($user, $data) {
                DB::table('user_phones')->where('user_id', $user->id)->update(['is_active' => 0]);
                DB::table('user_phones')->where('id', $data['active_phone_id'])->update(['is_active' => 1]);
            });
        }

        return redirect()->route('admin.users.show', $user->id)
            ->with('success', __('messages.users.user_updated') ?? 'User updated');
    }

    // add new phone
    public function addPhone(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
        ]);

        $phone = new UserPhone();
        $phone->user_id = $user->id;
        $phone->phone = $data['phone'];
        $phone->is_active = 0;
        $phone->save();

        return redirect()->route('admin.users.show', $user->id)->with('success', __('messages.users.phone_added') ?? 'Phone added');
    }

    public function deletePhone(Request $request, $id, $phoneId)
    {
        $user = User::findOrFail($id);
        $phone = UserPhone::where('user_id', $user->id)->where('id', $phoneId)->firstOrFail();

        // if it's active, try to unset or set another phone active
        if ($phone->is_active) {
            // set another phone active (if exists)
            $other = UserPhone::where('user_id', $user->id)->where('id', '<>', $phone->id)->first();
            if ($other) {
                $other->is_active = 1;
                $other->save();
            }
        }

        $phone->delete();

        return redirect()->route('admin.users.show', $user->id)->with('success', __('messages.users.phone_deleted') ?? 'Phone deleted');
    }
    public function canUsePhone(string $phone, ?int $currentUserId = null): bool
    {
        $userPhone = UserPhone::where('phone', $phone)->where('is_active', true)->first();

        if (!$userPhone) {
            return true;
        }

        if ($userPhone->is_active) {
            return false;
        }

        return true;
    }


    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        try {
            $old = $user->avatar;
            if ($old && $old->path) Storage::disk('public')->delete($old->path);
            $user->avatar()->delete();
        } catch (\Throwable $e) {
        }

        $departmentId = $user->department_id;
        $user->delete();

        return redirect()->back()->with('success', __('messages.users.user_deleted') ?? 'User deleted');
    }
    public function createTelegramUser(Request $request, $departmentId)
    {
        $validator = Validator::make($request->all(), [
            'name'        => ['required', 'string', 'max:255'],
            'login'       => ['required', 'string', 'max:255', Rule::unique('users', 'email')],
            'password'    => ['required', 'string', 'min:6'],
            'role_id'     => ['required', 'integer', 'exists:roles,id'],
            'user_limit'  => ['sometimes', 'integer', 'min:1'],
            'minute_package' => ['nullable', 'boolean'],
            'ban_starts_at' => ['nullable', 'date_format:Y-m-d'],
            'catalog_ids' => ['nullable', 'array'],
            'catalog_ids.*' => ['integer', 'exists:catalogs,id'],

        ]);



        if ($validator->fails()) {
            return response()->json([
                'message' => __('messages.validation_failed'),
                'errors'  => $validator->errors()
            ], 422);
        }

        $auth = $request->user();
        if (!$this->limit->canCreateUser($auth)) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.telegram.limit')
            ], 403);
        }
        if ($auth->role->name === 'user') {
            abort(403, __('messages.no_permission') ?? 'Forbidden');
        }

        $department = Department::find($departmentId);
        if (!$department) {
            return response()->json([
                'status'  => 'error',
                'message' => __('messages.departments.not_found')
            ], 404);
        }

        $data = $validator->validated();

        // Create user
        $newUser = User::create([
            'name'          => $data['name'],
            'email'         => $data['login'],
            'password'      => Hash::make($data['password']),
            'role_id'       => $data['role_id'],
            'created_by'    => $auth->id,
            'department_id' => $department->id,
        ]);


            AuditLog::create([
                'type' => $department->plan,
                'action' => 'user_create',
                'subject_type' => User::class,
                'subject_id' => $newUser->id,
                'causer_type' => User::class,
                'causer_id' => $auth->id,
                'changes' => [
                    'user' => [
                        'old' => null,
                        'new' => [
                            'name' => $newUser->name,
                            'email' => $newUser->email,
                            'role_id' => $newUser->role_id,
                            'role'=>$newUser->role?->name,
                            'department_id' => $newUser->department_id,
                            'department_name' => $department?->name,
                            'minute_package' => (bool) $request->minute_package,
                            'ban_starts_at' => $request->ban_starts_at,
                        ],
                    ],
                ],
            ]);
        if ($request->has('catalog_ids')) {
            $catalogIds = $request->input('catalog_ids', []);
            $clonedCatalogs = [];

            foreach ($catalogIds as $id) {
                $newCatalog = $this->cloneCatalogForUser($id, $newUser->id);
                if ($newCatalog) {
                    $clonedCatalogs[] = $newCatalog;
                }
            }
        }
        if (isset($data['ban_starts_at'])) {
            $newUser->ban()->create([
                'starts_at' => $data['ban_starts_at'],
                'active' => false,
            ]);
        }

        // Minute package
        if ($request->boolean('minute_package')) {
            UserMinuteAccess::create([
                'user_id'   => $newUser->id,
                'is_active' => true,
            ]);
        }

        // Superadmin → admin yaratayotganda limit
        $role = Role::find($data['role_id']);
        if ($auth->role->name === 'superadmin' && $role && strtolower($role->name) === 'admin') {
            Limit::create([
                'max_users'      => $data['user_limit'] ?? 10,
                'limitable_type' => User::class,
                'limitable_id'   => $newUser->id,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'user_id' => $newUser->id,
            'user' => [
                'id'    => $newUser->id,
                'name'  => $newUser->name,
                'email' => $newUser->email,
            ]
        ]);
    }
    public function cloneCatalogForUser(int $catalogId, $user): ?Catalog
    {
        $catalog = Catalog::find($catalogId);
        if (!$catalog) {
            return null;
        }

        if ($user instanceof User) {
            $userId = $user->id;
        } else {
            $userId = (int) $user;
        }

        $newCatalog = $catalog->replicate();
        $newCatalog->user_id = $userId;
        $newCatalog->title = $catalog->title . ' (Copy)';
        $newCatalog->push();

        return $newCatalog->fresh();
    }

    public function sendPhone(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'phone' => ['required', 'string', 'regex:/^\+\d{6,16}$/'],
                'name' => 'required|string|max:255',
                'login' => 'required|string|max:255|unique:users,email',
                'password' => 'required|string|min:6',
                'role_id' => 'nullable|integer|exists:roles,id',
                'department_id' => 'required|integer|exists:departments,id',
                'minute_package' => 'nullable|boolean', // <-- yangi
                'ban_starts_at' => 'nullable|date_format:Y-m-d',
                'catalog_ids' => 'nullable|array',
                'catalog_ids.*' => 'integer|exists:catalogs,id',
            ],
            [
                'phone.regex' => __('messages.telegram.phone_invalid'),
            ]
        );

        $user = Auth::user();
        if (!$this->limit->canCreateUser($user)) {
            return response()->json([
                'status' => 'error',
                'message' => __('messages.telegram.limit')
            ], 403);
        }
        if ($validator->fails()) {
            return response()->json([
                'message' => __('messages.validation_failed') ?? 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $phone = preg_replace('/[^0-9+]/', '', $request->input('phone', ''));
            if (!str_starts_with($phone, '+')) {
                $phone = '+' . $phone;
            }

            if (!$this->canUsePhone($phone)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('messages.telegram.user_exists')
                ], 403);
            }


            if (!$this->limit->canCreateUser($user)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('messages.telegram.limit')
                ], 403);
            }

            $department_id = $request->department_id;

            $newUser = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('login'),
                'password' => Hash::make($request->input('password')),
                'role_id' => $request->input('role_id'),
                'created_by' => $user->id,
                'department_id' => $department_id,
            ]);
            $dep=Department::find($department_id);

            AuditLog::create([
                'type' => $dep->plan,
                'action' => 'user_create',
                'subject_type' => User::class,
                'subject_id' => $newUser->id,
                'causer_type' => User::class,
                'causer_id' => $user->id,
                'changes' => [
                    'user' => [
                        'old' => null,
                        'new' => [
                            'name' => $newUser->name,
                            'email' => $newUser->email,
                            'role_id' => $newUser->role_id,
                            'role'=>$newUser->role?->name,
                            'department_id' => $newUser->department_id,
                            'department_name' => $dep?->name,
                            'minute_package' => (bool) $request->minute_package,
                            'ban_starts_at' => $request->ban_starts_at,
                        ],
                    ],
                ],
            ]);


            if ($request->has('catalog_ids')) {
                $catalogIds = $request->input('catalog_ids', []);
                $clonedCatalogs = [];

                foreach ($catalogIds as $id) {
                    $newCatalog = $this->cloneCatalogForUser($id, $newUser->id);
                    if ($newCatalog) {
                        $clonedCatalogs[] = $newCatalog;
                    }
                }
            }
            if ($request->ban_starts_at) {
                $newUser->ban()->create([
                    'starts_at' => $request->ban_starts_at,
                    'active' => false,
                ]);
            }

            // Minute package

            if ($request->boolean('minute_package')) {
                UserMinuteAccess::create([
                    'user_id' => $newUser->id,
                    'is_active' => true,
                ]);
            }

            $session = \App\Models\TelegramAuthSession::updateOrCreate(
                [
                    'user_id' => $newUser->id,
                    'phone' => $request->phone,
                ],
                [
                    'status' => 'pending',
                    'message_key' => 'wait',
                    'message' => null,
                    'attempts' => 0
                ]
            );

            TelegramAuthJob::dispatch($phone, $newUser->id, $session->id)->onQueue('telegram');

            return response()->json([
                'status' => 'processing',
                'user_id' => $newUser->id,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
    public function newTelegramUsers(Request $request, $deptID = null)
    {
        $user = $request->user();
        $authUserRole = optional($user->role)->name ?? '';
        $isSuperadmin = $authUserRole === 'superadmin';

        if ($authUserRole === 'user') {
            abort(403, __('messages.admin.no_permission'));
        }

        $department = $isSuperadmin
            ? Department::with('users.phones')->findOrFail($deptID)
            : $user->department;

        if (!$department) {
            return redirect()->back()
                ->withErrors(['department' => __('messages.admin.not_found')]);
        }

        $ban = $department->ban;
        $newUserNeedBan = !(
            $ban &&
            $ban->starts_at &&
            $ban->starts_at->isFuture()
        );

        $roles = Role::query()
            ->when($authUserRole === 'superadmin', function ($q) {
                $q->whereNotIn('name', ['superadmin']);
            }, function ($q) {
                $q->whereNotIn('name', ['superadmin', 'admin']);
            })
            ->get();

        $limit = \App\Models\Limit::where('limitable_type', User::class)
            ->where('limitable_id', $user->id)
            ->first();

        $usersCount = $department->users()->count();
        $maxUsers = (int) ($limit?->max_users ?? 0);

        if ($authUserRole === 'superadmin') {
            $canAdd = true;
            $usersLimitReached = false;
        } else {
            $canAdd = $usersCount < $maxUsers;
            $usersLimitReached = !$canAdd;
        }

        $catalogs = collect();

        if ($isSuperadmin) {
            $catalogs = Catalog::query()
                ->whereHas('user.role', fn($q) => $q->where('name', 'superadmin'))
                ->select('id', 'title')
                ->orderBy('title')
                ->get();
        }

        return view('pages.general.telegram.new-user', compact(
            'department',
            'catalogs',
            'roles',
            'authUserRole',
            'usersLimitReached',
            'canAdd',
            'limit',
            'usersCount',
            'maxUsers',
            'newUserNeedBan'
        ));
    }


    protected function resolveUserFromRequest(Request $request): User
    {
        $userId = $request->input('user_id') ?? $request->query('user_id');
        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                abort(404, 'User topilmadi');
            }
            return $user;
        }

        $user = $request->user();
        if (! $user) {
            abort(401, 'Login talab qilinadi');
        }
        return $user;
    }
}
