{{--
    Paging.

    A phone gets three big targets (back / position / forward); a desktop
    gets the numbered pages and the "showing x-y of z" line.
--}}

<div
    x-show="pagination.last_page > 1"
    x-cloak
    class="flex flex-col gap-3 border-t border-gray-200 px-3.5 py-3 sm:flex-row sm:items-center
           sm:justify-between sm:px-4 dark:border-gray-800"
>
    <p class="hidden text-xs text-gray-500 sm:block dark:text-gray-400">
        {{ __('telegram.ui.showing') }}
        <span class="font-semibold tabular-nums text-gray-700 dark:text-gray-200" x-text="number(pagination.from)">0</span>
        &ndash;
        <span class="font-semibold tabular-nums text-gray-700 dark:text-gray-200" x-text="number(pagination.to)">0</span>
        {{ __('telegram.ui.of') }}
        <span class="font-semibold tabular-nums text-gray-700 dark:text-gray-200" x-text="number(pagination.total)">0</span>
    </p>

    <div class="flex items-center justify-between gap-2 sm:justify-end">
        <button
            type="button"
            x-on:click="goToPage(pagination.current_page - 1)"
            :disabled="pagination.current_page <= 1 || loading"
            class="dc-tap inline-flex h-11 items-center gap-1.5 rounded-xl border border-gray-300
                   bg-white px-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50
                   disabled:cursor-not-allowed disabled:opacity-40 sm:h-9
                   dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.07]"
        >
            <x-driver-check.icon name="chevron-left" class="h-4 w-4" />
            <span class="hidden sm:inline">{{ __('telegram.ui.previous') }}</span>
        </button>

        {{-- Compact position readout, phones only --}}
        <span class="text-sm font-medium tabular-nums text-gray-600 sm:hidden dark:text-gray-300">
            <span x-text="pagination.current_page">1</span>
            <span class="text-gray-400 dark:text-gray-500">/</span>
            <span x-text="pagination.last_page">1</span>
        </span>

        <div class="hidden items-center gap-1 sm:flex">
            <template x-for="page in visiblePages()" :key="page">
                <span>
                    <template x-if="isGap(page)">
                        <span class="px-1.5 text-sm text-gray-400 dark:text-gray-600">&hellip;</span>
                    </template>

                    <template x-if="!isGap(page)">
                        <button
                            type="button"
                            x-on:click="goToPage(page)"
                            :disabled="loading"
                            :aria-current="page === pagination.current_page ? 'page' : null"
                            :class="page === pagination.current_page
                                ? 'bg-brand-500 text-white shadow-sm shadow-brand-500/25'
                                : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/[0.07]'"
                            class="dc-tap h-9 min-w-9 rounded-lg px-2.5 text-sm font-medium tabular-nums transition
                                   disabled:cursor-not-allowed"
                            x-text="page"
                        ></button>
                    </template>
                </span>
            </template>
        </div>

        <button
            type="button"
            x-on:click="goToPage(pagination.current_page + 1)"
            :disabled="pagination.current_page >= pagination.last_page || loading"
            class="dc-tap inline-flex h-11 items-center gap-1.5 rounded-xl border border-gray-300
                   bg-white px-3 text-sm font-medium text-gray-700 transition hover:bg-gray-50
                   disabled:cursor-not-allowed disabled:opacity-40 sm:h-9
                   dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-white/[0.07]"
        >
            <span class="hidden sm:inline">{{ __('telegram.ui.next') }}</span>
            <x-driver-check.icon name="chevron-right" class="h-4 w-4" />
        </button>
    </div>
</div>
