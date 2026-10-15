@props([
    'variant' => 'secondary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconRight' => null,
    'type' => 'button',
])

{{--
    One button, four voices:
      primary   - ink on paper, the single "do it" of a view
      secondary - hairline, the everyday action
      ghost     - no frame, for quiet or repeated actions
      danger / success - only where the colour is the message
    Sizes are 40px on touch screens and 36px from sm up (32 for `sm`).
--}}

@php
    $base = 'dc-tap inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg font-medium
             transition-colors duration-150 select-none outline-none
             focus-visible:ring-2 focus-visible:ring-brand-500/60 focus-visible:ring-offset-1 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-dark
             disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:opacity-50';

    $variants = [
        'primary' => 'bg-gray-900 text-white hover:bg-gray-700 active:bg-black
                      dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200 dark:active:bg-gray-300',

        'secondary' => 'border border-gray-300 bg-white text-gray-800 hover:border-gray-400 hover:bg-gray-50 active:bg-gray-100
                        dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-100 dark:hover:border-gray-500 dark:hover:bg-white/[0.07]',

        'success' => 'bg-success-600 text-white hover:bg-success-700 active:bg-success-800',

        'danger' => 'bg-error-600 text-white hover:bg-error-700 active:bg-error-800',

        'ghost' => 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 active:bg-gray-200
                    dark:text-gray-400 dark:hover:bg-white/[0.07] dark:hover:text-white',
    ];

    $sizes = [
        'lg' => 'h-11 px-5 text-sm sm:h-10',
        'md' => 'h-10 px-3.5 text-[13px] sm:h-9',
        'sm' => 'h-9 px-3 text-[13px] sm:h-8 sm:text-xs',
        'icon' => 'h-10 w-10 sm:h-9 sm:w-9',
    ];

    $classes = trim(preg_replace('/\s+/', ' ',
        $base . ' ' . ($variants[$variant] ?? $variants['secondary']) . ' ' . ($sizes[$size] ?? $sizes['md'])
    ));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-driver-check.icon :name="$icon" class="h-4 w-4 shrink-0" />
        @endif

        {{ $slot }}

        @if ($iconRight)
            <x-driver-check.icon :name="$iconRight" class="h-4 w-4 shrink-0" />
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-driver-check.icon :name="$icon" class="h-4 w-4 shrink-0" />
        @endif

        {{ $slot }}

        @if ($iconRight)
            <x-driver-check.icon :name="$iconRight" class="h-4 w-4 shrink-0" />
        @endif
    </button>
@endif
