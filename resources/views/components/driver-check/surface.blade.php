{{-- The one card surface used across the panel. --}}

<div
    {{ $attributes->merge([
        'class' => 'rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]',
    ]) }}
>
    {{ $slot }}
</div>
