<tr class="hover:bg-gray-50/60 dark:hover:bg-gray-800/40">
    <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $log->id }}</td>

    <td class="px-4 py-3">
        <div class="text-sm font-medium text-gray-900 dark:text-white">
            {{ $log->created_at?->format('Y-m-d H:i') }}
        </div>
        
    </td>


    <td class="px-4 py-3">
        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset">
    {{ __('messages.log_actions.' . $log->action) }}
</span>
    </td>

    <td class="px-4 py-3">
        <div class="text-sm font-medium text-gray-900 dark:text-white">
            {{ $log->causer?->name ?? __('messages.logs.system') }}
        </div>
        <div class="text-xs text-gray-600 dark:text-gray-400">
            {{ $log->causer_id ? '#' . $log->causer_id : __('messages.logs.system') }}
        </div>
    </td>

    <td class="px-4 py-3">
        <button
            type="button"
            class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-900 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700"
            data-log-show
            data-url="{{ route('logs.show', $log->id) }}"
        >
            {{ __('messages.logs.view_changes') }}
        </button>
    </td>
</tr>