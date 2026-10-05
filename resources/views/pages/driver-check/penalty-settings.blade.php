@extends('layouts.app')

@section('title', __('telegram.penalty_settings.title'))

@php
    /*
     * The names the first four levels are shown with until one is typed.
     */
    $defaultNames = collect(range(0, \App\Application\Telegram\Services\ClientCheckRules::MAX_LEVELS - 1))
        ->map(fn (int $n) => \Illuminate\Support\Facades\Lang::has("telegram.penalties.levels.{$n}")
            ? __("telegram.penalties.levels.{$n}")
            : null)
        ->all();

    $placeholders = collect(\App\Application\Telegram\Services\ClientCheckRules::PLACEHOLDERS)
        ->map(fn (string $name) => ['name' => $name, 'hint' => __("telegram.penalty_settings.placeholders.{$name}")])
        ->values();
@endphp

@section('content')

<div
    x-data="dcPenaltySettings({
        endpoints: {
            rules: @js(route('api.telegram.client-checks.rules')),
            settings: @js(route('api.telegram.client-checks.settings.update')),
        },
        settings: @js([
            'enabled' => \App\Models\Telegram\TelegramSetting::clientChecksEnabled(),
            'comments_enabled' => \App\Models\Telegram\TelegramSetting::clientCheckCommentsEnabled(),
        ]),
        settingsSaved: @js(__('telegram.penalties.settings.saved')),
        maxLevels: @js(\App\Application\Telegram\Services\ClientCheckRules::MAX_LEVELS),
        defaultNames: @js($defaultNames),
        placeholders: @js($placeholders),
        translations: @js(__('telegram.penalty_settings')),
        ui: @js(__('telegram.ui')),
    })"
    class="pb-24"
>
    <x-driver-check.shell>

        {{-- ============================================================
             Header
        ============================================================= --}}
        <x-driver-check.page-header
            icon="sliders"
            tone="brand"
            eyebrow="CRM"
            :title="__('telegram.penalty_settings.title')"
            :description="__('telegram.penalty_settings.description')"
        >
            <x-slot:actions>
                <span
                    x-show="!loading"
                    x-cloak
                    class="hidden h-9 items-center gap-1.5 rounded-full px-3 text-[12px] font-medium sm:inline-flex"
                    :class="customised
                        ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400'
                        : 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300'"
                    x-text="customised ? translations.actions.customised : translations.actions.default"
                ></span>

                <x-driver-check.button icon="arrow-left" :href="route('driver-check.penalties')" class="col-span-2 whitespace-nowrap sm:col-span-1">
                    {{ __('telegram.penalty_settings.back') }}
                </x-driver-check.button>
            </x-slot:actions>
        </x-driver-check.page-header>

        <div
            x-show="loadError"
            x-cloak
            class="dc-break rounded-2xl bg-error-50 px-4 py-3 text-sm text-error-700 dark:bg-error-500/10 dark:text-error-400"
        >
            <span x-text="loadError"></span>
            <button type="button" class="ml-2 font-semibold underline" x-on:click="load()">{{ __('telegram.ui.refresh') }}</button>
        </div>

        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">

            {{-- ========================================================
                 Levels: the ladder, calm at the top, harsh at the bottom
            ========================================================= --}}
            <section class="flex min-w-0 flex-col gap-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ __('telegram.penalty_settings.sections.levels') }}
                    </h2>
                    <p class="mt-0.5 text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                        {{ __('telegram.penalty_settings.sections.levels_hint') }}
                    </p>
                </div>

                {{-- Loading --}}
                <template x-if="loading">
                    <div class="flex flex-col gap-3">
                        <template x-for="i in 3" :key="'sk-' + i">
                            <div class="h-44 animate-pulse rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"></div>
                        </template>
                    </div>
                </template>

                <template x-for="(level, index) in form.levels" :key="level.key">
                    <article
                        class="relative overflow-hidden rounded-2xl border bg-white shadow-sm transition dark:bg-white/[0.03]"
                        :class="reachable(index)
                            ? 'border-gray-200 dark:border-gray-800'
                            : 'border-error-300 dark:border-error-500/40'"
                    >
                        {{-- How harsh: a coloured rail down the left edge --}}
                        <span class="absolute inset-y-0 left-0 w-1" :class="tone(index).rail"></span>

                        <div class="flex flex-col gap-4 p-4 pl-5 sm:p-5 sm:pl-6">

                            {{-- Head: always visible --}}
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span
                                    class="inline-flex h-7 shrink-0 items-center rounded-lg px-2.5 text-[12px] font-semibold tabular-nums"
                                    :class="tone(index).badge"
                                    x-text="levelTitle(index)"
                                ></span>

                                <input
                                    type="text"
                                    x-model="level.name"
                                    maxlength="60"
                                    :placeholder="levelName(index) || translations.level.name_placeholder"
                                    :aria-label="translations.level.name"
                                    class="h-8 min-w-0 flex-1 rounded-lg border border-transparent bg-transparent px-2 text-sm font-semibold
                                           text-gray-900 outline-none transition placeholder:font-semibold placeholder:text-gray-900
                                           hover:border-gray-200 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10
                                           dark:text-white dark:placeholder:text-white dark:hover:border-gray-700"
                                >

                                <span
                                    class="hidden shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium tabular-nums text-gray-600
                                           sm:inline dark:bg-white/[0.06] dark:text-gray-300"
                                    x-text="phraseCount(index) + ' · ' + translations.level.phrases.toLowerCase()"
                                ></span>

                                <button
                                    type="button"
                                    x-show="index > 0"
                                    x-on:click="removeLevel(index)"
                                    class="dc-tap inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400
                                           transition hover:bg-error-50 hover:text-error-600 dark:hover:bg-error-500/10 dark:hover:text-error-400"
                                    :title="translations.level.remove"
                                    :aria-label="translations.level.remove"
                                >
                                    <x-driver-check.icon name="close" class="h-4 w-4" />
                                </button>

                                <button
                                    type="button"
                                    x-on:click="toggleLevel(index)"
                                    :aria-expanded="level.open"
                                    class="dc-tap inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-500
                                           transition hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.06]"
                                >
                                    <x-driver-check.icon
                                        name="chevron-down"
                                        class="h-4 w-4 transition-transform duration-200"
                                        ::class="level.open && 'rotate-180'"
                                    />
                                </button>
                            </div>

                            {{-- When, in one line; folded, also the first phrase --}}
                            <button type="button" x-on:click="toggleLevel(index)" class="-mt-1 flex flex-col gap-1.5 text-left">
                                <span class="flex flex-wrap items-baseline gap-x-2 text-[13px]">
                                    <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                        {{ __('telegram.penalty_settings.level.conditions') }}
                                    </span>
                                    <span
                                        :class="reachable(index)
                                            ? 'text-gray-700 dark:text-gray-300'
                                            : 'font-medium text-error-600 dark:text-error-400'"
                                        x-text="summary(index)"
                                    ></span>
                                </span>

                                <span
                                    x-show="!level.open && firstPhrase(index)"
                                    class="flex min-w-0 items-start gap-2 rounded-lg bg-gray-50 px-2.5 py-1.5 dark:bg-white/[0.03]"
                                >
                                    <x-driver-check.icon name="send" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" />
                                    {{-- Already reduced to Telegram's tags by telegramHtml(). --}}
                                    <span class="line-clamp-1 text-[12px] text-gray-600 dark:text-gray-300" x-html="firstPhrase(index)"></span>
                                </span>
                            </button>

                            <div x-show="level.open" x-collapse class="flex flex-col gap-4">

                            {{-- When: the thresholds --}}
                            <div x-show="index > 0" class="grid grid-cols-2 gap-2.5 border-t border-gray-100 pt-4 sm:grid-cols-5 dark:border-gray-800">
                                <template x-for="condition in conditions" :key="condition">
                                    <label class="min-w-0" :title="translations.conditions[condition + '_hint']">
                                        <span
                                            class="mb-1 block truncate text-[11px] font-medium text-gray-500 dark:text-gray-400"
                                            x-text="translations.conditions[condition]"
                                        ></span>
                                        <input
                                            type="number"
                                            min="1"
                                            inputmode="numeric"
                                            x-model="level[condition]"
                                            placeholder="—"
                                            class="h-10 w-full rounded-xl border bg-white px-3 text-sm tabular-nums text-gray-900 outline-none
                                                   transition placeholder:text-gray-300 focus:border-brand-500 focus:ring-4
                                                   focus:ring-brand-500/10 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-600"
                                            :class="fieldError('levels.' + index + '.' + condition)
                                                ? 'border-error-400 dark:border-error-500/60'
                                                : 'border-gray-300 dark:border-gray-700'"
                                        >
                                    </label>
                                </template>
                            </div>

                            {{-- What it says --}}
                            <div class="flex flex-col gap-2.5 border-t border-gray-100 pt-4 dark:border-gray-800">
                                <div class="flex items-end justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                            {{ __('telegram.penalty_settings.level.phrases') }}
                                        </p>
                                        <p class="mt-0.5 text-[12px] leading-snug text-gray-500 dark:text-gray-400">
                                            {{ __('telegram.penalty_settings.level.phrases_hint') }}
                                        </p>
                                    </div>
                                </div>

                                <p
                                    x-show="level.phrases.length === 0 && index > 0"
                                    class="rounded-xl border border-dashed border-gray-200 px-3 py-2.5 text-[12px] text-gray-500
                                           dark:border-gray-800 dark:text-gray-400"
                                >{{ __('telegram.penalty_settings.level.phrases_borrow') }}</p>

                                <template x-for="(phrase, p) in level.phrases" :key="phrase.key">
                                    <div class="group rounded-xl border border-gray-200 bg-gray-50/60 p-2.5 dark:border-gray-800 dark:bg-white/[0.02]">
                                        <div class="flex items-start gap-2">
                                            <textarea
                                                :id="'phrase-' + index + '-' + p"
                                                x-model="phrase.text"
                                                x-on:focus="focusPhrase(index, p)"
                                                rows="2"
                                                maxlength="1000"
                                                class="min-h-[2.75rem] w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2 text-[13px]
                                                       leading-relaxed text-gray-900 outline-none transition focus:border-brand-500 focus:ring-4
                                                       focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                                            ></textarea>

                                            <button
                                                type="button"
                                                x-on:click="removePhrase(index, p)"
                                                class="dc-tap mt-1 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400
                                                       transition hover:bg-error-50 hover:text-error-600 dark:hover:bg-error-500/10
                                                       dark:hover:text-error-400"
                                                :title="translations.level.remove_phrase"
                                                :aria-label="translations.level.remove_phrase"
                                            >
                                                <x-driver-check.icon name="close" class="h-4 w-4" />
                                            </button>
                                        </div>

                                        {{-- Already reduced to Telegram's tags by telegramHtml(). --}}
                                        <div
                                            x-show="String(phrase.text || '').trim() !== ''"
                                            class="mt-2 flex items-start gap-2 px-1"
                                        >
                                            <x-driver-check.icon name="send" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" />
                                            <p
                                                class="dc-break whitespace-pre-wrap text-[12px] leading-relaxed text-gray-600 dark:text-gray-300"
                                                :title="translations.level.preview"
                                                x-html="preview(phrase.text, index)"
                                            ></p>
                                        </div>
                                    </div>
                                </template>

                                <div>
                                    <x-driver-check.button size="sm" variant="ghost" icon="plus" x-on:click="addPhrase(index)">
                                        {{ __('telegram.penalty_settings.level.add_phrase') }}
                                    </x-driver-check.button>
                                </div>
                            </div>
                            </div>

                            <template x-for="message in levelErrors(index)" :key="message">
                                <p class="rounded-lg bg-error-50 px-3 py-2 text-[12px] font-medium text-error-600 dark:bg-error-500/10 dark:text-error-400"
                                   x-text="message"></p>
                            </template>
                        </div>
                    </article>
                </template>

                <button
                    type="button"
                    x-show="!loading && form.levels.length < maxLevels"
                    x-cloak
                    x-on:click="addLevel()"
                    x-ref="levelsEnd"
                    class="dc-tap flex h-14 items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-300
                           text-sm font-medium text-gray-500 transition hover:border-brand-400 hover:bg-brand-25 hover:text-brand-600
                           dark:border-gray-700 dark:text-gray-400 dark:hover:border-brand-500/50 dark:hover:bg-brand-500/[0.06]
                           dark:hover:text-brand-400"
                >
                    <x-driver-check.icon name="plus" class="h-4 w-4" />
                    {{ __('telegram.penalty_settings.level.add') }}
                </button>
            </section>

            {{-- ========================================================
                 Aside: the switches, the placeholders, the timings
            ========================================================= --}}
            <aside class="flex flex-col gap-4 xl:sticky xl:top-24">

                {{-- Delivery: saved at once, these are the "stop now" buttons --}}
                <x-driver-check.surface class="divide-y divide-gray-100 dark:divide-gray-800">
                    <h3 class="px-4 pb-2 pt-4 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ __('telegram.penalty_settings.sections.delivery') }}
                    </h3>

                    @foreach ([
                        'enabled' => ['telegram.penalties.settings.enabled', 'telegram.penalties.settings.enabled_hint'],
                        'comments_enabled' => ['telegram.penalties.settings.comments', 'telegram.penalties.settings.comments_hint'],
                    ] as $key => [$label, $hint])
                        <div class="flex items-start justify-between gap-4 px-4 py-3.5">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __($label) }}</p>
                                <p class="mt-0.5 text-[12px] leading-snug text-gray-500 dark:text-gray-400">{{ __($hint) }}</p>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="settings.{{ $key }}"
                                aria-label="{{ __($label) }}"
                                x-on:click="toggleSetting('{{ $key }}')"
                                :disabled="!!settingsBusy{{ $key === 'comments_enabled' ? ' || !settings.enabled' : '' }}"
                                :class="settings.{{ $key }} ? 'bg-brand-500' : 'bg-gray-200 dark:bg-white/[0.12]'"
                                class="dc-tap relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full transition
                                       outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 disabled:opacity-50"
                            >
                                <span
                                    :class="settings.{{ $key }} ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                                ></span>
                            </button>
                        </div>
                    @endforeach
                </x-driver-check.surface>

                {{-- Placeholders --}}
                <x-driver-check.surface class="p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ __('telegram.penalty_settings.sections.placeholders') }}
                    </h3>
                    <p class="mt-1 text-[12px] leading-snug text-gray-500 dark:text-gray-400">
                        {{ __('telegram.penalty_settings.sections.placeholders_hint') }}
                    </p>

                    <ul class="mt-3 flex flex-col gap-1">
                        <template x-for="item in placeholders" :key="item.name">
                            <li>
                                <button
                                    type="button"
                                    x-on:click="insertPlaceholder(item.name)"
                                    class="dc-tap flex w-full items-center justify-between gap-3 rounded-lg px-2 py-1.5 text-left transition
                                           hover:bg-brand-25 dark:hover:bg-brand-500/[0.07]"
                                >
                                    <code
                                        class="shrink-0 rounded-md bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] text-gray-700
                                               dark:bg-white/[0.06] dark:text-gray-300"
                                        x-text="'{' + item.name + '}'"
                                    ></code>
                                    <span class="truncate text-[12px] text-gray-500 dark:text-gray-400" x-text="item.hint"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                </x-driver-check.surface>

                {{-- Timing --}}
                <x-driver-check.surface class="p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ __('telegram.penalty_settings.sections.timing') }}
                    </h3>

                    <div class="mt-3 grid grid-cols-2 gap-3">
                        @foreach (['batch_quiet_seconds', 'history_days', 'max_attempts', 'retry_minutes'] as $field)
                            <label class="min-w-0" title="{{ __("telegram.penalty_settings.timing.{$field}_hint") }}">
                                <span class="mb-1 block text-[11px] font-medium leading-tight text-gray-500 dark:text-gray-400">
                                    {{ __("telegram.penalty_settings.timing.{$field}") }}
                                </span>
                                <input
                                    type="number"
                                    min="1"
                                    inputmode="numeric"
                                    x-model="form.{{ $field }}"
                                    class="h-10 w-full rounded-xl border bg-white px-3 text-sm tabular-nums text-gray-900 outline-none
                                           transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10
                                           dark:bg-gray-900 dark:text-white"
                                    :class="fieldError('{{ $field }}')
                                        ? 'border-error-400 dark:border-error-500/60'
                                        : 'border-gray-300 dark:border-gray-700'"
                                >
                                <span
                                    x-show="fieldError('{{ $field }}')"
                                    x-cloak
                                    class="mt-1 block text-[11px] text-error-600 dark:text-error-400"
                                    x-text="fieldError('{{ $field }}')"
                                ></span>
                            </label>
                        @endforeach
                    </div>

                    <label class="mt-3 block">
                        <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-gray-400">
                            {{ __('telegram.penalty_settings.timing.batch_line') }}
                        </span>
                        <x-driver-check.input
                            id="batch-line"
                            x-model="form.batch_line"
                            x-on:focus="focusBatchLine()"
                            maxlength="500"
                        />
                        <span class="mt-1 block text-[11px] leading-snug text-gray-400 dark:text-gray-500">
                            {{ __('telegram.penalty_settings.timing.batch_line_hint') }}
                        </span>
                    </label>
                </x-driver-check.surface>

                <div class="flex justify-end">
                    <x-driver-check.button
                        size="sm"
                        variant="ghost"
                        icon="refresh"
                        x-show="customised"
                        x-cloak
                        x-on:click="reset()"
                        ::disabled="saving"
                    >
                        <span x-text="resetArmed ? translations.actions.reset_confirm : translations.actions.reset"></span>
                    </x-driver-check.button>
                </div>
            </aside>
        </div>
    </x-driver-check.shell>

    {{-- ================================================================
         Save bar: only there when there is something to save
    ================================================================= --}}
    <div
        x-show="dirty() || saving || formError"
        x-cloak
        x-transition.opacity
        {{-- Clear of the sidebar, which is wide or narrow (layouts/app.blade.php) --}}
        :class="$store.sidebar.isExpanded || $store.sidebar.isHovered ? 'xl:left-[290px]' : 'xl:left-[90px]'"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-[0_-8px_24px_-12px_rgba(16,24,40,0.18)]
               backdrop-blur dark:border-gray-800 dark:bg-gray-900/95"
    >
        <div class="mx-auto flex max-w-screen-2xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p class="flex items-center gap-2 text-sm">
                <span class="h-2 w-2 shrink-0 rounded-full" :class="formError ? 'bg-error-500' : 'bg-warning-500'"></span>
                <span
                    :class="formError ? 'font-medium text-error-600 dark:text-error-400' : 'text-gray-700 dark:text-gray-300'"
                    x-text="formError || translations.actions.dirty"
                ></span>
            </p>

            <div class="flex gap-2">
                <x-driver-check.button x-on:click="discard()" ::disabled="saving" class="flex-1 sm:flex-none">
                    {{ __('telegram.penalty_settings.actions.discard') }}
                </x-driver-check.button>

                <x-driver-check.button variant="primary" x-on:click="save()" ::disabled="saving || !dirty()" class="flex-1 sm:flex-none">
                    <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin" x-show="saving" x-cloak />
                    <span x-text="saving ? translations.actions.saving : translations.actions.save"></span>
                </x-driver-check.button>
            </div>
        </div>
    </div>
</div>

@endsection
