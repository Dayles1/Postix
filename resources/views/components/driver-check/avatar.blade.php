@props(['size' => 'md'])

@php
    $sizes = [
        'sm' => 'h-8 w-8 text-[10px]',
        'md' => 'h-10 w-10 text-xs',
        'lg' => 'h-14 w-14 text-base sm:h-16 sm:w-16 sm:text-lg',
    ];
@endphp

<span
    {{ $attributes->merge([
        'class' => 'flex shrink-0 items-center justify-center rounded-full font-semibold uppercase '
            . ($sizes[$size] ?? $sizes['md']),
    ]) }}
>
    {{ $slot }}
</span>
