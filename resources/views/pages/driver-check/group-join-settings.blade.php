@extends('layouts.driver-check')

@section('title', __('driver_check.menu.group_join_settings'))

@section('content')

@php
    /* One number field: its label and hint come from driver_check.join.settings.fields.<key>. */
    $numberField = fn (string $key) => [
        'key' => $key,
        'label' => __('driver_check.join.settings.fields.' . $key . '.label'),
        'hint' => __('driver_check.join.settings.fields.' . $key . '.hint'),
    ];

    $sections = [
        'quality' => ['min_members', 'min_messages_week', 'max_silence_hours'],
        'pace' => ['joins_per_day', 'join_delay_min', 'join_delay_max', 'accounts_per_group', 'max_groups_per_account'],
        'flood' => ['flood_extra_min', 'flood_extra_max'],
        'search' => ['search_every_hours', 'recheck_days'],
        'members' => ['max_members_read', 'members_every_days'],
    ];
@endphp

<div
    x-data="dcGroupJoinSettings({
        endpoint: @js(route('api.telegram.group-join.settings')),
        translations: @js(__('driver_check.join.settings')),
    })"
>
    <x-driver-check.shell>

        <x-driver-check.page-header
            :title="__('driver_check.menu.group_join_settings')"
            :description="__('driver_check.join.settings.text')"
        >
            <x-slot:actions>
                <x-driver-check.button href="{{ route('driver-check.group-join') }}" variant="primary" icon="users">
                    {{ __('driver_check.menu.group_join') }}
                </x-driver-check.button>
            </x-slot:actions>
        </x-driver-check.page-header>

        <x-driver-check.error-alert :title="__('driver_check.join.settings.load_failed')" retry="load()" />

        <div x-show="loading && !form" class="dc-skeleton h-72 rounded-2xl bg-gray-100 dark:bg-white/[0.06]"></div>

        <template x-if="form">
            <form class="max-w-5xl" x-on:submit.prevent="save()">
                <x-driver-check.surface class="overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('driver_check.join.settings.title') }}</h2>

                        <span
                            class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600 dark:bg-white/[0.07] dark:text-gray-300"
                            x-text="savedInPanel ? s.source_saved : s.source_default"
                        ></span>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-800">

                        {{-- On / off --}}
                        <section class="p-4">
                            <label class="flex cursor-pointer items-start gap-3">
                                <span class="relative mt-0.5 inline-flex h-6 w-10 shrink-0 items-center">
                                    <input type="checkbox" x-model="form.enabled" class="peer sr-only">
                                    <span class="absolute inset-0 rounded-full bg-gray-300 transition peer-checked:bg-brand-500 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/50 dark:bg-gray-700"></span>
                                    <span class="absolute left-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-4"></span>
                                </span>
                                <span>
                                    <span class="block text-[13px] font-semibold text-gray-900 dark:text-white">{{ __('driver_check.join.settings.enabled') }}</span>
                                    <span class="block text-[11.5px] leading-snug text-gray-400">{{ __('driver_check.join.settings.enabled_hint') }}</span>
                                </span>
                            </label>
                        </section>

                        {{-- Which accounts --}}
                        <section class="p-4">
                            <div class="mb-2 flex items-baseline justify-between gap-3">
                                <div>
                                    <h3 class="text-[13px] font-semibold text-gray-900 dark:text-white">{{ __('driver_check.join.settings.accounts') }}</h3>
                                    <p class="mt-0.5 text-[11.5px] leading-snug text-gray-400">{{ __('driver_check.join.settings.accounts_hint') }}</p>
                                </div>
                                <span class="shrink-0 text-[12px] tabular-nums text-gray-500" x-text="s.marked.replace(':n', markedCount())"></span>
                            </div>

                            <ul class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                <template x-for="account in accounts" :key="account.id">
                                    <li>
                                        <label
                                            class="flex items-center gap-3 rounded-lg border px-3 py-2.5 transition"
                                            :class="[
                                                isMarked(account) ? 'border-gray-900 bg-white dark:border-white dark:bg-white/[0.04]' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-transparent',
                                                account.is_primary ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:border-gray-400 dark:hover:border-gray-600',
                                            ]"
                                        >
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 shrink-0 rounded border-gray-300 text-gray-900 focus:ring-brand-500"
                                                :checked="isMarked(account)"
                                                :disabled="account.is_primary"
                                                x-on:change="toggleMarked(account)"
                                            >
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-[13px] font-medium tabular-nums text-gray-900 dark:text-white" x-text="account.phone || ('#' + account.id)"></span>
                                                <span class="block truncate text-[11.5px] text-gray-400" x-text="account.is_primary ? s.listener : (account.username ? '@' + account.username : '')"></span>
                                            </span>
                                            <span x-show="isMarked(account)" x-cloak class="shrink-0 rounded bg-gray-900 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white dark:bg-white dark:text-gray-900">{{ __('driver_check.join.settings.marked_tag') }}</span>
                                        </label>
                                    </li>
                                </template>
                            </ul>
                        </section>

                        @foreach ($sections as $section => $keys)
                            <section class="p-4">
                                <h3 class="mb-3 text-[13px] font-semibold text-gray-900 dark:text-white">{{ __('driver_check.join.settings.sections.' . $section) }}</h3>

                                @if ($section === 'members')
                                    <label class="mb-4 flex cursor-pointer items-start gap-3">
                                        <input type="checkbox" x-model="form.read_members" class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-gray-900 focus:ring-brand-500">
                                        <span>
                                            <span class="block text-[13px] font-medium text-gray-900 dark:text-white">{{ __('driver_check.join.settings.read_members') }}</span>
                                            <span class="block text-[11.5px] leading-snug text-gray-400">{{ __('driver_check.join.settings.read_members_hint') }}</span>
                                        </span>
                                    </label>
                                @endif

                                <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2 xl:grid-cols-4">
                                    @foreach ($keys as $key)
                                        @php($field = $numberField($key))
                                        <x-driver-check.field :label="$field['label']" :hint="$field['hint']">
                                            <x-driver-check.input
                                                type="number"
                                                inputmode="numeric"
                                                x-model="form.{{ $key }}"
                                                ::min="limits.{{ $key }}?.[0]"
                                                ::max="limits.{{ $key }}?.[1]"
                                                required
                                            />
                                        </x-driver-check.field>
                                    @endforeach
                                </div>

                                @if ($section === 'pace')
                                    <p class="mt-3 text-[12px] text-gray-500 dark:text-gray-400" x-text="s.per_day_total.replace(':accounts', markedCount()).replace(':n', perDay())"></p>
                                @endif
                            </section>
                        @endforeach
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-25 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-white/[0.015]">
                        <span x-show="dirty" x-cloak class="inline-flex items-center gap-1.5 text-[12px] font-medium text-warning-700 dark:text-warning-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-warning-500"></span>
                            {{ __('driver_check.join.settings.dirty') }}
                        </span>
                        <span x-show="!dirty" class="hidden sm:block"></span>

                        <div class="flex items-center gap-2 sm:justify-end">
                            <x-driver-check.button variant="ghost" x-on:click="reset()" ::disabled="busy || !savedInPanel">
                                {{ __('driver_check.join.settings.reset') }}
                            </x-driver-check.button>
                            <x-driver-check.button type="submit" variant="primary" ::disabled="busy || !dirty">
                                {{ __('driver_check.join.settings.save') }}
                            </x-driver-check.button>
                        </div>
                    </div>
                </x-driver-check.surface>
            </form>
        </template>
    </x-driver-check.shell>
</div>

@endsection
