@props([
    'icon' => null,
    'title',
    'description' => null,
    'eyebrow' => null,
    'tone' => null,
])

{{--
    The title bar of a page: the name, one quiet line under it, the actions
    on the right. The section the page belongs to is already in the top bar,
    so there is no eyebrow and no icon tile here (`icon`, `eyebrow` and `tone`
    are accepted for the pages that still pass them and ignored).

    Actions keep one order everywhere: the secondary ones first, the primary
    one last, at the right edge.
--}}

<header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <h1 class="truncate text-[22px] font-semibold leading-7 tracking-[-0.015em] text-gray-950 sm:text-2xl sm:leading-8 dark:text-white">
            {{ $title }}
        </h1>

        @if ($description)
            <p class="mt-1 max-w-3xl text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                {{ $description }}
            </p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 sm:shrink-0 sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</header>
