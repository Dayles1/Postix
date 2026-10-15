{{-- A small tag. The tone comes from the caller (usually a :class binding). --}}

<span
    {{ $attributes->merge([
        'class' => 'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-md
                    px-2 py-0.5 text-[11px] font-semibold leading-5',
    ]) }}
>
    {{ $slot }}
</span>
