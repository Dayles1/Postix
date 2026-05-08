<div class="peers-list space-y-3">
    @forelse($peers as $peer)
        @include('pages.general.groups.partials.peer-card', [
            'peerKey' => $peer->peerKey ?? '',
            'catalogId' => $peer->catalogId ?? null,
            'inCatalog' => $peer->inCatalog ?? false,
            'peerUrl' => $peer->peerUrl ?? null,
            'peerLabel' => $peer->peerLabel ?? ($peer->peerKey ?? ''),
            'peerBadgeCls' => $peer->peerBadgeCls ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
            'statusCounts' => $peer->statusCounts ?? [],
            'primaryStatus' => $peer->primaryStatus ?? 'unknown',
            'total' => $peer->total ?? 0,
            'firstSendAt' => $peer->firstSendAt ?? null,
            'lastSendAt' => $peer->lastSendAt ?? null,
            'lastSentAt' => $peer->lastSentAt ?? null,
            'tg_link' => $peer->tg_link ?? null,
            'failed_keys' => $peer->failed_keys ?? [],
        ])
    @empty
        <div class="peers-empty bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 text-center text-sm text-gray-500 dark:text-gray-400">
            -
        </div>
    @endforelse
</div>

@if((method_exists($peers, 'hasMorePages') && $peers->hasMorePages()) || ($hasMore ?? false))
    <div class="peers-pagination flex justify-center pt-4">
        <button
            type="button"
            class="peer-page-btn px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            data-page="{{ method_exists($peers, 'currentPage') ? $peers->currentPage() + 1 : (($page ?? 1) + 1) }}">
            {{ __('messages.load_more') }}
        </button>
    </div>
@endif
