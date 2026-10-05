@props([
    'label',
    'icon' => null,
    'chip' => 'bg-gray-100 text-gray-600 ring-gray-200/70 dark:bg-white/[0.06] dark:text-gray-300 dark:ring-white/10',
    'tone' => null,
    'hint' => null,
])

{{--
    Two per row on a phone, four on a desktop.

    The colour lives in the icon chip only; the number stays neutral, so a
    row of counters reads as one row. A value that needs alarm colours it
    itself (an inner span with its own class wins). The hint line keeps its
    height even when empty, so tiles with and without one stay level.

    `tone` is accepted for the pages that still pass it and ignored.
--}}

<div
    {{ $attributes->merge([
        'class' => 'group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 shadow-xs
                    transition duration-200 hover:-translate-y-px hover:shadow-md
                    dark:border-gray-800 dark:bg-white/[0.03]',
    ]) }}
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-[11px] font-semibold uppercase tracking-[0.1em] text-gray-500 dark:text-gray-400">
                {{ $label }}
            </p>

            <p class="mt-2 text-2xl font-semibold leading-none tracking-tight tabular-nums text-gray-900 dark:text-white">
                {{ $slot }}
            </p>

            <p class="mt-2 min-h-4 truncate text-[11px] leading-4 text-gray-400 dark:text-gray-500">
                {{ $hint ?? '' }}
            </p>
        </div>

        @if ($icon)
            <span
                class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset ring-current/15
                       transition duration-200 group-hover:scale-105 sm:flex {{ $chip }}"
            >
                <x-driver-check.icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif
    </div>
</div>
