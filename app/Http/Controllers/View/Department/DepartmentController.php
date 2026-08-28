<?php

namespace App\Http\Controllers\View\Department;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ban;
use App\Models\Department;
use App\Models\MessageGroup;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    // Controller (masalan DepartmentsController)

    public function index(Request $request)
{
    $user = $request->user();

    if (
        $user
        && ($user->role->name ?? null) === 'driverCheck'
    ) {
        return redirect()->route(
            'driver-check.dashboard'
        );
    }

    return $this->getByPlan(
        $request,
        'pro',
        false,
        false
    );
}

    public function free(Request $request)
    {
        return $this->getByPlan($request, 'trial', true, false);
    }
    public function freeBanned(Request $request)
    {
        return $this->getByPlan($request, 'trial', true, false, 'banned');
    }
    public function freeUsers(Request $request)
    {
        return $this->getByPlan($request, 'trial', true, false, null, 'user');
    }
    public function proUsers(Request $request)
    {
        return $this->getByPlan($request, 'pro', false, false, null, 'user');
    }
    public function deleted(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role->name, ['superadmin'])) {
            return redirect()->route('departments.show', $user->department_id);
        }

        $search = $request->get('search');
        $sort = $request->get('sort', 'desc');
        $bannedFilter = $request->get('banned', 'all');

        $bannableType = addslashes(\App\Models\Department::class);

        $query = DB::table('departments')
            ->whereIn('plan', ['pro', 'trial'])
            ->whereNotNull('departments.deleted_at')
            ->select('departments.*')
            ->addSelect($this->baseDepartmentSelects($bannableType));

        if ($search) {
            $query->where('departments.name', 'like', '%' . $search . '%');
        }

        // banned filter
        if ($bannedFilter === 'banned') {
            $query->whereRaw("(SELECT COUNT(*) FROM bans WHERE bans.bannable_type = ? AND bans.bannable_id = departments.id AND bans.active = 1) > 0", [\App\Models\Department::class]);
        } elseif ($bannedFilter === 'not_banned') {
            $query->whereRaw("(SELECT COUNT(*) FROM bans WHERE bans.bannable_type = ? AND bans.bannable_id = departments.id AND bans.active = 1) = 0", [\App\Models\Department::class]);
        }

        $deptStats = $query
            ->orderBy('departments.created_at', $sort)
            ->paginate(18)
            ->withQueryString();

        $status = 'deleted';
        $free = null;
        $deleted = true; // bu flag qo‘shildi
        $forcedBanned = null;
        return view('pages.superadmin.department.departments', compact('deptStats', 'search', 'status', 'sort', 'free', 'bannedFilter', 'deleted', 'forcedBanned'));
    }
    protected function getByPlan(Request $request, string $plan, bool $free, bool $deleted, ?string $forcedBanned = null, $type = 'department')
    {
        $user = $request->user();
        if (!in_array($user->role->name, ['superadmin'])) {
            return $this->show($request, $user->department_id);
        }

        $search = $request->get('search');
        $sort   = $request->get('sort', 'desc');

        // Forced banned bo'lsa shuni ishlat, aks holda requestdan olish
        $bannedFilter = $forcedBanned ?? $request->get('banned', 'all');

        $query = $this->buildDepartmentsQuery($plan, !$deleted, $type);

        if ($search) {
            $query->where('departments.name', 'like', '%' . $search . '%');
        }

        // ====================== MUHIM O'ZGARISH ======================
        if ($free && $forcedBanned === null) {
            $query->whereRaw(
                "(SELECT COUNT(*) FROM bans 
              WHERE bans.bannable_type = ? 
                AND bans.bannable_id = departments.id 
                AND bans.active = 1) = 0",
                [\App\Models\Department::class]
            );
        } elseif ($bannedFilter === 'banned') {
            $query->whereRaw(
                "(SELECT COUNT(*) FROM bans 
              WHERE bans.bannable_type = ? 
                AND bans.bannable_id = departments.id 
                AND bans.active = 1) > 0",
                [\App\Models\Department::class]
            );
        } elseif ($bannedFilter === 'not_banned') {
            $query->whereRaw(
                "(SELECT COUNT(*) FROM bans 
              WHERE bans.bannable_type = ? 
                AND bans.bannable_id = departments.id 
                AND bans.active = 1) = 0",
                [\App\Models\Department::class]
            );
        }

        $deptStats = $query
            ->orderBy('departments.created_at', $sort)
            ->paginate(18)
            ->withQueryString();

        if ($type == 'user') {

            return view('pages.superadmin.department.user-departments', compact(
                'deptStats',
                'search',
                'sort',
                'plan',
                'bannedFilter',
                // 'deleted',
                // 'forcedBanned'
            ));
        }
        return view('pages.superadmin.department.departments', compact(
            'deptStats',
            'search',
            'sort',
            'free',
            'bannedFilter',
            'deleted',
            'forcedBanned'
        ));
    }
    protected function baseDepartmentSelects(string $bannableType)
    {
        return [
            DB::raw("(SELECT COUNT(*) FROM users WHERE users.department_id = departments.id) AS users_count"),
            DB::raw("(SELECT COUNT(*) FROM users WHERE users.department_id = departments.id AND users.deleted_at IS NULL) AS users_count_active"),
            DB::raw("(SELECT COUNT(*) FROM users WHERE users.department_id = departments.id AND users.deleted_at IS NOT NULL) AS users_count_deleted"),
            DB::raw("(SELECT COUNT(*) FROM user_phones up
            JOIN users u ON u.id = up.user_id
            WHERE u.department_id = departments.id AND up.is_active = 1 AND u.deleted_at IS NULL) AS active_phones_count"),
            DB::raw("(SELECT COUNT(*) FROM message_groups mg
            JOIN user_phones up2 ON up2.id = mg.user_phone_id
            JOIN users u2 ON u2.id = up2.user_id
            WHERE u2.department_id = departments.id) AS message_groups_count"),
            DB::raw("(SELECT COUNT(*) FROM telegram_messages tm
            JOIN message_groups mg2 ON mg2.id = tm.message_group_id
            JOIN user_phones up3 ON up3.id = mg2.user_phone_id
            JOIN users u3 ON u3.id = up3.user_id
            WHERE u3.department_id = departments.id) AS telegram_messages_count"),
            DB::raw("(SELECT COUNT(*) FROM bans WHERE bans.bannable_type = '{$bannableType}' AND bans.bannable_id = departments.id AND bans.active = 1) AS bans_active_count"),
            DB::raw("(SELECT reason FROM bans WHERE bans.bannable_type = '{$bannableType}' AND bans.bannable_id = departments.id ORDER BY id DESC LIMIT 1) AS ban_reason"),
        ];
    }
    protected function buildDepartmentsQuery(string $plan, bool $onlyNonDeleted = true, $type)
    {
        $bannableType = addslashes(\App\Models\Department::class);

        $query = DB::table('departments')
            ->where('plan', $plan)
            ->where('type', $type)
            ->select('departments.*')
            ->addSelect($this->baseDepartmentSelects($bannableType))
            // =================== BIRINCHI USER ===================
            ->addSelect([
                'first_user_id' => DB::table('users')
                    ->select('id')
                    ->whereColumn('department_id', 'departments.id')
                    ->orderBy('id', 'asc')
                    ->limit(1),
                'first_user_name' => DB::table('users')
                    ->select('name')
                    ->whereColumn('department_id', 'departments.id')
                    ->orderBy('id', 'asc')
                    ->limit(1),
            ]);

        if ($onlyNonDeleted) {
            $query->whereNull('departments.deleted_at');
        }

        return $query;
    }
    public function upgrade(Request $request, $departmentId)
    {
        $user = $request->user();

        if ($user->role->name !== 'superadmin') {
            abort(403);
        }

        $dept = Department::findOrFail($departmentId);

        $banOld = null;
        if ($dept->ban) {
            $banOld = [
                'active' => $dept->ban->active,
                'starts_at' => $dept->ban->starts_at,
                'until' => $dept->ban->until,
            ];
        }

        $planOld = $dept->plan;
        $dept->update([
            'plan' => 'pro',
        ]);

        if ($dept->ban) {
            $dept->ban->update([
                'active' => false,
                'starts_at' => null,
                'until' => null,
            ]);
        }

        AuditLog::create([
            'type' => 'trial',
            'action' => 'upgraded',
            'subject_type' => Department::class,
            'subject_id' => $dept->id,
            'causer_type' => User::class,
            'causer_id' => Auth::id(),
            'changes' => [
                'department' => [
                    'old' => ['plan' => $planOld],
                    'new' => ['plan' => $dept->plan],
                ],
                // 'ban' => $dept->ban ? [
                //     'old' => $banOld,
                //     'new' => [
                //         'active' => $dept->ban->active,
                //         'starts_at' => $dept->ban->starts_at,
                //         'until' => $dept->ban->until,
                //     ],
                // ] : null,
            ],
        ]);

        return redirect()
            ->back()
            ->with('success', __('messages.departments.upgraded_success'));
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'plan' => 'nullable|in:pro,trial',
            'ban_starts_at' => 'nullable|date',
        ]);

        $plan = $validated['plan'] ?? 'pro';

        $now = Carbon::now();
        $expires = $now->copy()->addDays(7);

        $dept = Department::create([
            'name' => $validated['name'] ?? 'Workspace',
            'plan' => $plan,
            'trial_started_at' => $now,
            'trial_expires_at' => $expires,
        ]);
        $auth = Auth::user();
        if ($plan == 'trial') {
            $deptBan = Ban::create([
                'bannable_type' => Department::class,
                'bannable_id'   => $dept->id,
                'reason'        => 'trial_expired',
                'active'        => false,
                'starts_at'     => $expires,
                'until'         => null,
            ]);

            AuditLog::create([
                'type' => 'trial',
                'action' => 'created',
                'subject_type' => Department::class,
                'subject_id' => $dept->id,
                'causer_type' => User::class,
                'causer_id' => $auth->id,
                'changes' => [
                    'department' => [
                        'old' => null,
                        'new' => [
                            'id' => $dept->id,
                            'name' => $dept->name,
                            'plan' => $dept->plan,
                        ],
                    ],
                    'ban' => [
                        'old' => null,
                        'new' => [
                            'id' => $deptBan->id,
                            'active' => $deptBan->active,
                            'starts_at' => $deptBan->starts_at->format('Y-m-d H:i'),
                        ],
                    ],
                ],
            ]);
        } else {
            $startsAt = $validated['ban_starts_at'] ? Carbon::parse($validated['ban_starts_at']) : null;
            $deptBan = Ban::create([
                'bannable_type' => Department::class,
                'bannable_id'   => $dept->id,
                'reason'        => 'plan',
                'active'        => false,
                'starts_at'     => $startsAt,
                'until'         => null,
            ]);
            AuditLog::create([
                'type' => 'pro',
                'action' => 'created',
                'subject_type' => Department::class,
                'subject_id' => $dept->id,
                'causer_type' => User::class,
                'causer_id' => $auth->id,
                'changes' => [
                    'department' => [
                        'old' => null,
                        'new' => [
                            'id' => $dept->id,
                            'name' => $dept->name,
                            'plan' => $dept->plan,
                        ],
                    ],
                    'ban' => [
                        'old' => null,
                        'new' => [
                            'id' => $deptBan->id,
                            'active' => $deptBan->active,
                            'starts_at' => $deptBan->starts_at->format('Y-m-d H:i'),
                        ],
                    ],
                ],
            ]);
            AuditLog::create([
                'type' => 'subscription',
                'action' => 'created',
                'subject_type' => Department::class,
                'subject_id' => $dept->id,
                'causer_type' => User::class,
                'causer_id' => $auth->id,
                'changes' => [
                    'ban' => [
                        'old' => null,
                        'new' => [
                            'id' => $deptBan->id,
                            'active' => $deptBan->active,
                            'starts_at' => $deptBan->starts_at->format('Y-m-d H:i'),
                        ],
                    ],
                ],
            ]);
        }


        return redirect()->back()->with('success', __('messages.departments.created_success'));
    }
    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name,' . $department->id,
        ]);

        $old = [
            'name' => $department->name,
        ];

        $department->update($data);

        $auth = Auth::user();
        AuditLog::create([
            'type' => $department->plan,
            'action' => 'updated',
            'subject_type' => Department::class,
            'subject_id' => $department->id,
            'causer_type' => User::class,
            'causer_id' => $auth->id,
            'changes' => [
                'department' => [
                    'old' => $old,
                    'new' => [
                        'name' => $department->name,
                    ],
                ],
            ],
        ]);

        return redirect()->back()->with('success', 'Department updated successfully');
    }
    public function destroy(Department $department)
    {
        $auth = Auth::user();

        $old = [
            'id' => $department->id,
            'name' => $department->name,
            'plan' => $department->plan,
        ];

        $department->delete();

        AuditLog::create([
            'type' => $old['plan'],
            'action' => 'deleted',
            'subject_type' => Department::class,
            'subject_id' => $old['id'],
            'causer_type' => User::class,
            'causer_id' => $auth->id,
            'changes' => [
                'department' => [
                    'old' => $old,
                    'new' => null,
                ],
            ],
        ]);
        return redirect()->back()->with('success', 'Department o‘chirildi');
    }
    public function show(Request $request, $id)
    {
        $auth = $request->user();

        $department = Department::find($id);
        if (!$department) abort(404);

        $roleName = $auth->role->name ?? 'user';
        $isSuperadmin = $roleName === 'superadmin';
        $isAdmin = $roleName === 'admin';
        $isUser = $roleName === 'user';

        if (!$isSuperadmin && $auth->department_id !== $department->id) {
            abort(403);
        }

        $ban = DB::table('bans')
            ->where('bannable_type', Department::class)
            ->where('bannable_id', $department->id)
            ->orderByDesc('id')
            ->first();

        $banMeta = [
            'isBannedActive' => false,
            'isScheduled' => false,
            'startsAt' => null,
            'untilAt' => null,
        ];

        if ($ban) {
            $now = Carbon::now();
            $active = (int) ($ban->active ?? 0);
            $starts = $ban->starts_at ? Carbon::parse($ban->starts_at) : null;
            $until  = $ban->until ? Carbon::parse($ban->until) : null;

            if ($active === 1) {
                if ($until && $until->lte($now)) {
                    DB::table('bans')->where('id', $ban->id)->update(['active' => 0, 'updated_at' => $now]);
                    $active = 0;
                } else {
                    $banMeta['isBannedActive'] = true;
                    $banMeta['startsAt'] = $starts?->format('Y-m-d, H:i');
                    $banMeta['untilAt']  = $until?->format('Y-m-d, H:i');
                }
            }

            if ($active === 0 && $starts) {
                if ($starts->lte($now)) {
                    DB::table('bans')->where('id', $ban->id)->update(['active' => 1, 'updated_at' => $now]);
                    $banMeta['isBannedActive'] = true;
                    $banMeta['startsAt'] = $starts->format('Y-m-d, H:i');
                    $banMeta['untilAt']  = $until?->format('Y-m-d, H:i');
                } else {
                    $banMeta['isScheduled'] = true;
                    $banMeta['startsAt'] = $starts->format('Y-m-d, H:i');
                    $banMeta['untilAt']  = $until?->format('Y-m-d, H:i');
                }
            }
        }

        // user uchun department count kerak emas
        $usersCount = $isUser
            ? null
            : (int) DB::table('users')->where('department_id', $department->id)->count();

        // user yoki department bo‘yicha phone ids
        $phoneIdsQuery = function () use ($department, $auth, $isUser) {
            $query = DB::table('user_phones')->select('user_phones.id'); // <--- user_phones.id aniq belgilandi

            if ($isUser) {
                return $query->where('user_id', $auth->id);
            }

            return $query
                ->join('users', 'users.id', '=', 'user_phones.user_id')
                ->where('users.department_id', $department->id);
        };

        $messageGroupsCount = (int) DB::table('message_groups')
            ->whereIn('user_phone_id', $phoneIdsQuery())
            ->where('status', 'pending')
            ->count();

        $telegramMessagesCount = (int) DB::table('telegram_messages')
            ->whereIn('message_group_id', function ($q) use ($phoneIdsQuery) {
                $q->select('message_groups.id')
                    ->from('message_groups')
                    ->whereIn('message_groups.user_phone_id', $phoneIdsQuery());
            })
            ->count();

        $users = User::where('department_id', $department->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $permissions = [
            'canManageDepartment' => $isSuperadmin,
            'canBanDepartment'    => $isSuperadmin,
            'canAddUser'          => $isSuperadmin || $isAdmin,
            'canEditUser'         => $isSuperadmin || $isAdmin,
            'canDeleteUser'       => $isSuperadmin || $isAdmin,
            'canAssignRole'       => $isSuperadmin || $isAdmin,
            'isSuperadmin'        => $isSuperadmin,
            'isAdmin'             => $isAdmin,
            'isUser'              => $isUser,
        ];

        // scheduled ban activation fix
        if ($department->ban && $department->ban->active == 0 && $department->ban->starts_at && $department->ban->starts_at < now()) {
            $department->ban->active = 1;
            $department->ban->save();
        }

        $q = trim((string) $request->get('q', ''));
        $status = $request->get('status', null);
        $from = $request->get('from', null);
        $to = $request->get('to', null);
        $selectedUserId = $request->get('user_id', null);

        $usersForFilter = $isUser ? collect() : $users;

        $base = MessageGroup::whereIn('user_phone_id', $phoneIdsQuery())
            ->where('status', 'pending');

        if ($q !== '') {
            $base->where('message_groups.message_text', 'like', "%{$q}%");
        }

        if ($status) {
            $base->whereExists(function ($sub) use ($status) {
                $sub->selectRaw(1)
                    ->from('telegram_messages')
                    ->whereColumn('telegram_messages.message_group_id', 'message_groups.id')
                    ->where('telegram_messages.status', $status);
            });
        }

        if ($from || $to) {
            $base->whereExists(function ($sub) use ($from, $to) {
                $sub->selectRaw(1)
                    ->from('telegram_messages')
                    ->whereColumn('telegram_messages.message_group_id', 'message_groups.id');

                if ($from) $sub->where('telegram_messages.sent_at', '>=', Carbon::parse($from)->startOfDay());
                if ($to) $sub->where('telegram_messages.sent_at', '<=', Carbon::parse($to)->endOfDay());
            });
        }

        $perPage = (int) $request->get('per_page', 25);
        $page = (int) $request->get('page', 1);

        $messageGroups = $base->with([
            'phone:id,user_id,phone',
            'phone.user:id,name',
            'catalogs:id,title',
        ])
            ->orderByDesc('message_groups.id')
            ->paginate($perPage, ['*'], 'page', $page);

        $groupIds = $messageGroups->pluck('id')->toArray();

        $rawStats = collect();
        if (!empty($groupIds)) {
            $rawStats = DB::table('telegram_messages')
                ->whereIn('message_group_id', $groupIds)
                ->select(
                    'message_group_id',
                    DB::raw('COUNT(*) as total_messages'),
                    DB::raw('MIN(sent_at) as started_at'),
                    DB::raw('MAX(sent_at) as ended_at')
                )
                ->groupBy('message_group_id')
                ->get()
                ->keyBy('message_group_id');
        }

        $statusRows = collect();
        if (!empty($groupIds)) {
            $statusRows = DB::table('telegram_messages')
                ->whereIn('message_group_id', $groupIds)
                ->select('message_group_id', 'status', DB::raw('COUNT(*) as cnt'))
                ->groupBy('message_group_id', 'status')
                ->get();
        }

        $countsByGroup = [];
        foreach ($statusRows as $r) {
            $gid = $r->message_group_id;
            $countsByGroup[$gid][$r->status] = (int) $r->cnt;
        }

        $operations = $messageGroups->getCollection()->map(function ($group) use ($rawStats, $countsByGroup) {
            $gid = $group->id;
            $phone = $group->phone;
            $user = $phone->user ?? null;
            $catalogs = $group->catalogs->pluck('title')->values()->all();
            $raw = $rawStats[$gid] ?? null;

            $counts = [
                'sent'      => (int) ($countsByGroup[$gid]['sent'] ?? 0),
                'failed'    => (int) ($countsByGroup[$gid]['failed'] ?? 0),
                'canceled'  => (int) ($countsByGroup[$gid]['canceled'] ?? 0),
                'scheduled' => (int) ($countsByGroup[$gid]['scheduled'] ?? 0),
                'pending'   => (int) ($countsByGroup[$gid]['pending'] ?? 0),
            ];

            return (object) [
                'id' => $gid,
                'phone_id' => $phone->id ?? null,
                'phone' => $phone->phone ?? null,
                'user_id' => $user->id ?? null,
                'user_name' => $user->name ?? null,
                'message_text' => $group->message_text,
                'catalogs' => $catalogs,
                'totals' => [
                    'total_messages' => (int) ($raw->total_messages ?? 0),
                    'counts_by_status' => $counts,
                ],
                'started_at' => $raw->started_at ?? null,
                'ended_at' => $raw->ended_at ?? null,
                'group_status' => $group->status,
            ];
        })->toArray();

        $messageGroupsTotal = (int) MessageGroup::whereIn('user_phone_id', $phoneIdsQuery())->count();

        $telegramMessagesTotal = (int) DB::table('telegram_messages')
            ->whereIn('message_group_id', function ($q) use ($phoneIdsQuery) {
                $q->select('id')
                    ->from('message_groups')
                    ->whereIn('user_phone_id', $phoneIdsQuery());
            })
            ->count();

        return view('pages.general.departments.show', [
            'department' => $department,
            'users' => $users,
            'usersCount' => $usersCount,
            'messageGroupsCount' => $messageGroupsCount,
            'telegramMessagesCount' => $telegramMessagesCount,
            'ban' => $ban,
            'banMeta' => $banMeta,
            'permissions' => $permissions,

            'operations' => $operations,
            'operations_meta' => [
                'current_page' => $messageGroups->currentPage(),
                'last_page' => $messageGroups->lastPage(),
                'per_page' => $messageGroups->perPage(),
                'total' => $messageGroups->total(),
            ],
            'totals' => [
                'message_groups_total' => $messageGroupsTotal,
                'telegram_messages_total' => $telegramMessagesTotal,
            ],
            'usersForFilter' => $usersForFilter,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'from' => $from,
                'to' => $to,
                'selected_user_id' => $selectedUserId,
            ],
        ]);
    }
    public function users(Request $request, $id = null)
    {
        $auth = $request->user();
        $roleName = $auth->role->name ?? 'user';
        $isSuperadmin = $roleName === 'superadmin';
        $isAdmin = $roleName === 'admin';
        $isUser = $roleName === 'user';

        $department = $isSuperadmin
            ? Department::with('users.phones')->findOrFail($id)
            : $auth->department;

        if (!$department) abort(404);

        if (!$isSuperadmin && !$isAdmin) {
            abort(403);
        }

        $departmentBan = DB::table('bans')
            ->where('bannable_type', Department::class)
            ->where('bannable_id', $department->id)
            ->where(function ($query) {
                $query->where('active', true)
                    ->orWhere('starts_at', '<', now());
            })
            ->first();

        if (!$isSuperadmin && ($departmentBan)) {
            return redirect('/');
        }

        if (!$isSuperadmin && $auth->department_id !== $department->id) {
            abort(403);
        }
        $users = User::withTrashed()
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.department_id', $department->id)
            ->when(!$isSuperadmin, function ($q) {
                $q->whereNull('users.deleted_at');
            })
            ->select(
                'users.id',
                'users.name',
                'users.telegram_id',
                'users.email',
                'users.role_id',
                'roles.name as role_name',
                'users.deleted_at'
            )
            ->orderByDesc('users.id')
            ->with('avatar')  // ← AVATAR RELATION SHU YERDA QO'SHILDI (profile namunasiga to'liq mos)
            ->get();
        // dd($users->toArray());

        $userIds = $users->pluck('id')->toArray();

        $phonesQuery = DB::table('user_phones')
            ->whereIn('user_id', $userIds)
            ->select('id', 'user_id', 'phone', 'is_active')
            ->get();

        $phones = $phonesQuery->groupBy('user_id');

        $allPhoneIds = $phonesQuery->pluck('id')->toArray();

        // user bans
        $userBans = DB::table('bans')
            ->where('bannable_type', User::class)
            ->whereIn('bannable_id', $userIds)
            ->get()
            ->keyBy('bannable_id');

        // phone bans (if your bannable_type for phones is App\Models\UserPhone)
        $phoneBans = collect();
        if (!empty($allPhoneIds)) {
            $phoneBans = DB::table('bans')
                ->where('bannable_type', 'App\Models\UserPhone')
                ->whereIn('bannable_id', $allPhoneIds)
                ->get()
                ->keyBy('bannable_id');
        }

        // roles (id => name) — useful if you need mapping
        $roles = DB::table('roles')->pluck('name', 'id');

        // Prepare user objects for frontend (attach phones, ban info, role_name)
        // avatar allaqachon $u->avatar da (Eloquent with orqali)
        $usersPrepared = $users->map(function ($u) use ($phones, $userBans, $phoneBans, $roles) {
            $u->phones = $phones[$u->id] ?? collect([]);

            $banRow = $userBans[$u->id] ?? null;
            $u->is_banned = (bool) ($banRow->active ?? false);
            $u->ban = $banRow;

            // attach ban info to each phone
            $u->phones = collect($u->phones)->map(function ($ph) use ($phoneBans) {
                $phBan = $phoneBans[$ph->id] ?? null;
                $ph->is_banned = (bool) ($phBan->active ?? false);
                $ph->ban = $phBan;
                return $ph;
            });

            $u->role_name = $roles[$u->role_id] ?? ($u->role_name ?? null);
            return $u;
        });

        // Admin limit logic (if current user is admin, check their limit)
        $adminLimit = null;
        $adminLimitReached = false;
        $adminRemainingSlots = null;
        $deletesNeeded = 0; // yangi: qancha o'chirish kerak bo'ladi

        if ($isAdmin) {
            $limit = $auth->limit ?? null;
            if ($limit) {
                $adminLimit = [
                    'max_users' => (int) ($limit->max_users ?? 0),
                    'max_phones' => (int) ($limit->max_phones ?? 0),
                    'max_operations' => (int) ($limit->max_operations ?? 0),
                ];

                // current users in department
                $currentUsersCount = count($userIds);

                $adminRemainingSlots = $adminLimit['max_users'] - $currentUsersCount;

                if ($adminRemainingSlots <= 0) {
                    $adminLimitReached = true;
                    $deletesNeeded = ($currentUsersCount + 1) - $adminLimit['max_users'];
                    if ($deletesNeeded < 0) $deletesNeeded = 0;
                    $adminRemainingSlots = 0;
                } else {
                    $adminLimitReached = false;
                    $deletesNeeded = 0;
                }
            }
        }


        $permissions = [
            'canViewUser' => true,
            'canAddUser' => $isSuperadmin || ($isAdmin && !$adminLimitReached),
            'canEditUser' => $isSuperadmin || $isAdmin,
            'canDeleteUser' => $isSuperadmin || $isAdmin,
            'canAssignRole' => $isSuperadmin || $isAdmin,
            'isSuperadmin' => $isSuperadmin,
            'isAdmin' => $isAdmin,
            'isUser' => $isUser,
        ];
        $roles = Role::where('name', '!=', 'superadmin')->get();
        return view('pages.general.departments.users-list', [
            'roles' => $roles,
            'department' => $department,
            'users' => $usersPrepared,
            'permissions' => $permissions,
            'adminLimit' => $adminLimit,
            'adminLimitReached' => $adminLimitReached,
            'adminRemainingSlots' => $adminRemainingSlots,
        ]);
    }
    public function history(Request $request, $id = null)
    {
        $auth = $request->user();
        $roleName = $auth->role?->name ?? 'user';

        $isSuperadmin = $roleName === 'superadmin';
        $isAdmin      = $roleName === 'admin';
        $isUser       = $roleName === 'user';

        $department = $isSuperadmin
            ? Department::with('users.phones')->findOrFail($id)
            : $auth->department;

        if (!$department) {
            abort(404);
        }

        if (!$isSuperadmin && $auth->department_id !== $department->id) {
            abort(403);
        }

        $departmentBan = DB::table('bans')
            ->where('bannable_type', Department::class)
            ->where('bannable_id', $department->id)
            ->where(function ($query) {
                $query->where('active', true)
                    ->orWhere('starts_at', '<', now());
            })
            ->first();

        if (!$isSuperadmin && $departmentBan) {
            return redirect('/');
        }

        /**
         * Superadmin/Admin -> department ichidagi barcha user phone’lar
         * User -> faqat o‘zining phone’lari
         */
        $phoneSub = function ($qsub) use ($department, $auth, $isUser) {
            $qsub->select('user_phones.id')
                ->from('user_phones');

            if ($isUser) {
                $qsub->where('user_phones.user_id', $auth->id);
                return;
            }

            $qsub->join('users', 'users.id', '=', 'user_phones.user_id')
                ->where('users.department_id', $department->id);
        };

        $usersCount = $isUser
            ? null
            : (int) DB::table('users')->where('department_id', $department->id)->count();

        $messageGroupsCount = (int) DB::table('message_groups')
            ->whereIn('user_phone_id', $phoneSub)
            ->whereNot('status', 'pending')
            ->count();

        $telegramMessagesCount = (int) DB::table('telegram_messages')
            ->whereIn('message_group_id', function ($q) use ($phoneSub) {
                $q->select('message_groups.id')
                    ->from('message_groups')
                    ->whereIn('message_groups.user_phone_id', $phoneSub)->whereNot('status', 'pending');
            })
            ->count();

        $sentMessagesCount = (int) DB::table('telegram_messages')
            ->whereIn('message_group_id', function ($q) use ($phoneSub) {
                $q->select('message_groups.id')
                    ->from('message_groups')
                    ->whereIn('message_groups.user_phone_id', $phoneSub)->whereNot('status', 'pending');
            })
            ->where('status', 'sent')
            ->count();

        $usersForFilter = $isUser
            ? User::where('id', $auth->id)->select('id', 'name')->get()
            : User::where('department_id', $department->id)->select('id', 'name')->orderBy('name')->get();

        // Unique statuses for filter
        $statuses = DB::table('message_groups')
            ->whereIn('user_phone_id', $phoneSub)
            ->whereNot('status', 'pending')
            ->select('message_groups.status')
            ->distinct()
            ->pluck('status')
            ->filter()
            ->toArray();

        $permissions = [
            'isSuperadmin' => $isSuperadmin,
            'isAdmin'      => $isAdmin,
            'isUser'       => $isUser,
        ];

        // Filters
        $q = trim((string) $request->get('q', ''));
        $status = $request->get('status', null);
        $from = $request->get('from', null);
        $to = $request->get('to', null);
        $selectedUserId = $request->get('user_id', null);

        $base = MessageGroup::whereIn('user_phone_id', $phoneSub)->where('status', '<>', 'pending');

        if ($q !== '') {
            $base->where('message_groups.message_text', 'like', "%{$q}%");
        }

        if ($status) {
            $base->where('message_groups.status', $status);
        }

        if ($from || $to) {
            $base->whereExists(function ($sub) use ($from, $to) {
                $sub->selectRaw(1)
                    ->from('telegram_messages')
                    ->whereColumn('telegram_messages.message_group_id', 'message_groups.id');

                if ($from) {
                    $sub->where('telegram_messages.sent_at', '>=', Carbon::parse($from)->startOfDay());
                }

                if ($to) {
                    $sub->where('telegram_messages.sent_at', '<=', Carbon::parse($to)->endOfDay());
                }
            });
        }

        // user uchun filterdagi selected user faqat o‘zi bo‘lishi kerak
        if ($isUser) {
            $selectedUserId = $auth->id;
        }

        $perPage = (int) $request->get('per_page', 25);
        $page = (int) $request->get('page', 1);

        $messageGroups = $base->with([
            'phone:id,user_id,phone',
            'phone.user:id,name',
            'catalogs:id,title',
        ])
            ->orderByDesc('message_groups.id')
            ->paginate($perPage, ['*'], 'page', $page);

        $groupIds = $messageGroups->pluck('id')->toArray();

        $rawStats = collect();
        if (!empty($groupIds)) {
            $rawStats = DB::table('telegram_messages')
                ->whereIn('message_group_id', $groupIds)
                ->select(
                    'message_group_id',
                    DB::raw('COUNT(*) as total_messages'),
                    DB::raw('MIN(sent_at) as started_at'),
                    DB::raw('MAX(sent_at) as ended_at')
                )
                ->groupBy('message_group_id')
                ->get()
                ->keyBy('message_group_id');
        }

        $statusRows = collect();
        if (!empty($groupIds)) {
            $statusRows = DB::table('telegram_messages')
                ->whereIn('message_group_id', $groupIds)
                ->select('message_group_id', 'status', DB::raw('COUNT(*) as cnt'))
                ->groupBy('message_group_id', 'status')
                ->get();
        }

        $countsByGroup = [];
        foreach ($statusRows as $r) {
            $gid = $r->message_group_id;
            $countsByGroup[$gid][$r->status] = (int) $r->cnt;
        }

        $operations = $messageGroups->getCollection()->map(function ($group) use ($rawStats, $countsByGroup) {
            $gid = $group->id;
            $phone = $group->phone;
            $user = $phone->user ?? null;
            $catalogs = $group->catalogs->pluck('title')->values()->all();
            $raw = $rawStats[$gid] ?? null;

            $counts = [
                'sent'       => (int) ($countsByGroup[$gid]['sent'] ?? 0),
                'failed'     => (int) ($countsByGroup[$gid]['failed'] ?? 0),
                'canceled'   => (int) ($countsByGroup[$gid]['canceled'] ?? 0),
                'scheduled'  => (int) ($countsByGroup[$gid]['scheduled'] ?? 0),
                'pending'    => (int) ($countsByGroup[$gid]['pending'] ?? 0),
                'processing' => (int) ($countsByGroup[$gid]['processing'] ?? 0),
            ];

            return (object) [
                'id' => $gid,
                'phone_id' => $phone->id ?? null,
                'phone' => $phone->phone ?? null,
                'user_id' => $user->id ?? null,
                'user_name' => $user->name ?? null,
                'message_text' => $group->message_text,
                'catalogs' => $catalogs,
                'totals' => [
                    'total_messages' => (int) ($raw->total_messages ?? 0),
                    'counts_by_status' => $counts,
                ],
                'started_at' => $raw->started_at ?? null,
                'ended_at' => $raw->ended_at ?? null,
                'group_status' => $group->status,
            ];
        })->toArray();

        return view('pages.general.departments.history', [
            'department' => $department,
            'usersCount' => $usersCount,
            'messageGroupsCount' => $messageGroupsCount,
            'telegramMessagesCount' => $telegramMessagesCount,
            'sentMessagesCount' => $sentMessagesCount,
            'permissions' => $permissions,
            'operations' => $operations,
            'operations_meta' => [
                'current_page' => $messageGroups->currentPage(),
                'last_page' => $messageGroups->lastPage(),
                'per_page' => $messageGroups->perPage(),
                'total' => $messageGroups->total(),
            ],
            'usersForFilter' => $usersForFilter,
            'statuses' => $statuses,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'from' => $from,
                'to' => $to,
                'selected_user_id' => $selectedUserId,
            ],
        ]);
    }
}
