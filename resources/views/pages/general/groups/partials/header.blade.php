@php
    $status = $operation->status ?? 'unknown';

    $statusBadgeCls = match ($status) {
        'sent' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
        'failed' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        'pending', 'scheduled', 'processing' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
        'canceled' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    };

    $sentCount = (int) ($stats->sent_count ?? 0);
    $failedCount = (int) ($stats->failed_count ?? 0);
    $pendingCount = (int) ($stats->pending_count ?? 0);
    $scheduledCount = (int) ($stats->scheduled_count ?? 0);
    $processingCount = (int) ($stats->processing_count ?? 0);
    $canceledCount = (int) ($stats->canceled_count ?? 0);

    $totalMessages = max(1, (int) ($stats->total_messages ?? 0));

    $sentPercent = round(($sentCount / $totalMessages) * 100, 2);
    $failedPercent = round(($failedCount / $totalMessages) * 100, 2);
    $pendingPercent = round((($pendingCount + $scheduledCount + $processingCount) / $totalMessages) * 100, 2);
    $canceledPercent = round(($canceledCount / $totalMessages) * 100, 2);

    $totalPeers = (int) ($stats->total_peers ?? 0);
@endphp

<div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
    <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
        <div class="space-y-3 w-full">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ __('messages.op_show.title', ['id' => $operation->id ?? '']) }}
                </h1>

                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $statusBadgeCls }}">
                    {{ $statusMap[$operation->status ?? 'unknown'] ?? ucfirst($operation->status ?? 'unknown') }}
                </span>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('messages.op_show.subtitle') }}
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 pt-2">
                <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.op_show.sender_phone') }}
                    </div>
                    <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white truncate">
                        {{ $senderPhone ?? '-' }}</div>
                </div>

                <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.op_show.department') }}</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white truncate">
                        {{ $departmentName ?? '-' }}</div>
                </div>

                <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.op_show.started_at') }}</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $firstSendAt ? $firstSendAt->format('d-m-Y H:i:s') : '-' }}
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $isPending ? __('messages.op_show.expected_end_at') : __('messages.op_show.ended_at') }}
                    </div>
                    <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $topRightTimeValue ? $topRightTimeValue->format('d-m-Y H:i:s') : '-' }}
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 pt-1">
                <span
                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                    {{ __('messages.op_show.total_messages') }}: {{ (int) ($stats->total_messages ?? 0) }}
                </span>
                <span
                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                    {{ __('messages.op_show.total_peers') }}: {{ $totalPeers }}
                </span>
            </div>
        </div>

        <div
            class="w-full xl:max-w-xl bg-gray-50 dark:bg-gray-900/40 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ __('messages.overall_statuses') }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('messages.overall_statuses_subtitle') }}
                    </div>
                </div>

                @if($isPending)
                    <button type="button" id="openUpdateModalBtn"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition-colors">
                        <span>✏️</span>
                        <span>{{ __('messages.op_show.update_message') }}</span>
                    </button>
                @endif
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach(['sent', 'failed', 'pending', 'scheduled', 'processing', 'canceled'] as $st)
                    @php
                        $count = (int) ($stats->{$st . '_count'} ?? 0);
                        $badgeCls = match ($st) {
                            'sent' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                            'failed' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                            'pending', 'scheduled', 'processing' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                            'canceled' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                        };
                    @endphp

                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium {{ $badgeCls }}">
                        <span>{{ $statusMap[$st] ?? ucfirst($st) }}</span>
                        <span class="font-semibold">({{ $count }})</span>
                    </span>
                @endforeach
            </div>
            <div class="mt-4 h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="flex h-full w-full">
                    <div class="bg-green-500" style="width: {{ $sentPercent }}%"></div>
                    <div class="bg-red-500" style="width: {{ $failedPercent }}%"></div>
                    <div class="bg-yellow-500" style="width: {{ $pendingPercent }}%"></div>
                    <div class="bg-gray-500" style="width: {{ $canceledPercent }}%"></div>
                </div>
            </div>
        </div>
    </div>
    @if ($totalPeers > 0)
    <div class="mt-3 flex flex-col gap-2">
        <div class="flex justify-end">
            <button type="button"
                id="removeSelectedPeersBtn"
                class="hidden w-full sm:w-auto inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-red-600 text-white hover:bg-red-700 transition text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                <span>🗑</span>
                <span>{{ __('messages.op_show2.remove_selected') }}</span>
                <span id="selectedPeersCount"
                    class="ml-1 text-xs font-semibold bg-white/20 px-2 py-0.5 rounded-full">0</span>
            </button>
        </div>

        <span id="peerSelectionHint"
            class="w-full text-xs text-gray-500 dark:text-gray-400 sm:text-right">
            {{ __('messages.op_show2.select_hint') }}
        </span>
    </div>
@endif
</div>