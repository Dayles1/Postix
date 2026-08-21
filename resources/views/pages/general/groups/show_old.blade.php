@extends('layouts.app')

@section('title', __('messages.op_show.title', ['id' => $operation->id ?? '']))

@section('page-title')
    {{ __('messages.op_show.title', ['id' => $operation->id ?? '']) }}
@endsection

@section('page-subtitle')
    {{ __('messages.op_show.subtitle') }}
@endsection

@section('content')
@php
    $messagesCollection = $operation->messages ?? collect();
    $allPeers = $messagesCollection->groupBy('peer');
    $currentStatus = request()->get('status', '');

    $statusMap = [
        'sent'       => __('messages.sent'),
        'failed'     => __('messages.failed'),
        'canceled'   => __('messages.canceled'),
        'scheduled'  => __('messages.scheduled'),
        'pending'    => __('messages.pending'),
        'processing' => __('messages.processing'),
    ];

    $fullMessage = $operation->message_text ?? $messagesCollection->first()?->message_text ?? '-';

    $statusOrder = ['sent', 'failed', 'pending', 'scheduled', 'processing', 'canceled'];
    $overallCounts = $messagesCollection
        ->countBy(fn ($m) => $m->status ?? 'unknown')
        ->toArray();

    $totalMessages = max(1, $messagesCollection->count());

    $percentages = [];
    foreach ($statusOrder as $st) {
        $percentages[$st] = round((($overallCounts[$st] ?? 0) / $totalMessages) * 100, 2);
    }

    $renderErrorKey = function (?string $errorKey) {
        if (!$errorKey) {
            return __('messages.failed');
        }

        $directKey = 'messages.errors.' . $errorKey;
        if (trans()->has($directKey)) {
            return __($directKey);
        }

        if (preg_match('/^(slowmode|flood)_wait_(\d+)$/i', $errorKey, $m)) {
            $type = strtolower($m[1]);
            $secondsTotal = (int) $m[2];
            $minutes = intdiv($secondsTotal, 60);
            $seconds = $secondsTotal % 60;

            $transKey = $type === 'slowmode'
                ? 'messages.errors.slowmode_wait'
                : 'messages.errors.flood_wait';

            if (trans()->has($transKey)) {
                return __($transKey, [
                    'minutes' => $minutes,
                    'seconds' => $seconds,
                    'seconds_total' => $secondsTotal
                ]);
            }

            if ($minutes > 0 && $seconds > 0) {
                return __('messages.errors.wait_minutes_seconds', ['minutes' => $minutes, 'seconds' => $seconds]);
            } elseif ($minutes > 0) {
                return __('messages.errors.wait_minutes', ['minutes' => $minutes]);
            }

            return __('messages.errors.wait_seconds', ['seconds' => $secondsTotal]);
        }

        $shortKey = 'messages.errors.' . strtoupper($errorKey);
        if (trans()->has($shortKey)) {
            return __($shortKey);
        }

        $baseKey = 'messages.errors.' . strtolower($errorKey);
        if (trans()->has($baseKey)) {
            return __($baseKey);
        }

        return __('messages.errors.unknown_error');
    };

    $firstScheduled = $messagesCollection->sortBy('send_at')->first();
    $lastScheduled = $messagesCollection->sortByDesc('send_at')->first();
    $lastSent = $messagesCollection->whereNotNull('sent_at')->sortByDesc('sent_at')->first();

    $startedAt = $firstScheduled?->send_at ? \Carbon\Carbon::parse($firstScheduled->send_at) : null;
    $expectedEndAt = $lastScheduled?->send_at ? \Carbon\Carbon::parse($lastScheduled->send_at) : null;
    $endedAt = $lastSent?->sent_at ? \Carbon\Carbon::parse($lastSent->sent_at) : null;

    $isPending = (($operation->status ?? '') === 'pending');

    $topRightTimeLabel = $isPending
        ? __('messages.op_show.expected_end_at')
        : __('messages.op_show.ended_at');

    $topRightTimeValue = $isPending
        ? $expectedEndAt
        : ($endedAt ?: $expectedEndAt);

    $senderPhone = $operation->phone->phone ?? ($operation->phone->number ?? '-');
    $departmentName = $department->name ?? ($operation->phone->user->department->name ?? '-');
    $messageGroupStatus = $operation->status ?? 'unknown';

    $statusBadgeCls = match($messageGroupStatus) {
        'sent' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
        'failed' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        'pending', 'scheduled', 'processing' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
        'canceled' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
    };

    $startedAtText = $startedAt ? $startedAt->format('Y-m-d H:i:s') : '-';
    $topRightTimeText = $topRightTimeValue ? $topRightTimeValue->format('Y-m-d H:i:s') : '-';
@endphp

<div class="min-h-screen py-6 px-4 sm:px-6 lg:px-8" x-data>
    <div class="max-w-7xl mx-auto space-y-6">

        {{-- Header / Summary --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
            <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ __('messages.op_show.title', ['id' => $operation->id ?? '']) }}
                        </h1>

                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $statusBadgeCls }}">
                            {{ $statusMap[$messageGroupStatus] ?? ucfirst($messageGroupStatus) }}
                        </span>
                    </div>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('messages.op_show.subtitle') }}
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 pt-2">
                        <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.op_show.sender_phone') }}</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white truncate">
                                {{ $senderPhone }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.op_show.department') }}</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white truncate">
                                {{ $departmentName }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.op_show.started_at') }}</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $startedAtText }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 p-4">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $topRightTimeLabel }}</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $topRightTimeText }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                            {{ __('messages.op_show.total_messages') }}: {{ $messagesCollection->count() }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                            {{ __('messages.op_show.total_peers') }}: {{ $allPeers->count() }}
                        </span>
                    </div>
                </div>

                {{-- Umumiy statuslar --}}
                <div class="w-full xl:max-w-xl bg-gray-50 dark:bg-gray-900/40 rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
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
                            <button type="button"
                                id="openUpdateModalBtn"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition-colors">
                                <span>✏️</span>
                                <span>{{ __('messages.op_show.update_message') }}</span>
                            </button>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @foreach($statusOrder as $st)
                            @php
                                $count = $overallCounts[$st] ?? 0;
                                $label = $statusMap[$st] ?? ucfirst($st);
                                $badgeCls = match($st) {
                                    'sent' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                                    'failed' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                    'pending', 'scheduled', 'processing' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                                    'canceled' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium {{ $badgeCls }}">
                                <span>{{ $label }}</span>
                                <span class="font-semibold">({{ $count }})</span>
                            </span>
                        @endforeach
                    </div>

                    <div class="mt-4 h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                        <div class="flex h-full w-full">
                            <div class="bg-green-500" style="width: {{ $percentages['sent'] }}%"></div>
                            <div class="bg-red-500" style="width: {{ $percentages['failed'] }}%"></div>
                            <div class="bg-yellow-500" style="width: {{ $percentages['pending'] + $percentages['scheduled'] + $percentages['processing'] }}%"></div>
                            <div class="bg-gray-500" style="width: {{ $percentages['canceled'] }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Full message preview --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ __('messages.op_show.message_preview') }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('messages.op_show.message_preview_subtitle') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    
                    <button id="copyMessageBtn"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <span>📋</span>
                        <span>{{ __('messages.op_show.copy') }}</span>
                    </button>

                    <button id="toggleFullMessageBtn"
                        data-expanded="false"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <span>🔍</span>
                        <span>{{ __('messages.op_show.expand') }}</span>
                    </button>
                </div>
            </div>

            <div class="mt-4 border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-900/50 overflow-hidden">
                <pre id="fullMessageText"
                    class="p-4 m-0 text-sm text-gray-800 dark:text-gray-100 whitespace-pre-wrap break-words max-h-44 overflow-auto">{{ $fullMessage }}</pre>
                <div id="fullMessageOverlay"
                    class="relative -mt-11 h-11 bg-gradient-to-b from-transparent to-gray-50 dark:to-gray-900/50"></div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex items-center gap-3 w-full">
                    <label class="text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                        {{ __('messages.table.status') }}:
                    </label>

                    <select id="status-filter"
                        class="px-3 py-2 border rounded-lg text-sm bg-gray-50 dark:bg-gray-700 dark:border-gray-600 flex-1 text-gray-800 dark:text-gray-100">
                        <option value="">{{ __('messages.op_show.all_statuses') }}</option>
                        @foreach($messagesCollection->pluck('status')->unique() as $status)
                            <option value="{{ $status }}">{{ $statusMap[$status] ?? ucfirst($status) }}</option>
                        @endforeach
                    </select>

                    <input type="text" id="peer-search"
                        placeholder="{{ __('messages.op_show.search_peer_placeholder') }}"
                        class="px-3 py-2 border rounded-lg text-sm w-full sm:w-auto bg-gray-50 dark:bg-gray-700 dark:border-gray-600 text-gray-800 dark:text-gray-100"
                        style="flex:1;" />
                </div>
            </div>
        </div>

        {{-- Peers list --}}
        <div id="peers-list" class="space-y-4">
            @forelse($allPeers as $peer => $peerMsgs)
                @php
                    $peerKey = $peer ?: __('messages.layout.unknown_peer');
                    $peerStatuses = $peerMsgs->pluck('status')->unique()->toArray();
                    $statusCounts = $peerMsgs->countBy(fn($m) => ($m->status ?? 'unknown'))->toArray();
                    $total = array_sum($statusCounts);

                    $peerMainStatus = array_key_first($statusCounts) ?: 'unknown';

                    $peerBadgeCls = match($peerMainStatus) {
                        'sent' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                        'failed' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                        'pending', 'scheduled', 'processing' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                        'canceled' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                    };

                    $peerFirstSend = $peerMsgs->sortBy('send_at')->first()?->send_at;
                    $peerLastSend = $peerMsgs->sortByDesc('send_at')->first()?->send_at;
                    $peerLastSent = $peerMsgs->whereNotNull('sent_at')->sortByDesc('sent_at')->first()?->sent_at;
                @endphp

                @if($peerMsgs->count() > 0)
                    <div class="peer-item bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden border border-gray-100 dark:border-gray-700"
                         x-data="{ open:false }"
                         data-peer="{{ strtolower($peerKey) }}"
                         data-status="{{ implode(',', $peerStatuses) }}"
                         data-status-counts='@json($statusCounts)'
                         data-total="{{ $total }}">

                        <button @click="open = !open" class="w-full px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-left">
                            <div class="flex items-start gap-3 min-w-0">
                                <span class="mt-1 inline-flex h-3 w-3 rounded-full {{ str_contains($peerMainStatus, 'fail') ? 'bg-red-500' : (in_array($peerMainStatus, ['pending','scheduled','processing']) ? 'bg-yellow-500' : 'bg-green-500') }}"></span>

                                <div class="min-w-0">
                                    <div class="flex items-center gap-3 min-w-0 flex-wrap">
                                        <h3 class="font-medium text-gray-900 dark:text-gray-100 truncate max-w-[260px]">
                                            {{ $peerKey }}
                                        </h3>

                                        <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $peerBadgeCls }}">
                                            {{ $total }} {{ __('messages.table.totals') ?? __('messages.messages') }}
                                        </span>
                                    </div>

                                    <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-700">
                                            <strong>{{ __('messages.op_show.scheduled_at') }}:</strong>
                                            <span>{{ $peerFirstSend ? \Carbon\Carbon::parse($peerFirstSend)->format('Y-m-d H:i:s') : '-' }}</span>
                                        </span>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-700">
                                            <strong>{{ $isPending ? __('messages.op_show.expected_end_at') : __('messages.op_show.actual_end_at') }}:</strong>
                                            <span>
                                                @if($isPending)
                                                    {{ $peerLastSend ? \Carbon\Carbon::parse($peerLastSend)->format('Y-m-d H:i:s') : '-' }}
                                                @else
                                                    {{ $peerLastSent ? \Carbon\Carbon::parse($peerLastSent)->format('Y-m-d H:i:s') : ($peerLastSend ? \Carbon\Carbon::parse($peerLastSend)->format('Y-m-d H:i:s') : '-') }}
                                                @endif
                                            </span>
                                        </span>
                                    </div>

                                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        {{ __('messages.peer_messages_count', ['count' => $total]) }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @foreach($statusCounts as $st => $cnt)
                                    @php
                                        $cls = \Illuminate\Support\Str::slug($st);
                                        $label = $statusMap[$st] ?? ucfirst($st);

                                        $badgeStyle = match(true) {
                                            in_array($cls, ['sent','delivered']) => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                                            in_array($cls, ['failed']) => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                            in_array($cls, ['pending','scheduled','processing']) => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                                            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $badgeStyle }}">
                                        {{ $cnt }} {{ $label }}
                                    </span>
                                @endforeach
                            </div>
                        </button>

                        <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                            <div class="p-4 space-y-3 max-h-[28rem] overflow-auto">
                                @foreach($peerMsgs->sortBy('send_at') as $msg)
                                    @php
                                        $failed = (($msg->status ?? '') === 'failed' || !empty($msg->error_key));
                                        $label = $statusMap[$msg->status ?? ''] ?? ucfirst($msg->status ?? 'n/a');
                                        $errorText = $msg->error_key ? $renderErrorKey($msg->error_key) : null;
                                    @endphp

                                    <article class="peer-message bg-gray-50 dark:bg-gray-900 rounded-2xl p-4 border border-gray-100 dark:border-gray-700"
                                             data-msg-status="{{ $msg->status ?? 'unknown' }}">
                                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                                        <strong>ID:</strong> {{ $msg->id }}
                                                    </span>

                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                                        <strong>{{ __('messages.op_show.status') }}:</strong>
                                                        <span class="font-medium">{{ $label }}</span>
                                                    </span>

                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                                        <strong>{{ __('messages.op_show.scheduled_at') }}:</strong>
                                                        <span class="text-gray-500 dark:text-gray-400">
                                                            {{ $msg->send_at ? \Carbon\Carbon::parse($msg->send_at)->format('Y-m-d H:i:s') : '-' }}
                                                        </span>
                                                    </span>

                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                                                        <strong>{{ __('messages.op_show.sent_at') }}:</strong>
                                                        <span class="text-gray-500 dark:text-gray-400">
                                                            {{ $msg->sent_at ? \Carbon\Carbon::parse($msg->sent_at)->format('Y-m-d H:i:s') : '-' }}
                                                        </span>
                                                    </span>
                                                </div>

                                                @if($msg->message)
                                                    <div class="mt-3 text-sm text-gray-800 dark:text-gray-100 whitespace-pre-wrap break-words bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                                                        {{ $msg->message }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="flex-shrink-0 lg:text-right">
                                                @if($failed)
                                                    <div class="text-sm font-semibold text-red-600 dark:text-red-400">
                                                        {{ $errorText ?? __('messages.failed') }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            @empty
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    {{ __('messages.op_show.no_messages') }}
                </div>
            @endforelse
        </div>
    </div>

    {{-- Update Modal --}}
    @if($isPending)
    <div id="updateModal" class="fixed inset-0 z-[99999] hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-[2px]"></div>

        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="w-[32rem] max-w-[90vw] max-h-[90vh] min-w-[28rem] min-h-[28rem] resize overflow-hidden rounded-3xl bg-white dark:bg-gray-900 shadow-2xl border border-gray-100 dark:border-gray-800 flex flex-col">
                <div class="flex items-start justify-between gap-4 p-4 border-b border-gray-100 dark:border-gray-800">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ __('messages.op_show.update_modal_title') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('messages.op_show.update_modal_subtitle') }}
                        </p>
                    </div>

                    <button type="button" id="closeUpdateModalBtn"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        ✕
                    </button>
                </div>

                <form id="updateMessageForm"
                    method="POST"
                    action="{{ route('message-groups.update', $operation->id) }}"
                    class="flex-1 p-4 space-y-4 overflow-y-auto">
                    @csrf
                    @method('PUT')

                    <div class="rounded-2xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-950/30 p-3 text-sm text-amber-800 dark:text-amber-200">
                        {{ __('messages.op_show.update_modal_note') }}
                    </div>

                    <div>
                        <label for="updateMessageInput" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('messages.op_show.new_message_label') }}
                        </label>
                        <textarea id="updateMessageInput" name="message" rows="12"
                            maxlength="3900"
                            class="w-full rounded-2xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 text-sm text-gray-900 dark:text-gray-100 focus:border-brand-500 focus:ring-brand-500 resize-y min-h-[260px]"
                            placeholder="{{ __('messages.op_show.current_message') }}">{{ $operation->message_text ?? '' }}</textarea>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-1">
                        <button type="button" id="cancelUpdateBtn"
                            class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                            {{ __('messages.op_show.cancel') }}
                        </button>

                        <button type="submit"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 transition-colors">
                            <span>💾</span>
                            <span>{{ __('messages.op_show.save_changes') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

    {{-- Toast --}}
    <div x-data="{ open:false, text:'' }" x-init="
        window.showToast = (t)=>{ text=t; open=true; setTimeout(()=>open=false,3500) }
    " class="fixed top-5 right-5 z-50">
        <div x-show="open" x-transition class="px-4 py-2 bg-green-600 text-white rounded-lg shadow-lg" x-text="text"></div>
    </div>
</div>

@push('scripts')
<script>
    function filterPeersByStatus() {
        const search = (document.getElementById('peer-search').value || '').toLowerCase().trim();
        const selectedStatus = document.getElementById('status-filter').value;

        document.querySelectorAll('#peers-list .peer-item').forEach(el => {
            const peer = (el.dataset.peer || '').toLowerCase();
            const total = parseInt(el.dataset.total || '0');
            let counts = {};

            try {
                counts = el.dataset.statusCounts ? JSON.parse(el.dataset.statusCounts) : {};
            } catch (e) {
                counts = {};
            }

            const matchesPeer = peer.includes(search);

            let matchesStatus = true;
            if (selectedStatus) {
                const cnt = parseInt(counts[selectedStatus] || 0);
                matchesStatus = (total > 0 && cnt === total);
            }

            const showPeer = matchesPeer && matchesStatus;
            el.style.display = showPeer ? '' : 'none';

            const messages = el.querySelectorAll('.peer-message');
            messages.forEach(msgEl => {
                const msgStatus = (msgEl.dataset.msgStatus || '').toString();
                if (!selectedStatus) {
                    msgEl.style.display = '';
                } else {
                    msgEl.style.display = (msgStatus === selectedStatus) ? '' : 'none';
                }
            });
        });
    }

    document.getElementById('peer-search')?.addEventListener('input', filterPeersByStatus);
    document.getElementById('status-filter')?.addEventListener('change', filterPeersByStatus);

    document.addEventListener('DOMContentLoaded', function () {
        filterPeersByStatus();

        const copyBtn = document.getElementById('copyMessageBtn');
        const toggleBtn = document.getElementById('toggleFullMessageBtn');
        const pre = document.getElementById('fullMessageText');
        const overlay = document.getElementById('fullMessageOverlay');

        copyBtn?.addEventListener('click', async function () {
            const text = pre?.innerText?.trim() || '';
            try {
                await navigator.clipboard.writeText(text);
                window.showToast?.('{{ __("messages.copied") }}');
            } catch (e) {
                window.showToast?.('{{ __("messages.copy_failed") }}');
            }
        });

        toggleBtn?.addEventListener('click', function () {
            const expanded = this.getAttribute('data-expanded') === 'true';

            if (!expanded) {
                pre.style.maxHeight = 'none';
                overlay.style.display = 'none';
                this.setAttribute('data-expanded', 'true');
                this.innerHTML = '<span>🔎</span><span>{{ __("messages.op_show.collapse") }}</span>';
            } else {
                pre.style.maxHeight = '11rem';
                overlay.style.display = 'block';
                this.setAttribute('data-expanded', 'false');
                this.innerHTML = '<span>🔍</span><span>{{ __("messages.op_show.expand") }}</span>';
            }
        });

        const openBtn = document.getElementById('openUpdateModalBtn');
        const modal = document.getElementById('updateModal');
        const closeBtn = document.getElementById('closeUpdateModalBtn');
        const cancelBtn = document.getElementById('cancelUpdateBtn');
        const textarea = document.getElementById('updateMessageInput');

        function openModal() {
            if (!modal) return;
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => textarea?.focus(), 50);
        }

        function closeModal() {
            if (!modal) return;
            modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        openBtn?.addEventListener('click', openModal);
        closeBtn?.addEventListener('click', closeModal);
        cancelBtn?.addEventListener('click', closeModal);

        modal?.addEventListener('click', function (e) {
            if (e.target === modal || e.target.classList.contains('bg-black/50')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    });
</script>
@endpush
@endsection