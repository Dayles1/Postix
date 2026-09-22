@props([
    'items' => 'periodPresets()',
    'selected' => 'periodPreset',
    'action' => 'setPeriod',
    'label' => null,
])

{{--
    A swipeable row of choices. Beats a <select> for 3-5 short options and
    keeps the current period visible without opening anything.
--}}

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    @if ($label)
        <p class="mb-1.5 text-xs font-medium text-gray-600 dark:text-gray-400">{{ $label }}</p>
    @endif

    <div class="dc-chips -mx-0.5 flex gap-1.5 overflow-x-auto px-0.5 py-0.5" role="group">
        <template x-for="item in {{ $items }}" :key="item.value">
            <button
                type="button"
                x-on:click="{{ $action }}(item.value)"
                :aria-pressed="{{ $selected }} === item.value"
                :class="{{ $selected }} === item.value
                    ? 'bg-brand-500 text-white border-brand-500 shadow-sm shadow-brand-500/25'
                    : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.07]'"
                class="dc-tap h-9 shrink-0 whitespace-nowrap rounded-full border px-3.5 text-[13px]
                       font-medium transition outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20"
                x-text="item.label"
            ></button>
        </template>
    </div>
</div>
