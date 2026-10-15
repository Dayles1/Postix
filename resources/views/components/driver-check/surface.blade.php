{{-- The one panel of the console: flat, a hairline, no shadow. --}}

<div
    {{ $attributes->merge([
        'class' => 'dc-surface rounded-2xl',
    ]) }}
>
    {{ $slot }}
</div>
