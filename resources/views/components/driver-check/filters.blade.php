@props([
    'searchPlaceholder' => null,
    'searchModel' => 'filters.search',
])

{{--
    Filter card.

    Search is always in reach; everything else folds away behind one button
    that carries a count, so a phone screen is not two thumb-scrolls of
    inputs before the first row of data.
--}}

<x-driver-check.surface class="p-3 sm:p-4">
    <div class="flex flex-col gap-3">

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            @if ($searchPlaceholder !== null)
                <div class="min-w-0 flex-1">
                    <x-driver-check.search :model="$searchModel" :placeholder="$searchPlaceholder" />
                </div>
            @endif

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    x-on:click="advancedOpen = !advancedOpen"
                    :aria-expanded="advancedOpen"
                    :class="advancedOpen || activeFilterCount() > 0
                        ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400'
                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.07]'"
                    class="dc-tap inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl border
                           px-3.5 text-sm font-medium transition outline-none
                           focus-visible:ring-4 focus-visible:ring-brand-500/20 sm:h-10 sm:flex-none"
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

                    <x-driver-check.icon
                        name="chevron-down"
                        class="h-4 w-4 transition-transform duration-200"
                        ::class="advancedOpen && 'rotate-180'"
                    />
                </button>

                <button
                    type="button"
                    x-show="hasActiveFilters()"
                    x-cloak
                    x-on:click="resetFilters()"
                    class="dc-tap inline-flex h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl
                           px-3 text-sm font-medium text-gray-500 transition hover:bg-gray-100
                           hover:text-gray-900 sm:h-10 dark:text-gray-400 dark:hover:bg-white/[0.06]
                           dark:hover:text-white"
                >
                    <x-driver-check.icon name="close" class="h-4 w-4" />
                    <span class="hidden sm:inline">{{ __('telegram.ui.reset') }}</span>
                </button>
            </div>
        </div>

        <div x-show="advancedOpen" x-collapse x-cloak>
            <div class="grid grid-cols-1 gap-3 border-t border-gray-100 pt-3.5 sm:grid-cols-2 xl:grid-cols-4 dark:border-gray-800">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-driver-check.surface>
