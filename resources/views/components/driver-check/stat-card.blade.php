@props([
    'label',
    'icon' => null,
    'chip' => 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300',
    'tone' => 'text-gray-900 dark:text-white',
    'hint' => null,
])

{{--
    Two per row on a phone, four on a desktop. The icon disappears below sm
    so the number keeps the whole tile to itself.
--}}

<div
    {{ $attributes->merge([
        'class' => 'rounded-2xl border border-gray-200 bg-white p-3.5 shadow-sm transition
                    hover:shadow-md sm:p-4 dark:border-gray-800 dark:bg-white/[0.03]',
    ]) }}
>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-[11px] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                {{ $label }}
            </p>

            <p class="mt-1.5 text-xl font-semibold tracking-tight tabular-nums sm:text-2xl {{ $tone }}">
                {{ $slot }}
            </p>

            @if ($hint)
                <p class="mt-0.5 truncate text-[11px] text-gray-400 dark:text-gray-500">
                    {{ $hint }}
                </p>
            @endif
        </div>

        @if ($icon)
            <span class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-xl sm:flex {{ $chip }}">
                <x-driver-check.icon :name="$icon" class="h-[18px] w-[18px]" />
            </span>
        @endif
    </div>
</div>
