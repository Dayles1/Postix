@extends('layouts.app')

@section('title', __('telegram.auto_replies.title'))

@php
    $placeholders = collect(\App\Application\Telegram\Services\AutoReplyRules::PLACEHOLDERS)
        ->map(fn (string $name) => ['name' => $name, 'hint' => __("telegram.auto_replies.placeholders.{$name}")])
        ->values();
@endphp

@section('content')

<div
    x-data="dcAutoReplies({
        endpoints: {
            rules: @js(route('api.telegram.auto-replies')),
            upload: @js(route('api.telegram.auto-replies.media.upload')),
            media: @js(url('/api/telegram/auto-replies/media')),
        },
        maxReplies: @js(\App\Application\Telegram\Services\AutoReplyRules::MAX_REPLIES),
        placeholders: @js($placeholders),
        languages: @js(__('telegram.penalty_settings.languages')),
        translations: @js(__('telegram.auto_replies')),
    })"
    class="pb-24"
>
    <x-driver-check.shell>

        {{-- ============================================================
             Header
        ============================================================= --}}
        <x-driver-check.page-header
            icon="send"
            tone="brand"
            :eyebrow="__('telegram.menu.groups.settings')"
            :title="__('telegram.auto_replies.title')"
            :description="__('telegram.auto_replies.description')"
        >
            <x-slot:actions>
                <span
                    x-show="!loading"
                    x-cloak
                    class="hidden h-9 items-center gap-1.5 rounded-full px-3 text-[12px] font-medium sm:inline-flex"
                    :class="customised
                        ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400'
                        : 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300'"
                    x-text="customised ? translations.file.customised : translations.file.default"
                ></span>

                <x-driver-check.button icon="download" :href="route('api.telegram.auto-replies.download')" class="col-span-2 whitespace-nowrap sm:col-span-1">
                    {{ __('telegram.auto_replies.file.download') }}
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

        {{-- A hand edit gone wrong: nothing is answered until it is fixed --}}
        <div
            x-show="fileError"
            x-cloak
            class="dc-break flex items-start gap-2 rounded-2xl bg-error-50 px-4 py-3 text-sm text-error-700 dark:bg-error-500/10 dark:text-error-400"
        >
            <x-driver-check.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
            <span x-text="translations.file.broken.replace(':error', fileError)"></span>
        </div>

        {{-- Try a message, against the form as it is now --}}
        <x-driver-check.surface class="p-4">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {{ __('telegram.auto_replies.sections.test') }}
            </h3>

            <input
                type="text"
                x-model="test.text"
                :placeholder="translations.test.placeholder"
                class="mt-3 h-10 w-full rounded-xl border border-gray-300 bg-white px-3 text-sm text-gray-900 outline-none transition
                       focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            >

            <div class="mt-2 flex items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                <span x-text="translations.test.as"></span>
                <select
                    x-model="test.language"
                    class="h-7 rounded-lg border border-gray-300 bg-white px-2 text-[12px] text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                >
                    <template x-for="lang in languages" :key="'tl-' + lang">
                        <option :value="lang" x-text="lang.toUpperCase()"></option>
                    </template>
                </select>
                <select
                    x-model="test.tone"
                    class="h-7 rounded-lg border border-gray-300 bg-white px-2 text-[12px] text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                >
                    <template x-for="t in tones" :key="'tt-' + t">
                        <option :value="t" x-text="translations.tones[t]"></option>
                    </template>
                </select>
            </div>

            <div x-show="tried().state !== 'empty'" x-cloak class="mt-3 flex flex-col gap-1.5">
                <p
                    class="text-[12px] font-medium"
                    :class="tried().state === 'match' ? 'text-success-700 dark:text-success-400' : 'text-gray-500 dark:text-gray-400'"
                    x-text="triedText()"
                ></p>

                {{-- Every possible answer, one is picked at random. Already reduced to Telegram's tags by telegramHtml(). --}}
                <template x-for="(answer, a) in tried().answers || []" :key="'ta-' + a">
                    <p
                        class="dc-break self-start whitespace-pre-wrap rounded-xl bg-brand-25 px-3 py-2 text-[13px] leading-relaxed
                               text-gray-800 dark:bg-brand-500/[0.07] dark:text-gray-200"
                        x-html="answer"
                    ></p>
                </template>

                <p
                    x-show="tried().gifs || tried().voices"
                    class="text-[11px] text-gray-500 dark:text-gray-400"
                    x-text="translations.test.media.replace(':gifs', tried().gifs || 0).replace(':voices', tried().voices || 0)"
                ></p>
            </div>
        </x-driver-check.surface>

        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">

            {{-- ========================================================
                 The kinds: what they write, what we answer
            ========================================================= --}}
            <section class="flex min-w-0 flex-col gap-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ __('telegram.auto_replies.sections.kinds') }}
                        </h2>
                        <p class="mt-0.5 max-w-2xl text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                            {{ __('telegram.auto_replies.sections.kinds_hint') }}
                        </p>
                    </div>

                    {{-- The language being edited --}}
                    <div
                        class="inline-flex shrink-0 gap-1 self-start rounded-xl bg-gray-100 p-1 sm:self-auto dark:bg-white/[0.04]"
                        role="group"
                        aria-label="{{ __('telegram.auto_replies.sections.language') }}"
                    >
                        <template x-for="lang in languages" :key="'lang-' + lang">
                            <button
                                type="button"
                                x-on:click="language = lang"
                                :aria-pressed="language === lang"
                                :class="language === lang
                                    ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white'
                                    : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                                class="dc-tap inline-flex h-9 items-center gap-2 rounded-lg px-3.5 text-[13px] font-medium transition"
                            >
                                <span class="font-mono text-[10px] font-semibold opacity-60" x-text="lang.toUpperCase()"></span>
                                <span x-text="languageNames[lang]"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Loading --}}
                <template x-if="loading">
                    <div class="flex flex-col gap-3">
                        <template x-for="i in 2" :key="'sk-' + i">
                            <div class="h-40 animate-pulse rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"></div>
                        </template>
                    </div>
                </template>

                <template x-for="(kind, k) in form.replies" :key="kind.key">
                    <article
                        class="relative overflow-hidden rounded-2xl border bg-white shadow-sm transition dark:bg-white/[0.03]"
                        :class="kindErrors(k).length
                            ? 'border-error-300 dark:border-error-500/40'
                            : 'border-gray-200 dark:border-gray-800'"
                    >
                        <span class="absolute inset-y-0 left-0 w-1 bg-success-500"></span>

                        <div class="flex flex-col gap-4 p-4 pl-5 sm:p-5 sm:pl-6">

                            {{-- Head: order, name, remove --}}
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex h-7 min-w-7 shrink-0 items-center justify-center rounded-lg bg-success-50 px-2 font-mono text-[12px]
                                           font-semibold text-success-700 dark:bg-success-500/10 dark:text-success-400"
                                    x-text="k + 1"
                                ></span>

                                <input
                                    type="text"
                                    x-model="kind.name"
                                    maxlength="60"
                                    :placeholder="kindTitle(k)"
                                    :aria-label="translations.kind.name"
                                    class="h-8 min-w-0 flex-1 rounded-lg border border-transparent bg-transparent px-2 text-sm font-semibold
                                           text-gray-900 outline-none transition placeholder:text-gray-400 hover:border-gray-200
                                           focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10
                                           dark:text-white dark:placeholder:text-gray-500 dark:hover:border-gray-700"
                                >

                                <span
                                    class="hidden shrink-0 rounded-full bg-gray-100 px-2 py-0.5 font-mono text-[11px] font-medium text-gray-600
                                           sm:inline dark:bg-white/[0.06] dark:text-gray-300"
                                    x-text="keywordCount(k)"
                                ></span>

                                <button
                                    type="button"
                                    x-on:click="moveKind(k, -1)"
                                    :disabled="k === 0"
                                    class="dc-tap inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-500 transition
                                           hover:bg-gray-100 disabled:opacity-30 dark:text-gray-400 dark:hover:bg-white/[0.06]"
                                    :title="translations.kind.move_up"
                                    :aria-label="translations.kind.move_up"
                                >
                                    <x-driver-check.icon name="arrow-up" class="h-4 w-4" />
                                </button>

                                <button
                                    type="button"
                                    x-on:click="moveKind(k, 1)"
                                    :disabled="k === form.replies.length - 1"
                                    class="dc-tap inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-500 transition
                                           hover:bg-gray-100 disabled:opacity-30 dark:text-gray-400 dark:hover:bg-white/[0.06]"
                                    :title="translations.kind.move_down"
                                    :aria-label="translations.kind.move_down"
                                >
                                    <x-driver-check.icon name="arrow-down" class="h-4 w-4" />
                                </button>

                                <button
                                    type="button"
                                    x-on:click="removeKind(k)"
                                    class="dc-tap inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400
                                           transition hover:bg-error-50 hover:text-error-600 dark:hover:bg-error-500/10 dark:hover:text-error-400"
                                    :title="translations.kind.remove"
                                    :aria-label="translations.kind.remove"
                                >
                                    <x-driver-check.icon name="close" class="h-4 w-4" />
                                </button>
                            </div>

                            {{-- What they write --}}
                            <label class="flex flex-col gap-1">
                                <span class="text-[13px] font-medium text-gray-700 dark:text-gray-300" x-text="translations.kind.keywords"></span>
                                <textarea
                                    x-model="kind.keywords"
                                    rows="2"
                                    class="w-full resize-y rounded-lg border bg-white px-2.5 py-2 font-mono text-[12px] leading-relaxed
                                           text-gray-900 outline-none transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10
                                           dark:bg-gray-900 dark:text-white"
                                    :class="fieldError('replies.' + k + '.keywords')
                                        ? 'border-error-400 dark:border-error-500/60'
                                        : 'border-gray-300 dark:border-gray-700'"
                                ></textarea>
                                <span class="text-[11px] leading-snug text-gray-400 dark:text-gray-500" x-text="translations.kind.keywords_hint"></span>
                            </label>

                            {{-- How long a message this kind still takes --}}
                            <label class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="text-[13px] font-medium text-gray-700 dark:text-gray-300" x-text="translations.kind.max_words"></span>
                                <input
                                    type="number"
                                    min="1"
                                    max="50"
                                    inputmode="numeric"
                                    x-model="kind.max_words"
                                    :placeholder="form.max_words"
                                    class="h-9 w-20 rounded-lg border bg-white px-2.5 text-sm tabular-nums text-gray-900 outline-none transition
                                           focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:bg-gray-900 dark:text-white"
                                    :class="fieldError('replies.' + k + '.max_words')
                                        ? 'border-error-400 dark:border-error-500/60'
                                        : 'border-gray-300 dark:border-gray-700'"
                                >
                                <span class="text-[11px] text-gray-400 dark:text-gray-500" x-text="maxWordsHint()"></span>
                            </label>

                            {{-- What we answer: both tones side by side --}}
                            <div class="flex flex-col gap-2">
                                <span class="text-[13px] font-medium text-gray-700 dark:text-gray-300" x-text="translations.kind.answers"></span>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <template x-for="t in tones" :key="'col-' + t">
                                        <div
                                            class="flex min-w-0 flex-col gap-2 rounded-xl p-3"
                                            :class="t === 'respectful'
                                                ? 'bg-brand-25 ring-1 ring-inset ring-brand-100 dark:bg-brand-500/[0.05] dark:ring-brand-500/20'
                                                : 'bg-gray-50 ring-1 ring-inset ring-gray-100 dark:bg-white/[0.02] dark:ring-gray-800'"
                                        >
                                            <div class="flex items-baseline justify-between gap-2">
                                                <p
                                                    class="text-[12px] font-semibold"
                                                    :class="t === 'respectful' ? 'text-brand-700 dark:text-brand-300' : 'text-gray-700 dark:text-gray-200'"
                                                    x-text="translations.tones[t]"
                                                ></p>
                                                <p class="truncate text-[11px] text-gray-400 dark:text-gray-500" x-text="translations.tones[t + '_hint']"></p>
                                            </div>

                                            <p
                                                x-show="answers(k, t).length === 0"
                                                class="rounded-lg border border-dashed border-gray-300 px-2.5 py-2 text-[12px] italic text-gray-400
                                                       dark:border-gray-700 dark:text-gray-500"
                                                x-text="translations.kind.empty"
                                            ></p>

                                            <template x-for="(answer, a) in answers(k, t)" :key="answer.key">
                                                <div class="rounded-lg bg-white p-2 shadow-xs ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                                                    <div class="flex items-start gap-1.5">
                                                        <textarea
                                                            :id="answerId(k, t, a)"
                                                            x-model="answer.text"
                                                            x-on:focus="focusAnswer(k, t, a)"
                                                            rows="1"
                                                            maxlength="1000"
                                                            class="min-h-[2.25rem] w-full resize-y rounded-md border-0 bg-transparent px-1.5 py-1 text-[13px]
                                                                   leading-relaxed text-gray-900 outline-none focus:ring-0 dark:text-white"
                                                        ></textarea>

                                                        <button
                                                            type="button"
                                                            x-on:click="removeAnswer(k, t, a)"
                                                            class="dc-tap inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400
                                                                   transition hover:bg-error-50 hover:text-error-600 dark:hover:bg-error-500/10
                                                                   dark:hover:text-error-400"
                                                            :title="translations.kind.remove_answer"
                                                            :aria-label="translations.kind.remove_answer"
                                                        >
                                                            <x-driver-check.icon name="close" class="h-3.5 w-3.5" />
                                                        </button>
                                                    </div>

                                                    {{-- Already reduced to Telegram's tags by telegramHtml(). --}}
                                                    <div
                                                        x-show="String(answer.text || '').trim() !== ''"
                                                        class="mt-1.5 flex items-start gap-1.5 border-t border-gray-100 px-1.5 pt-1.5 dark:border-gray-800"
                                                    >
                                                        <x-driver-check.icon name="send" class="mt-0.5 h-3 w-3 shrink-0 text-gray-300 dark:text-gray-600" />
                                                        <p
                                                            class="dc-break whitespace-pre-wrap text-[12px] leading-relaxed text-gray-500 dark:text-gray-400"
                                                            x-html="preview(answer.text)"
                                                        ></p>
                                                    </div>
                                                </div>
                                            </template>

                                            <button
                                                type="button"
                                                x-on:click="addAnswer(k, t)"
                                                class="dc-tap inline-flex h-8 items-center gap-1.5 self-start rounded-lg px-2 text-[12px] font-medium
                                                       text-gray-500 transition hover:bg-white hover:text-gray-900 dark:text-gray-400
                                                       dark:hover:bg-white/[0.06] dark:hover:text-white"
                                            >
                                                <x-driver-check.icon name="plus" class="h-3.5 w-3.5" />
                                                <span x-text="translations.kind.add_answer"></span>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- GIFs and voice messages: picked at random among the texts --}}
                            <div class="flex flex-col gap-2">
                                <span class="text-[13px] font-medium text-gray-700 dark:text-gray-300" x-text="translations.media.title"></span>
                                @include('pages.driver-check.partials.auto-reply-media', ['owner' => 'k'])
                            </div>

                            <template x-for="message in kindErrors(k)" :key="message">
                                <p class="rounded-lg bg-error-50 px-3 py-2 text-[12px] font-medium text-error-600 dark:bg-error-500/10 dark:text-error-400"
                                   x-text="message"></p>
                            </template>
                        </div>
                    </article>
                </template>

                <p
                    x-show="!loading && form.replies.length === 0"
                    x-cloak
                    class="rounded-2xl border border-dashed border-gray-200 px-4 py-3 text-[13px] text-gray-500
                           dark:border-gray-800 dark:text-gray-400"
                >{{ __('telegram.auto_replies.kind.none') }}</p>

                <button
                    type="button"
                    x-show="!loading && form.replies.length < maxReplies"
                    x-cloak
                    x-on:click="addKind()"
                    x-ref="kindsEnd"
                    class="dc-tap flex h-12 items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-300
                           text-sm font-medium text-gray-500 transition hover:border-brand-400 hover:bg-brand-25 hover:text-brand-600
                           dark:border-gray-700 dark:text-gray-400 dark:hover:border-brand-500/50 dark:hover:bg-brand-500/[0.06]
                           dark:hover:text-brand-400"
                >
                    <x-driver-check.icon name="plus" class="h-4 w-4" />
                    {{ __('telegram.auto_replies.kind.add') }}
                </button>

                <p class="px-1 text-[12px] text-gray-400 dark:text-gray-500">
                    {{ __('telegram.auto_replies.kind.random_hint') }}
                </p>

                {{-- ====================================================
                     When they stay silent: one nudge per penalty
                ===================================================== --}}
                <article
                    x-show="!loading"
                    x-cloak
                    class="relative mt-2 overflow-hidden rounded-2xl border bg-white shadow-sm transition dark:bg-white/[0.03]"
                    :class="fieldError('silence.answers')
                        ? 'border-error-300 dark:border-error-500/40'
                        : 'border-gray-200 dark:border-gray-800'"
                >
                    <span class="absolute inset-y-0 left-0 w-1 bg-warning-400"></span>

                    <div class="flex flex-col gap-4 p-4 pl-5 sm:p-5 sm:pl-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                                    {{ __('telegram.auto_replies.sections.silence') }}
                                </h2>
                                <p class="mt-0.5 text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                                    {{ __('telegram.auto_replies.silence.enabled_hint') }}
                                </p>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="form.silence.enabled"
                                aria-label="{{ __('telegram.auto_replies.silence.enabled') }}"
                                x-on:click="form.silence.enabled = !form.silence.enabled"
                                :class="form.silence.enabled ? 'bg-brand-500' : 'bg-gray-200 dark:bg-white/[0.12]'"
                                class="dc-tap relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full transition
                                       outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
                            >
                                <span
                                    :class="form.silence.enabled ? 'translate-x-6' : 'translate-x-1'"
                                    class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                                ></span>
                            </button>
                        </div>

                        <div x-show="form.silence.enabled" x-collapse class="flex flex-col gap-4">
                            <label class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="text-[13px] font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('telegram.auto_replies.silence.after_penalties') }}
                                </span>
                                <input
                                    type="number"
                                    min="1"
                                    max="50"
                                    inputmode="numeric"
                                    x-model="form.silence.after_penalties"
                                    class="h-9 w-20 rounded-lg border bg-white px-2.5 text-sm tabular-nums text-gray-900 outline-none transition
                                           focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:bg-gray-900 dark:text-white"
                                    :class="fieldError('silence.after_penalties')
                                        ? 'border-error-400 dark:border-error-500/60'
                                        : 'border-gray-300 dark:border-gray-700'"
                                >
                                <span class="text-[11px] text-gray-400 dark:text-gray-500">
                                    {{ __('telegram.auto_replies.silence.after_penalties_hint') }}
                                </span>
                            </label>

                            <div class="grid gap-4 md:grid-cols-2">
                                <template x-for="t in tones" :key="'silence-' + t">
                                    <div
                                        class="flex min-w-0 flex-col gap-2 rounded-xl p-3"
                                        :class="t === 'respectful'
                                            ? 'bg-brand-25 ring-1 ring-inset ring-brand-100 dark:bg-brand-500/[0.05] dark:ring-brand-500/20'
                                            : 'bg-gray-50 ring-1 ring-inset ring-gray-100 dark:bg-white/[0.02] dark:ring-gray-800'"
                                    >
                                        <div class="flex items-baseline justify-between gap-2">
                                            <p
                                                class="text-[12px] font-semibold"
                                                :class="t === 'respectful' ? 'text-brand-700 dark:text-brand-300' : 'text-gray-700 dark:text-gray-200'"
                                                x-text="translations.tones[t]"
                                            ></p>
                                            <p class="truncate text-[11px] text-gray-400 dark:text-gray-500" x-text="translations.tones[t + '_hint']"></p>
                                        </div>

                                        <p
                                            x-show="answers('silence', t).length === 0"
                                            class="rounded-lg border border-dashed border-gray-300 px-2.5 py-2 text-[12px] italic text-gray-400
                                                   dark:border-gray-700 dark:text-gray-500"
                                            x-text="translations.kind.empty"
                                        ></p>

                                        <template x-for="(answer, a) in answers('silence', t)" :key="answer.key">
                                            <div class="rounded-lg bg-white p-2 shadow-xs ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                                                <div class="flex items-start gap-1.5">
                                                    <textarea
                                                        :id="answerId('silence', t, a)"
                                                        x-model="answer.text"
                                                        x-on:focus="focusAnswer('silence', t, a)"
                                                        rows="1"
                                                        maxlength="1000"
                                                        class="min-h-[2.25rem] w-full resize-y rounded-md border-0 bg-transparent px-1.5 py-1 text-[13px]
                                                               leading-relaxed text-gray-900 outline-none focus:ring-0 dark:text-white"
                                                    ></textarea>

                                                    <button
                                                        type="button"
                                                        x-on:click="removeAnswer('silence', t, a)"
                                                        class="dc-tap inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400
                                                               transition hover:bg-error-50 hover:text-error-600 dark:hover:bg-error-500/10
                                                               dark:hover:text-error-400"
                                                        :title="translations.kind.remove_answer"
                                                        :aria-label="translations.kind.remove_answer"
                                                    >
                                                        <x-driver-check.icon name="close" class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>

                                                {{-- Already reduced to Telegram's tags by telegramHtml(). --}}
                                                <div
                                                    x-show="String(answer.text || '').trim() !== ''"
                                                    class="mt-1.5 flex items-start gap-1.5 border-t border-gray-100 px-1.5 pt-1.5 dark:border-gray-800"
                                                >
                                                    <x-driver-check.icon name="send" class="mt-0.5 h-3 w-3 shrink-0 text-gray-300 dark:text-gray-600" />
                                                    <p
                                                        class="dc-break whitespace-pre-wrap text-[12px] leading-relaxed text-gray-500 dark:text-gray-400"
                                                        x-html="preview(answer.text)"
                                                    ></p>
                                                </div>
                                            </div>
                                        </template>

                                        <button
                                            type="button"
                                            x-on:click="addAnswer('silence', t)"
                                            class="dc-tap inline-flex h-8 items-center gap-1.5 self-start rounded-lg px-2 text-[12px] font-medium
                                                   text-gray-500 transition hover:bg-white hover:text-gray-900 dark:text-gray-400
                                                   dark:hover:bg-white/[0.06] dark:hover:text-white"
                                        >
                                            <x-driver-check.icon name="plus" class="h-3.5 w-3.5" />
                                            <span x-text="translations.kind.add_answer"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            {{-- GIFs and voice messages: picked at random among the texts --}}
                            <div class="flex flex-col gap-2">
                                <span class="text-[13px] font-medium text-gray-700 dark:text-gray-300" x-text="translations.media.title"></span>
                                @include('pages.driver-check.partials.auto-reply-media', ['owner' => "'silence'"])
                            </div>

                            <p
                                x-show="fieldError('silence.answers')"
                                x-cloak
                                class="rounded-lg bg-error-50 px-3 py-2 text-[12px] font-medium text-error-600 dark:bg-error-500/10 dark:text-error-400"
                                x-text="fieldError('silence.answers')"
                            ></p>
                        </div>
                    </div>
                </article>
            </section>

            {{-- ========================================================
                 Aside: the switch, when to answer, placeholders,
                 the file
            ========================================================= --}}
            <aside class="flex flex-col gap-4 xl:sticky xl:top-24">

                {{-- When to answer --}}
                <x-driver-check.surface class="divide-y divide-gray-100 dark:divide-gray-800">
                    <h3 class="px-4 pb-2 pt-4 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ __('telegram.auto_replies.sections.behaviour') }}
                    </h3>

                    <div class="flex items-start justify-between gap-4 px-4 py-3.5">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ __('telegram.auto_replies.fields.enabled') }}</p>
                            <p class="mt-0.5 text-[12px] leading-snug text-gray-500 dark:text-gray-400">{{ __('telegram.auto_replies.fields.enabled_hint') }}</p>
                        </div>

                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.enabled"
                            aria-label="{{ __('telegram.auto_replies.fields.enabled') }}"
                            x-on:click="form.enabled = !form.enabled"
                            :class="form.enabled ? 'bg-brand-500' : 'bg-gray-200 dark:bg-white/[0.12]'"
                            class="dc-tap relative mt-0.5 inline-flex h-7 w-12 shrink-0 items-center rounded-full transition
                                   outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
                        >
                            <span
                                :class="form.enabled ? 'translate-x-6' : 'translate-x-1'"
                                class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                            ></span>
                        </button>
                    </div>

                    {{-- Whom: any message of theirs, or only a penalty's reply --}}
                    <div class="flex flex-col gap-2 px-4 py-3.5" role="radiogroup">
                        @foreach (['scope_any' => false, 'scope_penalty' => true] as $scope => $only)
                            <label
                                class="dc-tap flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                                :class="form.only_after_penalty === @js($only)
                                    ? 'border-brand-500 bg-brand-25 dark:border-brand-500/50 dark:bg-brand-500/[0.07]'
                                    : 'border-gray-200 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.03]'"
                            >
                                <input
                                    type="radio"
                                    name="auto-replies-scope"
                                    :checked="form.only_after_penalty === @js($only)"
                                    x-on:change="form.only_after_penalty = @js($only)"
                                    class="mt-0.5 h-4 w-4 shrink-0 border-gray-300 text-brand-500 focus:ring-brand-500/30 dark:border-gray-600 dark:bg-gray-900"
                                >
                                <span class="min-w-0">
                                    <span class="block text-[13px] font-medium text-gray-900 dark:text-white">
                                        {{ __("telegram.auto_replies.fields.{$scope}") }}
                                    </span>
                                    <span class="mt-0.5 block text-[11px] leading-snug text-gray-500 dark:text-gray-400">
                                        {{ __("telegram.auto_replies.fields.{$scope}_hint") }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="flex flex-col gap-3.5 px-4 py-3.5">
                        @foreach (['penalty_window_minutes' => 1, 'cooldown_minutes' => 0, 'max_words' => 1] as $field => $min)
                            <label class="flex items-start justify-between gap-3">
                                <span class="min-w-0">
                                    <span class="block text-[13px] font-medium text-gray-700 dark:text-gray-300">
                                        {{ __("telegram.auto_replies.fields.{$field}") }}
                                    </span>
                                    <span class="mt-0.5 block text-[11px] leading-snug text-gray-400 dark:text-gray-500">
                                        {{ __("telegram.auto_replies.fields.{$field}_hint") }}
                                    </span>
                                    <span
                                        x-show="fieldError('{{ $field }}')"
                                        x-cloak
                                        class="mt-1 block text-[11px] text-error-600 dark:text-error-400"
                                        x-text="fieldError('{{ $field }}')"
                                    ></span>
                                </span>
                                <input
                                    type="number"
                                    min="{{ $min }}"
                                    inputmode="numeric"
                                    x-model="form.{{ $field }}"
                                    class="h-9 w-20 shrink-0 rounded-lg border bg-white px-2.5 text-right text-sm tabular-nums text-gray-900 outline-none
                                           transition focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 dark:bg-gray-900 dark:text-white"
                                    :class="fieldError('{{ $field }}')
                                        ? 'border-error-400 dark:border-error-500/60'
                                        : 'border-gray-300 dark:border-gray-700'"
                                >
                            </label>
                        @endforeach
                    </div>
                </x-driver-check.surface>

                {{-- Placeholders --}}
                <x-driver-check.surface class="p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ __('telegram.auto_replies.sections.placeholders') }}
                    </h3>
                    <p class="mt-1 text-[12px] leading-snug text-gray-500 dark:text-gray-400">
                        {{ __('telegram.auto_replies.sections.placeholders_hint') }}
                    </p>

                    <ul class="mt-3 flex flex-col gap-0.5">
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

                {{-- The file --}}
                <x-driver-check.surface class="p-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ __('telegram.auto_replies.sections.file') }}
                    </h3>

                    <p class="dc-break mt-2 font-mono text-[11px] text-gray-500 dark:text-gray-400" x-text="path"></p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-driver-check.button size="sm" icon="download" :href="route('api.telegram.auto-replies.download')">
                            {{ __('telegram.auto_replies.file.download') }}
                        </x-driver-check.button>

                        <x-driver-check.button
                            size="sm"
                            variant="ghost"
                            icon="refresh"
                            x-show="customised"
                            x-cloak
                            x-on:click="reset()"
                            ::disabled="saving"
                        >
                            <span x-text="resetArmed ? translations.file.reset_confirm : translations.file.reset"></span>
                        </x-driver-check.button>
                    </div>
                </x-driver-check.surface>
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
                    {{ __('telegram.auto_replies.actions.discard') }}
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
