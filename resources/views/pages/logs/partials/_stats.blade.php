@php
    $totalLogs = method_exists($activities, 'total') ? $activities->total() : count($activities);
    $createdCount = \Spatie\Activitylog\Models\Activity::query()->where('event', 'created')->count();
    $updatedCount = \Spatie\Activitylog\Models\Activity::query()->where('event', 'updated')->count();
    $deletedCount = \Spatie\Activitylog\Models\Activity::query()->where('event', 'deleted')->count();
@endphp

<div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
    <div class="rounded-2xl border border-gray-300 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ __('messages.logs.stats.total') }}</div>
        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $totalLogs }}</div>
    </div>

    <div class="rounded-2xl border border-gray-300 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ __('messages.logs.stats.created') }}</div>
        <div class="mt-1 text-lg font-semibold text-green-700 dark:text-green-400">{{ $createdCount }}</div>
    </div>

    <div class="rounded-2xl border border-gray-300 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ __('messages.logs.stats.updated') }}</div>
        <div class="mt-1 text-lg font-semibold text-yellow-700 dark:text-yellow-400">{{ $updatedCount }}</div>
    </div>

    <div class="rounded-2xl border border-gray-300 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="text-[10px] uppercase tracking-wide text-gray-600 dark:text-gray-300">{{ __('messages.logs.stats.deleted') }}</div>
        <div class="mt-1 text-lg font-semibold text-red-700 dark:text-red-400">{{ $deletedCount }}</div>
    </div>
</div>