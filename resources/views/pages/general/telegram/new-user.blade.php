@extends('layouts.app')

@section('title', __('messages.telegram.login'))
@section('page-title', __('messages.telegram.login'))

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $newUserNeedBan = (bool) ($newUserNeedBan ?? false);
        $isSuperadmin = ($authUserRole ?? '') === 'superadmin';
        $catalogs = $catalogs ?? collect();
    @endphp

    <div class="mx-auto max-w-lg px-4 pt-10 pb-8 sm:pt-14">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h4 class="mb-4 text-center text-lg font-semibold text-gray-800 dark:text-white">
                {{ __('messages.telegram.login') }}
            </h4>

            @if (!($canAdd ?? true))
                <div class="mb-3 rounded-md bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                    {{ __('messages.users.limit_reached_text', ['count' => $usersCount ?? 0, 'limit' => $maxUsers ?? 0]) }}
                </div>
            @endif

            @if (session('success'))
                <div class="mb-3 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <div id="alertError" class="hidden rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert"></div>

            <form id="userForm" data-can-add="{{ $canAdd ?? true ? '1' : '0' }}" onsubmit="return false;">
                @csrf
                <input type="hidden" id="departmentHidden" value="{{ $department->id ?? 0 }}">

                <div id="topInputs" class="space-y-3">
                    <div>
                        <label class="mb-1 block text-sm text-gray-600">{{ __('messages.users.name') }}</label>
                        <input id="name" name="name" type="text" @disabled(!($canAdd ?? true))
                            class="w-full rounded-md border px-3 py-2 text-sm {{ !($canAdd ?? true) ? 'locked-field bg-gray-100' : '' }}"
                            placeholder="{{ __('messages.users.name') }}">
                        <div id="nameError" class="mt-1 hidden text-xs text-red-600"></div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm text-gray-600">{{ __('messages.users.email') }}</label>
                        <input id="login" name="login" type="text" @disabled(!($canAdd ?? true))
                            class="w-full rounded-md border px-3 py-2 text-sm {{ !($canAdd ?? true) ? 'locked-field bg-gray-100' : '' }}"
                            placeholder="{{ __('messages.users.email') }}">
                        <div id="loginError" class="mt-1 hidden text-xs text-red-600"></div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm text-gray-600">{{ __('messages.users.new_password') }}</label>
                        <input id="password" name="password" type="password" @disabled(!($canAdd ?? true))
                            class="w-full rounded-md border px-3 py-2 text-sm {{ !($canAdd ?? true) ? 'locked-field bg-gray-100' : '' }}"
                            placeholder="********">
                        <div id="passwordError" class="mt-1 hidden text-xs text-red-600"></div>
                    </div>

                    @if (isset($roles) && $roles->count())
                        <div>
                            <label class="mb-1 block text-sm text-gray-600">{{ __('messages.users.role') }}</label>
                            <select id="role" name="role_id" @disabled(!($canAdd ?? true))
                                class="w-full rounded-md border px-3 py-2 text-sm {{ !($canAdd ?? true) ? 'locked-field bg-gray-100' : '' }}">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ ucfirst($role->name) }}</option>
                                @endforeach
                            </select>
                            <div id="roleError" class="mt-1 hidden text-xs text-red-600"></div>
                        </div>
                    @endif

                    @if ($isSuperadmin)
    <div
        class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-800/40">
        <div class="mb-2 flex items-center justify-between gap-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ __('messages.catalogs.title') }}
            </label>

            <div class="flex items-center gap-2">
                <input type="text" id="catalogSearchInput"
                    class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-700 dark:border-gray-700 dark:text-gray-200"
                    placeholder="{{ __('messages.users.search') }}">
                <button type="button" id="catalogSelectAllBtn"
                    class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700">
                    {{ __('messages.period.all') }}
                </button>
                <button type="button" id="catalogClearAllBtn"
                    class="rounded-lg border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700">
                    {{ __('messages.telegram.zq_ban_7f_clear') }}
                </button>
            </div>
        </div>

        <div id="catalogIdsError" class="mb-2 hidden text-xs text-red-600"></div>

        @if ($catalogs->count())
            <div id="catalogCheckboxList"
                class="grid grid-cols-1 gap-2 overflow-y-auto rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900"
                style="max-height: 5cm; min-height: 5cm;">
                @foreach ($catalogs as $catalog)
                    <label
                        class="catalog-item flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                        data-title="{{ strtolower($catalog->title) }}">
                        <input type="checkbox"
                            class="catalog-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            name="catalog_ids[]" value="{{ $catalog->id }}">
                        <span class="flex-1">{{ $catalog->title }}</span>
                    </label>
                @endforeach
            </div>
        @else
            <div
                class="rounded-lg border border-dashed border-gray-300 bg-white px-3 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                -
            </div>
        @endif
    </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('catalogSearchInput');
    const selectAllBtn = document.getElementById('catalogSelectAllBtn');
    const clearAllBtn = document.getElementById('catalogClearAllBtn');
    const items = Array.from(document.querySelectorAll('.catalog-item'));
    const checkboxes = Array.from(document.querySelectorAll('.catalog-checkbox'));

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            items.forEach(item => {
                const title = item.dataset.title || '';
                item.classList.toggle('hidden', q && !title.includes(q));
            });
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            checkboxes.forEach(cb => cb.checked = true);
        });
    }

    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function () {
            checkboxes.forEach(cb => cb.checked = false);
        });
    }
});
</script>

                    <div id="withoutPhoneBlock">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" id="withoutPhoneCheckbox" @disabled(!($canAdd ?? true)) />
                            <span class="text-sm text-gray-700">
                                {{ __('messages.telegram.without_phone_label') ?? 'Telefon kiritmasdan foydalanuvchi yaratish' }}
                            </span>
                        </label>
                    </div>

                    <div id="userLimitBlock" class="hidden">
                        <label class="mb-1 block text-sm text-gray-600">User create limit (for admin)</label>
                        <input id="userLimitInput" name="user_limit" type="number" min="1" step="1"
                            value="10" class="w-full rounded-md border px-3 py-2 text-sm">
                        <div id="userLimitError" class="mt-1 hidden text-xs text-red-600"></div>
                    </div>

                    <div id="minuteBlock">
                        <label class="mb-1 block text-sm text-gray-600">{{ __('messages.add_minute') }}</label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="minute_package" id="minute_package" value="1"
                                @disabled(!($canAdd ?? true))>
                            {{ __('messages.yes') ?? 'Ha' }}
                        </label>
                    </div>

                    <div id="banScheduleBlock">
                        <label class="mb-1 block text-sm text-gray-600">
                            {{ __('messages.telegram2332.login_ban_schedule_label') }}
                        </label>

                        <input id="ban_starts_at" name="ban_starts_at" type="date"
                            @if ($newUserNeedBan) required @endif
                            onclick="if (this.showPicker) this.showPicker()"
                            class="w-full rounded-md border px-3 py-2 text-sm {{ !($canAdd ?? true) ? 'locked-field bg-gray-100' : '' }}"
                            @disabled(!($canAdd ?? true))>

                        <p class="mt-1 text-xs text-gray-500">
                            {{ __('messages.telegram2332.login_ban_schedule_help') }}
                        </p>

                        <div id="banStartsAtError" class="mt-1 hidden text-xs text-red-600"></div>
                    </div>

                    <div id="phoneBlock">
                        <label class="mb-1 block text-sm text-gray-600">{{ __('messages.telegram.phone_label') }}</label>
                        <input id="phone" name="phone" type="text" @disabled(!($canAdd ?? true))
                            class="w-full rounded-md border px-3 py-2 text-sm {{ !($canAdd ?? true) ? 'locked-field bg-gray-100' : '' }}"
                            autocomplete="tel" placeholder="{{ __('messages.telegram.phone_placeholder') }}">
                        <div id="phoneError" class="mt-1 hidden text-xs text-red-600"></div>
                        <div id="phoneFeedback" class="mt-3 flex min-h-[24px] items-center text-sm"></div>
                    </div>
                </div>

                <div class="mt-5">
                    <button type="button" id="btnSubmit"
                        class="w-full rounded-2xl bg-blue-600 px-4 py-2 text-sm font-medium text-white">
                        {{ __('messages.admin.add_user') }}
                    </button>
                </div>
            </form>

            <div id="codeWrap" class="mt-6 hidden">
                <label class="mb-1 block text-sm text-gray-600">{{ __('messages.telegram.code_label') }}</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input id="codeInput" type="text" class="flex-1 rounded-md border px-3 py-2 text-sm"
                        placeholder="{{ __('messages.telegram.code_placeholder') }}">
                    <button id="btnVerifyCode"
                        class="w-full rounded-2xl bg-green-600 px-4 py-2 text-sm font-medium text-white sm:w-auto">
                        {{ __('messages.telegram.send_code') }}
                    </button>
                </div>
                <div id="codeFeedback" class="mt-3 flex min-h-[24px] items-center text-sm"></div>
            </div>

            <div id="passwordWrap" class="mt-6 hidden">
                <label class="mb-1 block text-sm text-gray-600">{{ __('messages.enter_password_label') }}</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input id="passwordInput" type="password" class="flex-1 rounded-md border px-3 py-2 text-sm"
                        placeholder="{{ __('messages.enter_password_placeholder') }}">
                    <button id="btnSendPassword"
                        class="w-full rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white sm:w-auto">
                        {{ __('messages.sendm') }}
                    </button>
                </div>
                <div id="passwordFeedback" class="mt-3 flex min-h-[24px] items-center text-sm"></div>
            </div>

            <div id="failedWrap" class="mt-6 hidden">
                <div id="failedMessage"
                    class="rounded-md bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200"></div>

                <div class="mt-3 flex gap-2">
                    <a id="btnUserProfile" href="#"
                        class="flex-1 rounded-2xl bg-emerald-600 px-6 py-2.5 text-center text-sm font-medium text-white transition hover:bg-emerald-700">
                        {{ __('messages.table.user') }}
                    </a>

                    <button id="btnReload"
                        class="flex-1 rounded-2xl bg-gray-500 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-gray-600">
                        {{ __('messages.reload_page') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="limitModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl dark:bg-gray-900">
            <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">
                {{ __('messages.users.limit_reached_title') }}
            </h3>

            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ __('messages.users.limit_reached_text', ['count' => $usersCount ?? 0, 'limit' => $maxUsers ?? 0]) }}
            </p>

            <div class="mt-4 flex justify-end">
                <button type="button" id="closeLimitModal"
                    class="rounded-2xl bg-gray-600 px-4 py-2 text-sm font-medium text-white">
                    {{ __('messages.users.limit_reached_ok') }}
                </button>
            </div>
        </div>
    </div>

    <style>
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0, 0, 0, 0.15);
            border-left-color: transparent;
            border-radius: 50%;
            animation: spin .8s linear infinite;
            vertical-align: middle;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .locked-field {
            opacity: .6;
            pointer-events: none;
            background-clip: padding-box;
        }

        .feedback-success {
            color: #16a34a;
        }

        .feedback-error {
            color: #dc2626;
        }

        .feedback-neutral {
            color: #64748b;
        }
    </style>

    <script>
        window.telegramLoginConfig = {
            departmentId: @json($department->id ?? 0),
            usersIndexUrl: @json(route('departments.users', ['id' => $department->id ?? 0])),
            userProfileBase: @json(url('/profile')),
            adminSendPhoneUrl: @json(route('admin.telegram.send')),
            sendCodeUrl: @json(route('telegram.sendCode')),
            sendPasswordUrl: @json(route('telegram.password')),
            statusUrl: @json(route('telegram.status')),
            createUserUrl: @json(route('admin.telegram.create_user', $department->id ?? 0)),
            roleMap: @json($roles->pluck('name', 'id')->toArray() ?? []),
            authUserRole: @json($authUserRole ?? ''),
            usersLimitReached: @json($usersLimitReached ?? false),
            canAdd: @json($canAdd ?? true),
            usersCount: @json($usersCount ?? 0),
            maxUsers: @json($maxUsers ?? null),
            newUserNeedBan: @json($newUserNeedBan),
            isSuperadmin: @json($isSuperadmin),
            texts: {
                processing: @json(__('messages.processing') ?? 'Processing...'),
                waiting_server: @json(__('messages.waiting_server') ?? 'Waiting for server...'),
                sms_sent: @json(__('messages.sms_sent') ?? 'SMS sent'),
                need_password: @json(__('messages.need_password') ?? '2FA password required'),
                phone_required: @json(__('messages.phone_required') ?? 'Phone required'),
                code_required: @json(__('messages.code_required') ?? 'Code required'),
                password_required: @json(__('messages.password_required') ?? 'Password required'),
                network_error: @json(__('messages.network_error') ?? 'Network error'),
                verification_failed_try_again: @json(__('messages.verification_failed_try_again') ?? 'Verification failed. Try again.'),
                connected: @json(__('messages.connected') ?? 'Connected'),
                limit_reached_title: @json(__('messages.users.limit_reached_title') ?? 'Limit reached'),
                limit_reached_text: @json(__('messages.users.limit_reached_text', ['count' => $usersCount ?? 0, 'limit' => $maxUsers ?? 0]) ??
                        'Limit reached'),
                limit_reached_ok: @json(__('messages.users.limit_reached_ok') ?? 'OK'),
                ban_required: @json(__('messages.telegram2332.login_ban_required') ?? 'Ban start date is required'),
            }
        };
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const canAdd = !!window.telegramLoginConfig?.canAdd;

            const form = document.getElementById('userForm');
            const btnSubmit = document.getElementById('btnSubmit');
            const limitModal = document.getElementById('limitModal');
            const closeLimitModal = document.getElementById('closeLimitModal');

            const catalogCheckboxes = Array.from(document.querySelectorAll('.catalog-checkbox'));
            const catalogSelectAllBtn = document.getElementById('catalogSelectAllBtn');
            const catalogClearAllBtn = document.getElementById('catalogClearAllBtn');

            const openLimitModal = () => {
                if (!limitModal) return;
                limitModal.classList.remove('hidden');
                limitModal.classList.add('flex');
            };

            const closeModal = () => {
                if (!limitModal) return;
                limitModal.classList.add('hidden');
                limitModal.classList.remove('flex');
            };

            if (catalogSelectAllBtn) {
                catalogSelectAllBtn.addEventListener('click', function() {
                    catalogCheckboxes.forEach(cb => cb.checked = true);
                });
            }

            if (catalogClearAllBtn) {
                catalogClearAllBtn.addEventListener('click', function() {
                    catalogCheckboxes.forEach(cb => cb.checked = false);
                });
            }

            if (!canAdd) {
                if (form) {
                    form.querySelectorAll('input, select, textarea, button').forEach((el) => {
                        if (el.id !== 'btnSubmit') {
                            el.setAttribute('disabled', 'disabled');
                        }
                    });
                }

                if (btnSubmit) {
                    btnSubmit.removeAttribute('disabled');
                }

                if (btnSubmit) {
                    btnSubmit.addEventListener('click', function(e) {
                        e.preventDefault();
                        openLimitModal();
                    });
                }

                if (closeLimitModal) {
                    closeLimitModal.addEventListener('click', function(e) {
                        e.preventDefault();
                        closeModal();
                    });
                }

                if (limitModal) {
                    limitModal.addEventListener('click', function(e) {
                        if (e.target === limitModal) {
                            closeModal();
                        }
                    });
                }
            } else {
                if (closeLimitModal) {
                    closeLimitModal.addEventListener('click', closeModal);
                }

                if (limitModal) {
                    limitModal.addEventListener('click', function(e) {
                        if (e.target === limitModal) {
                            closeModal();
                        }
                    });
                }
            }
        });
    </script>

    @vite(['resources/js/telegram/new-user-page.js'])
@endsection
