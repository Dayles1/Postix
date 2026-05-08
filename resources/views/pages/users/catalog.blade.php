{{-- resources/views/pages/users/catalog.blade.php --}}
@extends('layouts.app')

@section('title', __('messages.catalogs.title'))

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $permissions = $permissions ?? [
            'isSuperadmin' => optional(optional(auth()->user())->role)->name === 'superadmin',
            'isAdmin' => optional(optional(auth()->user())->role)->name === 'admin',
            'isUser' => optional(optional(auth()->user())->role)->name === 'user',
        ];

        $departmentId = $department?->id ?? null;
        $baseUrl = $departmentId ? url("departments/{$departmentId}/catalogs") : url('catalogs');

        $indexRoute = $departmentId
            ? route('catalogs.index', ['department' => $departmentId])
            : route('catalogs.index');

        $currentFilter = request()->get('filter', 'all');
        $queryParams = request()->only(['search']);
        $allFilterUrl = $indexRoute . '?' . http_build_query(array_merge($queryParams, ['filter' => 'all']));
        $myFilterUrl = $indexRoute . '?' . http_build_query(array_merge($queryParams, ['filter' => 'my']));
    @endphp

    <div class="mx-auto max-w-7xl p-4 space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h1 class="text-2xl font-semibold">{{ __('messages.catalogs.title') }}</h1>
            @if (!$permissions['isSuperadmin'])
                <button type="button" data-modal-open data-modal-target="modalCreate"
                    class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-2xl font-medium whitespace-nowrap">
                    {{ __('messages.catalogs.create') }}
                </button>
            @endif

        </div>

        {{-- Filters --}}
        <div class="flex flex-col sm:flex-row gap-4 items-end">
            <form method="GET" action="{{ $indexRoute }}" class="flex-1 flex gap-2 w-full">
                <input type="hidden" name="filter" value="{{ $currentFilter }}">
                <input name="search" value="{{ request('search') }}"
                    placeholder="{{ __('messages.catalogs.search_placeholder') }}"
                    class="flex-1 px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700" />
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-medium">
                    {{ __('messages.catalogs.search_btn') }}
                </button>
            </form>

            <div class="inline-flex rounded-2xl shadow-sm border border-gray-300 dark:border-gray-600 overflow-hidden">
                <a href="{{ $allFilterUrl }}"
                    class="px-5 py-3 text-sm font-medium transition-colors {{ $currentFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                    {{ __('messages.period.all') }}
                </a>
                <a href="{{ $myFilterUrl }}"
                    class="px-5 py-3 text-sm font-medium transition-colors {{ $currentFilter === 'my' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                    {{ __('messages.period.my') }}
                </a>
            </div>
        </div>

        {{-- List / Table --}}
        <div class="space-y-4">
            @if ($catalogs->isEmpty())
                <div class="p-6 bg-white rounded-2xl shadow text-center text-gray-500 dark:bg-gray-800 dark:text-gray-300">
                    {{ __('messages.catalogs.empty') }}
                </div>
            @else
                <div
                    class="bg-white border border-gray-200 rounded-3xl shadow dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
                    <!-- Desktop table -->
                    <div class="hidden md:block">
                        <table class="min-w-full w-full table-auto">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 dark:text-gray-400">
                                        {{ __('messages.catalogs.table.catalog') }}</th>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 dark:text-gray-400">
                                        {{ __('messages.catalogs.table.owner') }}</th>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 dark:text-gray-400">
                                        {{ __('messages.catalogs.table.peers_count') }}</th>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-600 dark:text-gray-400">
                                        {{ __('messages.catalogs.table.created_at') }}</th>
                                    <th class="px-6 py-4 text-right text-xs font-medium text-gray-600 dark:text-gray-400">
                                        {{ __('messages.catalogs.table.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y dark:divide-gray-700">
                                @foreach ($catalogs as $c)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                        data-id="{{ $c->id }}">
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $c->title }}
                                            </div>
                                            @if (!empty($c->description ?? null))
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                                    {{ \Illuminate\Support\Str::limit($c->description, 90) }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            {{ $c->user->name ?? '-' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            {{ is_array($c->peers) ? count($c->peers) : 0 }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                                            <div class="whitespace-nowrap">
                                                {{ $c->created_at?->format('Y-m-d H:i') ?? '-' }}</div>

                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex justify-end gap-2">
                                                <button type="button"
                                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-xl transition"
                                                    data-action="show" data-id="{{ $c->id }}">
                                                    {{ __('messages.catalogs.btn_show') }}
                                                </button>

                                                @if ($permissions['isSuperadmin'] || $permissions['isAdmin'] || $c->user_id === auth()->id())
                                                    <button type="button"
                                                        class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm rounded-xl transition"
                                                        data-action="edit" data-id="{{ $c->id }}">
                                                        {{ __('messages.catalogs.btn_edit') }}
                                                    </button>
                                                    <button type="button"
                                                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-xl transition"
                                                        data-action="delete" data-id="{{ $c->id }}"
                                                        data-title="{{ e($c->title) }}">
                                                        {{ __('messages.catalogs.btn_delete') }}
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile cards -->
                    <div class="md:hidden space-y-3 p-4">
                        @foreach ($catalogs as $c)
                            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-3xl p-5 shadow-sm"
                                data-id="{{ $c->id }}">
                                <div class="flex justify-between">
                                    <div>
                                        <div class="font-semibold text-base text-gray-900 dark:text-white">
                                            {{ $c->title }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $c->user->name ?? '-' }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ is_array($c->peers) ? count($c->peers) : 0 }}
                                            {{ __('messages.catalogs.peers_label') }}</div>
                                    </div>
                                </div>

                                <div class="mt-5 grid grid-cols-3 gap-2">
                                    <button type="button"
                                        class="col-span-1 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm rounded-2xl transition"
                                        data-action="show" data-id="{{ $c->id }}">
                                        {{ __('messages.catalogs.btn_show') }}
                                    </button>

                                    @if ($permissions['isSuperadmin'] || $permissions['isAdmin'] || $c->user_id === auth()->id())
                                        <button type="button"
                                            class="col-span-1 py-3 bg-yellow-500 hover:bg-yellow-600 text-white text-sm rounded-2xl transition"
                                            data-action="edit" data-id="{{ $c->id }}">
                                            {{ __('messages.catalogs.btn_edit') }}
                                        </button>
                                        <button type="button"
                                            class="col-span-1 py-3 bg-red-600 hover:bg-red-700 text-white text-sm rounded-2xl transition"
                                            data-action="delete" data-id="{{ $c->id }}"
                                            data-title="{{ e($c->title) }}">
                                            {{ __('messages.catalogs.btn_delete') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    @include('components.pagination', ['paginator' => $catalogs])
                </div>
            @endif
        </div>
    </div>

    {{-- MODALS – NOTE: overlay includes "flex" so centering works --}}
    {{-- Create Modal --}}
    <div id="modalCreate" class="fixed inset-0 z-[99999] hidden flex items-center justify-center p-4 bg-black/60"
        aria-hidden="true">
        <div class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-h-[92vh] overflow-y-auto">
            <button type="button" data-modal-close
                class="absolute top-5 right-5 text-4xl leading-none text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">×</button>

            <div class="p-6">
                <h3 class="text-2xl font-semibold mb-6">{{ __('messages.catalogs.create_title') }}</h3>

                <form id="formCreate" method="POST" action="{{ $baseUrl }}">
                    @csrf

                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-2">{{ __('messages.catalogs.field_title') }}</label>
                        <input id="create_title" name="title" required
                            class="w-full border border-gray-300 dark:border-gray-600 rounded-2xl px-5 py-4 focus:ring-2 focus:ring-green-500 dark:bg-gray-700">
                        <div id="error_create_title" class="text-red-600 text-sm mt-2 hidden"></div>
                    </div>

                    @if ($permissions['isSuperadmin'])
                        <div class="mb-6 flex items-center gap-3">
                            <input type="checkbox" id="create_is_global" name="is_global" value="1"
                                class="w-5 h-5 text-green-600 border-gray-300 rounded focus:ring-green-500">
                            <label for="create_is_global" class="text-sm font-medium">Global (hamma uchun
                                ko‘rinadigan)</label>
                        </div>
                    @else
                        <input type="hidden" name="is_global" value="0">
                    @endif

                    <div class="mb-6">
                        <label
                            class="block text-sm font-medium mb-3">{{ __('messages.catalogs.field_peers_help') }}</label>
                        <div id="createPeersContainer"
                            class="border border-gray-300 dark:border-gray-600 rounded-3xl p-5 min-h-[160px] max-h-[280px] overflow-y-auto bg-gray-50 dark:bg-gray-700 mb-4">
                        </div>

                        <div class="flex gap-3">
                            <textarea id="create-peer-input" rows="3"
                                class="flex-1 border border-gray-300 dark:border-gray-600 rounded-3xl px-5 py-4 resize-none focus:ring-2 focus:ring-green-500 dark:bg-gray-700"
                                placeholder="@username yoki Telegram link"></textarea>
                            <button type="button" id="create-add-peer"
                                class="px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-3xl font-medium whitespace-nowrap transition">
                                Qo‘shish
                            </button>
                        </div>
                        <div id="error_create_peers" class="text-red-600 text-sm mt-2 hidden"></div>
                    </div>

                    <div class="flex gap-3 justify-end">
                        <button type="button" data-modal-close
                            class="px-8 py-4 border border-gray-300 dark:border-gray-600 rounded-3xl hover:bg-gray-50 dark:hover:bg-gray-700">Bekor
                            qilish</button>
                        <button id="createSubmit" type="submit"
                            class="px-8 py-4 bg-green-600 hover:bg-green-700 text-white rounded-3xl">Saqlash</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div id="modalEdit" class="fixed inset-0 z-[99999] hidden flex items-center justify-center p-4 bg-black/60"
        aria-hidden="true">
        <div
            class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-h-[92vh] overflow-y-auto">
            <button type="button" data-modal-close
                class="absolute top-5 right-5 text-4xl leading-none text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">×</button>

            <div class="p-6">
                <h3 class="text-2xl font-semibold mb-6">{{ __('messages.catalogs.edit_title') }}</h3>

                <form id="formEdit" method="POST" action="#">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="catalog_id" id="edit_catalog_id">

                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-2">{{ __('messages.catalogs.field_title') }}</label>
                        <input id="edit_title" name="title" required
                            class="w-full border border-gray-300 dark:border-gray-600 rounded-2xl px-5 py-4 focus:ring-2 focus:ring-yellow-500 dark:bg-gray-700">
                        <div id="error_edit_title" class="text-red-600 text-sm mt-2 hidden"></div>
                    </div>

                    @if ($permissions['isSuperadmin'])
                        <div class="mb-6 flex items-center gap-3">
                            <input type="checkbox" id="edit_is_global" name="is_global" value="1"
                                class="w-5 h-5 text-yellow-600 border-gray-300 rounded focus:ring-yellow-500">
                            <label for="edit_is_global" class="text-sm font-medium">Global (hamma uchun
                                ko‘rinadigan)</label>
                        </div>
                    @else
                        <input type="hidden" name="is_global" value="0">
                    @endif

                    <div class="mb-6">
                        <label
                            class="block text-sm font-medium mb-3">{{ __('messages.catalogs.field_peers_help') }}</label>
                        <div id="editPeersContainer"
                            class="border border-gray-300 dark:border-gray-600 rounded-3xl p-5 min-h-[160px] max-h-[280px] overflow-y-auto bg-gray-50 dark:bg-gray-700 mb-4">
                        </div>

                        <div class="flex gap-3">
                            <textarea id="edit-peer-input" rows="3"
                                class="flex-1 border border-gray-300 dark:border-gray-600 rounded-3xl px-5 py-4 resize-none focus:ring-2 focus:ring-yellow-500 dark:bg-gray-700"
                                placeholder="@username yoki Telegram link"></textarea>
                            <button type="button" id="edit-add-peer"
                                class="px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-3xl font-medium whitespace-nowrap transition">
                                Qo‘shish
                            </button>
                        </div>
                        <div id="error_edit_peers" class="text-red-600 text-sm mt-2 hidden"></div>
                    </div>

                    <div class="flex gap-3 justify-end">
                        <button type="button" data-modal-close
                            class="px-8 py-4 border border-gray-300 dark:border-gray-600 rounded-3xl hover:bg-gray-50 dark:hover:bg-gray-700">Bekor
                            qilish</button>
                        <button id="editSubmit" type="submit"
                            class="px-8 py-4 bg-yellow-500 hover:bg-yellow-600 text-white rounded-3xl">Yangilash</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Show Modal --}}
    <div id="modalShow" class="fixed inset-0 z-[99999] hidden flex items-center justify-center p-4 bg-black/60"
        aria-hidden="true">
        <div
            class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-h-[92vh] overflow-y-auto">
            <button type="button" data-modal-close
                class="absolute top-5 right-5 text-4xl leading-none text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">×</button>

            <div class="p-6">
                <h3 id="show_title" class="text-2xl font-semibold mb-1"></h3>
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    {{ __('messages.catalogs.owner') }}: <span id="show_owner"
                        class="font-medium text-gray-900 dark:text-white"></span>
                </div>

                <div class="mb-6">
                    <div class="text-sm font-medium mb-3">{{ __('messages.catalogs.peers_label') }}</div>
                    <div
                        class="border border-gray-200 dark:border-gray-600 rounded-3xl p-5 bg-gray-50 dark:bg-gray-700 max-h-64 overflow-y-auto">
                        <ul id="show_peers" class="list-disc pl-5 space-y-1 text-sm"></ul>
                    </div>
                </div>

                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ __('messages.catalogs.created_at') }}: <span id="show_created_at"></span>
                </div>

                <div class="mt-8 flex justify-end">
                    <button type="button" data-modal-close
                        class="px-8 py-4 border border-gray-300 dark:border-gray-600 rounded-3xl hover:bg-gray-50 dark:hover:bg-gray-700">Yopish</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Delete Modal --}}
    <div id="modalDelete" class="fixed inset-0 z-[99999] hidden flex items-center justify-center p-4 bg-black/60"
        aria-hidden="true">
        <div class="relative w-full max-w-sm bg-white dark:bg-gray-800 rounded-3xl shadow-2xl">
            <button type="button" data-modal-close
                class="absolute top-5 right-5 text-4xl leading-none text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">×</button>

            <div class="p-8 text-center">
                <h3 class="text-2xl font-semibold mb-3">{{ __('messages.catalogs.delete_title') }}</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-8">
                    {{ __('messages.catalogs.delete_confirm') }} <strong id="deleteTitle" class="text-red-600"></strong>?
                </p>

                <form id="formDelete" method="POST" action="#">
                    @csrf
                    @method('DELETE')
                    <div class="flex gap-3 justify-center">
                        <button type="button" data-modal-close
                            class="px-8 py-4 border border-gray-300 dark:border-gray-600 rounded-3xl hover:bg-gray-50 dark:hover:bg-gray-700">Bekor
                            qilish</button>
                        <button id="deleteSubmit" type="submit"
                            class="px-8 py-4 bg-red-600 hover:bg-red-700 text-white rounded-3xl">O‘chirish</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Toast container: ensure highest z so it's above everything --}}
    <div id="toastContainer" class="fixed top-6 right-6 z-[100000] space-y-3 max-w-xs w-full pointer-events-none"></div>

    {{-- Scripts --}}
    <script type="module">
        /*
          To'liq ishlaydigan JS:
          - requestJson(url, body = {}, method = 'post') — fetch asosida (axios ga o'xshash ishlatiladi)
          - request({method, url, data}) — oldingi kod bilan mos kelishi uchun wrapper
          - toast va modal centering/focus muammolari hal qilindi (overlayda "flex" bor)
          - 422 validation errorlarni handle qiladi
        */

        /* --- Request helpers (fetch-based) --- */
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content') || '';

        /**
         * requestJson(url, body = {}, method = 'post')
         * Qaytaradi: parsed JSON yoki throw qilingan error obyekti { status, message, errors? }
         * Misol: const res = await requestJson('/api/items/1', {}, 'delete');
         */
        async function requestJson(url, body = {}, method = 'post') {
            method = (method || 'post').toLowerCase();

            const headers = {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            };

            // Agar fayl yuborish bo'lmasa, JSON header qo'shamiz
            if (!(body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
            }

            const opts = {
                method: method.toUpperCase(),
                headers,
                credentials: 'same-origin'
            };

            if (method === 'get' || method === 'head') {
                // params qo'yish: build query string
                const qs = body && Object.keys(body).length ? '?' + new URLSearchParams(body).toString() : '';
                url = url + qs;
            } else {
                opts.body = (body instanceof FormData) ? body : JSON.stringify(body);
            }

            const res = await fetch(url, opts);

            const contentType = res.headers.get('content-type') || '';
            const isJson = contentType.includes('application/json') || contentType.includes('application/vnd.api+json');

            if (!res.ok) {
                let parsed = null;
                if (isJson) {
                    try {
                        parsed = await res.json();
                    } catch (e) {
                        parsed = null;
                    }
                }
                const err = {
                    status: res.status,
                    message: parsed?.message || parsed?.error || res.statusText || 'Unknown error',
                    errors: parsed?.errors || parsed?.error_details || null
                };
                throw err;
            }

            if (isJson) {
                return await res.json();
            }

            // fallback for non-json success (rare)
            return await res.text();
        }

        /**
         * request({ method, url, data })
         * Oldingi kodni moslashtirish uchun wrapper. Agar server { success, message } formatida qaytsa to'g'ri ishlaydi.
         */
        async function request({
            method = 'get',
            url = '',
            data = {}
        } = {}) {
            try {
                const resp = await requestJson(url, data, method);
                // Agar API oddiy obyektdan tashqari strukturaga ega bo'lsa, moslashish uchun return qilingan qiymat:
                return resp;
            } catch (err) {
                // throw qilingan obyektda status va errors bo'lishi kutiladi
                throw err;
            }
        }

        /* --- Toast --- */
        const toastContainer = document.getElementById('toastContainer');

        function showToast(text = '', type = 'success', timeout = 3500) {
            const id = `toast_${Date.now()}`;
            const el = document.createElement('div');
            el.id = id;
            el.className =
                `pointer-events-auto px-6 py-4 rounded-3xl shadow-2xl text-white text-sm flex items-center gap-3`;
            el.classList.add(type === 'success' ? 'bg-green-600' : 'bg-red-600');
            el.innerHTML = `<span>${text}</span>`;
            toastContainer.appendChild(el);

            // entrance
            el.style.opacity = '0';
            el.style.transform = 'translateY(-6px)';
            requestAnimationFrame(() => {
                el.style.transition = 'all 0.25s ease';
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            });

            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-10px)';
                setTimeout(() => el.remove(), 300);
            }, timeout);
        }

        /* --- Modal helpers --- */
        function openModalById(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
            // lock body scroll
            document.documentElement.classList.add('overflow-hidden');
            // focus first focusable element inside modal if any
            setTimeout(() => {
                const focusable = modal.querySelector(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (focusable) focusable.focus();
            }, 100);
        }

        function closeModalById(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            // unlock body scroll if no other modal open
            setTimeout(() => {
                const anyOpen = Array.from(document.querySelectorAll('[id^="modal"]')).some(m => !m.classList
                    .contains('hidden'));
                if (!anyOpen) document.documentElement.classList.remove('overflow-hidden');
            }, 50);
        }

        /* close when clicking close or overlay (but not inner content) */
        document.addEventListener('click', ev => {
            // open
            const opener = ev.target.closest('[data-modal-open]');
            if (opener) {
                ev.preventDefault();
                const target = opener.getAttribute('data-modal-target');
                if (target) openModalById(target);
                return;
            }

            // close buttons
            if (ev.target.closest('[data-modal-close]')) {
                ev.preventDefault();
                const modal = ev.target.closest('[id^="modal"]');
                if (modal) closeModalById(modal.id);
                return;
            }

            // overlay click: if click on overlay (has id starting with modal and is not the inner dialog)
            const overlay = ev.target.closest('[id^="modal"]');
            if (overlay && overlay === ev.target) {
                // clicked directly on overlay
                closeModalById(overlay.id);
            }
        });

        /* Escape to close */
        window.addEventListener('keydown', ev => {
            if (ev.key === 'Escape') {
                document.querySelectorAll('[id^="modal"]').forEach(m => {
                    if (!m.classList.contains('hidden')) closeModalById(m.id);
                });
            }
        });

        /* --- Peers helpers --- */
        function normalizePeer(raw) {
            let v = String(raw || '').trim();
            if (!v) return null;
            if (!v.startsWith('http') && !v.startsWith('@')) v = '@' + v;
            return v;
        }

        function splitBulk(value) {
            return value.split(/(?:\r?\n|->|,|;|\s)+/).filter(Boolean);
        }

        function createPeerElement(value) {
            const div = document.createElement('div');
            div.className =
                'peer-item flex items-center justify-between bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-2xl px-5 py-3.5 mb-3';

            const span = document.createElement('span');
            span.className = 'text-sm text-gray-800 dark:text-gray-200 flex-1';
            span.textContent = value;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className =
                'text-red-600 hover:text-red-700 text-2xl leading-none w-8 h-8 flex items-center justify-center';
            removeBtn.innerHTML = '&times;';
            removeBtn.onclick = () => div.remove();

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'peers[]';
            hidden.value = value;

            div.appendChild(span);
            div.appendChild(removeBtn);
            div.appendChild(hidden);
            return div;
        }

        function getPeersFromContainer(container) {
            return Array.from(container.querySelectorAll('input[name="peers[]"]')).map(i => i.value);
        }

        function existingPeersSet(container) {
            const set = new Set();
            container.querySelectorAll('input[name="peers[]"]').forEach(i => set.add(i.value.trim()));
            return set;
        }

        function addPeersFromString(container, value) {
            const parts = splitBulk(value);
            const existing = existingPeersSet(container);
            let added = 0;
            for (let p of parts) {
                const normalized = normalizePeer(p);
                if (!normalized || existing.has(normalized)) continue;
                container.appendChild(createPeerElement(normalized));
                existing.add(normalized);
                added++;
            }
            return added;
        }

        /* --- Wire up peers UI --- */
        const createPeersContainer = document.getElementById('createPeersContainer');
        const createPeerInput = document.getElementById('create-peer-input');
        const createAddBtn = document.getElementById('create-add-peer');

        if (createPeersContainer && createPeerInput && createAddBtn) {
            createAddBtn.addEventListener('click', () => {
                const val = createPeerInput.value.trim();
                if (!val) return;
                const count = addPeersFromString(createPeersContainer, val);
                if (count) {
                    createAddBtn.textContent = `Qo‘shildi ${count}`;
                    setTimeout(() => createAddBtn.textContent = 'Qo‘shish', 1300);
                }
                createPeerInput.value = '';
                createPeerInput.focus();
            });
            createPeerInput.addEventListener('keydown', e => {
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    createAddBtn.click();
                }
            });
        }

        const editPeersContainer = document.getElementById('editPeersContainer');
        const editPeerInput = document.getElementById('edit-peer-input');
        const editAddBtn = document.getElementById('edit-add-peer');

        if (editPeersContainer && editPeerInput && editAddBtn) {
            editAddBtn.addEventListener('click', () => {
                const val = editPeerInput.value.trim();
                if (!val) return;
                const count = addPeersFromString(editPeersContainer, val);
                if (count) {
                    editAddBtn.textContent = `Qo‘shildi ${count}`;
                    setTimeout(() => editAddBtn.textContent = 'Qo‘shish', 1300);
                }
                editPeerInput.value = '';
                editPeerInput.focus();
            });
            editPeerInput.addEventListener('keydown', e => {
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    editAddBtn.click();
                }
            });
        }

        /* --- Actions (show, edit, delete) --- */
        const baseUrl = @json($baseUrl);

        document.addEventListener('click', async (ev) => {
            const btn = ev.target.closest('[data-action]');
            if (!btn) return;

            const action = btn.getAttribute('data-action');
            const id = btn.getAttribute('data-id');
            if (!action || !id) return;

            if (action === 'show') {
                try {
                    const data = await request({
                        method: 'get',
                        url: `${baseUrl}/${id}`
                    });
                    document.getElementById('show_title').textContent = data.title ?? '';
                    document.getElementById('show_owner').textContent = data.user_name ?? data.user?.name ??
                        '-';
                    document.getElementById('show_created_at').textContent = data.created_at ?? '';

                    const ul = document.getElementById('show_peers');
                    ul.innerHTML = '';
                    (data.peers || []).forEach(p => {
                        const li = document.createElement('li');
                        li.textContent = p;
                        ul.appendChild(li);
                    });

                    openModalById('modalShow');
                } catch (err) {
                    showToast(err.message || '{{ __('messages.error_occurred') }}', 'error');
                }
            }

            if (action === 'edit') {
                try {
                    const data = await request({
                        method: 'get',
                        url: `${baseUrl}/${id}`
                    });

                    document.getElementById('edit_catalog_id').value = data.id ?? '';
                    document.getElementById('edit_title').value = data.title ?? '';

                    const editGlobalCheck = document.getElementById('edit_is_global');
                    if (editGlobalCheck) editGlobalCheck.checked = !!data.is_global;

                    editPeersContainer.innerHTML = '';
                    (data.peers || []).forEach(p => editPeersContainer.appendChild(createPeerElement(p)));

                    const formEdit = document.getElementById('formEdit');
                    formEdit.action = `${baseUrl}/${data.id}`;

                    openModalById('modalEdit');
                } catch (err) {
                    showToast(err.message || '{{ __('messages.error_occurred') }}', 'error');
                }
            }

            if (action === 'delete') {
                const title = btn.getAttribute('data-title') || '{{ __('messages.catalogs.this_catalog') }}';
                document.getElementById('deleteTitle').textContent = title;
                const formDelete = document.getElementById('formDelete');
                formDelete.action = `${baseUrl}/${id}`;
                openModalById('modalDelete');
            }
        });

        /* --- CREATE submit --- */
        const formCreate = document.getElementById('formCreate');
        if (formCreate) {
            formCreate.addEventListener('submit', async (ev) => {
                ev.preventDefault();

                const title = document.getElementById('create_title').value.trim();
                const peers = getPeersFromContainer(createPeersContainer || document.createElement('div'));
                const isGlobal = document.getElementById('create_is_global')?.checked ? 1 : 0;

                const errTitle = document.getElementById('error_create_title');
                const errPeers = document.getElementById('error_create_peers');
                errTitle?.classList.add('hidden');
                errPeers?.classList.add('hidden');

                try {
                    const data = await request({
                        method: 'post',
                        url: baseUrl,
                        data: {
                            title,
                            peers,
                            is_global: isGlobal
                        }
                    });

                    showToast(data.message || '{{ __('messages.catalogs.created_success') }}', 'success');
                    closeModalById('modalCreate');
                    setTimeout(() => location.reload(), 700);
                } catch (err) {
                    if (err.status === 422 && err.errors) {
                        if (err.errors.title && errTitle) {
                            errTitle.textContent = Array.isArray(err.errors.title) ? err.errors.title.join(
                                ' ') : err.errors.title;
                            errTitle.classList.remove('hidden');
                        }
                        if ((err.errors.peers || err.errors['peers.*']) && errPeers) {
                            const msg = (err.errors.peers || err.errors['peers.*']);
                            errPeers.textContent = Array.isArray(msg) ? msg.join(' ') : msg;
                            errPeers.classList.remove('hidden');
                        }
                    } else {
                        showToast(err.message || '{{ __('messages.error_occurred') }}', 'error');
                    }
                }
            });
        }

        /* --- EDIT submit --- */
        const formEdit = document.getElementById('formEdit');
        if (formEdit) {
            formEdit.addEventListener('submit', async (ev) => {
                ev.preventDefault();

                const id = document.getElementById('edit_catalog_id').value;
                if (!id) return showToast('ID topilmadi', 'error');

                const title = document.getElementById('edit_title').value.trim();
                const peers = getPeersFromContainer(editPeersContainer || document.createElement('div'));
                const isGlobal = document.getElementById('edit_is_global')?.checked ? 1 : 0;

                const errTitle = document.getElementById('error_edit_title');
                const errPeers = document.getElementById('error_edit_peers');
                errTitle?.classList.add('hidden');
                errPeers?.classList.add('hidden');

                try {
                    const data = await request({
                        method: 'put',
                        url: `${baseUrl}/${id}`,
                        data: {
                            title,
                            peers,
                            is_global: isGlobal
                        }
                    });

                    showToast(data.message || '{{ __('messages.catalogs.updated_success') }}', 'success');
                    closeModalById('modalEdit');
                    setTimeout(() => location.reload(), 700);
                } catch (err) {
                    if (err.status === 422 && err.errors) {
                        if (err.errors.title && errTitle) {
                            errTitle.textContent = Array.isArray(err.errors.title) ? err.errors.title.join(
                                ' ') : err.errors.title;
                            errTitle.classList.remove('hidden');
                        }
                        if ((err.errors.peers || err.errors['peers.*']) && errPeers) {
                            const msg = (err.errors.peers || err.errors['peers.*']);
                            errPeers.textContent = Array.isArray(msg) ? msg.join(' ') : msg;
                            errPeers.classList.remove('hidden');
                        }
                    } else {
                        showToast(err.message || '{{ __('messages.error_occurred') }}', 'error');
                    }
                }
            });
        }

        /* --- DELETE submit --- */
        const formDelete = document.getElementById('formDelete');
        if (formDelete) {
            formDelete.addEventListener('submit', async (ev) => {
                ev.preventDefault();
                const url = formDelete.action;

                try {
                    const data = await requestJson(url, {}, 'delete');
                    // Agar server JSON { success: true, message: '...' } qaytarsa:
                    if (data && (data.success === false)) {
                        throw {
                            status: 400,
                            message: data.message || '{{ __('messages.error_occurred') }}'
                        };
                    }
                    showToast(data.message || '{{ __('messages.catalogs.deleted_success') }}', 'success');
                    closeModalById('modalDelete');
                    setTimeout(() => location.reload(), 700);
                } catch (err) {
                    showToast(err.message || '{{ __('messages.error_occurred') }}', 'error');
                }
            });
        }
    </script>
@endsection
