@props([
    'icon' => 'pencil',
    'label',
])

{{--
    The action at the end of a table row: one square, one icon, the words in
    the tooltip. The same size on every list, so the last column lines up.
--}}

<button
    type="button"
    title="{{ $label }}"
    aria-label="{{ $label }}"
    {{ $attributes->merge([
        'class' => 'dc-tap inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent
                    text-gray-400 transition hover:border-gray-200 hover:bg-white hover:text-brand-600
                    focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/50
                    dark:hover:border-gray-700 dark:hover:bg-white/[0.05] dark:hover:text-brand-300',
    ]) }}
>
    <x-driver-check.icon :name="$icon" class="h-4 w-4" />
</button>
