@extends('layouts.app')

@section('title', 'Operation Users')

@section('content')
<div
    x-data="operationUsersPage()"
    x-init="init()"
    class="min-h-[calc(100vh-5rem)] px-3 py-4 sm:px-5 sm:py-6 lg:px-8 lg:py-8"
>
    <div class="mx-auto flex w-full max-w-[1920px] flex-col gap-6">

        {{-- Header --}}
        <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-600/20 dark:bg-blue-500 dark:shadow-blue-500/20">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 0 0 7.75"/>
                    </svg>
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl dark:text-white">
                            Operation Users
                        </h1>
                        <span class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-gray-500 shadow-sm dark:border-gray-800 dark:bg-white/[0.04] dark:text-gray-400">
                            Telegram
                        </span>
                    </div>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Telegram users and driver check statistics
                    </p>
                </div>
            </div>

            <button
                type="button"
                @click="load()"
                :disabled="loading"
                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700 shadow-sm transition hover:-translate-y-0.5 hover:border-gray-300 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto dark:border-gray-800 dark:bg-gray-900/80 dark:text-gray-200 dark:hover:border-gray-700 dark:hover:bg-white/[0.05]"
            >
                <svg class="h-4 w-4" :class="{ 'animate-spin': loading }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9M4.582 9H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2M19.419 15H15"/>
                </svg>
                <span x-text="loading ? 'Loading...' : 'Refresh'"></span>
            </button>
        </header>

        {{-- Summary --}}
        <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <div class="group rounded-2xl border border-gray-200/80 bg-white/90 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-5 dark:border-gray-800 dark:bg-gray-900/70">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Users</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white" x-text="formatNumber(pagination.total)">0</p>
                    </div>
                    <div class="hidden h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-gray-600 sm:flex dark:bg-white/[0.06] dark:text-gray-300">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="group rounded-2xl border border-gray-200/80 bg-white/90 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-5 dark:border-gray-800 dark:bg-gray-900/70">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Drivers</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white" x-text="formatNumber(sumStats('drivers'))">0</p>
                    </div>
                    <div class="hidden h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600 sm:flex dark:bg-blue-500/10 dark:text-blue-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 17h14M7 17V9l3-4h4l3 4v8M9 17v2m6-2v2M8 9h8"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="group rounded-2xl border border-gray-200/80 bg-white/90 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-5 dark:border-gray-800 dark:bg-gray-900/70">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Checks</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white" x-text="formatNumber(sumStats('checks'))">0</p>
                    </div>
                    <div class="hidden h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 sm:flex dark:bg-indigo-500/10 dark:text-indigo-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" d="M9 12l2 2 4-4"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 4v5c0 4.5-3 7.8-7 9-4-1.2-7-4.5-7-9V7l7-4z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="group rounded-2xl border border-gray-200/80 bg-white/90 p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-5 dark:border-gray-800 dark:bg-gray-900/70">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Avg. Match</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white" x-text="averageStats('match_rate') + '%'">0%</p>
                    </div>
                    <div class="hidden h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 sm:flex dark:bg-emerald-500/10 dark:text-emerald-400">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6"/>
                        </svg>
                    </div>
                </div>
            </div>
        </section>

        {{-- Filters --}}
        <section class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white/90 shadow-sm dark:border-gray-800 dark:bg-gray-900/70">
            <div class="flex flex-col gap-3 border-b border-gray-200/80 px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:justify-between dark:border-gray-800">
                <div>
                    <h2 class="text-base font-semibold tracking-tight text-gray-950 dark:text-white">Filters</h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Search and sort operation users</p>
                </div>
                <button
                    type="button"
                    @click="resetFilters()"
                    class="self-start text-sm font-medium text-gray-500 transition hover:text-gray-950 lg:self-auto dark:text-gray-400 dark:hover:text-white"
                >
                    Reset
                </button>
            </div>

            <div class="p-4 sm:p-5">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">Search</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <circle cx="11" cy="11" r="7"/>
                                    <path stroke-linecap="round" d="m20 20-4-4"/>
                                </svg>
                            </span>
                            <input
                                type="text"
                                x-model="filters.search"
                                @keydown.enter="applyFilters()"
                                placeholder="Name, username, Telegram ID..."
                                class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 pl-9 pr-3 text-sm text-gray-950 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white dark:focus:border-blue-500 dark:focus:bg-white/[0.05]"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">Telegram ID</label>
                        <input
                            type="number"
                            x-model="filters.telegram_id"
                            @keydown.enter="applyFilters()"
                            placeholder="Telegram ID"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3 text-sm text-gray-950 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white dark:focus:border-blue-500 dark:focus:bg-white/[0.05]"
                        >
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">Username</label>
                        <input
                            type="text"
                            x-model="filters.telegram_username"
                            @keydown.enter="applyFilters()"
                            placeholder="@username"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3 text-sm text-gray-950 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white dark:focus:border-blue-500 dark:focus:bg-white/[0.05]"
                        >
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Sort</span>
                        <select
                            x-model="filters.sort"
                            @change="applyFilters()"
                            class="h-9 rounded-lg border border-gray-200 bg-gray-50 px-3 text-xs font-medium text-gray-700 outline-none transition focus:border-blue-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"
                        >
                            <option value="created_at">Created</option>
                            <option value="updated_at">Updated</option>
                            <option value="name">Name</option>
                            <option value="drivers">Drivers</option>
                            <option value="checks">Checks</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="not_confirmed">Not confirmed</option>
                            <option value="pending">Pending</option>
                            <option value="match_rate">Match rate</option>
                            <option value="avg_match_score">Average score</option>
                            <option value="last_check_at">Last check</option>
                        </select>

                        <button
                            type="button"
                            @click="filters.direction = filters.direction === 'asc' ? 'desc' : 'asc'; applyFilters()"
                            :title="filters.direction === 'asc' ? 'Ascending' : 'Descending'"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-600 transition hover:border-gray-300 hover:bg-gray-100 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]"
                        >
                            <svg x-show="filters.direction === 'asc'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" d="M12 19V5"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 7-7 7 7"/>
                            </svg>
                            <svg x-show="filters.direction === 'desc'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" d="M12 5v14"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19 12-7 7-7-7"/>
                            </svg>
                        </button>
                    </div>

                    <button
                        type="button"
                        @click="applyFilters()"
                        :disabled="loading"
                        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto dark:bg-blue-500 dark:hover:bg-blue-400"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="11" cy="11" r="7"/>
                            <path stroke-linecap="round" d="m20 20-4-4"/>
                        </svg>
                        Search
                    </button>
                </div>
            </div>
        </section>

        {{-- Error --}}
        <div
            x-show="error"
            x-cloak
            class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/40 dark:bg-red-950/30"
        >
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="9"/>
                    <path stroke-linecap="round" d="M12 8v4"/>
                    <path stroke-linecap="round" d="M12 16h.01"/>
                </svg>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-red-800 dark:text-red-300">Failed to load operation users</p>
                    <p class="mt-1 break-words text-sm text-red-700 dark:text-red-400" x-text="error"></p>
                </div>
            </div>
        </div>

        {{-- Single responsive data view --}}
        <section class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white/90 shadow-sm dark:border-gray-800 dark:bg-gray-900/70">
            <div class="flex flex-col gap-3 border-b border-gray-200/80 px-4 py-4 sm:px-5 md:flex-row md:items-center md:justify-between dark:border-gray-800">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold tracking-tight text-gray-950 dark:text-white">Users</h2>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500 dark:bg-white/[0.05] dark:text-gray-400" x-text="formatNumber(pagination.total)">0</span>
                    </div>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Showing <span x-text="pagination.from || 0"></span>–<span x-text="pagination.to || 0"></span>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="hidden text-xs text-gray-500 sm:inline dark:text-gray-400">Per page</span>
                    <select
                        x-model.number="filters.per_page"
                        @change="applyFilters()"
                        class="h-9 rounded-lg border border-gray-200 bg-gray-50 px-2.5 text-sm font-medium text-gray-700 outline-none focus:border-blue-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] table-fixed sm:min-w-[960px] xl:min-w-[1180px]">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/70 dark:border-gray-800 dark:bg-white/[0.02]">
                            <th class="w-[25%] px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 sm:px-5 xl:w-[24%] dark:text-gray-400">User</th>
                            <th class="w-[18%] px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Telegram</th>
                            <th class="w-[10%] px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Drivers</th>
                            <th class="hidden w-[10%] px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 sm:table-cell dark:text-gray-400">Checks</th>
                            <th class="hidden w-[12%] px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 md:table-cell dark:text-gray-400">Result</th>
                            <th class="w-[13%] px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">Match</th>
                            <th class="hidden w-[9%] px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 lg:table-cell dark:text-gray-400">Score</th>
                            <th class="w-[7%] px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 sm:px-5 dark:text-gray-400"></th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        {{-- Loading --}}
                        <template x-if="loading && rows.length === 0">
                            <template x-for="i in 8" :key="'skeleton-' + i">
                                <tr>
                                    <td colspan="8" class="px-4 py-4 sm:px-5">
                                        <div class="h-11 animate-pulse rounded-xl bg-gray-100 dark:bg-white/[0.04]"></div>
                                    </td>
                                </tr>
                            </template>
                        </template>

                        {{-- ONE row markup for every viewport --}}
                        <template x-for="row in rows" :key="row.id">
                            <tr
                                role="link"
                                tabindex="0"
                                @click="openUser(row)"
                                @keydown.enter.prevent="openUser(row)"
                                @keydown.space.prevent="openUser(row)"
                                class="group cursor-pointer outline-none transition-colors hover:bg-blue-50/60 focus:bg-blue-50/60 focus:ring-2 focus:ring-inset focus:ring-blue-500/40 dark:hover:bg-blue-500/[0.045] dark:focus:bg-blue-500/[0.045]"
                                :aria-label="'Open user ' + (row.name || row.id)"
                            >
                                <td class="px-4 py-4 sm:px-5">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-gray-200 via-gray-100 to-white text-xs font-bold text-gray-700 ring-1 ring-inset ring-gray-300 shadow-sm transition group-hover:scale-105 dark:from-indigo-500/30 dark:via-slate-700 dark:to-slate-900 dark:text-indigo-100 dark:ring-indigo-400/30">
                                            <svg class="absolute h-6 w-6 text-gray-400/45 dark:text-indigo-200/20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                                <circle cx="12" cy="8" r="3.5"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.5 20c.7-3.2 3-5 6.5-5s5.8 1.8 6.5 5"/>
                                            </svg>
                                            <span class="relative z-10" x-text="initials(row.name)"></span>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white" x-text="row.name || '—'"></p>
                                            <div class="mt-1 flex items-center gap-2 text-[11px] text-gray-400 dark:text-gray-500">
                                                <span>ID</span>
                                                <span class="font-mono" x-text="row.id ?? '—'"></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-4">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100" x-text="telegramUsername(row.telegram_username)"></p>
                                        <p class="mt-1 truncate font-mono text-[11px] text-gray-400 dark:text-gray-500" x-text="row.telegram_id ?? 'No Telegram ID'"></p>
                                    </div>
                                </td>

                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex min-w-8 items-center justify-center rounded-lg bg-gray-100 px-2 py-1 text-sm font-semibold text-gray-800 dark:bg-white/[0.06] dark:text-gray-200" x-text="stats(row).drivers"></span>
                                </td>

                                <td class="hidden px-4 py-4 text-center sm:table-cell">
                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200" x-text="stats(row).checks"></span>
                                </td>

                                <td class="hidden px-4 py-4 md:table-cell">
                                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                                        <span class="inline-flex min-w-7 items-center justify-center gap-1 rounded-lg bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200/70 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/15" :title="'Confirmed: ' + stats(row).confirmed">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                                            <span x-text="stats(row).confirmed"></span>
                                        </span>
                                        <span class="inline-flex min-w-7 items-center justify-center gap-1 rounded-lg bg-red-50 px-2 py-1 text-[11px] font-semibold text-red-700 ring-1 ring-inset ring-red-200/70 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/15" :title="'Not confirmed: ' + stats(row).not_confirmed">
                                            <span class="h-1.5 w-1.5 rounded-full bg-red-500 dark:bg-red-400"></span>
                                            <span x-text="stats(row).not_confirmed"></span>
                                        </span>
                                        <span class="inline-flex min-w-7 items-center justify-center gap-1 rounded-lg bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-200/70 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/15" :title="'Pending: ' + stats(row).pending">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500 dark:bg-amber-400"></span>
                                            <span x-text="stats(row).pending"></span>
                                        </span>
                                        <span class="inline-flex min-w-7 items-center justify-center gap-1 rounded-lg bg-violet-50 px-2 py-1 text-[11px] font-semibold text-violet-700 ring-1 ring-inset ring-violet-200/70 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-400/15" :title="'Processing: ' + stats(row).processing">
                                            <span class="h-1.5 w-1.5 rounded-full bg-violet-500 dark:bg-violet-400"></span>
                                            <span x-text="stats(row).processing"></span>
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-4">
                                    <div class="mx-auto max-w-[112px]">
                                        <div class="flex items-center justify-center">
                                            <span class="text-xs font-bold tabular-nums text-gray-900 dark:text-white" x-text="formatPercent(stats(row).match_rate) + '%'"></span>
                                        </div>
                                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/[0.08]">
                                            <div
                                                class="h-full rounded-full bg-blue-600 transition-all dark:bg-blue-500"
                                                :style="{ width: Math.min(100, Math.max(0, Number(stats(row).match_rate || 0))) + '%' }"
                                            ></div>
                                        </div>
                                    </div>
                                </td>

                                <td class="hidden px-4 py-4 text-center lg:table-cell">
                                    <div class="flex flex-col items-center">
                                        <span class="text-sm font-semibold tabular-nums text-gray-900 dark:text-white" x-text="formatScore(stats(row).avg_match_score)"></span>
                                        <span class="mt-0.5 text-[10px] font-medium text-gray-400 dark:text-gray-500" x-text="'Best ' + formatScore(stats(row).best_match_score)"></span>
                                    </div>
                                </td>

                                <td class="px-4 py-4 sm:px-5">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="hidden text-right xl:block">
                                            <p class="text-xs font-medium text-gray-700 dark:text-gray-300" x-text="stats(row).last_check_at ? formatDate(stats(row).last_check_at, true) : 'Never'"></p>
                                            <p class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500">Last check</p>
                                        </div>
                                        <div class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-400 transition group-hover:border-blue-200 group-hover:bg-blue-50 group-hover:text-blue-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-500 dark:group-hover:border-blue-500/30 dark:group-hover:bg-blue-500/10 dark:group-hover:text-blue-400">
                                            <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/>
                                            </svg>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        {{-- Empty --}}
                        <template x-if="!loading && rows.length === 0 && !error">
                            <tr>
                                <td colspan="8" class="px-5 py-20 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-white/[0.06] dark:text-gray-500">
                                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                                <circle cx="11" cy="11" r="7"/>
                                                <path stroke-linecap="round" d="m20 20-4-4"/>
                                            </svg>
                                        </div>
                                        <h3 class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">No users found</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try changing your filters or search query.</p>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div
                x-show="pagination.last_page > 1"
                x-cloak
                class="border-t border-gray-200/80 px-4 py-4 sm:px-5 dark:border-gray-800"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                        Page
                        <span class="font-semibold text-gray-700 dark:text-gray-300" x-text="pagination.current_page"></span>
                        of
                        <span class="font-semibold text-gray-700 dark:text-gray-300" x-text="pagination.last_page"></span>
                    </p>

                    <div class="flex items-center justify-between gap-1 sm:justify-end">
                        <button
                            type="button"
                            @click="goToPage(pagination.current_page - 1)"
                            :disabled="pagination.current_page <= 1 || loading"
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
                                        :disabled="loading"
                                        class="h-9 min-w-9 rounded-lg px-2.5 text-xs font-medium transition sm:text-sm"
                                        :class="Number(page) === Number(pagination.current_page)
                                            ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950'
                                            : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]'"
                                        x-text="page"
                                    ></button>
                                </template>

                                <template x-if="page === '...'">
                                    <span class="flex h-9 min-w-7 items-center justify-center text-xs text-gray-400">...</span>
                                </template>
                            </template>
                        </div>

                        <button
                            type="button"
                            @click="goToPage(pagination.current_page + 1)"
                            :disabled="pagination.current_page >= pagination.last_page || loading"
                            class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 sm:text-sm dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
function operationUsersPage() {

        return {
            detailsBaseUrl: @json(url()->current()),

            openUser(row) {
                if (!row?.id) {
                    return;
                }

                window.location.href = `${this.detailsBaseUrl}/${encodeURIComponent(row.id)}`;
            },

            loading: false,

            error: null,

            rows: [],

            filters: {

                search: '',

                telegram_id: '',

                telegram_username: '',

                sort: 'created_at',

                direction: 'desc',

                per_page: 10,

                page: 1,

            },

            pagination: {

                current_page: 1,

                last_page: 1,

                per_page: 10,

                total: 0,

                from: 0,

                to: 0,

            },

            init() {

                this.readUrl();

                this.load();

            },

            async load() {

                this.loading = true;

                this.error = null;

                const params = new URLSearchParams();

                if (this.filters.search) {

                    params.set('search', this.filters.search);

                }

                if (this.filters.telegram_id) {

                    params.set('telegram_id', this.filters.telegram_id);

                }

                if (this.filters.telegram_username) {

                    params.set('telegram_username', this.filters.telegram_username);

                }

                params.set('sort', this.filters.sort);

                params.set('direction', this.filters.direction);

                params.set('per_page', this.filters.per_page);

                params.set('page', this.filters.page);

                try {

                    const response = await fetch(

                        @json(route('api.telegram.operation-users')) + '?' + params.toString(),

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

                        let message = `HTTP ${response.status}`;

                        try {

                            const body = await response.json();

                            if (body.message) {

                                message = body.message;

                            }

                        } catch (_) {

                        }

                        throw new Error(message);

                    }

                    const json = await response.json();

                    this.rows = Array.isArray(json.data)

                        ? json.data

                        : [];

                    this.setPagination(json);

                    this.syncUrl();

                } catch (error) {

                    console.error(error);

                    this.rows = [];

                    this.error = error?.message || 'Unknown error occurred.';

                } finally {

                    this.loading = false;

                }

            },

            stats(row) {

                return row?.stats ?? {

                    drivers: 0,

                    checks: 0,

                    confirmed: 0,

                    not_confirmed: 0,

                    pending: 0,

                    processing: 0,

                    match_rate: 0,

                    avg_match_score: null,

                    best_match_score: null,

                    last_check_at: null,

                };

            },

            sumStats(field) {

                return this.rows.reduce((total, row) => {

                    return total + Number(this.stats(row)[field] || 0);

                }, 0);

            },

            averageStats(field) {

                if (!this.rows.length) {

                    return '0.0';

                }

                const total = this.rows.reduce((sum, row) => {

                    return sum + Number(this.stats(row)[field] || 0);

                }, 0);

                return (total / this.rows.length).toFixed(1);

            },

            setPagination(json) {

                const meta = json.meta || {};

                this.pagination = {

                    current_page: Number(

                        meta.current_page ??

                        json.current_page ??

                        this.filters.page ??

                        1

                   ),

                    last_page: Number(

                        meta.last_page ??

                        json.last_page ??

                        1

                   ),

                    per_page: Number(

                        meta.per_page ??

                        json.per_page ??

                        this.filters.per_page ??

                        10

                   ),

                    total: Number(

                        meta.total ??

                        json.total ??

                        this.rows.length

                   ),

                    from: Number(

                        meta.from ??

                        json.from ??

                        (

                            this.rows.length

                                ? ((Number(meta.current_page ?? this.filters.page ?? 1) - 1) *

                                   Number(meta.per_page ?? this.filters.per_page ?? 10)) + 1

                                : 0

                       )

                   ),

                    to: Number(

                        meta.to ??

                        json.to ??

                        (

                            this.rows.length

                                ? ((Number(meta.current_page ?? this.filters.page ?? 1) - 1) *

                                   Number(meta.per_page ?? this.filters.per_page ?? 10)) + this.rows.length

                                : 0

                       )

                   ),

                };

                this.filters.page = this.pagination.current_page;

            },

            applyFilters() {

                this.filters.page = 1;

                this.load();

            },

            resetFilters() {

                this.filters = {

                    search: '',

                    telegram_id: '',

                    telegram_username: '',

                    sort: 'created_at',

                    direction: 'desc',

                    per_page: 10,

                    page: 1,

                };

                this.load();

            },

            goToPage(page) {

                page = Number(page);

                if (

                    page < 1 ||

                    page > this.pagination.last_page ||

                    page === this.pagination.current_page ||

                    this.loading

               ) {

                    return;

                }

                this.filters.page = page;

                this.load();

            },

            readUrl() {

                const params = new URLSearchParams(window.location.search);

                this.filters.search =

                    params.get('search') || '';

                this.filters.telegram_id =

                    params.get('telegram_id') || '';

                this.filters.telegram_username =

                    params.get('telegram_username') || '';

                this.filters.sort =

                    params.get('sort') || 'created_at';

                this.filters.direction =

                    params.get('direction') === 'asc'

                        ? 'asc'

                        : 'desc';

                this.filters.per_page =

                    Number(params.get('per_page') || 10);

                this.filters.page =

                    Number(params.get('page') || 1);

            },

            syncUrl() {

                const params = new URLSearchParams();

                if (this.filters.search) {

                    params.set('search', this.filters.search);

                }

                if (this.filters.telegram_id) {

                    params.set('telegram_id', this.filters.telegram_id);

                }

                if (this.filters.telegram_username) {

                    params.set('telegram_username', this.filters.telegram_username);

                }

                params.set('sort', this.filters.sort);

                params.set('direction', this.filters.direction);

                params.set('per_page', this.filters.per_page);

                if (this.filters.page > 1) {

                    params.set('page', this.filters.page);

                }

                const query = params.toString();

                window.history.replaceState(

                    {},

                    '',

                    window.location.pathname + (query ? '?' + query : '')

               );

            },

            visiblePages() {

                const current = Number(this.pagination.current_page);

                const last = Number(this.pagination.last_page);

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

            telegramUsername(username) {

                if (!username) {

                    return 'No username';

                }

                return '@' + String(username).replace(/^@/, '');

            },

            formatNumber(value) {

                return Number(value || 0).toLocaleString();

            },

            formatPercent(value) {

                if (

                    value === null ||

                    value === undefined ||

                    value === ''

               ) {

                    return '0.0';

                }

                return Number(value).toFixed(1);

            },

            formatScore(value) {

                if (

                    value === null ||

                    value === undefined ||

                    value === ''

               ) {

                    return '—';

                }

                return Number(value).toFixed(1);

            },

            formatDate(value, withTime = false) {

                if (!value) {

                    return '—';

                }

                const date = new Date(value.replace(' ', 'T'));

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

        };

    }
</script>
@endpush
