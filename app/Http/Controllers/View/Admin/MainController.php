<?php

namespace App\Http\Controllers\View\Admin;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Department;
use App\Models\MessageGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class MainController extends Controller
{


    public function index(Request $request, $id)
    {
        $department = Department::findOrFail($id);
        $user = $request->user();
        $this->permissonCheck($user, $department);

        // default monthly range for dashboard
        $range = $request->get('range', 'month');

        /** FAST COUNTS (scope to range when applicable) */
        $now = Carbon::now();

        if ($range === 'day') {
            $start = $now->copy()->subDay()->startOfHour();
            $intervalSpec = 'PT1H';
            $sqlFormat = '%Y-%m-%d %H:00';
            $labelType = 'hour';
        } elseif ($range === 'month') {
            $start = $now->copy()->subMonth()->startOfDay();
            $intervalSpec = 'P1D';
            $sqlFormat = '%Y-%m-%d';
            $labelType = 'day';
        } elseif ($range === 'all') {
            $minDate = DB::table('telegram_messages')
                ->join('message_groups', 'message_groups.id', '=', 'telegram_messages.message_group_id')
                ->join('user_phones', 'user_phones.id', '=', 'message_groups.user_phone_id')
                ->join('users', 'users.id', '=', 'user_phones.user_id')
                ->where('users.department_id', $department->id)
                ->min('telegram_messages.sent_at');

            $start = $minDate ? Carbon::parse($minDate)->startOfMonth() : $now->copy()->subYear()->startOfMonth();
            $intervalSpec = 'P1M';
            $sqlFormat = '%Y-%m';
            $labelType = 'month';
        } else { // year
            $start = $now->copy()->subYear()->startOfMonth();
            $intervalSpec = 'P1M';
            $sqlFormat = '%Y-%m';
            $labelType = 'month';
        }

        $end = $now->copy();
        $periodEnd = $end->copy()->addDay();

        // users count (department-level, stable)
        $usersCount = $department->users()->count();

        // active phones count — restricted to messages after $start is optional.
        $activePhonesCount = DB::table('user_phones')
            ->join('users', 'users.id', '=', 'user_phones.user_id')
            ->where('users.department_id', $department->id)
            ->where('user_phones.is_active', 1)
            ->count();

        // message groups count — INCLUDE only groups that have messages within the selected range
        $messageGroupsCount = DB::table('message_groups')
            ->whereIn('user_phone_id', function ($q) use ($department) {
                $q->select('user_phones.id')
                    ->from('user_phones')
                    ->join('users', 'users.id', '=', 'user_phones.user_id')
                    ->where('users.department_id', $department->id);
            })
            ->whereExists(function ($sq) use ($start) {
                $sq->selectRaw(1)
                    ->from('telegram_messages')
                    ->whereColumn('telegram_messages.message_group_id', 'message_groups.id')
                    ->where('telegram_messages.sent_at', '>=', $start);
            })
            ->count();

        // telegram messages count within range
        $telegramMessagesCount = DB::table('telegram_messages')
            ->join('message_groups', 'message_groups.id', '=', 'telegram_messages.message_group_id')
            ->join('user_phones', 'user_phones.id', '=', 'message_groups.user_phone_id')
            ->join('users', 'users.id', '=', 'user_phones.user_id')
            ->where('users.department_id', $department->id)
            ->where('telegram_messages.sent_at', '>=', $start)
            ->count();

        /** LAST ACTIVE USERS */
        $lastActiveUsers = User::with('avatar')
            ->where('department_id', $department->id)
            ->whereHas('phones.messageGroups.messages', function ($q) use ($start) {
                $q->where('telegram_messages.sent_at', '>=', $start);
            })
            ->select('users.id', 'users.name')
            ->selectRaw('MAX(telegram_messages.sent_at) as last_active')
            ->selectRaw('COUNT(telegram_messages.id) as ops_count')
            ->join('user_phones', 'user_phones.user_id', '=', 'users.id')
            ->join('message_groups', 'message_groups.user_phone_id', '=', 'user_phones.id')
            ->join('telegram_messages', 'telegram_messages.message_group_id', '=', 'message_groups.id')
            ->where('telegram_messages.sent_at', '>=', $start)
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('last_active')
            ->limit(5)
            ->get();

        /** MESSAGES PER PERIOD (line) */
        $rawPerPeriod = DB::table('telegram_messages')
            ->join('message_groups', 'message_groups.id', '=', 'telegram_messages.message_group_id')
            ->join('user_phones', 'user_phones.id', '=', 'message_groups.user_phone_id')
            ->join('users', 'users.id', '=', 'user_phones.user_id')
            ->where('users.department_id', $department->id)
            ->where('telegram_messages.sent_at', '>=', $start)
            ->select(DB::raw("DATE_FORMAT(telegram_messages.sent_at, '{$sqlFormat}') as period"), DB::raw("COUNT(*) as cnt"))
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('cnt', 'period');

        // build labels sequence
        $iterator = new \DatePeriod($start, new \DateInterval($intervalSpec), $periodEnd);
        $messagesPerDayLabels = [];
        $messagesPerDayValues = [];
        foreach ($iterator as $dt) {
            if ($labelType === 'month') {
                $key = $dt->format('Y-m');
                $label = $dt->format('M Y');
            } elseif ($labelType === 'day') {
                $key = $dt->format('Y-m-d');
                $label = $dt->format('Y-m-d');
            } else {
                $key = $dt->format('Y-m-d H:00');
                $label = $dt->format('d H:00');
            }
            $messagesPerDayLabels[] = $label;
            $messagesPerDayValues[] = isset($rawPerPeriod[$key]) ? (int)$rawPerPeriod[$key] : 0;
        }

        /** USERS BY OPERATIONS (top N) - doughnut */
        $topN = 10;
        $usersOps = DB::table('users')
            ->join('user_phones', 'user_phones.user_id', '=', 'users.id')
            ->join('message_groups', 'message_groups.user_phone_id', '=', 'user_phones.id')
            ->join('telegram_messages', 'telegram_messages.message_group_id', '=', 'message_groups.id')
            ->where('users.department_id', $department->id)
            ->where('telegram_messages.sent_at', '>=', $start)
            ->select('users.id', 'users.name', DB::raw('COUNT(telegram_messages.id) as cnt'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('cnt')
            ->limit($topN)
            ->get();

        $usersOpsLabels = $usersOps->pluck('name')->toArray();
        $usersOpsValues = $usersOps->pluck('cnt')->map(fn($v) => (int)$v)->toArray();
        $userIds = $usersOps->pluck('id')->toArray();

        /** GROUPED BAR: phones per user (for top users) */
        $phones = collect();
        $phoneLabels = [];
        $phoneDatasets = [];
        $phoneCount = 0;
        $userCount = count($userIds);

        if (!empty($userIds)) {
            $phones = DB::table('user_phones')
                ->whereIn('user_id', $userIds)
                ->select('id', 'user_id', 'phone', 'is_active')
                ->orderBy('user_id')
                ->get();

            $phonesByUser = [];
            $phoneIds = [];
            foreach ($phones as $p) {
                $phonesByUser[$p->user_id][] = $p;
                $phoneIds[] = $p->id;
            }

            $phoneLabels = [];
            $phoneIndexMap = [];
            foreach ($phones as $p) {
                $label = $p->phone;
                $masked = preg_replace('/\d(?=\d{4})/', '*', $label);
                $phoneLabels[] = $masked;
                $phoneIndexMap[$p->id] = count($phoneLabels) - 1;
            }

            $phoneCount = count($phoneLabels);
            $matrix = array_fill(0, $phoneCount, array_fill(0, $userCount, 0));

            if (!empty($phoneIds)) {
                $rawPhoneCounts = DB::table('telegram_messages')
                    ->join('message_groups', 'message_groups.id', '=', 'telegram_messages.message_group_id')
                    ->join('user_phones', 'user_phones.id', '=', 'message_groups.user_phone_id')
                    ->join('users', 'users.id', '=', 'user_phones.user_id')
                    ->where('users.department_id', $department->id)
                    ->whereIn('user_phones.id', $phoneIds)
                    ->where('telegram_messages.sent_at', '>=', $start)
                    ->select('user_phones.id as phone_id', 'users.id as user_id', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('user_phones.id', 'users.id')
                    ->get();

                $userPos = array_flip($userIds);

                foreach ($rawPhoneCounts as $r) {
                    $pId = $r->phone_id;
                    $uId = $r->user_id;
                    $cnt = (int)$r->cnt;

                    if (isset($phoneIndexMap[$pId]) && isset($userPos[$uId])) {
                        $pi = $phoneIndexMap[$pId];
                        $ui = $userPos[$uId];
                        $matrix[$pi][$ui] = $cnt;
                    }
                }
            }

            for ($i = 0; $i < $phoneCount; $i++) {
                $phoneDatasets[] = [
                    'label' => $phoneLabels[$i],
                    'data' => $matrix[$i],
                ];
            }
        }

        return view('departments.adminShow', compact(
            'department',
            'usersCount',
            'activePhonesCount',
            'messageGroupsCount',
            'telegramMessagesCount',
            'lastActiveUsers',
            'messagesPerDayLabels',
            'messagesPerDayValues',
            'usersOpsLabels',
            'usersOpsValues',
            'phoneLabels',
            'phoneDatasets',
            'userIds',
            'userCount',
            'phoneCount',
            'range'
        ));
    }

    public function users(Request $request, Department $department)
    {
        $user = $request->user();
        $this->permissonCheck($user, $department);

        $q = $request->input('q');

        $usersQuery = User::with([
            'avatar',
            'phones' => function ($query) {
                $query->where('is_active', true); // faqat aktiv telefonlar
            },
            'phones.ban',
            'ban',
            'role'
        ])->where('department_id', $department->id);

        if ($q) {
            $usersQuery->where(function ($w) use ($q) {
                $w->where('users.name', 'like', "%{$q}%")
                    ->orWhere('users.email', 'like', "%{$q}%")
                    ->orWhere('users.telegram_id', 'like', "%{$q}%");
            });
        }

        $users = $usersQuery->orderByDesc('created_at')->paginate(15)->withQueryString();

        return view('user.index', compact('department', 'users', 'q'));
    }
    public function operations(Request $request, Department $department)
    {
        $user = $request->user();
        $this->permissonCheck($user, $department);

        // auto-activate scheduled ban as in show()
        if ($department->ban && $department->ban->active == 0 && $department->ban->starts_at && $department->ban->starts_at < now()) {
            $department->ban->active = 1;
            $department->ban->save();
        }

        $q = trim((string) $request->get('q', ''));
        $status = $request->get('status', null);
        $from = $request->get('from', null);
        $to = $request->get('to', null);
        $selectedUserId = $request->get('user_id', null); // <-- yangi

        // department ichidagi users ro'yxati (filter uchun)
        $users = \App\Models\User::where('department_id', $department->id)
            ->orderBy('name')
            ->get();

        // Subquery: user_phone ids uchun reusable closure (agar user filter bo'lsa unga moslaydi)
        $phoneSub = function ($q) use ($department, $selectedUserId) {
            $q->select('user_phones.id')
                ->from('user_phones')
                ->join('users', 'users.id', '=', 'user_phones.user_id')
                ->where('users.department_id', $department->id);

            if ($selectedUserId) {
                $q->where('users.id', $selectedUserId);
            }
        };

        // Base query: message_groups for department phones (va optional user filter)
        $base = MessageGroup::whereIn('user_phone_id', $phoneSub);

        // Apply search: now check message_groups.message_text (text moved to groups)
        if ($q !== '') {
            $base->where('message_groups.message_text', 'like', "%{$q}%");
        }

        // Apply status filter (groups that have at least one message with that status)
        if ($status) {
            $base->whereExists(function ($sub) use ($status) {
                $sub->selectRaw(1)
                    ->from('telegram_messages')
                    ->whereColumn('telegram_messages.message_group_id', 'message_groups.id')
                    ->where('telegram_messages.status', $status);
            });
        }

        // Apply date range on telegram_messages.sent_at (groups that have messages in range)
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

        $messageGroups = $base->orderByDesc('id')
            ->paginate(10, ['*'], 'groups_page')
            ->withQueryString();

        $groupIds = $messageGroups->pluck('id')->toArray();

        // TEXT STATS per group
        $textStats = collect();
        if (!empty($groupIds)) {
            // get counts and min/max sent_at from telegram_messages
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

            // get group texts (sample_text) from message_groups
            $groupTexts = DB::table('message_groups')
                ->whereIn('id', $groupIds)
                ->pluck('message_text', 'id');

            // build final textStats: distinct_texts = 1 (because text is per group)
            $textStats = $rawStats->map(function ($row, $gid) use ($groupTexts) {
                return (object) [
                    'message_group_id' => $gid,
                    'total_messages' => (int) $row->total_messages,
                    'distinct_texts' => 1,
                    'sample_text' => $groupTexts[$gid] ?? null,
                    'started_at' => $row->started_at,
                    'ended_at' => $row->ended_at,
                ];
            });
        }

        // Peer + status counts: per group -> per peer -> status counts
        $peerStatusRaw = collect();
        if (!empty($groupIds)) {
            $peerStatusRaw = DB::table('telegram_messages')
                ->whereIn('message_group_id', $groupIds)
                ->whereIn('status', ['pending', 'scheduled', 'sent', 'canceled', 'failed'])
                ->select('message_group_id', 'peer', 'status', DB::raw('COUNT(*) as cnt'))
                ->groupBy('message_group_id', 'peer', 'status')
                ->get();
        }

        $peerStatusByGroup = [];
        $groupTotals = [];

        foreach ($peerStatusRaw as $row) {
            $gid = $row->message_group_id;
            $peer = $row->peer;
            $statusKey = $row->status;

            $peerStatusByGroup[$gid][$peer][$statusKey] = $row->cnt;
            $groupTotals[$gid][$statusKey] = ($groupTotals[$gid][$statusKey] ?? 0) + $row->cnt;
        }

        // TOTALS for header (groups & messages) — FILTERED by same phoneSub
        $messageGroupsTotal = MessageGroup::whereIn('user_phone_id', $phoneSub)->count();

        $telegramMessagesTotal = DB::table('telegram_messages')
            ->whereIn('message_group_id', function ($q) use ($phoneSub) {
                $q->select('id')
                    ->from('message_groups')
                    ->whereIn('user_phone_id', $phoneSub);
            })
            ->count();

        // Recent messages per group (if you still want to show some recent telegram_messages)
        $recentMessagesByGroup = [];
        if (!empty($groupIds)) {
            $recentRows = DB::table('telegram_messages')
                ->whereIn('message_group_id', $groupIds)
                ->orderByDesc('sent_at')
                ->get()
                ->groupBy('message_group_id');

            foreach ($recentRows as $gid => $rows) {
                $recentMessagesByGroup[$gid] = $rows->take(10);
            }
        }
        return view('operations.index', compact(
            'department',
            'messageGroups',
            'textStats',
            'peerStatusByGroup',
            'groupTotals',
            'recentMessagesByGroup',
            'messageGroupsTotal',
            'telegramMessagesTotal',
            'q',
            'status',
            'from',
            'to',
            'users',
            'selectedUserId'
        ));
    }
    public function showOperation(Request $request, $operationId)
    {
        $user = $request->user();
        $department = $user->department;

        $operation = MessageGroup::with([
            'phone' => function ($q) {
                $q->withTrashed()
                    ->with(['user' => function ($q2) {
                        $q2->withTrashed()
                            ->with(['department' => function ($q3) {
                                $q3->withTrashed();
                            }]);
                    }]);
            }
        ])->findOrFail($operationId);


        if ($user->role->name !== 'superadmin') {
            $this->permissonCheck($user, $operation->phone->user->department);
        }

        // Status filter from request
        $status = $request->get('status', '');


        // Filter messages by status if provided
        $messages = $operation->messages()->orderBy('send_at');
        if ($status) {
            $messages->where('status', $status);
        }
        $messages = $messages->get();


        // Group messages by peer
        $peers = $messages->groupBy('peer');


        return view('operations.show', [
            'operation' => $operation,
            'department' => $department,
            'peers' => $peers,
            'currentStatus' => $status,
            'user' => $user
        ]);
    }

    public function permissonCheck($user, $department)
    {
        if ($department) {
            if ($user->department_id !== $department->id) {
                abort(403, __('messages.operations.error_no_permission'));
            }
        }
    }
}
