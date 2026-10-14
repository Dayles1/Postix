@extends('layouts.app')

@section('title', __('telegram.contact_sync.title'))

@section('content')

<div
    x-data="dcContactSync({
        endpoints: {
            show: @js(route('api.telegram.contact-sync.show')),
            run: @js(route('api.telegram.contact-sync.run')),
            logs: @js(route('api.telegram.contact-sync.logs')),
            groups: @js(url('/api/telegram/contact-sync/groups')),
            accounts: @js(url('/api/telegram/contact-sync/accounts')),
        },
        translations: @js(__('telegram.contact_sync')),
        ui: @js(__('telegram.ui')),
    })"
>
    <x-driver-check.shell>

        <x-driver-check.page-header
            icon="send"
            tone="brand"
            :eyebrow="__('telegram.menu.groups.monitoring')"
            :title="__('telegram.contact_sync.title')"
            :description="__('telegram.contact_sync.description')"
        >
            <x-slot:actions>
                <x-driver-check.button
                    variant="primary"
                    x-on:click="run()"
                    ::disabled="!!busy || status?.running"
                >
                    <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin" x-show="busy === 'run' || status?.running" x-cloak />
                    <x-driver-check.icon name="play" class="h-4 w-4" x-show="busy !== 'run' && !status?.running" />
                    <span x-text="status?.running ? t.actions.running : t.actions.run"></span>
                </x-driver-check.button>

                <x-driver-check.button
                    size="icon"
                    class="hidden sm:inline-flex"
                    x-on:click="load(); loadLogs()"
                    ::disabled="loading"
                    :title="__('telegram.ui.refresh')"
                    :aria-label="__('telegram.ui.refresh')"
                >
                    <x-driver-check.icon name="refresh" class="h-4 w-4" ::class="loading && 'animate-spin'" />
                </x-driver-check.button>
            </x-slot:actions>
        </x-driver-check.page-header>

        <x-driver-check.error-alert :title="__('telegram.contact_sync.errors.title')" />

        <div x-show="!status && loading" class="grid gap-3 sm:grid-cols-4">
            <template x-for="i in 4" :key="'sk-' + i">
                <div class="dc-skeleton h-24 rounded-2xl bg-gray-100 dark:bg-white/[0.06]"></div>
            </template>
        </div>

        <template x-if="status">
            <div class="flex flex-col gap-4 sm:gap-5">

                {{-- Totals --}}
                <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach (['users', 'added', 'failed', 'unassigned'] as $key)
                        <x-driver-check.surface class="p-4">
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ __('telegram.contact_sync.totals.' . $key) }}</p>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900 dark:text-white" x-text="number(status.totals.{{ $key }})"></p>
                        </x-driver-check.surface>
                    @endforeach
                </section>

                {{-- Groups --}}
                <x-driver-check.surface class="p-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('telegram.contact_sync.groups.title') }}</h2>

                    <form class="mt-3 flex gap-2" x-on:submit.prevent="addGroup()">
                        <x-driver-check.input x-model="newLink" placeholder="https://t.me/group" />
                        <x-driver-check.button type="submit" variant="primary" ::disabled="!!busy || newLink.trim() === ''">
                            {{ __('telegram.contact_sync.groups.add') }}
                        </x-driver-check.button>
                    </form>

                    <p x-show="status.groups.length === 0" x-cloak class="mt-3 text-[13px] text-gray-500 dark:text-gray-400">
                        {{ __('telegram.contact_sync.groups.empty') }}
                    </p>

                    <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-800">
                        <template x-for="group in status.groups" :key="group.id">
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white" x-text="groupName(group)"></p>
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                        <span x-text="number(group.users_count) + ' ' + t.groups.people"></span>
                                        ·
                                        <span x-text="group.last_synced_at ? relative(group.last_synced_at) : ui.never"></span>
                                    </p>
                                    <p x-show="group.last_error" x-cloak class="dc-break text-[11px] text-error-600 dark:text-error-400" x-text="group.last_error"></p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <x-driver-check.button size="sm" x-on:click="run(group.id)" ::disabled="!!busy || status.running">
                                        {{ __('telegram.contact_sync.groups.run') }}
                                    </x-driver-check.button>
                                    <x-driver-check.button size="sm" x-on:click="toggleGroup(group)" ::disabled="!!busy">
                                        <span x-text="group.is_active ? t.groups.pause : t.groups.resume"></span>
                                    </x-driver-check.button>
                                    <x-driver-check.button size="sm" variant="danger" x-on:click="removeGroup(group)" ::disabled="!!busy">
                                        {{ __('telegram.contact_sync.groups.delete') }}
                                    </x-driver-check.button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </x-driver-check.surface>

                {{-- Accounts --}}
                <x-driver-check.surface class="overflow-hidden">
                    <div class="p-4 pb-2">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('telegram.contact_sync.accounts.title') }}</h2>
                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                            {{ __('telegram.contact_sync.accounts.hint') }}
                            <span class="tabular-nums" x-text="status.daily_limit"></span>
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-[13px]">
                            <thead class="text-[11px] uppercase text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-2 font-medium">{{ __('telegram.contact_sync.accounts.account') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('telegram.contact_sync.accounts.contacts') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('telegram.contact_sync.accounts.today') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('telegram.contact_sync.accounts.failed') }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ __('telegram.contact_sync.accounts.skipped') }}</th>
                                    <th class="px-4 py-2 font-medium">{{ __('telegram.contact_sync.accounts.last_added') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template x-for="account in status.accounts" :key="account.id">
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <span class="tabular-nums" x-text="phone(account.phone)"></span>
                                            <span x-show="account.is_primary" x-cloak class="ml-1 text-[11px] text-gray-500" x-text="'· ' + t.accounts.primary"></span>
                                            <div x-show="account.held" x-cloak class="mt-0.5 flex items-center gap-2 text-[11px] text-error-600 dark:text-error-400">
                                                <span class="dc-break" x-text="t.accounts.held + ': ' + (account.held_reason || dash)"></span>
                                                <button type="button" class="font-medium underline" x-on:click="enableAccount(account)" :disabled="!!busy" x-text="t.accounts.enable"></button>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums" x-text="number(account.added)"></td>
                                        <td class="px-3 py-2.5 text-right tabular-nums" x-text="number(account.today) + ' / ' + status.daily_limit"></td>
                                        <td class="px-3 py-2.5 text-right tabular-nums" :class="account.failed > 0 ? 'text-error-600 dark:text-error-400' : ''" x-text="number(account.failed)"></td>
                                        <td class="px-3 py-2.5 text-right tabular-nums" x-text="number(account.skipped)"></td>
                                        <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400" x-text="account.last_added_at ? relative(account.last_added_at) : ui.never"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </x-driver-check.surface>

                {{-- Log --}}
                <x-driver-check.surface class="overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 p-4 pb-2">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('telegram.contact_sync.log.title') }}</h2>

                        <div class="flex gap-2">
                            <select x-model="filters.event" x-on:change="loadLogs(1)" class="dc-input h-9 rounded-xl border border-gray-300 bg-white px-2.5 text-[13px] dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="">{{ __('telegram.contact_sync.log.all_events') }}</option>
                                <template x-for="event in events" :key="event">
                                    <option :value="event" x-text="eventLabel(event)"></option>
                                </template>
                            </select>

                            <select x-model="filters.account_id" x-on:change="loadLogs(1)" class="dc-input h-9 rounded-xl border border-gray-300 bg-white px-2.5 text-[13px] dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="">{{ __('telegram.contact_sync.log.all_accounts') }}</option>
                                <template x-for="account in status.accounts" :key="account.id">
                                    <option :value="account.id" x-text="phone(account.phone)"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <p x-show="logs.length === 0" x-cloak class="px-4 pb-4 text-[13px] text-gray-500 dark:text-gray-400">
                        {{ __('telegram.contact_sync.log.empty') }}
                    </p>

                    <div class="overflow-x-auto" x-show="logs.length > 0">
                        <table class="w-full text-left text-[13px]">
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template x-for="row in logs" :key="row.id">
                                    <tr>
                                        <td class="whitespace-nowrap px-4 py-2 text-[12px] tabular-nums text-gray-500 dark:text-gray-400" :title="date(row.created_at)" x-text="relative(row.created_at)"></td>
                                        <td class="px-3 py-2">
                                            <x-driver-check.badge ::class="eventClass(row.event)">
                                                <span x-text="eventLabel(row.event)"></span>
                                            </x-driver-check.badge>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 tabular-nums" x-text="row.account ? phone(row.account.phone) : dash"></td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-300" x-text="row.group ? groupName(row.group) : dash"></td>
                                        <td class="dc-break px-4 py-2 text-gray-800 dark:text-gray-200">
                                            <span x-text="row.message"></span>
                                            <span class="text-gray-400" x-text="logContext(row)"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between gap-2 border-t border-gray-100 px-4 py-2.5 text-[12px] text-gray-500 dark:border-gray-800 dark:text-gray-400" x-show="logMeta.last_page > 1" x-cloak>
                        <span x-text="number(logMeta.total) + ' · ' + logMeta.current_page + ' / ' + logMeta.last_page"></span>
                        <div class="flex gap-2">
                            <x-driver-check.button size="sm" x-on:click="loadLogs(logMeta.current_page - 1)" ::disabled="logMeta.current_page <= 1">←</x-driver-check.button>
                            <x-driver-check.button size="sm" x-on:click="loadLogs(logMeta.current_page + 1)" ::disabled="logMeta.current_page >= logMeta.last_page">→</x-driver-check.button>
                        </div>
                    </div>
                </x-driver-check.surface>
            </div>
        </template>
    </x-driver-check.shell>
</div>

@endsection
