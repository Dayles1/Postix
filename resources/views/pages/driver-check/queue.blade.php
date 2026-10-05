@extends('layouts.app')

@section('title', __('telegram.queue.title'))

@section('content')

<div
    x-data="dcQueue({
        endpoints: {
            summary: @js(route('api.telegram.queue.summary')),
            jobs: @js(route('api.telegram.queue.jobs')),
            failed: @js(route('api.telegram.queue.failed')),
            failedBase: @js(url('/api/telegram/queue/failed')),
            move: @js(route('api.telegram.queue.move')),
            retryAll: @js(route('api.telegram.queue.retry-all')),
            flush: @js(route('api.telegram.queue.flush')),
        },
        translations: @js(__('telegram.queue')),
        ui: @js(__('telegram.ui')),
    })"
>
    <x-driver-check.shell>

        {{-- ============================================================
             Header
        ============================================================= --}}
        <x-driver-check.page-header
            icon="inbox"
            tone="blue"
            :eyebrow="__('telegram.menu.groups.monitoring')"
            :title="__('telegram.queue.title')"
            :description="__('telegram.queue.description')"
        >
            <x-slot:actions>
                <x-driver-check.button x-on:click="refreshAll()" ::disabled="loading">
                    <x-driver-check.icon name="refresh" class="h-4 w-4" ::class="loading && 'animate-spin'" />
                    <span x-text="ui.refresh"></span>
                </x-driver-check.button>
            </x-slot:actions>
        </x-driver-check.page-header>

        <p
            x-show="summaryError"
            x-cloak
            class="rounded-2xl border border-error-200 bg-error-25 px-4 py-3 text-[13px] text-error-700 dark:border-error-500/30 dark:bg-error-500/[0.07] dark:text-error-400"
            x-text="summaryError"
        ></p>

        {{-- ============================================================
             Worker
        ============================================================= --}}
        <template x-if="summary">
            <section class="flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5" :class="workerBanner()">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="mt-0.5 shrink-0">
                        <x-driver-check.icon name="check-circle" class="h-6 w-6" x-show="workerState() === 'alive'" />
                        <x-driver-check.icon name="x-circle" class="h-6 w-6" x-show="workerState() === 'dead'" x-cloak />
                        <x-driver-check.icon name="alert" class="h-6 w-6" x-show="workerState() === 'never'" x-cloak />
                    </span>

                    <div class="min-w-0">
                        <p class="text-base font-semibold" x-text="t.worker[workerState()]"></p>
                        <p
                            x-show="workerState() !== 'alive'"
                            class="mt-0.5 font-mono text-[12px] leading-relaxed opacity-90"
                            x-text="workerState() === 'dead' ? t.worker.dead_hint : t.worker.never_hint"
                        ></p>

                        <p x-show="worker()?.pulse_at" class="mt-1 text-[12px] opacity-80">
                            <span x-text="t.worker.pulse + ': ' + relative(worker()?.pulse_at)"></span>
                            <template x-if="worker()?.queues">
                                <span x-text="' · ' + t.worker.listens + ': ' + worker().queues.join(', ')"></span>
                            </template>
                            <template x-if="worker()?.pid">
                                <span class="font-mono" x-text="' · PID ' + worker().pid"></span>
                            </template>
                        </p>
                    </div>
                </div>

                <dl class="grid shrink-0 grid-cols-1 gap-2 text-[12px] sm:w-72">
                    <div x-show="worker()?.last_processed" class="rounded-lg bg-white/60 px-2.5 py-1.5 dark:bg-white/[0.04]">
                        <dt class="text-[10px] font-semibold uppercase tracking-wider opacity-70" x-text="t.worker.last_processed"></dt>
                        <dd class="truncate">
                            <span class="font-medium" x-text="worker()?.last_processed?.job"></span>
                            <span class="opacity-70" x-text="' · ' + relative(worker()?.last_processed?.at)"></span>
                        </dd>
                    </div>

                    <div x-show="worker()?.last_failed" x-cloak class="rounded-lg bg-white/60 px-2.5 py-1.5 dark:bg-white/[0.04]">
                        <dt class="text-[10px] font-semibold uppercase tracking-wider opacity-70" x-text="t.worker.last_failed"></dt>
                        <dd class="truncate" :title="worker()?.last_failed?.error">
                            <span class="font-medium" x-text="worker()?.last_failed?.job"></span>
                            <span class="opacity-70" x-text="' · ' + relative(worker()?.last_failed?.at)"></span>
                        </dd>
                    </div>
                </dl>
            </section>
        </template>

        {{-- ============================================================
             Counters (the main queue)
        ============================================================= --}}
        <section class="grid grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-4">
            <x-driver-check.stat-card :label="__('telegram.queue.stats.ready')" icon="clock"
                tone="text-success-600 dark:text-success-400"
                chip="bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400">
                <span x-text="number(mainQueue().ready)">0</span>
            </x-driver-check.stat-card>

            <x-driver-check.stat-card :label="__('telegram.queue.stats.delayed')" icon="calendar">
                <span x-text="number(mainQueue().delayed)">0</span>
            </x-driver-check.stat-card>

            <x-driver-check.stat-card :label="__('telegram.queue.stats.reserved')" icon="refresh"
                tone="text-blue-light-600 dark:text-blue-light-400"
                chip="bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/10 dark:text-blue-light-400">
                <span x-text="number(mainQueue().reserved)">0</span>
            </x-driver-check.stat-card>

            <x-driver-check.stat-card :label="__('telegram.queue.stats.failed')" icon="alert"
                role="button" tabindex="0" class="dc-tap cursor-pointer"
                x-on:click="switchTab('failed')">
                <span :class="failedTotal() > 0 ? 'text-error-600 dark:text-error-400' : 'text-gray-900 dark:text-white'"
                    x-text="number(failedTotal())">0</span>
                <span class="block text-[11px] font-normal text-gray-500 dark:text-gray-400"
                    x-text="t.stats.failed_day.replace(':count', number(summary?.failed?.last_day ?? 0))"></span>
            </x-driver-check.stat-card>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">

            {{-- ========================================================
                 Queues
            ========================================================= --}}
            <x-driver-check.surface class="overflow-hidden">
                <h2 class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-900 dark:border-gray-800 dark:text-white">
                    {{ __('telegram.queue.queues.title') }}
                </h2>

                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    <template x-for="queue in queues()" :key="'q-' + queue.queue">
                        <li class="flex flex-col gap-2 px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-sm font-semibold text-gray-900 dark:text-white" x-text="queue.queue"></span>

                                <span
                                    x-show="queue.is_main"
                                    class="rounded-md bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold text-brand-600 dark:bg-brand-500/10 dark:text-brand-400"
                                    x-text="t.queues.main"
                                ></span>

                                <span
                                    x-show="isOrphan(queue)"
                                    x-cloak
                                    class="rounded-md bg-error-50 px-1.5 py-0.5 text-[10px] font-semibold text-error-600 dark:bg-error-500/10 dark:text-error-400"
                                    :title="t.queues.not_listened_hint"
                                    x-text="t.queues.not_listened"
                                ></span>

                                <span class="ml-auto text-[12px] tabular-nums text-gray-500 dark:text-gray-400" x-text="number(queue.total)"></span>
                            </div>

                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-[12px] tabular-nums text-gray-600 dark:text-gray-400">
                                <span x-text="t.stats.ready + ': ' + number(queue.ready)"></span>
                                <span x-text="t.stats.delayed + ': ' + number(queue.delayed)"></span>
                                <span x-text="t.stats.reserved + ': ' + number(queue.reserved)"></span>
                                <span
                                    :class="queue.stuck > 0 && 'font-semibold text-warning-700 dark:text-warning-400'"
                                    x-text="t.queues.stuck + ': ' + number(queue.stuck)"
                                ></span>
                                <span
                                    x-show="queue.oldest_wait_seconds !== null"
                                    x-text="t.queues.oldest + ': ' + duration(queue.oldest_wait_seconds)"
                                ></span>
                            </div>

                            <p x-show="isOrphan(queue)" x-cloak class="text-[12px] text-error-600 dark:text-error-400" x-text="t.queues.not_listened_hint"></p>

                            <div x-show="!queue.is_main && queue.total > queue.reserved" x-cloak>
                                <x-driver-check.button size="sm" variant="primary" x-on:click="askConfirm('move', { queue: queue.queue })">
                                    {{ __('telegram.queue.queues.move') }}
                                </x-driver-check.button>
                            </div>
                        </li>
                    </template>
                </ul>
            </x-driver-check.surface>

            {{-- ========================================================
                 Waiting, by job class
            ========================================================= --}}
            <x-driver-check.surface class="overflow-hidden">
                <h2 class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-900 dark:border-gray-800 dark:text-white">
                    {{ __('telegram.queue.classes.title') }}
                </h2>

                <p x-show="summary && classes().length === 0" x-cloak class="px-4 py-6 text-center text-[13px] text-gray-500 dark:text-gray-400" x-text="t.classes.empty"></p>

                <table x-show="classes().length > 0" class="w-full text-left text-[13px]">
                    <thead class="bg-gray-50/70 text-[10px] uppercase tracking-[0.08em] text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2 font-semibold">{{ __('telegram.queue.classes.job') }}</th>
                            <th class="px-2 py-2 text-right font-semibold">{{ __('telegram.queue.stats.ready') }}</th>
                            <th class="px-2 py-2 text-right font-semibold">{{ __('telegram.queue.stats.delayed') }}</th>
                            <th class="px-4 py-2 text-right font-semibold">{{ __('telegram.queue.classes.total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template x-for="row in classes()" :key="'c-' + row.job_class">
                            <tr>
                                <td class="max-w-0 px-4 py-2">
                                    <button
                                        type="button"
                                        class="block w-full truncate text-left font-medium text-gray-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                                        :title="row.job_class"
                                        x-on:click="filters.tab = 'pending'; filters.search = row.job || ''; applyFilters()"
                                        x-text="row.job || dash"
                                    ></button>
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300" x-text="number(row.ready)"></td>
                                <td class="px-2 py-2 text-right tabular-nums text-gray-700 dark:text-gray-300" x-text="number(row.delayed)"></td>
                                <td class="px-4 py-2 text-right font-semibold tabular-nums text-gray-900 dark:text-white" x-text="number(row.total)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </x-driver-check.surface>
        </section>

        {{-- ============================================================
             Tabs
        ============================================================= --}}
        <div class="inline-flex w-full gap-1 self-start rounded-xl bg-gray-100 p-1 sm:w-auto dark:bg-white/[0.04]" role="tablist">
            @foreach (['pending', 'failed'] as $tab)
                <button
                    type="button"
                    role="tab"
                    x-on:click="switchTab('{{ $tab }}')"
                    :aria-selected="filters.tab === '{{ $tab }}'"
                    class="dc-tap inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-lg px-4 text-[13px] font-medium transition sm:flex-none"
                    :class="filters.tab === '{{ $tab }}'
                        ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white'
                        : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
                >
                    {{ __('telegram.queue.tabs.' . $tab) }}
                    @if ($tab === 'failed')
                        <span
                            x-show="failedTotal() > 0"
                            x-cloak
                            class="ml-1 rounded-full bg-error-500 px-1.5 py-0.5 text-[10px] font-semibold text-white"
                            x-text="number(failedTotal())"
                        ></span>
                    @endif
                </button>
            @endforeach
        </div>

        <x-driver-check.error-alert :title="__('telegram.queue.errors.title')" />

        {{-- One card for the jobs: the filters on top, then the list of the tab --}}
        <x-driver-check.surface class="overflow-hidden" x-ref="listTop">
            <div class="border-b border-gray-200 p-3 sm:p-4 dark:border-gray-800">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="sm:col-span-1">
                        <x-driver-check.search
                            model="filters.search"
                            :placeholder="__('telegram.queue.filters.search')"
                        />
                    </div>

                        <x-driver-check.select x-model="filters.queue" x-on:change="applyFilters()">
                            <option value="">{{ __('telegram.queue.filters.queue_all') }}</option>
                            <template x-for="queue in queues()" :key="'fq-' + queue.queue">
                                <option :value="queue.queue" x-text="queue.queue" :selected="filters.queue === queue.queue"></option>
                            </template>
                        </x-driver-check.select>

                        <div x-show="!isFailedTab()">
                            <x-driver-check.select x-model="filters.state" x-on:change="applyFilters()">
                                <option value="">{{ __('telegram.queue.filters.state_all') }}</option>
                                @foreach (['ready', 'delayed', 'reserved', 'stuck'] as $state)
                                    <option value="{{ $state }}">{{ __('telegram.queue.state.' . $state) }}</option>
                                @endforeach
                            </x-driver-check.select>
                        </div>

                        <div x-show="isFailedTab()" x-cloak class="flex gap-2">
                            <x-driver-check.button class="flex-1" x-on:click="askConfirm('retry_all')" ::disabled="failedTotal() === 0 || !!busy">
                                {{ __('telegram.queue.actions.retry_all') }}
                            </x-driver-check.button>

                            <x-driver-check.button variant="ghost" class="flex-1" x-on:click="askConfirm('flush')" ::disabled="failedTotal() === 0 || !!busy">
                                {{ __('telegram.queue.actions.flush') }}
                            </x-driver-check.button>
                        </div>
                    </div>
                </div>

            {{-- ============================================================
                 Waiting jobs
            ============================================================= --}}
            <div x-show="!isFailedTab()">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left">
                        <thead class="dc-thead border-b border-gray-200 bg-gray-50/70 dark:border-gray-800 dark:bg-white/[0.02]">
                            <tr class="text-[10px] uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-2.5 font-semibold">#</th>
                                <th class="px-3 py-2.5 font-semibold">{{ __('telegram.queue.table.job') }}</th>
                                <th class="px-3 py-2.5 font-semibold">{{ __('telegram.queue.table.queue') }}</th>
                                <th class="px-3 py-2.5 font-semibold">{{ __('telegram.queue.table.state') }}</th>
                                <th class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.queue.table.attempts') }}</th>
                                <th class="px-4 py-2.5 font-semibold">{{ __('telegram.queue.table.created') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <x-driver-check.skeleton-rows :cols="6" />

                            <template x-for="row in (isFailedTab() ? [] : rows)" :key="'j-' + row.id">
                                <tr class="text-[13px] transition hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                                    <td class="px-4 py-2.5 font-mono text-[12px] text-gray-500 dark:text-gray-400" x-text="row.id"></td>
                                    <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-white" :title="row.job_class" x-text="row.job || dash"></td>
                                    <td class="px-3 py-2.5 font-mono text-[12px] text-gray-600 dark:text-gray-300" x-text="row.queue"></td>
                                    <td class="px-3 py-2.5">
                                        <x-driver-check.badge ::class="stateClass(row)">
                                            <span class="h-1.5 w-1.5 rounded-full" :class="stateDot(row)"></span>
                                            <span x-text="t.state[row.state]"></span>
                                        </x-driver-check.badge>
                                        <span
                                            x-show="row.state === 'delayed'"
                                            class="ml-1 text-[11px] text-gray-500 dark:text-gray-400"
                                            :title="date(row.available_at)"
                                            x-text="relative(row.available_at)"
                                        ></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300" x-text="attempts(row)"></td>
                                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400" :title="date(row.created_at)" x-text="relative(row.created_at)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <x-driver-check.empty-state
                    icon="inbox"
                    :title="__('telegram.queue.empty.pending_title')"
                    :description="__('telegram.queue.empty.pending_description')"
                    show="!isFailedTab() && !loading && rows.length === 0 && !error"
                />

                <x-driver-check.pagination />
            </div>
            {{-- ============================================================
                 Failed jobs
            ============================================================= --}}

            <div x-show="isFailedTab()" x-cloak>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    <template x-for="row in (isFailedTab() ? rows : [])" :key="'f-' + row.uuid">
                        <li class="flex flex-col gap-2 p-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-white" :title="row.job_class" x-text="row.job || dash"></span>
                                    <span class="rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] text-gray-600 dark:bg-white/[0.06] dark:text-gray-300" x-text="row.queue"></span>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400" :title="date(row.failed_at)" x-text="relative(row.failed_at)"></span>
                                </div>

                                <p class="dc-break mt-1 line-clamp-2 font-mono text-[12px] text-error-600 dark:text-error-400" :title="row.error" x-text="row.error"></p>
                            </div>

                            <div class="flex shrink-0 gap-1.5">
                                <x-driver-check.button size="sm" x-on:click="openDetail(row)">
                                    {{ __('telegram.queue.actions.details') }}
                                </x-driver-check.button>

                                <x-driver-check.button size="sm" variant="primary" x-on:click="retry(row)" ::disabled="!!busy">
                                    <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin" x-show="busy === 'retry-' + row.uuid" x-cloak />
                                    {{ __('telegram.queue.actions.retry') }}
                                </x-driver-check.button>

                                <x-driver-check.button size="sm" variant="ghost" x-on:click="askConfirm('delete', { uuid: row.uuid })" ::disabled="!!busy">
                                    {{ __('telegram.queue.actions.delete') }}
                                </x-driver-check.button>
                            </div>
                        </li>
                    </template>
                </ul>

                <x-driver-check.empty-state
                    icon="check-circle"
                    :title="__('telegram.queue.empty.failed_title')"
                    :description="__('telegram.queue.empty.failed_description')"
                    show="isFailedTab() && !loading && rows.length === 0 && !error"
                />

                <x-driver-check.pagination />
            </div>
        </x-driver-check.surface>

        <p class="text-center text-[11px] text-gray-400 dark:text-gray-500" x-text="t.auto_refresh"></p>
    </x-driver-check.shell>

    {{-- ================================================================
         Failed job details
    ================================================================= --}}
    <x-driver-check.modal open="detailOpen" close="closeDetail()" size="sm:max-w-3xl">
        <x-slot:heading>
            <span x-text="detail?.job || t.detail.title"></span>
        </x-slot:heading>

        <template x-if="detail">
            <div class="flex flex-col gap-3">
                <dl class="grid grid-cols-2 gap-x-3 gap-y-2 rounded-xl bg-gray-50 p-3 sm:grid-cols-3 dark:bg-white/[0.02]">
                    <x-driver-check.kv :label="__('telegram.queue.table.queue')">
                        <span class="font-mono" x-text="detail.queue"></span>
                    </x-driver-check.kv>

                    <x-driver-check.kv :label="__('telegram.queue.table.failed_at')">
                        <span x-text="date(detail.failed_at)"></span>
                    </x-driver-check.kv>

                    <x-driver-check.kv :label="__('telegram.queue.detail.uuid')" class="col-span-2 sm:col-span-1">
                        <span class="font-mono text-[11px]" x-text="detail.uuid"></span>
                    </x-driver-check.kv>

                    <x-driver-check.kv :label="__('telegram.queue.table.job')" class="col-span-2 sm:col-span-3">
                        <span class="font-mono text-[12px]" x-text="detail.job_class || dash"></span>
                    </x-driver-check.kv>
                </dl>

                <div>
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ __('telegram.queue.detail.exception') }}
                    </p>

                    <div x-show="detailLoading" class="dc-skeleton h-40 rounded-xl bg-gray-100 dark:bg-white/[0.06]"></div>

                    <pre
                        x-show="!detailLoading"
                        class="max-h-[50vh] overflow-auto whitespace-pre-wrap break-words rounded-xl bg-gray-900 p-3 font-mono text-[11px] leading-relaxed text-gray-100"
                        x-text="detail.exception || detail.error"
                    ></pre>
                </div>
            </div>
        </template>

        <x-slot:footer>
            <x-driver-check.button x-on:click="closeDetail()">
                {{ __('telegram.ui.close') }}
            </x-driver-check.button>

            <x-driver-check.button variant="ghost" x-on:click="askConfirm('delete', { uuid: detail?.uuid })" ::disabled="!!busy">
                {{ __('telegram.queue.actions.delete') }}
            </x-driver-check.button>

            <x-driver-check.button variant="primary" x-on:click="retry(detail)" ::disabled="!!busy || !detail">
                {{ __('telegram.queue.actions.retry') }}
            </x-driver-check.button>
        </x-slot:footer>
    </x-driver-check.modal>

    {{-- ================================================================
         Confirmation
    ================================================================= --}}
    <x-driver-check.modal open="confirmOpen" close="closeConfirm()" size="sm:max-w-md">
        <x-slot:heading>
            <span x-text="confirmTitle()"></span>
        </x-slot:heading>

        <p class="text-[13px] leading-relaxed text-gray-600 dark:text-gray-300" x-text="confirmText()"></p>

        <p
            x-show="confirmError"
            x-cloak
            class="dc-break mt-3 rounded-xl bg-error-50 px-3 py-2.5 text-xs text-error-600 dark:bg-error-500/10 dark:text-error-400"
            x-text="confirmError"
        ></p>

        <x-slot:footer>
            <x-driver-check.button x-on:click="closeConfirm()" ::disabled="!!busy">
                {{ __('telegram.ui.cancel') }}
            </x-driver-check.button>

            <x-driver-check.button variant="danger" x-show="confirmDanger()" x-on:click="runConfirmed()" ::disabled="!!busy">
                <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin" x-show="!!busy" x-cloak />
                {{ __('telegram.queue.confirm.confirm') }}
            </x-driver-check.button>

            <x-driver-check.button variant="primary" x-show="!confirmDanger()" x-on:click="runConfirmed()" ::disabled="!!busy">
                <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin" x-show="!!busy" x-cloak />
                {{ __('telegram.queue.confirm.confirm') }}
            </x-driver-check.button>
        </x-slot:footer>
    </x-driver-check.modal>
</div>

@endsection
