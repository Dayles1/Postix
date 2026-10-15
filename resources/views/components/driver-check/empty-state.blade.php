@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
    'show' => '!loading && rows.length === 0 && !error',
])

<div x-show="{{ $show }}" x-cloak class="flex flex-col items-center px-6 py-16 text-center sm:py-24">
    <span
        class="flex h-11 w-11 items-center justify-center rounded-xl border border-dashed border-gray-300
               text-gray-400 dark:border-gray-700 dark:text-gray-500"
    >
        <x-driver-check.icon :name="$icon" class="h-5 w-5" />
    </span>

    <p class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">{{ $title }}</p>

    @if ($description)
        <p class="mt-1 max-w-sm text-[13px] leading-relaxed text-gray-500 dark:text-gray-400">
            {{ $description }}
        </p>
    @endif

    <div x-show="hasActiveFilters()" x-cloak class="mt-4">
        <x-driver-check.button size="sm" x-on:click="resetFilters()" icon="close">
            {{ __('telegram.ui.reset') }}
        </x-driver-check.button>
    </div>

    {{ $slot }}
</div>
