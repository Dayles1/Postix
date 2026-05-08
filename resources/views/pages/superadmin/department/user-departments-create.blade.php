@extends('layouts.app')

@section('title', __('messages.telegram.login'))
@section('page-title', __('messages.telegram.login'))

@section('content')
    @php
        $catalogs = $catalogs ?? collect();
        $plan = old('plan', $plan ?? 'pro');

        $redirectUrl = $plan === 'pro'
            ? route('departments.pro-users')
            : route('departments.free-users');
    @endphp

    <div class="mx-auto max-w-3xl px-4 py-6 sm:py-8">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="mb-6 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                        {{ __('messages.telegram.login') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $plan === 'trial'
                            ? __('messages.departments.free_explanation') ?? 'Trial rejimda ban 7 kunga avtomatik qo‘yiladi.'
                            : __('messages.telegram2332.login_ban_schedule_help') }}
                    </p>
                </div>

                <div
                    class="shrink-0 rounded-full px-3 py-1 text-xs font-medium
                    {{ $plan === 'trial'
                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                        : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' }}">
                    {{ strtoupper($plan) }}
                </div>
            </div>

            @if (session('success'))
                <div class="mb-4 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800 dark:bg-green-900/20 dark:text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('user.departments.store') }}" method="POST" class="space-y-5">
                @csrf

                <input type="hidden" name="plan" value="{{ $plan }}">
                <input type="hidden" name="redirecturl" value="{{ $redirectUrl }}">

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('messages.users.name') }}
                        </label>
                        <input type="text" name="name" value="{{ old('name') }}"
                            placeholder="{{ __('messages.users.name') }}"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('messages.users.email') }}
                        </label>
                        <input type="email" name="login" value="{{ old('login') }}"
                            placeholder="{{ __('messages.users.email') }}"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                        @error('login')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('messages.users.new_password') }}
                    </label>

                    <div class="relative">
                        <input type="password" id="passwordField" name="password"
                            value="{{ old('password', \Illuminate\Support\Str::random(12)) }}" placeholder="******"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 pr-12 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white">

                        <button type="button" id="togglePassword"
                            class="absolute inset-y-0 right-2 my-auto inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800"
                            aria-label="Toggle password visibility">
                            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 12s3.75-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.75 7.5-9.75 7.5S2.25 12 2.25 12Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0Z" />
                            </svg>
                        </button>
                    </div>

                    @error('password')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-800/40">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ __('messages.catalogs.title') }}
                        </label>

                        <div class="flex items-center gap-2">
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

                    <div class="mb-2">
                        <input type="text" id="catalogSearchInput"
                            placeholder="{{ __('messages.users.search') ?? 'Search...' }}"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
                    </div>

                    @error('catalog_ids')
                        <p class="mb-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror

                    @if ($catalogs->count())
                        <div id="catalogCheckboxList"
                            class="grid grid-cols-1 gap-2 overflow-y-auto rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900"
                            style="max-height: 5cm; min-height: 5cm">
                            @foreach ($catalogs as $catalog)
                                <label
                                    class="catalog-item flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                    data-title="{{ \Illuminate\Support\Str::lower($catalog->title) }}">
                                    <input type="checkbox"
                                        class="catalog-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        name="catalog_ids[]" value="{{ $catalog->id }}"
                                        {{ in_array($catalog->id, old('catalog_ids', [])) ? 'checked' : '' }}>
                                    <span class="flex-1">{{ $catalog->title }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-lg border border-dashed border-gray-300 bg-white px-3 py-4 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                            -
                        </div>
                    @endif
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Ban starts at
                    </label>

                    <div class="space-y-2">
                        <div class="relative">
                            <input
                                type="datetime-local"
                                id="banStartsAtInput"
                                name="ban_starts_at"
                                value="{{ old('ban_starts_at') }}"
                                min="{{ now()->format('Y-m-d\TH:i') }}"
                                step="60"
                                required
                                class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 pr-12 text-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >

                            <button
                                type="button"
                                id="banOpenCalendarBtn"
                                class="absolute inset-y-0 right-2 my-auto inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800"
                                aria-label="Open calendar">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 2.75v3M16 2.75v3M3.75 9.25h16.5M6 5.75h12A2.25 2.25 0 0 1 20.25 8v10.5A2.25 2.25 0 0 1 18 20.75H6A2.25 2.25 0 0 1 3.75 18.5V8A2.25 2.25 0 0 1 6 5.75Z" />
                                </svg>
                            </button>
                        </div>

                        
                    </div>

                    @error('ban_starts_at')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-800/40">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('messages.add_minute') }}
                    </label>

                    <div class="grid grid-cols-2 gap-3 rounded-xl border border-gray-200 px-3 py-3 dark:border-gray-700 sm:px-4">
                        <label
                            class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm transition
                            {{ old('minute_package', '0') == '1' ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300' : 'border-gray-200 text-gray-700 dark:border-gray-700 dark:text-gray-300' }}">
                            <input type="radio" name="minute_package" value="1"
                                class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"
                                {{ old('minute_package', '0') == '1' ? 'checked' : '' }}>
                            {{ __('messages.yes') ?? 'Ha' }}
                        </label>

                        <label
                            class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm transition
                            {{ old('minute_package', '0') == '0' ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300' : 'border-gray-200 text-gray-700 dark:border-gray-700 dark:text-gray-300' }}">
                            <input type="radio" name="minute_package" value="0"
                                class="h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"
                                {{ old('minute_package', '0') == '0' ? 'checked' : '' }}>
                            {{ __('messages.no') ?? 'Yo‘q' }}
                        </label>
                    </div>

                    @error('minute_package')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:items-center sm:justify-end">
                    <a href="{{ $redirectUrl }}"
                        class="inline-flex h-11 items-center justify-center rounded-xl border border-gray-300 px-5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                        {{ __('messages.admin.cancel') ?? 'Cancel' }}
                    </a>

                    <button type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-xl bg-blue-600 px-5 text-sm font-medium text-white hover:bg-blue-700">
                        {{ __('messages.admin.add_user') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

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

            const passwordField = document.getElementById('passwordField');
            const togglePassword = document.getElementById('togglePassword');
            const eyeIcon = document.getElementById('eyeIcon');

            if (togglePassword && passwordField) {
                togglePassword.addEventListener('click', function () {
                    const isPassword = passwordField.type === 'password';
                    passwordField.type = isPassword ? 'text' : 'password';

                    if (eyeIcon) {
                        eyeIcon.innerHTML = isPassword
                            ? `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 2.25 12s3.75 7.5 9.75 7.5c1.95 0 3.724-.38 5.246-1.002M6.53 6.53A9.956 9.956 0 0 1 12 4.5c6 0 9.75 7.5 9.75 7.5a18.285 18.285 0 0 1-4.16 4.67M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6.75 9.75L3 2.25" />`
                            : `<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.75 7.5-9.75 7.5S2.25 12 2.25 12Z" />
                               <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 0 1 6 0Z" />`;
                    }
                });
            }

            const banInput = document.getElementById('banStartsAtInput');
            const banOpenCalendarBtn = document.getElementById('banOpenCalendarBtn');

            if (banOpenCalendarBtn && banInput) {
                banOpenCalendarBtn.addEventListener('click', function () {
                    if (typeof banInput.showPicker === 'function') {
                        banInput.showPicker();
                        return;
                    }

                    banInput.focus();
                });
            }

            if (banInput) {
                const now = new Date();
                const pad = (n) => String(n).padStart(2, '0');
                const minValue = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
                banInput.min = minValue;
            }
        });
    </script>
@endsection