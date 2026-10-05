@extends('layouts.app')

@section('title', __('telegram.operation_user.title'))

@section('content')

<div
    x-data="dcOperationUser({
        operationUserId: @js($operationUser->id),
        operatorUrl: @js(route('api.telegram.operation-users.show', $operationUser->id)),
        driversUrl: @js(route('api.telegram.operation-users.drivers', $operationUser->id)),
        exportBaseUrl: @js(route('driver-check.export.details')),
        translations: @js(__('telegram.operation_user')),
        ui: @js(__('telegram.ui')),
    })"
>
    <x-driver-check.shell>

        {{-- ============================================================
             Back
        ============================================================= --}}
        <a
            href="{{ route('driver-check.operation-users') }}"
            class="dc-tap -mb-1 inline-flex w-fit items-center gap-1.5 rounded-lg py-1 text-sm
                   font-medium text-gray-500 transition hover:text-gray-900
                   dark:text-gray-400 dark:hover:text-white"
        >
            <x-driver-check.icon name="arrow-left" class="h-4 w-4" />
            {{ __('telegram.operation_user.back') }}
        </a>

        {{-- ============================================================
             Operator
        ============================================================= --}}
        <x-driver-check.surface class="p-4 sm:p-5">

            {{-- Loading --}}
            <div x-show="operatorLoading && !operator" x-cloak class="flex items-center gap-4">
                <div class="dc-skeleton h-14 w-14 shrink-0 rounded-full bg-gray-100 sm:h-16 sm:w-16 dark:bg-white/[0.06]"></div>

                <div class="flex-1 space-y-2.5">
                    <div class="dc-skeleton h-4 w-2/5 rounded bg-gray-100 dark:bg-white/[0.06]"></div>
                    <div class="dc-skeleton h-3 w-1/4 rounded bg-gray-100 dark:bg-white/[0.06]"></div>
                </div>
            </div>

            <div x-show="operator" x-cloak class="flex flex-col gap-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 items-center gap-3.5">
                        <x-driver-check.avatar
                            size="lg"
                            class="bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
                            x-text="initials(operator?.name)"
                        />

                        <div class="min-w-0">
                            <h1
                                class="dc-break text-lg font-semibold tracking-tight text-gray-900 sm:text-xl dark:text-white"
                                x-text="operator?.name || translations.unknown"
                            ></h1>

                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                <span
                                    class="group inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2 py-1
                                           text-[11px] font-medium text-gray-600
                                           dark:bg-white/[0.06] dark:text-gray-300"
                                >
                                    <x-driver-check.icon name="telegram" class="h-3.5 w-3.5" />
                                    <a
                                        :href="telegramUrl(operator?.telegram_username)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        :class="operator?.telegram_username && 'text-brand-600 hover:underline dark:text-brand-400'"
                                        x-text="handle(operator?.telegram_username) || translations.no_username"
                                    ></a>

                                    <template x-if="operator?.telegram_username">
                                        <x-driver-check.copy-button value="operator.telegram_username" />
                                    </template>
                                </span>

                                <span
                                    x-show="operator?.telegram_id"
                                    class="group inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2 py-1
                                           text-[11px] font-medium tabular-nums text-gray-600
                                           dark:bg-white/[0.06] dark:text-gray-300"
                                >
                                    <span x-text="translations.id"></span>
                                    <span x-text="operator?.telegram_id"></span>

                                    <template x-if="operator?.telegram_id">
                                        <x-driver-check.copy-button value="operator.telegram_id" />
                                    </template>
                                </span>

                                <span
                                    x-show="operatorStats().last_check_at"
                                    class="inline-flex items-center gap-1 rounded-lg bg-gray-100 px-2 py-1
                                           text-[11px] font-medium text-gray-600
                                           dark:bg-white/[0.06] dark:text-gray-300"
                                >
                                    <x-driver-check.icon name="clock" class="h-3.5 w-3.5" />
                                    <span x-text="relative(operatorStats().last_check_at)"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 lg:flex lg:shrink-0">
                        <x-driver-check.button
                            x-on:click="refreshAll()"
                            ::disabled="operatorLoading || loading"
                        >
                            <x-driver-check.icon
                                name="refresh"
                                class="h-4 w-4"
                                ::class="(operatorLoading || loading) && 'animate-spin'"
                            />
                            <span x-text="(operatorLoading || loading) ? ui.loading : ui.refresh"></span>
                        </x-driver-check.button>

                        <x-driver-check.button
                            icon="download"
                            :href="route('driver-check.export.details', ['operation_user_id' => $operationUser->id])"
                            ::href="exportUrl()"
                            :title="__('telegram.operation_user.export.hint')"
                        >
                            {{ __('telegram.operation_user.export.button') }}
                        </x-driver-check.button>
                    </div>
                </div>

                {{-- Operator counters --}}
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 xl:grid-cols-5">
                    <x-driver-check.stat-card :label="__('telegram.operation_user.stats.drivers')" icon="truck"
                        chip="bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                        <span x-text="number(operatorStats().drivers)">0</span>
                    </x-driver-check.stat-card>

                    <x-driver-check.stat-card :label="__('telegram.operation_user.stats.checks')" icon="shield">
                        <span x-text="number(operatorStats().checks)">0</span>
                    </x-driver-check.stat-card>

                    <x-driver-check.stat-card
                        :label="__('telegram.operation_user.stats.confirmed')"
                        icon="check-circle"
                        tone="text-success-600 dark:text-success-400"
                        chip="bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400"
                    >
                        <span x-text="number(operatorStats().confirmed)">0</span>
                    </x-driver-check.stat-card>

                    <x-driver-check.stat-card
                        :label="__('telegram.operation_user.stats.not_confirmed')"
                        icon="x-circle"
                        tone="text-error-600 dark:text-error-400"
                        chip="bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400"
                    >
                        <span x-text="number(operatorStats().not_confirmed)">0</span>
                    </x-driver-check.stat-card>

                    <x-driver-check.stat-card
                        :label="__('telegram.operation_user.stats.match_rate')"
                        icon="trending"
                        chip="bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/10 dark:text-blue-light-400"
                        class="col-span-2 sm:col-span-1"
                    >
                        <span x-text="percent(operatorStats().match_rate) + '%'">0%</span>
                    </x-driver-check.stat-card>
                </div>
            </div>
        </x-driver-check.surface>

        <x-driver-check.error-alert
            model="operatorError"
            retry="loadOperator()"
            :title="__('telegram.operation_user.errors.load_operator')"
        />

        <x-driver-check.error-alert :title="__('telegram.operation_user.errors.load_drivers')" />

        {{-- ============================================================
             Drivers: one card - period and status on top, a row per
             driver, the count and paging at the bottom
        ============================================================= --}}
        <x-driver-check.surface class="overflow-hidden" x-ref="listTop">
            <div class="relative flex flex-col gap-3 border-b border-gray-200 p-3 sm:p-4 dark:border-gray-800">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <x-driver-check.chips class="lg:flex-1" />

                    <div class="flex items-center gap-2">
                        <x-driver-check.select
                            x-model="filters.status"
                            x-on:change="applyFilters()"
                            class="flex-1 lg:w-56 lg:flex-none"
                            :aria-label="__('telegram.operation_user.filters.status')"
                        >
                            <option value="">{{ __('telegram.operation_user.filters.status_all') }}</option>
                            <option value="confirmed">{{ __('telegram.operation_user.filters.confirmed') }}</option>
                            <option value="not_confirmed">{{ __('telegram.operation_user.filters.not_confirmed') }}</option>
                            <option value="pending">{{ __('telegram.operation_user.filters.pending') }}</option>
                            <option value="processing">{{ __('telegram.operation_user.filters.processing') }}</option>
                        </x-driver-check.select>

                        <button
                            type="button"
                            x-show="hasActiveFilters()"
                            x-cloak
                            x-on:click="resetFilters()"
                            class="dc-tap inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-gray-500
                                   transition hover:bg-gray-100 hover:text-gray-900 sm:h-10 sm:w-10
                                   dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-white"
                            title="{{ __('telegram.operation_user.filters.clear') }}"
                            aria-label="{{ __('telegram.operation_user.filters.clear') }}"
                        >
                            <x-driver-check.icon name="close" class="h-4 w-4" />
                        </button>

                        <x-driver-check.select
                            x-model.number="filters.per_page"
                            x-on:change="changePerPage()"
                            class="w-20 shrink-0"
                            aria-label="{{ __('telegram.ui.per_page') }}"
                        >
                            @foreach ([10, 20, 50, 100] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </x-driver-check.select>
                    </div>
                </div>

                <div x-show="periodPreset === 'custom'" x-cloak class="grid grid-cols-2 gap-2 sm:max-w-md">
                    <x-driver-check.input
                        type="date"
                        x-model="filters.period_from"
                        x-on:change="applyCustomPeriod()"
                        :aria-label="__('telegram.operation_user.filters.from')"
                    />
                    <x-driver-check.input
                        type="date"
                        x-model="filters.period_to"
                        x-on:change="applyCustomPeriod()"
                        :aria-label="__('telegram.operation_user.filters.to')"
                    />
                </div>

                <div x-show="loading" x-cloak class="dc-progress absolute inset-x-0 -bottom-px h-0.5 overflow-hidden">
                    <span class="block h-full w-1/3 rounded-full bg-brand-500"></span>
                </div>
            </div>

            {{-- Column heads, desktop only: the rows below line up under them --}}
            <div
                class="dc-thead hidden grid-cols-[minmax(0,1.25fr)_minmax(0,1.6fr)_minmax(0,1fr)_9rem] gap-4 border-b border-gray-200
                       bg-gray-50/70 px-5 py-2.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-gray-500
                       lg:grid dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400"
            >
                <span>{{ __('telegram.drivers.table.driver') }}</span>
                <span>Telegram</span>
                <span>{{ __('telegram.operation_user.drivers.phone') }}</span>
                <span class="text-right">{{ __('telegram.drivers.table.status') }}</span>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                <x-driver-check.skeleton-cards :count="5" />

                <template x-for="driver in rows" :key="driver.id">
                    <article class="relative transition hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                        {{-- Status stripe: the row's verdict, readable before any text --}}
                        <span
                            class="absolute inset-y-0 left-0 w-1"
                            :class="statusTone(driver.status, 'stripe')"
                            aria-hidden="true"
                        ></span>

                        <div
                            class="grid gap-3 py-3.5 pl-5 pr-4 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,1.6fr)_minmax(0,1fr)_9rem]
                                   lg:items-center lg:gap-4"
                        >
                            {{-- Driver, as the group message named them --}}
                            <div class="flex min-w-0 items-center gap-3">
                                <x-driver-check.avatar
                                    ::class="statusTone(driver.status, 'soft')"
                                    x-text="initials(driver.name)"
                                />

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white" :title="driver.name" x-text="driver.name || dash"></p>

                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[11px] tabular-nums text-gray-400 dark:text-gray-500">
                                        <span><span x-text="translations.id"></span> <span x-text="driver.id"></span></span>
                                        <span aria-hidden="true">&middot;</span>
                                        <span>
                                            <span x-text="number(driver.stats?.checks ?? 0)"></span>
                                            <span x-text="translations.drivers.checks"></span>
                                        </span>
                                    </p>
                                </div>

                                {{-- Phone-sized screens: the verdict next to the name --}}
                                <x-driver-check.badge ::class="statusTone(driver.status)" class="lg:hidden">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusTone(driver.status, 'dot')"></span>
                                    <span x-text="driverLabel(driver)"></span>
                                </x-driver-check.badge>
                            </div>

                            {{-- Who Telegram says it is, and how close the names are --}}
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="inline-flex h-9 w-14 shrink-0 items-center justify-center rounded-lg text-[12px] font-semibold tabular-nums ring-1 ring-inset"
                                    :class="driverScore(driver) === null
                                        ? 'bg-gray-50 text-gray-400 ring-gray-200 dark:bg-white/[0.03] dark:ring-gray-800'
                                        : statusTone(driver.status, 'soft') + ' ' + statusTone(driver.status, 'ring')"
                                    :title="driverDescription(driver)"
                                    x-text="driverScore(driver) === null ? dash : score(driverScore(driver), 0) + '%'"
                                ></span>

                                <div class="min-w-0">
                                    <p class="truncate text-[13px] font-medium text-gray-900 dark:text-white" :title="telegramName(driver)" x-text="telegramName(driver)"></p>
                                    <p class="truncate text-[11px] text-gray-500 dark:text-gray-400">
                                        <span x-text="telegramUsername(driver)"></span>
                                        <template x-if="telegramUserId(driver)">
                                            <span class="tabular-nums">
                                                &middot; <span x-text="translations.id"></span>
                                                <span x-text="telegramUserId(driver)"></span>
                                            </span>
                                        </template>
                                    </p>
                                </div>
                            </div>

                            {{-- Phones: the latest, the rest one click away --}}
                            <div class="min-w-0">
                                <template x-if="phones(driver).length === 0">
                                    <p class="text-[12px] text-gray-400 dark:text-gray-500" x-text="translations.drivers.no_resolved_phones"></p>
                                </template>

                                <template x-if="phones(driver).length > 0">
                                    <div class="group flex items-center gap-1.5">
                                        <x-driver-check.icon name="phone" class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                        <span
                                            class="truncate text-[13px] font-medium tabular-nums text-gray-800 dark:text-gray-100"
                                            x-text="phone(primaryPhone(driver)?.phone)"
                                        ></span>
                                        <x-driver-check.copy-button value="primaryPhone(driver)?.phone" />

                                        <button
                                            type="button"
                                            x-show="phones(driver).length > 1"
                                            x-on:click="toggleExpanded(driver.id)"
                                            class="dc-tap ml-0.5 shrink-0 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold
                                                   tabular-nums text-gray-600 transition hover:bg-brand-50 hover:text-brand-600
                                                   dark:bg-white/[0.06] dark:text-gray-300"
                                            :title="isExpanded(driver.id) ? translations.drivers.hide_phones : translations.drivers.show_phones"
                                            x-text="'+' + (phones(driver).length - 1)"
                                        ></button>
                                    </div>
                                </template>
                            </div>

                            {{-- Verdict, desktop --}}
                            <div class="hidden justify-end lg:flex">
                                <x-driver-check.badge ::class="statusTone(driver.status)">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusTone(driver.status, 'dot')"></span>
                                    <span x-text="driverLabel(driver)"></span>
                                </x-driver-check.badge>
                            </div>
                        </div>

                        {{-- The other phones, unfolded --}}
                        <div x-show="isExpanded(driver.id)" x-collapse x-cloak>
                            <div class="grid gap-1.5 pb-3.5 pl-5 pr-4 sm:grid-cols-2 lg:grid-cols-3">
                                <template x-for="extra in phones(driver).slice(1)" :key="extra.id">
                                    <div class="group flex items-center gap-2 rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-white/[0.03]">
                                        <x-driver-check.icon name="phone" class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[12px] tabular-nums text-gray-800 dark:text-gray-100" x-text="phone(extra.phone)"></p>
                                            <a
                                                :href="telegramUrl(extra.telegram_username)"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="block truncate text-[11px] transition"
                                                :class="extra.telegram_username
                                                    ? 'text-brand-600 hover:underline dark:text-brand-400'
                                                    : 'text-gray-500 dark:text-gray-400'"
                                                x-text="handle(extra.telegram_username) || translations.drivers.no_username"
                                            ></a>
                                        </div>
                                        <x-driver-check.copy-button value="extra.phone" />
                                    </div>
                                </template>
                            </div>
                        </div>
                    </article>
                </template>
            </div>

            <x-driver-check.empty-state
                icon="truck"
                :title="__('telegram.operation_user.drivers.no_drivers')"
                :description="__('telegram.operation_user.drivers.no_drivers_description')"
            />

            <x-driver-check.pagination />
        </x-driver-check.surface>
    </x-driver-check.shell>
</div>

@endsection
