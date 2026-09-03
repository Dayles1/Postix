@extends('layouts.app')

@section('title', 'Operation User')

@section('content')
    <div
        x-data="operationUserPage({
            operationUserId: @js($operationUser->id),
            operationUserUrl: @js(route('api.telegram.operation-users.show', $operationUser->id)),
            driversUrl: @js(route('api.telegram.operation-users.drivers', $operationUser->id)),
        })"
        x-init="init()"
        class="min-h-[calc(100vh-5rem)] px-4 pb-28 pt-5 sm:px-6 sm:pb-32 sm:pt-6 lg:px-8 lg:pt-8"
    >
        <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-5">

            {{-- Back --}}
            <div>
                <a
                    href="{{ route('driver-check.operation-users') }}"
                    class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>

                    Operation Users
                </a>
            </div>

            {{-- ==========================================================
                 OPERATOR HEADER
            =========================================================== --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-xs sm:p-6 dark:border-gray-800 dark:bg-gray-900">

                {{-- Loading --}}
                <template x-if="operatorLoading">
                    <div class="animate-pulse">
                        <div class="flex items-start gap-4">
                            <div class="h-14 w-14 rounded-2xl bg-gray-100 dark:bg-white/[0.06]"></div>

                            <div class="flex-1">
                                <div class="h-5 w-64 rounded bg-gray-100 dark:bg-white/[0.06]"></div>
                                <div class="mt-2 h-4 w-40 rounded bg-gray-100 dark:bg-white/[0.06]"></div>
                            </div>
                        </div>

                        <div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-5">
                            <template x-for="i in 5" :key="i">
                                <div class="h-20 rounded-xl bg-gray-100 dark:bg-white/[0.06]"></div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Operator --}}
                <template x-if="!operatorLoading && operator">
                    <div>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex min-w-0 items-center gap-4">
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-sm font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">
                                    <span x-text="initials(operator.name)"></span>
                                </div>

                                <div class="min-w-0">
                                    <h1 class="break-words text-lg font-semibold text-gray-900 sm:text-xl dark:text-white"
                                        x-text="operator.name || '—'">
                                    </h1>

                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                        <span>
                                            ID:
                                            <span class="font-medium text-gray-700 dark:text-gray-300"
                                                  x-text="operator.id"></span>
                                        </span>

                                        <span class="hidden sm:inline">•</span>

                                        <span
                                            x-text="operator.telegram_username ? '@' + String(operator.telegram_username).replace(/^@/, '') : 'No Telegram username'"
                                        ></span>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="refreshAll()"
                                :disabled="operatorLoading || driversLoading"
                                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    :class="{ 'animate-spin': operatorLoading || driversLoading }"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9M4.582 9H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2M19.419 15H15"/>
                                </svg>

                                Refresh
                            </button>
                        </div>

                        {{-- Stats --}}
                        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-5">

                            {{-- Drivers --}}
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    Drivers
                                </p>

                                <p
                                    class="mt-2 text-xl font-semibold text-gray-900 dark:text-white"
                                    x-text="number(operator.drivers_count ?? operator.stats?.drivers ?? 0)"
                                ></p>
                            </div>

                            {{-- Checks --}}
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    Checks
                                </p>

                                <p
                                    class="mt-2 text-xl font-semibold text-gray-900 dark:text-white"
                                    x-text="number(operator.checks_count ?? operator.stats?.checks ?? 0)"
                                ></p>
                            </div>

                            {{-- Confirmed --}}
                            <div class="rounded-xl border border-green-100 bg-green-50 p-4 dark:border-green-900/30 dark:bg-green-500/10">
                                <p class="text-xs font-medium text-green-700 dark:text-green-400">
                                    Confirmed
                                </p>

                                <p
                                    class="mt-2 text-xl font-semibold text-green-700 dark:text-green-400"
                                    x-text="number(operator.stats?.confirmed ?? 0)"
                                ></p>
                            </div>

                            {{-- Not confirmed --}}
                            <div class="rounded-xl border border-red-100 bg-red-50 p-4 dark:border-red-900/30 dark:bg-red-500/10">
                                <p class="text-xs font-medium text-red-700 dark:text-red-400">
                                    Not confirmed
                                </p>

                                <p
                                    class="mt-2 text-xl font-semibold text-red-700 dark:text-red-400"
                                    x-text="number(operator.stats?.not_confirmed ?? 0)"
                                ></p>
                            </div>

                            {{-- Match --}}
                            <div class="col-span-2 rounded-xl border border-gray-100 bg-gray-50 p-4 sm:col-span-1 dark:border-gray-800 dark:bg-white/[0.03]">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                        Match rate
                                    </p>

                                    <span
                                        class="text-sm font-semibold text-gray-900 dark:text-white"
                                        x-text="percent(operator.stats?.match_rate) + '%'"
                                    ></span>
                                </div>

                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/[0.08]">
                                    <div
                                        class="h-full rounded-full bg-blue-600 transition-all"
                                        :style="{ width: Math.min(100, Math.max(0, Number(operator.stats?.match_rate || 0))) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Operator error --}}
                <template x-if="operatorError">
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/40 dark:bg-red-950/30">
                        <p class="text-sm font-medium text-red-800 dark:text-red-300">
                            Failed to load operator
                        </p>

                        <p class="mt-1 text-sm text-red-700 dark:text-red-400"
                           x-text="operatorError">
                        </p>
                    </div>
                </template>
            </div>

            {{-- ==========================================================
                 DRIVERS
            =========================================================== --}}
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-xs dark:border-gray-800 dark:bg-gray-900">

                {{-- Header --}}
                <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-4 sm:px-5 dark:border-gray-800">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900 sm:text-base dark:text-white">
                                Drivers
                            </h2>

                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="driversMeta.from || 0"></span>
                                –
                                <span x-text="driversMeta.to || 0"></span>
                                of
                                <span x-text="number(driversMeta.total)"></span>
                            </p>
                        </div>

                        <div class="flex items-center justify-between gap-3 sm:justify-end">
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Per page
                            </span>

                            <select
                                x-model.number="perPage"
                                @change="changePerPage()"
                                class="h-9 rounded-lg border border-gray-200 bg-gray-50 px-2.5 text-sm text-gray-700 outline-none focus:border-blue-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"
                            >
                                <option :value="10">10</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                                <option :value="100">100</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Loading --}}
                <template x-if="driversLoading && drivers.length === 0">
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template x-for="i in 5" :key="i">
                            <div class="p-4 sm:p-5">
                                <div class="animate-pulse">
                                    <div class="h-5 w-64 rounded bg-gray-100 dark:bg-white/[0.05]"></div>
                                    <div class="mt-3 h-16 rounded-xl bg-gray-100 dark:bg-white/[0.05]"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Driver list --}}
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    <template x-for="driver in drivers" :key="driver.id">
                        <div
                            class="p-4 transition hover:bg-gray-50/60 sm:p-5 dark:hover:bg-white/[0.02]"
                            x-data="{ phonesOpen: false }"
                        >
                            <div class="flex items-start gap-3">

                                {{-- Driver avatar --}}
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700 dark:bg-white/[0.06] dark:text-gray-300">
                                    <span x-text="initials(driver.name)"></span>
                                </div>

                                <div class="min-w-0 flex-1">

                                    {{-- Driver header --}}
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <h3
                                                class="break-words text-sm font-semibold text-gray-900 dark:text-white"
                                                x-text="driver.name || '—'"
                                            ></h3>

                                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                <span>
                                                    ID:
                                                    <span
                                                        class="font-medium text-gray-700 dark:text-gray-300"
                                                        x-text="driver.id"
                                                    ></span>
                                                </span>

                                                <span>•</span>

                                                <span x-text="driver.status || 'unknown'"></span>
                                            </div>
                                        </div>

                                        <div class="flex shrink-0 items-center gap-2">
                                            <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">
                                                <span x-text="driver.resolved_phones_count ?? (driver.resolved_phones || []).length"></span>
                                                phones
                                            </span>

                                            <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-white/[0.06] dark:text-gray-300">
                                                <span x-text="driver.checks_count ?? 0"></span>
                                                checks
                                            </span>
                                        </div>
                                    </div>

                                    {{-- Phones --}}
                                    <div class="mt-4">
                                        <button
                                            type="button"
                                            @click="phonesOpen = !phonesOpen"
                                            class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 transition hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                        >
                                            <span x-text="phonesOpen ? 'Hide phones' : 'Show phones'"></span>

                                            <svg
                                                class="h-4 w-4 transition-transform"
                                                :class="{ 'rotate-180': phonesOpen }"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                                            </svg>
                                        </button>

                                        <div
                                            x-show="phonesOpen"
                                            x-collapse
                                            class="mt-3"
                                        >
                                            <template x-if="!driver.resolved_phones || driver.resolved_phones.length === 0">
                                                <div class="rounded-xl border border-dashed border-gray-200 px-4 py-5 text-center dark:border-gray-800">
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                                        No resolved phones
                                                    </p>
                                                </div>
                                            </template>

                                            <div
                                                x-show="driver.resolved_phones && driver.resolved_phones.length > 0"
                                                class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3"
                                            >
                                                <template
                                                    x-for="phone in (driver.resolved_phones || [])"
                                                    :key="phone.id"
                                                >
                                                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
                                                        <div class="flex items-start justify-between gap-3">
                                                            <div class="min-w-0">
                                                                <p
                                                                    class="font-mono text-sm font-semibold text-gray-900 dark:text-white"
                                                                    x-text="phone.phone || phone.phone_normalized || '—'"
                                                                ></p>

                                                                <p
                                                                    class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400"
                                                                    x-text="phone.telegram_username ? '@' + String(phone.telegram_username).replace(/^@/, '') : 'No username'"
                                                                ></p>
                                                            </div>

                                                            <span
                                                                class="shrink-0 rounded-md bg-white px-1.5 py-1 text-[10px] text-gray-400 dark:bg-gray-900 dark:text-gray-500"
                                                                x-text="phone.telegram_user_id ?? '—'"
                                                            ></span>
                                                        </div>

                                                        <div class="mt-2 text-[11px] text-gray-400 dark:text-gray-500">
                                                            <span
                                                                x-text="phone.resolved_at ? formatDate(phone.resolved_at, true) : 'Unknown date'"
                                                            ></span>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Empty --}}
                <template x-if="!driversLoading && drivers.length === 0 && !driversError">
                    <div class="px-5 py-16 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-white/[0.06]">
                            <svg class="h-6 w-6 text-gray-400" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                            </svg>
                        </div>

                        <h3 class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">
                            No drivers
                        </h3>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            This operator has no drivers yet.
                        </p>
                    </div>
                </template>

                {{-- Error --}}
                <template x-if="driversError">
                    <div class="p-4 sm:p-5">
                        <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/40 dark:bg-red-950/30">
                            <p class="text-sm font-medium text-red-800 dark:text-red-300">
                                Failed to load drivers
                            </p>

                            <p
                                class="mt-1 text-sm text-red-700 dark:text-red-400"
                                x-text="driversError"
                            ></p>
                        </div>
                    </div>
                </template>

                {{-- Pagination --}}
                <div
                    x-show="driversMeta.last_page > 1"
                    x-cloak
                    class="border-t border-gray-200 px-4 py-4 sm:px-5 dark:border-gray-800"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                        <p class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                            Page
                            <span
                                class="font-medium text-gray-700 dark:text-gray-300"
                                x-text="driversMeta.current_page"
                            ></span>
                            of
                            <span
                                class="font-medium text-gray-700 dark:text-gray-300"
                                x-text="driversMeta.last_page"
                            ></span>
                        </p>

                        <div class="flex items-center justify-between gap-1 sm:justify-end">
                            <button
                                type="button"
                                @click="goToPage(driversMeta.current_page - 1)"
                                :disabled="driversMeta.current_page <= 1 || driversLoading"
                                class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 sm:text-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]"
                            >
                                Previous
                            </button>

                            <div class="flex items-center gap-1">
                                <template x-for="page in visiblePages()" :key="page">
                                    <template x-if="page !== '...'">
                                        <button
                                            type="button"
                                            @click="goToPage(page)"
                                            :disabled="driversLoading"
                                            class="h-9 min-w-9 rounded-lg px-2.5 text-xs font-medium transition sm:text-sm"
                                            :class="Number(page) === Number(driversMeta.current_page)
                                                ? 'bg-blue-600 text-white'
                                                : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]'"
                                            x-text="page"
                                        ></button>
                                    </template>

                                    <template x-if="page === '...'">
                                        <span class="flex h-9 min-w-7 items-center justify-center text-xs text-gray-400">
                                            ...
                                        </span>
                                    </template>
                                </template>
                            </div>

                            <button
                                type="button"
                                @click="goToPage(driversMeta.current_page + 1)"
                                :disabled="driversMeta.current_page >= driversMeta.last_page || driversLoading"
                                class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 sm:text-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Bottom breathing room because no footer --}}
            <div class="h-12 sm:h-20 lg:h-28"></div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function operationUserPage(config) {
        return {
            operationUserId: config.operationUserId,
            operationUserUrl: config.operationUserUrl,
            driversUrl: config.driversUrl,

            operator: null,
            operatorLoading: false,
            operatorError: null,

            drivers: [],
            driversLoading: false,
            driversError: null,

            page: 1,
            perPage: 10,

            driversMeta: {
                current_page: 1,
                last_page: 1,
                per_page: 10,
                total: 0,
                from: 0,
                to: 0,
            },

            async init() {
                this.readUrl();

                await Promise.all([
                    this.loadOperator(),
                    this.loadDrivers(),
                ]);
            },

            async refreshAll() {
                await Promise.all([
                    this.loadOperator(),
                    this.loadDrivers(),
                ]);
            },

            async loadOperator() {
                this.operatorLoading = true;
                this.operatorError = null;

                try {
                    const response = await fetch(
                        this.operatorUrl,
                        {
                            method: 'GET',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        }
                    );

                    if (!response.ok) {
                        throw await this.httpError(response);
                    }

                    const json = await response.json();

                    this.operator = json.data ?? json;

                } catch (error) {
                    console.error(error);
                    this.operatorError = error?.message || 'Unknown error occurred.';
                } finally {
                    this.operatorLoading = false;
                }
            },

            async loadDrivers() {
                this.driversLoading = true;
                this.driversError = null;

                const params = new URLSearchParams();

                params.set('page', this.page);
                params.set('per_page', this.perPage);

                try {
                    const response = await fetch(
                        this.driversUrl + '?' + params.toString(),
                        {
                            method: 'GET',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        }
                    );

                    if (!response.ok) {
                        throw await this.httpError(response);
                    }

                    const json = await response.json();

                    this.drivers = Array.isArray(json.data)
                        ? json.data
                        : [];

                    this.setDriversMeta(json);
                    this.syncUrl();

                } catch (error) {
                    console.error(error);

                    this.drivers = [];
                    this.driversError = error?.message || 'Unknown error occurred.';
                } finally {
                    this.driversLoading = false;
                }
            },

            async httpError(response) {
                let message = `HTTP ${response.status}`;

                try {
                    const json = await response.json();

                    if (json.message) {
                        message = json.message;
                    }
                } catch (_) {
                }

                return new Error(message);
            },

            setDriversMeta(json) {
                const meta = json.meta || {};

                this.driversMeta = {
                    current_page: Number(
                        meta.current_page ??
                        json.current_page ??
                        this.page
                    ),

                    last_page: Number(
                        meta.last_page ??
                        json.last_page ??
                        1
                    ),

                    per_page: Number(
                        meta.per_page ??
                        json.per_page ??
                        this.perPage
                    ),

                    total: Number(
                        meta.total ??
                        json.total ??
                        this.drivers.length
                    ),

                    from: Number(
                        meta.from ??
                        json.from ??
                        (
                            this.drivers.length
                                ? ((this.page - 1) * this.perPage) + 1
                                : 0
                        )
                    ),

                    to: Number(
                        meta.to ??
                        json.to ??
                        (
                            this.drivers.length
                                ? ((this.page - 1) * this.perPage) + this.drivers.length
                                : 0
                        )
                    ),
                };

                this.page = this.driversMeta.current_page;
            },

            changePerPage() {
                this.page = 1;
                this.loadDrivers();
            },

            goToPage(page) {
                page = Number(page);

                if (
                    page < 1 ||
                    page > this.driversMeta.last_page ||
                    page === this.driversMeta.current_page ||
                    this.driversLoading
                ) {
                    return;
                }

                this.page = page;
                this.loadDrivers();
            },

            visiblePages() {
                const current = Number(this.driversMeta.current_page);
                const last = Number(this.driversMeta.last_page);

                if (last <= 7) {
                    return Array.from(
                        { length: last },
                        (_, index) => index + 1
                    );
                }

                const pages = [1];

                if (current > 4) {
                    pages.push('...');
                }

                const start = Math.max(2, current - 1);
                const end = Math.min(last - 1, current + 1);

                for (let page = start; page <= end; page++) {
                    pages.push(page);
                }

                if (current < last - 3) {
                    pages.push('...');
                }

                pages.push(last);

                return [...new Set(pages)];
            },

            syncUrl() {
                const params = new URLSearchParams();

                if (this.page > 1) {
                    params.set('page', this.page);
                }

                if (this.perPage !== 10) {
                    params.set('per_page', this.perPage);
                }

                const query = params.toString();

                window.history.replaceState(
                    {},
                    '',
                    window.location.pathname + (query ? '?' + query : '')
                );
            },

            readUrl() {
                const params = new URLSearchParams(
                    window.location.search
                );

                this.page = Number(
                    params.get('page') || 1
                );

                this.perPage = Number(
                    params.get('per_page') || 10
                );

                if (![10, 25, 50, 100].includes(this.perPage)) {
                    this.perPage = 10;
                }
            },

            stats(row) {
                return row?.stats ?? {};
            },

            number(value) {
                return Number(value || 0).toLocaleString();
            },

            percent(value) {
                if (
                    value === null ||
                    value === undefined ||
                    value === ''
                ) {
                    return '0.0';
                }

                return Number(value).toFixed(1);
            },

            initials(name) {
                if (!name) {
                    return '?';
                }

                return String(name)
                    .trim()
                    .split(/\s+/)
                    .slice(0, 2)
                    .map(part => part.charAt(0).toUpperCase())
                    .join('');
            },

            formatDate(value, withTime = false) {
                if (!value) {
                    return '—';
                }

                let normalized = String(value);

                if (
                    !normalized.includes('T') &&
                    normalized.includes(' ')
                ) {
                    normalized = normalized.replace(' ', 'T');
                }

                const date = new Date(normalized);

                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return new Intl.DateTimeFormat(
                    navigator.language || 'en-US',
                    withTime
                        ? {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                        }
                        : {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                        }
                ).format(date);
            },
        };
    }
</script>
@endpush