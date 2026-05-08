<div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white md:text-xl">
                {{ __('messages.logs.title') }}
            </h1>
            <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300 md:text-sm">
                {{ __('messages.logs.subtitle') }}
            </p>
        </div>

        <a href="{{ route('logs.index') }}"
           class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-900 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">
            {{ __('messages.reset') }}
        </a>
    </div>
</div>