@props([
    'icon' => 'users',
    'title',
    'description' => null,
    'eyebrow' => null,
    'tone' => 'brand',
])

@php
    $tones = [
        'brand' => 'bg-brand-500 text-white shadow-brand-500/25',
        'success' => 'bg-success-500 text-white shadow-success-500/25',
        'blue' => 'bg-blue-light-500 text-white shadow-blue-light-500/25',
        'warning' => 'bg-warning-500 text-white shadow-warning-500/25',
    ];
@endphp

<header class="flex flex-col gap-4">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            <span
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl shadow-lg
                       {{ $tones[$tone] ?? $tones['brand'] }}"
            >
                <x-driver-check.icon :name="$icon" class="h-5 w-5" />
            </span>

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <h1 class="text-lg font-semibold tracking-tight text-gray-900 sm:text-xl lg:text-2xl dark:text-white">
                        {{ $title }}
                    </h1>

                    @if ($eyebrow)
                        <span
                            class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[10px]
                                   font-semibold uppercase tracking-wider text-gray-500
                                   dark:border-gray-800 dark:bg-white/[0.04] dark:text-gray-400"
                        >
                            {{ $eyebrow }}
                        </span>
                    @endif
                </div>

                @if ($description)
                    <p class="mt-1 max-w-2xl text-[13px] leading-relaxed text-gray-500 sm:text-sm dark:text-gray-400">
                        {{ $description }}
                    </p>
                @endif
            </div>
        </div>

        {{--
            Actions go full width on a phone (thumb-sized rows), then collapse
            into a normal button group from sm up.
        --}}
        @isset($actions)
            <div class="grid grid-cols-2 gap-2 sm:flex sm:shrink-0 sm:flex-wrap sm:items-center sm:justify-end">
                {{ $actions }}
            </div>
        @endisset
    </div>
</header>
