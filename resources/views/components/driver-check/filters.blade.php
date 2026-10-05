@props([
    'searchPlaceholder' => null,
    'searchModel' => 'filters.search',
    /*
     * Inside the results card, as its top strip, instead of a card of its
     * own. The ordering and page size then sit in the same row, so a list
     * has exactly one toolbar.
     */
    'embedded' => false,
    'sortOptions' => [],
    'perPage' => true,
])

{{--
    Filter strip.

    Search is always in reach; everything else folds away behind one button
    that carries a count, so a phone screen is not two thumb-scrolls of
    inputs before the first row of data.

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
        'rounded-2xl border border-gray-200 bg-white p-3 shadow-xs sm:p-4 dark:border-gray-800 dark:bg-white/[0.03]' => ! $embedded,
        'border-b border-gray-200 p-3 sm:p-4 dark:border-gray-800' => $embedded,
    ])
>
    <div class="flex flex-col gap-3">
        {{-- Optional: what narrows the list before anything is typed (tabs, an active chip). --}}
        @isset($leading)
            {{ $leading }}
        @endisset

        <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
            @if ($searchPlaceholder !== null)
                <div class="min-w-0 lg:flex-1">
                    <x-driver-check.search :model="$searchModel" :placeholder="$searchPlaceholder" />
                </div>
            @endif

            <div class="flex items-center gap-2">
                @if ($hasAdvanced)
                    <button
                        type="button"
                        x-on:click="advancedOpen = !advancedOpen"
                        :aria-expanded="advancedOpen"
                        :class="advancedOpen || activeFilterCount() > 0
                            ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400'
                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.07]'"
                        class="dc-tap inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl border
                               px-3.5 text-sm font-medium transition outline-none
                               focus-visible:ring-4 focus-visible:ring-brand-500/20 sm:h-10 lg:flex-none"
                    >
                        <x-driver-check.icon name="sliders" class="h-4 w-4" />

                        <span x-text="advancedOpen ? @js(__('telegram.ui.filters_hide')) : @js(__('telegram.ui.filters'))"></span>

                        <span
                            x-show="activeFilterCount() > 0"
                            x-cloak
                            class="inline-flex h-5 min-w-5 items-center justify-center rounded-full
                                   bg-brand-500 px-1.5 text-[11px] font-semibold text-white"
                            x-text="activeFilterCount()"
                        ></span>
                    </button>

                    <button
                        type="button"
                        x-show="hasActiveFilters()"
                        x-cloak
                        x-on:click="resetFilters()"
                        class="dc-tap inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-gray-500
                               transition hover:bg-gray-100 hover:text-gray-900 sm:h-10 sm:w-10
                               dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-white"
                        :title="@js(__('telegram.ui.reset'))"
                        :aria-label="@js(__('telegram.ui.reset'))"
                    >
                        <x-driver-check.icon name="close" class="h-4 w-4" />
                    </button>
                @endif

                @if (count($sortOptions) > 0)
                    @if ($hasAdvanced)
                        <span class="mx-1 hidden h-6 w-px shrink-0 bg-gray-200 lg:block dark:bg-gray-800"></span>
                    @endif

                    <x-driver-check.select
                        x-model="filters.sort"
                        x-on:change="applyFilters()"
                        class="flex-1 lg:w-44 lg:flex-none"
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
                        class="dc-tap flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border
                               border-gray-300 bg-white text-gray-600 transition hover:bg-gray-50 sm:h-10 sm:w-10
                               dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.07]"
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
                        class="w-20 shrink-0"
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
                <div class="grid grid-cols-1 gap-3 border-t border-gray-100 pt-3.5 sm:grid-cols-2 xl:grid-cols-4 dark:border-gray-800">
                    {{ $slot }}
                </div>
            </div>
        @endif
    </div>

    {{-- A refetch in flight: a thin bar along the bottom edge, nothing jumps --}}
    @if ($embedded)
        <div x-show="loading" x-cloak class="dc-progress absolute inset-x-0 -bottom-px h-0.5 overflow-hidden">
            <span class="block h-full w-1/3 rounded-full bg-brand-500"></span>
        </div>
    @endif
</div>
