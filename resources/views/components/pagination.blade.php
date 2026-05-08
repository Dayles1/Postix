@php
    $total = $paginator->lastPage();
    $current = $paginator->currentPage();
    $start = max($current - 2, 1);
    $end = min($current + 2, $total);
@endphp

<div class="px-6 py-4 border-t flex flex-col sm:flex-row items-center justify-between gap-4 dark:border-gray-700">
    <div class="text-sm text-gray-600 dark:text-gray-400">
        {{ __('messages.catalogs.showing', ['from' => $paginator->firstItem() ?? 0, 'to' => $paginator->lastItem() ?? 0, 'total' => $paginator->total()]) }}
    </div>

    <div class="flex items-center gap-1">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="px-3 py-1 text-gray-400">{{ __('messages.previous') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="px-3 py-1 border rounded">{{ __('messages.previous') }}</a>
        @endif

        {{-- First pages --}}
        @if($start > 1)
            <a href="{{ $paginator->url(1) }}" class="px-3 py-1 border rounded">1</a>
            @if($start > 2) <span class="px-1">...</span> @endif
        @endif

        {{-- Middle pages --}}
        @for ($i = $start; $i <= $end; $i++)
            @if ($i == $current)
                <span class="px-3 py-1 border rounded bg-gray-300 dark:bg-gray-700">{{ $i }}</span>
            @else
                <a href="{{ $paginator->url($i) }}" class="px-3 py-1 border rounded">{{ $i }}</a>
            @endif
        @endfor

        {{-- Last pages --}}
        @if($end < $total)
            @if($end < $total - 1) <span class="px-1">...</span> @endif
            <a href="{{ $paginator->url($total-1) }}" class="px-3 py-1 border rounded">{{ $total-1 }}</a>
            <a href="{{ $paginator->url($total) }}" class="px-3 py-1 border rounded">{{ $total }}</a>
        @endif

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="px-3 py-1 border rounded">{{ __('messages.next') }}</a>
        @else
            <span class="px-3 py-1 text-gray-400">{{ __('messages.next') }}</span>
        @endif
    </div>
</div>