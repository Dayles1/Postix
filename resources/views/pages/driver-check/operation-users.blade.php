@extends('layouts.app')

@section('title', __('telegram.operation_users.title'))

@section('content')

<div
    x-data="dcOperationUsers({
        endpoints: {
            index: @js(route('api.telegram.operation-users')),
        },
        detailBaseUrl: @js(url()->current()),
        exportUrls: {
            operators: @js(route('driver-check.export.operators')),
            details: @js(route('driver-check.export.details')),
        },
        translations: @js(__('telegram.operation_users')),
        ui: @js(__('telegram.ui')),
    })"
>
    <x-driver-check.shell>

        {{-- ============================================================
             Header
        ============================================================= --}}
        <x-driver-check.page-header
            icon="users"
            tone="blue"
            eyebrow="Telegram"
            :title="__('telegram.operation_users.title')"
            :description="__('telegram.operation_users.description')"
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

                <div class="relative" x-data="{ exportOpen: false }" x-on:click.outside="exportOpen = false">
                    <x-driver-check.button
                        variant="success"
                        icon="download"
                        class="w-full"
                        x-on:click="exportOpen = !exportOpen"
                        ::aria-expanded="exportOpen"
                    >
                        {{ __('telegram.operation_users.export.title') }}
                    </x-driver-check.button>

                    <div
                        x-show="exportOpen"
                        x-cloak
                        x-transition.origin.top.right
                        class="absolute right-0 z-30 mt-2 w-64 overflow-hidden rounded-xl border
                               border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-dark"
                    >
                        <a
                            href="{{ route('driver-check.export.operators') }}"
                            :href="exportUrl('operators')"
                            x-on:click="exportOpen = false"
                            class="dc-tap flex items-center gap-2.5 px-4 py-3 text-sm font-medium text-gray-700
                                   transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/[0.05]"
                        >
                            <x-driver-check.icon name="users" class="h-4 w-4 text-gray-400" />
                            {{ __('telegram.operation_users.export.operators') }}
                        </a>

                        <a
                            href="{{ route('driver-check.export.details') }}"
                            :href="exportUrl('details')"
                            x-on:click="exportOpen = false"
                            class="dc-tap flex items-center gap-2.5 border-t border-gray-100 px-4 py-3 text-sm
                                   font-medium text-gray-700 transition hover:bg-gray-50
                                   dark:border-gray-800 dark:text-gray-200 dark:hover:bg-white/[0.05]"
                        >
                            <x-driver-check.icon name="chart" class="h-4 w-4 text-gray-400" />
                            {{ __('telegram.operation_users.export.details') }}
                        </a>

                        <p class="border-t border-gray-100 bg-gray-50 px-4 py-2 text-[11px] leading-snug text-gray-500
                                  dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400">
                            {{ __('telegram.operation_users.export.hint') }}
                        </p>
                    </div>
                </div>
            </x-slot:actions>
        </x-driver-check.page-header>

        {{-- ============================================================
             Counters (whole filtered set, not just this page)
        ============================================================= --}}
        <section class="grid grid-cols-2 gap-2.5 sm:gap-3 xl:grid-cols-4">
            <x-driver-check.stat-card :label="__('telegram.operation_users.stats.users')" icon="users">
                <span x-text="number(pagination.total)">0</span>
            </x-driver-check.stat-card>

            <x-driver-check.stat-card
                :label="__('telegram.operation_users.stats.drivers')"
                icon="truck"
                chip="bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
            >
                <span x-text="compact(globalStats.drivers)">0</span>
            </x-driver-check.stat-card>

            <x-driver-check.stat-card
                :label="__('telegram.operation_users.stats.checks')"
                icon="shield"
                chip="bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/10 dark:text-blue-light-400"
            >
                <span x-text="compact(globalStats.checks)">0</span>
            </x-driver-check.stat-card>

            <x-driver-check.stat-card
                :label="__('telegram.operation_users.stats.avg_match')"
                icon="trending"
                chip="bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400"
            >
                <span x-text="percent(globalStats.match_rate) + '%'">0%</span>
            </x-driver-check.stat-card>
        </section>

        {{-- ============================================================
             Filters
        ============================================================= --}}
        <x-driver-check.filters :search-placeholder="__('telegram.operation_users.filters.search_placeholder')">
            <x-driver-check.field
                :label="__('telegram.operation_users.filters.period')"
                class="sm:col-span-2 xl:col-span-4"
            >
                <div class="flex flex-col gap-2.5">
                    <x-driver-check.chips />

                    <div x-show="periodPreset === 'custom'" x-cloak class="grid grid-cols-2 gap-2 sm:max-w-md">
                        <x-driver-check.input
                            type="date"
                            x-model="filters.period_from"
                            x-on:change="applyCustomPeriod()"
                            :aria-label="__('telegram.operation_users.filters.period_from')"
                        />
                        <x-driver-check.input
                            type="date"
                            x-model="filters.period_to"
                            x-on:change="applyCustomPeriod()"
                            :aria-label="__('telegram.operation_users.filters.period_to')"
                        />
                    </div>
                </div>
            </x-driver-check.field>

            <x-driver-check.field :label="__('telegram.operation_users.filters.status')">
                <x-driver-check.select x-model="filters.status" x-on:change="applyFilters()">
                    <option value="">{{ __('telegram.operation_users.filters.status_all') }}</option>
                    <option value="confirmed">{{ __('telegram.operation_users.result.confirmed') }}</option>
                    <option value="not_confirmed">{{ __('telegram.operation_users.result.not_confirmed') }}</option>
                    <option value="pending">{{ __('telegram.operation_users.result.pending') }}</option>
                    <option value="processing">{{ __('telegram.operation_users.result.processing') }}</option>
                </x-driver-check.select>
            </x-driver-check.field>

            <x-driver-check.field :label="__('telegram.operation_users.filters.has_driver')">
                <x-driver-check.select x-model="filters.has_driver" x-on:change="applyFilters()">
                    <option value="">{{ __('telegram.operation_users.filters.any') }}</option>
                    <option value="1">{{ __('telegram.operation_users.filters.yes') }}</option>
                    <option value="0">{{ __('telegram.operation_users.filters.no') }}</option>
                </x-driver-check.select>
            </x-driver-check.field>

            <x-driver-check.field :label="__('telegram.operation_users.filters.telegram_id')">
                <x-driver-check.input
                    type="number"
                    inputmode="numeric"
                    x-model="filters.telegram_id"
                    x-on:input.debounce.500ms="applyFilters()"
                    :placeholder="__('telegram.operation_users.filters.telegram_id_placeholder')"
                />
            </x-driver-check.field>

            <x-driver-check.field :label="__('telegram.operation_users.filters.username')">
                <x-driver-check.input
                    x-model="filters.telegram_username"
                    x-on:input.debounce.500ms="applyFilters()"
                    autocapitalize="off"
                    spellcheck="false"
                    :placeholder="__('telegram.operation_users.filters.username_placeholder')"
                />
            </x-driver-check.field>

            <x-driver-check.field
                :label="__('telegram.operation_users.filters.score_range')"
                class="sm:col-span-2"
            >
                <div class="grid grid-cols-2 gap-2">
                    <x-driver-check.input
                        type="number"
                        inputmode="numeric"
                        min="0"
                        max="100"
                        x-model="filters.min_match_score"
                        x-on:input.debounce.500ms="applyFilters()"
                        :placeholder="__('telegram.operation_users.filters.score_from')"
                    />
                    <x-driver-check.input
                        type="number"
                        inputmode="numeric"
                        min="0"
                        max="100"
                        x-model="filters.max_match_score"
                        x-on:input.debounce.500ms="applyFilters()"
                        :placeholder="__('telegram.operation_users.filters.score_to')"
                    />
                </div>
            </x-driver-check.field>
        </x-driver-check.filters>

        <x-driver-check.error-alert :title="__('telegram.operation_users.errors.load_failed')" />

        {{-- ============================================================
             Rows
        ============================================================= --}}
        <x-driver-check.surface class="overflow-hidden" x-ref="listTop">
            <x-driver-check.list-toolbar
                :title="__('telegram.operation_users.title')"
                :sort-options="[
                    'created_at' => __('telegram.operation_users.filters.created'),
                    'name' => __('telegram.operation_users.filters.name'),
                    'drivers' => __('telegram.operation_users.filters.drivers'),
                    'checks' => __('telegram.operation_users.filters.checks'),
                    'confirmed' => __('telegram.operation_users.filters.confirmed'),
                    'not_confirmed' => __('telegram.operation_users.filters.not_confirmed'),
                    'match_rate' => __('telegram.operation_users.filters.match_rate'),
                    'last_check_at' => __('telegram.operation_users.filters.last_check'),
                ]"
            />

            {{-- Table (xl and up) --}}
            <div class="hidden xl:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] table-fixed text-left">
                        <colgroup>
                            <col class="w-[22%]">
                            <col class="w-[16%]">
                            <col class="w-[7%]">
                            <col class="w-[7%]">
                            <col class="w-[17%]">
                            <col class="w-[16%]">
                            <col class="w-[10%]">
                            <col class="w-[5%]">
                        </colgroup>

                        <thead class="border-b border-gray-200 bg-gray-50/70 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr class="text-[10px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                                <th scope="col" class="px-4 py-2.5 font-semibold">{{ __('telegram.operation_users.table.user') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.operation_users.table.telegram') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.operation_users.table.drivers') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.operation_users.table.checks') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.operation_users.table.result') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.operation_users.table.match') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.operation_users.table.score') }}</th>
                                <th scope="col" class="px-4 py-2.5"><span class="sr-only">{{ __('telegram.ui.open') }}</span></th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <x-driver-check.skeleton-rows :cols="8" />

                            <template x-for="row in rows" :key="row.id">
                                <tr class="group align-middle transition hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                    {{-- Operator --}}
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <x-driver-check.avatar
                                                size="sm"
                                                class="bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
                                                x-text="initials(row.name)"
                                            />

                                            <div class="min-w-0">
                                                <a
                                                    :href="detailUrl(row)"
                                                    class="block truncate text-sm font-medium text-gray-900 transition
                                                           hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                                                    :title="row.name"
                                                    x-text="row.name || dash"
                                                ></a>

                                                <p class="truncate text-[11px] tabular-nums text-gray-400 dark:text-gray-500">
                                                    {{ __('telegram.operation_users.table.id') }}
                                                    <span x-text="row.id"></span>
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Telegram --}}
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-1">
                                            <a
                                                :href="telegramUrl(row.telegram_username)"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="truncate text-[13px] transition"
                                                :class="row.telegram_username
                                                    ? 'text-brand-600 hover:underline dark:text-brand-400'
                                                    : 'text-gray-400 dark:text-gray-500'"
                                                x-text="handle(row.telegram_username) || translations.table.no_username"
                                            ></a>

                                            <template x-if="row.telegram_username">
                                                <x-driver-check.copy-button value="row.telegram_username" />
                                            </template>
                                        </div>

                                        <p
                                            class="truncate text-[11px] tabular-nums text-gray-500 dark:text-gray-400"
                                            x-text="row.telegram_id ?? translations.table.no_telegram_id"
                                        ></p>
                                    </td>

                                    <td
                                        class="px-3 py-3 text-right text-sm font-medium tabular-nums text-gray-800 dark:text-gray-200"
                                        x-text="number(stats(row).drivers)"
                                    ></td>

                                    <td
                                        class="px-3 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300"
                                        x-text="number(stats(row).checks)"
                                    ></td>

                                    {{-- Result buckets --}}
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <template x-for="chip in resultChips(row)" :key="chip.key">
                                                <x-driver-check.badge
                                                    ::class="statusTone(chip.key, 'soft')"
                                                    ::title="statusLabel(chip.key)"
                                                >
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusTone(chip.key, 'dot')"></span>
                                                    <span class="tabular-nums" x-text="number(chip.value)"></span>
                                                </x-driver-check.badge>
                                            </template>

                                            <span
                                                x-show="resultChips(row).length === 0"
                                                class="text-[13px] text-gray-400 dark:text-gray-500"
                                                x-text="dash"
                                            ></span>
                                        </div>
                                    </td>

                                    {{-- Match rate --}}
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-full max-w-[90px] overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                                                <div
                                                    class="h-full rounded-full transition-all duration-500"
                                                    :class="matchTone(row)"
                                                    :style="`width: ${matchWidth(row)}`"
                                                ></div>
                                            </div>

                                            <span
                                                class="shrink-0 text-[13px] font-medium tabular-nums text-gray-700 dark:text-gray-200"
                                                x-text="percent(stats(row).match_rate) + '%'"
                                            ></span>
                                        </div>
                                    </td>

                                    {{-- Score --}}
                                    <td class="px-3 py-3 text-right">
                                        <p
                                            class="text-[13px] tabular-nums text-gray-700 dark:text-gray-200"
                                            x-text="score(stats(row).avg_match_score)"
                                        ></p>
                                        <p class="text-[11px] tabular-nums text-gray-400 dark:text-gray-500">
                                            <span x-text="translations.table.best"></span>
                                            <span x-text="score(stats(row).best_match_score)"></span>
                                        </p>
                                    </td>

                                    <td class="px-4 py-3 text-right">
                                        <a
                                            :href="detailUrl(row)"
                                            class="dc-tap inline-flex h-9 w-9 items-center justify-center rounded-xl
                                                   text-gray-400 transition hover:bg-gray-100 hover:text-gray-700
                                                   dark:hover:bg-white/[0.08] dark:hover:text-gray-200"
                                            :aria-label="row.name"
                                        >
                                            <x-driver-check.icon name="chevron-right" class="h-4 w-4" />
                                        </a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Cards (below xl) --}}
            <div class="divide-y divide-gray-100 xl:hidden dark:divide-gray-800">
                <x-driver-check.skeleton-cards />

                <template x-for="row in rows" :key="'card-' + row.id">
                    <a
                        :href="detailUrl(row)"
                        class="dc-tap flex flex-col gap-3 p-3.5 transition active:bg-gray-50 dark:active:bg-white/[0.03]"
                    >
                        <div class="flex items-center gap-3">
                            <x-driver-check.avatar
                                class="bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
                                x-text="initials(row.name)"
                            />

                            <div class="min-w-0 flex-1">
                                <p class="dc-break text-sm font-semibold text-gray-900 dark:text-white" x-text="row.name || dash"></p>
                                <p
                                    class="truncate text-[12px] text-gray-500 dark:text-gray-400"
                                    x-text="handle(row.telegram_username) || translations.table.no_username"
                                ></p>
                            </div>

                            <x-driver-check.icon name="chevron-right" class="h-4 w-4 shrink-0 text-gray-300 dark:text-gray-600" />
                        </div>

                        {{-- Match rate bar: the number this page exists for --}}
                        <div class="flex items-center gap-2.5">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="matchTone(row)"
                                    :style="`width: ${matchWidth(row)}`"
                                ></div>
                            </div>

                            <span
                                class="shrink-0 text-[13px] font-semibold tabular-nums text-gray-800 dark:text-gray-100"
                                x-text="percent(stats(row).match_rate) + '%'"
                            ></span>
                        </div>

                        <dl class="grid grid-cols-3 gap-2 rounded-xl bg-gray-50 p-3 dark:bg-white/[0.02]">
                            <x-driver-check.kv :label="__('telegram.operation_users.table.drivers')">
                                <span class="font-medium tabular-nums" x-text="number(stats(row).drivers)"></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.operation_users.table.checks')">
                                <span class="font-medium tabular-nums" x-text="number(stats(row).checks)"></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.operation_users.table.score')">
                                <span class="font-medium tabular-nums" x-text="score(stats(row).avg_match_score)"></span>
                            </x-driver-check.kv>
                        </dl>

                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="chip in resultChips(row)" :key="chip.key">
                                <x-driver-check.badge ::class="statusTone(chip.key, 'soft')">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusTone(chip.key, 'dot')"></span>
                                    <span x-text="statusLabel(chip.key)"></span>
                                    <span class="tabular-nums font-semibold" x-text="number(chip.value)"></span>
                                </x-driver-check.badge>
                            </template>
                        </div>
                    </a>
                </template>
            </div>

            <x-driver-check.empty-state
                icon="users"
                :title="__('telegram.operation_users.empty.title')"
                :description="__('telegram.operation_users.empty.description')"
            />

            <x-driver-check.pagination />
        </x-driver-check.surface>
    </x-driver-check.shell>
</div>

@endsection
