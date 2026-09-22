@props([
    'model' => 'filters.search',
    'placeholder' => '',
    'debounce' => '400ms',
])

{{--
    Search types as you go (debounced) and clears with one thumb tap - no
    "Apply" round trip for the field people use most.
--}}

<div class="relative">
    <x-driver-check.icon
        name="search"
        class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
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
            'class' => 'dc-search h-11 w-full rounded-xl border border-gray-300 bg-white pl-10 pr-10
                        text-sm text-gray-900 outline-none transition placeholder:text-gray-400
                        focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10
                        sm:h-10 dark:border-gray-700 dark:bg-gray-900 dark:text-white
                        dark:placeholder:text-gray-500',
        ]) }}
    >

    <button
        type="button"
        x-show="{{ $model }}"
        x-cloak
        x-on:click="clearSearch()"
        class="dc-tap absolute right-1.5 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center
               justify-center rounded-lg text-gray-400 transition hover:bg-gray-100
               hover:text-gray-700 dark:hover:bg-white/[0.08] dark:hover:text-gray-200"
        aria-label="{{ __('telegram.ui.search_clear') }}"
    >
        <x-driver-check.icon name="close" class="h-4 w-4" />
    </button>
</div>
