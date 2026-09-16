@extends('layouts.app')

@section('title', __('telegram.operation_user.title'))

@section('content')

    <div
        x-data="operationUserPage({
            operationUserId: @js($operationUser->id),

            operationUserUrl: @js(
                route(
                    'api.telegram.operation-users.show',
                    $operationUser->id
                )
            ),

            driversUrl: @js(
                route(
                    'api.telegram.operation-users.drivers',
                    $operationUser->id
                )
            ),

            translations: @js(__('telegram.operation_user')),
        })"
        x-init="init()"
        class="min-h-[calc(100vh-5rem)] px-4 pb-28 pt-5 sm:px-6 sm:pb-32 sm:pt-6 lg:px-8 lg:pt-8"
    >

        <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-5">

            {{-- ==========================================================
                BACK
            =========================================================== --}}
            <div>
                <a
                    href="{{ route('driver-check.operation-users') }}"
                    class="inline-flex items-center gap-2 text-sm font-medium
                           text-gray-500 transition hover:text-gray-900
                           dark:text-gray-400 dark:hover:text-white"
                >
                    <svg
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>

                    {{ __('telegram.operation_user.back') }}
                </a>
            </div>


            {{-- ==========================================================
                OPERATOR
            =========================================================== --}}
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm
                       sm:p-6 dark:border-gray-800 dark:bg-gray-900"
            >

                {{-- Loading --}}
                <template x-if="operatorLoading && !operator">

                    <div class="animate-pulse">

                        <div class="flex items-start gap-4">

                            <div
                                class="h-14 w-14 rounded-2xl bg-gray-100
                                       dark:bg-white/[0.06]"
                            ></div>

                            <div class="flex-1">

                                <div
                                    class="h-5 w-64 rounded bg-gray-100
                                           dark:bg-white/[0.06]"
                                ></div>

                                <div
                                    class="mt-2 h-4 w-40 rounded bg-gray-100
                                           dark:bg-white/[0.06]"
                                ></div>

                            </div>

                        </div>


                        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-5">

                            <template x-for="i in 5" :key="i">

                                <div
                                    class="h-20 rounded-xl bg-gray-100
                                           dark:bg-white/[0.06]"
                                ></div>

                            </template>

                        </div>

                    </div>

                </template>


                {{-- Operator --}}
                <template x-if="!operatorLoading && operator">

                    <div>

                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >

                            <div class="flex min-w-0 items-center gap-4">

                                {{-- Avatar --}}
                                <div
                                    class="flex h-14 w-14 shrink-0 items-center justify-center
                                           rounded-2xl bg-blue-50 text-sm font-semibold
                                           tracking-wide text-blue-700
                                           ring-1 ring-inset ring-blue-200
                                           dark:bg-blue-500/10 dark:text-blue-300
                                           dark:ring-blue-400/20"
                                    x-text="initials(operator.name)"
                                ></div>


                                <div class="min-w-0">

                                    <h1
                                        class="break-words text-lg font-semibold
                                               text-gray-900 sm:text-xl
                                               dark:text-white"
                                        x-text="operator.name || '—'"
                                    ></h1>


                                    <div
                                        class="mt-1 flex flex-wrap items-center
                                               gap-x-3 gap-y-1 text-xs
                                               text-gray-500 dark:text-gray-400"
                                    >

                                        <span>
                                            {{ __('telegram.operation_user.id') }}:

                                            <span
                                                class="font-medium text-gray-700
                                                       dark:text-gray-300"
                                                x-text="operator.id ?? '—'"
                                            ></span>
                                        </span>


                                        <span class="hidden sm:inline">•</span>


                                        <span
                                            x-text="
                                                operator.telegram_username
                                                    ? '@' + String(operator.telegram_username).replace(/^@/, '')
                                                    : translations.no_username
                                            "
                                        ></span>

                                    </div>

                                </div>

                            </div>


                            {{-- Refresh --}}
                            <button
                                type="button"
                                @click="refreshAll()"
                                :disabled="operatorLoading || driversLoading"
                                class="inline-flex h-10 w-full items-center justify-center
                                       gap-2 rounded-xl border border-gray-200 bg-white
                                       px-4 text-sm font-medium text-gray-700 transition
                                       hover:bg-gray-50 disabled:cursor-not-allowed
                                       disabled:opacity-60 sm:w-auto
                                       dark:border-gray-800 dark:bg-gray-950
                                       dark:text-gray-300 dark:hover:bg-white/[0.04]"
                            >

                                <svg
                                    class="h-4 w-4"
                                    :class="{
                                        'animate-spin':
                                            operatorLoading || driversLoading
                                    }"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0
                                           004.582 9M4.582 9H9m11 11v-5h-.581
                                           a8.003 8.003 0 01-15.357-2M19.419 15H15"
                                    />
                                </svg>

                                <span
                                    x-text="
                                        operatorLoading || driversLoading
                                            ? translations.loading
                                            : translations.refresh
                                    "
                                ></span>

                            </button>

                        </div>


                        {{-- Stats --}}
                        <div
                            class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4 xl:grid-cols-5"
                        >

                            {{-- Drivers --}}
                            <div
                                class="rounded-xl border border-gray-100 bg-gray-50 p-4
                                       dark:border-gray-800 dark:bg-white/[0.03]"
                            >
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    {{ __('telegram.operation_user.stats.drivers') }}
                                </p>

                                <p
                                    class="mt-2 text-xl font-semibold text-gray-900 dark:text-white"
                                    x-text="
                                        number(
                                            operator.drivers_count
                                            ?? operator.stats?.drivers
                                            ?? 0
                                        )
                                    "
                                ></p>
                            </div>


                            {{-- Checks --}}
                            <div
                                class="rounded-xl border border-gray-100 bg-gray-50 p-4
                                       dark:border-gray-800 dark:bg-white/[0.03]"
                            >
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                    {{ __('telegram.operation_user.stats.checks') }}
                                </p>

                                <p
                                    class="mt-2 text-xl font-semibold text-gray-900 dark:text-white"
                                    x-text="
                                        number(
                                            operator.checks_count
                                            ?? operator.stats?.checks
                                            ?? 0
                                        )
                                    "
                                ></p>
                            </div>


                            {{-- Confirmed --}}
                            <div
                                class="rounded-xl border border-green-100 bg-green-50 p-4
                                       dark:border-green-900/30 dark:bg-green-500/10"
                            >
                                <p class="text-xs font-medium text-green-700 dark:text-green-400">
                                    {{ __('telegram.operation_user.stats.confirmed') }}
                                </p>

                                <p
                                    class="mt-2 text-xl font-semibold
                                           text-green-700 dark:text-green-400"
                                    x-text="number(operator.stats?.confirmed ?? 0)"
                                ></p>
                            </div>


                            {{-- Not confirmed --}}
                            <div
                                class="rounded-xl border border-red-100 bg-red-50 p-4
                                       dark:border-red-900/30 dark:bg-red-500/10"
                            >
                                <p class="text-xs font-medium text-red-700 dark:text-red-400">
                                    {{ __('telegram.operation_user.stats.not_confirmed') }}
                                </p>

                                <p
                                    class="mt-2 text-xl font-semibold
                                           text-red-700 dark:text-red-400"
                                    x-text="number(operator.stats?.not_confirmed ?? 0)"
                                ></p>
                            </div>


                            {{-- Match --}}
                            <div
                                class="col-span-2 rounded-xl border border-gray-100
                                       bg-gray-50 p-4 sm:col-span-1
                                       dark:border-gray-800 dark:bg-white/[0.03]"
                            >

                                <div class="flex items-center justify-between gap-2">

                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                        {{ __('telegram.operation_user.stats.match_rate') }}
                                    </p>

                                    <span
                                        class="text-sm font-semibold
                                               text-gray-900 dark:text-white"
                                        x-text="
                                            percent(operator.stats?.match_rate) + '%'
                                        "
                                    ></span>

                                </div>


                                <div
                                    class="mt-3 h-1.5 overflow-hidden rounded-full
                                           bg-gray-200 dark:bg-white/[0.08]"
                                >

                                    <div
                                        class="h-full rounded-full bg-blue-600
                                               transition-all dark:bg-blue-500"
                                        :style="{
                                            width:
                                                Math.min(
                                                    100,
                                                    Math.max(
                                                        0,
                                                        Number(
                                                            operator.stats?.match_rate || 0
                                                        )
                                                    )
                                                ) + '%'
                                        }"
                                    ></div>

                                </div>

                            </div>

                        </div>

                    </div>

                </template>


                {{-- Operator Error --}}
                <template x-if="operatorError">

                    <div
                        class="rounded-xl border border-red-200 bg-red-50 p-4
                               dark:border-red-900/40 dark:bg-red-950/30"
                    >

                        <p
                            class="text-sm font-medium text-red-800
                                   dark:text-red-300"
                        >
                            {{ __('telegram.operation_user.errors.load_operator') }}
                        </p>

                        <p
                            class="mt-1 text-sm text-red-700 dark:text-red-400"
                            x-text="operatorError"
                        ></p>

                    </div>

                </template>

            </div>


            {{-- ==========================================================
                DRIVERS
            =========================================================== --}}
            <div
                class="overflow-hidden rounded-2xl border border-gray-200
                       bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >

                {{-- ======================================================
                    HEADER
                ======================================================= --}}
                <div
                    class="border-b border-gray-200 px-4 py-4 sm:px-5
                           dark:border-gray-800"
                >

                    <div
                        class="flex flex-col gap-4"
                    >

                        {{-- Title --}}
                        <div>

                            <h2
                                class="text-sm font-semibold text-gray-900
                                       sm:text-base dark:text-white"
                            >
                                {{ __('telegram.operation_user.drivers.title') }}
                            </h2>

                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">

                                <span x-text="driversMeta.from || 0"></span>
                                –
                                <span x-text="driversMeta.to || 0"></span>

                                {{ __('telegram.operation_user.pagination.of') }}

                                <span x-text="number(driversMeta.total)"></span>

                            </p>

                        </div>


                        {{-- =================================================
                            FILTERS
                        ================================================== --}}
                        <div
                            class="grid grid-cols-1 gap-3 xl:grid-cols-[auto_1fr_auto]
                                   xl:items-center"
                        >

                            {{-- Preset filters --}}
                            <div
                                class="flex flex-wrap items-center gap-1.5"
                            >

                                {{-- All --}}
                                <button
                                    type="button"
                                    @click="setDateFilter('all')"
                                    :disabled="driversLoading"
                                    class="h-9 rounded-lg px-3 text-xs font-medium
                                           transition disabled:opacity-50 sm:text-sm"
                                    :class="
                                        dateFilter === 'all'
                                            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                                            : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]'
                                    "
                                >
                                    {{ __('telegram.operation_user.filters.all') }}
                                </button>


                                {{-- Last Week --}}
                                <button
                                    type="button"
                                    @click="setDateFilter('last_week')"
                                    :disabled="driversLoading"
                                    class="h-9 rounded-lg px-3 text-xs font-medium
                                           transition disabled:opacity-50 sm:text-sm"
                                    :class="
                                        dateFilter === 'last_week'
                                            ? 'bg-blue-600 text-white'
                                            : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]'
                                    "
                                >
                                    {{ __('telegram.operation_user.filters.last_week') }}
                                </button>


                                {{-- Last Month --}}
                                <button
                                    type="button"
                                    @click="setDateFilter('last_month')"
                                    :disabled="driversLoading"
                                    class="h-9 rounded-lg px-3 text-xs font-medium
                                           transition disabled:opacity-50 sm:text-sm"
                                    :class="
                                        dateFilter === 'last_month'
                                            ? 'bg-blue-600 text-white'
                                            : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-white/[0.04]'
                                    "
                                >
                                    {{ __('telegram.operation_user.filters.last_month') }}
                                </button>

                            </div>


                            {{-- Custom date range --}}
                            <div
                                class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto_1fr]"
                            >

                                {{-- From --}}
                                <label class="group relative block">

                                    <span
                                        class="mb-1.5 block text-[11px] font-medium
                                               text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __('telegram.operation_user.filters.from') ?? 'From' }}
                                    </span>

                                    <div class="relative">

                                        <svg
                                            class="pointer-events-none absolute left-3 top-1/2 z-10
                                                   h-4 w-4 -translate-y-1/2
                                                   text-gray-400"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <rect
                                                x="3"
                                                y="4"
                                                width="18"
                                                height="18"
                                                rx="2"
                                            />
                                            <line
                                                x1="16"
                                                y1="2"
                                                x2="16"
                                                y2="6"
                                            />
                                            <line
                                                x1="8"
                                                y1="2"
                                                x2="8"
                                                y2="6"
                                            />
                                            <line
                                                x1="3"
                                                y1="10"
                                                x2="21"
                                                y2="10"
                                            />
                                        </svg>

                                        <input
                                            type="date"
                                            x-model="fromDate"
                                            @change="applyCustomDateFilter()"
                                            :disabled="driversLoading"
                                            class="h-10 w-full rounded-xl border border-gray-200
                                                   bg-white pl-10 pr-3 text-sm text-gray-700
                                                   outline-none transition
                                                   focus:border-blue-500 focus:ring-2
                                                   focus:ring-blue-500/10
                                                   disabled:cursor-not-allowed
                                                   disabled:opacity-50
                                                   dark:border-gray-800
                                                   dark:bg-gray-950
                                                   dark:text-gray-200"
                                        />

                                    </div>

                                </label>


                                {{-- Arrow --}}
                                <div
                                    class="hidden items-end justify-center pb-2 sm:flex"
                                >
                                    <svg
                                        class="h-4 w-4 text-gray-400"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M5 12h14m-6-6 6 6-6 6"
                                        />
                                    </svg>
                                </div>


                                {{-- To --}}
                                <label class="group relative block">

                                    <span
                                        class="mb-1.5 block text-[11px] font-medium
                                               text-gray-500 dark:text-gray-400"
                                    >
                                        {{ __('telegram.operation_user.filters.to') ?? 'To' }}
                                    </span>

                                    <div class="relative">

                                        <svg
                                            class="pointer-events-none absolute left-3 top-1/2 z-10
                                                   h-4 w-4 -translate-y-1/2
                                                   text-gray-400"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <rect
                                                x="3"
                                                y="4"
                                                width="18"
                                                height="18"
                                                rx="2"
                                            />
                                            <line
                                                x1="16"
                                                y1="2"
                                                x2="16"
                                                y2="6"
                                            />
                                            <line
                                                x1="8"
                                                y1="2"
                                                x2="8"
                                                y2="6"
                                            />
                                            <line
                                                x1="3"
                                                y1="10"
                                                x2="21"
                                                y2="10"
                                            />
                                        </svg>

                                        <input
                                            type="date"
                                            x-model="toDate"
                                            @change="applyCustomDateFilter()"
                                            :disabled="driversLoading"
                                            class="h-10 w-full rounded-xl border border-gray-200
                                                   bg-white pl-10 pr-3 text-sm text-gray-700
                                                   outline-none transition
                                                   focus:border-blue-500 focus:ring-2
                                                   focus:ring-blue-500/10
                                                   disabled:cursor-not-allowed
                                                   disabled:opacity-50
                                                   dark:border-gray-800
                                                   dark:bg-gray-950
                                                   dark:text-gray-200"
                                        />

                                    </div>

                                </label>

                            </div>


                            {{-- Right controls --}}
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-end xl:justify-end"
                            >

                                {{-- Clear --}}
                                <button
                                    type="button"
                                    x-show="fromDate || toDate"
                                    x-cloak
                                    @click="clearDateFilter()"
                                    :disabled="driversLoading"
                                    class="h-10 rounded-xl border border-gray-200
                                           bg-white px-3 text-xs font-medium
                                           text-gray-600 transition hover:bg-gray-50
                                           disabled:cursor-not-allowed
                                           disabled:opacity-50 sm:text-sm
                                           dark:border-gray-800 dark:bg-gray-950
                                           dark:text-gray-300
                                           dark:hover:bg-white/[0.04]"
                                >
                                    {{ __('telegram.operation_user.filters.clear') }}
                                </button>


                                {{-- Per page --}}
                                <div class="flex items-center gap-2">

                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ __('telegram.operation_user.drivers.per_page') }}
                                    </span>

                                    <select
                                        x-model.number="perPage"
                                        @change="changePerPage()"
                                        class="h-10 rounded-xl border border-gray-200
                                               bg-gray-50 px-2.5 text-sm text-gray-700
                                               outline-none focus:border-blue-500
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

                        </div>


                        {{-- Active filter --}}
                        <div
                            x-show="hasDateFilter()"
                            x-cloak
                            class="flex items-center gap-2"
                        >

                            <span
                                class="inline-flex max-w-full items-center gap-1.5 rounded-lg
                                       bg-blue-50 px-2.5 py-1.5 text-xs font-medium
                                       text-blue-700
                                       dark:bg-blue-500/10 dark:text-blue-300"
                            >

                                <svg
                                    class="h-3.5 w-3.5 shrink-0"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <rect
                                        x="3"
                                        y="4"
                                        width="18"
                                        height="18"
                                        rx="2"
                                    />
                                    <line
                                        x1="16"
                                        y1="2"
                                        x2="16"
                                        y2="6"
                                    />
                                    <line
                                        x1="8"
                                        y1="2"
                                        x2="8"
                                        y2="6"
                                    />
                                    <line
                                        x1="3"
                                        y1="10"
                                        x2="21"
                                        y2="10"
                                    />
                                </svg>

                                <span
                                    class="truncate"
                                    x-text="activeDateLabel()"
                                ></span>

                            </span>

                        </div>

                    </div>

                </div>


                {{-- ======================================================
                    LOADING
                ======================================================= --}}
                <template x-if="driversLoading && drivers.length === 0">

                    <div class="divide-y divide-gray-100 dark:divide-gray-800">

                        <template x-for="i in 5" :key="i">

                            <div class="p-4 sm:p-5">

                                <div class="animate-pulse">

                                    <div
                                        class="h-5 w-64 rounded bg-gray-100
                                               dark:bg-white/[0.05]"
                                    ></div>

                                    <div
                                        class="mt-3 h-24 rounded-xl bg-gray-100
                                               dark:bg-white/[0.05]"
                                    ></div>

                                </div>

                            </div>

                        </template>

                    </div>

                </template>


                {{-- ======================================================
                    DRIVER LIST
                ======================================================= --}}
                <div class="divide-y divide-gray-100 dark:divide-gray-800">

                    <template
                        x-for="driver in drivers"
                        :key="driver.id"
                    >

                        <div
                            x-data="{ phonesOpen: false }"
                            class="relative p-4 sm:p-5"
                            :class="driverItemClasses(driver)"
                        >

                            {{-- Status stripe --}}
                            <div
                                class="absolute inset-y-0 left-0 w-1"
                                :class="statusStripeClass(driver)"
                            ></div>


                            <div class="pl-1">

                                {{-- =================================================
                                    DRIVER TOP
                                ================================================== --}}
                                <div
                                    class="flex flex-col gap-4 lg:flex-row
                                           lg:items-start lg:justify-between"
                                >

                                    <div class="min-w-0 flex-1">

                                        {{-- Name + status --}}
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >

                                            <h3
                                                class="break-words text-sm font-semibold
                                                       text-gray-900 dark:text-white"
                                                x-text="driver.name || '—'"
                                            ></h3>


                                            {{-- STATUS --}}
                                            <span
                                                class="inline-flex items-center gap-1.5
                                                       rounded-full px-2.5 py-1
                                                       text-[10px] font-semibold
                                                       uppercase tracking-wide"
                                                :class="statusBadgeClass(driver)"
                                            >

                                                <span
                                                    class="h-1.5 w-1.5 rounded-full"
                                                    :class="statusDotClass(driver)"
                                                ></span>

                                                <span
                                                    x-text="statusLabel(driver)"
                                                ></span>

                                            </span>

                                        </div>


                                        {{-- ID + status --}}
                                        <div
                                            class="mt-1 flex flex-wrap items-center
                                                   gap-2 text-xs text-gray-500
                                                   dark:text-gray-400"
                                        >

                                            <span>

                                                {{ __('telegram.operation_user.id') }}:

                                                <span
                                                    class="font-medium text-gray-700
                                                           dark:text-gray-300"
                                                    x-text="driver.id ?? '—'"
                                                ></span>

                                            </span>


                                            <span>•</span>


                                            <span
                                                x-text="statusLabel(driver)"
                                            ></span>

                                        </div>

                                    </div>


                                    {{-- COUNTERS --}}
                                    <div
                                        class="flex shrink-0 flex-wrap
                                               items-center gap-2"
                                    >

                                        {{-- Phones --}}
                                        <span
                                            class="rounded-lg px-2.5 py-1
                                                   text-xs font-medium"
                                            :class="counterClass(driver)"
                                        >
                                            <span
                                                x-text="resolvedPhonesCount(driver)"
                                            ></span>

                                            <span>
                                                {{ __('telegram.operation_user.drivers.phones') }}
                                            </span>
                                        </span>


                                        {{-- Checks --}}
                                        <span
                                            class="rounded-lg px-2.5 py-1
                                                   text-xs font-medium"
                                            :class="counterClass(driver)"
                                        >
                                            <span
                                                x-text="driver.checks_count ?? 0"
                                            ></span>

                                            <span>
                                                {{ __('telegram.operation_user.drivers.checks') }}
                                            </span>
                                        </span>

                                    </div>

                                </div>


                                {{-- =================================================
                                    DRIVER ↔ TELEGRAM COMPARISON
                                ================================================== --}}
                                <div
                                    class="mt-4 overflow-hidden rounded-2xl border"
                                    :class="comparisonContainerClass(driver)"
                                >

                                    {{-- Header --}}
                                    <div
                                        class="border-b px-3 py-2.5 sm:px-4"
                                        :class="comparisonHeaderClass(driver)"
                                    >

                                        <div
                                            class="flex flex-col gap-1 sm:flex-row
                                                   sm:items-center sm:justify-between"
                                        >

                                            <div
                                                class="flex items-center gap-2"
                                            >

                                                <span
                                                    class="flex h-7 w-7 items-center
                                                           justify-center rounded-lg"
                                                    :class="comparisonIconClass(driver)"
                                                >

                                                    <svg
                                                        class="h-4 w-4"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            d="M8 12h8M12 8l4 4-4 4"
                                                        />
                                                    </svg>

                                                </span>


                                                <div>

                                                    <div
                                                        class="text-xs font-semibold"
                                                        :class="comparisonTitleClass(driver)"
                                                    >
                                                        Сравнение данных
                                                    </div>

                                                    <div
                                                        class="text-[11px]"
                                                        :class="comparisonSubtitleClass(driver)"
                                                    >
                                                        Водитель и Telegram
                                                    </div>

                                                </div>

                                            </div>


                                            {{-- Score --}}
                                            <div
                                                class="inline-flex w-fit items-center
                                                       gap-1.5 rounded-lg px-2.5 py-1
                                                       text-[11px] font-semibold"
                                                :class="comparisonScoreClass(driver)"
                                            >

                                                <span>Match</span>

                                                <span
                                                    x-text="matchScore(driver)"
                                                ></span>

                                            </div>

                                        </div>

                                    </div>


                                    {{-- Comparison body --}}
                                    <div
                                        class="grid grid-cols-1 lg:grid-cols-[1fr_auto_1fr]"
                                    >

                                        {{-- DRIVER --}}
                                        <div
                                            class="min-w-0 p-3 sm:p-4"
                                        >

                                            <div
                                                class="mb-2 flex items-center gap-2"
                                            >

                                                <span
                                                    class="flex h-7 w-7 items-center
                                                           justify-center rounded-lg
                                                           bg-blue-100 text-blue-700
                                                           dark:bg-blue-500/10
                                                           dark:text-blue-300"
                                                >

                                                    <svg
                                                        class="h-4 w-4"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            d="M20 21a8 8 0 00-16 0"
                                                        />
                                                        <circle
                                                            cx="12"
                                                            cy="7"
                                                            r="4"
                                                        />
                                                    </svg>

                                                </span>


                                                <span
                                                    class="text-[11px] font-semibold
                                                           uppercase tracking-wide
                                                           text-gray-500
                                                           dark:text-gray-400"
                                                >
                                                    Водитель
                                                </span>

                                            </div>


                                            <div
                                                class="rounded-xl border border-blue-100
                                                       bg-blue-50/60 p-3
                                                       dark:border-blue-900/30
                                                       dark:bg-blue-500/[0.05]"
                                            >

                                                <p
                                                    class="break-words text-sm font-semibold
                                                           text-gray-900
                                                           dark:text-white"
                                                    x-text="driver.name || '—'"
                                                ></p>


                                                <p
                                                    class="mt-1 text-[11px]
                                                           text-gray-500
                                                           dark:text-gray-400"
                                                >
                                                    ID:
                                                    <span
                                                        class="font-medium text-gray-700
                                                               dark:text-gray-300"
                                                        x-text="driver.id ?? '—'"
                                                    ></span>
                                                </p>

                                            </div>

                                        </div>


                                        {{-- CENTER --}}
                                        <div
                                            class="flex items-center justify-center
                                                   px-3 pb-2 lg:py-4"
                                        >

                                            <div
                                                class="flex h-9 w-9 rotate-90 items-center
                                                       justify-center rounded-full
                                                       border text-gray-400
                                                       lg:rotate-0"
                                                :class="comparisonArrowClass(driver)"
                                            >

                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M5 12h14m-6-6 6 6-6 6"
                                                    />
                                                </svg>

                                            </div>

                                        </div>


                                        {{-- TELEGRAM --}}
                                        <div
                                            class="min-w-0 p-3 pt-0 sm:p-4 sm:pt-0 lg:pt-4"
                                        >

                                            <div
                                                class="mb-2 flex items-center gap-2"
                                            >

                                                <span
                                                    class="flex h-7 w-7 items-center
                                                           justify-center rounded-lg
                                                           bg-indigo-100 text-indigo-700
                                                           dark:bg-indigo-500/10
                                                           dark:text-indigo-300"
                                                >

                                                    <svg
                                                        class="h-4 w-4"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            d="M21 5 10 16"
                                                        />
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            d="m21 5-7 17-4-6-6-4 17-7Z"
                                                        />
                                                    </svg>

                                                </span>


                                                <span
                                                    class="text-[11px] font-semibold
                                                           uppercase tracking-wide
                                                           text-gray-500
                                                           dark:text-gray-400"
                                                >
                                                    Telegram
                                                </span>

                                            </div>


                                            <div
                                                class="rounded-xl border border-indigo-100
                                                       bg-indigo-50/60 p-3
                                                       dark:border-indigo-900/30
                                                       dark:bg-indigo-500/[0.05]"
                                            >

                                                <p
                                                    class="break-words text-sm font-semibold
                                                           text-gray-900
                                                           dark:text-white"
                                                    x-text="telegramName(driver)"
                                                ></p>


                                                <p
                                                    class="mt-1 break-all text-xs
                                                           font-medium text-indigo-700
                                                           dark:text-indigo-300"
                                                    x-text="telegramUsername(driver)"
                                                ></p>


                                                <p
                                                    class="mt-1 text-[11px]
                                                           text-gray-500
                                                           dark:text-gray-400"
                                                    x-text="telegramUserId(driver)"
                                                ></p>

                                            </div>

                                        </div>

                                    </div>


                                    {{-- Phone --}}
                                    <template x-if="primaryResolvedPhone(driver)">

                                        <div
                                            class="border-t px-3 py-3 sm:px-4"
                                            :class="comparisonFooterClass(driver)"
                                        >

                                            <div
                                                class="flex flex-col gap-2 sm:flex-row
                                                       sm:items-center sm:justify-between"
                                            >

                                                <div
                                                    class="min-w-0"
                                                >

                                                    <div
                                                        class="text-[10px] font-semibold
                                                               uppercase tracking-wide
                                                               text-gray-400
                                                               dark:text-gray-500"
                                                    >
                                                        Телефон
                                                    </div>

                                                    <div
                                                        class="mt-0.5 font-mono text-xs
                                                               font-semibold"
                                                        :class="phoneTextClass(driver)"
                                                        x-text="primaryPhone(driver)"
                                                    ></div>

                                                </div>


                                                <div
                                                    class="text-[11px]"
                                                    :class="phoneSecondaryClass(driver)"
                                                    x-text="
                                                        primaryResolvedPhone(driver)?.resolved_at
                                                            ? formatDate(
                                                                primaryResolvedPhone(driver).resolved_at,
                                                                true
                                                            )
                                                            : '—'
                                                    "
                                                ></div>

                                            </div>

                                        </div>

                                    </template>

                                </div>


                                {{-- =================================================
                                    STATUS SUMMARY
                                ================================================== --}}
                                <div
                                    class="mt-4 rounded-xl border px-3 py-3"
                                    :class="statusSummaryClass(driver)"
                                >

                                    <div class="flex items-start gap-3">

                                        <div
                                            class="mt-0.5 flex h-8 w-8 shrink-0
                                                   items-center justify-center
                                                   rounded-lg"
                                            :class="statusIconContainerClass(driver)"
                                        >

                                            {{-- Confirmed --}}
                                            <template x-if="statusType(driver) === 'confirmed'">

                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2.5"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M5 13l4 4L19 7"
                                                    />
                                                </svg>

                                            </template>


                                            {{-- Not Confirmed --}}
                                            <template x-if="statusType(driver) === 'not_confirmed'">

                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2.5"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M6 6l12 12M18 6L6 18"
                                                    />
                                                </svg>

                                            </template>


                                            {{-- Pending --}}
                                            <template x-if="statusType(driver) === 'pending'">

                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <circle
                                                        cx="12"
                                                        cy="12"
                                                        r="9"
                                                    />

                                                    <path
                                                        stroke-linecap="round"
                                                        d="M12 7v5l3 2"
                                                    />
                                                </svg>

                                            </template>


                                            {{-- Unknown --}}
                                            <template x-if="statusType(driver) === 'unknown'">

                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <circle
                                                        cx="12"
                                                        cy="12"
                                                        r="9"
                                                    />

                                                    <path
                                                        stroke-linecap="round"
                                                        d="M9.5 9a2.6 2.6 0 115 1.2c0 1.8-2.5 2-2.5 3.8"
                                                    />

                                                    <path
                                                        stroke-linecap="round"
                                                        d="M12 17h.01"
                                                    />
                                                </svg>

                                            </template>

                                        </div>


                                        <div class="min-w-0 flex-1">

                                            <div
                                                class="text-xs font-semibold"
                                                :class="statusTextClass(driver)"
                                                x-text="statusLabel(driver)"
                                            ></div>

                                            <div
                                                class="mt-0.5 text-xs"
                                                :class="statusDescriptionClass(driver)"
                                                x-text="statusDescription(driver)"
                                            ></div>

                                        </div>

                                    </div>

                                </div>


                                {{-- =================================================
                                    PHONES
                                ================================================== --}}
                                <div class="mt-4">

                                    <button
                                        type="button"
                                        @click="phonesOpen = !phonesOpen"
                                        class="inline-flex items-center gap-2
                                               text-sm font-medium text-blue-600
                                               transition hover:text-blue-700
                                               dark:text-blue-400
                                               dark:hover:text-blue-300"
                                    >

                                        <span
                                            x-text="
                                                phonesOpen
                                                    ? translations.drivers.hide_phones
                                                    : translations.drivers.show_phones
                                            "
                                        ></span>

                                        <svg
                                            class="h-4 w-4 transition-transform"
                                            :class="{ 'rotate-180': phonesOpen }"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m6 9 6 6 6-6"
                                            />
                                        </svg>

                                    </button>


                                    <div
                                        x-show="phonesOpen"
                                        x-collapse
                                        class="mt-3"
                                    >

                                        {{-- No phones --}}
                                        <template
                                            x-if="
                                                !driver.resolved_phones
                                                || driver.resolved_phones.length === 0
                                            "
                                        >

                                            <div
                                                class="rounded-xl border border-dashed
                                                       border-gray-200 px-4 py-5
                                                       text-center
                                                       dark:border-gray-800"
                                            >

                                                <p
                                                    class="text-xs text-gray-500
                                                           dark:text-gray-400"
                                                >
                                                    {{
                                                        __('telegram.operation_user.drivers.no_resolved_phones')
                                                    }}
                                                </p>

                                            </div>

                                        </template>


                                        {{-- Phones --}}
                                        <div
                                            x-show="
                                                driver.resolved_phones
                                                && driver.resolved_phones.length > 0
                                            "
                                            class="grid grid-cols-1 gap-2
                                                   sm:grid-cols-2 xl:grid-cols-3"
                                        >

                                            <template
                                                x-for="
                                                    phone in (driver.resolved_phones || [])
                                                "
                                                :key="phone.id"
                                            >

                                                <div
                                                    class="rounded-xl border p-3"
                                                    :class="phoneCardClass(driver)"
                                                >

                                                    <div
                                                        class="flex items-start
                                                               justify-between gap-3"
                                                    >

                                                        <div class="min-w-0">

                                                            <p
                                                                class="font-mono text-sm
                                                                       font-semibold"
                                                                :class="phoneTextClass(driver)"
                                                                x-text="
                                                                    phone.phone
                                                                    || phone.phone_normalized
                                                                    || '—'
                                                                "
                                                            ></p>


                                                            <p
                                                                class="mt-1 break-all text-xs"
                                                                :class="phoneSecondaryClass(driver)"
                                                                x-text="
                                                                    phone.telegram_username
                                                                        ? '@' + String(
                                                                            phone.telegram_username
                                                                        ).replace(/^@/, '')
                                                                        : translations.drivers.no_username
                                                                "
                                                            ></p>

                                                        </div>


                                                        <span
                                                            class="shrink-0 rounded-md
                                                                   px-1.5 py-1 text-[10px]"
                                                            :class="phoneIdClass(driver)"
                                                            x-text="phone.telegram_user_id ?? '—'"
                                                        ></span>

                                                    </div>


                                                    <div
                                                        class="mt-2 text-[11px]"
                                                        :class="phoneSecondaryClass(driver)"
                                                    >
                                                        <span
                                                            x-text="
                                                                phone.resolved_at
                                                                    ? formatDate(
                                                                        phone.resolved_at,
                                                                        true
                                                                    )
                                                                    : translations.drivers.unknown_date
                                                            "
                                                        ></span>
                                                    </div>

                                                </div>

                                            </template>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </template>

                </div>


                {{-- ======================================================
                    EMPTY
                ======================================================= --}}
                <template
                    x-if="
                        !driversLoading
                        && drivers.length === 0
                        && !driversError
                    "
                >

                    <div class="px-5 py-16 text-center">

                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center
                                   rounded-full bg-gray-100
                                   dark:bg-white/[0.06]"
                        >

                            <svg
                                class="h-6 w-6 text-gray-400"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"
                                />

                                <circle
                                    cx="9"
                                    cy="7"
                                    r="4"
                                />
                            </svg>

                        </div>


                        <h3
                            class="mt-4 text-sm font-semibold text-gray-900
                                   dark:text-white"
                        >
                            {{ __('telegram.operation_user.drivers.no_drivers') }}
                        </h3>


                        <p
                            class="mt-1 text-sm text-gray-500
                                   dark:text-gray-400"
                        >
                            {{
                                __('telegram.operation_user.drivers.no_drivers_description')
                            }}
                        </p>

                    </div>

                </template>


                {{-- ======================================================
                    ERROR
                ======================================================= --}}
                <template x-if="driversError">

                    <div class="p-4 sm:p-5">

                        <div
                            class="rounded-xl border border-red-200 bg-red-50 p-4
                                   dark:border-red-900/40 dark:bg-red-950/30"
                        >

                            <p
                                class="text-sm font-medium text-red-800
                                       dark:text-red-300"
                            >
                                {{
                                    __('telegram.operation_user.errors.load_drivers')
                                }}
                            </p>

                            <p
                                class="mt-1 text-sm text-red-700
                                       dark:text-red-400"
                                x-text="driversError"
                            ></p>

                        </div>

                    </div>

                </template>


                {{-- ======================================================
                    PAGINATION
                ======================================================= --}}
                <div
                    x-show="driversMeta.last_page > 1"
                    x-cloak
                    class="border-t border-gray-200 px-4 py-4 sm:px-5
                           dark:border-gray-800"
                >

                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center
                               sm:justify-between"
                    >

                        <p
                            class="text-xs text-gray-500 sm:text-sm
                                   dark:text-gray-400"
                        >

                            {{ __('telegram.operation_user.pagination.page') }}

                            <span
                                class="font-medium text-gray-700
                                       dark:text-gray-300"
                                x-text="driversMeta.current_page"
                            ></span>

                            {{ __('telegram.operation_user.pagination.of') }}

                            <span
                                class="font-medium text-gray-700
                                       dark:text-gray-300"
                                x-text="driversMeta.last_page"
                            ></span>

                        </p>


                        <div
                            class="flex items-center justify-between gap-1
                                   sm:justify-end"
                        >

                            {{-- Previous --}}
                            <button
                                type="button"
                                @click="goToPage(driversMeta.current_page - 1)"
                                :disabled="
                                    driversMeta.current_page <= 1
                                    || driversLoading
                                "
                                class="h-9 rounded-lg border border-gray-200
                                       bg-white px-3 text-xs font-medium
                                       text-gray-700 transition hover:bg-gray-50
                                       disabled:cursor-not-allowed
                                       disabled:opacity-40 sm:text-sm
                                       dark:border-gray-800 dark:bg-gray-950
                                       dark:text-gray-300
                                       dark:hover:bg-white/[0.04]"
                            >
                                {{ __('telegram.operation_user.pagination.previous') }}
                            </button>


                            {{-- Pages --}}
                            <div class="flex items-center gap-1">

                                <template
                                    x-for="page in visiblePages()"
                                    :key="page"
                                >

                                    <template x-if="page !== '...'">

                                        <button
                                            type="button"
                                            @click="goToPage(page)"
                                            :disabled="driversLoading"
                                            class="h-9 min-w-9 rounded-lg px-2.5
                                                   text-xs font-medium transition
                                                   sm:text-sm"
                                            :class="
                                                Number(page)
                                                    === Number(driversMeta.current_page)

                                                    ? 'bg-blue-600 text-white'

                                                    : 'border border-gray-200
                                                       bg-white text-gray-700
                                                       hover:bg-gray-50
                                                       dark:border-gray-800
                                                       dark:bg-gray-950
                                                       dark:text-gray-300
                                                       dark:hover:bg-white/[0.04]'
                                            "
                                            x-text="page"
                                        ></button>

                                    </template>


                                    <template x-if="page === '...'">

                                        <span
                                            class="flex h-9 min-w-7 items-center
                                                   justify-center text-xs
                                                   text-gray-400"
                                        >
                                            ...
                                        </span>

                                    </template>

                                </template>

                            </div>


                            {{-- Next --}}
                            <button
                                type="button"
                                @click="goToPage(driversMeta.current_page + 1)"
                                :disabled="
                                    driversMeta.current_page >= driversMeta.last_page
                                    || driversLoading
                                "
                                class="h-9 rounded-lg border border-gray-200
                                       bg-white px-3 text-xs font-medium
                                       text-gray-700 transition hover:bg-gray-50
                                       disabled:cursor-not-allowed
                                       disabled:opacity-40 sm:text-sm
                                       dark:border-gray-800 dark:bg-gray-950
                                       dark:text-gray-300
                                       dark:hover:bg-white/[0.04]"
                            >
                                {{ __('telegram.operation_user.pagination.next') }}
                            </button>

                        </div>

                    </div>

                </div>

            </div>


            <div class="h-12 sm:h-20 lg:h-28"></div>

        </div>

    </div>

@endsection


@push('scripts')

<script>
    function operationUserPage(config) {
        return {

            /*
            |--------------------------------------------------------------------------
            | CONFIG
            |--------------------------------------------------------------------------
            */

            operationUserId: config.operationUserId,

            operationUserUrl: config.operationUserUrl,

            driversUrl: config.driversUrl,

            translations: config.translations,


            /*
            |--------------------------------------------------------------------------
            | INITIALIZATION GUARD
            |--------------------------------------------------------------------------
            |
            | Prevents duplicate requests if Alpine initializes the component
            | more than once.
            |
            */

            initialized: false,

            initializing: false,


            /*
            |--------------------------------------------------------------------------
            | REQUEST LOCKS
            |--------------------------------------------------------------------------
            */

            operatorRequest: null,

            driversRequest: null,


            /*
            |--------------------------------------------------------------------------
            | OPERATOR
            |--------------------------------------------------------------------------
            */

            operator: null,

            operatorLoading: false,

            operatorError: null,


            /*
            |--------------------------------------------------------------------------
            | DRIVERS
            |--------------------------------------------------------------------------
            */

            drivers: [],

            driversLoading: false,

            driversError: null,


            /*
            |--------------------------------------------------------------------------
            | PAGINATION
            |--------------------------------------------------------------------------
            */

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


            /*
            |--------------------------------------------------------------------------
            | FILTER
            |--------------------------------------------------------------------------
            */

            dateFilter: 'all',

            fromDate: '',

            toDate: '',


            /*
            |--------------------------------------------------------------------------
            | INIT
            |--------------------------------------------------------------------------
            */

            async init() {

                if (
                    this.initialized
                    || this.initializing
                ) {

                    return;

                }


                this.initializing = true;

                this.initialized = true;


                try {

                    this.readUrl();


                    await Promise.all([

                        this.loadOperator(),

                        this.loadDrivers(),

                    ]);

                } finally {

                    this.initializing = false;

                }

            },


            /*
            |--------------------------------------------------------------------------
            | REFRESH
            |--------------------------------------------------------------------------
            */

            async refreshAll() {

                if (
                    this.operatorLoading
                    || this.driversLoading
                ) {

                    return;

                }


                await Promise.all([

                    this.loadOperator(true),

                    this.loadDrivers(true),

                ]);

            },


            /*
            |--------------------------------------------------------------------------
            | OPERATOR API
            |--------------------------------------------------------------------------
            */

            async loadOperator(force = false) {

                /*
                 * If the exact same request is already running,
                 * return the existing Promise.
                 */
                if (
                    this.operatorRequest
                    && !force
                ) {

                    return this.operatorRequest;

                }


                this.operatorLoading = true;

                this.operatorError = null;


                this.operatorRequest = (async () => {

                    try {

                        const response = await fetch(

                            this.operationUserUrl,

                            {

                                method: 'GET',

                                headers: {

                                    Accept:
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',

                                },

                                credentials:
                                    'same-origin',

                                cache:
                                    'no-store',

                            }

                        );


                        if (!response.ok) {

                            throw await this.httpError(
                                response
                            );

                        }


                        const json =
                            await response.json();


                        this.operator =
                            json.data ?? json;


                    } catch (error) {

                        console.error(error);

                        this.operatorError =
                            error?.message
                            || this.translations.errors.unknown;

                    } finally {

                        this.operatorLoading = false;

                    }

                })();


                try {

                    await this.operatorRequest;

                } finally {

                    this.operatorRequest = null;

                }

            },


            /*
            |--------------------------------------------------------------------------
            | DRIVER API
            |--------------------------------------------------------------------------
            */

            async loadDrivers(force = false) {

                /*
                 * Prevent same endpoint from being requested twice
                 * while an existing request is already running.
                 */
                if (
                    this.driversRequest
                    && !force
                ) {

                    return this.driversRequest;

                }


                this.driversLoading = true;

                this.driversError = null;


                const params =
                    this.buildDriverParams();


                const requestUrl =
                    this.driversUrl
                    + '?'
                    + params.toString();


                this.driversRequest = (async () => {

                    try {

                        const response = await fetch(

                            requestUrl,

                            {

                                method: 'GET',

                                headers: {

                                    Accept:
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',

                                },

                                credentials:
                                    'same-origin',

                                cache:
                                    'no-store',

                            }

                        );


                        if (!response.ok) {

                            throw await this.httpError(
                                response
                            );

                        }


                        const json =
                            await response.json();


                        this.drivers =
                            Array.isArray(
                                json.data
                            )
                                ? json.data
                                : [];


                        this.setDriversMeta(
                            json
                        );


                        this.syncUrl();

                    } catch (error) {

                        console.error(error);

                        this.drivers = [];

                        this.driversError =
                            error?.message
                            || this.translations.errors.unknown;

                    } finally {

                        this.driversLoading = false;

                    }

                })();


                try {

                    await this.driversRequest;

                } finally {

                    this.driversRequest = null;

                }

            },


            /*
            |--------------------------------------------------------------------------
            | BUILD DRIVER QUERY
            |--------------------------------------------------------------------------
            */

            buildDriverParams() {

                const params =
                    new URLSearchParams();


                params.set(
                    'page',
                    String(this.page)
                );


                params.set(
                    'per_page',
                    String(this.perPage)
                );


                /*
                 * Preset filters.
                 */
                if (
                    this.dateFilter === 'last_week'
                    ||
                    this.dateFilter === 'last_month'
                ) {

                    params.set(
                        'date_filter',
                        this.dateFilter
                    );

                }


                /*
                 * Custom range.
                 */
                if (this.fromDate) {

                    params.set(
                        'from_date',
                        this.fromDate
                    );

                }


                if (this.toDate) {

                    params.set(
                        'to_date',
                        this.toDate
                    );

                }


                return params;

            },


            /*
            |--------------------------------------------------------------------------
            | HTTP ERROR
            |--------------------------------------------------------------------------
            */

            async httpError(response) {

                let message =
                    `HTTP ${response.status}`;


                try {

                    const json =
                        await response.json();


                    if (json.message) {

                        message =
                            json.message;

                    }

                } catch (_) {

                }


                return new Error(message);

            },


            /*
            |--------------------------------------------------------------------------
            | META
            |--------------------------------------------------------------------------
            */

            setDriversMeta(json) {

                const meta =
                    json.meta || {};


                this.driversMeta = {

                    current_page:
                        Number(
                            meta.current_page
                            ?? json.current_page
                            ?? this.page
                        ),


                    last_page:
                        Number(
                            meta.last_page
                            ?? json.last_page
                            ?? 1
                        ),


                    per_page:
                        Number(
                            meta.per_page
                            ?? json.per_page
                            ?? this.perPage
                        ),


                    total:
                        Number(
                            meta.total
                            ?? json.total
                            ?? this.drivers.length
                        ),


                    from:
                        Number(
                            meta.from
                            ?? json.from
                            ?? (
                                this.drivers.length

                                    ?

                                    (
                                        (
                                            this.page - 1
                                        )
                                        * this.perPage
                                    ) + 1

                                    :

                                    0
                            )
                        ),


                    to:
                        Number(
                            meta.to
                            ?? json.to
                            ?? (
                                this.drivers.length

                                    ?

                                    (
                                        (
                                            this.page - 1
                                        )
                                        * this.perPage
                                    )
                                    + this.drivers.length

                                    :

                                    0
                            )
                        ),

                };


                this.page =
                    this.driversMeta.current_page;

            },


            /*
            |--------------------------------------------------------------------------
            | DATE FILTER
            |--------------------------------------------------------------------------
            */

            setDateFilter(filter) {

                if (
                    this.driversLoading
                ) {

                    return;

                }


                this.page = 1;

                this.fromDate = '';

                this.toDate = '';

                this.dateFilter = filter;


                this.loadDrivers();

            },


            applyCustomDateFilter() {

                if (
                    this.driversLoading
                ) {

                    return;

                }


                /*
                 * If from > to, do not request broken range.
                 */
                if (
                    this.fromDate
                    &&
                    this.toDate
                    &&
                    this.fromDate > this.toDate
                ) {

                    const oldFrom =
                        this.fromDate;

                    this.fromDate =
                        this.toDate;

                    this.toDate =
                        oldFrom;

                }


                this.page = 1;

                this.dateFilter = 'custom';


                this.loadDrivers();

            },


            clearDateFilter() {

                if (
                    this.driversLoading
                ) {

                    return;

                }


                this.page = 1;

                this.dateFilter = 'all';

                this.fromDate = '';

                this.toDate = '';


                this.loadDrivers();

            },


            hasDateFilter() {

                return (
                    this.dateFilter !== 'all'
                    ||
                    Boolean(this.fromDate)
                    ||
                    Boolean(this.toDate)
                );

            },


            activeDateLabel() {

                if (
                    this.dateFilter === 'last_week'
                ) {

                    return (
                        this.translations.filters.last_week
                        || 'Last week'
                    );

                }


                if (
                    this.dateFilter === 'last_month'
                ) {

                    return (
                        this.translations.filters.last_month
                        || 'Last month'
                    );

                }


                if (
                    this.fromDate
                    &&
                    this.toDate
                ) {

                    return (
                        this.fromDate
                        + ' → '
                        + this.toDate
                    );

                }


                if (
                    this.fromDate
                ) {

                    return (
                        '≥ '
                        + this.fromDate
                    );

                }


                if (
                    this.toDate
                ) {

                    return (
                        '≤ '
                        + this.toDate
                    );

                }


                return (
                    this.translations.filters.all
                    || 'All'
                );

            },


            /*
            |--------------------------------------------------------------------------
            | PAGINATION
            |--------------------------------------------------------------------------
            */

            changePerPage() {

                this.page = 1;

                this.loadDrivers();

            },


            goToPage(page) {

                page =
                    Number(page);


                if (

                    page < 1

                    ||

                    page >
                        this.driversMeta.last_page

                    ||

                    page ===
                        this.driversMeta.current_page

                    ||

                    this.driversLoading

                ) {

                    return;

                }


                this.page =
                    page;


                this.loadDrivers();

            },


            visiblePages() {

                const current =
                    Number(
                        this.driversMeta.current_page
                    );


                const last =
                    Number(
                        this.driversMeta.last_page
                    );


                if (
                    last <= 7
                ) {

                    return Array.from(
                        {
                            length: last
                        },
                        (_, index) =>
                            index + 1
                    );

                }


                const pages = [1];


                if (
                    current > 4
                ) {

                    pages.push('...');

                }


                const start =
                    Math.max(
                        2,
                        current - 1
                    );


                const end =
                    Math.min(
                        last - 1,
                        current + 1
                    );


                for (
                    let page = start;
                    page <= end;
                    page++
                ) {

                    pages.push(page);

                }


                if (
                    current < last - 3
                ) {

                    pages.push('...');

                }


                pages.push(last);


                return [
                    ...new Set(pages)
                ];

            },


            /*
            |--------------------------------------------------------------------------
            | URL
            |--------------------------------------------------------------------------
            */

            syncUrl() {

                const params =
                    new URLSearchParams();


                if (
                    this.page > 1
                ) {

                    params.set(
                        'page',
                        String(this.page)
                    );

                }


                if (
                    this.perPage !== 10
                ) {

                    params.set(
                        'per_page',
                        String(this.perPage)
                    );

                }


                if (
                    this.dateFilter !== 'all'
                    &&
                    this.dateFilter !== 'custom'
                ) {

                    params.set(
                        'date_filter',
                        this.dateFilter
                    );

                }


                if (
                    this.fromDate
                ) {

                    params.set(
                        'from_date',
                        this.fromDate
                    );

                }


                if (
                    this.toDate
                ) {

                    params.set(
                        'to_date',
                        this.toDate
                    );

                }


                const query =
                    params.toString();


                const nextUrl =
                    window.location.pathname

                    + (

                        query

                            ?

                            '?' + query

                            :

                            ''

                    );


                const currentUrl =
                    window.location.pathname
                    + window.location.search;


                if (
                    nextUrl === currentUrl
                ) {

                    return;

                }


                window.history.replaceState(

                    {},

                    '',

                    nextUrl

                );

            },


            readUrl() {

                const params =
                    new URLSearchParams(
                        window.location.search
                    );


                this.page =
                    Number(
                        params.get('page')
                        || 1
                    );


                this.perPage =
                    Number(
                        params.get('per_page')
                        || 10
                    );


                if (
                    ![
                        10,
                        25,
                        50,
                        100
                    ].includes(
                        this.perPage
                    )
                ) {

                    this.perPage = 10;

                }


                const urlDateFilter =
                    params.get('date_filter');


                if (
                    [
                        'last_week',
                        'last_month'
                    ].includes(
                        urlDateFilter
                    )
                ) {

                    this.dateFilter =
                        urlDateFilter;

                } else {

                    this.dateFilter =
                        'all';

                }


                this.fromDate =
                    params.get(
                        'from_date'
                    )
                    || '';


                this.toDate =
                    params.get(
                        'to_date'
                    )
                    || '';


                if (
                    this.fromDate
                    ||
                    this.toDate
                ) {

                    this.dateFilter =
                        'custom';

                }

            },


            /*
            |--------------------------------------------------------------------------
            | STATUS
            |--------------------------------------------------------------------------
            */

            statusType(driver) {

                if (!driver) {

                    return 'unknown';

                }


                const raw =
                    String(

                        driver.status

                        ??
                        driver.check_status

                        ??
                        driver.telegram_driver_check_status

                        ??
                        driver.latest_check?.status

                        ??
                        ''

                    )
                        .trim()
                        .toLowerCase();


                /*
                 * IMPORTANT:
                 *
                 * not_confirmed MUST come first.
                 */

                if ([
                    'not_confirmed',
                    'not-confirmed',
                    'not confirmed',
                    'rejected',
                    'failed',
                    'unmatched',
                    'not_verified',
                    'not-verified',
                ].includes(raw)) {

                    return 'not_confirmed';

                }


                if ([
                    'confirmed',
                    'confirm',
                    'verified',
                    'success',
                    'matched',
                    'match',
                ].includes(raw)) {

                    return 'confirmed';

                }


                if ([
                    'pending',
                    'processing',
                    'in_progress',
                    'in-progress',
                    'waiting',
                ].includes(raw)) {

                    return 'pending';

                }


                return 'unknown';

            },


            statusLabel(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return (
                        this.translations.drivers.confirmed
                        || 'Confirmed'
                    );

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return (
                        this.translations.drivers.not_confirmed
                        || 'Not confirmed'
                    );

                }


                if (
                    type === 'pending'
                ) {

                    return (
                        this.translations.drivers.pending
                        || 'Pending'
                    );

                }


                return (
                    this.translations.unknown
                    || 'Unknown'
                );

            },


            statusDescription(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return (
                        this.translations.drivers.confirmed_description
                        || 'Driver information has been confirmed.'
                    );

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return (
                        this.translations.drivers.not_confirmed_description
                        || 'Driver information has not been confirmed.'
                    );

                }


                if (
                    type === 'pending'
                ) {

                    return (
                        this.translations.drivers.pending_description
                        || 'Driver is waiting for confirmation.'
                    );

                }


                return (
                    this.translations.drivers.unknown_description
                    || 'Driver status is unknown.'
                );

            },


            /*
            |--------------------------------------------------------------------------
            | ITEM
            |--------------------------------------------------------------------------
            */

            driverItemClasses(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        bg-green-50/70
                        dark:bg-green-500/[0.045]
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        bg-red-50/70
                        dark:bg-red-500/[0.045]
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        bg-amber-50/60
                        dark:bg-amber-500/[0.04]
                    `;

                }


                return `
                    bg-white
                    dark:bg-gray-900
                `;

            },


            statusStripeClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        bg-green-500
                        dark:bg-green-400
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        bg-red-500
                        dark:bg-red-400
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        bg-amber-500
                        dark:bg-amber-400
                    `;

                }


                return `
                    bg-gray-300
                    dark:bg-gray-700
                `;

            },


            /*
            |--------------------------------------------------------------------------
            | COMPARISON
            |--------------------------------------------------------------------------
            */

            primaryResolvedPhone(driver) {

                return (
                    driver?.resolved_phones?.[0]
                    ?? null
                );

            },


            resolvedPhonesCount(driver) {

                return Number(
                    driver?.resolved_phones_count
                    ??
                    (
                        Array.isArray(
                            driver?.resolved_phones
                        )
                            ?
                            driver.resolved_phones.length
                            :
                            0
                    )
                );

            },


            telegramName(driver) {

                const phone =
                    this.primaryResolvedPhone(
                        driver
                    );


                if (!phone) {

                    return '—';

                }


                const firstName =
                    String(
                        phone.telegram_first_name
                        || ''
                    ).trim();


                const lastName =
                    String(
                        phone.telegram_last_name
                        || ''
                    ).trim();


                const fullName =
                    `${firstName} ${lastName}`
                        .trim();


                return fullName || '—';

            },


            telegramUsername(driver) {

                const phone =
                    this.primaryResolvedPhone(
                        driver
                    );


                if (
                    !phone?.telegram_username
                ) {

                    return (
                        this.translations.drivers.no_username
                        || 'No username'
                    );

                }


                return (
                    '@'
                    +
                    String(
                        phone.telegram_username
                    ).replace(
                        /^@/,
                        ''
                    )
                );

            },


            telegramUserId(driver) {

                const phone =
                    this.primaryResolvedPhone(
                        driver
                    );


                if (
                    !phone?.telegram_user_id
                ) {

                    return 'ID: —';

                }


                return (
                    'ID: '
                    +
                    phone.telegram_user_id
                );

            },


            primaryPhone(driver) {

                const phone =
                    this.primaryResolvedPhone(
                        driver
                    );


                return (
                    phone?.phone
                    ??
                    phone?.phone_normalized
                    ??
                    '—'
                );

            },


            matchScore(driver) {

                const value =
                    driver?.stats?.avg_match_score;


                if (
                    value === null
                    ||
                    value === undefined
                    ||
                    value === ''
                ) {

                    return '—';

                }


                const score =
                    Number(value);


                if (
                    Number.isNaN(score)
                ) {

                    return '—';

                }


                return (
                    score.toFixed(2)
                    + '%'
                );

            },


            comparisonContainerClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        border-green-200
                        dark:border-green-900/40
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        border-red-200
                        dark:border-red-900/40
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        border-amber-200
                        dark:border-amber-900/40
                    `;

                }


                return `
                    border-gray-200
                    dark:border-gray-800
                `;

            },


            comparisonHeaderClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        border-green-100
                        bg-green-50
                        dark:border-green-900/30
                        dark:bg-green-500/[0.05]
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        border-red-100
                        bg-red-50
                        dark:border-red-900/30
                        dark:bg-red-500/[0.05]
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        border-amber-100
                        bg-amber-50
                        dark:border-amber-900/30
                        dark:bg-amber-500/[0.05]
                    `;

                }


                return `
                    border-gray-100
                    bg-gray-50
                    dark:border-gray-800
                    dark:bg-white/[0.02]
                `;

            },


            comparisonIconClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        bg-green-100
                        text-green-700
                        dark:bg-green-500/10
                        dark:text-green-300
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        bg-red-100
                        text-red-700
                        dark:bg-red-500/10
                        dark:text-red-300
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        bg-amber-100
                        text-amber-700
                        dark:bg-amber-500/10
                        dark:text-amber-300
                    `;

                }


                return `
                    bg-gray-100
                    text-gray-600
                    dark:bg-white/[0.06]
                    dark:text-gray-300
                `;

            },


            comparisonTitleClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        text-green-800
                        dark:text-green-300
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        text-red-800
                        dark:text-red-300
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        text-amber-800
                        dark:text-amber-300
                    `;

                }


                return `
                    text-gray-800
                    dark:text-gray-200
                `;

            },


            comparisonSubtitleClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        text-green-700/70
                        dark:text-green-300/60
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        text-red-700/70
                        dark:text-red-300/60
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        text-amber-700/70
                        dark:text-amber-300/60
                    `;

                }


                return `
                    text-gray-500
                    dark:text-gray-400
                `;

            },


            comparisonScoreClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        bg-green-100
                        text-green-700
                        dark:bg-green-500/10
                        dark:text-green-300
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        bg-red-100
                        text-red-700
                        dark:bg-red-500/10
                        dark:text-red-300
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        bg-amber-100
                        text-amber-700
                        dark:bg-amber-500/10
                        dark:text-amber-300
                    `;

                }


                return `
                    bg-gray-100
                    text-gray-600
                    dark:bg-white/[0.06]
                    dark:text-gray-300
                `;

            },


            comparisonArrowClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        border-green-200
                        text-green-600
                        dark:border-green-800
                        dark:text-green-400
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        border-red-200
                        text-red-600
                        dark:border-red-800
                        dark:text-red-400
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        border-amber-200
                        text-amber-600
                        dark:border-amber-800
                        dark:text-amber-400
                    `;

                }


                return `
                    border-gray-200
                    text-gray-400
                    dark:border-gray-700
                    dark:text-gray-500
                `;

            },


            comparisonFooterClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        border-green-100
                        bg-green-50/40
                        dark:border-green-900/30
                        dark:bg-green-500/[0.03]
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        border-red-100
                        bg-red-50/40
                        dark:border-red-900/30
                        dark:bg-red-500/[0.03]
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        border-amber-100
                        bg-amber-50/40
                        dark:border-amber-900/30
                        dark:bg-amber-500/[0.03]
                    `;

                }


                return `
                    border-gray-100
                    bg-gray-50/60
                    dark:border-gray-800
                    dark:bg-white/[0.01]
                `;

            },


            /*
            |--------------------------------------------------------------------------
            | AVATAR
            |--------------------------------------------------------------------------
            */

            driverAvatarClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        bg-green-100
                        text-green-700
                        ring-green-200
                        dark:bg-green-500/10
                        dark:text-green-300
                        dark:ring-green-400/20
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        bg-red-100
                        text-red-700
                        ring-red-200
                        dark:bg-red-500/10
                        dark:text-red-300
                        dark:ring-red-400/20
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        bg-amber-100
                        text-amber-700
                        ring-amber-200
                        dark:bg-amber-500/10
                        dark:text-amber-300
                        dark:ring-amber-400/20
                    `;

                }


                return `
                    bg-gray-100
                    text-gray-600
                    ring-gray-200
                    dark:bg-white/[0.06]
                    dark:text-gray-300
                    dark:ring-gray-700
                `;

            },


            /*
            |--------------------------------------------------------------------------
            | STATUS BADGE
            |--------------------------------------------------------------------------
            */

            statusBadgeClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        bg-green-100
                        text-green-700
                        dark:bg-green-500/10
                        dark:text-green-300
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        bg-red-100
                        text-red-700
                        dark:bg-red-500/10
                        dark:text-red-300
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        bg-amber-100
                        text-amber-700
                        dark:bg-amber-500/10
                        dark:text-amber-300
                    `;

                }


                return `
                    bg-gray-100
                    text-gray-600
                    dark:bg-white/[0.06]
                    dark:text-gray-300
                `;

            },


            statusDotClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return 'bg-green-500 dark:bg-green-400';

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return 'bg-red-500 dark:bg-red-400';

                }


                if (
                    type === 'pending'
                ) {

                    return 'bg-amber-500 dark:bg-amber-400';

                }


                return 'bg-gray-400 dark:bg-gray-500';

            },


            /*
            |--------------------------------------------------------------------------
            | STATUS SUMMARY
            |--------------------------------------------------------------------------
            */

            statusSummaryClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        border-green-200
                        bg-green-100/70
                        dark:border-green-900/40
                        dark:bg-green-500/[0.07]
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        border-red-200
                        bg-red-100/70
                        dark:border-red-900/40
                        dark:bg-red-500/[0.07]
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        border-amber-200
                        bg-amber-100/70
                        dark:border-amber-900/40
                        dark:bg-amber-500/[0.06]
                    `;

                }


                return `
                    border-gray-200
                    bg-gray-50
                    dark:border-gray-800
                    dark:bg-white/[0.02]
                `;

            },


            statusIconContainerClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        bg-green-200
                        text-green-700
                        dark:bg-green-500/10
                        dark:text-green-300
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        bg-red-200
                        text-red-700
                        dark:bg-red-500/10
                        dark:text-red-300
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        bg-amber-200
                        text-amber-700
                        dark:bg-amber-500/10
                        dark:text-amber-300
                    `;

                }


                return `
                    bg-gray-200
                    text-gray-600
                    dark:bg-white/[0.06]
                    dark:text-gray-300
                `;

            },


            statusTextClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        text-green-800
                        dark:text-green-300
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        text-red-800
                        dark:text-red-300
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        text-amber-800
                        dark:text-amber-300
                    `;

                }


                return `
                    text-gray-700
                    dark:text-gray-300
                `;

            },


            statusDescriptionClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        text-green-700/80
                        dark:text-green-300/70
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        text-red-700/80
                        dark:text-red-300/70
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        text-amber-700/80
                        dark:text-amber-300/70
                    `;

                }


                return `
                    text-gray-500
                    dark:text-gray-400
                `;

            },


            /*
            |--------------------------------------------------------------------------
            | COUNTER
            |--------------------------------------------------------------------------
            */

            counterClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        bg-green-100
                        text-green-700
                        dark:bg-green-500/10
                        dark:text-green-300
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        bg-red-100
                        text-red-700
                        dark:bg-red-500/10
                        dark:text-red-300
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        bg-amber-100
                        text-amber-700
                        dark:bg-amber-500/10
                        dark:text-amber-300
                    `;

                }


                return `
                    bg-gray-100
                    text-gray-600
                    dark:bg-white/[0.06]
                    dark:text-gray-300
                `;

            },


            /*
            |--------------------------------------------------------------------------
            | PHONE CARDS
            |--------------------------------------------------------------------------
            */

            phoneCardClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        border-green-200
                        bg-white/70
                        dark:border-green-900/30
                        dark:bg-green-500/[0.03]
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        border-red-200
                        bg-white/70
                        dark:border-red-900/30
                        dark:bg-red-500/[0.03]
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        border-amber-200
                        bg-white/70
                        dark:border-amber-900/30
                        dark:bg-amber-500/[0.03]
                    `;

                }


                return `
                    border-gray-200
                    bg-gray-50
                    dark:border-gray-800
                    dark:bg-white/[0.03]
                `;

            },


            phoneTextClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        text-green-900
                        dark:text-green-100
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        text-red-900
                        dark:text-red-100
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        text-amber-900
                        dark:text-amber-100
                    `;

                }


                return `
                    text-gray-900
                    dark:text-white
                `;

            },


            phoneSecondaryClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        text-green-700/70
                        dark:text-green-300/70
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        text-red-700/70
                        dark:text-red-300/70
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        text-amber-700/70
                        dark:text-amber-300/70
                    `;

                }


                return `
                    text-gray-500
                    dark:text-gray-400
                `;

            },


            phoneIdClass(driver) {

                const type =
                    this.statusType(driver);


                if (
                    type === 'confirmed'
                ) {

                    return `
                        bg-green-100
                        text-green-600
                        dark:bg-green-500/10
                        dark:text-green-400
                    `;

                }


                if (
                    type === 'not_confirmed'
                ) {

                    return `
                        bg-red-100
                        text-red-600
                        dark:bg-red-500/10
                        dark:text-red-400
                    `;

                }


                if (
                    type === 'pending'
                ) {

                    return `
                        bg-amber-100
                        text-amber-600
                        dark:bg-amber-500/10
                        dark:text-amber-400
                    `;

                }


                return `
                    bg-white
                    text-gray-400
                    dark:bg-gray-900
                    dark:text-gray-500
                `;

            },


            /*
            |--------------------------------------------------------------------------
            | HELPERS
            |--------------------------------------------------------------------------
            */

            number(value) {

                return Number(
                    value || 0
                ).toLocaleString();

            },


            percent(value) {

                if (

                    value === null

                    ||

                    value === undefined

                    ||

                    value === ''

                ) {

                    return '0.0';

                }


                return Number(
                    value
                ).toFixed(1);

            },


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
                                .toUpperCase()
                    )
                    .join('');

            },


            formatDate(
                value,
                withTime = false
            ) {

                if (!value) {

                    return '—';

                }


                let normalized =
                    String(value);


                if (

                    !normalized.includes('T')

                    &&

                    normalized.includes(' ')

                ) {

                    normalized =
                        normalized.replace(
                            ' ',
                            'T'
                        );

                }


                const date =
                    new Date(normalized);


                if (
                    Number.isNaN(
                        date.getTime()
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

                        }

                ).format(date);

            },

        };
    }
</script>

@endpush