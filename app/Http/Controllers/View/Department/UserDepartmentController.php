<?php

namespace App\Http\Controllers\View\Department;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Catalog;
use App\Models\Department;
use App\Models\MinutePackage\UserMinuteAccess;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class UserDepartmentController extends Controller
{

    public function create(Request $request)
    {
        $plan = $request->query('plan');
        $user = $request->user();
        $catalogs = collect();

        $catalogs = Catalog::query()
            ->whereHas('user.role', fn($q) => $q->where('name', 'superadmin'))
            ->select('id', 'title')
            ->orderBy('title')
            ->get();

        return view('pages.superadmin.department.user-departments-create', compact(
            'catalogs',
            'plan'
        ));
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::in(['trial', 'pro'])],
            'name' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'min:6'],
            'login' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'catalog_ids' => ['nullable', 'array', 'min:1'],
            'catalog_ids.*' => ['integer', 'exists:catalogs,id'],
            'minute_package' => ['required', Rule::in(['0', '1'])],
            'ban_starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after_or_equal:now'],
        ]);

        $dept = Department::create([
            'name' => $validated['name'],
            'type' => 'user',
            'plan' => $validated['plan'],
        ]);

        $newUser = User::create([
            'department_id' => $dept->id,
            'name' => $validated['name'],
            'email' => $validated['login'],
            'password' => Hash::make($validated['password']),
            'role_id' => Role::where('name', 'user')->value('id'),
        ]);
        // $formatted = $validated['ban_starts_at'] ? Carbon::parse($validated['ban_starts_at'])->format('Y-m-d H:i') : null;
        AuditLog::create([
            'type' => $validated['plan'],
            'action' => 'user_ban',
            'subject_type' => User::class,
            'subject_id' => $newUser->id,
            'causer_type' => User::class,
            'causer_id' => Auth::id(),
            'changes' => [
                    'user' => [
                        'old' => null,
                        'new' => [
                            'name' => $newUser->name,
                            'email' => $newUser->email,
                            'role_id' => $newUser->role_id,
                            'role'=>$newUser->role?->name,
                            'department_id' => $newUser->department_id,
                            'department_name' => $newUser->department?->name,
                            'minute_package' => (bool) $request->minute_package,
                            'ban_starts_at' => $request->ban_starts_at,
                        ],
                    ],
                ],
        ]);

        

        if (!empty($validated['catalog_ids'])) {
            foreach ($validated['catalog_ids'] as $id) {
                $this->cloneCatalogForUser($id, $newUser->id);
            }
        }

        $startsAt = $validated['ban_starts_at'];

        $newUser->ban()->create([
            'starts_at' => $startsAt,
            'active' => false,
        ]);
        if($validated['plan']=='pro'){
            AuditLog::create([
            'type' => 'subscription',
            'action' => 'user_ban',
            'subject_type' => User::class,
            'subject_id' => $newUser->id,
            'causer_type' => User::class,
            'causer_id' => Auth::id(),
                'changes' => [
                    'ban' => [
                        'old' => null,
                        'new' => [
                            'active' => false,
                            'starts_at' => $startsAt,
                        ],
                    ],
                ],
        ]);
        }

        if ($request->boolean('minute_package')) {
            UserMinuteAccess::create([
                'user_id' => $newUser->id,
                'is_active' => true,
            ]);
        }

        $redirectUrl = $request->input('redirecturl')
            ?? ($validated['plan'] === 'pro'
                ? route('departments.pro-users')
                : route('departments.free-users'));

        return redirect()->to($redirectUrl)->with('success', __('messages.common.user_created') ?? 'Saved successfully.');
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
}
