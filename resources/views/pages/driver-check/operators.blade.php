@extends('layouts.app')

@section('title', __('telegram.operators.title'))

@section('content')

<div
    x-data="dcOperators({
        endpoints: {
            index: @js(route('api.telegram.operators.index')),
            store: @js(route('api.telegram.operators.store')),
            base: @js(url('/api/telegram/operators')),
        },
        translations: @js(__('telegram.operators')),
        ui: @js(__('telegram.ui')),
    })"
>
    <x-driver-check.shell>

        {{-- ============================================================
             Header
        ============================================================= --}}
        <x-driver-check.page-header
            icon="operator"
            tone="brand"
            eyebrow="Telegram"
            :title="__('telegram.operators.title')"
            :description="__('telegram.operators.description')"
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

                <x-driver-check.button variant="primary" icon="plus" x-on:click="openCreate()">
                    {{ __('telegram.operators.create') }}
                </x-driver-check.button>
            </x-slot:actions>
        </x-driver-check.page-header>

        {{-- ============================================================
             Counters
        ============================================================= --}}
        <section class="grid grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-4">
            <x-driver-check.stat-card
                :label="__('telegram.operators.stats.total')"
                icon="users"
            >
                <span x-text="number(stats.total)">0</span>
            </x-driver-check.stat-card>

            <x-driver-check.stat-card
                :label="__('telegram.operators.stats.linked')"
                icon="link"
                tone="text-brand-600 dark:text-brand-400"
                chip="bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
            >
                <span x-text="number(stats.linked)">0</span>
            </x-driver-check.stat-card>

            <x-driver-check.stat-card
                :label="__('telegram.operators.stats.dm_enabled')"
                icon="send"
                tone="text-blue-light-600 dark:text-blue-light-400"
                chip="bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/10 dark:text-blue-light-400"
            >
                <span x-text="number(stats.dm_enabled)">0</span>
            </x-driver-check.stat-card>

            <x-driver-check.stat-card
                :label="__('telegram.operators.stats.failing')"
                icon="alert"
            >
                <span
                    :class="stats.failing > 0
                        ? 'text-error-600 dark:text-error-400'
                        : 'text-gray-900 dark:text-white'"
                    x-text="number(stats.failing)"
                >0</span>
            </x-driver-check.stat-card>
        </section>

        {{-- ============================================================
             Filters
        ============================================================= --}}
        <x-driver-check.filters :search-placeholder="__('telegram.operators.search_placeholder')">
            <x-driver-check.field :label="__('telegram.operators.filters.dm')">
                <x-driver-check.select x-model="filters.dm_enabled" x-on:change="applyFilters()">
                    <option value="">{{ __('telegram.operators.filters.dm_all') }}</option>
                    <option value="1">{{ __('telegram.operators.filters.dm_on') }}</option>
                    <option value="0">{{ __('telegram.operators.filters.dm_off') }}</option>
                </x-driver-check.select>
            </x-driver-check.field>

            <x-driver-check.field :label="__('telegram.operators.filters.linked')">
                <x-driver-check.select x-model="filters.linked" x-on:change="applyFilters()">
                    <option value="">{{ __('telegram.operators.filters.linked_all') }}</option>
                    <option value="1">{{ __('telegram.operators.filters.linked_yes') }}</option>
                    <option value="0">{{ __('telegram.operators.filters.linked_no') }}</option>
                </x-driver-check.select>
            </x-driver-check.field>
        </x-driver-check.filters>

        <x-driver-check.error-alert :title="__('telegram.operators.errors.title')" />

        {{-- ============================================================
             Rows

             A table from lg up, a card list below it. One layout squeezed
             into both widths is what made the columns collide on a phone.
        ============================================================= --}}
        <x-driver-check.surface class="overflow-hidden" x-ref="listTop">
            <x-driver-check.list-toolbar
                :title="__('telegram.operators.title')"
                :sort-options="[
                    'name' => __('telegram.operators.table.operator'),
                    'drivers' => __('telegram.operators.table.drivers'),
                    'checks' => __('telegram.operators.table.checks'),
                    'dm_last_sent_at' => __('telegram.operators.table.last_sent'),
                    'created_at' => __('telegram.ui.created'),
                ]"
            />

            {{-- Table (lg and up) --}}
            <div class="hidden lg:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] table-fixed text-left">
                        <colgroup>
                            <col class="w-[24%]">
                            <col class="w-[20%]">
                            <col class="w-[14%]">
                            <col class="w-[8%]">
                            <col class="w-[8%]">
                            <col class="w-[16%]">
                            <col class="w-[10%]">
                        </colgroup>

                        <thead class="border-b border-gray-200 bg-gray-50/70 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr class="text-[10px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                                <th scope="col" class="px-4 py-2.5 font-semibold">{{ __('telegram.operators.table.operator') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.operators.table.telegram') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.operators.table.dm') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.operators.table.drivers') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.operators.table.checks') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.operators.table.last_sent') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right font-semibold">{{ __('telegram.operators.table.actions') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <x-driver-check.skeleton-rows :cols="7" />

                            <template x-for="row in rows" :key="row.id">
                                <tr class="group align-middle transition hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                    {{-- Operator --}}
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <x-driver-check.avatar
                                                size="sm"
                                                ::class="avatarClass(row)"
                                                x-text="initials(row.name)"
                                            />

                                            <div class="min-w-0">
                                                <p
                                                    class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                                    :title="row.name"
                                                    x-text="row.name"
                                                ></p>
                                                <p
                                                    class="truncate text-[11px] text-gray-400 dark:text-gray-500"
                                                    :title="row.name_normalized"
                                                    x-text="row.name_normalized"
                                                ></p>
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

                                        <div class="flex items-center gap-1">
                                            <p
                                                class="truncate text-[11px] tabular-nums"
                                                :class="row.telegram_id
                                                    ? 'text-gray-500 dark:text-gray-400'
                                                    : 'text-gray-400 dark:text-gray-500'"
                                                x-text="row.telegram_id ? 'ID ' + row.telegram_id : translations.table.no_id"
                                            ></p>

                                            <template x-if="row.telegram_id">
                                                <x-driver-check.copy-button value="row.telegram_id" />
                                            </template>
                                        </div>
                                    </td>

                                    {{-- Delivery --}}
                                    <td class="px-3 py-3">
                                        <x-driver-check.badge ::class="deliveryClass(row)">
                                            <span class="h-1.5 w-1.5 rounded-full" :class="deliveryDot(row)"></span>
                                            <span x-text="deliveryLabel(row)"></span>
                                        </x-driver-check.badge>
                                    </td>

                                    <td
                                        class="px-3 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300"
                                        x-text="number(row.drivers_count)"
                                    ></td>

                                    <td
                                        class="px-3 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300"
                                        x-text="number(row.checks_count)"
                                    ></td>

                                    {{-- Last delivery --}}
                                    <td class="px-3 py-3">
                                        <p
                                            class="text-[13px] text-gray-700 dark:text-gray-300"
                                            :title="row.dm_last_sent_at ? date(row.dm_last_sent_at) : ''"
                                            x-text="row.dm_last_sent_at
                                                ? relative(row.dm_last_sent_at)
                                                : translations.table.never"
                                        ></p>

                                        <p
                                            x-show="row.dm_last_error"
                                            x-cloak
                                            class="mt-0.5 line-clamp-2 text-[11px] text-error-600 dark:text-error-400"
                                            :title="row.dm_last_error"
                                            x-text="row.dm_last_error"
                                        ></p>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-4 py-3 text-right">
                                        <x-driver-check.button size="sm" x-on:click="openEdit(row)">
                                            {{ __('telegram.operators.table.edit') }}
                                        </x-driver-check.button>
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
                            <x-driver-check.avatar ::class="avatarClass(row)" x-text="initials(row.name)" />

                            <div class="min-w-0 flex-1">
                                <p class="dc-break text-sm font-semibold text-gray-900 dark:text-white" x-text="row.name"></p>
                                <p class="dc-break text-[11px] text-gray-400 dark:text-gray-500" x-text="row.name_normalized"></p>
                            </div>

                            <x-driver-check.badge ::class="deliveryClass(row)">
                                <span class="h-1.5 w-1.5 rounded-full" :class="deliveryDot(row)"></span>
                                <span x-text="deliveryLabel(row)"></span>
                            </x-driver-check.badge>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 rounded-xl bg-gray-50 p-3 dark:bg-white/[0.02]">
                            <x-driver-check.kv :label="__('telegram.operators.table.telegram')" class="col-span-2">
                                <a
                                    :href="telegramUrl(row.telegram_username)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    :class="row.telegram_username && 'text-brand-600 dark:text-brand-400'"
                                    x-text="handle(row.telegram_username) || translations.table.no_username"
                                ></a>
                                <span
                                    class="block text-[11px] tabular-nums text-gray-500 dark:text-gray-400"
                                    x-text="row.telegram_id ? 'ID ' + row.telegram_id : translations.table.no_id"
                                ></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.operators.table.drivers')">
                                <span class="tabular-nums" x-text="number(row.drivers_count)"></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.operators.table.checks')">
                                <span class="tabular-nums" x-text="number(row.checks_count)"></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.operators.table.last_sent')" class="col-span-2">
                                <span x-text="row.dm_last_sent_at
                                    ? date(row.dm_last_sent_at)
                                    : translations.table.never"></span>
                            </x-driver-check.kv>
                        </dl>

                        <p
                            x-show="row.dm_last_error"
                            x-cloak
                            class="dc-break rounded-xl bg-error-50 px-3 py-2 text-[11px] leading-snug text-error-600
                                   dark:bg-error-500/10 dark:text-error-400"
                        >
                            <span class="font-semibold">{{ __('telegram.operators.errors.dm_last_error') }}:</span>
                            <span x-text="row.dm_last_error"></span>
                        </p>

                        <x-driver-check.button class="w-full" x-on:click="openEdit(row)">
                            {{ __('telegram.operators.table.edit') }}
                        </x-driver-check.button>
                    </article>
                </template>
            </div>

            <x-driver-check.empty-state
                icon="operator"
                :title="__('telegram.operators.empty.title')"
                :description="__('telegram.operators.empty.description')"
            >
                <div class="mt-3">
                    <x-driver-check.button variant="primary" size="sm" icon="plus" x-on:click="openCreate()">
                        {{ __('telegram.operators.create') }}
                    </x-driver-check.button>
                </div>
            </x-driver-check.empty-state>

            <x-driver-check.pagination />
        </x-driver-check.surface>
    </x-driver-check.shell>

    {{-- ================================================================
         Create / edit dialog
    ================================================================= --}}
    <x-driver-check.modal
        open="formOpen"
        close="closeForm()"
        size="sm:max-w-lg"
    >
        <x-slot:heading>
            <span x-text="form.id ? translations.form.edit_title : translations.form.create_title"></span>
        </x-slot:heading>

        <form id="operator-form" x-on:submit.prevent="save()" class="flex flex-col gap-4">

            {{-- Name --}}
            <x-driver-check.field
                :label="__('telegram.operators.form.name')"
                :hint="__('telegram.operators.form.name_hint')"
                for="operator-name"
            >
                <x-driver-check.input
                    id="operator-name"
                    x-ref="firstField"
                    x-model="form.name"
                    required
                    autocomplete="off"
                    ::data-invalid="!!(fieldError('name') || fieldError('name_normalized'))"
                />

                <x-slot:error>
                    <p
                        x-show="form.name"
                        x-cloak
                        class="dc-break mt-1.5 rounded-lg bg-gray-50 px-2.5 py-1.5 text-[11px]
                               text-gray-500 dark:bg-white/[0.03] dark:text-gray-400"
                    >
                        {{ __('telegram.operators.form.name_normalized') }}:
                        <span class="font-mono font-medium text-gray-700 dark:text-gray-200" x-text="normalizedPreview()"></span>
                    </p>

                    <p
                        x-show="fieldError('name') || fieldError('name_normalized')"
                        x-cloak
                        class="mt-1 text-[11px] font-medium text-error-600 dark:text-error-400"
                        x-text="fieldError('name') || fieldError('name_normalized')"
                    ></p>
                </x-slot:error>
            </x-driver-check.field>

            {{-- Username --}}
            <x-driver-check.field
                :label="__('telegram.operators.form.telegram_username')"
                :hint="__('telegram.operators.form.telegram_username_hint')"
                for="operator-username"
            >
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-gray-400">&#64;</span>

                    <x-driver-check.input
                        id="operator-username"
                        x-model="form.telegram_username"
                        placeholder="username"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        class="pl-8"
                        ::data-invalid="!!fieldError('telegram_username')"
                    />
                </div>

                <x-slot:error>
                    <p
                        x-show="fieldError('telegram_username')"
                        x-cloak
                        class="mt-1 text-[11px] font-medium text-error-600 dark:text-error-400"
                        x-text="fieldError('telegram_username')"
                    ></p>
                </x-slot:error>
            </x-driver-check.field>

            {{-- Telegram id --}}
            <x-driver-check.field
                :label="__('telegram.operators.form.telegram_id')"
                :hint="__('telegram.operators.form.telegram_id_hint')"
                for="operator-telegram-id"
            >
                <x-driver-check.input
                    id="operator-telegram-id"
                    type="number"
                    inputmode="numeric"
                    min="1"
                    x-model="form.telegram_id"
                    placeholder="123456789"
                    ::data-invalid="!!fieldError('telegram_id')"
                />

                <x-slot:error>
                    <p
                        x-show="fieldError('telegram_id')"
                        x-cloak
                        class="mt-1 text-[11px] font-medium text-error-600 dark:text-error-400"
                        x-text="fieldError('telegram_id')"
                    ></p>
                </x-slot:error>
            </x-driver-check.field>

            {{-- The only switch: copy the report into the operator's private chat --}}
            <label
                class="dc-tap flex cursor-pointer items-start gap-3 rounded-2xl border p-3.5 transition"
                :class="form.dm_enabled
                    ? 'border-brand-500 bg-brand-25 dark:border-brand-500/50 dark:bg-brand-500/[0.07]'
                    : 'border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-transparent dark:hover:bg-white/[0.03]'"
            >
                <input
                    type="checkbox"
                    x-model="form.dm_enabled"
                    class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-gray-300 text-brand-500
                           focus:ring-brand-500/30 dark:border-gray-600 dark:bg-gray-900"
                >

                <span class="min-w-0">
                    <span class="block text-sm font-medium text-gray-900 dark:text-white">
                        {{ __('telegram.operators.form.dm_enabled') }}
                    </span>
                    <span class="mt-0.5 block text-[11px] leading-snug text-gray-500 dark:text-gray-400">
                        {{ __('telegram.operators.form.dm_enabled_hint') }}
                    </span>
                </span>
            </label>

            <p
                x-show="formError"
                x-cloak
                class="dc-break rounded-xl bg-error-50 px-3 py-2.5 text-xs text-error-600
                       dark:bg-error-500/10 dark:text-error-400"
                x-text="formError"
            ></p>
        </form>

        <x-slot:footer>
            <x-driver-check.button x-on:click="closeForm()" ::disabled="saving">
                {{ __('telegram.operators.form.cancel') }}
            </x-driver-check.button>

            <x-driver-check.button
                variant="primary"
                type="submit"
                form="operator-form"
                ::disabled="saving"
            >
                <x-driver-check.icon
                    name="refresh"
                    class="h-4 w-4 animate-spin"
                    x-show="saving"
                    x-cloak
                />
                <span x-text="saving ? translations.form.saving : translations.form.save"></span>
            </x-driver-check.button>
        </x-slot:footer>
    </x-driver-check.modal>
</div>

@endsection
