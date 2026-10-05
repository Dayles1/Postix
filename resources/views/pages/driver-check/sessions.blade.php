@extends('layouts.app')

@section('title', __('telegram.sessions.title'))

@section('content')

<div
    x-data="dcSessions({
        endpoints: {
            index: @js(route('api.telegram.sessions.index')),
            store: @js(route('api.telegram.sessions.store')),
            base: @js(url('/api/telegram/sessions')),
        },
        processes: @js(__('telegram.sessions.processes.names')),
        translations: @js(__('telegram.sessions')),
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
            :eyebrow="__('telegram.menu.groups.settings')"
            :title="__('telegram.sessions.title')"
            :description="__('telegram.sessions.description')"
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

                <x-driver-check.button variant="primary" icon="plus" x-on:click="openLogin()">
                    {{ __('telegram.sessions.create') }}
                </x-driver-check.button>
            </x-slot:actions>
        </x-driver-check.page-header>

        {{-- Why nothing happens instantly --}}
        <div
            class="flex items-start gap-3 rounded-2xl border border-blue-light-200 bg-blue-light-25 p-3.5 text-[13px]
                   leading-relaxed text-blue-light-800 sm:p-4 dark:border-blue-light-500/30
                   dark:bg-blue-light-500/[0.07] dark:text-blue-light-300"
        >
            <x-driver-check.icon name="clock" class="mt-0.5 h-4 w-4 shrink-0" />
            <p>{{ __('telegram.sessions.notice') }}</p>
        </div>

        {{-- ============================================================
             Counters (click to filter)
        ============================================================= --}}
        <section class="grid grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-4">
            @foreach ([
                'authorized' => ['icon' => 'check-circle', 'tone' => 'text-success-600 dark:text-success-400', 'chip' => 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'],
                'pending' => ['icon' => 'clock', 'tone' => 'text-warning-600 dark:text-warning-400', 'chip' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400'],
                'problem' => ['icon' => 'alert', 'tone' => 'text-error-600 dark:text-error-400', 'chip' => 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400'],
                'logged_out' => ['icon' => 'unlink', 'tone' => 'text-gray-900 dark:text-white', 'chip' => 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300'],
            ] as $key => $tile)
                <x-driver-check.stat-card
                    :label="__('telegram.sessions.stats.' . $key)"
                    :icon="$tile['icon']"
                    :tone="$tile['tone']"
                    :chip="$tile['chip']"
                    role="button"
                    tabindex="0"
                    class="dc-tap cursor-pointer select-none"
                    ::class="filters.state === '{{ $key }}' && 'ring-2 ring-brand-500/60 dark:ring-brand-500/50'"
                    x-on:click="filterByState('{{ $key }}')"
                    x-on:keydown.enter.prevent="filterByState('{{ $key }}')"
                >
                    <span x-text="number(stats.{{ $key }})">0</span>
                </x-driver-check.stat-card>
            @endforeach
        </section>

        <x-driver-check.error-alert :title="__('telegram.sessions.errors.title')" />

        {{-- ============================================================
             Rows
        ============================================================= --}}
        <x-driver-check.surface class="overflow-hidden" x-ref="listTop">
            <x-driver-check.filters
                embedded
                :search-placeholder="__('telegram.sessions.search_placeholder')"
                :sort-options="[
                        'created_at' => __('telegram.ui.created'),
                        'phone' => __('telegram.sessions.table.phone'),
                        'authorized_at' => __('telegram.sessions.table.authorized_at'),
                        'last_checked_at' => __('telegram.sessions.table.last_checked'),
                    ]"
            >
                <x-driver-check.field :label="__('telegram.sessions.filters.state')">
                    <x-driver-check.select x-model="filters.state" x-on:change="applyFilters()">
                        <option value="">{{ __('telegram.sessions.filters.state_all') }}</option>
                        <option value="authorized">{{ __('telegram.sessions.stats.authorized') }}</option>
                        <option value="pending">{{ __('telegram.sessions.stats.pending') }}</option>
                        <option value="problem">{{ __('telegram.sessions.stats.problem') }}</option>
                        <option value="logged_out">{{ __('telegram.sessions.stats.logged_out') }}</option>
                    </x-driver-check.select>
                </x-driver-check.field>
            </x-driver-check.filters>

            {{-- Table (lg and up) --}}
            <div class="hidden lg:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] table-fixed text-left">
                        <colgroup>
                            <col class="w-[26%]">
                            <col class="w-[22%]">
                            <col class="w-[26%]">
                            <col class="w-[12%]">
                            <col class="w-[14%]">
                        </colgroup>

                        <thead class="dc-thead border-b border-gray-200 bg-gray-50/70 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr class="text-[10px] uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                                <th scope="col" class="px-4 py-2.5 font-semibold">{{ __('telegram.sessions.table.account') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.sessions.table.status') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.sessions.table.processes') }}</th>
                                <th scope="col" class="px-3 py-2.5 font-semibold">{{ __('telegram.sessions.table.last_checked') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right font-semibold">{{ __('telegram.sessions.table.actions') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <x-driver-check.skeleton-rows :cols="5" />

                            <template x-for="row in rows" :key="row.id">
                                <tr class="group align-middle transition hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                    {{-- Account --}}
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <x-driver-check.avatar
                                                size="sm"
                                                ::class="avatarClass(row)"
                                                x-text="avatarText(row)"
                                            />

                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5">
                                                    <p
                                                        class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                                        :title="displayName(row)"
                                                        x-text="displayName(row)"
                                                    ></p>

                                                    <span
                                                        x-show="row.is_primary"
                                                        x-cloak
                                                        class="shrink-0 rounded-md bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold
                                                               text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
                                                        title="{{ __('telegram.sessions.primary_hint') }}"
                                                    >{{ __('telegram.sessions.primary') }}</span>
                                                </div>

                                                <div class="flex items-center gap-1">
                                                    <p class="truncate text-[11px] tabular-nums text-gray-500 dark:text-gray-400">
                                                        <span x-text="phone(row.phone)"></span>
                                                        <template x-if="row.username">
                                                            <span x-text="' · ' + handle(row.username)"></span>
                                                        </template>
                                                    </p>

                                                    <x-driver-check.copy-button value="row.phone" />
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-3 py-3">
                                        <x-driver-check.badge ::class="stateClass(row)">
                                            <span class="h-1.5 w-1.5 rounded-full" :class="stateDot(row)"></span>
                                            <span x-text="stateLabel(row)"></span>
                                        </x-driver-check.badge>

                                        <p
                                            x-show="row.last_error"
                                            x-cloak
                                            class="mt-1 line-clamp-2 text-[11px] text-error-600 dark:text-error-400"
                                            :title="row.last_error"
                                            x-text="errorText(row.last_error)"
                                        ></p>
                                    </td>

                                    {{-- Processes --}}
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <template x-for="process in processes(row)" :key="process.id">
                                                <x-driver-check.badge
                                                    ::class="processClass(process)"
                                                    ::title="translations.processes.states[processState(process)]
                                                        + ' · ✓ ' + number(process.successes)
                                                        + ' · ✗ ' + number(process.failures)"
                                                >
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="processDot(process)"></span>
                                                    <span x-text="processLabel(process)"></span>
                                                </x-driver-check.badge>
                                            </template>

                                            <span
                                                x-show="processes(row).length === 0"
                                                class="text-[12px] text-gray-400 dark:text-gray-500"
                                            >{{ __('telegram.sessions.processes.none') }}</span>
                                        </div>
                                    </td>

                                    {{-- Last check --}}
                                    <td class="px-3 py-3">
                                        <p
                                            class="text-[13px] text-gray-700 dark:text-gray-300"
                                            :title="row.last_checked_at ? date(row.last_checked_at) : ''"
                                            x-text="row.last_checked_at ? relative(row.last_checked_at) : ui.never"
                                        ></p>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <template x-if="canContinueLogin(row)">
                                                <x-driver-check.button size="sm" variant="primary" x-on:click="openLogin(row)">
                                                    {{ __('telegram.sessions.actions.continue') }}
                                                </x-driver-check.button>
                                            </template>

                                            <x-driver-check.row-action icon="eye" :label="__('telegram.ui.details')" x-on:click="openDetail(row)" />
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
                            <x-driver-check.avatar ::class="avatarClass(row)" x-text="avatarText(row)" />

                            <div class="min-w-0 flex-1">
                                <p class="dc-break text-sm font-semibold text-gray-900 dark:text-white">
                                    <span x-text="displayName(row)"></span>
                                    <span
                                        x-show="row.is_primary"
                                        x-cloak
                                        class="ml-1 rounded-md bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold
                                               text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
                                    >{{ __('telegram.sessions.primary') }}</span>
                                </p>
                                <p class="dc-break text-[11px] tabular-nums text-gray-500 dark:text-gray-400">
                                    <span x-text="phone(row.phone)"></span>
                                    <template x-if="row.username">
                                        <span x-text="' · ' + handle(row.username)"></span>
                                    </template>
                                </p>
                            </div>

                            <x-driver-check.badge ::class="stateClass(row)">
                                <span class="h-1.5 w-1.5 rounded-full" :class="stateDot(row)"></span>
                                <span x-text="stateLabel(row)"></span>
                            </x-driver-check.badge>
                        </div>

                        <div x-show="processes(row).length > 0" class="flex flex-wrap gap-1">
                            <template x-for="process in processes(row)" :key="'card-p-' + process.id">
                                <x-driver-check.badge ::class="processClass(process)">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="processDot(process)"></span>
                                    <span x-text="processLabel(process)"></span>
                                </x-driver-check.badge>
                            </template>
                        </div>

                        <p
                            x-show="row.last_error"
                            x-cloak
                            class="dc-break rounded-xl bg-error-50 px-3 py-2 text-[11px] leading-snug text-error-600
                                   dark:bg-error-500/10 dark:text-error-400"
                            x-text="errorText(row.last_error)"
                        ></p>

                        <div class="grid grid-cols-2 gap-2">
                            <template x-if="canContinueLogin(row)">
                                <x-driver-check.button variant="primary" x-on:click="openLogin(row)">
                                    {{ __('telegram.sessions.actions.continue') }}
                                </x-driver-check.button>
                            </template>

                            <x-driver-check.button
                                x-on:click="openDetail(row)"
                                ::class="!canContinueLogin(row) && 'col-span-2'"
                            >
                                {{ __('telegram.ui.details') }}
                            </x-driver-check.button>
                        </div>
                    </article>
                </template>
            </div>

            <x-driver-check.empty-state
                icon="telegram"
                :title="__('telegram.sessions.empty.title')"
                :description="__('telegram.sessions.empty.description')"
            >
                <div class="mt-3">
                    <x-driver-check.button variant="primary" size="sm" icon="plus" x-on:click="openLogin()">
                        {{ __('telegram.sessions.create') }}
                    </x-driver-check.button>
                </div>
            </x-driver-check.empty-state>

            <x-driver-check.pagination />
        </x-driver-check.surface>
    </x-driver-check.shell>

    {{-- ================================================================
         Details
    ================================================================= --}}
    <x-driver-check.modal open="detailOpen" close="closeDetail()" size="sm:max-w-xl">
        <x-slot:heading>
            <span x-text="detail ? displayName(detail) : ''"></span>
        </x-slot:heading>

        <template x-if="detail">
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <x-driver-check.badge ::class="stateClass(detail)">
                        <span class="h-1.5 w-1.5 rounded-full" :class="stateDot(detail)"></span>
                        <span x-text="stateLabel(detail)"></span>
                    </x-driver-check.badge>

                    <span
                        x-show="detail.is_primary"
                        x-cloak
                        class="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-medium text-brand-600
                               dark:bg-brand-500/10 dark:text-brand-400"
                    >{{ __('telegram.sessions.primary_hint') }}</span>
                </div>

                <p
                    x-show="stateHint(detail)"
                    x-cloak
                    class="text-[13px] leading-relaxed text-gray-600 dark:text-gray-300"
                    x-text="stateHint(detail)"
                ></p>

                <div
                    x-show="detail.last_error"
                    x-cloak
                    class="dc-break rounded-xl bg-error-50 px-3 py-2.5 text-xs text-error-700 dark:bg-error-500/10 dark:text-error-400"
                >
                    <p class="font-semibold" x-text="errorText(detail.last_error)"></p>
                    <p
                        x-show="errorText(detail.last_error) !== detail.last_error"
                        class="mt-1 font-mono text-[11px] opacity-75"
                        x-text="detail.last_error"
                    ></p>
                </div>

                <dl class="grid grid-cols-2 gap-x-3 gap-y-3 rounded-xl bg-gray-50 p-3 dark:bg-white/[0.02]">
                    <x-driver-check.kv :label="__('telegram.sessions.table.phone')">
                        <span class="tabular-nums" x-text="phone(detail.phone)"></span>
                    </x-driver-check.kv>

                    <x-driver-check.kv :label="__('telegram.sessions.fields.telegram_id')">
                        <span class="tabular-nums" x-text="detail.telegram_user_id ?? dash"></span>
                    </x-driver-check.kv>

                    <x-driver-check.kv :label="__('telegram.sessions.fields.name')">
                        <span x-text="detail.name || dash"></span>
                    </x-driver-check.kv>

                    <x-driver-check.kv :label="__('telegram.sessions.fields.username')">
                        <a
                            x-show="detail.username"
                            :href="telegramUrl(detail.username)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-brand-600 hover:underline dark:text-brand-400"
                            x-text="handle(detail.username)"
                        ></a>
                        <span x-show="!detail.username" x-text="dash"></span>
                    </x-driver-check.kv>

                    <x-driver-check.kv :label="__('telegram.sessions.table.authorized_at')">
                        <span x-text="detail.authorized_at ? date(detail.authorized_at) : dash"></span>
                    </x-driver-check.kv>

                    <x-driver-check.kv :label="__('telegram.sessions.table.last_checked')">
                        <span x-text="detail.last_checked_at ? date(detail.last_checked_at) : ui.never"></span>
                    </x-driver-check.kv>

                    <x-driver-check.kv :label="__('telegram.sessions.fields.session_file')" class="col-span-2">
                        <span
                            :class="detail.session_exists
                                ? 'text-success-600 dark:text-success-400'
                                : 'text-gray-500 dark:text-gray-400'"
                            x-text="detail.session_exists
                                ? translations.fields.session_file_yes
                                : translations.fields.session_file_no"
                        ></span>
                    </x-driver-check.kv>
                </dl>

                {{-- Processes --}}
                <section>
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ __('telegram.sessions.table.processes') }}
                    </h3>

                    <p
                        x-show="processes(detail).length === 0"
                        class="rounded-xl border border-dashed border-gray-200 px-3 py-3 text-[13px] text-gray-500
                               dark:border-gray-800 dark:text-gray-400"
                    >{{ __('telegram.sessions.processes.none_hint') }}</p>

                    <ul class="flex flex-col gap-2">
                        <template x-for="process in processes(detail)" :key="'d-' + process.id">
                            <li class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="processLabel(process)"></p>

                                        <p class="mt-0.5 text-[11px] tabular-nums text-gray-500 dark:text-gray-400">
                                            <span x-text="translations.processes.successes + ': ' + number(process.successes)"></span>
                                            ·
                                            <span x-text="translations.processes.failures + ': ' + number(process.failures)"></span>
                                            ·
                                            <span x-text="translations.processes.streak + ': ' + number(process.consecutive_failures)"></span>
                                        </p>
                                    </div>

                                    <x-driver-check.badge ::class="processClass(process)">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="processDot(process)"></span>
                                        <span x-text="translations.processes.states[processState(process)]"></span>
                                    </x-driver-check.badge>
                                </div>

                                <p
                                    x-show="!process.is_available && process.disabled_reason"
                                    x-cloak
                                    class="dc-break mt-2 text-[11px] text-error-600 dark:text-error-400"
                                    x-text="translations.processes.disabled_reason + ': ' + errorText(process.disabled_reason)"
                                ></p>

                                <p
                                    x-show="process.is_stale_busy"
                                    x-cloak
                                    class="mt-2 text-[11px] text-warning-700 dark:text-warning-400"
                                    x-text="translations.processes.stuck_hint.replace(':time', relative(process.busy_at))"
                                ></p>

                                <div class="mt-2.5 flex justify-end">
                                    <x-driver-check.button
                                        size="sm"
                                        x-show="process.is_available"
                                        x-on:click="toggleProcess(detail, process)"
                                        ::disabled="!!detailBusy"
                                    >
                                        <x-driver-check.icon
                                            name="refresh"
                                            class="h-4 w-4 animate-spin"
                                            x-show="detailBusy === 'process-' + process.process"
                                            x-cloak
                                        />
                                        {{ __('telegram.sessions.processes.disable') }}
                                    </x-driver-check.button>

                                    <x-driver-check.button
                                        size="sm"
                                        variant="primary"
                                        x-show="!process.is_available"
                                        x-cloak
                                        x-on:click="toggleProcess(detail, process)"
                                        ::disabled="!!detailBusy"
                                    >
                                        <x-driver-check.icon
                                            name="refresh"
                                            class="h-4 w-4 animate-spin"
                                            x-show="detailBusy === 'process-' + process.process"
                                            x-cloak
                                        />
                                        {{ __('telegram.sessions.processes.enable') }}
                                    </x-driver-check.button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </section>

                <p
                    x-show="detailError"
                    x-cloak
                    class="dc-break rounded-xl bg-error-50 px-3 py-2.5 text-xs text-error-600 dark:bg-error-500/10 dark:text-error-400"
                    x-text="detailError"
                ></p>
            </div>
        </template>

        <x-slot:footer>
            <template x-if="detail && canDelete(detail)">
                <x-driver-check.button variant="ghost" x-on:click="askConfirm('delete')" ::disabled="!!detailBusy">
                    {{ __('telegram.sessions.actions.delete') }}
                </x-driver-check.button>
            </template>

            <template x-if="detail && canLogout(detail)">
                <x-driver-check.button variant="danger" x-on:click="askConfirm('logout')" ::disabled="!!detailBusy">
                    {{ __('telegram.sessions.actions.logout') }}
                </x-driver-check.button>
            </template>

            <template x-if="detail && canRestartLogin(detail)">
                <x-driver-check.button variant="primary" x-on:click="openLogin(detail)">
                    {{ __('telegram.sessions.actions.login_again') }}
                </x-driver-check.button>
            </template>

            <template x-if="detail && canContinueLogin(detail)">
                <x-driver-check.button variant="primary" x-on:click="openLogin(detail)">
                    {{ __('telegram.sessions.actions.continue') }}
                </x-driver-check.button>
            </template>

            <template x-if="detail && canCheck(detail)">
                <x-driver-check.button variant="primary" x-on:click="checkSession(detail)" ::disabled="!!detailBusy">
                    <x-driver-check.icon
                        name="refresh"
                        class="h-4 w-4"
                        ::class="detailBusy === 'check' && 'animate-spin'"
                    />
                    {{ __('telegram.sessions.actions.check') }}
                </x-driver-check.button>
            </template>
        </x-slot:footer>
    </x-driver-check.modal>

    {{-- ================================================================
         Login wizard: phone -> code -> [2FA password] -> done
    ================================================================= --}}
    <x-driver-check.modal open="loginOpen" close="closeLogin()" size="sm:max-w-md">
        <x-slot:heading>
            <span x-text="login.account ? phone(login.account.phone) : translations.login.title"></span>
        </x-slot:heading>

        {{-- Progress --}}
        <ol class="mb-4 flex items-center gap-2 text-[11px] font-medium text-gray-400 dark:text-gray-500">
            <template x-for="(step, index) in ['phone', 'code', 'password']" :key="step">
                <li class="flex flex-1 items-center gap-2">
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                        :class="{
                            done: 'bg-success-500 text-white',
                            current: 'bg-brand-500 text-white',
                            todo: 'bg-gray-100 dark:bg-white/[0.06]',
                        }[stepperState(index)]"
                        x-text="index + 1"
                    ></span>
                    <span class="truncate" x-text="translations.login.steps[step]"></span>
                </li>
            </template>
        </ol>

        {{-- Step: phone --}}
        <form
            x-show="loginStep() === 'phone'"
            id="session-login-phone"
            x-on:submit.prevent="sendPhone()"
            class="flex flex-col gap-3"
        >
            <x-driver-check.field
                :label="__('telegram.sessions.login.phone')"
                :hint="__('telegram.sessions.login.phone_hint')"
                for="session-phone"
            >
                <x-driver-check.input
                    id="session-phone"
                    type="tel"
                    inputmode="tel"
                    autocomplete="tel"
                    x-ref="loginPhone"
                    x-model="login.phone"
                    placeholder="+998901234567"
                    required
                    ::data-invalid="!!loginFieldError('phone')"
                />

                <x-slot:error>
                    <p
                        x-show="loginFieldError('phone')"
                        x-cloak
                        class="mt-1 text-[11px] font-medium text-error-600 dark:text-error-400"
                        x-text="loginFieldError('phone')"
                    ></p>
                </x-slot:error>
            </x-driver-check.field>
        </form>

        {{-- Step: waiting for the CLI --}}
        <div x-show="loginWaiting()" x-cloak class="flex flex-col items-center py-6 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/10 dark:text-blue-light-400">
                <x-driver-check.icon name="refresh" class="h-5 w-5 animate-spin" />
            </span>

            <p
                class="mt-3 text-sm font-medium text-gray-900 dark:text-white"
                x-text="loginStep() === 'sending' ? translations.login.sending : translations.login.verifying"
            ></p>

            <p class="mt-1 max-w-xs text-[12px] leading-relaxed text-gray-500 dark:text-gray-400">
                {{ __('telegram.sessions.login.waiting_hint') }}
            </p>
        </div>

        {{-- Step: code --}}
        <form
            x-show="loginStep() === 'code'"
            x-cloak
            id="session-login-code"
            x-on:submit.prevent="sendCode()"
            class="flex flex-col gap-3"
        >
            <x-driver-check.field
                :label="__('telegram.sessions.login.code')"
                :hint="__('telegram.sessions.login.code_hint')"
                for="session-code"
            >
                <x-driver-check.input
                    id="session-code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="12"
                    x-ref="loginCode"
                    x-model="login.code"
                    placeholder="12345"
                    required
                    class="text-center font-mono text-lg tracking-[0.4em]"
                    ::data-invalid="!!loginFieldError('code')"
                />

                <x-slot:error>
                    <p
                        x-show="loginFieldError('code')"
                        x-cloak
                        class="mt-1 text-[11px] font-medium text-error-600 dark:text-error-400"
                        x-text="loginFieldError('code')"
                    ></p>
                </x-slot:error>
            </x-driver-check.field>

            <p class="flex items-start gap-2 rounded-xl bg-warning-50 px-3 py-2 text-[11px] leading-snug text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
                <x-driver-check.icon name="alert" class="mt-px h-3.5 w-3.5 shrink-0" />
                <span>{{ __('telegram.sessions.login.code_warning') }}</span>
            </p>
        </form>

        {{-- Step: 2FA password --}}
        <form
            x-show="loginStep() === 'password'"
            x-cloak
            id="session-login-password"
            x-on:submit.prevent="sendPassword()"
            class="flex flex-col gap-3"
        >
            <p
                x-show="login.account?.status === 'password_invalid'"
                x-cloak
                class="rounded-xl bg-error-50 px-3 py-2 text-xs text-error-700 dark:bg-error-500/10 dark:text-error-400"
                x-text="errorText(login.account?.last_error) || translations.telegram_errors.PASSWORD_HASH_INVALID"
            ></p>

            <x-driver-check.field
                :label="__('telegram.sessions.login.password')"
                :hint="__('telegram.sessions.login.password_hint')"
                for="session-password"
            >
                <x-driver-check.input
                    id="session-password"
                    type="password"
                    autocomplete="current-password"
                    x-ref="loginPassword"
                    x-model="login.password"
                    required
                    ::data-invalid="!!loginFieldError('password')"
                />

                <x-slot:error>
                    <p
                        x-show="login.account?.password_hint"
                        x-cloak
                        class="dc-break mt-1.5 rounded-lg bg-gray-50 px-2.5 py-1.5 text-[11px] text-gray-500 dark:bg-white/[0.03] dark:text-gray-400"
                    >
                        {{ __('telegram.sessions.login.hint') }}:
                        <span class="font-medium text-gray-700 dark:text-gray-200" x-text="login.account?.password_hint"></span>
                    </p>

                    <p
                        x-show="loginFieldError('password')"
                        x-cloak
                        class="mt-1 text-[11px] font-medium text-error-600 dark:text-error-400"
                        x-text="loginFieldError('password')"
                    ></p>
                </x-slot:error>
            </x-driver-check.field>
        </form>

        {{-- Step: done --}}
        <div x-show="loginStep() === 'done'" x-cloak class="flex flex-col items-center py-6 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400">
                <x-driver-check.icon name="check-circle" class="h-6 w-6" />
            </span>

            <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">{{ __('telegram.sessions.login.done') }}</p>

            <p class="mt-1 text-[13px] text-gray-500 dark:text-gray-400">
                <span x-text="login.account?.name || ''"></span>
                <span x-show="login.account?.username" x-text="' ' + handle(login.account?.username)"></span>
            </p>
        </div>

        {{-- Step: failed --}}
        <div x-show="loginStep() === 'failed'" x-cloak class="flex flex-col items-center py-6 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400">
                <x-driver-check.icon name="x-circle" class="h-6 w-6" />
            </span>

            <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white" x-text="stateLabel(login.account)"></p>

            <p
                class="dc-break mt-1 max-w-xs text-[13px] leading-relaxed text-gray-500 dark:text-gray-400"
                x-text="errorText(login.account?.last_error) || stateHint(login.account)"
            ></p>
        </div>

        <p
            x-show="login.error"
            x-cloak
            class="dc-break mt-3 rounded-xl bg-error-50 px-3 py-2.5 text-xs text-error-600 dark:bg-error-500/10 dark:text-error-400"
            x-text="login.error"
        ></p>

        <x-slot:footer>
            <x-driver-check.button x-on:click="closeLogin()" ::disabled="login.submitting">
                <span x-text="loginStep() === 'done' ? ui.close : ui.cancel"></span>
            </x-driver-check.button>

            <template x-if="loginStep() === 'phone'">
                <x-driver-check.button variant="primary" type="submit" form="session-login-phone" ::disabled="login.submitting">
                    <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin" x-show="login.submitting" x-cloak />
                    {{ __('telegram.sessions.login.send_code') }}
                </x-driver-check.button>
            </template>

            <template x-if="loginStep() === 'code'">
                <x-driver-check.button variant="primary" type="submit" form="session-login-code" ::disabled="login.submitting">
                    <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin" x-show="login.submitting" x-cloak />
                    {{ __('telegram.sessions.login.verify') }}
                </x-driver-check.button>
            </template>

            <template x-if="loginStep() === 'password'">
                <x-driver-check.button variant="primary" type="submit" form="session-login-password" ::disabled="login.submitting">
                    <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin" x-show="login.submitting" x-cloak />
                    {{ __('telegram.sessions.login.verify') }}
                </x-driver-check.button>
            </template>

            <template x-if="loginStep() === 'code'">
                <x-driver-check.button variant="ghost" x-on:click="restartLogin()" ::disabled="login.submitting">
                    {{ __('telegram.sessions.login.resend') }}
                </x-driver-check.button>
            </template>

            <template x-if="loginStep() === 'failed'">
                <x-driver-check.button variant="primary" x-on:click="restartLogin()" ::disabled="login.submitting">
                    <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin" x-show="login.submitting" x-cloak />
                    {{ __('telegram.sessions.login.resend') }}
                </x-driver-check.button>
            </template>
        </x-slot:footer>
    </x-driver-check.modal>

    {{-- ================================================================
         Confirmation: logout / delete
    ================================================================= --}}
    <x-driver-check.modal open="confirmOpen" close="closeConfirm()" size="sm:max-w-md">
        <x-slot:heading>
            <span x-text="confirmAction === 'delete' ? translations.confirm.delete_title : translations.confirm.logout_title"></span>
        </x-slot:heading>

        <p
            class="text-[13px] leading-relaxed text-gray-600 dark:text-gray-300"
            x-text="confirmAction === 'delete' ? translations.confirm.delete_text : translations.confirm.logout_text"
        ></p>

        <p
            x-show="confirmAction === 'logout' && detail?.is_primary"
            x-cloak
            class="mt-3 flex items-start gap-2 rounded-xl bg-warning-50 px-3 py-2.5 text-xs leading-snug text-warning-800
                   dark:bg-warning-500/10 dark:text-warning-300"
        >
            <x-driver-check.icon name="alert" class="mt-px h-4 w-4 shrink-0" />
            <span>{{ __('telegram.sessions.confirm.logout_primary') }}</span>
        </p>

        <p
            class="dc-break mt-3 rounded-xl bg-gray-50 px-3 py-2 text-[13px] font-medium text-gray-700 dark:bg-white/[0.03] dark:text-gray-200"
            x-text="detail ? displayName(detail) + ' · ' + phone(detail.phone) : ''"
        ></p>

        <p
            x-show="confirmError"
            x-cloak
            class="dc-break mt-3 rounded-xl bg-error-50 px-3 py-2.5 text-xs text-error-600 dark:bg-error-500/10 dark:text-error-400"
            x-text="confirmError"
        ></p>

        <x-slot:footer>
            <x-driver-check.button x-on:click="closeConfirm()" ::disabled="confirmBusy">
                {{ __('telegram.ui.cancel') }}
            </x-driver-check.button>

            <x-driver-check.button variant="danger" x-on:click="confirm()" ::disabled="confirmBusy">
                <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin" x-show="confirmBusy" x-cloak />
                <span x-text="confirmAction === 'delete' ? translations.actions.delete : translations.actions.logout"></span>
            </x-driver-check.button>
        </x-slot:footer>
    </x-driver-check.modal>
</div>

@endsection
