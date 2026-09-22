@extends('layouts.app')

@section('title', __('telegram.resolved_phones.title'))

@section('content')

<div
    x-data="dcResolvedPhones({
        endpoints: {
            index: @js(route('api.telegram.resolved-phones')),
        },
        operatorBaseUrl: @js(url('/driver-check/operation-users')),
        translations: @js(__('telegram.resolved_phones')),
        statusLabels: @js(__('telegram.drivers.status')),
        ui: @js(__('telegram.ui')),
    })"
>
    <x-driver-check.shell>

        {{-- ============================================================
             Header
        ============================================================= --}}
        <x-driver-check.page-header
            icon="telegram"
            tone="blue"
            eyebrow="Telegram"
            :title="__('telegram.resolved_phones.title')"
            :description="__('telegram.resolved_phones.description')"
        >
            <x-slot:actions>
                <x-driver-check.button class="col-span-2" x-on:click="load()" ::disabled="loading">
                    <x-driver-check.icon
                        name="refresh"
                        class="h-4 w-4"
                        ::class="loading && 'animate-spin'"
                    />
                    <span x-text="loading ? ui.loading : ui.refresh"></span>
                </x-driver-check.button>
            </x-slot:actions>
        </x-driver-check.page-header>

        {{-- ============================================================
             Filters
        ============================================================= --}}
        <x-driver-check.filters :search-placeholder="__('telegram.resolved_phones.filters.search_placeholder')">
            <x-driver-check.field
                :label="__('telegram.resolved_phones.filters.period')"
                class="sm:col-span-2 xl:col-span-4"
            >
                <div class="flex flex-col gap-2.5">
                    <x-driver-check.chips />

                    <div x-show="periodPreset === 'custom'" x-cloak class="grid grid-cols-2 gap-2 sm:max-w-md">
                        <x-driver-check.input
                            type="date"
                            x-model="filters.period_from"
                            x-on:change="applyCustomPeriod()"
                            :aria-label="__('telegram.resolved_phones.filters.period_from')"
                        />
                        <x-driver-check.input
                            type="date"
                            x-model="filters.period_to"
                            x-on:change="applyCustomPeriod()"
                            :aria-label="__('telegram.resolved_phones.filters.period_to')"
                        />
                    </div>
                </div>
            </x-driver-check.field>

            <x-driver-check.field :label="__('telegram.resolved_phones.filters.has_username')">
                <x-driver-check.select x-model="filters.has_username" x-on:change="applyFilters()">
                    <option value="">{{ __('telegram.resolved_phones.filters.any') }}</option>
                    <option value="1">{{ __('telegram.resolved_phones.filters.yes') }}</option>
                    <option value="0">{{ __('telegram.resolved_phones.filters.no') }}</option>
                </x-driver-check.select>
            </x-driver-check.field>

            <x-driver-check.field :label="__('telegram.resolved_phones.filters.has_driver')">
                <x-driver-check.select x-model="filters.has_driver" x-on:change="applyFilters()">
                    <option value="">{{ __('telegram.resolved_phones.filters.any') }}</option>
                    <option value="1">{{ __('telegram.resolved_phones.filters.yes') }}</option>
                    <option value="0">{{ __('telegram.resolved_phones.filters.no') }}</option>
                </x-driver-check.select>
            </x-driver-check.field>

            <x-driver-check.field class="sm:col-span-2 sm:self-end">
                <label
                    class="dc-tap flex h-11 cursor-pointer items-center gap-2.5 rounded-xl border px-3.5
                           transition sm:h-10"
                    :class="filters.stale
                        ? 'border-warning-500 bg-warning-25 dark:border-warning-500/50 dark:bg-warning-500/[0.07]'
                        : 'border-gray-300 bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-white/[0.05]'"
                >
                    <input
                        type="checkbox"
                        x-model="filters.stale"
                        x-on:change="applyFilters()"
                        class="h-[18px] w-[18px] rounded border-gray-300 text-warning-500
                               focus:ring-warning-500/30 dark:border-gray-600 dark:bg-gray-900"
                    >

                    <span class="text-sm text-gray-700 dark:text-gray-200">
                        {{ __('telegram.resolved_phones.filters.stale') }}
                    </span>
                </label>
            </x-driver-check.field>
        </x-driver-check.filters>

        <x-driver-check.error-alert :title="__('telegram.resolved_phones.errors.load_failed')" />

        {{-- ============================================================
             Rows
        ============================================================= --}}
        <x-driver-check.surface class="overflow-hidden" x-ref="listTop">
            <x-driver-check.list-toolbar
                :title="__('telegram.resolved_phones.title')"
                :sort-options="[
                    'resolved_at' => __('telegram.resolved_phones.filters.resolved'),
                    'created_at' => __('telegram.resolved_phones.filters.created'),
                    'phone_normalized' => __('telegram.resolved_phones.filters.phone'),
                    'checks' => __('telegram.resolved_phones.filters.checks'),
                    'confirmed' => __('telegram.resolved_phones.filters.confirmed'),
                    'not_confirmed' => __('telegram.resolved_phones.filters.not_confirmed'),
                ]"
            />

            {{-- Table (lg and up) --}}
            <div class="hidden lg:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1040px] table-fixed text-left">
                        <colgroup>
                            <col class="w-[19%]">
                            <col class="w-[21%]">
                            <col class="w-[19%]">
                            <col class="w-[15%]">
                            <col class="w-[14%]">
                            <col class="w-[12%]">
                        </colgroup>

                        <thead class="border-b border-gray-200 bg-gray-50/70 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr class="text-[10px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                                <th scope="col" class="px-4 py-2.5 font-semibold">{{ __('telegram.resolved_phones.table.phone') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.resolved_phones.table.telegram') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.resolved_phones.table.driver') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.resolved_phones.table.operator') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.resolved_phones.table.resolved_at') }}</th>
                                <th scope="col" class="px-4 py-2.5 font-semibold">{{ __('telegram.resolved_phones.table.result') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <x-driver-check.skeleton-rows :cols="6" />

                            <template x-for="row in rows" :key="row.id">
                                <tr class="group align-middle transition hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                    {{-- Phone --}}
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1">
                                            <span
                                                class="truncate text-[13px] font-medium tabular-nums text-gray-900 dark:text-white"
                                                x-text="phone(row.phone_normalized)"
                                            ></span>

                                            <x-driver-check.copy-button value="row.phone_normalized" />
                                        </div>

                                        <p
                                            x-show="row.telegram_account"
                                            x-cloak
                                            class="truncate text-[11px] text-gray-400 dark:text-gray-500"
                                        >
                                            {{ __('telegram.resolved_phones.table.account') }}:
                                            <span x-text="row.telegram_account?.phone"></span>
                                        </p>
                                    </td>

                                    {{-- Telegram --}}
                                    <td class="px-3 py-3">
                                        <p
                                            class="truncate text-[13px] text-gray-900 dark:text-white"
                                            x-text="fullName(row) || dash"
                                        ></p>

                                        <a
                                            :href="telegramUrl(row.telegram_username)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="block truncate text-[11px] transition"
                                            :class="row.telegram_username
                                                ? 'text-brand-600 hover:underline dark:text-brand-400'
                                                : 'text-gray-400 dark:text-gray-500'"
                                            x-text="handle(row.telegram_username) || translations.table.no_username"
                                        ></a>
                                    </td>

                                    {{-- Driver --}}
                                    <td class="px-3 py-3">
                                        <template x-if="row.driver">
                                            <div class="min-w-0">
                                                <p class="truncate text-[13px] text-gray-900 dark:text-white" x-text="row.driver.name"></p>

                                                <x-driver-check.badge
                                                    class="mt-0.5"
                                                    ::class="statusTone(row.driver.status, 'soft')"
                                                >
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusTone(row.driver.status, 'dot')"></span>
                                                    <span x-text="statusLabel(row.driver.status)"></span>
                                                </x-driver-check.badge>
                                            </div>
                                        </template>

                                        <template x-if="!row.driver">
                                            <span class="text-[13px] text-gray-400 dark:text-gray-500">
                                                {{ __('telegram.resolved_phones.table.no_driver') }}
                                            </span>
                                        </template>
                                    </td>

                                    {{-- Operator --}}
                                    <td class="px-3 py-3">
                                        <template x-if="row.driver?.operation_user">
                                            <a
                                                :href="operatorUrl(row)"
                                                class="block truncate text-[13px] font-medium text-gray-900 transition
                                                       hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                                                x-text="row.driver.operation_user.name"
                                            ></a>
                                        </template>

                                        <template x-if="!row.driver?.operation_user">
                                            <span class="text-[13px] text-gray-400 dark:text-gray-500" x-text="dash"></span>
                                        </template>
                                    </td>

                                    {{-- Resolved --}}
                                    <td class="px-3 py-3">
                                        <p
                                            class="text-[13px] text-gray-700 dark:text-gray-300"
                                            :title="row.resolved_at ? date(row.resolved_at) : ''"
                                            x-text="row.resolved_at ? relative(row.resolved_at) : translations.dates.never"
                                        ></p>
                                    </td>

                                    {{-- Result --}}
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <span
                                                class="text-[13px] font-medium tabular-nums text-gray-800 dark:text-gray-100"
                                                x-text="number(stats(row).checks)"
                                            ></span>

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
                                        </div>
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
                            <x-driver-check.avatar class="bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/10 dark:text-blue-light-400">
                                <x-driver-check.icon name="phone" class="h-4 w-4" />
                            </x-driver-check.avatar>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1">
                                    <p
                                        class="dc-break text-sm font-semibold tabular-nums text-gray-900 dark:text-white"
                                        x-text="phone(row.phone_normalized)"
                                    ></p>

                                    <x-driver-check.copy-button value="row.phone_normalized" />
                                </div>

                                <a
                                    :href="telegramUrl(row.telegram_username)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block truncate text-[12px] transition"
                                    :class="row.telegram_username
                                        ? 'text-brand-600 dark:text-brand-400'
                                        : 'text-gray-500 dark:text-gray-400'"
                                    x-text="handle(row.telegram_username) || translations.table.no_username"
                                ></a>
                            </div>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 rounded-xl bg-gray-50 p-3 dark:bg-white/[0.02]">
                            <x-driver-check.kv :label="__('telegram.resolved_phones.table.telegram')" class="col-span-2">
                                <span x-text="fullName(row) || dash"></span>
                                <span
                                    x-show="row.telegram_user_id"
                                    class="block text-[11px] tabular-nums text-gray-500 dark:text-gray-400"
                                >
                                    ID <span x-text="row.telegram_user_id"></span>
                                </span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.resolved_phones.table.driver')" class="col-span-2">
                                <template x-if="row.driver">
                                    <span class="flex flex-wrap items-center gap-1.5">
                                        <span x-text="row.driver.name"></span>

                                        <x-driver-check.badge ::class="statusTone(row.driver.status, 'soft')">
                                            <span x-text="statusLabel(row.driver.status)"></span>
                                        </x-driver-check.badge>
                                    </span>
                                </template>

                                <template x-if="!row.driver">
                                    <span class="text-gray-400 dark:text-gray-500">
                                        {{ __('telegram.resolved_phones.table.no_driver') }}
                                    </span>
                                </template>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.resolved_phones.table.checks')">
                                <span class="font-medium tabular-nums" x-text="number(stats(row).checks)"></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.resolved_phones.table.resolved_at')">
                                <span x-text="row.resolved_at ? date(row.resolved_at) : translations.dates.never"></span>
                            </x-driver-check.kv>
                        </dl>

                        <div x-show="row.driver?.operation_user" x-cloak>
                            <a
                                :href="operatorUrl(row)"
                                class="dc-tap inline-flex items-center gap-1.5 text-[13px] font-medium
                                       text-brand-600 dark:text-brand-400"
                            >
                                <x-driver-check.icon name="operator" class="h-4 w-4" />
                                <span x-text="row.driver?.operation_user?.name"></span>
                                <x-driver-check.icon name="chevron-right" class="h-3.5 w-3.5" />
                            </a>
                        </div>
                    </article>
                </template>
            </div>

            <x-driver-check.empty-state
                icon="phone"
                :title="__('telegram.resolved_phones.empty.title')"
                :description="__('telegram.resolved_phones.empty.description')"
            />

            <x-driver-check.pagination />
        </x-driver-check.surface>
    </x-driver-check.shell>
</div>

@endsection
