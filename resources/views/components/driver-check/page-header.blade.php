@props([
    'icon' => 'users',
    'title',
    'description' => null,
    'eyebrow' => null,
    'tone' => 'brand',
])

@php
    /*
     * A gradient tile with a soft halo: the one bit of colour every page
     * header carries, so the pages tell themselves apart at a glance
     * without any of them shouting.
     */
    $tones = [
        'brand' => 'from-brand-400 to-brand-600 shadow-brand-500/30 ring-brand-500/10',
        'success' => 'from-success-400 to-success-600 shadow-success-500/30 ring-success-500/10',
        'blue' => 'from-blue-light-400 to-blue-light-600 shadow-blue-light-500/30 ring-blue-light-500/10',
        'warning' => 'from-warning-400 to-warning-600 shadow-warning-500/30 ring-warning-500/10',
        'error' => 'from-error-400 to-error-600 shadow-error-500/30 ring-error-500/10',
    ];
@endphp

<header class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div class="flex min-w-0 items-center gap-3.5 sm:gap-4">
        <span
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-white
                   shadow-lg ring-4 {{ $tones[$tone] ?? $tones['brand'] }}"
        >
            <x-driver-check.icon :name="$icon" class="h-[22px] w-[22px]" />
        </span>

        <div class="min-w-0">
            {{-- Where the page sits: the menu group it belongs to --}}
            @if ($eyebrow)
                <p class="mb-0.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-brand-500 dark:text-brand-400">
                    {{ $eyebrow }}
                </p>
            @endif

            <h1 class="truncate text-xl font-semibold tracking-tight text-gray-900 sm:text-2xl dark:text-white">
                {{ $title }}
            </h1>

            @if ($description)
                <p class="mt-1 max-w-3xl text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
                    {{ $description }}
                </p>
            @endif
        </div>
    </div>

    {{--
        Actions go full width on a phone (thumb-sized rows), then sit in one
        row of same-height buttons, centred on the title block.
    --}}
    @isset($actions)
        <div class="grid grid-cols-2 gap-2 sm:flex sm:shrink-0 sm:flex-wrap sm:items-center sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</header>
