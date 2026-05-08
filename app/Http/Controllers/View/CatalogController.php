<?php

namespace App\Http\Controllers\View;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogRequest;
use App\Models\Catalog;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    protected function normalizePeers($peers): array
    {
        if (is_null($peers)) return [];

        if (is_string($peers)) {
            $decoded = json_decode($peers, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map('trim', $decoded)));
            }
            $parts = preg_split('/(?:\r?\n|,|;|\s)+/', trim($peers));
            $parts = array_filter(array_map(fn($p) => $p ? (str_starts_with($p, '@') ? $p : '@' . $p) : null, $parts));
            return array_values(array_unique($parts));
        }

        if (is_array($peers) || $peers instanceof \Illuminate\Support\Collection) {
            return array_values(array_filter(array_unique(array_map(fn($p) => is_null($p) ? null : trim((string)$p), (array)$peers))));
        }

        return [];
    }

    public function index(Request $request, Department $department = null)
    {
        $search = $request->get('search');
        $filter = $request->get('filter', 'all');
        $user = Auth::user();
        $isSuperadmin = optional($user->role)->name === 'superadmin';
        $isAdmin = optional($user->role)->name === 'admin';
        $isUser = optional($user->role)->name === 'user';

        if (!$isSuperadmin) {
            $department = $user->department;
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
            return redirect("/");
        }

        $query = Catalog::with('user')
            ->whereHas('user', function ($q) use ($department) {
                $q->where('department_id', $department->id);
            });

        if ($filter === 'my') {
            $query->where('user_id', $user->id);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereJsonContains('peers', $search)
                    ->orWhere('peers', 'like', "%{$search}%");
            });
        }

        $catalogs = $query
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $catalogs->getCollection()->transform(function ($c) {
            $c->peers = $this->normalizePeers($c->peers);
            return $c;
        });

        $permissions = [
            'isSuperadmin' => $isSuperadmin,
            'isAdmin' => $isAdmin,
            'isUser' => $isUser,
        ];

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('messages.catalogs.fetched', [], $request->getPreferredLanguage() ?? null),
                'data' => $catalogs->items(),
                'meta' => [
                    'current_page' => $catalogs->currentPage(),
                    'last_page' => $catalogs->lastPage(),
                    'per_page' => $catalogs->perPage(),
                    'total' => $catalogs->total(),
                ],
            ]);
        }

        return view('pages.users.catalog', compact(
            'catalogs',
            'department',
            'permissions'
        ));
    }

    public function store(CatalogRequest $request, Department $department)
    {
        $user = Auth::user();
        $isGlobal = $request->boolean('is_global') && optional($user->role)->name === 'superadmin';

        $rawPeers = $request->input('peers', []);
        $cleanPeers = collect($rawPeers)
            ->map(fn($p) => trim((string)$p))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $catalog = Catalog::create([
            'title' => $request->input('title'),
            'user_id' => $user->id,
            'peers' => $cleanPeers,
            'is_global' => $isGlobal,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            $catalog->peers = $this->normalizePeers($catalog->peers);
            return response()->json([
                'success' => true,
                'message' => __('messages.catalogs.created_success'),
                'catalog' => $catalog->fresh(['user']),
            ], 201);
        }

        return redirect()->route('catalogs.index', ['department' => $department->id])
            ->with('success', __('messages.catalogs.created_success'));
    }

    public function show(Department $department, Catalog $catalog, Request $request)
    {
        if ($catalog->user && $catalog->user->department_id !== $department->id) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.error_occurred'),
                ], 404);
            }
            return redirect()->route('catalogs.index', ['department' => $department->id]);
        }

        $peers = $this->normalizePeers($catalog->peers);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('messages.catalogs.fetched', [], $request->getPreferredLanguage() ?? null),
                'id' => $catalog->id,
                'title' => $catalog->title,
                'peers' => $peers,
                'user_id' => $catalog->user_id,
                'user_name' => $catalog->user->name ?? null,
                'is_global' => (bool) $catalog->is_global,
                'created_at' => $catalog->created_at ? $catalog->created_at->toDateTimeString() : null,
            ]);
        }

        return redirect()->route('catalogs.index', ['department' => $department->id]);
    }

    public function update(CatalogRequest $request, Department $department, Catalog $catalog)
    {
        $user = Auth::user();

        if (
            !in_array(optional($user->role)->name, ['superadmin', 'admin'])
            && $catalog->user_id !== $user->id
        ) {

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.catalogs.update_forbidden') ?? 'Forbidden',
                ], 403);
            }

            abort(403, __('messages.catalogs.update_forbidden') ?? 'Forbidden');
        }

        $isGlobal = $request->boolean('is_global') && optional($user->role)->name === 'superadmin';

        $rawPeers = $request->input('peers', []);
        $cleanPeers = collect($rawPeers)
            ->map(fn($p) => trim((string)$p))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $catalog->update([
            'title' => $request->input('title'),
            'peers' => $cleanPeers,
            'is_global' => $isGlobal,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            $catalog->peers = $this->normalizePeers($catalog->peers);
            return response()->json([
                'success' => true,
                'message' => __('messages.catalogs.updated_success'),
                'catalog' => $catalog->fresh(['user']),
            ]);
        }

        return redirect()->route('catalogs.index', ['department' => $department->id])
            ->with('success', __('messages.catalogs.updated_success'));
    }

    public function destroy(Request $request, Department $department, Catalog $catalog)
    {
        $user = Auth::user();

        if (
            !in_array(optional($user->role)->name, ['superadmin', 'admin'])
            && $catalog->user_id !== $user->id
        ) {

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.catalogs.delete_forbidden') ?? 'Forbidden',
                ], 403);
            }

            abort(403, __('messages.catalogs.delete_forbidden') ?? 'Forbidden');
        }

        $catalog->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('messages.catalogs.deleted_success'),
                'id' => $catalog->id,
            ]);
        }

        return redirect()->route('catalogs.index', ['department' => $department->id])
            ->with('success', __('messages.catalogs.deleted_success'));
    }

    public function removePeer(Request $request)
    {
        $data = $request->validate([
            'catalog_id' => 'required|integer|exists:catalogs,id',
            'peer' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $isSuperadmin = optional($user->role)->name === 'superadmin';

        $catalog = Catalog::where('id', $data['catalog_id'])
            ->when(!$isSuperadmin, fn($q) => $q->where('user_id', $user->id))
            ->first();

        if (!$catalog) {
            return response()->json([
                'success' => false,
                'message' => __('messages.catalogs.not_found_or_not_owned') ?? 'Catalog not found or not owned by you.',
            ], 404);
        }

        $peers = $this->normalizePeers($catalog->peers);
        if (!in_array($data['peer'], $peers)) {
            return response()->json([
                'success' => true,
                'message' => __('messages.catalogs.peer_not_found_no_change') ?? 'Peer not found (no changes).',
                'peers' => $peers,
            ]);
        }

        $updated = array_values(array_diff($peers, [$data['peer']]));
        $catalog->peers = $updated;
        $catalog->save();

        return response()->json([
            'success' => true,
            'message' => __('messages.catalogs.peer_removed') ?? 'Peer removed.',
            'peers' => $updated,
        ]);
    }
}
