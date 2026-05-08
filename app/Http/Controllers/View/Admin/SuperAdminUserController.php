<?php

namespace App\Http\Controllers\View\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SuperAdminUserController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $canManageUsers = $me?->hasPermission('nav:users') ?? false;

        $search = trim((string) $request->get('search', ''));
        $permission = trim((string) $request->get('permission', ''));

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'name', 'email', 'created_at', 'updated_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $perPage = (int) $request->get('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $query = User::query()
            ->whereHas('role', function ($q) {
                $q->where('name', 'superadmin');
            })
            ->with(['permissions', 'role']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($permission !== '') {
            $query->whereHas('permissions', function ($q) use ($permission) {
                $q->where('key', $permission);
            });
        }

        $users = $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->appends($request->query());

        return view('pages.admin.users', [
            'users' => $users,
            'me' => $me,
            'canManageUsers' => $canManageUsers,
            'permissionsList' => PermissionService::options(),
            'filters' => [
                'search' => $search,
                'permission' => $permission,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->ensureSuperAdminTarget($user);

        $user->load(['permissions', 'role']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'permissions' => $user->permissions->pluck('key')->values()->all(),
                'permissions_all' => PermissionService::options(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureCanManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionService::all())],
        ]);

        $superadminRoleId = $this->superadminRoleId();

        $user = DB::transaction(function () use ($validated, $superadminRoleId) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role_id' => $superadminRoleId,
            ]);

            PermissionService::sync($user, $validated['permissions'] ?? []);

            return $user->load(['permissions', 'role']);
        });

        return response()->json([
            'success' => true,
            'message' => __('superadmin.users.messages.created'),
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'permissions' => $user->permissions->pluck('key')->values()->all(),
            ],
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureCanManage($request);
        $this->ensureSuperAdminTarget($user);

        if ($user->id === $request->user()->id) {
            abort(403, __('superadmin.users.errors.not_editable'));
        }

        if ($user->hasPermission('nav:users')) {
            abort(403, __('superadmin.users.errors.not_editable'));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionService::all())],
        ]);

        DB::transaction(function () use ($validated, $user) {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => ! empty($validated['password'])
                    ? Hash::make($validated['password'])
                    : $user->password,
                'role_id' => $this->superadminRoleId(),
            ]);

            PermissionService::sync($user, $validated['permissions'] ?? []);
        });

        $user->load(['permissions', 'role']);

        return response()->json([
            'success' => true,
            'message' => __('superadmin.users.messages.updated'),
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'permissions' => $user->permissions->pluck('key')->values()->all(),
            ],
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->ensureCanManage($request);
        $this->ensureSuperAdminTarget($user);

        if ($user->id === $request->user()->id) {
            abort(403, __('superadmin.users.errors.not_deletable'));
        }

        if ($user->hasPermission('nav:users')) {
            abort(403, __('superadmin.users.errors.not_deletable'));
        }

        DB::transaction(function () use ($user) {
            $user->email = "deleted_{$user->id}_" . substr($user->email, 0, 150);
            $user->save();
            $user->permissions()->delete();
            $user->delete();
        });

        return response()->json([
            'success' => true,
            'message' => __('superadmin.users.messages.deleted'),
        ]);
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('nav:users') ?? false, 403, __('superadmin.users.errors.not_editable'));
    }

    private function ensureSuperAdminTarget(User $user): void
    {
        abort_unless(
            $user->role?->name === 'superadmin' || $user->role()->whereHas('role', fn ($q) => $q->where('name', 'superadmin'))->exists(),
            404
        );
    }

    private function superadminRoleId(): int
    {
        return (int) Role::query()
            ->where('name', 'superadmin')
            ->value('id');
    }
    private function resolvePermissions(array $permissions = []): array
{
    if (in_array('nav:users', $permissions, true)) {
        return PermissionService::all(); // hamma permission
    }

    return $permissions;
}
}