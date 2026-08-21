<?php

namespace App\Http\Controllers\View;

use App\Http\Controllers\Controller;
use App\Jobs\V2\ExecJob;
use App\Models\Catalog;
use App\Models\Department;
use App\Models\MessageGroup;
use App\Models\MinutePackage\MinutePackage;
use App\Models\TelegramMessage;
use App\Models\User;
use App\Models\UserPhone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageGroupController extends Controller
{

    public function getPending(Request $request, Department $department)
    {
        $user = $request->user();

        $roleName = $user->role->name ?? 'user';
        if ($roleName !== 'superadmin' && $user->department_id !== $department->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($department->ban && $department->ban->active == 0 && $department->ban->starts_at && $department->ban->starts_at < now()) {
            $department->ban->active = 1;
            $department->ban->save();
        }

        $q = trim((string) $request->get('q', ''));
        $status = $request->get('status', null);
        $from = $request->get('from', null);
        $to = $request->get('to', null);
        $selectedUserId = $request->get('user_id', null);

        $users = User::where('department_id', $department->id)->select('id', 'name')->orderBy('name')->get();

        $phoneSub = function ($q) use ($department, $selectedUserId) {
            $q->select('user_phones.id')
                ->from('user_phones')
                ->join('users', 'users.id', '=', 'user_phones.user_id')
                ->where('users.department_id', $department->id);

            if ($selectedUserId) {
                $q->where('users.id', $selectedUserId);
            }
        };

        $base = MessageGroup::whereIn('user_phone_id', $phoneSub);

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

                if ($from) {
                    $sub->where('telegram_messages.sent_at', '>=', Carbon::parse($from)->startOfDay());
                }
                if ($to) {
                    $sub->where('telegram_messages.sent_at', '<=', Carbon::parse($to)->endOfDay());
                }
            });
        }

        // pagination params
        $perPage = (int) $request->get('per_page', 10);
        $page = (int) $request->get('page', 1);

        $messageGroups = $base->with([
            // eager load phone + user + catalogs
            'phone' => function ($q) {
                $q->select('id', 'user_id', 'phone');
            },
            'phone.user' => function ($q) {
                $q->select('id', 'name');
            },
            'catalogs' => function ($q) {
                $q->select('catalogs.id', 'catalogs.title');
            },
        ])
            ->orderByDesc('message_groups.id')
            ->paginate($perPage, ['*'], 'page', $page);

        $groupIds = $messageGroups->pluck('id')->toArray();

        // 1) text stats per group (total messages, min/max sent_at)
        $rawStats = collect();
        if (!empty($groupIds)) {
            $rawStats = DB::table('telegram_messages')
                ->whereIn('message_group_id', $groupIds)
                ->select('message_group_id', DB::raw('COUNT(*) as total_messages'), DB::raw('MIN(sent_at) as started_at'), DB::raw('MAX(sent_at) as ended_at'))
                ->groupBy('message_group_id')
                ->get()
                ->keyBy('message_group_id');
        }

        // 2) counts by status for all groups in one query
        $statusRows = collect();
        if (!empty($groupIds)) {
            $statusRows = DB::table('telegram_messages')
                ->whereIn('message_group_id', $groupIds)
                ->select('message_group_id', 'status', DB::raw('COUNT(*) as cnt'))
                ->groupBy('message_group_id', 'status')
                ->get();
        }

        // group status map: [groupId => [status => count, ...]]
        $countsByGroup = [];
        foreach ($statusRows as $r) {
            $gid = $r->message_group_id;
            $countsByGroup[$gid][$r->status] = (int)$r->cnt;
        }

        // assemble response data
        $data = $messageGroups->getCollection()->map(function ($group) use ($rawStats, $countsByGroup) {
            $gid = $group->id;
            $phone = $group->phone;
            $user = $phone->user ?? null;

            $catalogs = $group->catalogs->pluck('title')->values()->all();

            $raw = $rawStats[$gid] ?? null;

            // normalize counts
            $counts = [
                'sent' => (int) ($countsByGroup[$gid]['sent'] ?? 0),
                'failed' => (int) ($countsByGroup[$gid]['failed'] ?? 0),
                'canceled' => (int) ($countsByGroup[$gid]['canceled'] ?? 0),
                'scheduled' => (int) ($countsByGroup[$gid]['scheduled'] ?? 0),
                'pending' => (int) ($countsByGroup[$gid]['pending'] ?? 0),
                // include other statuses if you use them
            ];

            $totalMessages = (int) ($raw->total_messages ?? 0);

            // determine representative status for group (you may define your own logic)
            $groupStatus = $this->deriveGroupStatus($counts);

            return [
                'id' => $gid,
                'phone_id' => $phone->id ?? null,
                'phone' => $phone->phone ?? null,
                'user_id' => $user->id ?? null,
                'user_name' => $user->name ?? null,
                'message_text' => $group->message_text,
                'catalogs' => $catalogs,
                'totals' => [
                    'total_messages' => $totalMessages,
                    'counts_by_status' => $counts,
                ],
                'started_at' => $raw->started_at ?? null,
                'ended_at' => $raw->ended_at ?? null,
                'group_status' => $groupStatus,
            ];
        });

        $messageGroupsTotal = MessageGroup::whereIn('user_phone_id', $phoneSub)->count();
        $telegramMessagesTotal = DB::table('telegram_messages')
            ->whereIn('message_group_id', function ($q) use ($phoneSub) {
                $q->select('id')->from('message_groups')->whereIn('user_phone_id', $phoneSub);
            })->count();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $messageGroups->currentPage(),
                'last_page' => $messageGroups->lastPage(),
                'per_page' => $messageGroups->perPage(),
                'total' => $messageGroups->total(),
            ],
            'totals' => [
                'message_groups_total' => $messageGroupsTotal,
                'telegram_messages_total' => $telegramMessagesTotal,
            ],
            'users' => $users,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'from' => $from,
                'to' => $to,
                'selected_user_id' => $selectedUserId,
            ],
        ]);
    }
    protected function deriveGroupStatus(array $counts): string
    {
        $total = array_sum($counts);
        if ($total === 0) return 'unknown';

        if (($counts['canceled'] ?? 0) > 0 && ($counts['canceled'] ?? 0) === $total) {
            return 'canceled';
        }

        if (($counts['sent'] ?? 0) > 0 && ($counts['sent'] ?? 0) === $total) {
            return 'completed';
        }

        if (($counts['scheduled'] ?? 0) > 0 && ($counts['sent'] ?? 0) === 0) {
            return 'scheduled';
        }

        if (($counts['sent'] ?? 0) > 0 && ($counts['failed'] ?? 0) > 0) {
            return 'partial';
        }

        if (($counts['pending'] ?? 0) > 0 || ($counts['processing'] ?? 0) > 0) {
            return 'sending';
        }

        return 'processing';
    }
    public function show(Request $request, $operationId)
    {
        $user = $request->user();
        $isSuperadmin = optional($user->role)->name === 'superadmin';
        
        

            $operation = MessageGroup::with([
                'phone' => function ($q) {
                    $q->withTrashed()
                        ->with(['user' => function ($q2) {
                            $q2->withTrashed()
                                ->with(['department' => function ($q3) {
                                    $q3->withTrashed();
                                }]);
                        }]);
                },
                'messages' => function ($q) {
                    $q->orderBy('send_at');
                }
            ])->findOrFail($operationId);
            
        $department=$operation->phone->user->department->id;
        $department = $isSuperadmin
            ? Department::with('users.phones')->findOrFail($department)
            : $user->department;
        $ownerDepartment = data_get($operation, 'phone.user.department');

        if ($user->role->name !== 'superadmin') {
            $this->permissonCheck($user, $ownerDepartment);
        }

        $status = $request->get('status', '');

        $allMessages = $operation->messages ?? collect();

        $messages = $status
            ? $allMessages->where('status', $status)
            : $allMessages;

        $peers = $messages->groupBy('peer');

        $startsAt = $allMessages->min('send_at')
            ? Carbon::parse($allMessages->min('send_at'))
            : null;

        $lastScheduledAt = $allMessages->max('send_at')
            ? Carbon::parse($allMessages->max('send_at'))
            : null;

        $lastSentAt = $allMessages->whereNotNull('sent_at')->max('sent_at')
            ? Carbon::parse($allMessages->whereNotNull('sent_at')->max('sent_at'))
            : null;

        $endsAt = $operation->status === 'pending'
            ? $lastScheduledAt
            : ($lastSentAt ?: $lastScheduledAt);


        return view('pages.general.groups.show', [
            'operation'      => $operation,
            'department'     => $department,
            'peers'          => $peers,
            'currentStatus'  => $status,
            'user'           => $user,
            'startsAt'       => $startsAt,
            'endsAt'         => $endsAt,
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
    public function sendForm($id = null)
    {
        $user = Auth::user();
        $isSuperadmin = optional($user->role)->name === 'superadmin';
        $department = $isSuperadmin
            ? Department::with('users.phones')->findOrFail($id)
            : $user->department;



        $departmentBan = DB::table('bans')
            ->where('bannable_type', Department::class)
            ->where('bannable_id', $department->id)
            ->where(function ($query) {
                $query->where('active', true)
                    ->orWhere('starts_at', '<', now());
            })
            ->first();
        if (!$isSuperadmin && ($departmentBan)) {
            return redirect("/");
        }

        $userCatalogs = $user->catalogs()->get();

        $globalCatalogs = Catalog::whereHas('user', function ($q) use ($user) {
            $q->where('department_id', $user->department_id)
                ->where('id', '!=', $user->id);
        })
            ->get();

        $ownPhone = $user->phones()
            ->where('is_active', true)
            ->first();

        $ownPhones = $ownPhone ? [$ownPhone] : [];

        $minuteAccess = $user->minuteAccess;

        $minutePackages = null;
        if ($minuteAccess && $minuteAccess->is_active) {
            $minutePackages = MinutePackage::pluck('minutes');
        }

        return view('pages.users.send-messages', [
            'ownPhones' => $ownPhones,
            'minuteAccess' => $minuteAccess,
            'minutePackages' => $minutePackages,
            'catalogs' => $userCatalogs,
            'globalCatalogs' => $globalCatalogs,
            'department' => $department
        ]);
    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $user = Auth::user();

        $group = MessageGroup::with('phone.user.department')->findOrFail($id);

        if ($user->hasRole('superadmin')) {
        } elseif ($user->hasRole('admin')) {
            if (!$group->phone || !$group->phone->user || !$group->phone->user->department) {
                abort(403);
            }

            if ($group->phone->user->department_id !== $user->department_id) {
                abort(403);
            }
        } else {
            if (!$group->phone || $group->phone->user_id !== $user->id) {
                abort(403);
            }
        }
        $group->update([
            'message_text' => $request->message,
        ]);

        return back()->with('success', __('messages.success'));
    }
    // public function sendMassMessage(Request $request)
    // {
    //     $request->validate([
    //         'phone_id'      => 'required|exists:user_phones,id',
    //         'catalog_ids'   => 'required|array',
    //         'catalog_ids.*' => 'exists:catalogs,id',
    //         'message'       => 'required|string',
    //         'interval'      => 'required|integer|min:0|max:1440',
    //         'duration'      => 'required|integer|min:1|max:48',
    //     ]);

    //     $phone = UserPhone::with('messageGroups')->find($request->phone_id);

    //     if (!$phone) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Telefon topilmadi.'
    //         ], 404);
    //     }

    //     $pendingMessages = $phone->messageGroups->where('status', 'pending')->count();
    //     if ($pendingMessages >= 2) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => __('messages.send_limit'),
    //         ], 429);
    //     }

    //     $durationHours   = (int) $request->duration;
    //     $interval        = max(1, (int) ($request->interval ?? 61));
    //     $durationMinutes  = $durationHours * 60;
    //     $loopCount       = max(1, (int) ceil($durationMinutes / $interval));

    //     try {
    //         DB::beginTransaction();

    //         $group = MessageGroup::create([
    //             'user_phone_id'  => $phone->id,
    //             'status'         => 'pending',
    //             'message_text'   => $request->message,
    //             'interval'       => $interval,
    //             'total_batches'  => $loopCount,
    //             'current_batch'  => 0,
    //         ]);

    //         $catalogs = Catalog::whereIn('id', $request->catalog_ids)->get();
    //         $group->catalogs()->attach($catalogs->pluck('id')->toArray());

    //         $peers = [];

    //         foreach ($catalogs as $catalog) {
    //             $catalogPeers = is_array($catalog->peers)
    //                 ? $catalog->peers
    //                 : json_decode($catalog->peers ?? '[]', true);

    //             if (!empty($catalogPeers)) {
    //                 $peers = array_merge($peers, $catalogPeers);
    //             }
    //         }

    //         $peers = array_values(array_unique($peers));

    //         if (empty($peers)) {
    //             throw new \RuntimeException('Peerlar topilmadi');
    //         }

    //         $blockedPeers = PeerBlock::where('user_phone_id', $phone->id)
    //             ->pluck('peer')
    //             ->toArray();

    //         $blockedPeers = array_values(array_unique($blockedPeers));
    //         $now = now();
    //         $base = now();

    //         $insertChunk = [];

    //         foreach ($peers as $peer) {
    //             $isBlocked = in_array($peer, $blockedPeers, true);

    //             if ($isBlocked) {
    //                 $insertChunk[] = [
    //                     'message_group_id' => $group->id,
    //                     'peer'             => $peer,
    //                     'send_at'          => $now,
    //                     'status'           => 'failed',
    //                     'error_key'        => 'peer_blocked',
    //                     'batch_no'         => 0,
    //                     'created_at'       => $now,
    //                     'updated_at'       => $now,
    //                 ];

    //                 if (count($insertChunk) >= 1000) {
    //                     TelegramMessage::insert($insertChunk);
    //                     $insertChunk = [];
    //                 }

    //                 continue;
    //             }

    //             for ($i = 0; $i < $loopCount; $i++) {
    //                 $insertChunk[] = [
    //                     'message_group_id' => $group->id,
    //                     'peer'             => $peer,
    //                     'send_at'          => $base->copy()->addMinutes($i * $interval),
    //                     'status'           => 'pending',
    //                     'error_key'        => null,
    //                     'batch_no'         => $i + 1,
    //                     'created_at'       => $now,
    //                     'updated_at'       => $now,
    //                 ];

    //                 if (count($insertChunk) >= 1000) {
    //                     TelegramMessage::insert($insertChunk);
    //                     $insertChunk = [];
    //                 }
    //             }
    //         }

    //         if (!empty($insertChunk)) {
    //             TelegramMessage::insert($insertChunk);
    //         }

    //         $dispatchAt = now();

    //         foreach (range(1, $loopCount) as $batchNo) {
    //             if ($batchNo === 1) {
    //                 $dispatchAt = now();
    //             } else {
    //                 $randomSeconds = rand(30, 70);
    //                 $dispatchAt = $dispatchAt->copy()
    //                     ->addMinutes($interval)
    //                     ->addSeconds($randomSeconds);
    //             }

    //             Log::info('Dispatching ExecJob', [
    //                 'batch_no'     => $batchNo,
    //                 'interval_min' => $interval,
    //                 'dispatch_at'  => $dispatchAt->format('Y-m-d H:i:s'),
    //             ]);

    //             ExecJob::dispatch($group->id, $batchNo)
    //                 ->onQueue('telegram')
    //                 ->delay($dispatchAt);
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'success'  => true,
    //             'message'  => __('messages.schedule_created'),
    //             'group_id' => $group->id
    //         ]);
    //     } catch (\Throwable $e) {
    //         DB::rollBack();

    //         Log::error('Mass message error', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Xatolik yuz berdi'
    //         ], 500);
    //     }
    // }
    public function sendMassMessage(Request $request)
    {
        $request->validate([
            'phone_id'      => 'required|exists:user_phones,id',
            'catalog_ids'   => 'required|array',
            'catalog_ids.*' => 'exists:catalogs,id',
            'message'       => 'required|string',
            'interval'      => 'required|integer|min:0|max:1440',
            'duration'      => 'required|integer|min:1|max:48',
        ]);

        $phone = UserPhone::with('messageGroups')->find($request->phone_id);

        if (!$phone) {
            return response()->json([
                'success' => false,
                'message' => 'Telefon topilmadi.'
            ], 404);
        }

        $pendingMessages = $phone->messageGroups->where('status', 'pending')->count();
        if ($pendingMessages >= 10) {
            return response()->json([
                'success' => false,
                'message' => __('messages.send_limit'),
            ], 429);
        }

        $durationHours    = (int) $request->duration;
        $interval         = max(1, (int) ($request->interval ?? 61));
        $durationMinutes  = $durationHours * 60;
        $loopCount        = max(1, (int) ceil($durationMinutes / $interval));

        try {
            DB::beginTransaction();

            $group = MessageGroup::create([
                'user_phone_id' => $phone->id,
                'status'        => 'pending',
                'message_text'   => $request->message,
                'interval'       => $interval,
                'total_batches'  => $loopCount,
                'current_batch'  => 0,
            ]);

            $catalogs = Catalog::whereIn('id', $request->catalog_ids)->get();
            $group->catalogs()->attach($catalogs->pluck('id')->toArray());

            $peers = [];

            foreach ($catalogs as $catalog) {
                $catalogPeers = is_array($catalog->peers)
                    ? $catalog->peers
                    : json_decode($catalog->peers ?? '[]', true);

                if (!empty($catalogPeers)) {
                    $peers = array_merge($peers, $catalogPeers);
                }
            }

            $peers = array_values(array_unique($peers));

            if (empty($peers)) {
                throw new \RuntimeException('Peerlar topilmadi');
            }

            $now = now();
            $base = now();

            $insertChunk = [];

            foreach ($peers as $peer) {
                for ($i = 0; $i < $loopCount; $i++) {
                    $insertChunk[] = [
                        'message_group_id' => $group->id,
                        'peer'             => $peer,
                        'send_at'          => $base->copy()->addMinutes($i * $interval),
                        'status'           => 'pending',
                        'batch_no'         => $i + 1,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];

                    if (count($insertChunk) >= 1000) {
                        TelegramMessage::insert($insertChunk);
                        $insertChunk = [];
                    }
                }
            }

            if (!empty($insertChunk)) {
                TelegramMessage::insert($insertChunk);
            }

            $dispatchAt = now();

            foreach (range(1, $loopCount) as $batchNo) {
                if ($batchNo === 1) {
                    $dispatchAt = now();
                    $randomSeconds = 0;
                } else {
                    $randomSeconds = $this->getRandomSeconds($interval);

                    $dispatchAt = $dispatchAt->copy()
                        ->addMinutes($interval)
                        ->addSeconds($randomSeconds);
                }

                Log::info('Dispatching ExecJob', [
                    'batch_no' => $batchNo,
                    'interval_min' => $interval,
                    'random_sec' => $randomSeconds,
                    'dispatch_at' => $dispatchAt->format('Y-m-d H:i:s'),
                ]);

                ExecJob::dispatch($group->id, $batchNo)
                    ->onQueue('telegram')
                    ->delay($dispatchAt);
            }

            DB::commit();

            return response()->json([
                'success'  => true,
                'message'  => __('messages.schedule_created'),
                'group_id' => $group->id
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Mass message error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Xatolik yuz berdi'
            ], 500);
        }
    }
    private function getRandomSeconds(int $interval): int
{
    return match (true) {
        $interval <= 1  => rand(5, 10),
        $interval <= 5  => rand(10, 20),
        $interval <= 60 => rand(15, 30),
        default         => rand(30, 70),
    };
}
}
