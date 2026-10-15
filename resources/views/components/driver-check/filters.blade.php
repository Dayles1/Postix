@props([
    'searchPlaceholder' => null,
    'searchModel' => 'filters.search',
    /*
     * Inside the results panel, as its top strip, instead of a panel of its
     * own. The ordering and page size then sit in the same row, so a list
     * has exactly one toolbar.
     */
    'embedded' => false,
    'sortOptions' => [],
    'perPage' => true,
])

{{--
    The filter strip - one row, always in the same order:

        [ search ............ ] [ Filters (n) ] [ x ]   |   [ sort v ] [ asc/desc ] [ per page ]

    Search is always in reach; everything else folds away behind the one
    Filters button that carries a count, so a phone is not two thumb-scrolls
    of inputs before the first row of data.

    The sort options are rendered by Blade rather than by an x-for: Alpine
    applies x-model to the <select> before it renders the template's
    children, so a looped list would leave the control showing the first
    option while the state said something else (a sort read back from the
    URL, for instance).
--}}

@php
    $hasAdvanced = trim((string) $slot) !== '';
@endphp

<div
    @class([
        'relative',
        'dc-surface rounded-2xl p-3' => ! $embedded,
        'border-b border-gray-200 bg-gray-25 p-3 dark:border-gray-800 dark:bg-white/[0.015]' => $embedded,
    ])
>
    <div class="flex flex-col gap-3">
        {{-- Optional: what narrows the list before anything is typed (tabs, an active chip). --}}
        @isset($leading)
            {{ $leading }}
        @endisset

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            @if ($searchPlaceholder !== null)
                <div class="min-w-0 lg:max-w-md lg:flex-1">
                    <x-driver-check.search :model="$searchModel" :placeholder="$searchPlaceholder" />
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-2 lg:flex-1">
                @if ($hasAdvanced)
                    <button
                        type="button"
                        x-on:click="advancedOpen = !advancedOpen"
                        :aria-expanded="advancedOpen"
                        :class="advancedOpen || activeFilterCount() > 0
                            ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-900'
                            : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-gray-600'"
                        class="dc-tap inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-lg border
                               px-3 text-[13px] font-medium transition outline-none
                               focus-visible:ring-2 focus-visible:ring-brand-500/50 sm:h-9 lg:flex-none"
                    >
                        <x-driver-check.icon name="sliders" class="h-4 w-4" />

                        <span x-text="advancedOpen ? @js(__('telegram.ui.filters_hide')) : @js(__('telegram.ui.filters'))"></span>

                        <span
                            x-show="activeFilterCount() > 0"
                            x-cloak
                            class="inline-flex h-[18px] min-w-[18px] items-center justify-center rounded
                                   bg-brand-400 px-1 text-[10.5px] font-bold text-gray-950"
                            x-text="activeFilterCount()"
                        ></span>
                    </button>

                    <button
                        type="button"
                        x-show="hasActiveFilters()"
                        x-cloak
                        x-on:click="resetFilters()"
                        class="dc-tap inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900 sm:h-9 sm:w-9
                               dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-white"
                        :title="@js(__('telegram.ui.reset'))"
                        :aria-label="@js(__('telegram.ui.reset'))"
                    >
                        <x-driver-check.icon name="close" class="h-4 w-4" />
                    </button>
                @endif

                <div class="hidden flex-1 lg:block"></div>

                @if (count($sortOptions) > 0)
                    <x-driver-check.select
                        x-model="filters.sort"
                        x-on:change="applyFilters()"
                        class="flex-1 lg:w-48 lg:flex-none"
                        aria-label="{{ __('telegram.ui.sort') }}"
                    >
                        @foreach ($sortOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-driver-check.select>

                    <button
                        type="button"
                        x-on:click="toggleDirection()"
                        :aria-label="filters.direction === 'asc' ? @js(__('telegram.ui.sort_asc')) : @js(__('telegram.ui.sort_desc'))"
                        :title="filters.direction === 'asc' ? @js(__('telegram.ui.sort_asc')) : @js(__('telegram.ui.sort_desc'))"
                        class="dc-tap flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border
                               border-gray-300 bg-white text-gray-600 transition hover:border-gray-400 sm:h-9 sm:w-9
                               dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:hover:border-gray-600"
                    >
                        <x-driver-check.icon
                            name="arrow-up"
                            class="h-4 w-4 transition-transform duration-200"
                            ::class="filters.direction === 'desc' && 'rotate-180'"
                        />
                    </button>
                @endif

                @if ($embedded && $perPage)
                    <x-driver-check.select
                        x-model.number="filters.per_page"
                        x-on:change="changePerPage()"
                        class="w-[72px] shrink-0"
                        aria-label="{{ __('telegram.ui.per_page') }}"
                        :title="__('telegram.ui.per_page')"
                    >
                        @foreach ([10, 20, 50, 100] as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </x-driver-check.select>
                @endif
            </div>
        </div>

        @if ($hasAdvanced)
            <div x-show="advancedOpen" x-collapse x-cloak>
                <div class="grid grid-cols-1 gap-3 border-t border-dashed border-gray-200 pt-3 sm:grid-cols-2 xl:grid-cols-4 dark:border-gray-800">
                    {{ $slot }}
                </div>
            </div>
        @endif
    </div>

    {{-- A refetch in flight: a thin bar along the bottom edge, nothing jumps --}}
    @if ($embedded)
        <div x-show="loading" x-cloak class="dc-progress absolute inset-x-0 -bottom-px h-0.5 overflow-hidden">
            <span class="block h-full w-1/3 bg-brand-500"></span>
        </div>
    @endif
</div>
