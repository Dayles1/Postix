<div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 p-4">
    <div class="flex flex-col sm:flex-row gap-3">
        <div class="flex items-center gap-3 w-full">
            <label class="text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">
                {{ __('messages.table.status') }}:
            </label>

            <select id="status-filter"
                    class="px-3 py-2 border rounded-lg text-sm bg-gray-50 dark:bg-gray-700 dark:border-gray-600 flex-1 text-gray-800 dark:text-gray-100">
                <option value="">{{ __('messages.op_show.all_statuses') }}</option>
                @foreach($statusMap as $key => $label)
                    <option value="{{ $key }}" @selected($currentStatus === $key)>{{ $label }}</option>
                @endforeach
            </select>

            <input type="text"
                   id="peer-search"
                   placeholder="{{ __('messages.op_show.search_peer_placeholder') }}"
                   class="px-3 py-2 border rounded-lg text-sm w-full sm:w-auto bg-gray-50 dark:bg-gray-700 dark:border-gray-600 text-gray-800 dark:text-gray-100"
                   style="flex:1;" />
        </div>
    </div>
</div>