@props([
    'model' => 'filters.search',
    'placeholder' => '',
    'debounce' => '400ms',
])

{{--
    Search types as you go (debounced) and clears in one tap - no "Apply"
    round trip for the field people use most.
--}}

<div class="relative">
    <x-driver-check.icon
        name="search"
        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
    />

    <input
        type="search"
        inputmode="search"
        autocomplete="off"
        x-model="{{ $model }}"
        x-on:input.debounce.{{ $debounce }}="applyFilters()"
        x-on:search="applyFilters()"
        x-on:keydown.enter.prevent="applyFilters()"
        placeholder="{{ $placeholder }}"
        aria-label="{{ $placeholder ?: __('telegram.ui.search') }}"
        {{ $attributes->merge([
            'class' => 'dc-search h-10 w-full rounded-lg border border-gray-300 bg-white pl-9 pr-9
                        text-sm text-gray-900 outline-none transition placeholder:text-gray-400
                        hover:border-gray-400
                        focus:border-brand-500 focus:ring-[3px] focus:ring-brand-500/15
                        sm:h-9 dark:border-gray-700 dark:bg-gray-950 dark:text-white
                        dark:placeholder:text-gray-500 dark:hover:border-gray-600',
        ]) }}
    >

    <button
        type="button"
        x-show="{{ $model }}"
        x-cloak
        x-on:click="clearSearch()"
        class="dc-tap absolute right-1 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center
               justify-center rounded-md text-gray-400 transition hover:bg-gray-100
               hover:text-gray-700 dark:hover:bg-white/[0.08] dark:hover:text-gray-200"
        aria-label="{{ __('telegram.ui.search_clear') }}"
    >
        <x-driver-check.icon name="close" class="h-3.5 w-3.5" />
    </button>
</div>
