@php
    $selectedSearch = request('search');
    $selectedLogName = request('log_name');
    $selectedEvent = request('event');
    $selectedSubjectType = request('subject_type');
    $selectedFrom = request('from');
    $selectedTo = request('to');
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <form method="GET" action="{{ route('logs.index') }}" class="grid grid-cols-1 gap-3 xl:grid-cols-12">
        {{-- Search --}}
        <div class="xl:col-span-3">
            <label class="mb-1.5 block text-xs font-medium text-gray-900 dark:text-white">{{ __('messages.operations.btn_search') }}</label>
            <input
                type="text"
                name="search"
                value="{{ $selectedSearch }}"
                placeholder="{{ __('messages.logs.search_placeholder') }}"
                class="w-full rounded-xl border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-primary dark:border-gray-700 dark:text-white dark:focus:border-primary"
            >
        </div>

        {{-- Log Name --}}
        <div class="xl:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-gray-900 dark:text-white">{{ __('messages.logs.log_name') }}</label>
            <select name="log_name" class="w-full rounded-xl border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-primary">
                <option value="">{{ __('messages.operations.filter_all_status') }}</option>
                @foreach($logNames as $logName)
                    <option value="{{ $logName }}" @selected($selectedLogName === $logName)>{{ $logName }}</option>
                @endforeach
            </select>
        </div>

        {{-- Event --}}
        <div class="xl:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-gray-900 dark:text-white">{{ __('messages.logs.event') }}</label>
            <select name="event" class="w-full rounded-xl border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-primary">
                <option value="">{{ __('messages.operations.filter_all_status') }}</option>
                @foreach($events as $event)
                    <option value="{{ $event }}" @selected($selectedEvent === $event)>{{ __('messages.logs.events.' . $event) ?? $event }}</option>
                @endforeach
            </select>
        </div>

        {{-- Subject Type --}}
        <div class="xl:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-gray-900 dark:text-white">{{ __('messages.logs.subject_type') }}</label>
            <select name="subject_type" class="w-full rounded-xl border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-primary">
                <option value="">{{ __('messages.operations.filter_all_status') }}</option>
                @foreach($subjectTypes as $subjectType)
                    <option value="{{ $subjectType }}" @selected($selectedSubjectType === $subjectType)>{{ class_basename($subjectType) }}</option>
                @endforeach
            </select>
        </div>

        {{-- From Date --}}
        <div class="xl:col-span-1">
            <label class="mb-1.5 block text-xs font-medium text-gray-900 dark:text-white">{{ __('messages.logs.from') }}</label>
            <input type="text" name="from" value="{{ $selectedFrom }}" data-datepicker autocomplete="off" class="w-full rounded-xl border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-primary">
        </div>

        {{-- To Date --}}
        <div class="xl:col-span-1">
            <label class="mb-1.5 block text-xs font-medium text-gray-900 dark:text-white">{{ __('messages.logs.to') }}</label>
            <input type="text" name="to" value="{{ $selectedTo }}" data-datepicker autocomplete="off" class="w-full rounded-xl border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-primary dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:border-primary">
        </div>

        {{-- Submit Button --}}
        <div class="flex items-end xl:col-span-2">
            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-opacity-90">
                {{ __('messages.common.filter') }}
            </button>
        </div>
    </form>
</div>