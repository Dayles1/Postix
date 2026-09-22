@props(['label' => null, 'hint' => null, 'for' => null])

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    @if ($label)
        <label
            @if ($for) for="{{ $for }}" @endif
            class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400"
        >
            {{ $label }}
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="mt-1 text-[11px] leading-snug text-gray-400 dark:text-gray-500">
            {{ $hint }}
        </p>
    @endif

    @isset($error)
        {{ $error }}
    @endisset
</div>
