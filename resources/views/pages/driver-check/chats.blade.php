@extends('layouts.app')

@section('title', __('telegram.chats.title'))

@section('content')

<div
    x-data="dcChats({
        endpoints: {
            index: @js(route('api.telegram.chats.index')),
            store: @js(route('api.telegram.chats.store')),
            base: @js(url('/api/telegram/chats')),
        },
        translations: @js(__('telegram.chats')),
        ui: @js(__('telegram.ui')),
    })"
>
    <x-driver-check.shell>

        {{-- ============================================================
             Header
        ============================================================= --}}
        <x-driver-check.page-header
            icon="telegram"
            tone="brand"
            eyebrow="Telegram"
            :title="__('telegram.chats.title')"
            :description="__('telegram.chats.description')"
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
                    {{ __('telegram.chats.create') }}
                </x-driver-check.button>
            </x-slot:actions>
        </x-driver-check.page-header>

        {{-- ============================================================
             Why a new chat is not watched the second it is saved
        ============================================================= --}}
        <div
            class="flex items-start gap-2.5 rounded-2xl border border-blue-light-200 bg-blue-light-25 px-3.5 py-3
                   text-[13px] leading-relaxed text-blue-light-700
                   dark:border-blue-light-500/20 dark:bg-blue-light-500/[0.07] dark:text-blue-light-300"
        >
            <x-driver-check.icon name="clock" class="mt-0.5 h-4 w-4 shrink-0" />
            <p>{{ __('telegram.chats.notice') }}</p>
        </div>

        {{-- ============================================================
             Counters
        ============================================================= --}}
        <section class="grid grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-4">
            <x-driver-check.stat-card
                :label="__('telegram.chats.stats.total')"
                icon="inbox"
            >
                <span x-text="number(stats.total)">0</span>
            </x-driver-check.stat-card>

            <x-driver-check.stat-card
                :label="__('telegram.chats.stats.active')"
                icon="check-circle"
                tone="text-brand-600 dark:text-brand-400"
                chip="bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
            >
                <span x-text="number(stats.active)">0</span>
            </x-driver-check.stat-card>

            <x-driver-check.stat-card
                :label="__('telegram.chats.stats.watching')"
                icon="telegram"
                tone="text-success-600 dark:text-success-400"
                chip="bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400"
            >
                <span x-text="number(stats.watching)">0</span>
            </x-driver-check.stat-card>

            <x-driver-check.stat-card
                :label="__('telegram.chats.stats.failing')"
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
        <x-driver-check.filters :search-placeholder="__('telegram.chats.search_placeholder')">
            <x-driver-check.field :label="__('telegram.chats.filters.status')">
                <x-driver-check.select x-model="filters.is_active" x-on:change="applyFilters()">
                    <option value="">{{ __('telegram.chats.filters.status_all') }}</option>
                    <option value="1">{{ __('telegram.chats.filters.status_active') }}</option>
                    <option value="0">{{ __('telegram.chats.filters.status_inactive') }}</option>
                </x-driver-check.select>
            </x-driver-check.field>

            <x-driver-check.field :label="__('telegram.chats.filters.resolved')">
                <x-driver-check.select x-model="filters.resolved" x-on:change="applyFilters()">
                    <option value="">{{ __('telegram.chats.filters.resolved_all') }}</option>
                    <option value="1">{{ __('telegram.chats.filters.resolved_yes') }}</option>
                    <option value="0">{{ __('telegram.chats.filters.resolved_no') }}</option>
                </x-driver-check.select>
            </x-driver-check.field>
        </x-driver-check.filters>

        <x-driver-check.error-alert :title="__('telegram.chats.errors.title')" />

        {{-- ============================================================
             Rows

             A table from lg up, a card list below it - same split as the
             operators page.
        ============================================================= --}}
        <x-driver-check.surface class="overflow-hidden" x-ref="listTop">
            <x-driver-check.list-toolbar
                :title="__('telegram.chats.title')"
                :sort-options="[
                    'created_at' => __('telegram.ui.created'),
                    'title' => __('telegram.chats.table.chat'),
                    'last_message_at' => __('telegram.chats.table.last_message'),
                    'checks' => __('telegram.chats.table.checks'),
                ]"
            />

            {{-- Table (lg and up) --}}
            <div class="hidden lg:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] table-fixed text-left">
                        <colgroup>
                            <col class="w-[28%]">
                            <col class="w-[18%]">
                            <col class="w-[14%]">
                            <col class="w-[8%]">
                            <col class="w-[18%]">
                            <col class="w-[14%]">
                        </colgroup>

                        <thead class="border-b border-gray-200 bg-gray-50/70 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr class="text-[10px] uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                                <th scope="col" class="px-4 py-2.5 font-semibold">{{ __('telegram.chats.table.chat') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.chats.table.peer') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.chats.table.status') }}</th>
                                <th scope="col" class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.chats.table.checks') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.chats.table.last_message') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right font-semibold">{{ __('telegram.chats.table.actions') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <x-driver-check.skeleton-rows :cols="6" />

                            <template x-for="row in rows" :key="row.id">
                                <tr class="group align-middle transition hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                    {{-- Chat --}}
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <x-driver-check.avatar
                                                size="sm"
                                                ::class="avatarClass(row)"
                                            >
                                                <x-driver-check.icon name="telegram" class="h-4 w-4" />
                                            </x-driver-check.avatar>

                                            <div class="min-w-0">
                                                <p
                                                    class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                                    :title="row.label"
                                                    x-text="row.label"
                                                ></p>

                                                <div class="flex items-center gap-1">
                                                    <a
                                                        :href="telegramUrl(row.link)"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="truncate text-[11px] transition"
                                                        :class="telegramUrl(row.link)
                                                            ? 'text-brand-600 hover:underline dark:text-brand-400'
                                                            : (row.link
                                                                ? 'text-gray-500 dark:text-gray-400'
                                                                : 'text-gray-400 dark:text-gray-500')"
                                                        :title="row.link"
                                                        x-text="row.link || translations.table.no_link"
                                                    ></a>

                                                    <template x-if="telegramUrl(row.link)">
                                                        <x-driver-check.icon
                                                            name="external"
                                                            class="h-3 w-3 shrink-0 text-brand-500/70"
                                                        />
                                                    </template>

                                                    <template x-if="row.link">
                                                        <x-driver-check.copy-button value="row.link" />
                                                    </template>

                                                    <template x-if="row.source === 'env'">
                                                        <span
                                                            class="rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px]
                                                                   font-medium text-gray-500 dark:bg-white/[0.06] dark:text-gray-400"
                                                            x-text="translations.table.source_env"
                                                        ></span>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Peer --}}
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-1">
                                            <p
                                                class="truncate text-[13px] tabular-nums"
                                                :class="row.chat_id
                                                    ? 'text-gray-900 dark:text-white'
                                                    : 'text-gray-400 dark:text-gray-500'"
                                                x-text="row.chat_id || translations.table.no_id"
                                            ></p>

                                            <template x-if="row.chat_id">
                                                <x-driver-check.copy-button value="row.chat_id" />
                                            </template>
                                        </div>
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-3 py-3">
                                        <x-driver-check.badge ::class="stateClass(row)">
                                            <span class="h-1.5 w-1.5 rounded-full" :class="stateDot(row)"></span>
                                            <span x-text="stateLabel(row)"></span>
                                        </x-driver-check.badge>
                                    </td>

                                    <td
                                        class="px-3 py-3 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300"
                                        x-text="number(row.checks_count)"
                                    ></td>

                                    {{-- Last message --}}
                                    <td class="px-3 py-3">
                                        <p
                                            class="text-[13px] text-gray-700 dark:text-gray-300"
                                            :title="row.last_message_at ? date(row.last_message_at) : ''"
                                            x-text="row.last_message_at
                                                ? relative(row.last_message_at)
                                                : translations.table.never"
                                        ></p>

                                        <p
                                            x-show="row.resolve_error"
                                            x-cloak
                                            class="mt-0.5 line-clamp-2 text-[11px] text-error-600 dark:text-error-400"
                                            :title="row.resolve_error"
                                            x-text="row.resolve_error"
                                        ></p>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-4 py-3 text-right">
                                        <x-driver-check.button size="sm" x-on:click="openEdit(row)">
                                            {{ __('telegram.chats.table.edit') }}
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
                            <x-driver-check.avatar ::class="avatarClass(row)">
                                <x-driver-check.icon name="telegram" class="h-5 w-5" />
                            </x-driver-check.avatar>

                            <div class="min-w-0 flex-1">
                                <p class="dc-break text-sm font-semibold text-gray-900 dark:text-white" x-text="row.label"></p>
                                <a
                                    :href="telegramUrl(row.link)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="dc-break inline-flex items-center gap-1 text-[11px]"
                                    :class="telegramUrl(row.link)
                                        ? 'text-brand-600 dark:text-brand-400'
                                        : 'text-gray-400 dark:text-gray-500'"
                                >
                                    <span x-text="row.link || translations.table.no_link"></span>

                                    <template x-if="telegramUrl(row.link)">
                                        <x-driver-check.icon name="external" class="h-3 w-3 shrink-0" />
                                    </template>
                                </a>
                            </div>

                            <x-driver-check.badge ::class="stateClass(row)">
                                <span class="h-1.5 w-1.5 rounded-full" :class="stateDot(row)"></span>
                                <span x-text="stateLabel(row)"></span>
                            </x-driver-check.badge>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 rounded-xl bg-gray-50 p-3 dark:bg-white/[0.02]">
                            <x-driver-check.kv :label="__('telegram.chats.table.peer')" class="col-span-2">
                                <span class="tabular-nums" x-text="row.chat_id || translations.table.no_id"></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.chats.table.checks')">
                                <span class="tabular-nums" x-text="number(row.checks_count)"></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.chats.table.last_message')">
                                <span x-text="row.last_message_at
                                    ? relative(row.last_message_at)
                                    : translations.table.never"></span>
                            </x-driver-check.kv>
                        </dl>

                        <p
                            x-show="row.resolve_error"
                            x-cloak
                            class="dc-break rounded-xl bg-error-50 px-3 py-2 text-[11px] leading-snug text-error-600
                                   dark:bg-error-500/10 dark:text-error-400"
                        >
                            <span class="font-semibold">{{ __('telegram.chats.errors.resolve') }}:</span>
                            <span x-text="row.resolve_error"></span>
                        </p>

                        <x-driver-check.button class="w-full" x-on:click="openEdit(row)">
                            {{ __('telegram.chats.table.edit') }}
                        </x-driver-check.button>
                    </article>
                </template>
            </div>

            <x-driver-check.empty-state
                icon="telegram"
                :title="__('telegram.chats.empty.title')"
                :description="__('telegram.chats.empty.description')"
            >
                <div class="mt-3">
                    <x-driver-check.button variant="primary" size="sm" icon="plus" x-on:click="openCreate()">
                        {{ __('telegram.chats.create') }}
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

        <form id="chat-form" x-on:submit.prevent="save()" class="flex flex-col gap-4">

            {{-- Link or id --}}
            <x-driver-check.field
                :label="__('telegram.chats.form.chat')"
                :hint="__('telegram.chats.form.chat_hint')"
                for="chat-peer"
            >
                <x-driver-check.input
                    id="chat-peer"
                    x-ref="firstField"
                    x-model="form.chat"
                    required
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    placeholder="https://t.me/+..."
                    ::data-invalid="!!(fieldError('chat') || fieldError('link') || fieldError('chat_id'))"
                />

                <x-slot:error>
                    <p
                        x-show="fieldError('chat') || fieldError('link') || fieldError('chat_id')"
                        x-cloak
                        class="mt-1 text-[11px] font-medium text-error-600 dark:text-error-400"
                        x-text="fieldError('chat') || fieldError('link') || fieldError('chat_id')"
                    ></p>
                </x-slot:error>
            </x-driver-check.field>

            {{-- Name --}}
            <x-driver-check.field
                :label="__('telegram.chats.form.title')"
                :hint="__('telegram.chats.form.title_hint')"
                for="chat-title"
            >
                <x-driver-check.input
                    id="chat-title"
                    x-model="form.title"
                    autocomplete="off"
                    ::data-invalid="!!fieldError('title')"
                />

                <x-slot:error>
                    <p
                        x-show="fieldError('title')"
                        x-cloak
                        class="mt-1 text-[11px] font-medium text-error-600 dark:text-error-400"
                        x-text="fieldError('title')"
                    ></p>
                </x-slot:error>
            </x-driver-check.field>

            {{-- The only switch: is this chat watched at all --}}
            <label
                class="dc-tap flex cursor-pointer items-start gap-3 rounded-2xl border p-3.5 transition"
                :class="form.is_active
                    ? 'border-brand-500 bg-brand-25 dark:border-brand-500/50 dark:bg-brand-500/[0.07]'
                    : 'border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-800 dark:bg-transparent dark:hover:bg-white/[0.03]'"
            >
                <input
                    type="checkbox"
                    x-model="form.is_active"
                    class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-gray-300 text-brand-500
                           focus:ring-brand-500/30 dark:border-gray-600 dark:bg-gray-900"
                >

                <span class="min-w-0">
                    <span class="block text-sm font-medium text-gray-900 dark:text-white">
                        {{ __('telegram.chats.form.is_active') }}
                    </span>
                    <span class="mt-0.5 block text-[11px] leading-snug text-gray-500 dark:text-gray-400">
                        {{ __('telegram.chats.form.is_active_hint') }}
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
            {{-- Removing lives in the edit dialog: it is an edit, not a row action. --}}
            <x-driver-check.button
                x-show="form.id"
                x-cloak
                variant="ghost"
                class="sm:mr-auto"
                x-on:click="askDelete()"
                ::disabled="saving"
            >
                <span class="text-error-600 dark:text-error-400">
                    {{ __('telegram.chats.form.delete') }}
                </span>
            </x-driver-check.button>

            <x-driver-check.button x-on:click="closeForm()" ::disabled="saving">
                {{ __('telegram.chats.form.cancel') }}
            </x-driver-check.button>

            <x-driver-check.button
                variant="primary"
                type="submit"
                form="chat-form"
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

    {{-- ================================================================
         Delete confirmation
    ================================================================= --}}
    <x-driver-check.modal
        open="confirmOpen"
        close="closeConfirm()"
        size="sm:max-w-md"
        :title="__('telegram.chats.confirm.delete_title')"
    >
        <p class="text-[13px] leading-relaxed text-gray-600 dark:text-gray-300">
            {{ __('telegram.chats.confirm.delete_text') }}
        </p>

        <p
            class="dc-break mt-3 rounded-xl bg-gray-50 px-3 py-2 text-[13px] font-medium text-gray-700
                   dark:bg-white/[0.03] dark:text-gray-200"
            x-text="form.label"
        ></p>

        <p
            x-show="confirmError"
            x-cloak
            class="dc-break mt-3 rounded-xl bg-error-50 px-3 py-2.5 text-xs text-error-600
                   dark:bg-error-500/10 dark:text-error-400"
            x-text="confirmError"
        ></p>

        <x-slot:footer>
            <x-driver-check.button x-on:click="closeConfirm()" ::disabled="deleting">
                {{ __('telegram.chats.confirm.cancel') }}
            </x-driver-check.button>

            <x-driver-check.button
                variant="danger"
                x-on:click="destroy()"
                ::disabled="deleting"
            >
                <x-driver-check.icon
                    name="refresh"
                    class="h-4 w-4 animate-spin"
                    x-show="deleting"
                    x-cloak
                />
                {{ __('telegram.chats.confirm.delete') }}
            </x-driver-check.button>
        </x-slot:footer>
    </x-driver-check.modal>
</div>

@endsection
