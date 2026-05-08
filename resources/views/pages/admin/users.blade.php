@extends('layouts.app')

@section('title', __('superadmin.users.title'))

@section('content')
    @php
        $me = $me ?? auth()->user();
        $canManageUsers = $me?->hasPermission('nav:users') ?? false;
        $filters = $filters ?? [
            'search' => request('search', ''),
            'permission' => request('permission', ''),
            'sort_by' => request('sort_by', 'created_at'),
            'sort_dir' => request('sort_dir', 'desc'),
            'per_page' => request('per_page', 10),
        ];
    @endphp

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div id="users-page" x-data="superadminUsersPage({
        baseUrl: @js(url()->current()),
        me: @js($me ?? null),
        permissionsAll: @js($permissionsList ?? []),
    })" x-init="init()"
        class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8 2xl:px-10">
        @if (session('success'))
            <div x-show="toast.open" x-transition x-cloak
                class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div x-show="toast.open" x-transition x-cloak
                class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white md:text-3xl">
                    {{ __('superadmin.users.title') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('superadmin.users.subtitle') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('superadmin.index') }}"
                    class="flex items-center gap-x-2 rounded-2xl border border-gray-300 bg-white px-5 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 active:scale-95 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    {{ __('superadmin.common.reset') }}
                </a>

                <button type="button" @click="showAdvanced = !showAdvanced"
                    class="flex items-center gap-x-2 rounded-2xl border border-gray-300 bg-white px-5 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50 active:scale-95 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    <span x-text="showAdvanced ? @js(__('superadmin.common.hide')) : @js(__('superadmin.common.more'))"></span>
                </button>

                @if ($canManageUsers)
                    <button type="button" @click="openCreate()"
                        class="flex items-center gap-x-2 rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 active:scale-95 shadow-lg shadow-emerald-500/30">
                        {{ __('superadmin.common.create') }}
                    </button>
                @endif
            </div>
        </div>

        <form method="GET" action="{{ route('superadmin.index') }}"
            class="mb-8 rounded-3xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] md:p-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ __('superadmin.users.search') }}
                    </label>
                    <input name="search" value="{{ $filters['search'] }}" type="text"
                        class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        placeholder="{{ __('superadmin.users.search_placeholder') }}">
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ __('superadmin.users.per_page') }}
                    </label>
                    <select name="per_page"
                        class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="10" @selected((int) $filters['per_page'] === 10)>10</option>
                        <option value="25" @selected((int) $filters['per_page'] === 25)>25</option>
                        <option value="50" @selected((int) $filters['per_page'] === 50)>50</option>
                        <option value="100" @selected((int) $filters['per_page'] === 100)>100</option>
                    </select>
                </div>

                <div x-show="showAdvanced" x-transition class="md:col-span-2 xl:col-span-2">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ __('superadmin.users.permission') }}
                            </label>
                            <select name="permission"
                                class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="">{{ __('superadmin.common.all') }}</option>
                                @foreach ($permissionsList as $perm)
                                    <option value="{{ $perm['key'] }}" @selected($filters['permission'] === $perm['key'])>
                                        {{ $perm['text'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ __('superadmin.users.sort_by') }}
                            </label>
                            <select name="sort_by"
                                class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="created_at" @selected($filters['sort_by'] === 'created_at')>
                                    {{ __('superadmin.users.created_at') }}</option>
                                <option value="updated_at" @selected($filters['sort_by'] === 'updated_at')>
                                    {{ __('superadmin.users.updated_at') }}</option>
                                <option value="name" @selected($filters['sort_by'] === 'name')>{{ __('superadmin.users.name') }}
                                </option>
                                <option value="email" @selected($filters['sort_by'] === 'email')>{{ __('superadmin.users.email') }}
                                </option>
                                <option value="id" @selected($filters['sort_by'] === 'id')>{{ __('superadmin.users.id') }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ __('superadmin.users.sort_dir') }}
                            </label>
                            <select name="sort_dir"
                                class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition focus:border-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="desc" @selected($filters['sort_dir'] === 'desc')>DESC</option>
                                <option value="asc" @selected($filters['sort_dir'] === 'asc')>ASC</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <button type="submit"
                    class="rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-indigo-700">
                    {{ __('superadmin.common.search') }}
                </button>

                <a href="{{ route('superadmin.index') }}"
                    class="rounded-2xl border border-gray-200 bg-white px-5 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    {{ __('superadmin.common.clear') }}
                </a>
            </div>
        </form>

        <x-users.table :users="$users" :me="$me" :can-manage-users="$canManageUsers" />

        <div class="mt-6">
            {{ $users->links() }}
        </div>

        @include('pages.admin.users.partials.create-modal')
        @include('pages.admin.users.partials.view-modal')
        @include('pages.admin.users.partials.delete-modal')

        <script>
            function superadminUsersPage(config = {}) {
                return {
                    baseUrl: config.baseUrl || window.location.pathname,
                    me: config.me || null,
                    permissionsAll: Array.isArray(config.permissionsAll) ? config.permissionsAll : [],

                    showAdvanced: false,

                    createModalOpen: false,
                    viewModalOpen: false,
                    deleteModalOpen: false,
                    modalMode: 'view',
                    loadingUser: false,

                    currentUser: null,
                    targetLocked: false,
                    activeTargetId: null,

                    createForm: {
                        name: '',
                        email: '',
                        password: '',
                        permissions: [],
                    },

                    editForm: {
                        id: null,
                        name: '',
                        email: '',
                        password: '',
                        permissions: [],
                    },

                    deleteTarget: null,

                    createErrors: {},
                    editErrors: {},
                    deleteErrors: {},

                    toast: {
                        open: true,
                    },

                    init() {
                        setTimeout(() => {
                            this.toast.open = false;
                        }, 3500);

                        this.syncBodyScroll();
                    },

                    syncBodyScroll() {
                        const open = this.createModalOpen || this.viewModalOpen || this.deleteModalOpen;
                        document.body.classList.toggle('overflow-hidden', open);
                    },

                    getPermissionLabel(key) {
                        const found = this.permissionsAll.find(p => p.key === key);
                        return found ? found.text : key;
                    },
                    notify(message, type = 'success') {
                        if (window.showToast) {
                            window.showToast(message, type);
                            return;
                        }

                        console[type === 'error' ? 'error' : 'log'](message);
                    },

                    async parseJson(res) {
                        try {
                            return await res.json();
                        } catch (e) {
                            return {};
                        }
                    },

                    userPermissionKeys(user) {
                        if (!user || !Array.isArray(user.permissions)) return [];

                        return user.permissions
                            .map(p => {
                                if (typeof p === 'string') return p;
                                return p?.key ?? p?.name ?? '';
                            })
                            .filter(Boolean);
                    },

                    userHasPermission(user, key) {
                        return this.userPermissionKeys(user).includes(key);
                    },

                    canEditCurrentTarget() {
                        if (!this.currentUser) return false;
                        if (this.me && this.me.id === this.currentUser.id) return false;
                        return !this.targetLocked;
                    },

                    canDeleteCurrentTarget() {
                        if (!this.currentUser) return false;
                        if (this.me && this.me.id === this.currentUser.id) return false;
                        return !this.targetLocked;
                    },

                    togglePermission(formName, key, checked) {
                        const form = this[formName];
                        if (!form) return;

                        if (!Array.isArray(form.permissions)) {
                            form.permissions = [];
                        }

                        if (checked) {
                            if (!form.permissions.includes(key)) {
                                form.permissions.push(key);
                            }
                        } else {
                            form.permissions = form.permissions.filter(item => item !== key);
                        }
                    },

                    resetCreateForm() {
                        this.createForm = {
                            name: '',
                            email: '',
                            password: '',
                            permissions: [],
                        };
                        this.createErrors = {};
                    },

                    resetEditForm() {
                        this.editForm = {
                            id: null,
                            name: '',
                            email: '',
                            password: '',
                            permissions: [],
                        };
                        this.editErrors = {};
                    },

                    async fetchUser(id) {
                        const res = await fetch(`${this.baseUrl}/${id}`, {
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        const json = await this.parseJson(res);

                        if (!res.ok) {
                            const message = json?.message || @js(__('superadmin.users.errors.load'));
                            throw new Error(message);
                        }

                        return json.data ?? json;
                    },

                    openCreate() {
                        this.resetCreateForm();
                        this.createModalOpen = true;
                        this.syncBodyScroll();
                    },

                    closeCreate() {
                        this.createModalOpen = false;
                        this.resetCreateForm();
                        this.syncBodyScroll();
                    },

                    async openView(id) {
                        this.modalMode = 'view';
                        this.viewModalOpen = true;
                        this.loadingUser = true;
                        this.currentUser = null;
                        this.targetLocked = false;
                        this.activeTargetId = id;
                        this.resetEditForm();
                        this.syncBodyScroll();

                        try {
                            const data = await this.fetchUser(id);

                            this.currentUser = {
                                id: data.id ?? null,
                                name: data.name ?? '',
                                email: data.email ?? '',
                                permissions: Array.isArray(data.permissions) ? [...data.permissions] : [],
                            };

                            this.targetLocked = this.userHasPermission(this.currentUser, 'nav:users');

                            this.editForm = {
                                id: data.id ?? null,
                                name: data.name ?? '',
                                email: data.email ?? '',
                                password: '',
                                permissions: Array.isArray(data.permissions) ? [...data.permissions] : [],
                            };
                        } catch (e) {
                            console.error(e);
                            this.notify(e.message || @js(__('superadmin.users.errors.load')), 'error');
                            this.closeView();
                        } finally {
                            this.loadingUser = false;
                        }
                    },

                    async openEdit(id) {
                        this.modalMode = 'edit';
                        this.viewModalOpen = true;
                        this.loadingUser = true;
                        this.currentUser = null;
                        this.targetLocked = false;
                        this.activeTargetId = id;
                        this.resetEditForm();
                        this.syncBodyScroll();

                        try {
                            const data = await this.fetchUser(id);

                            this.currentUser = {
                                id: data.id ?? null,
                                name: data.name ?? '',
                                email: data.email ?? '',
                                permissions: Array.isArray(data.permissions) ? [...data.permissions] : [],
                            };

                            this.targetLocked = this.userHasPermission(this.currentUser, 'nav:users');

                            if (!this.canEditCurrentTarget()) {
                                this.notify(@js(__('superadmin.users.errors.not_editable')), 'error');
                                this.closeView();
                                return;
                            }

                            this.editForm = {
                                id: data.id ?? null,
                                name: data.name ?? '',
                                email: data.email ?? '',
                                password: '',
                                permissions: Array.isArray(data.permissions) ? [...data.permissions] : [],
                            };
                        } catch (e) {
                            console.error(e);
                            this.notify(e.message || @js(__('superadmin.users.errors.load')), 'error');
                            this.closeView();
                        } finally {
                            this.loadingUser = false;
                        }
                    },

                    closeView() {
                        this.viewModalOpen = false;
                        this.modalMode = 'view';
                        this.currentUser = null;
                        this.targetLocked = false;
                        this.activeTargetId = null;
                        this.resetEditForm();
                        this.syncBodyScroll();
                    },

                    async openDelete(userId) {
                        this.deleteModalOpen = true;
                        this.loadingUser = true;
                        this.deleteErrors = {};
                        this.syncBodyScroll();

                        try {
                            const data = await this.fetchUser(userId);

                            this.currentUser = {
                                id: data.id ?? null,
                                name: data.name ?? '',
                                email: data.email ?? '',
                                permissions: Array.isArray(data.permissions) ? [...data.permissions] : [],
                            };

                            this.targetLocked = this.userHasPermission(this.currentUser, 'nav:users');

                            if (!this.canDeleteCurrentTarget()) {
                                this.notify(@js(__('superadmin.users.errors.not_deletable')), 'error');
                                this.closeDelete();
                                return;
                            }

                            this.deleteTarget = {
                                id: data.id ?? null,
                                name: data.name ?? '',
                                email: data.email ?? '',
                                permissions: Array.isArray(data.permissions) ? [...data.permissions] : [],
                            };
                        } catch (e) {
                            console.error(e);
                            this.notify(e.message || @js(__('superadmin.users.errors.load')), 'error');
                            this.closeDelete();
                        } finally {
                            this.loadingUser = false;
                        }
                    },

                    closeDelete() {
                        this.deleteModalOpen = false;
                        this.deleteTarget = null;
                        this.deleteErrors = {};
                        this.currentUser = null;
                        this.targetLocked = false;
                        this.activeTargetId = null;
                        this.syncBodyScroll();
                    },

                    async submitCreate() {
                        this.createErrors = {};

                        try {
                            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                            const res = await fetch(this.baseUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': token,
                                },
                                body: JSON.stringify({
                                    name: this.createForm.name,
                                    email: this.createForm.email,
                                    password: this.createForm.password,
                                    permissions: Array.isArray(this.createForm.permissions) ? this
                                        .createForm.permissions : [],
                                })
                            });

                            const json = await this.parseJson(res);

                            if (res.status === 422) {
                                this.createErrors = json.errors || {};
                                const firstError = Object.values(json.errors || {})?.flat?.()?.[0];
                                this.notify(firstError || json.message || @js(__('superadmin.users.errors.validation')), 'error');
                                return;
                            }

                            if (!res.ok || json.success === false) {
                                this.notify(json.message || @js(__('superadmin.users.errors.create_failed')), 'error');
                                return;
                            }

                            this.closeCreate();
                            this.notify(json.message || @js(__('superadmin.users.messages.created')), 'success');
                            window.location.reload();
                        } catch (e) {
                            console.error(e);
                            this.notify(e.message || @js(__('superadmin.users.errors.create_failed')), 'error');
                        }
                    },

                    async submitUpdate() {
                        if (!this.editForm.id) return;

                        this.editErrors = {};

                        if (!this.canEditCurrentTarget()) {
                            this.notify(@js(__('superadmin.users.errors.not_editable')), 'error');
                            return;
                        }

                        try {
                            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                            const payload = {
                                name: this.editForm.name,
                                email: this.editForm.email,
                                permissions: Array.isArray(this.editForm.permissions) ? this.editForm.permissions : [],
                            };

                            if (this.editForm.password) {
                                payload.password = this.editForm.password;
                            }

                            const res = await fetch(`${this.baseUrl}/${this.editForm.id}`, {
                                method: 'PUT',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': token,
                                },
                                body: JSON.stringify(payload)
                            });

                            const json = await this.parseJson(res);

                            if (res.status === 422) {
                                this.editErrors = json.errors || {};
                                const firstError = Object.values(json.errors || {})?.flat?.()?.[0];
                                this.notify(firstError || json.message || @js(__('superadmin.users.errors.validation')), 'error');
                                return;
                            }

                            if (!res.ok || json.success === false) {
                                this.notify(json.message || @js(__('superadmin.users.errors.update_failed')), 'error');
                                return;
                            }

                            this.closeView();
                            this.notify(json.message || @js(__('superadmin.users.messages.updated')), 'success');
                            window.location.reload();
                        } catch (e) {
                            console.error(e);
                            this.notify(e.message || @js(__('superadmin.users.errors.update_failed')), 'error');
                        }
                    },

                    async confirmDelete() {
                        if (!this.deleteTarget?.id) return;

                        try {
                            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                            const res = await fetch(`${this.baseUrl}/${this.deleteTarget.id}`, {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token,
                                },
                            });

                            const json = await this.parseJson(res);

                            if (!res.ok || json.success === false) {
                                this.notify(json.message || @js(__('superadmin.users.errors.delete_failed')), 'error');
                                return;
                            }

                            this.closeDelete();
                            this.notify(json.message || @js(__('superadmin.users.messages.deleted')), 'success');
                            window.location.reload();
                        } catch (e) {
                            console.error(e);
                            this.notify(e.message || @js(__('superadmin.users.errors.delete_failed')), 'error');
                        }
                    },
                };
            }
        </script>
    </div>
@endsection
