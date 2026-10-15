@props([
    'items' => 'periodPresets()',
    'selected' => 'periodPreset',
    'action' => 'setPeriod',
    'label' => null,
])

{{--
    A segmented control: the options sit in one tray and the chosen one is
    lifted out of it. Beats a <select> for 3-6 short options and keeps the
    current choice visible without opening anything.
--}}

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    @if ($label)
        <p class="mb-1.5 text-[12px] font-semibold text-gray-600 dark:text-gray-400">{{ $label }}</p>
    @endif

    <div class="dc-chips inline-flex max-w-full gap-0.5 overflow-x-auto rounded-lg bg-gray-100 p-0.5 dark:bg-white/[0.06]" role="group">
        <template x-for="item in {{ $items }}" :key="item.value">
            <button
                type="button"
                x-on:click="{{ $action }}(item.value)"
                :aria-pressed="{{ $selected }} === item.value"
                :class="{{ $selected }} === item.value
                    ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-700 dark:text-white'
                    : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'"
                class="dc-tap h-8 shrink-0 whitespace-nowrap rounded-md px-3 text-[13px] font-medium transition
                       outline-none focus-visible:ring-2 focus-visible:ring-brand-500/50 sm:h-7"
                x-text="item.label"
            ></button>
        </template>
    </div>
</div>
