@props(['type' => 'text'])

{{-- 40px on touch screens, 36px from sm up. --}}

<input
    type="{{ $type }}"
    {{ $attributes->merge([
        'class' => 'dc-input h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900
                    outline-none transition placeholder:text-gray-400
                    hover:border-gray-400
                    focus:border-brand-500 focus:ring-[3px] focus:ring-brand-500/15
                    disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-400
                    sm:h-9 dark:border-gray-700 dark:bg-gray-950 dark:text-white
                    dark:placeholder:text-gray-500 dark:hover:border-gray-600 dark:disabled:bg-white/[0.02]'
        . ($type === 'date' ? ' dc-date' : ''),
    ]) }}
>
