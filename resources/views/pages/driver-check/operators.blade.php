@extends('layouts.app')

@section('title', __('telegram.operators.title'))

@section('content')
<div
    x-data="operatorsPage()"
    x-init="init()"
    class="px-3 py-4 sm:px-5 sm:py-6 lg:px-8 lg:py-8"
>
    <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-5 sm:gap-6">

        {{-- ================================================================
             Header
        ================================================================= --}}
        <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl
                           bg-indigo-600 text-white shadow-lg shadow-indigo-600/20
                           dark:bg-indigo-500 dark:shadow-indigo-500/20"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="9" cy="8" r="3.25" />
                        <path stroke-linecap="round" d="M3.5 19.5C3.5 16.74 5.96 14.5 9 14.5c1.2 0 2.31.35 3.22.94" />
                        <path stroke-linejoin="round" d="M15.5 21 14 22v-6.5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1h-4.5Z" />
                    </svg>
                </span>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold tracking-tight text-gray-950 sm:text-2xl dark:text-white">
                            {{ __('telegram.operators.title') }}
                        </h1>

                        <span
                            class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px]
                                   font-semibold uppercase tracking-wider text-gray-500
                                   dark:border-gray-800 dark:bg-white/[0.04] dark:text-gray-400"
                        >
                            Telegram
                        </span>
                    </div>

                    <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                        {{ __('telegram.operators.description') }}
                    </p>
                </div>
            </div>

            <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row">
                <button
                    type="button"
                    @click="load()"
                    :disabled="loading"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border
                           border-gray-200 bg-white px-4 text-sm font-medium text-gray-700
                           transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60
                           dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200
                           dark:hover:bg-white/[0.05]"
                >
                    <svg class="h-4 w-4" :class="loading && 'animate-spin'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9M4.582 9H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2M19.419 15H15" />
                    </svg>

                    <span x-text="loading ? translations.loading : translations.refresh"></span>
                </button>

                <button
                    type="button"
                    @click="openCreate()"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-indigo-600
                           px-4 text-sm font-semibold text-white shadow-sm shadow-indigo-600/20
                           transition hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    </svg>
                    {{ __('telegram.operators.create') }}
                </button>
            </div>
        </header>

        {{-- ================================================================
             Stats
        ================================================================= --}}
        <section class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <template x-for="card in statCards()" :key="card.key">
                <div class="rounded-2xl border border-gray-200/80 bg-white p-4 dark:border-gray-800 dark:bg-gray-900/60">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                       x-text="card.label"></p>

                    <p class="mt-1.5 text-2xl font-semibold tracking-tight"
                       :class="card.tone"
                       x-text="formatNumber(card.value)"></p>
                </div>
            </template>
        </section>

        {{-- ================================================================
             Filters
        ================================================================= --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white p-4 sm:p-5 dark:border-gray-800 dark:bg-gray-900/60">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ __('telegram.operators.filters.title') }}
                </h2>

                <button
                    type="button"
                    @click="resetFilters()"
                    class="shrink-0 text-xs font-medium text-gray-500 transition hover:text-gray-900
                           dark:text-gray-400 dark:hover:text-white"
                >
                    {{ __('telegram.operators.filters.reset') }}
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="sm:col-span-2">
                    <label for="operators-search" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">
                        {{ __('telegram.operators.search_placeholder') }}
                    </label>

                    <input
                        id="operators-search"
                        type="search"
                        x-model="filters.search"
                        @input.debounce.400ms="applyFilters()"
                        placeholder="{{ __('telegram.operators.search_placeholder') }}"
                        class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm
                               text-gray-900 placeholder:text-gray-400 focus:border-indigo-500
                               focus:outline-none focus:ring-2 focus:ring-indigo-500/20
                               dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >
                </div>

                <div>
                    <label for="operators-dm" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">
                        {{ __('telegram.operators.filters.dm') }}
                    </label>

                    <select
                        id="operators-dm"
                        x-model="filters.dm_enabled"
                        @change="applyFilters()"
                        class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm
                               text-gray-900 focus:border-indigo-500 focus:outline-none
                               focus:ring-2 focus:ring-indigo-500/20
                               dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >
                        <option value="">{{ __('telegram.operators.filters.dm_all') }}</option>
                        <option value="1">{{ __('telegram.operators.filters.dm_on') }}</option>
                        <option value="0">{{ __('telegram.operators.filters.dm_off') }}</option>
                    </select>
                </div>

                <div>
                    <label for="operators-linked" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">
                        {{ __('telegram.operators.filters.linked') }}
                    </label>

                    <select
                        id="operators-linked"
                        x-model="filters.linked"
                        @change="applyFilters()"
                        class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-sm
                               text-gray-900 focus:border-indigo-500 focus:outline-none
                               focus:ring-2 focus:ring-indigo-500/20
                               dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                    >
                        <option value="">{{ __('telegram.operators.filters.linked_all') }}</option>
                        <option value="1">{{ __('telegram.operators.filters.linked_yes') }}</option>
                        <option value="0">{{ __('telegram.operators.filters.linked_no') }}</option>
                    </select>
                </div>
            </div>
        </section>

        {{-- ================================================================
             Error
        ================================================================= --}}
        <div
            x-show="error"
            x-cloak
            class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3
                   dark:border-red-900/40 dark:bg-red-950/30"
        >
            <p class="text-sm font-medium text-red-700 dark:text-red-300">
                {{ __('telegram.operators.errors.title') }}
            </p>
            <p class="mt-0.5 break-words text-sm text-red-600 dark:text-red-400" x-text="error"></p>
        </div>

        {{-- ================================================================
             Rows

             Two separate renderings of the same data: a table from lg up, a
             card list below it. One layout squeezed into both widths is what
             made the columns collide on a narrow screen.
        ================================================================= --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white dark:border-gray-800 dark:bg-gray-900/60">

            {{-- Table (lg and up) --}}
            <div class="hidden lg:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] table-fixed text-left">
                        <colgroup>
                            <col class="w-[22%]">
                            <col class="w-[18%]">
                            <col class="w-[13%]">
                            <col class="w-[9%]">
                            <col class="w-[9%]">
                            <col class="w-[19%]">
                            <col class="w-[10%]">
                        </colgroup>

                        <thead class="border-b border-gray-200/80 bg-gray-50/80 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-5 py-3 font-medium">{{ __('telegram.operators.table.operator') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('telegram.operators.table.telegram') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('telegram.operators.table.dm') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('telegram.operators.table.drivers') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('telegram.operators.table.checks') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('telegram.operators.table.last_sent') }}</th>
                                <th class="px-5 py-3 text-right font-medium">{{ __('telegram.operators.table.actions') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="row in rows" :key="row.id">
                                <tr class="align-top transition hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                    {{-- Operator --}}
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                                :class="avatarClass(row)"
                                                x-text="initials(row.name)"
                                            ></span>

                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                                   :title="row.name"
                                                   x-text="row.name"></p>
                                                <p class="truncate text-[11px] text-gray-400 dark:text-gray-500"
                                                   :title="row.name_normalized"
                                                   x-text="row.name_normalized"></p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Telegram --}}
                                    <td class="px-4 py-3">
                                        <p class="truncate text-sm"
                                           :class="row.telegram_username
                                               ? 'text-gray-900 dark:text-white'
                                               : 'text-gray-400 dark:text-gray-500'"
                                           x-text="row.telegram_username
                                               ? '@' + row.telegram_username
                                               : translations.table.no_username"></p>

                                        <p class="truncate text-[11px]"
                                           :class="row.telegram_id
                                               ? 'text-gray-500 dark:text-gray-400'
                                               : 'text-gray-400 dark:text-gray-500'"
                                           x-text="row.telegram_id
                                               ? 'ID ' + row.telegram_id
                                               : translations.table.no_id"></p>
                                    </td>

                                    {{-- DM --}}
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium"
                                            :class="dmBadgeClass(row)"
                                            x-text="dmBadgeLabel(row)"
                                        ></span>
                                    </td>

                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300"
                                        x-text="formatNumber(row.drivers_count)"></td>

                                    <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300"
                                        x-text="formatNumber(row.checks_count)"></td>

                                    {{-- Last delivery --}}
                                    <td class="px-4 py-3">
                                        <p class="text-sm text-gray-700 dark:text-gray-300"
                                           x-text="row.dm_last_sent_at
                                               ? formatDate(row.dm_last_sent_at)
                                               : translations.table.never"></p>

                                        <p
                                            x-show="row.dm_last_error"
                                            x-cloak
                                            class="mt-0.5 line-clamp-2 text-[11px] text-red-600 dark:text-red-400"
                                            :title="row.dm_last_error"
                                            x-text="translations.errors.dm_last_error + ': ' + row.dm_last_error"
                                        ></p>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-5 py-3 text-right">
                                        <button
                                            type="button"
                                            @click="openEdit(row)"
                                            class="h-8 rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium
                                                   text-gray-700 transition hover:bg-gray-50
                                                   dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300
                                                   dark:hover:bg-white/[0.04]"
                                        >
                                            {{ __('telegram.operators.table.edit') }}
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Cards (below lg) --}}
            <div class="divide-y divide-gray-100 lg:hidden dark:divide-gray-800">
                <template x-for="row in rows" :key="'card-' + row.id">
                    <article class="flex flex-col gap-3 p-4">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                :class="avatarClass(row)"
                                x-text="initials(row.name)"
                            ></span>

                            <div class="min-w-0 flex-1">
                                <p class="break-words text-sm font-medium text-gray-900 dark:text-white"
                                   x-text="row.name"></p>
                                <p class="break-all text-[11px] text-gray-400 dark:text-gray-500"
                                   x-text="row.name_normalized"></p>
                            </div>

                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-medium"
                                :class="dmBadgeClass(row)"
                                x-text="dmBadgeLabel(row)"
                            ></span>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-[11px]">
                            <div class="min-w-0">
                                <dt class="text-gray-400 dark:text-gray-500">{{ __('telegram.operators.table.telegram') }}</dt>
                                <dd class="break-all text-xs text-gray-700 dark:text-gray-300"
                                    x-text="row.telegram_username
                                        ? '@' + row.telegram_username
                                        : translations.table.no_username"></dd>
                                <dd class="break-all text-[11px] text-gray-500 dark:text-gray-400"
                                    x-text="row.telegram_id
                                        ? 'ID ' + row.telegram_id
                                        : translations.table.no_id"></dd>
                            </div>

                            <div class="min-w-0">
                                <dt class="text-gray-400 dark:text-gray-500">{{ __('telegram.operators.table.last_sent') }}</dt>
                                <dd class="text-xs text-gray-700 dark:text-gray-300"
                                    x-text="row.dm_last_sent_at
                                        ? formatDate(row.dm_last_sent_at)
                                        : translations.table.never"></dd>
                            </div>

                            <div>
                                <dt class="text-gray-400 dark:text-gray-500">{{ __('telegram.operators.table.drivers') }}</dt>
                                <dd class="text-xs tabular-nums text-gray-700 dark:text-gray-300"
                                    x-text="formatNumber(row.drivers_count)"></dd>
                            </div>

                            <div>
                                <dt class="text-gray-400 dark:text-gray-500">{{ __('telegram.operators.table.checks') }}</dt>
                                <dd class="text-xs tabular-nums text-gray-700 dark:text-gray-300"
                                    x-text="formatNumber(row.checks_count)"></dd>
                            </div>
                        </dl>

                        <p
                            x-show="row.dm_last_error"
                            x-cloak
                            class="break-words rounded-lg bg-red-50 px-2.5 py-1.5 text-[11px] text-red-600
                                   dark:bg-red-950/30 dark:text-red-400"
                            x-text="translations.errors.dm_last_error + ': ' + row.dm_last_error"
                        ></p>

                        <button
                            type="button"
                            @click="openEdit(row)"
                            class="h-9 w-full rounded-lg border border-gray-200 bg-white text-xs font-medium
                                   text-gray-700 transition hover:bg-gray-50
                                   dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300
                                   dark:hover:bg-white/[0.04]"
                        >
                            {{ __('telegram.operators.table.edit') }}
                        </button>
                    </article>
                </template>
            </div>

            {{-- Empty --}}
            <div x-show="!loading && rows.length === 0" x-cloak class="px-4 py-12 text-center">
                <p class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ __('telegram.operators.empty.title') }}
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('telegram.operators.empty.description') }}
                </p>
            </div>

            {{-- Pagination --}}
            <div
                x-show="pagination.last_page > 1"
                x-cloak
                class="flex flex-col gap-3 border-t border-gray-200/80 px-4 py-4 sm:flex-row
                       sm:items-center sm:justify-between sm:px-5 dark:border-gray-800"
            >
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    <span>{{ __('telegram.operators.pagination.showing') }}</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300" x-text="pagination.from || 0"></span>
                    <span>{{ __('telegram.operators.pagination.to') }}</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300" x-text="pagination.to || 0"></span>
                    <span>{{ __('telegram.operators.pagination.of') }}</span>
                    <span class="font-medium text-gray-700 dark:text-gray-300" x-text="pagination.total || 0"></span>
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="goToPage(pagination.current_page - 1)"
                        :disabled="pagination.current_page <= 1 || loading"
                        class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium
                               text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed
                               disabled:opacity-40 dark:border-gray-800 dark:bg-gray-950
                               dark:text-gray-300 dark:hover:bg-white/[0.04]"
                    >
                        {{ __('telegram.operators.pagination.previous') }}
                    </button>

                    <button
                        type="button"
                        @click="goToPage(pagination.current_page + 1)"
                        :disabled="pagination.current_page >= pagination.last_page || loading"
                        class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-xs font-medium
                               text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed
                               disabled:opacity-40 dark:border-gray-800 dark:bg-gray-950
                               dark:text-gray-300 dark:hover:bg-white/[0.04]"
                    >
                        {{ __('telegram.operators.pagination.next') }}
                    </button>
                </div>
            </div>
        </section>
    </div>

    {{-- ====================================================================
         Form modal

         Teleported to <body>: inside the page it sits under the layout's
         sidebar/header stacking contexts, which is what made it render
         behind the rest of the page.
    ===================================================================== --}}
    <template x-teleport="body">
        <div
            x-show="formOpen"
            x-cloak
            class="fixed inset-0 z-[9999]"
            role="dialog"
            aria-modal="true"
            @keydown.escape.window="closeForm()"
        >
            <div
                x-show="formOpen"
                x-transition.opacity
                class="absolute inset-0 bg-gray-900/60"
            ></div>

            <div class="absolute inset-0 overflow-y-auto overscroll-contain">
                <div
                    class="flex min-h-full items-end justify-center p-0 sm:items-center sm:p-4"
                    @click.self="closeForm()"
                >
                    <div
                        x-show="formOpen"
                        x-transition
                        class="relative w-full max-w-lg rounded-t-3xl bg-white p-5 shadow-xl
                               sm:rounded-3xl sm:p-6 dark:bg-gray-900"
                    >
                        <div class="mb-5 flex items-start justify-between gap-4">
                            <h2 class="text-lg font-semibold text-gray-950 dark:text-white"
                                x-text="form.id
                                    ? translations.form.edit_title
                                    : translations.form.create_title"></h2>

                            <button
                                type="button"
                                @click="closeForm()"
                                class="-m-1.5 rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100
                                       hover:text-gray-700 dark:hover:bg-white/[0.06] dark:hover:text-gray-200"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                                </svg>
                            </button>
                        </div>

                        <form @submit.prevent="save()" class="flex flex-col gap-4">
                            {{-- Name --}}
                            <div>
                                <label for="operator-name" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    {{ __('telegram.operators.form.name') }}
                                </label>

                                <input
                                    id="operator-name"
                                    type="text"
                                    x-model="form.name"
                                    required
                                    class="h-10 w-full rounded-xl border bg-white px-3 text-sm text-gray-900
                                           focus:outline-none focus:ring-2 focus:ring-indigo-500/20
                                           dark:bg-gray-950 dark:text-white"
                                    :class="fieldError('name') || fieldError('name_normalized')
                                        ? 'border-red-400 dark:border-red-800'
                                        : 'border-gray-200 focus:border-indigo-500 dark:border-gray-800'"
                                >

                                <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                    {{ __('telegram.operators.form.name_hint') }}
                                </p>

                                <p x-show="form.name" x-cloak
                                   class="mt-1 break-all text-[11px] text-gray-400 dark:text-gray-500">
                                    {{ __('telegram.operators.form.name_normalized') }}:
                                    <span class="font-mono" x-text="normalizedPreview()"></span>
                                </p>

                                <p x-show="fieldError('name') || fieldError('name_normalized')" x-cloak
                                   class="mt-1 text-[11px] text-red-600 dark:text-red-400"
                                   x-text="fieldError('name') || fieldError('name_normalized')"></p>
                            </div>

                            {{-- Username --}}
                            <div>
                                <label for="operator-username" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    {{ __('telegram.operators.form.telegram_username') }}
                                </label>

                                <div class="relative">
                                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">&#64;</span>

                                    <input
                                        id="operator-username"
                                        type="text"
                                        x-model="form.telegram_username"
                                        placeholder="username"
                                        autocomplete="off"
                                        class="h-10 w-full rounded-xl border bg-white pl-7 pr-3 text-sm text-gray-900
                                               focus:outline-none focus:ring-2 focus:ring-indigo-500/20
                                               dark:bg-gray-950 dark:text-white"
                                        :class="fieldError('telegram_username')
                                            ? 'border-red-400 dark:border-red-800'
                                            : 'border-gray-200 focus:border-indigo-500 dark:border-gray-800'"
                                    >
                                </div>

                                <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                    {{ __('telegram.operators.form.telegram_username_hint') }}
                                </p>

                                <p x-show="fieldError('telegram_username')" x-cloak
                                   class="mt-1 text-[11px] text-red-600 dark:text-red-400"
                                   x-text="fieldError('telegram_username')"></p>
                            </div>

                            {{-- Telegram id --}}
                            <div>
                                <label for="operator-telegram-id" class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">
                                    {{ __('telegram.operators.form.telegram_id') }}
                                </label>

                                <input
                                    id="operator-telegram-id"
                                    type="number"
                                    min="1"
                                    x-model="form.telegram_id"
                                    placeholder="123456789"
                                    class="h-10 w-full rounded-xl border bg-white px-3 text-sm text-gray-900
                                           focus:outline-none focus:ring-2 focus:ring-indigo-500/20
                                           dark:bg-gray-950 dark:text-white"
                                    :class="fieldError('telegram_id')
                                        ? 'border-red-400 dark:border-red-800'
                                        : 'border-gray-200 focus:border-indigo-500 dark:border-gray-800'"
                                >

                                <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                    {{ __('telegram.operators.form.telegram_id_hint') }}
                                </p>

                                <p x-show="fieldError('telegram_id')" x-cloak
                                   class="mt-1 text-[11px] text-red-600 dark:text-red-400"
                                   x-text="fieldError('telegram_id')"></p>
                            </div>

                            {{-- The only switch: copy the report into the operator's private chat --}}
                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                                <input
                                    type="checkbox"
                                    x-model="form.dm_enabled"
                                    class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600
                                           focus:ring-indigo-500/30 dark:border-gray-700"
                                >
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-gray-900 dark:text-white">
                                        {{ __('telegram.operators.form.dm_enabled') }}
                                    </span>
                                    <span class="block text-[11px] text-gray-400 dark:text-gray-500">
                                        {{ __('telegram.operators.form.dm_enabled_hint') }}
                                    </span>
                                </span>
                            </label>

                            <p x-show="formError" x-cloak
                               class="break-words rounded-xl bg-red-50 px-3 py-2 text-xs text-red-600
                                      dark:bg-red-950/30 dark:text-red-400"
                               x-text="formError"></p>

                            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <button
                                    type="button"
                                    @click="closeForm()"
                                    class="h-10 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium
                                           text-gray-700 transition hover:bg-gray-50 dark:border-gray-800
                                           dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]"
                                >
                                    {{ __('telegram.operators.form.cancel') }}
                                </button>

                                <button
                                    type="submit"
                                    :disabled="saving"
                                    class="h-10 rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white
                                           transition hover:bg-indigo-700 disabled:cursor-not-allowed
                                           disabled:opacity-60 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                                    x-text="saving ? translations.form.saving : translations.form.save"
                                ></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- Toast --}}
    <template x-teleport="body">
        <div
            x-show="toast"
            x-cloak
            x-transition
            class="pointer-events-none fixed bottom-5 left-1/2 z-[10000] -translate-x-1/2 rounded-xl
                   bg-gray-900 px-4 py-2.5 text-sm font-medium text-white shadow-lg
                   dark:bg-white dark:text-gray-900"
            x-text="toast"
        ></div>
    </template>
</div>
@endsection

@push('scripts')
<script>
    function operatorsPage() {
        return {
            translations: @json(__('telegram.operators')),

            endpoints: {
                index: @json(route('api.telegram.operators.index')),
                store: @json(route('api.telegram.operators.store')),
                base: @json(url('/api/telegram/operators')),
            },

            csrf: document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') || '',

            loading: false,
            saving: false,

            error: null,
            formError: null,
            formErrors: {},
            toast: null,
            toastTimer: null,

            rows: [],

            stats: {
                total: 0,
                linked: 0,
                dm_enabled: 0,
                failing: 0,
            },

            pagination: {
                current_page: 1,
                last_page: 1,
                from: 0,
                to: 0,
                total: 0,
            },

            filters: {
                search: '',
                dm_enabled: '',
                linked: '',
                sort: 'name',
                direction: 'asc',
                per_page: 20,
                page: 1,
            },

            formOpen: false,

            form: {
                id: null,
                name: '',
                telegram_username: '',
                telegram_id: '',
                dm_enabled: true,
            },

            init() {
                this.readUrl();
                this.load();

                this.$watch('formOpen', open => this.lockScroll(open));
            },

            /*
             * =====================================================
             * SCROLL LOCK
             *
             * The overlay does its own scrolling, so the page behind it must
             * stay put. html and body are both locked because the layout sets
             * h-full on each, which makes html the scrolling element - locking
             * body alone would do nothing. The scrollbar width is given back as
             * padding so the page underneath does not jump sideways.
             * =====================================================
             */
            lockScroll(locked) {
                const root = document.documentElement;
                const body = document.body;

                if (locked) {
                    const gap = window.innerWidth - root.clientWidth;

                    root.style.overflow = 'hidden';
                    body.style.overflow = 'hidden';

                    if (gap > 0) {
                        body.style.paddingRight = gap + 'px';
                    }

                    return;
                }

                root.style.overflow = '';
                body.style.overflow = '';
                body.style.paddingRight = '';
            },

            /*
             * =====================================================
             * STATS
             * =====================================================
             */
            statCards() {
                return [
                    {
                        key: 'total',
                        label: this.translations.stats.total,
                        value: this.stats.total,
                        tone: 'text-gray-950 dark:text-white',
                    },
                    {
                        key: 'linked',
                        label: this.translations.stats.linked,
                        value: this.stats.linked,
                        tone: 'text-indigo-600 dark:text-indigo-400',
                    },
                    {
                        key: 'dm_enabled',
                        label: this.translations.stats.dm_enabled,
                        value: this.stats.dm_enabled,
                        tone: 'text-blue-600 dark:text-blue-400',
                    },
                    {
                        key: 'failing',
                        label: this.translations.stats.failing,
                        value: this.stats.failing,
                        tone: this.stats.failing > 0
                            ? 'text-red-600 dark:text-red-400'
                            : 'text-gray-950 dark:text-white',
                    },
                ];
            },

            /*
             * A report only reaches an operator when the switch is on AND a
             * username or id is filled in, so the badge reports that whole
             * condition rather than the dm_enabled flag alone.
             */
            dmBadgeLabel(row) {
                if (!row.dm_enabled) {
                    return this.translations.table.dm_off;
                }

                if (!row.has_telegram_peer) {
                    return this.translations.table.dm_unreachable;
                }

                return this.translations.table.dm_on;
            },

            dmBadgeClass(row) {
                if (row.can_receive_dm) {
                    return 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300';
                }

                if (row.dm_enabled && !row.has_telegram_peer) {
                    return 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
                }

                return 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-400';
            },

            avatarClass(row) {
                return row.can_receive_dm
                    ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300'
                    : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400';
            },

            /*
             * =====================================================
             * LOAD
             * =====================================================
             */
            buildParams(withPage = true) {
                const params = new URLSearchParams();

                Object.entries(this.filters).forEach(([key, value]) => {
                    if (key === 'page' && !withPage) {
                        return;
                    }

                    if (value === '' || value === null || value === undefined) {
                        return;
                    }

                    params.set(key, value);
                });

                return params;
            },

            async load() {
                this.loading = true;
                this.error = null;

                const params = this.buildParams();

                try {
                    const response = await fetch(
                        this.endpoints.index + '?' + params.toString(),
                        {
                            method: 'GET',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        },
                    );

                    const json = await this.readResponse(response);

                    this.rows = Array.isArray(json.data) ? json.data : [];

                    this.stats = {
                        total: Number(json.stats?.total ?? 0),
                        linked: Number(json.stats?.linked ?? 0),
                        dm_enabled: Number(json.stats?.dm_enabled ?? 0),
                        failing: Number(json.stats?.failing ?? 0),
                    };

                    this.pagination = {
                        current_page: Number(json.meta?.current_page ?? 1),
                        last_page: Number(json.meta?.last_page ?? 1),
                        from: Number(json.meta?.from ?? 0),
                        to: Number(json.meta?.to ?? 0),
                        total: Number(json.meta?.total ?? 0),
                    };

                    this.syncUrl();
                } catch (e) {
                    this.error = e.message || this.translations.errors.load;
                    this.rows = [];
                } finally {
                    this.loading = false;
                }
            },

            applyFilters() {
                this.filters.page = 1;
                this.load();
            },

            resetFilters() {
                this.filters = {
                    search: '',
                    dm_enabled: '',
                    linked: '',
                    sort: 'name',
                    direction: 'asc',
                    per_page: 20,
                    page: 1,
                };

                this.load();
            },

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

            readUrl() {
                const params = new URLSearchParams(window.location.search);

                this.filters.search = params.get('search') || '';
                this.filters.dm_enabled = params.get('dm_enabled') || '';
                this.filters.linked = params.get('linked') || '';
                this.filters.per_page = Number(params.get('per_page') || 20);
                this.filters.page = Number(params.get('page') || 1);
            },

            syncUrl() {
                const query = this.buildParams().toString();

                window.history.replaceState(
                    {},
                    '',
                    window.location.pathname + (query ? '?' + query : ''),
                );
            },

            /*
             * =====================================================
             * FORM
             * =====================================================
             */
            openCreate() {
                this.form = {
                    id: null,
                    name: '',
                    telegram_username: '',
                    telegram_id: '',
                    dm_enabled: true,
                };

                this.formError = null;
                this.formErrors = {};
                this.formOpen = true;
            },

            openEdit(row) {
                this.form = {
                    id: row.id,
                    name: row.name || '',
                    telegram_username: row.telegram_username || '',
                    telegram_id: row.telegram_id ?? '',
                    dm_enabled: !!row.dm_enabled,
                };

                this.formError = null;
                this.formErrors = {};
                this.formOpen = true;
            },

            closeForm() {
                this.formOpen = false;
                this.saving = false;
            },

            fieldError(field) {
                const messages = this.formErrors[field];

                return Array.isArray(messages) ? messages[0] : null;
            },

            /*
             * Mirrors OperationUser::normalizeName() so the operator can see,
             * before saving, the exact key their entry will be matched on.
             */
            normalizedPreview() {
                return String(this.form.name || '')
                    .trim()
                    .replace(/\s+/g, ' ')
                    .toUpperCase();
            },

            async save() {
                this.saving = true;
                this.formError = null;
                this.formErrors = {};

                const editing = !!this.form.id;

                const url = editing
                    ? this.endpoints.base + '/' + this.form.id
                    : this.endpoints.store;

                try {
                    const response = await fetch(url, {
                        method: editing ? 'PUT' : 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            name: this.form.name,
                            telegram_username: this.form.telegram_username || null,
                            telegram_id: this.form.telegram_id === ''
                                ? null
                                : Number(this.form.telegram_id),
                            dm_enabled: this.form.dm_enabled,
                        }),
                    });

                    if (response.status === 422) {
                        const body = await response.json();

                        this.formErrors = body.errors || {};
                        this.formError = body.message || null;

                        return;
                    }

                    await this.readResponse(response);

                    this.closeForm();

                    this.showToast(
                        editing
                            ? this.translations.messages.updated
                            : this.translations.messages.created,
                    );

                    this.load();
                } catch (e) {
                    this.formError = e.message || this.translations.errors.save;
                } finally {
                    this.saving = false;
                }
            },

            /*
             * =====================================================
             * HELPERS
             * =====================================================
             */
            async readResponse(response) {
                let json = {};

                try {
                    json = await response.json();
                } catch (_) {
                    json = {};
                }

                if (!response.ok) {
                    throw new Error(
                        json.message || ('HTTP ' + response.status),
                    );
                }

                return json;
            },

            showToast(message) {
                this.toast = message;

                clearTimeout(this.toastTimer);

                this.toastTimer = setTimeout(() => {
                    this.toast = null;
                }, 3000);
            },

            formatNumber(value) {
                return Number(value || 0).toLocaleString();
            },

            formatDate(value) {
                if (!value) {
                    return '—';
                }

                const date = new Date(String(value).replace(' ', 'T'));

                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return new Intl.DateTimeFormat(
                    document.documentElement.lang || 'uz-UZ',
                    {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    },
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
