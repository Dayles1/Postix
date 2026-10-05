@extends('layouts.app')

@section('title', __('telegram.watchdog.title'))

@section('content')

<div
    x-data="dcWatchdog({
        endpoints: {
            show: @js(route('api.telegram.watchdog.show')),
            start: @js(route('api.telegram.watchdog.start')),
            restart: @js(route('api.telegram.watchdog.restart-listener')),
            stop: @js(route('api.telegram.watchdog.stop')),
        },
        sessionsUrl: @js(route('driver-check.sessions')),
        queueUrl: @js(route('driver-check.queue')),
        translations: @js(__('telegram.watchdog')),
        ui: @js(__('telegram.ui')),
    })"
>
    <x-driver-check.shell>

        {{-- ============================================================
             Header
        ============================================================= --}}
        <x-driver-check.page-header
            icon="shield"
            tone="brand"
            :eyebrow="__('telegram.menu.groups.monitoring')"
            :title="__('telegram.watchdog.title')"
            :description="__('telegram.watchdog.description')"
        >
            <x-slot:actions>
                {{-- Control: one joined group, the colour only on the icons --}}
                <div
                    class="col-span-2 inline-flex overflow-hidden rounded-xl border border-gray-300 bg-white shadow-xs
                           divide-x divide-gray-200 dark:divide-gray-700 dark:border-gray-700 dark:bg-white/[0.03]"
                    role="group"
                >
                    <button
                        type="button"
                        x-on:click="start()"
                        :disabled="!!busy"
                        class="dc-tap inline-flex h-11 flex-1 items-center justify-center gap-2 px-3.5 text-sm font-medium text-gray-700
                               transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 sm:h-10
                               dark:text-gray-200 dark:hover:bg-white/[0.06]"
                    >
                        <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin text-success-600" x-show="busy === 'start'" x-cloak />
                        <x-driver-check.icon name="play" class="h-4 w-4 text-success-600 dark:text-success-400" x-show="busy !== 'start'" />
                        <span class="whitespace-nowrap">{{ __('telegram.watchdog.actions.start') }}</span>
                    </button>

                    <button
                        type="button"
                        x-on:click="askConfirm('restart')"
                        :disabled="!!busy || !canRestart()"
                        class="dc-tap inline-flex h-11 flex-1 items-center justify-center gap-2 px-3.5 text-sm font-medium text-gray-700
                               transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 sm:h-10
                               dark:text-gray-200 dark:hover:bg-white/[0.06]"
                    >
                        <x-driver-check.icon name="refresh" class="h-4 w-4 text-brand-500 dark:text-brand-400" />
                        <span class="hidden whitespace-nowrap sm:inline" title="{{ __('telegram.watchdog.actions.restart') }}">{{ __('telegram.watchdog.actions.restart_short') }}</span>
                    </button>

                    <button
                        type="button"
                        x-on:click="askConfirm('stop')"
                        :disabled="!!busy || !canStop()"
                        class="dc-tap inline-flex h-11 flex-1 items-center justify-center gap-2 px-3.5 text-sm font-medium text-gray-700
                               transition hover:bg-error-50 disabled:cursor-not-allowed disabled:opacity-50 sm:h-10
                               dark:text-gray-200 dark:hover:bg-error-500/10"
                    >
                        <x-driver-check.icon name="stop" class="h-4 w-4 text-error-500" />
                        <span class="whitespace-nowrap">{{ __('telegram.watchdog.actions.stop') }}</span>
                    </button>
                </div>

                <x-driver-check.button
                    size="icon"
                    class="hidden sm:inline-flex"
                    x-on:click="load()"
                    ::disabled="loading"
                    :title="__('telegram.ui.refresh')"
                    :aria-label="__('telegram.ui.refresh')"
                >
                    <x-driver-check.icon name="refresh" class="h-4 w-4" ::class="loading && 'animate-spin'" />
                </x-driver-check.button>
            </x-slot:actions>
        </x-driver-check.page-header>

        <x-driver-check.error-alert :title="__('telegram.watchdog.errors.title')" />

        {{-- First load --}}
        <div x-show="!status && loading" class="grid gap-3 sm:grid-cols-3">
            <template x-for="i in 3" :key="'sk-' + i">
                <div class="dc-skeleton h-28 rounded-2xl bg-gray-100 dark:bg-white/[0.06]"></div>
            </template>
        </div>

        <template x-if="status">
            <div class="flex flex-col gap-4 sm:gap-5">

                {{-- ====================================================
                     Overall state
                ===================================================== --}}
                <section class="flex items-start gap-3 rounded-2xl border p-4 sm:p-5" :class="bannerClass()">
                    <span class="mt-0.5 shrink-0">
                        <x-driver-check.icon name="check-circle" class="h-6 w-6" x-show="stateTone() === 'success'" />
                        <x-driver-check.icon name="alert" class="h-6 w-6" x-show="stateTone() === 'waiting' || stateTone() === 'muted'" x-cloak />
                        <x-driver-check.icon name="x-circle" class="h-6 w-6" x-show="stateTone() === 'error'" x-cloak />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-base font-semibold" x-text="t.state[state()]"></p>
                        <p class="mt-0.5 text-[13px] leading-relaxed opacity-90" x-text="t.state_hint[state()]"></p>
                    </div>

                    <p class="hidden shrink-0 text-[11px] opacity-70 sm:block" x-text="t.auto_refresh"></p>
                </section>

                <p
                    x-show="!status.can_signal"
                    x-cloak
                    class="flex items-start gap-2 rounded-xl bg-gray-50 px-3 py-2.5 text-[12px] text-gray-600 dark:bg-white/[0.03] dark:text-gray-400"
                >
                    <x-driver-check.icon name="alert" class="mt-px h-4 w-4 shrink-0" />
                    <span x-text="t.signals_unavailable"></span>
                </p>

                {{-- ====================================================
                     Processes
                ===================================================== --}}
                <section class="grid gap-3 sm:grid-cols-3">
                    @foreach (['watchdog', 'spare', 'listener'] as $role)
                        <x-driver-check.surface class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ __('telegram.watchdog.processes.' . $role) }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] leading-snug text-gray-500 dark:text-gray-400">
                                        {{ __('telegram.watchdog.processes.' . $role . '_hint') }}
                                    </p>
                                </div>

                                <x-driver-check.badge ::class="processBadge('{{ $role }}')">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="processDot('{{ $role }}')"></span>
                                    <span x-text="processLabel('{{ $role }}')"></span>
                                </x-driver-check.badge>
                            </div>

                            <p class="mt-3 text-[12px] tabular-nums text-gray-500 dark:text-gray-400">
                                {{ __('telegram.watchdog.processes.pid') }}:
                                <span class="font-mono text-gray-800 dark:text-gray-200" x-text="process('{{ $role }}').pid ?? dash"></span>
                            </p>
                        </x-driver-check.surface>
                    @endforeach
                </section>

                <section class="grid gap-3 lg:grid-cols-3">

                    {{-- ================================================
                         Main account
                    ================================================= --}}
                    <x-driver-check.surface class="p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('telegram.watchdog.account.title') }}</h2>

                            <a :href="sessionsUrl" class="text-[12px] font-medium text-brand-600 hover:underline dark:text-brand-400">
                                {{ __('telegram.watchdog.account.open_sessions') }} →
                            </a>
                        </div>

                        <template x-if="account()">
                            <div class="mt-3 flex flex-col gap-2.5">
                                <div class="flex items-center gap-3">
                                    <x-driver-check.avatar
                                        size="sm"
                                        ::class="account().is_authorized
                                            ? 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'
                                            : 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400'"
                                    >
                                        <x-driver-check.icon name="telegram" class="h-4 w-4" />
                                    </x-driver-check.avatar>

                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white" x-text="accountName()"></p>
                                        <p class="truncate text-[11px] tabular-nums text-gray-500 dark:text-gray-400">
                                            <span x-text="phone(account().phone)"></span>
                                            <template x-if="account().username">
                                                <span x-text="' · ' + handle(account().username)"></span>
                                            </template>
                                        </p>
                                    </div>
                                </div>

                                <dl class="grid grid-cols-2 gap-x-3 gap-y-2 rounded-xl bg-gray-50 p-3 dark:bg-white/[0.02]">
                                    <x-driver-check.kv :label="__('telegram.watchdog.account.status')">
                                        <span class="font-mono text-[12px]" x-text="account().status || dash"></span>
                                    </x-driver-check.kv>

                                    <x-driver-check.kv label="ID">
                                        <span class="tabular-nums" x-text="account().id"></span>
                                    </x-driver-check.kv>

                                    <div class="col-span-2 text-[13px]">
                                        <span
                                            :class="account().is_authorized ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400'"
                                            x-text="account().is_authorized ? t.account.authorized : t.account.not_authorized"
                                        ></span>
                                        <span
                                            x-show="!account().session_exists"
                                            x-cloak
                                            class="block text-[11px] text-error-600 dark:text-error-400"
                                            x-text="t.account.session_missing"
                                        ></span>
                                    </div>
                                </dl>
                            </div>
                        </template>

                        <template x-if="!account()">
                            <div class="mt-3 rounded-xl bg-error-50 px-3 py-2.5 text-[12px] text-error-700 dark:bg-error-500/10 dark:text-error-400">
                                <p class="font-semibold" x-text="status.account_configured ? t.account.not_found : t.account.missing"></p>
                                <p x-show="!status.account_configured" class="mt-0.5" x-text="t.account.missing_hint"></p>
                            </div>
                        </template>
                    </x-driver-check.surface>

                    {{-- ================================================
                         Activity
                    ================================================= --}}
                    <x-driver-check.surface class="p-4">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('telegram.watchdog.activity.title') }}</h2>

                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-3">
                            <x-driver-check.kv :label="__('telegram.watchdog.activity.last_check')">
                                <span
                                    :title="status.activity.last_check_at ? date(status.activity.last_check_at) : ''"
                                    x-text="status.activity.last_check_at ? relative(status.activity.last_check_at) : ui.never"
                                ></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.watchdog.activity.last_report')">
                                <span
                                    :title="status.activity.last_report_at ? date(status.activity.last_report_at) : ''"
                                    x-text="status.activity.last_report_at ? relative(status.activity.last_report_at) : ui.never"
                                ></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.watchdog.activity.checks_today')">
                                <span class="text-lg font-semibold tabular-nums" x-text="number(status.activity.checks_today)"></span>
                            </x-driver-check.kv>

                            <x-driver-check.kv :label="__('telegram.watchdog.activity.pending')">
                                <span class="text-lg font-semibold tabular-nums" x-text="number(status.activity.pending)"></span>
                            </x-driver-check.kv>
                        </dl>
                    </x-driver-check.surface>

                    {{-- ================================================
                         Queue worker (the start button goes through it)
                    ================================================= --}}
                    <x-driver-check.surface class="p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('telegram.watchdog.worker.title') }}</h2>

                            <a :href="queueUrl" class="text-[12px] font-medium text-brand-600 hover:underline dark:text-brand-400">
                                {{ __('telegram.watchdog.worker.open_queue') }} →
                            </a>
                        </div>

                        <div class="mt-3">
                            <x-driver-check.badge
                                ::class="status.queue_worker_alive
                                    ? 'bg-success-50 text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:text-success-400'
                                    : 'bg-error-50 text-error-700 ring-1 ring-inset ring-error-600/20 dark:bg-error-500/10 dark:text-error-400'"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="status.queue_worker_alive ? 'bg-success-500' : 'bg-error-500'"></span>
                                <span x-text="status.queue_worker_alive ? t.worker.alive : t.worker.dead"></span>
                            </x-driver-check.badge>
                        </div>

                        <p
                            x-show="!status.queue_worker_alive"
                            x-cloak
                            class="mt-2.5 text-[12px] leading-relaxed text-gray-600 dark:text-gray-400"
                            x-text="t.worker.dead_hint"
                        ></p>

                        <p
                            x-show="status.start_queued > 0"
                            x-cloak
                            class="mt-2.5 rounded-lg bg-warning-50 px-2.5 py-1.5 text-[12px] text-warning-800 dark:bg-warning-500/10 dark:text-warning-300"
                            x-text="t.worker.start_queued.replace(':count', status.start_queued)"
                        ></p>
                    </x-driver-check.surface>
                </section>

                {{-- ====================================================
                     Why it went down last
                ===================================================== --}}
                <template x-if="restartNotice() || healthMarker()">
                    <x-driver-check.surface class="p-4">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('telegram.watchdog.restart.title') }}</h2>

                        <template x-if="restartNotice()">
                            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-3 sm:grid-cols-5">
                                <x-driver-check.kv :label="__('telegram.watchdog.restart.reason')">
                                    <span class="font-mono text-[12px]" x-text="restartNotice().reason ?? dash"></span>
                                </x-driver-check.kv>

                                <x-driver-check.kv :label="__('telegram.watchdog.restart.exit_code')">
                                    <span class="tabular-nums" x-text="restartNotice().exit_code ?? dash"></span>
                                </x-driver-check.kv>

                                <x-driver-check.kv :label="__('telegram.watchdog.restart.uptime')">
                                    <span x-text="duration(restartNotice().uptime_seconds)"></span>
                                </x-driver-check.kv>

                                <x-driver-check.kv :label="__('telegram.watchdog.restart.restarts')">
                                    <span class="tabular-nums" x-text="restartNotice().restart_count ?? dash"></span>
                                </x-driver-check.kv>

                                <x-driver-check.kv :label="__('telegram.watchdog.restart.at')">
                                    <span x-text="restartNotice().recorded_at ? relative(restartNotice().recorded_at) : dash"></span>
                                </x-driver-check.kv>

                                <x-driver-check.kv :label="__('telegram.watchdog.restart.detail')" class="col-span-2 sm:col-span-5">
                                    <span class="dc-break font-mono text-[12px]" x-text="restartNotice().detail || dash"></span>
                                </x-driver-check.kv>
                            </dl>
                        </template>

                        <p
                            x-show="healthMarker()"
                            x-cloak
                            class="mt-3 rounded-lg bg-warning-50 px-3 py-2 text-[12px] text-warning-800 dark:bg-warning-500/10 dark:text-warning-300"
                        >
                            <span class="font-semibold" x-text="t.restart.marker + ':'"></span>
                            <span class="font-mono" x-text="healthMarker()?.reason"></span>
                            <span x-text="healthMarker()?.marked_at ? '· ' + relative(healthMarker().marked_at) : ''"></span>
                        </p>
                    </x-driver-check.surface>
                </template>
            </div>
        </template>
    </x-driver-check.shell>

    {{-- ================================================================
         Confirmation: restart / stop
    ================================================================= --}}
    <x-driver-check.modal open="confirmOpen" close="closeConfirm()" size="sm:max-w-md">
        <x-slot:heading>
            <span x-text="confirmAction === 'stop' ? t.confirm.stop_title : t.confirm.restart_title"></span>
        </x-slot:heading>

        <p
            class="text-[13px] leading-relaxed text-gray-600 dark:text-gray-300"
            x-text="confirmAction === 'stop' ? t.confirm.stop_text : t.confirm.restart_text"
        ></p>

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

            <x-driver-check.button variant="danger" x-on:click="confirm()" ::disabled="!!busy">
                <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin" x-show="!!busy" x-cloak />
                {{ __('telegram.watchdog.confirm.confirm') }}
            </x-driver-check.button>
        </x-slot:footer>
    </x-driver-check.modal>
</div>

@endsection
