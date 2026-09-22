@props(['label'])

{{-- Label/value pair used inside the mobile cards. --}}

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <dt class="text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
        {{ $label }}
    </dt>

    <dd class="dc-break mt-0.5 text-[13px] leading-snug text-gray-700 dark:text-gray-200">
        {{ $slot }}
    </dd>
</div>
