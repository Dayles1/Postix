@php
    use Illuminate\Support\Str;

    $peerRaw = trim($peerKey ?? '');
    $peerText = $peerRaw ?: __('messages.layout.unknown_peer');

    if (Str::startsWith($peerText, 'http://') || Str::startsWith($peerText, 'https://')) {
        $peerUrl = $peerText;
        $peerLabel = $peerText;
    } elseif (Str::startsWith($peerText, '@')) {
        $peerUrl = 'https://t.me/' . ltrim($peerText, '@');
        $peerLabel = $peerText;
    } else {
        $peerUrl = null;
        $peerLabel = $peerText;
    }

    $statusColor = in_array($primaryStatus ?? '', ['failed'])
        ? 'bg-red-500'
        : (in_array($primaryStatus ?? '', ['pending', 'scheduled', 'processing'])
            ? 'bg-yellow-500'
            : 'bg-green-500');
@endphp

<div class="peer-item group bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden border border-gray-100 dark:border-gray-700 transition hover:shadow-md"
    data-peer="{{ e($peerText) }}" data-catalog-id="{{ e($catalogId ?? '') }}">

    <div class="peer-toggle px-4 py-4 cursor-pointer" role="button" tabindex="0" aria-expanded="false"
        data-peer="{{ e($peerText) }}">

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">

            <div class="flex items-start gap-3 min-w-0 flex-1">
                <span class="mt-1 inline-flex h-3 w-3 rounded-full shrink-0 {{ $statusColor }}"></span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2 min-w-0">
                        @if ($peerUrl)
                            <a href="{{ $peerUrl }}" target="_blank" rel="noopener noreferrer"
                                class="peer-link font-semibold text-gray-900 dark:text-gray-100 hover:underline truncate max-w-[280px]"
                                title="{{ $peerUrl }}" data-no-toggle="1">
                                {{ $peerLabel }}
                            </a>
                        @else
                            <span class="font-semibold text-gray-900 dark:text-gray-100 truncate max-w-[280px]"
                                data-no-toggle="1">
                                {{ $peerLabel }}
                            </span>
                        @endif

                        <button type="button"
                            class="peer-copy-btn inline-flex h-8 w-8 items-center justify-center rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-500 hover:text-blue-600 hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                            data-copy-text="{{ e($peerUrl ?? $peerText) }}" data-no-toggle="1"
                            title="{{ __('messages.remove_peer.copy') }}">
                            📋
                        </button>

                        <span
                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $inCatalog ? 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}"
                            data-peer-catalog-status data-in-catalog="{{ $inCatalog ? '1' : '0' }}">
                            {{ $inCatalog ? __('messages.remove_peer.in_catalog') : __('messages.remove_peer.removed') }}
                        </span>

                        <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $peerBadgeCls }}">
                            {{ $total }} {{ __('messages.operations.peer_total') }}
                        </span>
                    </div>

                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-700">
                            <strong>{{ __('messages.op_show.scheduled_at') }}:</strong>
                            <span>{{ $firstSendAt ? \Carbon\Carbon::parse($firstSendAt)->format('d-m-Y H:i:s') : '-' }}</span>
                        </span>

                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-700">
                            <strong>{{ __('messages.op_show.actual_end_at') }}:</strong>
                            <span>{{ $lastSentAt ? \Carbon\Carbon::parse($lastSentAt)->format('d-m-Y H:i:s') : ($lastSendAt ? \Carbon\Carbon::parse($lastSendAt)->format('d-m-Y H:i:s') : '-') }}</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 lg:justify-end shrink-0">
                @foreach ($statusCounts as $st => $cnt)
                    @continue($cnt <= 0)
                    @php
                        $badgeStyle = match ($st) {
                            'sent' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                            'failed' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                            'pending', 'scheduled', 'processing' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                            'canceled' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                        };
                    @endphp

                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $badgeStyle }}">
                        {{ $cnt }} {{ $st }}
                    </span>
                @endforeach

                @if ($peerUrl)
                    <a href="{{ $peerUrl }}" target="_blank" rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition text-xs font-medium"
                        data-no-toggle="1" title="{{ __('messages.remove_peer.open_telegram') }}">
                        🔗
                        <span>{{ __('messages.remove_peer.telegram') }}</span>
                    </a>
                @endif

                @if ($inCatalog && $catalogId)
                    <div class="peer-select-wrap shrink-0" data-peer-select-wrap>
                        <label class="peer-select-label inline-flex items-center gap-2 cursor-pointer select-none"
                            data-no-toggle="1">

                            <input type="checkbox" class="peer-select-checkbox sr-only" data-peer="{{ e($peerText) }}"
                                data-catalog-id="{{ e($catalogId ?? '') }}" data-no-toggle="1">

                            <span data-peer-select-pill
                                class="peer-select-pill inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-300 shadow-sm transition hover:border-red-300 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30">
                                <span data-peer-select-icon
                                    class="peer-select-icon inline-flex h-4 w-4 items-center justify-center rounded-md border border-current bg-transparent text-transparent">
                                    <svg viewBox="0 0 20 20" fill="none" class="h-3 w-3" aria-hidden="true">
                                        <path d="M4.5 10.5l3.2 3.2L15.5 6" stroke="currentColor" stroke-width="2.2"
                                            stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </span>

                                <span data-peer-select-text class="peer-select-text text-xs font-medium">
                                    {{ __('messages.op_show2.select') }}
                                </span>
                            </span>
                        </label>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="peer-messages-wrapper hidden border-t border-gray-100 dark:border-gray-700" data-loaded="0"
        data-peer="{{ e($peerText) }}">
        <div class="p-4 text-sm text-gray-500 dark:text-gray-400">
            {{ __('messages.loading') }}
        </div>
    </div>
</div>

<div id="peerRemoveModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/50 px-4"
    aria-hidden="true">
    <div
        class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ __('messages.remove_peer.modal_title') }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ __('messages.remove_peer.modal_description') }}
            </div>
        </div>

        <div class="px-5 py-4">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                {!! __('messages.remove_peer.selected_text', [
    'peer' => '<span id="peerRemoveName" class="font-semibold text-gray-900 dark:text-gray-100"></span>'
]) !!}
            </div>
        </div>

        <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-2">
            <button type="button" id="peerRemoveCancel"
                class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                {{ __('messages.remove_peer.cancel_text') }}
            </button>
            <button type="button" id="peerRemoveConfirm"
                class="px-4 py-2 rounded-xl bg-red-600 text-white hover:bg-red-700 transition">
                {{ __('messages.remove_peer.confirm_text') }}
            </button>
        </div>
    </div>
</div>