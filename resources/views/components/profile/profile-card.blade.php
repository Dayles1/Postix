@props([
    'user',
    'operationsCount' => 0,
    'messagesCount' => 0,
    'canBan' => false
])

@php
    use Carbon\Carbon;

    $auth = auth()->user();
    $role = $auth?->role?->name ?? 'user';
    $targetRole = $user?->role?->name ?? 'user';

    $isSuperadmin = $role === 'superadmin';
    $isAdmin = $role === 'admin';

    $canBanUser = $isSuperadmin || ($isAdmin && $targetRole === 'user');

    $banModel = $user?->ban ?? null;
    $isBanned = (bool) ($banModel?->active ?? false);

    $banStartsAtRaw = $banModel?->starts_at ?? null;
    $banEndsAtRaw = data_get($banModel, 'ends_at');
    $banCreatedAtRaw = $banModel?->created_at ?? null;

    $banStartsAt = !empty($banStartsAtRaw) ? Carbon::parse($banStartsAtRaw)->format('Y-m-d, H:i') : '';
    $banEndsAt = !empty($banEndsAtRaw) ? Carbon::parse($banEndsAtRaw)->format('Y-m-d, H:i') : '';
    $banCreatedAt = !empty($banCreatedAtRaw) ? Carbon::parse($banCreatedAtRaw)->format('Y-m-d, H:i') : '';

    $banScheduled = !empty($banStartsAtRaw) ? Carbon::parse($banStartsAtRaw)->gt(now()) : false;

    $uid = 'ban-card-' . ($user?->id ?? uniqid());
@endphp

<div
    class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-800"
    data-ban-card="{{ $user?->id }}"
>
    <div class="flex flex-col items-center gap-5">
        <div class="h-24 w-24 overflow-hidden rounded-full border border-gray-200 dark:border-gray-700">
            @if(!empty($user?->avatar?->path))
                <img
                    src="{{ asset('storage/' . $user->avatar->path) }}"
                    alt="{{ $user?->name ?? 'User' }}"
                    class="h-full w-full object-cover"
                >
            @else
                <div
                    class="flex h-full w-full items-center justify-center text-xl font-bold text-white"
                    style="background: linear-gradient(135deg,#6366f1,#22d3ee)"
                >
                    {{ strtoupper(mb_substr($user?->name ?? 'U', 0, 1)) }}
                </div>
            @endif
        </div>

        <div class="text-center">
            <div class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $user?->name ?? '—' }}
            </div>

            <div class="break-all text-sm text-gray-500 dark:text-gray-400">
                {{ $user?->email ?? '—' }}
            </div>

            <div class="mt-2 inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium
                {{ $isBanned ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' : ($banScheduled ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300') }}">
                @if($isBanned)
                    {{ __('messages.ban_modal.banned') }}
                @elseif($banScheduled)
                    {{ __('messages.scheduled') }}
                @else
                    {{ __('messages.ban_modal.not_banned') }}
                @endif
            </div>
        </div>

        <div class="w-full rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/60">
    @if($isBanned)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/40 dark:bg-red-900/15">
            <div class="text-sm font-semibold text-red-700 dark:text-red-300">
                {{ __('messages.ban_modal.user_banned_title') }}
            </div>

            <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-gray-700 dark:text-gray-200">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ __('messages.ban_modal.ban_started_at') }}
                    </span>
                    <span class="font-medium" data-ban-info-starts-at>
                        {{ $banStartsAt ?: ($banCreatedAt ?: '—') }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ __('messages.ban_modal.current_status') }}
                    </span>
                    <span class="font-medium" data-ban-info-status>
                        {{ __('messages.ban_modal.banned') }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ __('messages.ban_modal.ban_since') }}
                    </span>
                    <span class="font-medium">
                        {{ $banStartsAt ?: ($banCreatedAt ?: '—') }}
                    </span>
                </div>

                @if(!empty($banEndsAt))
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-gray-500 dark:text-gray-400">
                            {{ __('messages.ban_modal.ban_ends_at') }}
                        </span>
                        <span class="font-medium">{{ $banEndsAt }}</span>
                    </div>
                @endif
            </div>
        </div>
    @elseif($banScheduled)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-900/15">
            <div class="text-sm font-semibold text-amber-700 dark:text-amber-300">
                {{ __('messages.ban_modal.ban_scheduled_title') }}
            </div>

            <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-gray-700 dark:text-gray-200">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ __('messages.ban_modal.ban_starts_at') }}
                    </span>
                    <span class="font-medium" data-ban-info-starts-at>
                        {{ $banStartsAt ?: '—' }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ __('messages.ban_modal.current_status') }}
                    </span>
                    <span class="font-medium" data-ban-info-status>
                        {{ __('messages.scheduled') }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ __('messages.ban_modal.ban_not_active_yet') }}
                    </span>
                    <span class="font-medium">—</span>
                </div>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/40 dark:bg-emerald-900/15">
            <div class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                {{ __('messages.ban_modal.no_ban') }}
            </div>

            <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-gray-700 dark:text-gray-200">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ __('messages.ban_modal.current_status') }}
                    </span>
                    <span class="font-medium" data-ban-info-status>
                        {{ __('messages.ban_modal.not_banned') }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ __('messages.ban_modal.ban_starts_at') }}
                    </span>
                    <span class="font-medium" data-ban-info-starts-at>—</span>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ __('messages.ban_modal.ban_started_at') }}
                    </span>
                    <span class="font-medium">—</span>
                </div>
            </div>
        </div>
    @endif
</div>

        <div class="grid w-full grid-cols-2 gap-3">
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs text-gray-500">
                    {{ __('messages.users.operations_count') }}
                </div>
                <div class="mt-1 text-xl font-semibold text-gray-800 dark:text-white">
                    {{ $operationsCount }}
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="text-xs text-gray-500">
                    {{ __('messages.users.messages_count') }}
                </div>
                <div class="mt-1 text-xl font-semibold text-gray-800 dark:text-white">
                    {{ $messagesCount }}
                </div>
            </div>
        </div>
    </div>

    @if($canBan && $canBanUser)
        <div class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700">
            <button
                type="button"
                data-ban-toggle
                class="w-full rounded-lg px-4 py-2 text-sm font-medium text-white transition hover:opacity-90"
                style="background: {{ $isBanned ? '#ef4444' : ($banScheduled ? '#f59e0b' : '#6b7280') }}"
                data-banned="{{ $isBanned ? 1 : 0 }}"
                data-scheduled="{{ $banScheduled ? 1 : 0 }}"
                data-starts-at="{{ $banStartsAt }}"
                data-created-at="{{ $banCreatedAt }}"
                data-ends-at="{{ $banEndsAt }}"
                data-name="{{ e($user?->name ?? 'User') }}"
                data-id="{{ $user?->id }}"
                data-type="user"
            >
                @if($isBanned)
                    {{ __('messages.admin.unban') }}
                @elseif($banScheduled)
                    {{ __('messages.ban_actions.update') }}
                @else
                    {{ __('messages.admin.ban') }}
                @endif
            </button>
        </div>
    @endif

    @if($canBan && $canBanUser)
        <div id="banModal-{{ $uid }}" class="hidden fixed inset-0 flex items-center justify-center z-[99999]">
            <div class="absolute inset-0 bg-black/50" data-ban-backdrop></div>

            <div class="relative z-10 w-full max-w-lg mx-4 rounded-2xl bg-white shadow-xl dark:bg-gray-900">
                <div class="p-6 space-y-5">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ __('messages.ban_modal.title') }}
                        </h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300" data-ban-message>
                            {{ __('messages.ban_modal.description') }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/40">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-gray-600 dark:text-gray-300">
                                {{ __('messages.ban_modal.current_status') }}
                            </span>
                            <span class="font-medium text-gray-900 dark:text-white" data-ban-current-status>—</span>
                        </div>
                        <div class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            <div>
                                {{ __('messages.ban_modal.current_starts_at') }}:
                                <span data-ban-current-starts-at>—</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" class="rounded border-gray-300" data-schedule-toggle>
                            <span>{{ __('messages.scheduled') }}</span>
                        </label>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ __('messages.ban_modal.starts_at') }}
                            </label>
                            <input
                                type="datetime-local"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                data-ban-starts-at
                            >
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ __('messages.ban_modal.starts_at_help') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                            data-ban-cancel
                        >
                            {{ __('messages.ban_modal.cancel') }}
                        </button>

                        <button
                            type="button"
                            class="rounded-lg bg-amber-600 px-4 py-2 text-sm text-white"
                            data-ban-submit
                        >
                            {{ __('messages.ban_modal.confirm') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const banText = @json(__('messages.admin.ban'));
            const unbanText = @json(__('messages.admin.unban'));
            const updateBanText = @json(__('messages.ban_actions.update'));
            const confirmText = @json(__('messages.ban_modal.confirm'));
            const notBannedText = @json(__('messages.ban_modal.not_banned'));
            const bannedText = @json(__('messages.ban_modal.banned'));
            const scheduledText = @json(__('messages.scheduled'));

            const requestJson = async (url, body = {}, method = 'post') => {
                return axios({
                    method,
                    url,
                    data: method === 'get' ? undefined : body,
                    params: method === 'get' ? body : undefined,
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    withCredentials: true,
                });
            };

            document.querySelectorAll('[data-ban-card]').forEach((card) => {
                const btn = card.querySelector('[data-ban-toggle]');
                const modal = card.querySelector('[id^="banModal-"]');
                if (!btn || !modal) return;

                const backdrop = modal.querySelector('[data-ban-backdrop]');
                const cancel = modal.querySelector('[data-ban-cancel]');
                const submit = modal.querySelector('[data-ban-submit]');
                const message = modal.querySelector('[data-ban-message]');
                const currentStatus = modal.querySelector('[data-ban-current-status]');
                const currentStartsAt = modal.querySelector('[data-ban-current-starts-at]');
                const startsAtInput = modal.querySelector('[data-ban-starts-at]');
                const scheduleToggle = modal.querySelector('[data-schedule-toggle]');

                const infoStatus = card.querySelector('[data-ban-info-status]');
                const infoStartsAt = card.querySelector('[data-ban-info-starts-at]');

                let state = {
                    id: btn.dataset.id,
                    type: btn.dataset.type || 'user',
                    name: btn.dataset.name || 'User',
                    banned: btn.dataset.banned === '1',
                    scheduled: btn.dataset.scheduled === '1',
                    startsAt: btn.dataset.startsAt || '',
                    createdAt: btn.dataset.createdAt || '',
                    endsAt: btn.dataset.endsAt || '',
                };

                const getDisplayStartedAt = () => {
                    if (state.startsAt) return state.startsAt;
                    if (state.createdAt) return state.createdAt;
                    return '—';
                };

                const syncInfoCard = () => {
                    if (infoStatus) {
                        infoStatus.textContent = state.banned
                            ? bannedText
                            : (state.scheduled ? scheduledText : notBannedText);
                    }

                    if (infoStartsAt) {
                        if (state.banned) {
                            infoStartsAt.textContent = getDisplayStartedAt();
                        } else if (state.scheduled) {
                            infoStartsAt.textContent = state.startsAt || '—';
                        } else {
                            infoStartsAt.textContent = '—';
                        }
                    }
                };

                const open = () => {
                    if (currentStatus) {
                        currentStatus.textContent = state.banned
                            ? bannedText
                            : (state.scheduled ? scheduledText : notBannedText);
                    }

                    if (currentStartsAt) {
                        currentStartsAt.textContent = state.startsAt || '—';
                    }

                    if (startsAtInput) {
                        startsAtInput.value = state.startsAt || '';
                    }

                    if (scheduleToggle) {
                        scheduleToggle.checked = !!state.startsAt || state.scheduled;
                        startsAtInput.disabled = !scheduleToggle.checked;
                    }

                    if (message) {
                        const actionText = state.banned
                            ? unbanText
                            : (state.scheduled ? updateBanText : banText);

                        message.textContent = `${state.name} - ${actionText.toLowerCase()}? ${@json(__('messages.ban_modal.description'))}`;
                    }

                    if (submit) {
                        submit.textContent = confirmText;
                    }

                    modal.classList.remove('hidden');
                    document.documentElement.classList.add('overflow-hidden');
                };

                const close = () => {
                    modal.classList.add('hidden');
                    document.documentElement.classList.remove('overflow-hidden');
                };

                const syncButton = (payload) => {
                    state.banned = !!payload.is_banned;
                    state.scheduled = !!payload.is_scheduled;
                    state.startsAt = payload.starts_at || '';

                    btn.dataset.banned = state.banned ? '1' : '0';
                    btn.dataset.scheduled = state.scheduled ? '1' : '0';
                    btn.dataset.startsAt = state.startsAt;

                    btn.textContent = state.banned
                        ? unbanText
                        : (state.scheduled ? updateBanText : banText);

                    btn.style.background = state.banned
                        ? '#ef4444'
                        : (state.scheduled ? '#f59e0b' : '#6b7280');

                    syncInfoCard();
                };

                syncInfoCard();

                btn.addEventListener('click', open);
                cancel?.addEventListener('click', close);
                backdrop?.addEventListener('click', close);

                scheduleToggle?.addEventListener('change', () => {
                    if (!startsAtInput) return;
                    startsAtInput.disabled = !scheduleToggle.checked;
                    if (!scheduleToggle.checked) startsAtInput.value = '';
                });

                submit?.addEventListener('click', async () => {
                    submit.disabled = true;
                    const old = submit.textContent;
                    submit.textContent = @json(__('messages.loading') ?? 'Loading...');

                    try {
                        const scheduled = scheduleToggle?.checked && !!startsAtInput?.value;

                        const payload = {
                            bannable_type: state.type,
                            bannable_id: Number(state.id),
                            action: state.banned ? 'unban' : (scheduled ? 'update' : 'ban'),
                            starts_at: scheduled ? startsAtInput.value : null,
                        };

                        const res = await requestJson('/admin/ban-unban', payload, 'post');
                        const data = res?.data?.data ?? res?.data ?? {};

                        syncButton(data);

                        close();
                    } catch (err) {
                        console.error(err);
                    } finally {
                        submit.disabled = false;
                        submit.textContent = old || confirmText;
                    }
                });
            });
        });
    </script>
@endonce