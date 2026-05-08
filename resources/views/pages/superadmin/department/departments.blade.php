@extends('layouts.app')

@section('title', __('messages.admin.departments'))
@section('page-title', __('messages.admin.departments'))

@section('content')
    {{-- Flatpickr --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Flatpickr Responsive & Modern Styles --}}
    <style>
        .flatpickr-calendar {
            border-radius: 1.5rem !important;
            box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25) !important;
            border: 1px solid rgb(229 231 235) !important;
            background: #ffffff !important;
            width: 320px !important;
            max-width: 100% !important;
            font-family: inherit !important;
            font-size: 15px !important;
            overflow: hidden !important;
        }

        .dark .flatpickr-calendar {
            background: #111827 !important;
            border-color: #374151 !important;
            color: #f3f4f6 !important;
            box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.4) !important;
        }

        .flatpickr-days,
        .flatpickr-weekdays {
            padding: 8px !important;
        }

        .flatpickr-day {
            border-radius: 9999px !important;
            height: 38px !important;
            line-height: 38px !important;
            font-weight: 500 !important;
            color: #1f2937 !important;
        }

        .dark .flatpickr-day {
            color: #f3f4f6 !important;
        }

        .flatpickr-day.today {
            border: 2px solid #0ea5e9 !important;
            background: transparent !important;
        }

        .flatpickr-day.selected,
        .flatpickr-day.startRange,
        .flatpickr-day.endRange {
            background: #0ea5e9 !important;
            color: #ffffff !important;
            border: none !important;
        }

        .flatpickr-day.inRange {
            background: #0ea5e9 / 10% !important;
            color: #1f2937 !important;
        }

        .dark .flatpickr-day.inRange {
            background: #0ea5e9 / 20% !important;
            color: #f3f4f6 !important;
        }

        .flatpickr-months {
            padding: 12px 16px !important;
            align-items: center !important;
        }

        .flatpickr-prev-month,
        .flatpickr-next-month {
            padding: 6px !important;
            color: #6b7280 !important;
        }

        .dark .flatpickr-prev-month,
        .dark .flatpickr-next-month {
            color: #9ca3af !important;
        }

        .flatpickr-time {
            border-top: 1px solid #e5e7eb !important;
            padding: 12px 16px !important;
        }

        .dark .flatpickr-time {
            border-top-color: #374151 !important;
        }

        .flatpickr-time input {
            background: transparent !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 9999px !important;
            color: inherit !important;
            font-size: 15px !important;
            height: 36px !important;
        }

        .dark .flatpickr-time input {
            border-color: #4b5563 !important;
        }

        @media (max-width: 640px) {
            .flatpickr-calendar {
                width: 100% !important;
                max-width: 340px !important;
                left: 50% !important;
                transform: translateX(-50%) !important;
                margin: 12px auto !important;
                border-radius: 1.25rem !important;
            }

            .flatpickr-wrapper {
                width: 100% !important;
            }

            .flatpickr-input {
                font-size: 16px !important;
            }

            .flatpickr-day {
                height: 42px !important;
                line-height: 42px !important;
                font-size: 15px !important;
            }
        }
    </style>

    <div class="mx-auto max-w-screen-2xl pt-6 md:pt-8 lg:pt-10 pb-16 md:pb-20 px-4 md:px-6 space-y-6">

        {{-- Filters + Create (MOBILE-FRIENDLY) --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

            {{-- Filter form --}}
            <form method="GET" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <input type="text" name="search" value="{{ $search ?? '' }}"
                    placeholder="{{ __('messages.users.search') ?? 'Search...' }}"
                    class="flex-1 min-w-0 h-10 px-3 rounded-lg border border-gray-200 placeholder-gray-400
                       focus:ring-2 focus:ring-brand-300 focus:border-brand-500 dark:bg-gray-900 dark:border-gray-700 dark:placeholder-gray-500" />

                <select name="sort"
                    class="h-10 px-3 rounded-lg border border-gray-200 dark:bg-gray-900 dark:border-gray-700 flex-shrink-0">
                    <option value="desc" {{ ($sort ?? 'desc') === 'desc' ? 'selected' : '' }}>
                        {{ __('messages.new_first') }}
                    </option>
                    <option value="asc" {{ ($sort ?? '') === 'asc' ? 'selected' : '' }}>
                        {{ __('messages.old_first') }}
                    </option>
                </select>
                @unless ($deleted || $free)
                    <select name="banned"
                        class="h-10 px-3 rounded-lg border border-gray-200 dark:bg-gray-900 dark:border-gray-700 flex-shrink-0">
                        <option value="all" {{ ($bannedFilter ?? 'all') === 'all' ? 'selected' : '' }}>
                            {{ __('messages.filters.banned_all') ?? 'All' }}</option>
                        <option value="not_banned" {{ ($bannedFilter ?? '') === 'not_banned' ? 'selected' : '' }}>
                            {{ __('messages.filters.not_banned') ?? 'Not banned' }}</option>
                        <option value="banned" {{ ($bannedFilter ?? '') === 'banned' ? 'selected' : '' }}>
                            {{ __('messages.filters.banned') ?? 'Banned' }}</option>
                    </select>
                @endunless


                <button type="submit"
                    class="inline-flex items-center h-10 px-4 rounded-lg bg-brand-500 text-white hover:bg-brand-600 flex-shrink-0 whitespace-nowrap">
                    {{ __('messages.filter') ?? 'Filter' }}
                </button>
            </form>

            <div class="flex items-center gap-2 w-full sm:w-auto sm:justify-end">
                @unless ($deleted || $forcedBanned)
                    <button @click="$dispatch('open-dept-modal', { mode: 'create' })"
                        class="inline-flex items-center justify-center h-10 px-4 rounded-lg bg-green-600 text-white hover:bg-green-700 w-full sm:w-auto">
                        + {{ __('messages.admin.create_department') ?? 'Create Department' }}
                    </button>
                @endunless
            </div>
        </div>

        {{-- Departments grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($deptStats as $d)
                @php
                    $isDeleted = !is_null($d->deleted_at);
                    $isBanned = !empty($d->bans_active_count) && $d->bans_active_count > 0;
                    $deptPlan = $d->plan ?? 'pro';
                @endphp

                <article
                    class="relative overflow-hidden rounded-2xl border bg-white p-5 shadow-sm dark:bg-gray-900
                           {{ $isDeleted ? 'border-red-200/60 ring-1 ring-red-50/30' : 'border-gray-200' }}">

                    <div
                        class="absolute left-0 top-0 bottom-0 w-1.5 rounded-r-2xl 
                               {{ $isDeleted ? 'bg-red-500' : ($isBanned ? 'bg-orange-500' : 'bg-brand-500') }}">
                    </div>

                    <div class="pl-4">
                        <header class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate">
                                    {{ $d->name }}
                                </h3>

                                @if ($isBanned)
                                    <div class="mt-1 flex items-center gap-2 text-sm text-orange-700">
                                        <span class="text-base">⚠️</span>
                                        <span>{{ __('messages.admin.banned') ?? 'Banned' }}</span>
                                    </div>
                                @elseif($isDeleted)
                                    <div class="mt-1 flex items-center gap-2 text-sm text-red-600">
                                        <span class="text-base">🗑</span>
                                        <span>{{ \Carbon\Carbon::parse($d->deleted_at)->format('d.m.Y H:i') }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                @if (!$isDeleted)
                                    <button
                                        @click="$dispatch('open-dept-modal', { mode: 'edit', id: {{ $d->id }}, name: '{{ addslashes($d->name) }}', plan: '{{ $deptPlan }}' })"
                                        class="inline-flex items-center justify-center h-9 w-9 rounded-xl border border-gray-200 hover:bg-gray-50 dark:border-gray-700"
                                        title="{{ __('messages.admin.edit') ?? 'Edit' }}">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                                            <path stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M3 21v-3.75L17.81 2.44a2 2 0 012.83 0l.92.92a2 2 0 010 2.83L6.75 21H3z" />
                                        </svg>
                                    </button>

                                    @if ($deptPlan === 'trial')
                                        <button
                                            @click="$dispatch('open-upgrade-modal', { id: {{ $d->id }}, name: '{{ addslashes($d->name) }}' })"
                                            class="inline-flex items-center justify-center h-9 w-9 rounded-xl border border-gray-200 hover:bg-gray-50 dark:border-gray-700"
                                            title="{{ __('messages.departments.upgrade_confirm_button') ?? 'Upgrade to Pro' }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-green-600">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M12 16V8m0 0l-3 3m3-3l3 3M12 3a9 9 0 100 18 9 9 0 000-18z" />
                                            </svg>
                                        </button>
                                    @endif

                                    <button
                                        @click="$dispatch('open-delete-modal', { id: {{ $d->id }}, name: '{{ addslashes($d->name) }}' })"
                                        class="inline-flex items-center justify-center h-9 w-9 rounded-xl border border-gray-200 hover:bg-red-50 dark:border-gray-700"
                                        title="{{ __('messages.admin.delete') ?? 'Delete' }}">
                                        <svg class="w-4 h-4 text-red-600" viewBox="0 0 24 24" fill="none">
                                            <path d="M3 6h18" stroke="currentColor" stroke-width="1.5"
                                                stroke-linecap="round" />
                                            <path d="M8 6v12a2 2 0 002 2h4a2 2 0 002-2V6" stroke="currentColor"
                                                stroke-width="1.5" stroke-linecap="round" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </header>

                        <div class="mt-4 text-sm text-gray-600 dark:text-gray-300 space-y-1.5">
                            <div class="flex justify-between">
                                <span>{{ __('messages.admin.users') ?? 'Users' }}:</span>
                                <span class="font-semibold">{{ $d->users_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ __('messages.admin.phones') ?? 'Phones' }}:</span>
                                <span class="font-semibold">{{ $d->active_phones_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ __('messages.admin.operations') ?? 'Operations' }}:</span>
                                <span class="font-semibold">{{ $d->message_groups_count }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ __('messages.admin.messages_count') ?? 'Messages' }}:</span>
                                <span class="font-semibold">{{ $d->telegram_messages_count }}</span>
                            </div>
                        </div>

                        <footer class="mt-5">
                            @if (!$isDeleted)
                                <a href="{{ route('departments.show', $d->id) }}"
                                    class="block w-full text-center sm:w-auto sm:inline-flex items-center px-5 py-2.5 rounded-2xl 
                                        border border-gray-200 dark:border-gray-700 
                                        text-gray-800 dark:text-gray-200
                                        text-sm font-medium 
                                        hover:bg-gray-50 dark:hover:bg-gray-700 
                                        transition">
                                    {{ __('messages.admin.details') ?? 'Details' }}
                                </a>
                            @endif

                        </footer>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed p-8 text-center text-gray-500">
                    {{ __('messages.no_departments') ?? 'No departments found.' }}
                </div>
            @endforelse
        </div>

        @if ($deptStats->hasPages())
            <div class="mt-8 flex justify-center overflow-x-auto pb-2">
                <nav class="inline-flex -space-x-px rounded-2xl shadow-sm" aria-label="Pagination">
                    @if ($deptStats->onFirstPage())
                        <span
                            class="inline-flex items-center px-4 py-3 text-gray-400 bg-gray-100 rounded-l-2xl cursor-not-allowed text-sm">
                            Previous
                        </span>
                    @else
                        <a href="{{ $deptStats->previousPageUrl() }}"
                            class="inline-flex items-center px-4 py-3 text-gray-700 bg-white border border-gray-300 rounded-l-2xl hover:bg-gray-50 text-sm">
                            Previous
                        </a>
                    @endif

                    @foreach ($deptStats->getUrlRange(1, $deptStats->lastPage()) as $page => $url)
                        @if ($page == $deptStats->currentPage())
                            <span aria-current="page"
                                class="z-10 inline-flex items-center px-5 py-3 -ml-px text-white bg-blue-600 border border-blue-600 text-sm">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}"
                                class="inline-flex items-center px-5 py-3 -ml-px text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 text-sm">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach

                    @if ($deptStats->hasMorePages())
                        <a href="{{ $deptStats->nextPageUrl() }}"
                            class="inline-flex items-center px-4 py-3 -ml-px text-gray-700 bg-white border border-gray-300 rounded-r-2xl hover:bg-gray-50 text-sm">
                            Next
                        </a>
                    @else
                        <span
                            class="inline-flex items-center px-4 py-3 -ml-px text-gray-400 bg-gray-100 rounded-r-2xl cursor-not-allowed text-sm">
                            Next
                        </span>
                    @endif
                </nav>
            </div>
        @endif
    </div>

    <div x-data="departmentsPage({{ $free ? 'true' : 'false' }})" @open-dept-modal.window="handleOpenDeptModal($event.detail)"
        @open-delete-modal.window="handleOpenDeleteModal($event.detail)"
        @open-upgrade-modal.window="handleOpenUpgradeModal($event.detail)" x-cloak>

        {{-- Create / Edit Modal (X tugmasi qo‘shildi) --}}
        <div x-show="modalOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="closeModal()"></div>

            <div class="relative w-full max-w-lg rounded-3xl bg-white dark:bg-gray-900 p-6 shadow-2xl mx-2">

                <!-- YANGI: X tugmasi qo‘shilgan joy -->
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white"
                        x-text="modalMode === 'create' ? '{{ addslashes(__('messages.departments.create') ?? 'Create Department') }}' : '{{ addslashes(__('messages.departments.edit') ?? 'Edit Department') }}'">
                    </h3>

                    <!-- X (Close) tugmasi -->
                    <button @click="closeModal()"
                        class="flex items-center justify-center w-8 h-8 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form :action="formAction" method="POST" class="mt-1">
                    @csrf
                    <template x-if="modalMode === 'edit'">
                        <input type="hidden" name="_method" value="PUT" />
                    </template>

                    <input type="hidden" name="plan" :value="form.plan" />

                    <div class="space-y-4">
                        <div>
                            <label class="text-sm text-gray-700 dark:text-gray-300">
                                {{ __('messages.departments.name') ?? 'Name' }}
                            </label>
                            <input type="text" name="name" x-model="form.name" required maxlength="191"
                                class="mt-1 h-11 w-full rounded-2xl border px-4 bg-transparent border-gray-200 dark:border-gray-700" />
                            <p class="mt-1 text-xs text-gray-500">
                                {{ __('messages.departments.hint') ?? 'For example: Marketing, Sales, Support' }}
                            </p>
                        </div>

                        @if (!$free)
                            <div x-show="modalMode === 'create'">
                                <label class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ __('messages.telegram.zq_ban_7f_label') }}

                                </label>
                                <input type="text" name="ban_starts_at" id="ban_starts_at"
                                    x-model="form.ban_starts_at"
                                    class="mt-1 h-11 w-full rounded-2xl border px-4 bg-transparent border-gray-200 dark:border-gray-700" />
                            </div>
                        @endif

                        <template x-if="form.plan === 'trial'">
                            <p class="mt-2 text-sm text-gray-500">
                                {{ __('messages.departments.free_explanation') ?? 'Free departments are trials: the department will be automatically banned after 3 days unless upgraded.' }}
                            </p>
                        </template>

                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" @click="closeModal()"
                                class="h-11 px-6 rounded-2xl border text-sm font-medium">
                                {{ __('messages.admin.cancel') ?? 'Cancel' }}
                            </button>
                            <button type="submit"
                                class="h-11 px-6 rounded-2xl bg-brand-500 text-white text-sm font-medium">
                                {{ __('messages.common.save') ?? 'Save' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Upgrade confirm modal --}}
        <div x-show="upgradeOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="closeUpgrade()"></div>

            <div class="relative w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-5 shadow-xl mx-2">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('messages.departments.upgrade_confirm_title') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">
                    {{ __('messages.departments.upgrade_confirm_text') }}</p>

                <form :action="`/departments/${upgradeId}/upgrade`" method="POST" class="mt-4">
                    @csrf
                    <div class="flex justify-end gap-2 mt-2">
                        <button type="button" @click="closeUpgrade()" class="h-10 px-4 rounded-lg border">
                            {{ __('messages.admin.cancel') ?? 'Cancel' }}
                        </button>
                        <button type="submit" class="h-10 px-4 rounded-lg bg-brand-500 text-white">
                            {{ __('messages.departments.upgrade_confirm_button') ?? 'Upgrade to Pro' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Delete confirm modal --}}
        <div x-show="deleteOpen" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="closeDelete()"></div>

            <div class="relative w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-5 shadow-xl mx-2">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('messages.admin.delete') ?? 'Delete' }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">
                    {{ __('messages.users.delete_confirm') ?? 'Are you sure you want to delete this department?' }}
                </p>

                <form :action="deleteAction" method="POST" class="mt-4">
                    @csrf
                    <input type="hidden" name="_method" value="DELETE" />
                    <div class="flex justify-end gap-2 mt-2">
                        <button type="button" @click="closeDelete()" class="h-10 px-4 rounded-lg border">
                            {{ __('messages.admin.cancel') ?? 'Cancel' }}
                        </button>
                        <button type="submit" class="h-10 px-4 rounded-lg bg-red-600 text-white">
                            {{ __('messages.admin.delete') ?? 'Delete' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
        function departmentsPage(free = false) {
            return {
                modalOpen: false,
                modalMode: 'create',
                form: {
                    id: null,
                    name: '',
                    plan: free === true || free === 'true' ? 'trial' : 'pro',
                    ban_starts_at: '',
                },
                formAction: '{{ route('departments.store') }}',
                deleteOpen: false,
                deleteAction: '',
                upgradeOpen: false,
                upgradeId: null,
                upgradeDeptName: '',

                toggleBodyScroll() {
                    if (this.modalOpen || this.deleteOpen || this.upgradeOpen) {
                        document.documentElement.style.overflow = 'hidden';
                    } else {
                        document.documentElement.style.overflow = '';
                    }
                },

                init() {
                    @if (session('modal') === 'create')
                        this.openCreateModal();
                    @elseif (session('modal') === 'edit' && session('edit_id'))
                        this.openEditModal({
                            id: {{ session('edit_id') }},
                            name: '{{ addslashes(session('edit_name') ?? '') }}',
                            plan: '{{ session('edit_plan') ?? ($free ? 'trial' : 'pro') }}'
                        });
                    @endif

                    @if ($errors->any() && old('modal') === 'create')
                        this.openCreateModal();
                        this.form.name = '{{ addslashes(old('name', '')) }}';
                        this.form.ban_starts_at = '{{ addslashes(old('ban_starts_at', '')) }}';
                    @elseif ($errors->any() && old('modal') === 'edit' && old('edit_id'))
                        this.formAction = '/departments/' + {{ old('edit_id') }};
                        this.modalMode = 'edit';
                        this.form.id = {{ old('edit_id') }};
                        this.form.name = '{{ addslashes(old('name', '')) }}';
                        this.form.plan = '{{ old('plan', $free ? 'trial' : 'pro') }}';
                        this.form.ban_starts_at = '{{ addslashes(old('ban_starts_at', '')) }}';
                        this.modalOpen = true;
                    @endif

                    this.toggleBodyScroll();
                    this.initBanPicker();
                },

                initBanPicker() {
                    const el = document.querySelector('#ban_starts_at');
                    if (!el) return;

                    if (el._flatpickr) {
                        el._flatpickr.destroy();
                    }

                    flatpickr(el, {
                        enableTime: true,
                        dateFormat: "Y-m-d H:i",
                        allowInput: true,
                        minDate: "today",
                        static: false,
                        position: 'auto'
                    });
                },

                openCreateModal() {
                    this.modalMode = 'create';
                    this.form = {
                        id: null,
                        name: '',
                        plan: (free === true || free === 'true') ? 'trial' : 'pro',
                        ban_starts_at: '',
                    };
                    this.formAction = '{{ route('departments.store') }}';
                    this.modalOpen = true;
                    this.toggleBodyScroll();

                    setTimeout(() => this.initBanPicker(), 0);
                },

                openEditModal(detail) {
                    if (!detail || !detail.id) return;
                    this.modalMode = 'edit';
                    this.form.id = detail.id;
                    this.form.name = detail.name || '';
                    this.form.plan = detail.plan || ((free === true || free === 'true') ? 'trial' : 'pro');
                    this.formAction = '/departments/' + detail.id;
                    this.modalOpen = true;
                    this.toggleBodyScroll();
                },

                closeModal() {
                    this.modalOpen = false;
                    this.toggleBodyScroll();
                    this.form = {
                        id: null,
                        name: '',
                        plan: (free === true || free === 'true') ? 'trial' : 'pro',
                        ban_starts_at: '',
                    };
                    this.formAction = '{{ route('departments.store') }}';
                },

                handleOpenDeptModal(detail) {
                    if (!detail || !detail.mode) return;
                    if (detail.mode === 'create') this.openCreateModal();
                    else if (detail.mode === 'edit') this.openEditModal(detail);
                },

                handleOpenDeleteModal(detail) {
                    if (!detail || !detail.id) return;
                    this.deleteAction = '/departments/' + detail.id;
                    this.deleteOpen = true;
                    this.toggleBodyScroll();
                },

                closeDelete() {
                    this.deleteOpen = false;
                    this.deleteAction = '';
                    this.toggleBodyScroll();
                },

                handleOpenUpgradeModal(detail) {
                    if (!detail || !detail.id) return;
                    this.upgradeDeptName = detail.name || '';
                    this.upgradeId = detail.id;
                    this.upgradeOpen = true;
                    this.toggleBodyScroll();
                },

                closeUpgrade() {
                    this.upgradeOpen = false;
                    this.upgradeId = null;
                    this.upgradeDeptName = '';
                    this.toggleBodyScroll();
                }
            }
        }

        document.addEventListener('alpine:init', () => {});
    </script>
@endsection
