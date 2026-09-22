@props(['title' => null, 'sortOptions' => []])

{{--
    Header strip of the results card: how many rows, how they are ordered,
    how many per page. Ordering lives here rather than with the filters
    because it describes the table, not the query.

    The options are rendered by Blade rather than by an x-for: Alpine applies
    x-model to the <select> before it renders the template's children, so a
    looped list would leave the control showing the first option while the
    state said something else (a sort read back from the URL, for instance).
--}}

<div
    class="flex flex-col gap-3 border-b border-gray-200 px-3.5 py-3 sm:flex-row sm:items-center
           sm:justify-between sm:px-4 dark:border-gray-800"
>
    <div class="flex min-w-0 items-center gap-2">
        @if ($title)
            <h2 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
        @endif

        <span
            class="inline-flex h-6 items-center rounded-full bg-gray-100 px-2 text-[11px]
                   font-semibold tabular-nums text-gray-600 dark:bg-white/[0.06] dark:text-gray-300"
            x-text="number(pagination.total)"
        >0</span>

        <span
            x-show="loading"
            x-cloak
            class="inline-flex items-center gap-1.5 text-[11px] font-medium text-gray-400 dark:text-gray-500"
        >
            <span class="h-1.5 w-1.5 animate-ping rounded-full bg-brand-500"></span>
            {{ __('telegram.ui.loading') }}
        </span>
    </div>

    <div
        @class([
            'items-center gap-2 sm:flex sm:justify-end',
            'grid grid-cols-[1fr_auto_auto]' => count($sortOptions) > 0,
            'flex justify-end' => count($sortOptions) === 0,
        ])
    >
        @if (count($sortOptions) > 0)
            <x-driver-check.select
                x-model="filters.sort"
                x-on:change="applyFilters()"
                class="sm:w-48"
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

        <x-driver-check.select
            x-model.number="filters.per_page"
            x-on:change="changePerPage()"
            class="sm:w-28"
            aria-label="{{ __('telegram.ui.per_page') }}"
        >
            @foreach ([10, 20, 50, 100] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </x-driver-check.select>
    </div>
</div>
