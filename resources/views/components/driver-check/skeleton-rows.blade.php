@props(['cols' => 6, 'count' => 6])

{{-- Table placeholders. Only shown for the very first load, never on refetch. --}}

<template x-if="loading && rows.length === 0">
    <template x-for="i in {{ $count }}" :key="'skeleton-row-' + i">
        <tr>
            <td colspan="{{ $cols }}" class="px-4 py-3.5">
                <div class="flex items-center gap-3">
                    <div class="dc-skeleton h-9 w-9 shrink-0 rounded-full bg-gray-100 dark:bg-white/[0.06]"></div>

                    <div class="flex-1 space-y-2">
                        <div class="dc-skeleton h-3 w-2/5 rounded bg-gray-100 dark:bg-white/[0.06]"></div>
                        <div class="dc-skeleton h-2.5 w-1/4 rounded bg-gray-100 dark:bg-white/[0.06]"></div>
                    </div>

                    <div class="dc-skeleton hidden h-3 w-16 rounded bg-gray-100 sm:block dark:bg-white/[0.06]"></div>
                    <div class="dc-skeleton hidden h-3 w-24 rounded bg-gray-100 lg:block dark:bg-white/[0.06]"></div>
                </div>
            </td>
        </tr>
    </template>
</template>
