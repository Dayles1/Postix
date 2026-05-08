<?php

namespace App\Http\Controllers\View\Department;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserDepartmenIndexController extends Controller
{
    public function freeUsers(Request $request)
    {
        return $this->indexByFirstUserBan($request, false);
    }
    public function freeUsersBanned(Request $request)
    {
        return $this->indexByFirstUserBan($request, true);
    }
    protected function indexByFirstUserBan(Request $request, bool $onlyBanned)
    {
        $user = $request->user();

        if (($user->role->name ?? null) !== 'superadmin') {
            return redirect()->action(
                [\App\Http\Controllers\View\Department\DepartmentController::class, 'show'],
                ['id' => $user->department_id]
            );
        }

        $search = $request->get('search');
        $sort = $request->get('sort', 'desc');
        $bannedFilter = $request->get('banned', 'all');

        $query = DB::table('departments')
            ->where('departments.plan', 'trial')
            ->where('departments.type', 'user')
            ->whereNull('departments.deleted_at')
            ->select('departments.*')
            ->addSelect($this->baseDepartmentSelects())
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
            ])
            ->selectRaw(
                "COALESCE((
                    SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                    FROM bans b
                    WHERE b.bannable_type = ?
                      AND b.bannable_id = (
                          SELECT u.id
                          FROM users u
                          WHERE u.department_id = departments.id
                          ORDER BY u.id ASC
                          LIMIT 1
                      )
                      AND (
                          b.active = 1
                          OR (b.starts_at IS NOT NULL AND b.starts_at < NOW())
                      )
                ), 0) AS first_user_ban_active",
                [User::class]
            );

        if ($search) {
            $query->where('departments.name', 'like', '%' . $search . '%');
        }

        if ($onlyBanned || $bannedFilter === 'banned') {
            $query->whereRaw(
                "COALESCE((
                    SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                    FROM bans b
                    WHERE b.bannable_type = ?
                      AND b.bannable_id = (
                          SELECT u.id
                          FROM users u
                          WHERE u.department_id = departments.id
                          ORDER BY u.id ASC
                          LIMIT 1
                      )
                      AND (
                          b.active = 1
                          OR (b.starts_at IS NOT NULL AND b.starts_at < NOW())
                      )
                ), 0) = 1",
                [User::class]
            );
        } elseif ($bannedFilter === 'not_banned') {
            $query->whereRaw(
                "COALESCE((
                    SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                    FROM bans b
                    WHERE b.bannable_type = ?
                      AND b.bannable_id = (
                          SELECT u.id
                          FROM users u
                          WHERE u.department_id = departments.id
                          ORDER BY u.id ASC
                          LIMIT 1
                      )
                      AND (
                          b.active = 1
                          OR (b.starts_at IS NOT NULL AND b.starts_at < NOW())
                      )
                ), 0) = 0",
                [User::class]
            );
        }

        $deptStats = $query
            ->orderBy('departments.created_at', $sort)
            ->paginate(18)
            ->withQueryString();

        $deptStats->getCollection()->transform(function ($dept) {
            $dept->first_user_ban_active = (int) ($dept->first_user_ban_active ?? 0) === 1;
            return $dept;
        });

        $plan = 'trial';

        return view('pages.superadmin.department.user-departments', compact(
            'deptStats',
            'search',
            'sort',
            'bannedFilter',
            'plan'
        ));
    }
    protected function baseDepartmentSelects()
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
        ];
    }
    public function proUsers(Request $request)
    {
        return $this->indexByFirstUserBanForPro($request, false);
    }
    protected function indexByFirstUserBanForPro(Request $request, bool $onlyBanned)
    {
        $user = $request->user();

        if (($user->role->name ?? null) !== 'superadmin') {
            return redirect()->action(
                [\App\Http\Controllers\View\Department\DepartmentController::class, 'show'],
                ['id' => $user->department_id]
            );
        }

        $search = $request->get('search');
        $sort = $request->get('sort', 'desc');
        $bannedFilter = $request->get('banned', 'all');

        $query = DB::table('departments')
            ->where('departments.plan', 'pro')
            ->where('departments.type', 'user')
            ->whereNull('departments.deleted_at')
            ->select('departments.*')
            ->addSelect($this->baseDepartmentSelects())
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
            ])
            ->selectRaw(
                "COALESCE((
                    SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                    FROM bans b
                    WHERE b.bannable_type = ?
                      AND b.bannable_id = (
                          SELECT u.id
                          FROM users u
                          WHERE u.department_id = departments.id
                          ORDER BY u.id ASC
                          LIMIT 1
                      )
                      AND (
                          b.active = 1
                          OR (b.starts_at IS NOT NULL AND b.starts_at < NOW())
                      )
                ), 0) AS first_user_ban_active",
                [User::class]
            );

        if ($search) {
            $query->where('departments.name', 'like', '%' . $search . '%');
        }

        if ($onlyBanned || $bannedFilter === 'banned') {
            $query->whereRaw(
                "COALESCE((
                    SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                    FROM bans b
                    WHERE b.bannable_type = ?
                      AND b.bannable_id = (
                          SELECT u.id
                          FROM users u
                          WHERE u.department_id = departments.id
                          ORDER BY u.id ASC
                          LIMIT 1
                      )
                      AND (
                          b.active = 1
                          OR (b.starts_at IS NOT NULL AND b.starts_at < NOW())
                      )
                ), 0) = 1",
                [User::class]
            );
        } elseif ($bannedFilter === 'not_banned') {
            $query->whereRaw(
                "COALESCE((
                    SELECT CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END
                    FROM bans b
                    WHERE b.bannable_type = ?
                      AND b.bannable_id = (
                          SELECT u.id
                          FROM users u
                          WHERE u.department_id = departments.id
                          ORDER BY u.id ASC
                          LIMIT 1
                      )
                      AND (
                          b.active = 1
                          OR (b.starts_at IS NOT NULL AND b.starts_at < NOW())
                      )
                ), 0) = 0",
                [User::class]
            );
        }

        $deptStats = $query
            ->orderBy('departments.created_at', $sort)
            ->paginate(18)
            ->withQueryString();

        $deptStats->getCollection()->transform(function ($dept) {
            $dept->first_user_ban_active = (int) ($dept->first_user_ban_active ?? 0) === 1;
            return $dept;
        });

        $plan = 'pro';

        return view('pages.superadmin.department.user-departments', compact(
            'deptStats',
            'search',
            'sort',
            'bannedFilter',
            'plan'
        ));
    }
}