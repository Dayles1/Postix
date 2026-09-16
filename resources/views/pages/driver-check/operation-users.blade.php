@extends('layouts.app')

@section('title', __('telegram.operation_users.title'))

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
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl
                           bg-blue-600 text-white shadow-lg shadow-blue-600/20
                           dark:bg-blue-500 dark:shadow-blue-500/20"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                        />
                        <circle cx="9" cy="7" r="4" />
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 0 0 7.75"
                        />
                    </svg>
                </div>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl dark:text-white">
                            {{ __('telegram.operation_users.title') }}
                        </h1>

                        <span
                            class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px]
                                   font-semibold uppercase tracking-wider text-gray-500 shadow-sm
                                   dark:border-gray-800 dark:bg-white/[0.04] dark:text-gray-400"
                        >
                            Telegram
                        </span>
                    </div>

                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('telegram.operation_users.description') }}
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
                    <svg
                        class="h-4 w-4"
                        :class="{ 'animate-spin': loading }"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9M4.582 9H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2M19.419 15H15"
                        />
                    </svg>

                    <span x-text="loading ? translations.loading : translations.refresh"></span>
                </button>

                {{-- Export --}}
                <div class="relative" x-data="{ exportOpen: false }" @click.outside="exportOpen = false">
                    <button
                        type="button"
                        @click="exportOpen = !exportOpen"
                        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl
                               bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm
                               shadow-emerald-600/20 transition hover:-translate-y-0.5 hover:bg-emerald-700
                               sm:w-auto dark:bg-emerald-500 dark:hover:bg-emerald-400"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
                        </svg>
                        {{ __('telegram.operation_users.export.title') }}
                    </button>

                    <div
                        x-show="exportOpen"
                        x-cloak
                        x-transition
                        class="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded-xl border
                               border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                    >
                        <a
                            :href="exportUrl('operators')"
                            class="block px-4 py-3 text-sm font-medium text-gray-700 transition
                                   hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/[0.05]"
                        >
                            {{ __('telegram.operation_users.export.operators') }}
                        </a>
                        <a
                            :href="exportUrl('details')"
                            class="block border-t border-gray-100 px-4 py-3 text-sm font-medium
                                   text-gray-700 transition hover:bg-gray-50
                                   dark:border-gray-800 dark:text-gray-200 dark:hover:bg-white/[0.05]"
                        >
                            {{ __('telegram.operation_users.export.details') }}
                        </a>
                        <p class="border-t border-gray-100 px-4 py-2 text-[11px] text-gray-400 dark:border-gray-800 dark:text-gray-500">
                            {{ __('telegram.operation_users.export.hint') }}
                        </p>
                    </div>
                </div>
            </div>
        </header>

        {{-- Summary --}}
        <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">

            {{-- Users --}}
            <div
                class="group rounded-2xl border border-gray-200/80 bg-white/90 p-4 shadow-sm
                       transition hover:-translate-y-0.5 hover:shadow-md sm:p-5
                       dark:border-gray-800 dark:bg-gray-900/70"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">
                            {{ __('telegram.operation_users.stats.users') }}
                        </p>

                        <p
                            class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white"
                            x-text="formatNumber(pagination.total)"
                        >
                            0
                        </p>
                    </div>

                    <div
                        class="hidden h-10 w-10 items-center justify-center rounded-xl
                               bg-gray-100 text-gray-600 sm:flex dark:bg-white/[0.06] dark:text-gray-300"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"
                            />
                            <circle cx="9" cy="7" r="4" />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"
                            />
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Drivers --}}
            <div
                class="group rounded-2xl border border-gray-200/80 bg-white/90 p-4 shadow-sm
                       transition hover:-translate-y-0.5 hover:shadow-md sm:p-5
                       dark:border-gray-800 dark:bg-gray-900/70"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">
                            {{ __('telegram.operation_users.stats.drivers') }}
                        </p>

                        <p
                            class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white"
                            x-text="formatNumber(globalStats.drivers)"
                        >
                            0
                        </p>
                    </div>

                    <div
                        class="hidden h-10 w-10 items-center justify-center rounded-xl
                               bg-blue-50 text-blue-600 sm:flex
                               dark:bg-blue-500/10 dark:text-blue-400"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 17h14M7 17V9l3-4h4l3 4v8M9 17v2m6-2v2M8 9h8"
                            />
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Checks --}}
            <div
                class="group rounded-2xl border border-gray-200/80 bg-white/90 p-4 shadow-sm
                       transition hover:-translate-y-0.5 hover:shadow-md sm:p-5
                       dark:border-gray-800 dark:bg-gray-900/70"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">
                            {{ __('telegram.operation_users.stats.checks') }}
                        </p>

                        <p
                            class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white"
                            x-text="formatNumber(globalStats.checks)"
                        >
                            0
                        </p>
                    </div>

                    <div
                        class="hidden h-10 w-10 items-center justify-center rounded-xl
                               bg-indigo-50 text-indigo-600 sm:flex
                               dark:bg-indigo-500/10 dark:text-indigo-400"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" d="M9 12l2 2 4-4" />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 3l7 4v5c0 4.5-3 7.8-7 9-4-1.2-7-4.5-7-9V7l7-4z"
                            />
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Match --}}
            <div
                class="group rounded-2xl border border-gray-200/80 bg-white/90 p-4 shadow-sm
                       transition hover:-translate-y-0.5 hover:shadow-md sm:p-5
                       dark:border-gray-800 dark:bg-gray-900/70"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">
                            {{ __('telegram.operation_users.stats.avg_match') }}
                        </p>

                        <p
                            class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white"
                            x-text="formatPercent(globalStats.match_rate) + '%'"
                        >
                            0%
                        </p>
                    </div>

                    <div
                        class="hidden h-10 w-10 items-center justify-center rounded-xl
                               bg-emerald-50 text-emerald-600 sm:flex
                               dark:bg-emerald-500/10 dark:text-emerald-400"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 12l4 4L19 6"
                            />
                        </svg>
                    </div>
                </div>
            </div>
        </section>

        {{-- Filters --}}
        <section
            class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white/90 shadow-sm
                   dark:border-gray-800 dark:bg-gray-900/70"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200/80 px-4 py-4 sm:px-5
                       lg:flex-row lg:items-center lg:justify-between
                       dark:border-gray-800"
            >
                <div>
                    <h2 class="text-base font-semibold tracking-tight text-gray-950 dark:text-white">
                        {{ __('telegram.operation_users.filters.title') }}
                    </h2>

                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('telegram.operation_users.filters.description') }}
                    </p>
                </div>

                <button
                    type="button"
                    @click="resetFilters()"
                    class="self-start text-sm font-medium text-gray-500 transition
                           hover:text-gray-950 lg:self-auto dark:text-gray-400 dark:hover:text-white"
                >
                    {{ __('telegram.operation_users.filters.reset') }}
                </button>
            </div>

            <div class="p-4 sm:p-5">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">

                    {{-- Search --}}
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.operation_users.filters.search') }}
                        </label>

                        <div class="relative">
                            <span
                                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2
                                       text-gray-400 dark:text-gray-500"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    aria-hidden="true"
                                >
                                    <circle cx="11" cy="11" r="7" />
                                    <path stroke-linecap="round" d="m20 20-4-4" />
                                </svg>
                            </span>

                            <input
                                type="text"
                                x-model="filters.search"
                                @keydown.enter="applyFilters()"
                                placeholder="{{ __('telegram.operation_users.filters.search_placeholder') }}"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 pl-9 pr-3
                                       text-sm text-gray-950 outline-none transition
                                       focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10
                                       dark:border-gray-700 dark:bg-white/[0.03] dark:text-white
                                       dark:focus:border-blue-500 dark:focus:bg-white/[0.05]"
                            >
                        </div>
                    </div>

                    {{-- Telegram ID --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.operation_users.filters.telegram_id') }}
                        </label>

                        <input
                            type="number"
                            x-model="filters.telegram_id"
                            @keydown.enter="applyFilters()"
                            placeholder="{{ __('telegram.operation_users.filters.telegram_id_placeholder') }}"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3
                                   text-sm text-gray-950 outline-none transition
                                   focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10
                                   dark:border-gray-700 dark:bg-white/[0.03] dark:text-white
                                   dark:focus:border-blue-500 dark:focus:bg-white/[0.05]"
                        >
                    </div>

                    {{-- Username --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.operation_users.filters.username') }}
                        </label>

                        <input
                            type="text"
                            x-model="filters.telegram_username"
                            @keydown.enter="applyFilters()"
                            placeholder="{{ __('telegram.operation_users.filters.username_placeholder') }}"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3
                                   text-sm text-gray-950 outline-none transition
                                   focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-500/10
                                   dark:border-gray-700 dark:bg-white/[0.03] dark:text-white
                                   dark:focus:border-blue-500 dark:focus:bg-white/[0.05]"
                        >
                    </div>
                </div>

                {{-- Extra filters --}}
                <div class="mt-4 grid grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 xl:grid-cols-4 dark:border-gray-800">

                    {{-- Status --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.operation_users.filters.status') }}
                        </label>
                        <select
                            x-model="filters.status"
                            @change="applyFilters()"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3
                                   text-sm text-gray-950 outline-none transition focus:border-blue-500
                                   dark:border-gray-700 dark:bg-white/[0.03] dark:text-white"
                        >
                            <option value="">{{ __('telegram.operation_users.filters.status_all') }}</option>
                            <option value="confirmed">{{ __('telegram.operation_users.filters.confirmed') }}</option>
                            <option value="not_confirmed">{{ __('telegram.operation_users.filters.not_confirmed') }}</option>
                            <option value="pending">{{ __('telegram.operation_users.filters.pending') }}</option>
                            <option value="processing">{{ __('telegram.operation_users.filters.processing') }}</option>
                        </select>
                    </div>

                    {{-- Has telegram / driver --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.operation_users.filters.has_driver') }}
                        </label>
                        <select
                            x-model="filters.has_driver"
                            @change="applyFilters()"
                            class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3
                                   text-sm text-gray-950 outline-none transition focus:border-blue-500
                                   dark:border-gray-700 dark:bg-white/[0.03] dark:text-white"
                        >
                            <option value="">{{ __('telegram.operation_users.filters.any') }}</option>
                            <option value="1">{{ __('telegram.operation_users.filters.yes') }}</option>
                            <option value="0">{{ __('telegram.operation_users.filters.no') }}</option>
                        </select>
                    </div>

                    {{-- Score range --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.operation_users.filters.score_range') }}
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <input
                                type="number" min="0" max="100" step="1"
                                x-model="filters.min_match_score"
                                @keydown.enter="applyFilters()"
                                placeholder="{{ __('telegram.operation_users.filters.score_from') }}"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3
                                       text-sm text-gray-950 outline-none transition focus:border-blue-500
                                       dark:border-gray-700 dark:bg-white/[0.03] dark:text-white"
                            >
                            <input
                                type="number" min="0" max="100" step="1"
                                x-model="filters.max_match_score"
                                @keydown.enter="applyFilters()"
                                placeholder="{{ __('telegram.operation_users.filters.score_to') }}"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3
                                       text-sm text-gray-950 outline-none transition focus:border-blue-500
                                       dark:border-gray-700 dark:bg-white/[0.03] dark:text-white"
                            >
                        </div>
                    </div>

                    {{-- Period --}}
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.operation_users.filters.period') }}
                        </label>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <template x-for="preset in periodPresets" :key="preset.value">
                                <button
                                    type="button"
                                    @click="setPeriod(preset.value)"
                                    class="h-9 rounded-lg px-2.5 text-xs font-medium transition"
                                    :class="periodPreset === preset.value
                                        ? 'bg-blue-600 text-white'
                                        : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]'"
                                    x-text="preset.label"
                                ></button>
                            </template>
                        </div>
                    </div>

                    {{-- Custom period range (shown when preset = custom) --}}
                    <div class="sm:col-span-2 xl:col-span-2" x-show="periodPreset === 'custom'" x-cloak>
                        <label class="mb-1.5 block text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ __('telegram.operation_users.filters.period_from') }} — {{ __('telegram.operation_users.filters.period_to') }}
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <input
                                type="date"
                                x-model="filters.period_from"
                                @change="applyFilters()"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3
                                       text-sm text-gray-950 outline-none transition focus:border-blue-500
                                       dark:border-gray-700 dark:bg-white/[0.03] dark:text-white"
                            >
                            <input
                                type="date"
                                x-model="filters.period_to"
                                @change="applyFilters()"
                                class="h-11 w-full rounded-xl border border-gray-200 bg-gray-50/80 px-3
                                       text-sm text-gray-950 outline-none transition focus:border-blue-500
                                       dark:border-gray-700 dark:bg-white/[0.03] dark:text-white"
                            >
                        </div>
                    </div>
                </div>

                <div
                    class="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4
                           sm:flex-row sm:items-center sm:justify-between
                           dark:border-gray-800"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ __('telegram.operation_users.filters.sort') }}
                        </span>

                        <select
                            x-model="filters.sort"
                            @change="applyFilters()"
                            class="h-9 rounded-lg border border-gray-200 bg-gray-50 px-3
                                   text-xs font-medium text-gray-700 outline-none transition
                                   focus:border-blue-500
                                   dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"
                        >
                            <option value="created_at">
                                {{ __('telegram.operation_users.filters.created') }}
                            </option>

                            <option value="updated_at">
                                {{ __('telegram.operation_users.filters.updated') }}
                            </option>

                            <option value="name">
                                {{ __('telegram.operation_users.filters.name') }}
                            </option>

                            <option value="drivers">
                                {{ __('telegram.operation_users.filters.drivers') }}
                            </option>

                            <option value="checks">
                                {{ __('telegram.operation_users.filters.checks') }}
                            </option>

                            <option value="confirmed">
                                {{ __('telegram.operation_users.filters.confirmed') }}
                            </option>

                            <option value="not_confirmed">
                                {{ __('telegram.operation_users.filters.not_confirmed') }}
                            </option>

                            <option value="pending">
                                {{ __('telegram.operation_users.filters.pending') }}
                            </option>

                            <option value="match_rate">
                                {{ __('telegram.operation_users.filters.match_rate') }}
                            </option>

                            <option value="avg_match_score">
                                {{ __('telegram.operation_users.filters.average_score') }}
                            </option>

                            <option value="last_check_at">
                                {{ __('telegram.operation_users.filters.last_check') }}
                            </option>
                        </select>

                        <button
                            type="button"
                            @click="filters.direction = filters.direction === 'asc' ? 'desc' : 'asc'; applyFilters()"
                            :title="filters.direction === 'asc'
                                ? translations.filters.ascending
                                : translations.filters.descending"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg
                                   border border-gray-200 bg-gray-50 text-gray-600 transition
                                   hover:border-gray-300 hover:bg-gray-100
                                   dark:border-gray-800 dark:bg-white/[0.03]
                                   dark:text-gray-300 dark:hover:bg-white/[0.06]"
                        >
                            <svg
                                x-show="filters.direction === 'asc'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" d="M12 19V5" />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m5 12 7-7 7 7"
                                />
                            </svg>

                            <svg
                                x-show="filters.direction === 'desc'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" d="M12 5v14" />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m19 12-7 7-7-7"
                                />
                            </svg>
                        </button>
                    </div>

                    <button
                        type="button"
                        @click="applyFilters()"
                        :disabled="loading"
                        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl
                               bg-blue-600 px-5 text-sm font-semibold text-white
                               shadow-sm shadow-blue-600/20 transition hover:-translate-y-0.5
                               hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60
                               sm:w-auto dark:bg-blue-500 dark:hover:bg-blue-400"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <circle cx="11" cy="11" r="7" />
                            <path stroke-linecap="round" d="m20 20-4-4" />
                        </svg>

                        {{ __('telegram.operation_users.filters.apply') }}
                    </button>
                </div>
            </div>
        </section>

        {{-- Error --}}
        <div
            x-show="error"
            x-cloak
            class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3
                   dark:border-red-900/40 dark:bg-red-950/30"
        >
            <div class="flex items-start gap-3">
                <svg
                    class="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-400"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="12" r="9" />
                    <path stroke-linecap="round" d="M12 8v4" />
                    <path stroke-linecap="round" d="M12 16h.01" />
                </svg>

                <div class="min-w-0">
                    <p class="text-sm font-semibold text-red-800 dark:text-red-300">
                        {{ __('telegram.operation_users.errors.load_failed') }}
                    </p>

                    <p
                        class="mt-1 break-words text-sm text-red-700 dark:text-red-400"
                        x-text="error"
                    ></p>
                </div>
            </div>
        </div>

        {{-- Data --}}
        <section
            class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white/90 shadow-sm
                   dark:border-gray-800 dark:bg-gray-900/70"
        >
            {{-- Section header --}}
            <div
                class="flex flex-col gap-3 border-b border-gray-200/80 px-4 py-4 sm:px-5
                       md:flex-row md:items-center md:justify-between
                       dark:border-gray-800"
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold tracking-tight text-gray-950 dark:text-white">
                            {{ __('telegram.operation_users.stats.users') }}
                        </h2>

                        <span
                            class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px]
                                   font-semibold text-gray-500
                                   dark:bg-white/[0.05] dark:text-gray-400"
                            x-text="formatNumber(pagination.total)"
                        >
                            0
                        </span>
                    </div>

                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('telegram.operation_users.pagination.showing') }}

                        <span x-text="pagination.from || 0"></span>–

                        <span x-text="pagination.to || 0"></span>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="hidden text-xs text-gray-500 sm:inline dark:text-gray-400">
                        Per page
                    </span>

                    <select
                        x-model.number="filters.per_page"
                        @change="applyFilters()"
                        class="h-9 rounded-lg border border-gray-200 bg-gray-50 px-2.5
                               text-sm font-medium text-gray-700 outline-none
                               focus:border-blue-500
                               dark:border-gray-800 dark:bg-white/[0.03]
                               dark:text-gray-300"
                    >
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
                    <tr
                        class="border-b border-gray-100 bg-gray-50/70
                               dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                        <th
                            class="w-[25%] px-4 py-3 text-left text-[10px] font-semibold
                                   uppercase tracking-[0.14em] text-gray-500 sm:px-5
                                   dark:text-gray-400"
                        >
                            {{ __('telegram.operation_users.table.user') }}
                        </th>

                        <th
                            class="w-[18%] px-4 py-3 text-left text-[10px] font-semibold
                                   uppercase tracking-[0.14em] text-gray-500
                                   dark:text-gray-400"
                        >
                            {{ __('telegram.operation_users.table.telegram') }}
                        </th>

                        <th
                            class="w-[10%] px-4 py-3 text-center text-[10px] font-semibold
                                   uppercase tracking-[0.14em] text-gray-500
                                   dark:text-gray-400"
                        >
                            {{ __('telegram.operation_users.table.drivers') }}
                        </th>

                        <th
                            class="hidden w-[10%] px-4 py-3 text-center text-[10px] font-semibold
                                   uppercase tracking-[0.14em] text-gray-500 sm:table-cell
                                   dark:text-gray-400"
                        >
                            {{ __('telegram.operation_users.table.checks') }}
                        </th>

                        <th
                            class="hidden w-[14%] px-4 py-3 text-center text-[10px] font-semibold
                                   uppercase tracking-[0.14em] text-gray-500 md:table-cell
                                   dark:text-gray-400"
                        >
                            {{ __('telegram.operation_users.table.result') }}
                        </th>

                        <th
                            class="w-[12%] px-4 py-3 text-center text-[10px] font-semibold
                                   uppercase tracking-[0.14em] text-gray-500
                                   dark:text-gray-400"
                        >
                            {{ __('telegram.operation_users.table.match') }}
                        </th>

                        <th
                            class="hidden w-[9%] px-4 py-3 text-center text-[10px] font-semibold
                                   uppercase tracking-[0.14em] text-gray-500 lg:table-cell
                                   dark:text-gray-400"
                        >
                            {{ __('telegram.operation_users.table.score') }}
                        </th>

                        <th class="w-[7%] px-4 py-3 sm:px-5"></th>
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">

                    {{-- Loading --}}
                    <template x-if="loading && rows.length === 0">
                        <template x-for="i in 8" :key="'skeleton-' + i">
                            <tr>
                                <td colspan="8" class="px-4 py-4 sm:px-5">
                                    <div
                                        class="h-11 animate-pulse rounded-xl
                                               bg-gray-100 dark:bg-white/[0.04]"
                                    ></div>
                                </td>
                            </tr>
                        </template>
                    </template>

                    {{-- Rows --}}
                    <template x-for="row in rows" :key="row.id">
                        <tr
                            role="link"
                            tabindex="0"
                            @click="openUser(row)"
                            @keydown.enter.prevent="openUser(row)"
                            @keydown.space.prevent="openUser(row)"
                            :aria-label="'Open user ' + (row.name || row.id)"
                            class="group cursor-pointer outline-none transition-colors
                                   hover:bg-blue-50/60 focus:bg-blue-50/60
                                   focus:ring-2 focus:ring-inset focus:ring-blue-500/40
                                   dark:hover:bg-blue-500/[0.045]
                                   dark:focus:bg-blue-500/[0.045]"
                        >

                            {{-- User --}}
                            <td class="px-4 py-4 sm:px-5">
                                <div class="flex min-w-0 items-center gap-3">

                                    {{-- Initials-only avatar --}}
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center
                                               rounded-xl bg-blue-50 text-xs font-bold
                                               tracking-wide text-blue-700
                                               ring-1 ring-inset ring-blue-200
                                               shadow-sm transition
                                               group-hover:scale-105 group-hover:bg-blue-100
                                               dark:bg-blue-500/10 dark:text-blue-300
                                               dark:ring-blue-400/20
                                               dark:group-hover:bg-blue-500/15"
                                        x-text="initials(row.name)"
                                        :title="row.name || ''"
                                    ></div>

                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-sm font-semibold text-gray-950 dark:text-white"
                                            x-text="row.name || '—'"
                                        ></p>

                                        <div
                                            class="mt-1 flex items-center gap-2 text-[11px]
                                                   text-gray-400 dark:text-gray-500"
                                        >
                                            <span>
                                                {{ __('telegram.operation_users.table.id') }}
                                            </span>

                                            <span
                                                class="font-mono"
                                                x-text="row.id ?? '—'"
                                            ></span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Telegram --}}
                            <td class="px-4 py-4">
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-medium text-gray-900 dark:text-gray-100"
                                        x-text="telegramUsername(row.telegram_username)"
                                    ></p>

                                    <p
                                        class="mt-1 truncate font-mono text-[11px]
                                               text-gray-400 dark:text-gray-500"
                                        x-text="row.telegram_id ?? translations.table.no_telegram_id"
                                    ></p>
                                </div>
                            </td>

                            {{-- Drivers --}}
                            <td class="px-4 py-4 text-center">
                                <span
                                    class="inline-flex min-w-9 items-center justify-center
                                           rounded-lg bg-blue-50 px-2.5 py-1 text-sm font-semibold
                                           text-blue-700 ring-1 ring-inset ring-blue-200/70
                                           dark:bg-blue-500/10 dark:text-blue-300
                                           dark:ring-blue-400/15"
                                    x-text="stats(row).drivers"
                                >
                                    0
                                </span>
                            </td>

                            {{-- Checks --}}
                            <td class="hidden px-4 py-4 text-center sm:table-cell">
                                <span
                                    class="inline-flex min-w-9 items-center justify-center
                                           rounded-lg bg-gray-100 px-2.5 py-1 text-sm font-semibold
                                           text-gray-800
                                           dark:bg-white/[0.06] dark:text-gray-200"
                                    x-text="stats(row).checks"
                                >
                                    0
                                </span>
                            </td>

                            {{-- Result --}}
                            <td class="hidden px-4 py-4 md:table-cell">
                                <div class="flex items-center justify-center gap-1.5">

                                    {{-- Confirmed --}}
                                    <span
                                        class="inline-flex min-w-8 items-center justify-center
                                               rounded-md bg-emerald-50 px-2 py-1 text-[11px]
                                               font-semibold text-emerald-700
                                               dark:bg-emerald-500/10 dark:text-emerald-300"
                                        :title="translations.result.confirmed"
                                    >
                                        <span x-text="stats(row).confirmed"></span>
                                    </span>

                                    {{-- Not confirmed --}}
                                    <span
                                        class="inline-flex min-w-8 items-center justify-center
                                               rounded-md bg-red-50 px-2 py-1 text-[11px]
                                               font-semibold text-red-700
                                               dark:bg-red-500/10 dark:text-red-300"
                                        :title="translations.result.not_confirmed"
                                    >
                                        <span x-text="stats(row).not_confirmed"></span>
                                    </span>

                                    {{-- Pending --}}
                                    <span
                                        class="inline-flex min-w-8 items-center justify-center
                                               rounded-md bg-amber-50 px-2 py-1 text-[11px]
                                               font-semibold text-amber-700
                                               dark:bg-amber-500/10 dark:text-amber-300"
                                        :title="translations.result.pending"
                                    >
                                        <span x-text="stats(row).pending"></span>
                                    </span>

                                    {{-- Processing --}}
                                    <span
                                        class="inline-flex min-w-8 items-center justify-center
                                               rounded-md bg-violet-50 px-2 py-1 text-[11px]
                                               font-semibold text-violet-700
                                               dark:bg-violet-500/10 dark:text-violet-300"
                                        :title="translations.result.processing"
                                    >
                                        <span x-text="stats(row).processing"></span>
                                    </span>
                                </div>
                            </td>

                            {{-- Match --}}
                            <td class="px-4 py-4">
                                <div class="mx-auto max-w-[112px]">
                                    <div class="flex items-center justify-center">
                                        <span
                                            class="text-xs font-bold tabular-nums text-gray-900 dark:text-white"
                                            x-text="formatPercent(stats(row).match_rate) + '%'"
                                        >
                                            0%
                                        </span>
                                    </div>

                                    <div
                                        class="mt-1.5 h-1.5 overflow-hidden rounded-full
                                               bg-gray-100 dark:bg-white/[0.08]"
                                    >
                                        <div
                                            class="h-full rounded-full bg-blue-600 transition-all
                                                   dark:bg-blue-500"
                                            :style="{
                                                width: Math.min(
                                                    100,
                                                    Math.max(
                                                        0,
                                                        Number(stats(row).match_rate || 0)
                                                    )
                                                ) + '%'
                                            }"
                                        ></div>
                                    </div>
                                </div>
                            </td>

                            {{-- Score --}}
                            <td class="hidden px-4 py-4 text-center lg:table-cell">
                                <div class="flex flex-col items-center">
                                    <span
                                        class="text-sm font-semibold tabular-nums
                                               text-gray-900 dark:text-white"
                                        x-text="formatScore(stats(row).avg_match_score)"
                                    >
                                        —
                                    </span>

                                    <span
                                        class="mt-0.5 text-[10px] font-medium
                                               text-gray-400 dark:text-gray-500"
                                    >
                                        <span x-text="translations.table.best"></span>
                                        <span
                                            x-text="formatScore(stats(row).best_match_score)"
                                        ></span>
                                    </span>
                                </div>
                            </td>

                            {{-- Open --}}
                            <td class="px-4 py-4 sm:px-5">
                                <div class="flex items-center justify-end gap-2">

                                    <div class="hidden text-right xl:block">
                                        <p
                                            class="text-xs font-medium text-gray-700 dark:text-gray-300"
                                            x-text="
                                                stats(row).last_check_at
                                                    ? formatDate(stats(row).last_check_at, true)
                                                    : translations.dates.never
                                            "
                                        ></p>

                                        <p
                                            class="mt-0.5 text-[10px]
                                                   text-gray-400 dark:text-gray-500"
                                        >
                                            {{ __('telegram.operation_users.table.last_check') }}
                                        </p>
                                    </div>

                                    <div
                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center
                                               rounded-lg border border-gray-200 bg-white
                                               text-gray-400 transition
                                               group-hover:border-blue-200 group-hover:bg-blue-50
                                               group-hover:text-blue-600
                                               dark:border-gray-800 dark:bg-white/[0.03]
                                               dark:text-gray-500
                                               dark:group-hover:border-blue-500/30
                                               dark:group-hover:bg-blue-500/10
                                               dark:group-hover:text-blue-400"
                                    >
                                        <svg
                                            class="h-4 w-4 transition-transform
                                                   group-hover:translate-x-0.5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            aria-hidden="true"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m9 18 6-6-6-6"
                                            />
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
                                    <div
                                        class="flex h-14 w-14 items-center justify-center rounded-2xl
                                               bg-gray-100 text-gray-400
                                               dark:bg-white/[0.06] dark:text-gray-500"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                            aria-hidden="true"
                                        >
                                            <circle cx="11" cy="11" r="7" />
                                            <path stroke-linecap="round" d="m20 20-4-4" />
                                        </svg>
                                    </div>

                                    <h3 class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ __('telegram.operation_users.empty.title') }}
                                    </h3>

                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('telegram.operation_users.empty.description') }}
                                    </p>
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
                        {{ __('telegram.operation_users.pagination.page') }}

                        <span
                            class="font-semibold text-gray-700 dark:text-gray-300"
                            x-text="pagination.current_page"
                        ></span>

                        {{ __('telegram.operation_users.pagination.of') }}

                        <span
                            class="font-semibold text-gray-700 dark:text-gray-300"
                            x-text="pagination.last_page"
                        ></span>
                    </p>

                    <div class="flex items-center justify-between gap-1 sm:justify-end">

                        <button
                            type="button"
                            @click="goToPage(pagination.current_page - 1)"
                            :disabled="pagination.current_page <= 1 || loading"
                            class="h-9 rounded-lg border border-gray-200 bg-white px-3
                                   text-xs font-medium text-gray-700 transition
                                   hover:bg-gray-50 disabled:cursor-not-allowed
                                   disabled:opacity-40 sm:text-sm
                                   dark:border-gray-800 dark:bg-gray-950
                                   dark:text-gray-300 dark:hover:bg-white/[0.04]"
                        >
                            {{ __('telegram.operation_users.pagination.previous') }}
                        </button>

                        <div class="flex items-center gap-1">
                            <template x-for="page in visiblePages()" :key="page">

                                <template x-if="page !== '...'">
                                    <button
                                        type="button"
                                        @click="goToPage(page)"
                                        :disabled="loading"
                                        class="h-9 min-w-9 rounded-lg px-2.5 text-xs
                                               font-medium transition sm:text-sm"
                                        :class="Number(page) === Number(pagination.current_page)
                                            ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950'
                                            : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]'"
                                        x-text="page"
                                    ></button>
                                </template>

                                <template x-if="page === '...'">
                                    <span
                                        class="flex h-9 min-w-7 items-center justify-center
                                               text-xs text-gray-400"
                                    >
                                        ...
                                    </span>
                                </template>

                            </template>
                        </div>

                        <button
                            type="button"
                            @click="goToPage(pagination.current_page + 1)"
                            :disabled="pagination.current_page >= pagination.last_page || loading"
                            class="h-9 rounded-lg border border-gray-200 bg-white px-3
                                   text-xs font-medium text-gray-700 transition
                                   hover:bg-gray-50 disabled:cursor-not-allowed
                                   disabled:opacity-40 sm:text-sm
                                   dark:border-gray-800 dark:bg-gray-950
                                   dark:text-gray-300 dark:hover:bg-white/[0.04]"
                        >
                            {{ __('telegram.operation_users.pagination.next') }}
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

            translations: @json(__('telegram.operation_users')),

            loading: false,

            error: null,

            rows: [],

            /*
             * =====================================================
             * GLOBAL STATS
             * =====================================================
             *
             * Backenddan keladi.
             *
             * Bu current page stats emas.
             * Filterlangan barcha dataset statistikasi.
             */
            globalStats: {
                drivers: 0,
                checks: 0,
                confirmed: 0,
                not_confirmed: 0,
                pending: 0,
                processing: 0,
                match_rate: 0,
                avg_match_score: null,
                best_match_score: null,
            },

            filters: {
                search: '',

                telegram_id: '',

                telegram_username: '',

                status: '',

                has_driver: '',

                min_match_score: '',

                max_match_score: '',

                period_from: '',

                period_to: '',

                sort: 'created_at',

                direction: 'desc',

                per_page: 10,

                page: 1,
            },

            periodPreset: 'all',

            periodPresets: [
                { value: 'all', label: @json(__('telegram.operation_users.filters.period_all')) },
                { value: 'today', label: @json(__('telegram.operation_users.filters.period_today')) },
                { value: 'week', label: @json(__('telegram.operation_users.filters.period_week')) },
                { value: 'month', label: @json(__('telegram.operation_users.filters.period_month')) },
                { value: 'custom', label: @json(__('telegram.operation_users.filters.period_custom')) },
            ],

            exportUrls: {
                operators: @json(route('driver-check.export.operators')),
                details: @json(route('driver-check.export.details')),
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

            /*
             * =====================================================
             * OPEN USER
             * =====================================================
             */
            openUser(row) {
                if (!row?.id) {
                    return;
                }

                window.location.href =
                    `${this.detailsBaseUrl}/${encodeURIComponent(row.id)}`;
            },

            /*
             * =====================================================
             * LOAD
             * =====================================================
             */
            /*
             * =====================================================
             * PERIOD PRESETS
             * =====================================================
             */
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
                    const from = new Date(today);
                    from.setDate(from.getDate() - 6);
                    this.filters.period_from = iso(from);
                    this.filters.period_to = iso(today);
                } else if (preset === 'month') {
                    const from = new Date(today);
                    from.setDate(from.getDate() - 29);
                    this.filters.period_from = iso(from);
                    this.filters.period_to = iso(today);
                } else {
                    // 'custom' -- leave whatever the user typed, just switch mode.
                    return;
                }

                this.applyFilters();
            },

            /*
             * =====================================================
             * BUILD QUERY PARAMS
             * =====================================================
             *
             * Shared by load(), syncUrl() and exportUrl() so the three
             * never drift apart on which filters are sent.
             */
            buildParams(includePage = true) {
                const params = new URLSearchParams();

                const stringFields = [
                    'search', 'telegram_id', 'telegram_username',
                    'status', 'has_driver',
                    'min_match_score', 'max_match_score',
                    'period_from', 'period_to',
                ];

                stringFields.forEach((field) => {
                    const value = this.filters[field];

                    if (value !== '' && value !== null && value !== undefined) {
                        params.set(field, value);
                    }
                });

                params.set('sort', this.filters.sort);
                params.set('direction', this.filters.direction);
                params.set('per_page', this.filters.per_page);

                if (includePage) {
                    params.set('page', this.filters.page);
                }

                return params;
            },

            exportUrl(kind) {
                const params = this.buildParams(false);

                // The export endpoint uses from/to (it also controls the
                // export file name), the list API uses period_from/period_to.
                if (params.has('period_from')) {
                    params.set('from', params.get('period_from'));
                    params.delete('period_from');
                }
                if (params.has('period_to')) {
                    params.set('to', params.get('period_to'));
                    params.delete('period_to');
                }

                return this.exportUrls[kind] + '?' + params.toString();
            },

            async load() {
                this.loading = true;

                this.error = null;

                const params = this.buildParams();

                try {
                    const response = await fetch(
                        @json(route('api.telegram.operation-users'))
                        + '?'
                        + params.toString(),
                        {
                            method: 'GET',

                            headers: {
                                Accept: 'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest',
                            },

                            credentials: 'same-origin',
                        }
                    );

                    if (!response.ok) {
                        let message =
                            `HTTP ${response.status}`;

                        try {
                            const body =
                                await response.json();

                            if (body.message) {
                                message =
                                    body.message;
                            }
                        } catch (_) {
                        }

                        throw new Error(
                            message,
                        );
                    }

                    const json =
                        await response.json();

                    /*
                     * =================================================
                     * CURRENT PAGE
                     * =================================================
                     */
                    this.rows = Array.isArray(
                        json.data,
                    )
                        ? json.data
                        : [];

                    /*
                     * =================================================
                     * GLOBAL STATS
                     * =================================================
                     */
                    this.globalStats = {
                        drivers: Number(
                            json.stats?.drivers ?? 0,
                        ),

                        checks: Number(
                            json.stats?.checks ?? 0,
                        ),

                        confirmed: Number(
                            json.stats?.confirmed ?? 0,
                        ),

                        not_confirmed: Number(
                            json.stats?.not_confirmed ?? 0,
                        ),

                        pending: Number(
                            json.stats?.pending ?? 0,
                        ),

                        processing: Number(
                            json.stats?.processing ?? 0,
                        ),

                        match_rate: Number(
                            json.stats?.match_rate ?? 0,
                        ),

                        avg_match_score:
                            json.stats?.avg_match_score
                            ?? null,

                        best_match_score:
                            json.stats?.best_match_score
                            ?? null,
                    };

                    this.setPagination(
                        json,
                    );

                    this.syncUrl();

                } catch (error) {
                    console.error(
                        error,
                    );

                    this.rows = [];

                    this.globalStats = {
                        drivers: 0,
                        checks: 0,
                        confirmed: 0,
                        not_confirmed: 0,
                        pending: 0,
                        processing: 0,
                        match_rate: 0,
                        avg_match_score: null,
                        best_match_score: null,
                    };

                    this.error =
                        error?.message
                        || this.translations.errors.unknown;

                } finally {
                    this.loading = false;
                }
            },

            /*
             * =====================================================
             * ROW STATS
             * =====================================================
             *
             * Bu faqat table ichidagi rowlar uchun.
             */
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

            /*
             * =====================================================
             * PAGINATION
             * =====================================================
             */
            setPagination(json) {
                const meta =
                    json.meta || {};

                this.pagination = {
                    current_page: Number(
                        meta.current_page
                        ?? json.current_page
                        ?? this.filters.page
                        ?? 1,
                    ),

                    last_page: Number(
                        meta.last_page
                        ?? json.last_page
                        ?? 1,
                    ),

                    per_page: Number(
                        meta.per_page
                        ?? json.per_page
                        ?? this.filters.per_page
                        ?? 10,
                    ),

                    total: Number(
                        meta.total
                        ?? json.total
                        ?? 0,
                    ),

                    from: Number(
                        meta.from
                        ?? json.from
                        ?? 0,
                    ),

                    to: Number(
                        meta.to
                        ?? json.to
                        ?? 0,
                    ),
                };

                this.filters.page =
                    this.pagination.current_page;
            },

            /*
             * =====================================================
             * APPLY FILTERS
             * =====================================================
             */
            applyFilters() {
                this.filters.page = 1;

                this.load();
            },

            /*
             * =====================================================
             * RESET FILTERS
             * =====================================================
             */
            resetFilters() {
                this.filters = {
                    search: '',
                    telegram_id: '',
                    telegram_username: '',
                    status: '',
                    has_driver: '',
                    min_match_score: '',
                    max_match_score: '',
                    period_from: '',
                    period_to: '',
                    sort: 'created_at',
                    direction: 'desc',
                    per_page: 10,
                    page: 1,
                };

                this.periodPreset = 'all';

                this.load();
            },

            /*
             * =====================================================
             * PAGE
             * =====================================================
             */
            goToPage(page) {
                page = Number(page);

                if (
                    page < 1
                    || page > this.pagination.last_page
                    || page === this.pagination.current_page
                    || this.loading
                ) {
                    return;
                }

                this.filters.page = page;

                this.load();
            },

            /*
             * =====================================================
             * READ URL
             * =====================================================
             */
            readUrl() {
                const params =
                    new URLSearchParams(
                        window.location.search,
                    );

                this.filters.search = params.get('search') || '';
                this.filters.telegram_id = params.get('telegram_id') || '';
                this.filters.telegram_username = params.get('telegram_username') || '';
                this.filters.status = params.get('status') || '';
                this.filters.has_driver = params.get('has_driver') || '';
                this.filters.min_match_score = params.get('min_match_score') || '';
                this.filters.max_match_score = params.get('max_match_score') || '';
                this.filters.period_from = params.get('period_from') || '';
                this.filters.period_to = params.get('period_to') || '';

                this.periodPreset = (this.filters.period_from || this.filters.period_to)
                    ? 'custom'
                    : 'all';

                this.filters.sort =
                    params.get('sort')
                    || 'created_at';

                this.filters.direction =
                    params.get('direction') === 'asc'
                        ? 'asc'
                        : 'desc';

                this.filters.per_page =
                    Number(
                        params.get('per_page')
                        || 10,
                    );

                this.filters.page =
                    Number(
                        params.get('page')
                        || 1,
                    );
            },

            /*
             * =====================================================
             * SYNC URL
             * =====================================================
             */
            syncUrl() {
                const params = this.buildParams();

                const query =
                    params.toString();

                window.history.replaceState(
                    {},
                    '',
                    window.location.pathname
                    + (
                        query
                            ? '?' + query
                            : ''
                    ),
                );
            },

            /*
             * =====================================================
             * VISIBLE PAGES
             * =====================================================
             */
            visiblePages() {
                const current =
                    Number(
                        this.pagination.current_page,
                    );

                const last =
                    Number(
                        this.pagination.last_page,
                    );

                if (last <= 7) {
                    return Array.from(
                        {
                            length: last,
                        },
                        (_, index) =>
                            index + 1,
                    );
                }

                const pages = [1];

                if (current > 4) {
                    pages.push('...');
                }

                const start =
                    Math.max(
                        2,
                        current - 1,
                    );

                const end =
                    Math.min(
                        last - 1,
                        current + 1,
                    );

                for (
                    let page = start;
                    page <= end;
                    page++
                ) {
                    pages.push(page);
                }

                if (current < last - 3) {
                    pages.push('...');
                }

                pages.push(last);

                return [
                    ...new Set(
                        pages,
                    ),
                ];
            },

            /*
             * =====================================================
             * TELEGRAM USERNAME
             * =====================================================
             */
            telegramUsername(username) {
                if (!username) {
                    return this.translations
                        .table
                        .no_username;
                }

                return '@'
                    + String(username)
                        .replace(/^@/, '');
            },

            /*
             * =====================================================
             * NUMBER
             * =====================================================
             */
            formatNumber(value) {
                return Number(
                    value || 0,
                ).toLocaleString();
            },

            /*
             * =====================================================
             * PERCENT
             * =====================================================
             */
            formatPercent(value) {
                if (
                    value === null
                    || value === undefined
                    || value === ''
                ) {
                    return '0.0';
                }

                return Number(
                    value,
                ).toFixed(1);
            },

            /*
             * =====================================================
             * SCORE
             * =====================================================
             */
            formatScore(value) {
                if (
                    value === null
                    || value === undefined
                    || value === ''
                ) {
                    return '—';
                }

                return Number(
                    value,
                ).toFixed(1);
            },

            /*
             * =====================================================
             * DATE
             * =====================================================
             */
            formatDate(
                value,
                withTime = false,
            ) {
                if (!value) {
                    return '—';
                }

                const normalized =
                    String(value)
                        .replace(
                            ' ',
                            'T',
                        );

                const date =
                    new Date(
                        normalized,
                    );

                if (
                    Number.isNaN(
                        date.getTime(),
                    )
                ) {
                    return value;
                }

                return new Intl.DateTimeFormat(
                    document.documentElement.lang
                    || 'uz-UZ',

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
                        },
                ).format(
                    date,
                );
            },

            /*
             * =====================================================
             * INITIALS
             * =====================================================
             */
            initials(name) {
                if (!name) {
                    return '?';
                }

                return String(name)
                    .trim()
                    .split(/\s+/)
                    .slice(0, 2)
                    .map(
                        part =>
                            part
                                .charAt(0)
                                .toUpperCase(),
                    )
                    .join('');
            },
        };
    }
</script>
@endpush