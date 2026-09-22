@props([
    'open' => 'modalOpen',
    'close' => 'closeModal()',
    'size' => 'sm:max-w-lg',
    'title' => null,
    'subtitle' => null,
])

{{--
    Dialog.

    Two things it deliberately does NOT do:

    1. It does not lock <html>/<body>. The layout gives html a fixed height,
       so setting overflow:hidden on it makes the browser clamp the scroll
       offset - the page jumped to the very top the moment a dialog opened,
       and stayed there after it closed. Instead the dialog's own scroller
       carries `overscroll-contain`, which keeps a wheel or a swipe over the
       dialog from ever reaching the page behind it. Nothing moves.

    2. It does not render in place. Teleported to <body> it sits above the
       sticky header and the sidebar, both of which run at z-index 99999 -
       the old dialog at z-9999 rendered underneath them.

    Bottom sheet on a phone, centred dialog from sm up.
--}}

<template x-teleport="body">
    <div
        x-show="{{ $open }}"
        x-cloak
        class="fixed inset-0 z-[100050]"
        role="dialog"
        aria-modal="true"
        x-on:keydown.escape.window="{{ $close }}"
    >
        <div
            x-show="{{ $open }}"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-gray-900/60 backdrop-blur-[2px]"
        ></div>

        <div class="absolute inset-0 overflow-y-auto overscroll-contain">
            <div
                class="flex min-h-full items-end justify-center sm:items-center sm:p-4"
                x-on:click.self="{{ $close }}"
            >
                <div
                    x-show="{{ $open }}"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-6 sm:translate-y-0 sm:scale-95"
                    class="relative flex w-full flex-col rounded-t-3xl bg-white shadow-2xl
                           sm:rounded-2xl dark:bg-gray-dark {{ $size }}"
                >
                    {{-- Grab handle: tells a thumb this sheet is a sheet. --}}
                    <div class="flex justify-center pt-2.5 sm:hidden">
                        <span class="h-1 w-10 rounded-full bg-gray-300 dark:bg-gray-700"></span>
                    </div>

                    <div
                        class="sticky top-0 z-10 flex items-start justify-between gap-4 rounded-t-3xl
                               border-b border-gray-200 bg-white px-4 py-3.5 sm:rounded-t-2xl sm:px-5
                               dark:border-gray-800 dark:bg-gray-dark"
                    >
                        <div class="min-w-0 pt-0.5">
                            <h2 class="truncate text-base font-semibold text-gray-900 dark:text-white">
                                {{-- `heading` carries a title the page has to render itself (x-text). --}}
                                @isset($heading)
                                    {{ $heading }}
                                @else
                                    {{ $title }}
                                @endisset
                            </h2>

                            @if ($subtitle)
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
                            @endif
                        </div>

                        <button
                            type="button"
                            x-on:click="{{ $close }}"
                            class="dc-tap -mr-1.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl
                                   text-gray-400 transition hover:bg-gray-100 hover:text-gray-700
                                   dark:hover:bg-white/[0.08] dark:hover:text-gray-200"
                            aria-label="{{ __('telegram.ui.close') }}"
                        >
                            <x-driver-check.icon name="close" class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="px-4 py-4 sm:px-5">
                        {{ $slot }}
                    </div>

                    @isset($footer)
                        <div
                            class="sticky bottom-0 z-10 flex flex-col-reverse gap-2 border-t border-gray-200
                                   bg-white px-4 pt-3 sm:flex-row sm:justify-end sm:rounded-b-2xl sm:px-5
                                   sm:pb-4 dark:border-gray-800 dark:bg-gray-dark"
                            style="padding-bottom: max(0.875rem, env(safe-area-inset-bottom));"
                        >
                            {{ $footer }}
                        </div>
                    @endisset
                </div>
            </div>
        </div>
    </div>
</template>
