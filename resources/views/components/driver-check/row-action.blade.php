@props([
    'icon' => 'pencil',
    'label',
])

{{--
    The action at the end of a table row: one square, one icon, the words in
    the tooltip. The same size on every list, so the last column lines up
    and never gets clipped by a long translation.
--}}

<button
    type="button"
    title="{{ $label }}"
    aria-label="{{ $label }}"
    {{ $attributes->merge([
        'class' => 'dc-tap inline-flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200 bg-white
                    text-gray-500 shadow-xs transition hover:border-brand-300 hover:bg-brand-25 hover:text-brand-600
                    focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20
                    dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-400 dark:hover:border-brand-500/40
                    dark:hover:bg-brand-500/10 dark:hover:text-brand-400',
    ]) }}
>
    <x-driver-check.icon :name="$icon" class="h-4 w-4" />
</button>
