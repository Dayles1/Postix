@props([
    'label',
    'icon' => null,
    'chip' => null,
    'tone' => null,
    'hint' => null,
])

{{--
    One reading. Number first, in tabular figures; the label above it in
    small capitals; the icon is a quiet mark in the corner, never a coloured
    tile. `chip` and `tone` are accepted for the pages that still pass them
    and ignored.
--}}

<div
    {{ $attributes->merge([
        'class' => 'dc-surface group relative overflow-hidden rounded-2xl p-4 transition-colors hover:border-gray-300 dark:hover:border-gray-600',
    ]) }}
>
    <div class="flex items-start justify-between gap-3">
        <p class="dc-eyebrow truncate">{{ $label }}</p>

        @if ($icon)
            <x-driver-check.icon :name="$icon" class="h-4 w-4 shrink-0 text-gray-300 transition-colors group-hover:text-brand-500 dark:text-gray-600" />
        @endif
    </div>

    <p class="mt-3 text-[28px] font-semibold leading-none tracking-[-0.02em] tabular-nums text-gray-950 dark:text-white">
        {{ $slot }}
    </p>

    @if ($hint)
        <p class="mt-2.5 truncate text-[11.5px] leading-4 text-gray-400 dark:text-gray-500">
            {{ $hint }}
        </p>
    @endif
</div>
