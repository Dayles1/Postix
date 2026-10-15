{{--
    Native select (the right control on a phone) with the browser arrow
    replaced by one that matches the console.

    A class passed by the caller sizes the wrapper, never the select: the
    select stays w-full so the two can never disagree.
--}}

<div {{ $attributes->only('class')->merge(['class' => 'relative min-w-0']) }}>
    <select
        {{ $attributes->except('class')->merge([
            'class' => 'dc-select h-10 w-full appearance-none rounded-lg border border-gray-300 bg-white pl-3 pr-8
                        text-sm text-gray-900 outline-none transition hover:border-gray-400
                        focus:border-brand-500 focus:ring-[3px] focus:ring-brand-500/15
                        disabled:cursor-not-allowed disabled:opacity-60
                        sm:h-9 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:hover:border-gray-600',
        ]) }}
    >
        {{ $slot }}
    </select>

    <x-driver-check.icon
        name="chevron-down"
        class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
    />
</div>
