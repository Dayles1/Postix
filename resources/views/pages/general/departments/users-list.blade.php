@extends('layouts.app')

@section('title', ($department->name ?? __('messages.department')) . ' — ' . (__('messages.admin.users') ?: 'Users'))

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="mx-auto max-w-7xl p-4 space-y-6">
        {{-- HEADER --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $department->name }}</h1>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('messages.admin.users') }} · {{ count($users) }}
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if ($permissions['isSuperadmin'])
                    <a href="{{ route('admin.telegram.new-users', $department->id) }}"
                    class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                    {{ __('messages.admin.add_user') }}
                </a>
                @else
                <a href="{{ route('user.telegram.new-users') }}"
                    class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                    {{ __('messages.admin.add_user') }}
                </a>
                @endif
                
                
                

                <a href="{{ route('departments.show', $department->id) }}"
                    class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                    {{ __('messages.admin.back') }}
                </a>
            </div>
        </div>

        {{-- Controls: search / role / show deleted --}}
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <div class="relative w-full sm:w-1/2">
                    <input id="js-search" type="search" placeholder="{{ __('messages.admin.search_users') }}"
                        class="w-full h-[44px] px-4 pl-12 text-sm border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white" />
                    <div class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
                        <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                        </svg>
                    </div>
                </div>

                <select id="js-filter-role"
                    class="w-full sm:w-auto px-4 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">{{ __('messages.admin.all_role') ?? 'All roles' }}</option>
                    @foreach ($roles ?? \App\Models\Role::all() as $r)
                        <option value="{{ $r->id }}">{{ ucfirst($r->name) }}</option>
                    @endforeach
                </select>

                @if (!empty($permissions['isSuperadmin']))
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input id="js-show-deleted" type="checkbox" class="rounded" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            {{ __('messages.admin.deleted') ?? 'Show deleted' }}
                        </span>
                    </label>
                @endif

                <button id="js-reset"
                    class="ml-auto sm:ml-0 px-4 py-2 text-sm bg-white border border-gray-200 rounded-lg hover:bg-gray-50 text-gray-700 dark:bg-gray-700 dark:border-gray-600 dark:hover:bg-gray-600 dark:text-gray-200">
                    Reset
                </button>

                <div class="ml-auto sm:ml-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('messages.operations.peer_total') ?? 'Total' }}:
                    <span class="font-medium text-gray-900 dark:text-white">{{ count($users) }}</span>
                </div>
            </div>
        </div>

        {{-- Users list as table --}}
        <div class="rounded-2xl border border-gray-200 bg-white pt-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-col gap-2 px-5 mb-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ __('messages.admin.users') }}
                    </h3>
                </div>
            </div>

            <div class="overflow-hidden">
                <div class="max-w-full px-5 overflow-x-auto">
                    <table class="min-w-full" role="table" aria-label="Users table">
                        <thead>
                            <tr class="border-gray-200 border-y dark:border-gray-700">
                                <th class="px-4 py-3 font-normal text-gray-500 text-start text-theme-sm dark:text-gray-400">
                                    {{ __('messages.users.title') }}
                                </th>
                                <th class="px-4 py-3 font-normal text-gray-500 text-start text-theme-sm dark:text-gray-400">
                                    Telegram / {{ __('messages.users.role') }}
                                </th>
                                <th class="px-4 py-3 font-normal text-gray-500 text-start text-theme-sm dark:text-gray-400">
                                    {{ __('messages.phone_label') }}
                                </th>
                                <th class="px-4 py-3 font-normal text-gray-500 text-start text-theme-sm dark:text-gray-400">
                                    {{ __('messages.table.actions') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($users as $user)
                                @php
                                    $isDeleted = method_exists($user, 'trashed')
                                        ? $user->trashed()
                                        : !is_null($user->deleted_at);

                                    $userBanned = $user->is_banned ?? false;

                                    $banModel = $user->ban ?? null;

                                    $banStartsAtRaw = $banModel?->starts_at;

                                    $banStartsAt = !empty($banStartsAtRaw)
                                        ? \Carbon\Carbon::parse($banStartsAtRaw)->format('Y-m-d')
                                        : null;

                                    $banActive = (bool) ($banModel?->active ?? false);

                                    $banScheduled = $banStartsAt
                                        ? \Carbon\Carbon::parse($banStartsAt)->gt(now())
                                        : false;
                                @endphp

                                <tr class="user-row" data-user-id="{{ $user->id }}"
                                    data-deleted="{{ $isDeleted ? '1' : '0' }}"
                                    data-banned="{{ $userBanned ? '1' : '0' }}" data-role-id="{{ $user->role_id ?? '' }}">
                                    <td class="py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-4 min-w-0">
                                            <div
                                                class="w-10 h-10 flex-shrink-0 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden border border-gray-200 dark:border-gray-600">
                                                @if ($user->avatar && $user->avatar->path)
                                                    <img src="{{ asset('storage/' . $user->avatar->path) }}"
                                                        alt="{{ $user->name ?? 'User' }}"
                                                        class="w-full h-full object-cover" loading="lazy">
                                                @else
                                                    <div
                                                        class="w-full h-full flex items-center justify-center text-sm font-medium text-gray-600 dark:text-gray-300 bg-gray-200 dark:bg-gray-600">
                                                        {{ strtoupper(mb_substr($user->name ?? '', 0, 1)) ?: '?' }}
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-900 dark:text-white truncate user-name">
                                                    {{ $user->name ?? '—' }}
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400 truncate user-meta">
                                                    {{ $user->email ?? '' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            ({{ $user->telegram_id ?? __('messages.admin.no_telegram') }})
                                            • {{ $user->role_name ?? __('messages.admin.no_role') }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <select
                                            class="phone-select px-3 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                            data-user-id="{{ $user->id }}"
                                            aria-label="Select phone for {{ $user->name }}">
                                            @foreach ($user->phones as $phone)
                                                <option value="{{ $phone->id }}"
                                                    {{ $phone->is_active ? 'selected' : '' }}>
                                                    {{ $phone->phone }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>

                                    @php
                                        $authUser = auth()->user();
                                        $authRole = strtolower($authUser->role->name ?? '');
                                        $targetRole = strtolower($user->role_name ?? '');
                                        $isSelf = $authUser->id === $user->id;

                                        $canBan = false;
                                        $canDelete = false;

                                        if ($authRole === 'superadmin') {
                                            $canBan = !$isSelf;
                                            $canDelete = !$isSelf;
                                        } elseif ($authRole === 'admin') {
                                            $canBan =
                                                !$isSelf && $targetRole !== 'admin' && $targetRole !== 'superadmin';
                                            $canDelete =
                                                !$isSelf && $targetRole !== 'admin' && $targetRole !== 'superadmin';
                                        }
                                    @endphp

                                    <td class="px-4 py-4 text-sm font-medium text-right whitespace-nowrap">
                                        @if (!$isDeleted)
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('users.profile', $user->id) }}"
                                                    class="details-link text-indigo-600 hover:text-indigo-900 text-sm px-2 py-1"
                                                    aria-label="Details for {{ $user->name }}">
                                                    {{ __('messages.admin.details') }}
                                                </a>

                                                {{-- BAN --}}
                                                @if ($canBan)
                                                    <button class="user-ban-btn px-3 py-1 rounded-md text-sm font-medium"
                                                        data-type="user" data-id="{{ $user->id }}"
                                                        data-banned="{{ $banActive ? '1' : '0' }}"
                                                        data-scheduled="{{ $banScheduled ? '1' : '0' }}"
                                                        data-name="{{ e($user->name ?? 'User') }}"
                                                        data-starts-at="{{ $banStartsAt ?? '' }}"
                                                        data-until="{{ $banUntil ?? '' }}"
                                                        style="background:#fff7ed;color:#92400e;border:1px solid #fcd34d">
                                                        {{ $banActive || $banScheduled ? __('messages.ban_actions.update') : __('messages.admin.ban') }}
                                                    </button>
                                                @else
                                                    <button
                                                        class="px-3 py-1 rounded-md text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed"
                                                        disabled
                                                        title="{{ __('messages.admin.cannot_manage') ?? 'You are not allowed to manage this user' }}"
                                                        aria-hidden="true">
                                                        {{ __('messages.admin.ban') }}
                                                    </button>
                                                @endif

                                                {{-- DELETE --}}
                                                @if ($canDelete)
                                                    <button
                                                        class="btn-delete-user px-3 py-1 rounded-md text-sm font-medium text-white"
                                                        data-action="{{ route('users.destroy', $user->id) }}"
                                                        style="background:#ef4444;">
                                                        {{ __('messages.admin.delete') }}
                                                    </button>
                                                @else
                                                    <button
                                                        class="px-3 py-1 rounded-md text-sm font-medium text-gray-400 bg-gray-100 cursor-not-allowed"
                                                        disabled
                                                        title="{{ __('messages.admin.cannot_manage') ?? 'You are not allowed to manage this user' }}"
                                                        aria-hidden="true">
                                                        {{ __('messages.admin.delete') }}
                                                    </button>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4"
                                        class="p-4 text-sm text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800">
                                        {{ __('messages.admin.no_recent_activity') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if (method_exists($users, 'links'))
                <div class="px-6 py-4 border-t border-gray-200 dark:border-white/[0.05]">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Delete confirmation modal --}}
    <div id="deleteConfirmModal" class="hidden fixed inset-0 z-[10000] flex items-center justify-center"
        aria-hidden="true" aria-labelledby="deleteConfirmTitle" role="dialog">
        <div id="deleteConfirmBackdrop" class="absolute inset-0 bg-black/50"></div>

        <div class="relative z-50 w-full max-w-md mx-4 rounded-2xl bg-white dark:bg-gray-900 overflow-hidden"
            style="max-height:90vh;">
            <div class="p-6">
                <h3 id="deleteConfirmTitle" class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('messages.catalogs.delete_title') ?? 'Confirm delete' }}
                </h3>
                <p id="deleteConfirmMessage" class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('messages.departments.delete_confirm') ?? 'Are you sure you want to delete this user? This action can be reversed only via restore.' }}
                </p>

                <div class="mt-4 flex items-center justify-end gap-3">
                    <button id="deleteConfirmCancel" type="button"
                        class="px-4 py-2 rounded-lg border border-gray-200 bg-white text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                        {{ __('messages.admin.cancel') ?? 'Cancel' }}
                    </button>

                    <button id="deleteConfirmSubmit" type="button"
                        class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm">
                        {{ __('messages.admin.delete') ?? 'Delete' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Ban confirmation modal --}}
    <div id="banConfirmModal" class="hidden fixed inset-0 z-[10001] flex items-center justify-center" aria-hidden="true"
        aria-labelledby="banConfirmTitle" role="dialog">
        <div id="banConfirmBackdrop" class="absolute inset-0 bg-black/50"></div>

        <div class="relative z-50 w-full max-w-lg mx-4 rounded-2xl bg-white dark:bg-gray-900 overflow-hidden"
            style="max-height:90vh;">
            <div class="p-6 space-y-5">
                <div>
                    <h3 id="banConfirmTitle" class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ __('messages.ban_modal.title') }}
                    </h3>
                    <p id="banConfirmMessage" class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ __('messages.ban_modal.description') }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="banStartsAt" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('messages.ban_modal.starts_at') }}
                        </label>
                        <input id="banStartsAt" type="datetime-local"
                            class="mt-1 w-full px-4 py-2 text-sm border border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('messages.ban_modal.starts_at_help') }}
                        </p>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-800/40">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span
                            class="text-gray-600 dark:text-gray-300">{{ __('messages.ban_modal.current_status') }}</span>
                        <span id="banCurrentStatus" class="font-medium text-gray-900 dark:text-white">—</span>
                    </div>
                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        <div>{{ __('messages.ban_modal.current_starts_at') }}: <span id="banCurrentStartsAt">—</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-end gap-3">
                    <button id="banConfirmCancel" type="button"
                        class="px-4 py-2 rounded-lg border border-gray-200 bg-white text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200">
                        {{ __('messages.ban_modal.cancel') }}
                    </button>

                    <button id="banConfirmSubmit" type="button"
                        class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm">
                        {{ __('messages.ban_modal.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div id="simpleToast" class="fixed top-4 right-4 z-50 hidden pointer-events-none" aria-live="polite"
        aria-atomic="true">
        <div id="simpleToastInner" class="px-6 py-3 rounded-lg shadow-lg text-white"></div>
    </div>

    {{-- Modal: user show --}}
    <div id="userShowModal" class="hidden fixed inset-0 z-[9999] flex items-center justify-center">
        <div id="modalBackdrop" class="absolute inset-0 bg-black/50"></div>

        <div id="modalPanel"
            class="relative z-50 w-full max-w-3xl mx-4 rounded-2xl bg-white dark:bg-gray-900 overflow-auto"
            style="max-height:90vh;">
            <div id="modalContent" class="p-4"></div>
            <div class="p-4 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-2">
                <button id="modalClose"
                    class="px-4 py-2 rounded-lg bg-white border border-gray-200 dark:bg-gray-700 dark:text-gray-200">
                    Close
                </button>
            </div>
        </div>
    </div>

    <style>
        tr.user-row[data-deleted="1"] {
            background: rgba(239, 68, 68, 0.04);
            border-left: 4px solid rgba(239, 68, 68, 0.9);
        }

        tr.user-row[data-deleted="1"] .user-name {
            color: #991b1b;
        }

        @media (max-width: 640px) {
            #modalPanel {
                border-radius: 0.5rem;
                width: 100%;
                height: 100vh;
                max-width: 100%;
                margin: 0;
                border-radius: 0;
            }

            #modalContent {
                padding: 1rem;
            }
        }

        .touchable {
            -webkit-tap-highlight-color: rgba(0, 0, 0, 0.05);
        }

        @keyframes toast-in {
            from {
                transform: translateY(-8px) scale(.98);
                opacity: 0;
            }

            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        @keyframes toast-out {
            from {
                transform: translateY(0) scale(1);
                opacity: 1;
            }

            to {
                transform: translateY(-8px) scale(.98);
                opacity: 0;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const searchInput = document.getElementById('js-search');
            const roleSelect = document.getElementById('js-filter-role');
            const showDeletedCheckbox = document.getElementById('js-show-deleted');
            const resetBtn = document.getElementById('js-reset');

            const toastRoot = document.getElementById('simpleToast');
            const toastInner = document.getElementById('simpleToastInner');

            const deleteModal = document.getElementById('deleteConfirmModal');
            const deleteBackdrop = document.getElementById('deleteConfirmBackdrop');
            const deleteCancel = document.getElementById('deleteConfirmCancel');
            const deleteSubmit = document.getElementById('deleteConfirmSubmit');

            const banModal = document.getElementById('banConfirmModal');
            const banBackdrop = document.getElementById('banConfirmBackdrop');
            const banCancel = document.getElementById('banConfirmCancel');
            const banSubmit = document.getElementById('banConfirmSubmit');
            const banMessage = document.getElementById('banConfirmMessage');
            const banTitle = document.getElementById('banConfirmTitle');
            const banStartsAtInput = document.getElementById('banStartsAt');
            const banCurrentStatus = document.getElementById('banCurrentStatus');
            const banCurrentStartsAt = document.getElementById('banCurrentStartsAt');

            const banText = @json(__('messages.ban_actions.ban'));
            const unbanText = @json(__('messages.ban_actions.unban'));
            const updateBanText = @json(__('messages.ban_actions.update'));
            const modalTitleText = @json(__('messages.ban_modal.title'));
            const modalDescriptionText = @json(__('messages.ban_modal.description'));
            const confirmBanBtn = @json(__('messages.ban_modal.confirm'));
            const cancelBanBtn = @json(__('messages.ban_modal.cancel'));
            const bannedText = @json(__('messages.ban_modal.banned'));
            const scheduledText = @json(__('messages.ban_modal.scheduled'));
            const notBannedText = @json(__('messages.ban_modal.not_banned'));

            let deleteActionUrl = null;
            let deleteTargetRow = null;

            let banActionUrl = '/admin/ban-unban';
            let banTargetButton = null;
            let banTargetId = null;
            let banTargetType = 'user';
            let banTargetName = '';
            let banTargetBanned = false;
            let banTargetScheduled = false;

            function extractMessage(respOrError) {
                if (!respOrError) return null;

                if (respOrError.data) {
                    const d = respOrError.data;
                    if (typeof d === 'string') return d;
                    if (d.message) return d.message;
                    if (d.data && d.data.message) return d.data.message;
                    if (d.meta && d.meta.message) return d.meta.message;
                }

                if (respOrError.response && respOrError.response.data) {
                    const d = respOrError.response.data;
                    if (d.message) return d.message;
                    if (d.data && d.data.message) return d.data.message;
                }

                if (respOrError.message) return respOrError.message;

                return null;
            }

            function toast(message, type = 'success', timeout = 3000) {
                if (!message || !toastRoot || !toastInner) return;

                toastInner.textContent = message;
                toastInner.style.background = type === 'success' ? '#16a34a' : '#dc2626';
                toastInner.style.color = '#ffffff';
                toastRoot.classList.remove('hidden');
                toastRoot.style.pointerEvents = 'auto';
                toastInner.style.animation = 'toast-in .15s ease-out forwards';

                clearTimeout(toastRoot._t);
                toastRoot._t = setTimeout(() => {
                    toastInner.style.animation = 'toast-out .12s ease-in forwards';
                    setTimeout(() => {
                        toastRoot.classList.add('hidden');
                        toastRoot.style.pointerEvents = 'none';
                    }, 120);
                }, timeout);
            }

            async function requestJson(url, body = {}, method = 'post') {
                return axios({
                    method,
                    url,
                    data: method === 'get' ? undefined : body,
                    params: method === 'get' ? body : undefined,
                    headers: {
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json'
                    },
                    withCredentials: true
                });
            }

            function openDeleteModal(actionUrl, targetRow) {
                deleteActionUrl = actionUrl;
                deleteTargetRow = targetRow;
                deleteModal?.classList.remove('hidden');
                deleteModal?.setAttribute('aria-hidden', 'false');
                document.documentElement.classList.add('overflow-hidden');
            }

            function closeDeleteModal() {
                deleteActionUrl = null;
                deleteTargetRow = null;
                deleteModal?.classList.add('hidden');
                deleteModal?.setAttribute('aria-hidden', 'true');
                document.documentElement.classList.remove('overflow-hidden');
            }

            function formatCurrentValue(val) {
                return val && String(val).trim() !== '' ? val : '—';
            }

            function actionLabel() {
                if (banTargetBanned) return unbanText;
                if (banTargetScheduled) return updateBanText;
                return banText;
            }

            function openBanModal(button) {
                banTargetButton = button;
                banTargetId = button.dataset.id;
                banTargetType = button.dataset.type || 'user';
                banTargetName = button.dataset.name || 'User';
                banTargetBanned = String(button.dataset.banned) === '1';
                banTargetScheduled = String(button.dataset.scheduled) === '1';

                const currentStartsAt = button.dataset.startsAt || '';

                if (banStartsAtInput) {
                    banStartsAtInput.value = currentStartsAt;
                }

                if (banCurrentStatus) {
                    banCurrentStatus.textContent = banTargetBanned ?
                        bannedText :
                        (banTargetScheduled ? scheduledText : notBannedText);
                }

                if (banCurrentStartsAt) {
                    banCurrentStartsAt.textContent = formatCurrentValue(currentStartsAt);
                }

                if (banTitle) {
                    banTitle.textContent = modalTitleText;
                }

                if (banMessage) {
                    banMessage.textContent =
                        `${banTargetName}  ${actionLabel().toLowerCase()}? ${modalDescriptionText}`;
                }

                if (banSubmit) {
                    banSubmit.textContent = confirmBanBtn;
                }

                banModal?.classList.remove('hidden');
                banModal?.setAttribute('aria-hidden', 'false');
                document.documentElement.classList.add('overflow-hidden');
            }

            function closeBanModal() {
                banTargetButton = null;
                banTargetId = null;
                banTargetType = 'user';
                banTargetName = '';
                banTargetBanned = false;
                banTargetScheduled = false;

                if (banStartsAtInput) {
                    banStartsAtInput.value = '';
                }

                banModal?.classList.add('hidden');
                banModal?.setAttribute('aria-hidden', 'true');
                document.documentElement.classList.remove('overflow-hidden');
            }

            function applyFilters() {
                const q = (searchInput?.value || '').toLowerCase().trim();
                const roleId = roleSelect?.value || '';
                const showDeleted = showDeletedCheckbox ? showDeletedCheckbox.checked : false;

                document.querySelectorAll('tr.user-row').forEach(row => {
                    const name = row.querySelector('.user-name')?.textContent?.toLowerCase() || '';
                    const meta = row.querySelector('.user-meta')?.textContent?.toLowerCase() || '';
                    const deleted = row.dataset.deleted === '1';
                    const roleMatch = row.dataset.roleId || '';

                    const matchesQ = !q || name.includes(q) || meta.includes(q) || row.textContent
                        .toLowerCase().includes(q);
                    const matchesRole = !roleId || (roleMatch === roleId);
                    const matchesDeleted = showDeleted || !deleted;

                    row.style.display = (matchesQ && matchesRole && matchesDeleted) ? '' : 'none';
                });
            }

            function resetFilters() {
                if (searchInput) searchInput.value = '';
                if (roleSelect) roleSelect.value = '';
                if (showDeletedCheckbox) showDeletedCheckbox.checked = false;
                applyFilters();
            }

            function debounce(fn, wait = 300) {
                let t;
                return (...args) => {
                    clearTimeout(t);
                    t = setTimeout(() => fn.apply(this, args), wait);
                };
            }

            deleteCancel?.addEventListener('click', closeDeleteModal);
            deleteBackdrop?.addEventListener('click', closeDeleteModal);

            banCancel?.addEventListener('click', closeBanModal);
            banBackdrop?.addEventListener('click', closeBanModal);

            deleteSubmit?.addEventListener('click', async () => {
                if (!deleteActionUrl) return;

                deleteSubmit.disabled = true;
                const oldText = deleteSubmit.textContent;
                deleteSubmit.textContent = @json(__('messages.deleting') ?? 'Deleting...');

                try {
                    const res = await requestJson(deleteActionUrl, {}, 'delete');
                    const msg = extractMessage(res) || @json(__('messages.admin.success') ?? 'Success');
                    toast(msg, 'success');

                    if (deleteTargetRow) deleteTargetRow.remove();
                    closeDeleteModal();
                } catch (err) {
                    console.error(err);
                    const msg = extractMessage(err) || @json(__('messages.admin.error') ?? 'Error');
                    toast(msg, 'error');
                } finally {
                    deleteSubmit.disabled = false;
                    deleteSubmit.textContent = oldText || @json(__('messages.admin.delete') ?? 'Delete');
                }
            });

            banSubmit?.addEventListener('click', async () => {
                if (!banTargetButton || !banTargetId) return;

                banSubmit.disabled = true;
                const oldText = banSubmit.textContent;
                banSubmit.textContent = @json(__('messages.loading') ?? 'Loading...');

                try {
                    const startsAt = banStartsAtInput?.value || null;

                    const payload = {
                        bannable_type: banTargetType,
                        bannable_id: Number(banTargetId),
                        action: banTargetBanned ? 'unban' : 'update',
                        starts_at: startsAt
                    };

                    const res = await requestJson(banActionUrl, payload, 'post');
                    const data = res?.data?.data || {};

                    const newBanned = !!data.is_banned;
                    const newScheduled = !!data.is_scheduled;
                    const newStartsAt = data.starts_at || '';

                    banTargetButton.dataset.banned = newBanned ? '1' : '0';
                    banTargetButton.dataset.scheduled = newScheduled ? '1' : '0';
                    banTargetButton.dataset.startsAt = newStartsAt;
                    banTargetButton.dataset.until = '';

                    banTargetButton.textContent = newBanned ?
                        unbanText :
                        (newScheduled ? updateBanText : banText);

                    const msg = extractMessage(res) || @json(__('messages.admin.success') ?? 'Success');
                    toast(msg, 'success');
                    closeBanModal();
                } catch (err) {
                    console.error(err);
                    const msg = extractMessage(err) || @json(__('messages.admin.error') ?? 'Error');
                    toast(msg, 'error');
                } finally {
                    banSubmit.disabled = false;
                    banSubmit.textContent = oldText || confirmBanBtn;
                }
            });

            searchInput?.addEventListener('input', debounce(applyFilters, 220));
            roleSelect?.addEventListener('change', applyFilters);
            showDeletedCheckbox?.addEventListener('change', applyFilters);
            resetBtn?.addEventListener('click', resetFilters);

            applyFilters();

            document.body.addEventListener('click', async (ev) => {
                const banBtn = ev.target.closest('.user-ban-btn');
                if (banBtn) {
                    if (banBtn.disabled || banBtn.classList.contains('cursor-not-allowed')) return;
                    ev.preventDefault();
                    openBanModal(banBtn);
                    return;
                }

                const delBtn = ev.target.closest('.btn-delete-user');
                if (delBtn) {
                    if (delBtn.disabled || delBtn.classList.contains('cursor-not-allowed')) return;
                    ev.preventDefault();
                    const action = delBtn.dataset.action;
                    const row = delBtn.closest('tr.user-row');
                    openDeleteModal(action, row);
                    return;
                }

                const restoreBtn = ev.target.closest('.btn-restore-user');
                if (restoreBtn) {
                    ev.preventDefault();
                    const id = restoreBtn.dataset.userId;

                    if (!confirm(@json(__('messages.admin.restore') ?? 'Restore?'))) return;

                    try {
                        const res = await requestJson(`/admin/users/${id}/restore`, {}, 'post');
                        const msg = extractMessage(res) || @json(__('messages.admin.success') ?? 'Success');
                        toast(msg, 'success');
                        window.location.reload();
                    } catch (err) {
                        console.error(err);
                        const msg = extractMessage(err) || @json(__('messages.admin.error') ?? 'Error');
                        toast(msg, 'error');
                    }
                }
            });

            document.body.addEventListener('change', async (ev) => {
                const sel = ev.target.closest('.phone-select');
                if (!sel) return;

                const userId = sel.dataset.userId;
                const phoneId = sel.value;

                try {
                    const res = await requestJson('/admin/phones/activate', {
                        user_id: Number(userId),
                        phone_id: Number(phoneId)
                    }, 'post');

                    const msg = extractMessage(res) || @json(__('messages.admin.success') ?? 'Success');
                    toast(msg, 'success');
                } catch (err) {
                    console.error(err);
                    const msg = extractMessage(err) || @json(__('messages.admin.error') ?? 'Error');
                    toast(msg, 'error');
                }
            });

            const modal = document.getElementById('userShowModal');
            const modalContent = document.getElementById('modalContent');
            const modalClose = document.getElementById('modalClose');
            const modalBackdrop = document.getElementById('modalBackdrop');

            function closeModal() {
                modal?.classList.add('hidden');
                document.documentElement.classList.remove('overflow-hidden');
                if (modalContent) modalContent.innerHTML = '';
            }

            modalClose?.addEventListener('click', closeModal);
            modalBackdrop?.addEventListener('click', closeModal);

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeModal();
                    closeDeleteModal();
                    closeBanModal();
                }
            });
        });
    </script>
@endsection
