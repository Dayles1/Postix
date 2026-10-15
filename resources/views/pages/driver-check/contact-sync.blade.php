@extends('layouts.driver-check')

@section('title', __('driver_check.menu.groups'))

@section('content')

<div
    x-data="dcContactSync({
        endpoints: {
            show: @js(route('api.telegram.contact-sync.show')),
            groups: @js(url('/api/telegram/contact-sync/groups')),
            accounts: @js(url('/api/telegram/contact-sync/accounts')),
        },
        translations: @js(__('telegram.contact_sync')),
        ui: @js(__('telegram.ui')),
    })"
>
    <x-driver-check.shell>

        {{-- Header: refresh, then the way to the history (where the Run button is) --}}
        <x-driver-check.page-header
            :title="__('driver_check.menu.groups')"
            :description="__('telegram.contact_sync.description')"
        >
            <x-slot:actions>
                <x-driver-check.button
                    size="icon"
                    x-on:click="load()"
                    ::disabled="loading"
                    :title="__('telegram.ui.refresh')"
                    :aria-label="__('telegram.ui.refresh')"
                >
                    <x-driver-check.icon name="refresh" class="h-4 w-4" ::class="loading && 'animate-spin'" />
                </x-driver-check.button>

                <x-driver-check.button href="{{ route('driver-check.contact-report') }}" variant="primary" icon="chart">
                    {{ __('driver_check.sync.history') }}
                </x-driver-check.button>
            </x-slot:actions>
        </x-driver-check.page-header>

        <x-driver-check.error-alert :title="__('telegram.contact_sync.errors.title')" />

        <div x-show="!status && loading" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <template x-for="i in 4" :key="'sk-' + i">
                <div class="dc-skeleton h-24 rounded-2xl bg-gray-100 dark:bg-white/[0.06]"></div>
            </template>
        </div>

        <template x-if="status">
            <div class="flex flex-col gap-5">

                {{-- Totals --}}
                <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @foreach (['users', 'added', 'failed', 'unassigned'] as $key)
                        <x-driver-check.stat-card :label="__('telegram.contact_sync.totals.' . $key)">
                            <span x-text="number(status.totals.{{ $key }})"></span>
                        </x-driver-check.stat-card>
                    @endforeach
                </section>

                {{-- Groups --}}
                <x-driver-check.surface class="overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('telegram.contact_sync.groups.title') }}</h2>
                        {{-- Shows while a run is in progress --}}
                        <span x-show="status.running" x-cloak class="dc-dot animate-pulse text-blue-light-500"></span>
                    </div>

                    <p x-show="status.groups.length === 0" x-cloak class="px-4 py-8 text-center text-[13px] text-gray-500 dark:text-gray-400">
                        {{ __('telegram.contact_sync.groups.empty') }}
                    </p>

                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template x-for="group in status.groups" :key="group.id">
                            <li class="dc-row flex flex-col gap-2 px-4 py-3 hover:bg-gray-25 sm:flex-row sm:items-center sm:gap-3 dark:hover:bg-white/[0.025]">
                                <div class="flex min-w-0 flex-1 items-start gap-3">
                                    <span class="dc-dot mt-[7px] shrink-0" :class="group.is_active ? 'text-success-500' : 'text-gray-400'"></span>

                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white" x-text="groupName(group)"></p>
                                        <p class="text-[12px] text-gray-500 dark:text-gray-400">
                                            <span class="tabular-nums" x-text="number(group.users_count) + ' ' + t.groups.people"></span>
                                            ·
                                            <span x-text="group.last_synced_at ? relative(group.last_synced_at) : ui.never"></span>
                                        </p>
                                        <p x-show="group.last_error" x-cloak class="dc-break mt-0.5 text-[12px] text-error-600 dark:text-error-400" x-text="group.last_error"></p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 items-center gap-1.5 pl-[19px] sm:pl-0">
                                    <x-driver-check.button size="sm" x-on:click="toggleGroup(group)" ::disabled="!!busy">
                                        <span x-text="group.is_active ? t.groups.pause : t.groups.resume"></span>
                                    </x-driver-check.button>
                                    <x-driver-check.row-action
                                        icon="trash"
                                        :label="__('telegram.contact_sync.groups.delete')"
                                        class="h-9 w-9 sm:h-8 sm:w-8 hover:!text-error-600"
                                        x-on:click="removeGroup(group)"
                                        ::disabled="!!busy"
                                    />
                                </div>
                            </li>
                        </template>
                    </ul>

                    {{-- Add a group: the footer row of the panel --}}
                    <form class="flex gap-2 border-t border-gray-200 bg-gray-25 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.015]" x-on:submit.prevent="addGroup()">
                        <x-driver-check.input x-model="newLink" placeholder="https://t.me/group" />
                        <x-driver-check.button type="submit" variant="primary" ::disabled="!!busy || newLink.trim() === ''">
                            {{ __('telegram.contact_sync.groups.add') }}
                        </x-driver-check.button>
                    </form>
                </x-driver-check.surface>

                {{-- Accounts --}}
                <x-driver-check.surface class="overflow-hidden">
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('telegram.contact_sync.accounts.title') }}</h2>
                        <p class="mt-0.5 text-[12px] text-gray-500 dark:text-gray-400">
                            {{ __('telegram.contact_sync.accounts.hint') }}
                            <span class="tabular-nums" x-text="status.daily_limit"></span>
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-[13px]">
                            <thead class="dc-thead border-b border-gray-200 bg-gray-25 text-[10.5px] uppercase tracking-[0.08em] text-gray-500 dark:border-gray-800 dark:bg-white/[0.015] dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-2.5 font-semibold">{{ __('telegram.contact_sync.accounts.account') }}</th>
                                    <th class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.contact_sync.accounts.contacts') }}</th>
                                    <th class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.contact_sync.accounts.today') }}</th>
                                    <th class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.contact_sync.accounts.failed') }}</th>
                                    <th class="px-3 py-2.5 text-right font-semibold">{{ __('telegram.contact_sync.accounts.skipped') }}</th>
                                    <th class="px-4 py-2.5 font-semibold">{{ __('telegram.contact_sync.accounts.last_added') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template x-for="account in status.accounts" :key="account.id">
                                    <tr class="dc-row hover:bg-gray-25 dark:hover:bg-white/[0.025]">
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-2.5">
                                                <span class="dc-dot shrink-0" :class="account.held ? 'text-error-500' : 'text-success-500'"></span>
                                                <div class="min-w-0">
                                                    <span class="font-medium tabular-nums text-gray-900 dark:text-white" x-text="phone(account.phone)"></span>
                                                    <span x-show="account.is_primary" x-cloak class="ml-1 text-[11px] text-gray-500" x-text="'· ' + t.accounts.primary"></span>
                                                    <div x-show="account.held" x-cloak class="mt-0.5 flex items-center gap-2 text-[11px] text-error-600 dark:text-error-400">
                                                        <span class="dc-break" x-text="t.accounts.held + ': ' + (account.held_reason || dash)"></span>
                                                        <button type="button" class="font-medium underline" x-on:click="enableAccount(account)" :disabled="!!busy" x-text="t.accounts.enable"></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums text-gray-900 dark:text-white" x-text="number(account.added)"></td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300" x-text="number(account.today) + ' / ' + status.daily_limit"></td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300" :class="account.failed > 0 ? '!text-error-600 dark:!text-error-400' : ''" x-text="number(account.failed)"></td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-300" x-text="number(account.skipped)"></td>
                                        <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400" x-text="account.last_added_at ? relative(account.last_added_at) : ui.never"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </x-driver-check.surface>
            </div>
        </template>
    </x-driver-check.shell>
</div>

@endsection
