<?php

namespace App\Http\Controllers\View\Messages;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\Department;
use App\Models\MessageGroup;
use App\Models\TelegramMessage;
use App\Services\ErrorKeyService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ShowController extends Controller
{
    public function __construct(private ErrorKeyService $error_key_service) {}

    private function ensureAccess($user, MessageGroup $operation): void
    {
        $isSuperadmin = optional($user->role)->name === 'superadmin';

        if ($isSuperadmin) {
            return;
        }

        $ownerDepartment = data_get($operation, 'phone.user.department');
        $this->permissonCheck($user, $ownerDepartment);
    }

    public function permissonCheck($user, $department)
    {
        if ($department && $user->department_id !== $department->id) {
            abort(403, __('messages.operations.error_no_permission'));
        }
    }

    private function normalizePeers(mixed $peers): array
    {
        if (is_array($peers)) {
            return array_values(array_filter(array_map('trim', $peers)));
        }

        if (! is_string($peers) || trim($peers) === '') {
            return [];
        }

        $peers = trim($peers);

        $decoded = json_decode($peers, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded)));
        }

        return array_values(array_filter(array_map('trim', explode(',', $peers))));
    }

    private function buildCatalogPeerMap(MessageGroup $messageGroup): array
    {
        $messageGroup->loadMissing('catalogs'); // OK
        $map = [];

        foreach ($messageGroup->catalogs as $catalog) {
            foreach ($this->normalizePeers($catalog->peers ?? []) as $peer) {
                if ($peer === '') {
                    continue;
                }

                // first found catalog wins
                $map[$peer] ??= $catalog->id;
            }
        }

        return $map;
    }

    private function makePeerUrl(string $peer): ?string
    {
        $peer = trim($peer);

        if ($peer === '') {
            return null;
        }

        if (Str::startsWith($peer, ['http://', 'https://'])) {
            return $peer;
        }

        if (Str::startsWith($peer, '@')) {
            return 'https://t.me/' . ltrim($peer, '@');
        }

        if (preg_match('/^[A-Za-z0-9_]{5,}$/', $peer)) {
            return 'https://t.me/' . $peer;
        }

        return null;
    }

    private function makePeerBadgeClass(string $primaryStatus): string
    {
        return match ($primaryStatus) {
            'sent' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            'failed' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
            'pending', 'scheduled', 'processing' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
            'canceled' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        };
    }

    private function resolvePrimaryStatus(object $row): string
    {
        if ((int) $row->pending_count > 0 || (int) $row->scheduled_count > 0 || (int) $row->processing_count > 0) {
            return 'pending';
        }

        if ((int) $row->failed_count > 0) {
            return 'failed';
        }

        if ((int) $row->sent_count > 0) {
            return 'sent';
        }

        if ((int) $row->canceled_count > 0) {
            return 'canceled';
        }

        return 'unknown';
    }

    public function show(Request $request, MessageGroup $messageGroup)
    {
        $user = $request->user();
        $isSuperadmin = optional($user->role)->name === 'superadmin';

        $messageGroup->load([
            'phone' => function ($q) {
                $q->withTrashed()
                    ->with([
                        'user' => function ($q2) {
                            $q2->withTrashed()
                                ->with([
                                    'department' => function ($q3) {
                                        $q3->withTrashed();
                                    }
                                ]);
                        }
                    ]);
            },
        ]);

        $ownerDepartment = data_get($messageGroup, 'phone.user.department');

        if (! $ownerDepartment) {
            abort(404, "");
        }

        if (! $isSuperadmin) {
            $this->permissonCheck($user, $ownerDepartment);
        }

        $department = $isSuperadmin
            ? Department::with('users.phones')->findOrFail($ownerDepartment->id)
            : $user->department;

        $stats = TelegramMessage::query()
            ->where('message_group_id', $messageGroup->id)
            ->selectRaw("
                COUNT(*) as total_messages,
                COUNT(DISTINCT peer) as total_peers,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_count,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
                SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled_count,
                MIN(send_at) as first_send_at,
                MAX(send_at) as last_send_at,
                MAX(sent_at) as last_sent_at
            ")
            ->first();

        $firstSendAt = ! empty($stats->first_send_at)
            ? Carbon::parse($stats->first_send_at)
            : null;

        $topRightTimeValue = $messageGroup->status === 'pending'
            ? (! empty($stats->last_send_at) ? Carbon::parse($stats->last_send_at) : null)
            : (! empty($stats->last_sent_at)
                ? Carbon::parse($stats->last_sent_at)
                : (! empty($stats->last_send_at) ? Carbon::parse($stats->last_send_at) : null));

        $isPending = ($messageGroup->status ?? '') === 'pending';

        $senderPhone = $messageGroup->phone->phone
            ?? $messageGroup->phone->number
            ?? '-';

        $departmentName = $department->name ?? '-';

        $statusMap = [
            'sent'       => __('messages.sent'),
            'failed'     => __('messages.failed'),
            'canceled'   => __('messages.canceled'),
            'scheduled'  => __('messages.scheduled'),
            'pending'    => __('messages.pending'),
            'processing' => __('messages.processing'),
        ];

        return view('pages.general.groups.show', [
            'operation'         => $messageGroup,
            'department'        => $department,
            'stats'             => $stats,
            'currentStatus'     => (string) $request->get('status', ''),
            'user'              => $user,
            'statusMap'         => $statusMap,
            'firstSendAt'       => $firstSendAt,
            'topRightTimeValue' => $topRightTimeValue,
            'isPending'         => $isPending,
            'senderPhone'       => $senderPhone,
            'departmentName'    => $departmentName,
        ]);
    }

    public function peers(Request $request, MessageGroup $messageGroup)
    {
        $user = $request->user();
        $this->ensureAccess($user, $messageGroup);

        $messageGroup->loadMissing('catalogs'); // OK

        $catalogPeerMap = $this->buildCatalogPeerMap($messageGroup);

        $perPage = max(5, min((int) $request->get('per_page', 10), 50));
        $search = trim((string) $request->get('search', ''));
        $status = trim((string) $request->get('status', ''));

        $query = TelegramMessage::query()
            ->where('message_group_id', $messageGroup->id)
            ->when($search !== '', function ($q) use ($search) {
                $q->where('peer', 'like', '%' . $search . '%');
            })
            ->when($status !== '', function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->selectRaw("
                COALESCE(peer, '') as peer_key,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_count,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
                SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled_count,
                MIN(send_at) as first_send_at,
                MAX(send_at) as last_send_at,
                MAX(sent_at) as last_sent_at
            ")
            ->groupByRaw("COALESCE(peer, '')")
            ->orderByDesc('last_send_at');

        $peers = $query->paginate($perPage);

        $peers->setCollection(
            $peers->getCollection()->map(function ($row) use ($catalogPeerMap) {
                $peerKey = (string) ($row->peer_key ?? '');
                $primaryStatus = $this->resolvePrimaryStatus($row);
                $catalogId = $catalogPeerMap[$peerKey] ?? null;
                $peerUrl = $this->makePeerUrl($peerKey);

                return (object) [
                    'peerKey'       => $peerKey,
                    'peer'          => $peerKey,
                    'peerLabel'     => $peerKey,
                    'peerUrl'       => $peerUrl,
                    'peerBadgeCls'  => $this->makePeerBadgeClass($primaryStatus),
                    'statusCounts'  => [
                        'sent'       => (int) $row->sent_count,
                        'failed'     => (int) $row->failed_count,
                        'pending'    => (int) $row->pending_count,
                        'scheduled'  => (int) $row->scheduled_count,
                        'processing' => (int) $row->processing_count,
                        'canceled'   => (int) $row->canceled_count,
                    ],
                    'primaryStatus' => $primaryStatus,
                    'total'         => (int) $row->total,
                    'firstSendAt'   => ! empty($row->first_send_at) ? Carbon::parse($row->first_send_at) : null,
                    'lastSendAt'    => ! empty($row->last_send_at) ? Carbon::parse($row->last_send_at) : null,
                    'lastSentAt'    => ! empty($row->last_sent_at) ? Carbon::parse($row->last_sent_at) : null,
                    'catalogId'     => $catalogId,
                    'inCatalog'     => $catalogId !== null,
                    'tg_link'       => $peerUrl,
                    'failed_keys'   => [],
                ];
            })
        );

        $html = view('pages.general.groups.partials.peers-list', [
            'operation'     => $messageGroup,
            'peers'         => $peers,
            'currentStatus' => $status,
            'search'        => $search,
        ])->render();

        return response()->json([
            'html'        => $html,
            'next_page'   => $peers->nextPageUrl(),
            'current_page' => $peers->currentPage(),
            'last_page'   => $peers->lastPage(),
        ]);
    }

    public function peerMessages(Request $request, MessageGroup $messageGroup)
    {
        $user = $request->user();
        $this->ensureAccess($user, $messageGroup);

        $peer = (string) $request->get('peer', '');
        $status = trim((string) $request->get('status', ''));
        $perPage = max(10, min((int) $request->get('per_page', 20), 100));

        abort_if($peer === '', 422, 'peer is required');

        $query = TelegramMessage::query()
            ->where('message_group_id', $messageGroup->id)
            ->where('peer', $peer)
            ->when($status !== '', function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->orderBy('send_at');

        $messages = $query->paginate($perPage);

        $messages->getCollection()->transform(function ($msg) {
            $msg->error_text = $this->error_key_service->translateErrorKey($msg->error_key);
            return $msg;
        });

        $html = view('pages.general.groups.partials.peer-messages', [
            'messages'  => $messages,
            'operation' => $messageGroup,
            'peer'      => $peer,
        ])->render();

        return response()->json([
            'html'        => $html,
            'next_page'   => $messages->nextPageUrl(),
            'current_page' => $messages->currentPage(),
            'last_page'   => $messages->lastPage(),
        ]);
    }
    public function removePeer(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'catalog_id' => 'required|integer|exists:catalogs,id',
            'peer' => 'required|string|max:255',
        ]);

$catalog = Catalog::query()->findOrFail($data['catalog_id']);

        // 🔐 ROLE CHECK
        $role = $user?->role?->name;

        if ($role === 'user') {
            if ((int) $catalog->user_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.remove_peer.forbidden'),
                ], 403);
            }
        }

        // admin va superadmin hech qanday cheklovsiz davom etadi

        $peers = $this->normalizePeers($catalog->peers ?? []);

        if (! in_array($data['peer'], $peers, true)) {
            return response()->json([
                'success' => true,
                'message' => __('messages.remove_peer.peer_not_found_no_change'),
                'peers' => $peers,
            ]);
        }

        $updated = array_values(array_filter($peers, fn($p) => $p !== $data['peer']));

        if (is_array($catalog->peers)) {
            $catalog->peers = $updated;
        } elseif (is_string($catalog->peers) && str_contains($catalog->peers, '[')) {
            $catalog->peers = json_encode($updated, JSON_UNESCAPED_UNICODE);
        } else {
            $catalog->peers = implode(',', $updated);
        }

        $catalog->save();

        return response()->json([
            'success' => true,
            'message' => __('messages.remove_peer.peer_removed'),
            'peers' => $updated,
        ]);
    }
    public function removePeers(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'catalog_id' => 'required|integer|exists:catalogs,id',
            'peers'      => 'required|array|min:1',
            'peers.*'    => 'required|string|max:255',
        ]);

        $catalog = Catalog::find($data['catalog_id']);

        if (! $catalog) {
            return response()->json([
                'success' => false,
                'message' => __('messages.remove_peer.catalog_not_found'),
            ], 404);
        }

        $role = $user?->role?->name;

        if ($role === 'user') {
            if ((int) $catalog->user_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.remove_peer.forbidden'),
                ], 403);
            }
        }

        $peers = $this->normalizePeers($catalog->peers ?? []);
        $removePeers = array_values(array_unique($data['peers']));

        $updated = array_values(array_filter($peers, function ($peer) use ($removePeers) {
            return !in_array($peer, $removePeers, true);
        }));

        if (is_array($catalog->peers)) {
            $catalog->peers = $updated;
        } elseif (is_string($catalog->peers) && str_contains($catalog->peers, '[')) {
            $catalog->peers = json_encode($updated, JSON_UNESCAPED_UNICODE);
        } else {
            $catalog->peers = implode(',', $updated);
        }

        $catalog->save();

        return response()->json([
            'success' => true,
            'message' => __('messages.op_show2.peers_removed'),
            'peers'   => $updated,
        ]);
    }
}
