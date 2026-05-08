@php
    $messagesCollection = $operation->messages ?? collect();
    $fullMessage = $operation->message_text ?? $messagesCollection->first()?->message_text ?? '-';
@endphp

<div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 p-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ __('messages.op_show.message_preview') }}
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('messages.op_show.message_preview_subtitle') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button id="copyMessageBtn"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                <span>📋</span>
                <span>{{ __('messages.op_show.copy') }}</span>
            </button>

            <button id="toggleFullMessageBtn"
                data-expanded="false"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                <span>🔍</span>
                <span>{{ __('messages.op_show.expand') }}</span>
            </button>
        </div>
    </div>

    <div class="mt-4 border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-900/50 overflow-hidden">
        <pre id="fullMessageText"
             class="p-4 m-0 text-sm text-gray-800 dark:text-gray-100 whitespace-pre-wrap break-words max-h-44 overflow-auto">{{ $fullMessage }}</pre>
        <div id="fullMessageOverlay"
             class="relative -mt-11 h-11 bg-gradient-to-b from-transparent to-gray-50 dark:to-gray-900/50"></div>
    </div>
</div>