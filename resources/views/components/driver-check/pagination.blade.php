{{--
    The foot of a list - no page numbers: the next page loads by itself when
    this strip comes into view (list-page.js: loadMore). What is left of the
    "paging" is information and a way out:

      - "Showing 1-20 of 124" (what is on screen of what exists)
      - a spinner while the next page is coming
      - "Load more" for anyone who prefers a click (and as the retry after
        an error)
      - "That is all" at the very end

    The marker (x-ref moreSentinel) is watched from 320px away, so the rows
    are usually there before the eye gets to the end.
--}}

<div
    x-show="pagination.total > 0"
    x-cloak
    class="flex flex-col gap-2 border-t border-gray-200 bg-gray-25 px-3.5 py-2.5 sm:flex-row sm:items-center
           sm:justify-between dark:border-gray-800 dark:bg-white/[0.015]"
>
    <p class="text-center text-xs text-gray-500 sm:text-left dark:text-gray-400">
        {{ __('telegram.ui.showing') }}
        <span class="font-semibold tabular-nums text-gray-800 dark:text-gray-100" x-text="number(rows.length ? 1 : 0)">0</span>
        &ndash;
        <span class="font-semibold tabular-nums text-gray-800 dark:text-gray-100" x-text="number(rows.length)">0</span>
        {{ __('telegram.ui.of') }}
        <span class="font-semibold tabular-nums text-gray-800 dark:text-gray-100" x-text="number(pagination.total)">0</span>
    </p>

    <div class="flex items-center justify-center gap-2 sm:justify-end">
        <span
            x-show="appending"
            x-cloak
            class="inline-flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400"
        >
            <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-gray-300 border-t-brand-500 dark:border-gray-700"></span>
            {{ __('driver_check.list.loading') }}
        </span>

        <span x-show="moreError" x-cloak class="text-xs text-error-600 dark:text-error-400" x-text="moreError"></span>

        <button
            type="button"
            x-show="hasMore() && !appending"
            x-cloak
            x-on:click="moreError = null; loadMore()"
            class="dc-tap inline-flex h-10 items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 text-[13px]
                   font-medium text-gray-700 transition hover:border-gray-400 sm:h-8
                   dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-gray-600"
        >
            <x-driver-check.icon name="arrow-down" class="h-4 w-4" />
            <span x-text="moreError ? @js(__('telegram.ui.retry')) : @js(__('driver_check.list.more'))"></span>
        </button>

        <span
            x-show="!hasMore() && !appending && pagination.total > 0 && rows.length > 0 && pagination.last_page > 1"
            x-cloak
            class="text-xs text-gray-400 dark:text-gray-500"
        >{{ __('driver_check.list.end') }}</span>
    </div>

    {{-- Watched marker: when it nears the screen, the next page is asked for. --}}
    <div
        x-ref="moreSentinel"
        x-init="new IntersectionObserver((entries) => { if (entries[0].isIntersecting) { loadMore(); } }, { rootMargin: '320px' }).observe($el)"
        class="pointer-events-none absolute h-px w-px"
        aria-hidden="true"
    ></div>
</div>
