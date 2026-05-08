<?php

namespace App\Http\Controllers\View\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogRequest;
use App\Models\Catalog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CatalogController extends Controller
{
    protected function normalizePeers($peers): array
    {
        if (is_null($peers)) {
            return [];
        }

        if (is_string($peers)) {
            $decoded = json_decode($peers, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map('trim', $decoded)));
            }

            $parts = preg_split('/(?:\r?\n|,|;|\s)+/', trim($peers));
            $parts = array_filter(array_map(
                fn ($p) => $p ? (str_starts_with($p, '@') ? $p : '@' . $p) : null,
                $parts
            ));

            return array_values(array_unique($parts));
        }

        if (is_array($peers) || $peers instanceof \Illuminate\Support\Collection) {
            return array_values(array_filter(array_unique(array_map(
                fn ($p) => is_null($p) ? null : trim((string) $p),
                (array) $peers
            ))));
        }

        return [];
    }

    protected function ensureSuperadmin(): void
    {
        $user = Auth::user();

        abort_unless(optional($user->role)->name === 'superadmin', 403, 'Forbidden');
    }

    public function index(Request $request)
    {
        $this->ensureSuperadmin();

        $search = $request->get('search');

        $query = Catalog::with(['user.role'])
            ->whereHas('user.role', function ($q) {
                $q->where('name', 'superadmin');
            });

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

        $catalogs->getCollection()->transform(function ($catalog) {
            $catalog->peers = $this->normalizePeers($catalog->peers);
            return $catalog;
        });

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

        return view('pages.admin.catalog', compact('catalogs'));
    }

    public function store(CatalogRequest $request)
    {
        $this->ensureSuperadmin();

        $user = Auth::user();
        $isGlobal = $request->boolean('is_global');

        $rawPeers = $request->input('peers', []);
        $cleanPeers = collect($rawPeers)
            ->map(fn ($p) => trim((string) $p))
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
                'catalog' => $catalog->fresh(['user.role']),
            ], 201);
        }

        return redirect()
            ->route('admin.catalogs.index')
            ->with('success', __('messages.catalogs.created_success'));
    }

    public function show(Catalog $catalog, Request $request)
    {
        $this->ensureSuperadmin();

        $catalog->load(['user.role']);

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

        return redirect()->route('admin.catalogs.index');
    }

    public function update(CatalogRequest $request, Catalog $catalog)
    {
        $this->ensureSuperadmin();

        $user = Auth::user();
        $isGlobal = $request->boolean('is_global');

        $rawPeers = $request->input('peers', []);
        $cleanPeers = collect($rawPeers)
            ->map(fn ($p) => trim((string) $p))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $catalog->update([
            'title' => $request->input('title'),
            'peers' => $cleanPeers,
            'is_global' => $isGlobal,
            'user_id' => $catalog->user_id ?: $user->id,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            $catalog->peers = $this->normalizePeers($catalog->peers);

            return response()->json([
                'success' => true,
                'message' => __('messages.catalogs.updated_success'),
                'catalog' => $catalog->fresh(['user.role']),
            ]);
        }

        return redirect()
            ->route('admin.catalogs.index')
            ->with('success', __('messages.catalogs.updated_success'));
    }

    public function destroy(Request $request, Catalog $catalog)
    {
        $this->ensureSuperadmin();

        $catalog->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => __('messages.catalogs.deleted_success'),
                'id' => $catalog->id,
            ]);
        }

        return redirect()
            ->route('admin.catalogs.index')
            ->with('success', __('messages.catalogs.deleted_success'));
    }

    public function removePeer(Request $request)
    {
        $this->ensureSuperadmin();

        $data = $request->validate([
            'catalog_id' => 'required|integer|exists:catalogs,id',
            'peer' => 'required|string|max:255',
        ]);

        $catalog = Catalog::find($data['catalog_id']);

        if (!$catalog) {
            return response()->json([
                'success' => false,
                'message' => __('messages.catalogs.not_found') ?? 'Catalog not found.',
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