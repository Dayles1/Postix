@props([
    'variant' => 'secondary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'iconRight' => null,
    'type' => 'button',
])

@php
    $base = 'dc-tap inline-flex items-center justify-center gap-2 rounded-xl font-medium
             transition select-none outline-none focus-visible:ring-4
             disabled:cursor-not-allowed disabled:opacity-55 aria-disabled:opacity-55';

    $variants = [
        'primary' => 'bg-brand-500 text-white shadow-sm shadow-brand-500/25 hover:bg-brand-600
                      focus-visible:ring-brand-500/25 active:bg-brand-700',

        'secondary' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50
                        focus-visible:ring-gray-500/15 active:bg-gray-100
                        dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-200
                        dark:hover:bg-white/[0.08] dark:active:bg-white/[0.12]',

        'success' => 'bg-success-600 text-white shadow-sm shadow-success-600/25 hover:bg-success-700
                      focus-visible:ring-success-500/25 active:bg-success-800',

        'danger' => 'bg-error-600 text-white shadow-sm shadow-error-600/25 hover:bg-error-700
                     focus-visible:ring-error-500/25',

        'ghost' => 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus-visible:ring-gray-500/15
                    dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-white',
    ];

    $sizes = [
        'lg' => 'h-12 px-5 text-sm',
        'md' => 'h-11 px-4 text-sm sm:h-10',
        'sm' => 'h-9 px-3 text-[13px]',
        'icon' => 'h-10 w-10 shrink-0',
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
