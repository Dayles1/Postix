<?php

namespace App\Http\Controllers\View;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ban;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BanController extends Controller
{
    public function banUnban(Request $request)
    {
        try {
            $authUser = $request->user();
            $role = $authUser->role->name ?? null;

            $data = $request->validate([
                'bannable_type' => ['required', 'string'],
                'bannable_id'   => ['required', 'integer'],
                'action'        => ['nullable', 'string', 'in:ban,unban,update'],
                'starts_at'     => ['nullable', 'date'],
                'until'         => ['nullable', 'date', 'after_or_equal:starts_at'],
            ]);

            $type = strtolower(trim($data['bannable_type']));
            $action = strtolower(trim($data['action'] ?? 'ban'));
            $id = (int) $data['bannable_id'];

            $startsAt = !empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : null;
            $untilAt = !empty($data['until']) ? Carbon::parse($data['until']) : null;

            if ($type === 'user') {
                return $this->handleUserBanUnban($request, $id, $action, $startsAt, $untilAt, $role);
            }

            if ($type === 'department') {
                return $this->handleDepartmentBanUnban($request, $id, $action, $startsAt, $untilAt, $role);
            }

            return $this->error2(__('messages.ban.invalid_type'), 422);
        } catch (\Throwable $e) {
            Log::error('BanController error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => optional($request->user())->id,
            ]);

            return $this->error2(__('messages.ban.internal_error'), 500);
        }
    }

    private function handleUserBanUnban(
        Request $request,
        int $id,
        string $action,
        ?Carbon $startsAt,
        ?Carbon $untilAt,
        ?string $role
    ) {
        $authUser = $request->user();

        if (!$this->canManageUserBan($authUser, $role, $id)) {
            return $this->error2(__('messages.ban.no_permission'), 403);
        }

        $user = User::with(['ban', 'department', 'role'])->find($id);

        if (!$user) {
            return $this->error2(__('messages.ban.not_found', ['model' => 'User']), 404);
        }

        $label = class_basename(User::class);
        $oldBan = $this->banSnapshot($user->ban);

        if ($action === 'unban') {
            if ($user->ban) {
                $user->ban->update([
                    'active' => false,
                    'starts_at' => null,
                    'until' => null,
                ]);
            }

            $newBan = $this->banSnapshot($user->ban?->fresh());

            if ($oldBan || $newBan) {
                $this->createAuditLog(
                    data_get($user, 'department.plan', 'free'),
                    'user_unban',
                    User::class,
                    $user->id,
                    [
                        'ban' => [
                            'old' => $oldBan,
                            'new' => $newBan,
                        ],
                    ],
                    $authUser
                );
            }
            if (data_get($user, 'department.plan') === 'pro') {
                $this->createAuditLog(
                    'subscription',
                    'user_unban',
                    User::class,
                    $user->id,
                    [
                        'ban' => [
                            'old' => $oldBan,
                            'new' => $newBan,
                        ],
                    ],
                    $authUser
                );
            }

            return $this->success2([
                'model' => $label,
                'is_banned' => false,
                'is_scheduled' => false,
                'starts_at' => null,
                'until' => null,
            ], __('messages.ban.unbanned', ['model' => $label]));
        }

        $now = Carbon::now();
        $isScheduled = $startsAt ? $startsAt->gt($now) : false;
        $isActiveNow = !$startsAt || $startsAt->lte($now);

        $ban = $user->ban()->updateOrCreate([], [
            'starts_at' => $startsAt ?? now(),
            'until' => $untilAt,
            'active' => $isActiveNow,
        ]);

        $newBan = $this->banSnapshot($ban);

        $this->createAuditLog(
            data_get($user, 'department.plan', 'free'),
            'user_ban',
            User::class,
            $user->id,
            [
                'ban' => [
                    'old' => $oldBan,
                    'new' => $newBan,
                ],
            ],
            $authUser
        );
        if (data_get($user, 'department.plan') === 'pro') {
            $this->createAuditLog(
                'subscription',
                $action === 'unban' ? 'user_unban' : 'user_ban',
                User::class,
                $user->id,
                [
                    'ban' => [
                        'old' => $oldBan,
                        'new' => $newBan,
                    ],
                ],
                $authUser
            );
        }

        return $this->success2([
            'model' => $label,
            'is_banned' => (bool) $ban->active,
            'is_scheduled' => (bool) $isScheduled,
            'starts_at' => $ban->starts_at?->format('Y-m-d H:i'),
            'until' => $ban->until?->format('Y-m-d H:i'),
        ], $isActiveNow
            ? __('messages.ban.banned_now', ['model' => $label])
            : __('messages.ban.scheduled', ['model' => $label]));
    }

    private function handleDepartmentBanUnban(
        Request $request,
        int $id,
        string $action,
        ?Carbon $startsAt,
        ?Carbon $untilAt,
        ?string $role
    ) {
        $authUser = $request->user();

        if ($role !== 'superadmin') {
            return $this->error2(__('messages.ban.no_permission'), 403);
        }

        $department = Department::with('ban')->find($id);

        if (!$department) {
            return $this->error2(__('messages.ban.not_found', ['model' => 'Department']), 404);
        }

        $label = class_basename(Department::class);
        $oldBan = $this->banSnapshot($department->ban);

        if ($action === 'unban') {
            if ($department->ban) {
                $department->ban->update([
                    'active' => false,
                    'starts_at' => null,
                    'until' => null,
                ]);
            }

            $newBan = $this->banSnapshot($department->ban?->fresh());

            $this->writeDepartmentLogs(
                $department,
                'department_unban',
                $oldBan,
                $newBan,
                $authUser
            );

            return $this->success2([
                'model' => $label,
                'is_banned' => false,
                'is_scheduled' => false,
                'starts_at' => null,
                'until' => null,
            ], __('messages.ban.unbanned', ['model' => $label]));
        }

        $now = Carbon::now();
        $isScheduled = $startsAt ? $startsAt->gt($now) : false;
        $isActiveNow = !$startsAt || $startsAt->lte($now);

        $ban = $department->ban()->updateOrCreate([], [
            'starts_at' => $startsAt,
            'until' => $untilAt,
            'active' => $isActiveNow,
        ]);

        $newBan = $this->banSnapshot($ban);

        $this->writeDepartmentLogs(
            $department,
            'department_ban',
            $oldBan,
            $newBan,
            $authUser
        );

        return $this->success2([
            'model' => $label,
            'is_banned' => (bool) $ban->active,
            'is_scheduled' => (bool) $isScheduled,
            'starts_at' => $ban->starts_at?->format('Y-m-d H:i'),
            'until' => $ban->until?->format('Y-m-d H:i'),
        ], $isActiveNow
            ? __('messages.ban.banned_now', ['model' => $label])
            : __('messages.ban.scheduled', ['model' => $label]));
    }

    private function canManageUserBan(User $authUser, ?string $role, int $targetUserId): bool
    {
        if (!in_array($role, ['superadmin', 'admin'], true)) {
            return false;
        }

        if ($role === 'admin') {
            $targetUser = User::with('role')->find($targetUserId);

            if (!$targetUser) {
                return false;
            }

            if (($targetUser->role->name ?? null) === 'admin') {
                return (int) $targetUser->created_by === (int) $authUser->id;
            }
        }

        return true;
    }

    private function writeDepartmentLogs(
        Department $department,
        string $action,
        ?array $oldBan,
        ?array $newBan,
        User $causer
    ): void {
        $baseType = $department->plan === 'trial' ? 'trial' : 'pro';

        $this->createAuditLog(
            $baseType,
            $action,
            Department::class,
            $department->id,
            [
                'ban' => [
                    'old' => $oldBan,
                    'new' => $newBan,
                ],
            ],
            $causer
        );

        if ($department->plan === 'pro') {
            $this->createAuditLog(
                'subscription',
                $action,
                Department::class,
                $department->id,
                [
                    'ban' => [
                        'old' => $oldBan,
                        'new' => $newBan,
                    ],
                ],
                $causer
            );
        }
    }

    private function createAuditLog(
        string $type,
        string $action,
        string $subjectType,
        int $subjectId,
        array $changes,
        ?User $causer = null
    ): void {
        AuditLog::create([
            'type' => $type,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'causer_type' => $causer ? User::class : null,
            'causer_id' => $causer?->id,
            'changes' => $changes,
        ]);
    }

    private function banSnapshot(?Ban $ban): ?array
    {
        if (!$ban) {
            return null;
        }

        return [
            'id' => $ban->id,
            'active' => (bool) $ban->active,
            'starts_at' => $ban->starts_at?->format('Y-m-d H:i:s'),
            'until' => $ban->until?->format('Y-m-d H:i:s'),
        ];
    }

    protected function error2(string $message = 'An error occurred', int $status = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    protected function success2($data = [], string $message = 'Operation successful', int $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}