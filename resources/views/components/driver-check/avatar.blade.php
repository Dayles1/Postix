@props(['size' => 'md'])

{{-- A rounded square monogram; the console has no round avatars. --}}

@php
    $sizes = [
        'sm' => 'h-8 w-8 text-[10px]',
        'md' => 'h-9 w-9 text-xs',
        'lg' => 'h-14 w-14 rounded-xl text-base sm:h-16 sm:w-16 sm:text-lg',
    ];
@endphp

<span
    {{ $attributes->merge([
        'class' => 'flex shrink-0 items-center justify-center rounded-lg bg-gray-100 font-bold uppercase text-gray-600 dark:bg-white/[0.07] dark:text-gray-300 '
            . ($sizes[$size] ?? $sizes['md']),
    ]) }}
>
    {{ $slot }}
</span>
