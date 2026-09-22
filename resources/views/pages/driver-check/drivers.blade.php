@extends('layouts.app')

@section('title', __('telegram.drivers.title'))

@section('content')

<div
    x-data="dcDrivers({
        endpoints: {
            index: @js(route('api.telegram.drivers')),
        },
        operatorBaseUrl: @js(url('/driver-check/operation-users')),
        exportBaseUrl: @js(route('driver-check.export.details')),
        translations: @js(__('telegram.drivers')),
        ui: @js(__('telegram.ui')),
    })"
>
    <x-driver-check.shell>

        {{-- ============================================================
             Header
        ============================================================= --}}
        <x-driver-check.page-header
            icon="truck"
            tone="brand"
            eyebrow="Telegram"
            :title="__('telegram.drivers.title')"
            :description="__('telegram.drivers.description')"
        >
            <x-slot:actions>
                <x-driver-check.button x-on:click="load()" ::disabled="loading">
                    <x-driver-check.icon
                        name="refresh"
                        class="h-4 w-4"
                        ::class="loading && 'animate-spin'"
                    />
                    <span x-text="loading ? ui.loading : ui.refresh"></span>
                </x-driver-check.button>

                <x-driver-check.button
                    variant="success"
                    icon="download"
                    ::href="exportUrl()"
                    :href="route('driver-check.export.details')"
                    :title="__('telegram.drivers.export.hint')"
                >
                    {{ __('telegram.drivers.export.button') }}
                </x-driver-check.button>
            </x-slot:actions>
        </x-driver-check.page-header>

        {{-- ============================================================
             Filters
        ============================================================= --}}
        <x-driver-check.filters :search-placeholder="__('telegram.drivers.filters.search_placeholder')">
            <x-driver-check.field
                :label="__('telegram.drivers.filters.period')"
                class="sm:col-span-2 xl:col-span-4"
            >
                <div class="flex flex-col gap-2.5">
                    <x-driver-check.chips />

                    <div x-show="periodPreset === 'custom'" x-cloak class="grid grid-cols-2 gap-2 sm:max-w-md">
                        <x-driver-check.input
                            type="date"
                            x-model="filters.period_from"
                            x-on:change="applyCustomPeriod()"
                            :aria-label="__('telegram.drivers.filters.period_from')"
                        />
                        <x-driver-check.input
                            type="date"
                            x-model="filters.period_to"
                            x-on:change="applyCustomPeriod()"
                            :aria-label="__('telegram.drivers.filters.period_to')"
                        />
                    </div>
                </div>
            </x-driver-check.field>

            <x-driver-check.field :label="__('telegram.drivers.filters.status')" class="sm:col-span-2">
                <x-driver-check.select x-model="filters.status" x-on:change="applyFilters()">
                    <option value="">{{ __('telegram.drivers.filters.status_all') }}</option>
                    <option value="confirmed">{{ __('telegram.drivers.filters.confirmed_status') }}</option>
                    <option value="not_confirmed">{{ __('telegram.drivers.filters.not_confirmed_status') }}</option>
                    <option value="pending">{{ __('telegram.drivers.filters.pending_status') }}</option>
                </x-driver-check.select>
            </x-driver-check.field>

            <x-driver-check.field :label="__('telegram.drivers.filters.score_range')" class="sm:col-span-2">
                <div class="grid grid-cols-2 gap-2">
                    <x-driver-check.input
                        type="number"
                        inputmode="numeric"
                        min="0"
                        max="100"
                        x-model="filters.min_match_score"
                        x-on:input.debounce.500ms="applyFilters()"
                        :placeholder="__('telegram.drivers.filters.score_from')"
                    />
                    <x-driver-check.input
                        type="number"
                        inputmode="numeric"
                        min="0"
                        max="100"
                        x-model="filters.max_match_score"
                        x-on:input.debounce.500ms="applyFilters()"
                        :placeholder="__('telegram.drivers.filters.score_to')"
                    />
                </div>
            </x-driver-check.field>
        </x-driver-check.filters>

        <x-driver-check.error-alert :title="__('telegram.drivers.errors.load_failed')" />

        {{-- ============================================================
             Rows
        ============================================================= --}}
        <x-driver-check.surface class="overflow-hidden" x-ref="listTop">
            <x-driver-check.list-toolbar
                :title="__('telegram.drivers.title')"
                :sort-options="[
                    'created_at' => __('telegram.drivers.filters.created'),
                    'name' => __('telegram.drivers.filters.name'),
                    'checks' => __('telegram.drivers.filters.checks'),
                    'confirmed' => __('telegram.drivers.filters.confirmed'),
                    'not_confirmed' => __('telegram.drivers.filters.not_confirmed'),
                    'best_match_score' => __('telegram.drivers.filters.best_match_score'),
                    'last_check_at' => __('telegram.drivers.filters.last_check'),
                ]"
            />

            {{-- Table (lg and up) --}}
            <div class="hidden lg:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1040px] table-fixed text-left">
                        <colgroup>
                            <col class="w-[24%]">
                            <col class="w-[17%]">
                            <col class="w-[12%]">
                            <col class="w-[8%]">
                            <col class="w-[16%]">
                            <col class="w-[9%]">
                            <col class="w-[14%]">
                        </colgroup>

                        <thead class="border-b border-gray-200 bg-gray-50/70 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr class="text-[10px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                                <th scope="col" class="px-4 py-2.5 font-semibold">{{ __('telegram.drivers.table.driver') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.drivers.table.operator') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.drivers.table.status') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.drivers.table.checks') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.drivers.table.result') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.drivers.table.score') }}</th>
                                <th scope="col" class="px-4 py-2.5 font-semibold">{{ __('telegram.drivers.table.last_check') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <x-driver-check.skeleton-rows :cols="7" />

                            <template x-for="row in rows" :key="row.id">
                                <tr class="group align-middle transition hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                    {{-- Driver --}}
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <x-driver-check.avatar
                                                size="sm"
                                                ::class="statusTone(row.status, 'soft')"
                                                x-text="initials(row.name)"
                                            />

                                            <div class="min-w-0">
                                                <p
                                                    class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                                    :title="row.name"
                                                    x-text="row.name || dash"
                                                ></p>

                                                <p class="flex items-center gap-1.5 truncate text-[11px] text-gray-400 dark:text-gray-500">
                                                    <span class="tabular-nums">
                                                        {{ __('telegram.drivers.table.id') }} <span x-text="row.id"></span>
                                                    </span>

                                                    <template x-if="phoneCount(row) > 0">
                                                        <span class="inline-flex items-center gap-1">
                                                            <span aria-hidden="true">&middot;</span>
                                                            <x-driver-check.icon name="phone" class="h-3 w-3" />
                                                            <span class="tabular-nums" x-text="phoneCount(row)"></span>
                                                        </span>
                                                    </template>
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Operator --}}
                                    <td class="px-3 py-3">
                                        <template x-if="row.operation_user">
                                            <div class="min-w-0">
                                                <a
                                                    :href="operatorUrl(row)"
                                                    class="block truncate text-[13px] font-medium text-gray-900 transition
                                                           hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                                                    x-text="row.operation_user.name"
                                                ></a>

                                                <a
                                                    :href="telegramUrl(row.operation_user.telegram_username)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="block truncate text-[11px] transition"
                                                    :class="row.operation_user.telegram_username
                                                        ? 'text-brand-600 hover:underline dark:text-brand-400'
                                                        : 'text-gray-400 dark:text-gray-500'"
                                                    x-text="handle(row.operation_user.telegram_username) || ''"
                                                ></a>
                                            </div>
                                        </template>

                                        <template x-if="!row.operation_user">
                                            <span class="text-[13px] text-gray-400 dark:text-gray-500">
                                                {{ __('telegram.drivers.table.no_operator') }}
                                            </span>
                                        </template>
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-3 py-3">
                                        <x-driver-check.badge ::class="statusTone(row.status)">
                                            <span class="h-1.5 w-1.5 rounded-full" :class="statusTone(row.status, 'dot')"></span>
                                            <span x-text="statusLabel(row.status)"></span>
                                        </x-driver-check.badge>
                                    </td>

                                    <td
                                        class="px-3 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300"
                                        x-text="number(stats(row).checks)"
                                    ></td>

                                    {{-- Result buckets --}}
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <template x-if="stats(row).confirmed > 0">
                                                <x-driver-check.badge class="bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                                    <x-driver-check.icon name="check" class="h-3 w-3" />
                                                    <span class="tabular-nums" x-text="number(stats(row).confirmed)"></span>
                                                </x-driver-check.badge>
                                            </template>

                                            <template x-if="stats(row).not_confirmed > 0">
                                                <x-driver-check.badge class="bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400">
                                                    <x-driver-check.icon name="close" class="h-3 w-3" />
                                                    <span class="tabular-nums" x-text="number(stats(row).not_confirmed)"></span>
                                                </x-driver-check.badge>
                                            </template>

                                            <template x-if="stats(row).pending > 0">
                                                <x-driver-check.badge class="bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                                                    <x-driver-check.icon name="clock" class="h-3 w-3" />
                                                    <span class="tabular-nums" x-text="number(stats(row).pending)"></span>
                                                </x-driver-check.badge>
                                            </template>

                                            <template x-if="stats(row).processing > 0">
                                                <x-driver-check.badge class="bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/10 dark:text-blue-light-400">
                                                    <x-driver-check.icon name="refresh" class="h-3 w-3" />
                                                    <span class="tabular-nums" x-text="number(stats(row).processing)"></span>
                                                </x-driver-check.badge>
                                            </template>
                                        </div>
                                    </td>

                                    {{-- Score --}}
                                    <td class="px-3 py-3 text-right">
                                        <p
                                            class="text-[13px] tabular-nums text-gray-700 dark:text-gray-200"
                                            x-text="score(stats(row).avg_match_score)"
                                        ></p>
                                        <p
                                            class="text-[11px] tabular-nums text-gray-400 dark:text-gray-500"
                                            x-text="score(stats(row).best_match_score)"
                                        ></p>
                                    </td>

                                    {{-- Last check --}}
                                    <td class="px-4 py-3">
                                        <p
                                            class="text-[13px] text-gray-700 dark:text-gray-300"
                                            :title="stats(row).last_check_at ? date(stats(row).last_check_at) : ''"
                                            x-text="stats(row).last_check_at
                                                ? relative(stats(row).last_check_at)
                                                : translations.dates.never"
                                        ></p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Cards (below lg) --}}
            <div class="divide-y divide-gray-100 lg:hidden dark:divide-gray-800">
                <x-driver-check.skeleton-cards />

                <template x-for="row in rows" :key="'card-' + row.id">
                    <article class="flex flex-col gap-3 p-3.5">
                        <div class="flex items-start gap-3">
                            <x-driver-check.avatar
                                ::class="statusTone(row.status, 'soft')"
                                x-text="initials(row.name)"
                            />

                            <div class="min-w-0 flex-1">
                                <p class="dc-break text-sm font-semibold text-gray-900 dark:text-white" x-text="row.name || dash"></p>

                                <p class="mt-0.5 truncate text-[12px] text-gray-500 dark:text-gray-400">
                                    <template x-if="row.operation_user">
                                        <a
                                            :href="operatorUrl(row)"
                                            class="font-medium text-brand-600 dark:text-brand-400"
                                            x-text="row.operation_user.name"
                                        ></a>
                                    </template>

                                    <template x-if="!row.operation_user">
                                        <span>{{ __('telegram.drivers.table.no_operator') }}</span>
                                    </template>
                                </p>
                            </div>

                            <x-driver-check.badge ::class="statusTone(row.status)">
                                <span class="h-1.5 w-1.5 rounded-full" :class="statusTone(row.status, 'dot')"></span>
                                <span x-text="statusLabel(row.status)"></span>
                            </x-driver-check.badge>
                        </div>

                        <dl class="grid grid-cols-3 gap-2 rounded-xl bg-gray-50 p-3 dark:bg-white/[0.02]">
                            <x-driver-check.kv :label="__('telegram.drivers.table.checks')">
                                <span class="font-medium tabular-nums" x-text="number(stats(row).checks)"></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.drivers.table.score')">
                                <span class="font-medium tabular-nums" x-text="score(stats(row).avg_match_score)"></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.drivers.table.phones')">
                                <span class="font-medium tabular-nums" x-text="phoneCount(row)"></span>
                            </x-driver-check.kv>
                        </dl>

                        <div class="flex flex-wrap items-center gap-1.5">
                            <template x-if="stats(row).confirmed > 0">
                                <x-driver-check.badge class="bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                    <x-driver-check.icon name="check" class="h-3 w-3" />
                                    <span>{{ __('telegram.drivers.status.confirmed') }}</span>
                                    <span class="font-semibold tabular-nums" x-text="number(stats(row).confirmed)"></span>
                                </x-driver-check.badge>
                            </template>

                            <template x-if="stats(row).not_confirmed > 0">
                                <x-driver-check.badge class="bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400">
                                    <x-driver-check.icon name="close" class="h-3 w-3" />
                                    <span>{{ __('telegram.drivers.status.not_confirmed') }}</span>
                                    <span class="font-semibold tabular-nums" x-text="number(stats(row).not_confirmed)"></span>
                                </x-driver-check.badge>
                            </template>

                            <template x-if="stats(row).pending > 0">
                                <x-driver-check.badge class="bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                                    <x-driver-check.icon name="clock" class="h-3 w-3" />
                                    <span>{{ __('telegram.drivers.status.pending') }}</span>
                                    <span class="font-semibold tabular-nums" x-text="number(stats(row).pending)"></span>
                                </x-driver-check.badge>
                            </template>
                        </div>

                        <p class="flex items-center gap-1.5 text-[11px] text-gray-400 dark:text-gray-500">
                            <x-driver-check.icon name="clock" class="h-3.5 w-3.5" />
                            <span>{{ __('telegram.drivers.table.last_check') }}:</span>
                            <span
                                class="text-gray-600 dark:text-gray-300"
                                x-text="stats(row).last_check_at
                                    ? date(stats(row).last_check_at)
                                    : translations.dates.never"
                            ></span>
                        </p>
                    </article>
                </template>
            </div>

            <x-driver-check.empty-state
                icon="truck"
                :title="__('telegram.drivers.empty.title')"
                :description="__('telegram.drivers.empty.description')"
            />

            <x-driver-check.pagination />
        </x-driver-check.surface>
    </x-driver-check.shell>
</div>

@endsection
