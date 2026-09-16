@extends('layouts.app')

@section('title', __('telegram.resolved_phones.title'))

@section('content')
<div
    x-data="resolvedPhonesPage()"
    x-init="init()"
    class="min-h-[calc(100vh-5rem)] px-3 py-4 sm:px-5 sm:py-6 lg:px-8 lg:py-8"
>
    <div class="mx-auto flex w-full max-w-[1920px] flex-col gap-6">

        {{-- Header --}}
        <header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl
                           bg-blue-600 text-white shadow-lg shadow-blue-600/20
                           dark:bg-blue-500 dark:shadow-blue-500/20">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.00002 12.0957C4.00002 7.67742 7.58174 4.0957 12 4.0957C16.4183 4.0957 20 7.67742 20 12.0957C20 16.514 16.4183 20.0957 12 20.0957H5.06068L6.34317 18.8132" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl dark:text-white">
                            {{ __('telegram.resolved_phones.title') }}
                        </h1>
                        <span class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px]
                                   font-semibold uppercase tracking-wider text-gray-500 shadow-sm
                                   dark:border-gray-800 dark:bg-white/[0.04] dark:text-gray-400">
                            Telegram
                        </span>
                    </div>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('telegram.resolved_phones.description') }}
                    </p>
                </div>
            </div>

            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <button
                    type="button"
                    @click="load()"
                    :disabled="loading"
                    class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl
                           border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700
                           shadow-sm transition hover:-translate-y-0.5 hover:border-gray-300
                           hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60
                           sm:w-auto dark:border-gray-800 dark:bg-gray-900/80
                           dark:text-gray-200 dark:hover:border-gray-700 dark:hover:bg-white/[0.05]"
                >
                    <svg class="h-4 w-4" :class="{ 'animate-spin': loading }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9M4.582 9H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2M19.419 15H15" />
                    </svg>
                    <span x-text="loading ? translations.loading : translations.refresh"></span>
                </button>
            </div>
        </header>

        {{-- Filters --}}
        <section class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white/90 shadow-sm
                   dark:border-gray-800 dark:bg-gray-900/70">
            <div class="flex flex-col gap-3 border-b border-gray-200/80 px-4 py-4 sm:px-5
                       lg:flex-row lg:items-center lg:justify-between dark:border-gray-800">
                <div>
                    <h2 class="text-base font-semibold tracking-tight text-gray-950 dark:text-white">
                        {{ __('telegram.resolved_phones.filters.title') }}
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('telegram.resolved_phones.filters.description') }}
                    </p>
                </div>
                <button
                    type="button"
                    @click="resetFilters()"
                    class="self-start text-sm font-medium text-gray-500 transition
                           hover:text-gray-950 lg:self-auto dark:text-gray-400 dark:hover:text-white"
                >
                    {{ __('telegram.resolved_phones.filters.reset') }}
                </button>
            </div>

            <div class="p-4 sm:p-5">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    {{-- Search --}}
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.resolved_phones.filters.search') }}
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-4-4" />
                                </svg>
                            </span>
                            <input type="text" x-model="filters.search" @keydown.enter="applyFilters()"
                                placeholder="{{ __('telegram.resolved_phones.filters.search_placeholder') }}"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 pl-9 pr-3
                                       text-sm text-gray-950 outline-none transition
                                       focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10
                                       dark:border-gray-700 dark:bg-white/[0.03] dark:text-white">
                        </div>
                    </div>

                    {{-- Has username --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.resolved_phones.filters.has_username') }}
                        </label>
                        <select x-model="filters.has_username" @change="applyFilters()"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3 text-sm text-gray-950
                                   outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white">
                            <option value="">{{ __('telegram.resolved_phones.filters.any') }}</option>
                            <option value="1">{{ __('telegram.resolved_phones.filters.yes') }}</option>
                            <option value="0">{{ __('telegram.resolved_phones.filters.no') }}</option>
                        </select>
                    </div>

                    {{-- Has driver --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.resolved_phones.filters.has_driver') }}
                        </label>
                        <select x-model="filters.has_driver" @change="applyFilters()"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3 text-sm text-gray-950
                                   outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white">
                            <option value="">{{ __('telegram.resolved_phones.filters.any') }}</option>
                            <option value="1">{{ __('telegram.resolved_phones.filters.yes') }}</option>
                            <option value="0">{{ __('telegram.resolved_phones.filters.no') }}</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 xl:grid-cols-4 dark:border-gray-800">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.resolved_phones.filters.period') }}
                        </label>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <template x-for="preset in periodPresets" :key="preset.value">
                                <button type="button" @click="setPeriod(preset.value)"
                                    class="h-9 rounded-lg px-2.5 text-xs font-medium transition"
                                    :class="periodPreset === preset.value
                                        ? 'bg-blue-600 text-white'
                                        : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]'"
                                    x-text="preset.label"
                                ></button>
                            </template>
                        </div>
                    </div>
                    <div class="sm:col-span-2 xl:col-span-2" x-show="periodPreset === 'custom'" x-cloak>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.resolved_phones.filters.period_from') }} — {{ __('telegram.resolved_phones.filters.period_to') }}
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="date" x-model="filters.period_from" @change="applyFilters()"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3 text-sm text-gray-950
                                       outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white">
                            <input type="date" x-model="filters.period_to" @change="applyFilters()"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3 text-sm text-gray-950
                                       outline-none focus:border-blue-500 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 flex items-center gap-2 text-xs font-medium text-gray-700 dark:text-gray-300">
                            <input type="checkbox" x-model="filters.stale" @change="applyFilters()" class="h-4 w-4 rounded border-gray-300">
                            {{ __('telegram.resolved_phones.filters.stale') }}
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4
                           sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ __('telegram.resolved_phones.filters.sort') }}
                        </span>
                        <select x-model="filters.sort" @change="applyFilters()"
                            class="h-9 rounded-lg border border-gray-200 bg-gray-50 px-3 text-xs font-medium
                                   text-gray-700 outline-none focus:border-blue-500
                                   dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                            <option value="resolved_at">{{ __('telegram.resolved_phones.filters.resolved') }}</option>
                            <option value="created_at">{{ __('telegram.resolved_phones.filters.created') }}</option>
                            <option value="phone_normalized">{{ __('telegram.resolved_phones.filters.phone') }}</option>
                            <option value="checks">{{ __('telegram.resolved_phones.filters.checks') }}</option>
                            <option value="confirmed">{{ __('telegram.resolved_phones.filters.confirmed') }}</option>
                            <option value="not_confirmed">{{ __('telegram.resolved_phones.filters.not_confirmed') }}</option>
                        </select>
                        <button type="button" @click="filters.direction = filters.direction === 'asc' ? 'desc' : 'asc'; applyFilters()"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200
                                   bg-gray-50 text-gray-600 transition hover:bg-gray-100
                                   dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                            <svg x-show="filters.direction === 'asc'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" d="M12 19V5" /><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 7-7 7 7" />
                            </svg>
                            <svg x-show="filters.direction === 'desc'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" d="M12 5v14" /><path stroke-linecap="round" stroke-linejoin="round" d="m19 12-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    <button type="button" @click="applyFilters()" :disabled="loading"
                        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5
                               text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition
                               hover:-translate-y-0.5 hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60
                               sm:w-auto dark:bg-blue-500 dark:hover:bg-blue-400">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-4-4" />
                        </svg>
                        {{ __('telegram.resolved_phones.filters.apply') }}
                    </button>
                </div>
            </div>
        </section>

        {{-- Error --}}
        <div x-show="error" x-cloak class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/40 dark:bg-red-950/30">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 8v4" /><path stroke-linecap="round" d="M12 16h.01" />
                </svg>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-red-800 dark:text-red-300">{{ __('telegram.resolved_phones.errors.load_failed') }}</p>
                    <p class="mt-1 break-words text-sm text-red-700 dark:text-red-400" x-text="error"></p>
                </div>
            </div>
        </div>

        {{-- Data --}}
        <section class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white/90 shadow-sm dark:border-gray-800 dark:bg-gray-900/70">
            <div class="flex flex-col gap-3 border-b border-gray-200/80 px-4 py-4 sm:px-5
                       md:flex-row md:items-center md:justify-between dark:border-gray-800">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold tracking-tight text-gray-950 dark:text-white">
                            {{ __('telegram.resolved_phones.stats.total') }}
                        </h2>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500
                                   dark:bg-white/[0.05] dark:text-gray-400" x-text="formatNumber(pagination.total)">0</span>
                    </div>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('telegram.resolved_phones.pagination.showing') }}
                        <span x-text="pagination.from || 0"></span>–<span x-text="pagination.to || 0"></span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="hidden text-xs text-gray-500 sm:inline dark:text-gray-400">Per page</span>
                    <select x-model.number="filters.per_page" @change="applyFilters()"
                        class="h-9 rounded-lg border border-gray-200 bg-gray-50 px-2.5 text-sm font-medium
                               text-gray-700 outline-none focus:border-blue-500
                               dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] table-fixed xl:min-w-[1180px]">
                    <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/70 dark:border-gray-800 dark:bg-white/[0.02]">
                        <th class="w-[16%] px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 sm:px-5 dark:text-gray-400">
                            {{ __('telegram.resolved_phones.table.phone') }}
                        </th>
                        <th class="w-[20%] px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">
                            {{ __('telegram.resolved_phones.table.telegram') }}
                        </th>
                        <th class="w-[18%] px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 md:table-cell dark:text-gray-400">
                            {{ __('telegram.resolved_phones.table.driver') }}
                        </th>
                        <th class="hidden w-[16%] px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 lg:table-cell dark:text-gray-400">
                            {{ __('telegram.resolved_phones.table.operator') }}
                        </th>
                        <th class="hidden w-[12%] px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 md:table-cell dark:text-gray-400">
                            {{ __('telegram.resolved_phones.table.result') }}
                        </th>
                        <th class="w-[18%] px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.14em] text-gray-500 sm:px-5 dark:text-gray-400">
                            {{ __('telegram.resolved_phones.table.resolved_at') }}
                        </th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">

                    <template x-if="loading && rows.length === 0">
                        <template x-for="i in 8" :key="'skeleton-' + i">
                            <tr><td colspan="6" class="px-4 py-4 sm:px-5">
                                <div class="h-11 animate-pulse rounded-xl bg-gray-100 dark:bg-white/[0.04]"></div>
                            </td></tr>
                        </template>
                    </template>

                    <template x-for="row in rows" :key="row.id">
                        <tr
                            role="link" tabindex="0"
                            @click="openDriver(row)"
                            @keydown.enter.prevent="openDriver(row)"
                            class="group cursor-pointer outline-none transition-colors hover:bg-blue-50/60 focus:bg-blue-50/60
                                   dark:hover:bg-blue-500/[0.045] dark:focus:bg-blue-500/[0.045]"
                        >
                            <td class="px-4 py-4 font-mono text-sm text-gray-900 sm:px-5 dark:text-gray-100" x-text="row.phone_normalized || '—'"></td>
                            <td class="px-4 py-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100"
                                       x-text="[row.telegram_first_name, row.telegram_last_name].filter(Boolean).join(' ') || '—'"></p>
                                    <p class="mt-1 truncate text-[11px] text-gray-400 dark:text-gray-500"
                                       x-text="row.telegram_username ? '@' + row.telegram_username : translations.table.no_username"></p>
                                </div>
                            </td>
                            <td class="px-4 py-4 md:table-cell">
                                <p class="truncate text-sm text-gray-800 dark:text-gray-200"
                                   x-text="row.driver?.name || translations.table.no_driver"></p>
                            </td>
                            <td class="hidden px-4 py-4 lg:table-cell">
                                <p class="truncate text-sm text-gray-800 dark:text-gray-200" x-text="row.driver?.operation_user?.name || '—'"></p>
                            </td>
                            <td class="hidden px-4 py-4 md:table-cell">
                                <div class="flex items-center justify-center gap-1.5">
                                    <span class="inline-flex min-w-8 items-center justify-center rounded-md bg-emerald-50 px-2 py-1
                                               text-[11px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                                          x-text="row.stats?.confirmed ?? 0"></span>
                                    <span class="inline-flex min-w-8 items-center justify-center rounded-md bg-red-50 px-2 py-1
                                               text-[11px] font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-300"
                                          x-text="row.stats?.not_confirmed ?? 0"></span>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-right sm:px-5">
                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300"
                                   x-text="row.resolved_at ? formatDate(row.resolved_at) : translations.dates.never"></p>
                            </td>
                        </tr>
                    </template>

                    <template x-if="!loading && rows.length === 0 && !error">
                        <tr><td colspan="6" class="px-5 py-20 text-center">
                            <div class="mx-auto flex max-w-sm flex-col items-center">
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-white/[0.06] dark:text-gray-500">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                        <circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-4-4" />
                                    </svg>
                                </div>
                                <h3 class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">{{ __('telegram.resolved_phones.empty.title') }}</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('telegram.resolved_phones.empty.description') }}</p>
                            </div>
                        </td></tr>
                    </template>
                    </tbody>
                </table>
            </div>

            <div x-show="pagination.last_page > 1" x-cloak class="border-t border-gray-200/80 px-4 py-4 sm:px-5 dark:border-gray-800">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-gray-500 sm:text-sm dark:text-gray-400">
                        {{ __('telegram.resolved_phones.pagination.page') }}
                        <span class="font-semibold text-gray-700 dark:text-gray-300" x-text="pagination.current_page"></span>
                        {{ __('telegram.resolved_phones.pagination.of') }}
                        <span class="font-semibold text-gray-700 dark:text-gray-300" x-text="pagination.last_page"></span>
                    </p>
                    <div class="flex items-center justify-between gap-1 sm:justify-end">
                        <button type="button" @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1 || loading"
                            class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 transition
                                   hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 sm:text-sm
                                   dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                            {{ __('telegram.resolved_phones.pagination.previous') }}
                        </button>
                        <div class="flex items-center gap-1">
                            <template x-for="page in visiblePages()" :key="page">
                                <template x-if="page !== '...'">
                                    <button type="button" @click="goToPage(page)" :disabled="loading"
                                        class="h-9 min-w-9 rounded-lg px-2.5 text-xs font-medium transition sm:text-sm"
                                        :class="Number(page) === Number(pagination.current_page)
                                            ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950'
                                            : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]'"
                                        x-text="page"></button>
                                </template>
                                <template x-if="page === '...'">
                                    <span class="flex h-9 min-w-7 items-center justify-center text-xs text-gray-400">...</span>
                                </template>
                            </template>
                        </div>
                        <button type="button" @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page || loading"
                            class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium text-gray-700 transition
                                   hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 sm:text-sm
                                   dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]">
                            {{ __('telegram.resolved_phones.pagination.next') }}
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
    function resolvedPhonesPage() {
        return {
            operationUserBaseUrl: @json(url('/driver-check/operation-users')),

            translations: @json(__('telegram.resolved_phones')),

            loading: false,
            error: null,
            rows: [],

            filters: {
                search: '',
                has_username: '',
                has_driver: '',
                stale: false,
                period_from: '',
                period_to: '',
                sort: 'resolved_at',
                direction: 'desc',
                per_page: 10,
                page: 1,
            },

            periodPreset: 'all',
            periodPresets: [
                { value: 'all', label: @json(__('telegram.resolved_phones.filters.period_all')) },
                { value: 'today', label: @json(__('telegram.resolved_phones.filters.period_today')) },
                { value: 'week', label: @json(__('telegram.resolved_phones.filters.period_week')) },
                { value: 'month', label: @json(__('telegram.resolved_phones.filters.period_month')) },
                { value: 'custom', label: @json(__('telegram.resolved_phones.filters.period_custom')) },
            ],

            pagination: { current_page: 1, last_page: 1, per_page: 10, total: 0, from: 0, to: 0 },

            init() {
                this.readUrl();
                this.load();
            },

            openDriver(row) {
                const operationUserId = row?.driver?.operation_user?.id;
                if (!operationUserId) return;
                window.location.href = `${this.operationUserBaseUrl}/${encodeURIComponent(operationUserId)}`;
            },

            setPeriod(preset) {
                this.periodPreset = preset;
                const today = new Date();
                const iso = (d) => d.toISOString().slice(0, 10);

                if (preset === 'all') {
                    this.filters.period_from = '';
                    this.filters.period_to = '';
                } else if (preset === 'today') {
                    this.filters.period_from = iso(today);
                    this.filters.period_to = iso(today);
                } else if (preset === 'week') {
                    const from = new Date(today); from.setDate(from.getDate() - 6);
                    this.filters.period_from = iso(from);
                    this.filters.period_to = iso(today);
                } else if (preset === 'month') {
                    const from = new Date(today); from.setDate(from.getDate() - 29);
                    this.filters.period_from = iso(from);
                    this.filters.period_to = iso(today);
                } else {
                    return;
                }

                this.applyFilters();
            },

            buildParams(includePage = true) {
                const params = new URLSearchParams();
                ['search', 'has_username', 'has_driver', 'period_from', 'period_to'].forEach((field) => {
                    const value = this.filters[field];
                    if (value !== '' && value !== null && value !== undefined) params.set(field, value);
                });
                if (this.filters.stale) params.set('stale', '1');
                params.set('sort', this.filters.sort);
                params.set('direction', this.filters.direction);
                params.set('per_page', this.filters.per_page);
                if (includePage) params.set('page', this.filters.page);
                return params;
            },

            async load() {
                this.loading = true;
                this.error = null;
                const params = this.buildParams();

                try {
                    const response = await fetch(@json(route('api.telegram.resolved-phones')) + '?' + params.toString(), {
                        method: 'GET',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        let message = `HTTP ${response.status}`;
                        try { const body = await response.json(); if (body.message) message = body.message; } catch (_) {}
                        throw new Error(message);
                    }

                    const json = await response.json();
                    this.rows = Array.isArray(json.data) ? json.data : [];
                    this.setPagination(json);
                    this.syncUrl();
                } catch (error) {
                    console.error(error);
                    this.rows = [];
                    this.error = error?.message || this.translations.errors.unknown;
                } finally {
                    this.loading = false;
                }
            },

            setPagination(json) {
                const meta = json.meta || {};
                this.pagination = {
                    current_page: Number(meta.current_page ?? this.filters.page ?? 1),
                    last_page: Number(meta.last_page ?? 1),
                    per_page: Number(meta.per_page ?? this.filters.per_page ?? 10),
                    total: Number(meta.total ?? 0),
                    from: Number(meta.from ?? 0),
                    to: Number(meta.to ?? 0),
                };
                this.filters.page = this.pagination.current_page;
            },

            applyFilters() {
                this.filters.page = 1;
                this.load();
            },

            resetFilters() {
                this.filters = {
                    search: '', has_username: '', has_driver: '', stale: false,
                    period_from: '', period_to: '', sort: 'resolved_at', direction: 'desc',
                    per_page: 10, page: 1,
                };
                this.periodPreset = 'all';
                this.load();
            },

            goToPage(page) {
                page = Number(page);
                if (page < 1 || page > this.pagination.last_page || page === this.pagination.current_page || this.loading) return;
                this.filters.page = page;
                this.load();
            },

            readUrl() {
                const params = new URLSearchParams(window.location.search);
                this.filters.search = params.get('search') || '';
                this.filters.has_username = params.get('has_username') || '';
                this.filters.has_driver = params.get('has_driver') || '';
                this.filters.stale = params.get('stale') === '1';
                this.filters.period_from = params.get('period_from') || '';
                this.filters.period_to = params.get('period_to') || '';
                this.periodPreset = (this.filters.period_from || this.filters.period_to) ? 'custom' : 'all';
                this.filters.sort = params.get('sort') || 'resolved_at';
                this.filters.direction = params.get('direction') === 'asc' ? 'asc' : 'desc';
                this.filters.per_page = Number(params.get('per_page') || 10);
                this.filters.page = Number(params.get('page') || 1);
            },

            syncUrl() {
                const params = this.buildParams();
                const query = params.toString();
                window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : ''));
            },

            visiblePages() {
                const current = Number(this.pagination.current_page);
                const last = Number(this.pagination.last_page);
                if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
                const pages = [1];
                if (current > 4) pages.push('...');
                const start = Math.max(2, current - 1);
                const end = Math.min(last - 1, current + 1);
                for (let p = start; p <= end; p++) pages.push(p);
                if (current < last - 3) pages.push('...');
                pages.push(last);
                return [...new Set(pages)];
            },

            formatNumber(value) {
                return Number(value || 0).toLocaleString();
            },

            formatDate(value) {
                if (!value) return '—';
                const date = new Date(String(value).replace(' ', 'T'));
                if (Number.isNaN(date.getTime())) return value;
                return new Intl.DateTimeFormat(document.documentElement.lang || 'uz-UZ', {
                    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
                }).format(date);
            },
        };
    }
</script>
@endpush
