@props(['type' => 'text'])

{{--
    44px tall on touch screens, 40px from sm up. Anything smaller is a
    missed tap on a phone.
--}}

<input
    type="{{ $type }}"
    {{ $attributes->merge([
        'class' => 'dc-input h-11 w-full rounded-xl border border-gray-300 bg-white px-3.5 text-sm text-gray-900
                    outline-none transition placeholder:text-gray-400
                    focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10
                    disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400
                    sm:h-10 dark:border-gray-700 dark:bg-gray-900 dark:text-white
                    dark:placeholder:text-gray-500 dark:disabled:bg-white/[0.02]'
        . ($type === 'date' ? ' dc-date' : ''),
    ]) }}
>
