{{--
    Native select (the right control on a phone) with the browser arrow
    replaced by one that matches the rest of the panel.

    A class passed by the caller sizes the wrapper, never the select: the
    select stays w-full so the two can never disagree.
--}}

<div {{ $attributes->only('class')->merge(['class' => 'relative min-w-0']) }}>
    <select
        {{ $attributes->except('class')->merge([
            'class' => 'h-11 w-full appearance-none rounded-xl border border-gray-300 bg-white pl-3.5 pr-9
                        text-sm text-gray-900 outline-none transition
                        focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10
                        disabled:cursor-not-allowed disabled:opacity-60
                        sm:h-10 dark:border-gray-700 dark:bg-gray-900 dark:text-white',
        ]) }}
    >
        {{ $slot }}
    </select>

    <x-driver-check.icon
        name="chevron-down"
        class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
    />
</div>
