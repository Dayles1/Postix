@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
    'show' => '!loading && rows.length === 0 && !error',
])

<div x-show="{{ $show }}" x-cloak class="flex flex-col items-center px-6 py-14 text-center sm:py-20">
    <span
        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100
               text-gray-400 dark:bg-white/[0.06] dark:text-gray-500"
    >
        <x-driver-check.icon :name="$icon" class="h-6 w-6" />
    </span>

    <p class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</p>

    @if ($description)
        <p class="mt-1 max-w-sm text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
            {{ $description }}
        </p>
    @endif

    <div
        x-show="hasActiveFilters()"
        x-cloak
        class="mt-4"
    >
        <x-driver-check.button size="sm" x-on:click="resetFilters()" icon="close">
            {{ __('telegram.ui.reset') }}
        </x-driver-check.button>
    </div>

    {{ $slot }}
</div>
