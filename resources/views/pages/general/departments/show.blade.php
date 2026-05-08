@extends('layouts.app')

@section('title',__('messages.admin.main'))

@section('content')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        use Carbon\Carbon;

        $uD = $department->type == 'user';

        $departmentUser = null;
        if ($uD) {
            $firstUser = $department->users->first();
            if ($firstUser) {
                $firstUser->load('ban');
                $departmentUser = $firstUser;
            }
        }

        $userBanModel = $departmentUser?->ban ?? null;
        $userIsBanned = (bool) ($userBanModel?->active ?? false);

        $userBanStartsAtRaw = $userBanModel?->starts_at ?? null;
        $userBanEndsAtRaw = data_get($userBanModel, 'ends_at');
        $userBanCreatedAtRaw = $userBanModel?->created_at ?? null;

        $userBanStartsAt = !empty($userBanStartsAtRaw) ? Carbon::parse($userBanStartsAtRaw)->format('Y-m-d, H:i') : '';
        $userBanEndsAt = !empty($userBanEndsAtRaw) ? Carbon::parse($userBanEndsAtRaw)->format('Y-m-d, H:i') : '';
        $userBanCreatedAt = !empty($userBanCreatedAtRaw) ? Carbon::parse($userBanCreatedAtRaw)->format('Y-m-d, H:i') : '';

        $userBanScheduled = !empty($userBanStartsAtRaw) ? Carbon::parse($userBanStartsAtRaw)->gt(now()) : false;

        $departmentBanActive = !empty($banMeta['isBannedActive']);
        $departmentBanScheduled = !empty($banMeta['isScheduled']);

        $departmentBanStartsAt = $banMeta['startsAt'] ?? '';
        $departmentBanUntilAt = $banMeta['untilAt'] ?? '';

        $currentBanType = $uD ? 'user' : 'department';
        $currentBanId = $uD ? ($departmentUser?->id ?? $department->id) : $department->id;
    @endphp

    <div class="mx-auto max-w-7xl p-4 space-y-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('messages.admin.main') }}</h1>

                @if($uD)
                    <div id="ban-badge-user" class="mt-2">
                        @if($userIsBanned)
                            @if(!empty($userBanEndsAt))
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200">
                                    {{ __('messages.ban.banned_from_to', ['starts' => $userBanStartsAt ?: ($userBanCreatedAt ?: '-'), 'until' => $userBanEndsAt ?: '-']) }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200">
                                    {{ __('messages.ban.banned_from', ['starts' => $userBanStartsAt ?: ($userBanCreatedAt ?: '-')]) }}
                                </span>
                            @endif
                        @elseif($userBanScheduled)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200">
                                {{ __('messages.scheduled_will_be', ['date' => $userBanStartsAt ?: '-']) }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-200">
                                {{ __('messages.ban.not_banned') }}
                            </span>
                        @endif
                    </div>
                @else
                    <div id="ban-badge" class="mt-2">
                        @if($departmentBanActive)
                            @if(!empty($departmentBanUntilAt))
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200">
                                    {{ __('messages.ban.banned_from_to', ['starts' => $departmentBanStartsAt ?? '-', 'until' => $departmentBanUntilAt ?? '-']) }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200">
                                    {{ __('messages.ban.banned_from', ['starts' => $departmentBanStartsAt ?? '-']) }}
                                </span>
                            @endif
                        @elseif($departmentBanScheduled)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200">
                                {{ __('messages.scheduled_will_be', ['date' => $departmentBanStartsAt ?? '-']) }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-200">
                                {{ __('messages.ban.not_banned') }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- ACTIONS --}}
            @if(!$uD)
                <div id="ban-actions" class="flex items-center gap-2">
                    @if(!empty($permissions['canBanDepartment']))
                        @if($departmentBanActive)
                            <button
                                id="unbanBtn"
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-white bg-gray-600 rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                                data-bannable-type="department"
                                data-bannable-id="{{ $department->id }}"
                            >
                                {{ __('messages.admin.unban') }}
                            </button>
                        @else
                            <button
                                id="openBanBtn"
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                data-bannable-type="department"
                                data-bannable-id="{{ $department->id }}"
                            >
                                {{ __('messages.admin.ban') }}
                            </button>
                        @endif
                    @endif
                </div>
            @else
                <div id="ban-actions-user" class="flex items-center gap-2">
                    @if(!empty($permissions['canBanDepartment']) || !empty($permissions['canBanUser']))
                        @if($userIsBanned)
                            <button
                                id="unbanUserBtn"
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-white bg-gray-600 rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                                data-bannable-type="user"
                                data-bannable-id="{{ $departmentUser?->id }}"
                            >
                                {{ __('messages.admin.unban') }}
                            </button>
                        @else
                            <button
                                id="openUserBanBtn"
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                data-bannable-type="user"
                                data-bannable-id="{{ $departmentUser?->id }}"
                            >
                                {{ __('messages.admin.ban') }}
                            </button>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @if ($usersCount)
                <div class="p-6 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('messages.admin.users') }}</dt>
                    <dd class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">{{ $usersCount }}</dd>
                </div>
            @endif

            <div class="p-6 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('messages.admin.operations') }}</dt>
                <dd class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">{{ $messageGroupsCount }}</dd>
            </div>

            <div class="p-6 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('messages.admin.messages_count') }}</dt>
                <dd class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">{{ $telegramMessagesCount }}</dd>
            </div>
        </div>

        {{-- Filters --}}
        <form method="get" action="{{ url()->current() }}"
            class="p-6 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <input name="q" value="{{ $filters['q'] ?? '' }}" type="text"
                    placeholder="{{ __('messages.search_messages2') }}"
                    class="flex-1 w-full px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" />

                @if(!$permissions['isUser'])
                    <select name="user_id"
                        class="w-full sm:w-auto px-4 py-2 text-sm border border-gray-300 rounded-lg
                               focus:ring-blue-500 focus:border-blue-500
                               dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500
                               max-h-48 overflow-y-auto">
                        <option value="">{{ __('messages.find.filter_all_users') }}</option>
                        @foreach ($usersForFilter as $u)
                            <option value="{{ $u->id }}" @if (($filters['selected_user_id'] ?? '') == $u->id) selected @endif>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <input name="from" id="filter-from" type="text" value="{{ $filters['from'] ?? '' }}"
                    placeholder="{{ __('messages.from') }}"
                    class="w-full sm:w-auto px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" />

                <input name="to" id="filter-to" type="text" value="{{ $filters['to'] ?? '' }}"
                    placeholder="{{ __('messages.to') }}"
                    class="w-full sm:w-auto px-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" />

                <button type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    {{ __('messages.operations.btn_search') }}
                </button>
                <a href="{{ url()->current() }}"
                    class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                    {{ __('messages.reset') }}
                </a>
            </div>
        </form>

        {{-- Table --}}
        <div class="overflow-hidden bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('messages.active_message_groups') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __('messages.table.id') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __('messages.table.user') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __('messages.table.text') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __('messages.table.catalogs') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __('messages.table.totals') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __('messages.table.status') }}</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">{{ __('messages.table.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($operations as $g)
                            @php
                                $text = $g->message_text ?? '';
                                $short = \Illuminate\Support\Str::limit($text, 60);

                                $status = $g->group_status ?? 'unknown';
                                $statusClasses = [
                                    'sent' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200',
                                    'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200',
                                    'canceled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                    'scheduled' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200',
                                    'pending' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-200',
                                    'processing' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-200',
                                    'unknown' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                ];

                                $payload = json_encode([
                                    'text'  => $text,
                                    'id'    => $g->id,
                                    'user'  => $g->user_name ?? '-',
                                    'phone' => $g->phone ?? '-',
                                ], JSON_HEX_APOS | JSON_HEX_QUOT);
                            @endphp
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $g->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $g->user_name ?? '-' }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $g->phone ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-gray-300">
                                        {{ $short }}
                                        @if (mb_strlen($text) > 60)
                                            <button type="button"
                                                    data-payload="{{ $payload }}"
                                                    onclick="openMessageModalFromEl(this)"
                                                    class="ml-2 text-blue-600 hover:text-blue-800 font-medium underline decoration-dotted">
                                                {{ __('messages.view_full') }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    @if (!empty($g->catalogs))
                                        @foreach ($g->catalogs as $c)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 mr-1">{{ $c }}</span>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ __('messages.operations.total') }}: <span class="font-semibold">{{ $g->totals['total_messages'] ?? 0 }}</span></div>
                                    <div>{{ __('messages.operations.total_sent') }}: <span class="font-semibold">{{ $g->totals['counts_by_status']['sent'] ?? 0 }}</span></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClasses[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ __("messages.{$status}") }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ url('/operations/'. $g->id) }}"
                                        class="text-indigo-600 hover:text-indigo-900">{{ __('messages.show') }}</a>
                                    @if ($permissions['isSuperadmin'] || $permissions['isAdmin'] || (isset($g->user_id) && $g->user_id == auth()->id()))
                                        <form action="{{ url('/message-groups/' . $g->id . '/cancel') }}" method="post" class="inline ml-4">
                                            @csrf
                                            <button type="submit" class="text-red-600 hover:text-red-900">{{ __('messages.cancel') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                    {{ __('messages.no_groups_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @php
                $current = $operations_meta['current_page'] ?? 1;
                $last = $operations_meta['last_page'] ?? 1;
            @endphp
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <a href="{{ request()->fullUrlWithQuery(['page' => max(1, $current - 1)]) }}"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600 @if ($current <= 1) opacity-50 pointer-events-none @endif">
                        {{ __('messages.previous') }}
                    </a>
                    <div class="hidden sm:flex items-center gap-1">
                        @for ($p = 1; $p <= $last; $p++)
                            @if ($p == 1 || $p == $last || ($p >= $current - 1 && $p <= $current + 1))
                                <a href="{{ request()->fullUrlWithQuery(['page' => $p]) }}"
                                    class="flex items-center justify-center h-8 w-8 text-sm font-medium rounded-lg @if ($p == $current) bg-blue-600 text-white @else text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-600 @endif">{{ $p }}</a>
                            @elseif($p == 2 || $p == $last - 1)
                                <span class="flex items-center justify-center h-8 w-8 text-gray-500 dark:text-gray-400">...</span>
                            @endif
                        @endfor
                    </div>
                    <a href="{{ request()->fullUrlWithQuery(['page' => min($last, $current + 1)]) }}"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600 @if ($current >= $last) opacity-50 pointer-events-none @endif">
                        {{ __('messages.next') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Full Message Modal (HISTORY uslubida – mobil uchun to‘liq mos) --}}
        <div id="messageModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
            <div id="messageModalOverlay" class="absolute inset-0 bg-black/50"></div>

            <div class="relative w-full max-w-3xl
                        min-h-[400px]
                        max-h-[85vh]
                        bg-white rounded-xl shadow-xl p-6 flex flex-col dark:bg-gray-800 overflow-hidden">

                <button id="messageModalClose"
                    class="absolute top-4 right-4 text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400"
                    aria-label="Close">
                    ✕
                </button>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-white shrink-0">
                    {{ __('messages.operations.title') }} — ID: <span id="messageId"></span>
                </h3>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 shrink-0">
                    <strong>{{ __('messages.by_user') }}:</strong>
                    <span id="messageUser"></span> (<span id="messagePhone"></span>)
                </p>

                <div id="messageText"
                     class="mt-4 flex-1 overflow-y-auto text-sm text-gray-900 dark:text-gray-200 whitespace-pre-wrap pr-4 custom-scrollbar">
                </div>

                <div class="mt-6 flex justify-end shrink-0">
                    <button id="messageModalCloseBtn"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                        {{ __('messages.close') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Ban Modal --}}
        <div id="banModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
            <div id="banModalOverlay" class="absolute inset-0 bg-black/50"></div>
            <form id="banForm" class="relative w-full max-w-md bg-white rounded-xl shadow-xl p-6 dark:bg-gray-800">
                <button type="button" id="banModalClose"
                    class="absolute top-4 right-4 text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400"
                    aria-label="Close">
                    ✕
                </button>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('messages.ban_department') }}</h3>
                <input type="hidden" name="bannable_type" id="ban_bannable_type" value="{{ $currentBanType }}">
                <input type="hidden" name="bannable_id" id="ban_bannable_id" value="{{ $currentBanId }}">
                <div class="mt-4 space-y-4">
                    <div>
                        <label for="ban_starts" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('messages.starts_at') }}</label>
                        <input id="ban_starts" name="starts_at" type="text" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('messages.ban.indefinite') }}</p>
                    </div>
                    <div>
                        <label for="ban_until" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('messages.until_optional') }}</label>
                        <input id="ban_until" name="until" type="text" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('messages.ban.indefinite') }}</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" id="banCancel"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                        {{ __('messages.cancel') }}
                    </button>
                    <button type="submit" id="banSubmit"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        {{ __('messages.admin.ban') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- Toast --}}
        <div id="simpleToast" class="fixed top-4 right-4 z-50 hidden">
            <div id="simpleToastInner" class="px-6 py-3 rounded-lg shadow-lg text-white"></div>
        </div>
    </div>

    <script>
        function openMessageModalFromEl(el) {
            const payload = JSON.parse(el.dataset.payload);
            openMessageModal(payload.text, payload.id, payload.user, payload.phone);
        }

        const getCsrfToken = () => {
            const el = document.querySelector('meta[name="csrf-token"]');
            return el ? el.getAttribute('content') : '';
        };

        async function request({
            method = 'get',
            url,
            data = null,
            headers = {},
            showToast = null,
            onSuccess = null,
            onError = null,
        }) {
            try {
                const config = {
                    method: method.toLowerCase(),
                    url,
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Accept': 'application/json',
                        ...headers,
                    },
                    withCredentials: true,
                };

                if (data !== null) {
                    config.data = data;
                }

                const res = await axios(config);

                if (res?.data?.message && typeof showToast === 'function') {
                    showToast(res.data.message, 'success');
                }

                if (typeof onSuccess === 'function') onSuccess(res.data, res);

                return res.data;
            } catch (error) {
                let payload = {
                    ok: false,
                    status: error?.response?.status ?? null,
                    message: error?.response?.data?.message ?? error.message,
                    errors: error?.response?.data?.errors ?? null,
                    raw: error,
                };

                if (typeof showToast === 'function') {
                    showToast(payload.message || '{{ __('messages.error_occurred') }}', 'error');
                }
                if (typeof onError === 'function') onError(payload);

                throw payload;
            }
        }

        const i18n = {
            banned_from_to: '{{ __('messages.ban.banned_from_to') }}',
            banned_from: '{{ __('messages.ban.banned_from') }}',
            scheduled: '{{ __('messages.scheduled') }}',
            not_banned: '{{ __('messages.ban.not_banned') }}',
            indefinite: '{{ __('messages.ban.indefinite') }}'
        };

        function format(template, vars) {
            let out = template + '';
            for (const k in vars) {
                out = out.replaceAll(':' + k, vars[k] ?? '');
            }
            return out;
        }

        function showToast(message, type = 'success', timeout = 3500) {
            const toast = document.getElementById('simpleToast');
            const inner = document.getElementById('simpleToastInner');
            inner.textContent = message;
            inner.className = 'px-6 py-3 rounded-lg shadow-lg text-white ' + (type === 'success' ? 'bg-green-600' : 'bg-red-600');
            toast.classList.remove('hidden');
            if (toast._timeout) clearTimeout(toast._timeout);
            toast._timeout = setTimeout(() => toast.classList.add('hidden'), timeout);
        }

        function lockScroll() {
            document.documentElement.classList.add('overflow-hidden');
        }

        function unlockScroll() {
            document.documentElement.classList.remove('overflow-hidden');
        }

        flatpickr('#filter-from', { dateFormat: "Y-m-d", allowInput: true });
        flatpickr('#filter-to', { dateFormat: "Y-m-d", allowInput: true });

        function initBanPickers() {
            flatpickr('#ban_starts', {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                defaultDate: new Date()
            });
            flatpickr('#ban_until', {
                enableTime: true,
                dateFormat: "Y-m-d H:i"
            });
        }

        function openMessageModal(text, id, user, phone) {
            document.getElementById('messageId').textContent = id;
            document.getElementById('messageUser').textContent = user;
            document.getElementById('messagePhone').textContent = phone;
            document.getElementById('messageText').textContent = text;
            document.getElementById('messageModal').classList.remove('hidden');
            lockScroll();
        }

        function closeMessageModal() {
            document.getElementById('messageModal').classList.add('hidden');
            unlockScroll();
        }

        function openBanModal() {
            document.getElementById('banModal').classList.remove('hidden');
            lockScroll();
            initBanPickers();
        }

        function closeBanModal() {
            document.getElementById('banModal').classList.add('hidden');
            unlockScroll();
        }

        function getTargetConfig(type) {
            return type === 'user'
                ? {
                    badgeId: 'ban-badge-user',
                    actionsId: 'ban-actions-user',
                }
                : {
                    badgeId: 'ban-badge',
                    actionsId: 'ban-actions',
                };
        }

        function bindBanActionButtons() {
            const deptOpen = document.getElementById('openBanBtn');
            const deptUnban = document.getElementById('unbanBtn');
            const userOpen = document.getElementById('openUserBanBtn');
            const userUnban = document.getElementById('unbanUserBtn');

            if (deptOpen) deptOpen.addEventListener('click', onOpenClick);
            if (deptUnban) deptUnban.addEventListener('click', onUnbanClick);
            if (userOpen) userOpen.addEventListener('click', onOpenClick);
            if (userUnban) userUnban.addEventListener('click', onUnbanClick);
        }

        let lastTarget = {
            type: @json($currentBanType),
            id: @json((string) $currentBanId),
        };

        function updateHeader(banMeta) {
            const type = banMeta.bannable_type || lastTarget.type || 'department';
            const targetId = banMeta.bannable_id || lastTarget.id;
            const cfg = getTargetConfig(type);

            const badgeContainer = document.getElementById(cfg.badgeId);
            const actionsContainer = document.getElementById(cfg.actionsId);

            const isBanned = !!banMeta.isBannedActive;
            const isScheduled = !!banMeta.isScheduled;
            const starts = banMeta.startsAt || '-';
            const until = banMeta.untilAt || '-';

            let badgeHtml = '';
            if (isBanned) {
                if (until !== '-') {
                    badgeHtml = `<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200">${format(i18n.banned_from_to, {starts, until})}</span>`;
                } else {
                    badgeHtml = `<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-200">${format(i18n.banned_from, {starts})}</span>`;
                }
            } else if (isScheduled) {
                badgeHtml = `<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200">${format(i18n.scheduled, {starts})}</span>`;
            } else {
                badgeHtml = `<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-200">${i18n.not_banned}</span>`;
            }

            if (badgeContainer) {
                badgeContainer.innerHTML = badgeHtml;
            }

            if (actionsContainer) {
                if (isBanned) {
                    actionsContainer.innerHTML = `
                        <button id="${type === 'user' ? 'unbanUserBtn' : 'unbanBtn'}" type="button"
                            class="px-4 py-2 text-sm font-medium text-white bg-gray-600 rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                            data-bannable-type="${type}" data-bannable-id="${targetId}">
                            {{ __('messages.admin.unban') }}
                        </button>
                    `;
                } else {
                    actionsContainer.innerHTML = `
                        <button id="${type === 'user' ? 'openUserBanBtn' : 'openBanBtn'}" type="button"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                            data-bannable-type="${type}" data-bannable-id="${targetId}">
                            {{ __('messages.admin.ban') }}
                        </button>
                    `;
                }
            }

            bindBanActionButtons();
        }

        async function onBanSubmit(e) {
            e.preventDefault();

            const payload = {
                bannable_type: document.getElementById('ban_bannable_type').value,
                bannable_id: parseInt(document.getElementById('ban_bannable_id').value, 10),
                action: 'ban',
                starts_at: document.getElementById('ban_starts').value || null,
                until: document.getElementById('ban_until').value || null,
            };

            lastTarget = {
                type: payload.bannable_type,
                id: String(payload.bannable_id),
            };

            try {
                const res = await request({
                    method: 'post',
                    url: '/admin/ban-unban',
                    data: payload,
                    showToast,
                });

                updateHeader(res.banMeta || {
                    bannable_type: payload.bannable_type,
                    bannable_id: payload.bannable_id,
                    isBannedActive: true,
                    isScheduled: false,
                    startsAt: payload.starts_at,
                    untilAt: payload.until
                });

                closeBanModal();
            } catch (err) {
                console.error(err);
            }
        }

        async function onUnbanClick(e) {
            const btn = e.currentTarget;

            lastTarget = {
                type: btn.dataset.bannableType || 'department',
                id: String(btn.dataset.bannableId || ''),
            };

            const payload = {
                bannable_type: btn.dataset.bannableType,
                bannable_id: parseInt(btn.dataset.bannableId, 10),
                action: 'unban'
            };

            try {
                const res = await request({
                    method: 'post',
                    url: '/admin/ban-unban',
                    data: payload,
                    showToast,
                });

                updateHeader(res.banMeta || {
                    bannable_type: payload.bannable_type,
                    bannable_id: payload.bannable_id,
                    isBannedActive: false,
                    isScheduled: false,
                    startsAt: null,
                    untilAt: null
                });
            } catch (err) {
                console.error(err);
            }
        }

        function onOpenClick(e) {
            const btn = e.currentTarget;

            lastTarget = {
                type: btn.dataset.bannableType || 'department',
                id: String(btn.dataset.bannableId || ''),
            };

            document.getElementById('ban_bannable_type').value = btn.dataset.bannableType;
            document.getElementById('ban_bannable_id').value = btn.dataset.bannableId;
            document.getElementById('ban_starts').value = '';
            document.getElementById('ban_until').value = '';
            openBanModal();
        }

        document.getElementById('messageModalOverlay').addEventListener('click', closeMessageModal);
        document.getElementById('messageModalClose').addEventListener('click', closeMessageModal);
        document.getElementById('messageModalCloseBtn').addEventListener('click', closeMessageModal);

        document.getElementById('banModalOverlay').addEventListener('click', closeBanModal);
        document.getElementById('banModalClose').addEventListener('click', closeBanModal);
        document.getElementById('banCancel').addEventListener('click', closeBanModal);
        document.getElementById('banForm').addEventListener('submit', onBanSubmit);

        bindBanActionButtons();

        window.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape') {
                if (!document.getElementById('messageModal').classList.contains('hidden')) closeMessageModal();
                if (!document.getElementById('banModal').classList.contains('hidden')) closeBanModal();
            }
        });
    </script>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 20px;
        }
    </style>
@endsection