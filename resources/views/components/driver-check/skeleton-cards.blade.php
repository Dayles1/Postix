@props(['count' => 4])

{{-- Card placeholders for the mobile list. --}}

<template x-if="loading && rows.length === 0">
    <template x-for="i in {{ $count }}" :key="'skeleton-card-' + i">
        <div class="space-y-3 p-3.5">
            <div class="flex items-center gap-3">
                <div class="dc-skeleton h-10 w-10 shrink-0 rounded-full bg-gray-100 dark:bg-white/[0.06]"></div>

                <div class="flex-1 space-y-2">
                    <div class="dc-skeleton h-3 w-1/2 rounded bg-gray-100 dark:bg-white/[0.06]"></div>
                    <div class="dc-skeleton h-2.5 w-1/3 rounded bg-gray-100 dark:bg-white/[0.06]"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="dc-skeleton h-10 rounded-lg bg-gray-100 dark:bg-white/[0.06]"></div>
                <div class="dc-skeleton h-10 rounded-lg bg-gray-100 dark:bg-white/[0.06]"></div>
            </div>
        </div>
    </template>
</template>
