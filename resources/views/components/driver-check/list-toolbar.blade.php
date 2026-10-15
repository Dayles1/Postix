@props(['title' => null, 'sortOptions' => []])

{{--
    Header strip of a results panel: how many rows, how they are ordered,
    how many per page. Ordering lives here rather than with the filters
    because it describes the table, not the query.

    The options are rendered by Blade rather than by an x-for: Alpine applies
    x-model to the <select> before it renders the template's children, so a
    looped list would leave the control showing the first option while the
    state said something else (a sort read back from the URL, for instance).
--}}

<div
    class="flex flex-col gap-3 border-b border-gray-200 px-3.5 py-2.5 sm:flex-row sm:items-center
           sm:justify-between dark:border-gray-800"
>
    <div class="flex min-w-0 items-center gap-2.5">
        @if ($title)
            <h2 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
        @endif

        <span
            class="inline-flex h-5 items-center rounded bg-gray-100 px-1.5 text-[11px] font-bold
                   tabular-nums text-gray-600 dark:bg-white/[0.07] dark:text-gray-300"
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

        <x-driver-check.select
            x-model.number="filters.per_page"
            x-on:change="changePerPage()"
            class="sm:w-24"
            aria-label="{{ __('telegram.ui.per_page') }}"
        >
            @foreach ([10, 20, 50, 100] as $option)
                <option value="{{ $option }}">{{ $option }}</option>
            @endforeach
        </x-driver-check.select>
    </div>
</div>
