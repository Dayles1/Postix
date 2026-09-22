{{-- Pill. The tone comes from the caller (usually a :class binding). --}}

<span
    {{ $attributes->merge([
        'class' => 'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full
                    px-2.5 py-1 text-[11px] font-medium',
    ]) }}
>
    {{ $slot }}
</span>
