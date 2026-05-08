<div id="logModal" class="fixed inset-0 z-[99999] hidden">
    <div class="absolute inset-0 bg-black/50"></div>

    <div class="relative flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-4xl rounded-2xl bg-white shadow-xl dark:bg-gray-800">
            <div class="flex items-center justify-between border-b border-gray-300 px-4 py-3 dark:border-gray-700">
                <div>
                    <h3 id="logModalTitle" class="text-base font-semibold text-gray-900 dark:text-white"></h3>
                    <p id="logModalSubtitle" class="text-xs text-gray-600 dark:text-gray-300"></p>
                </div>

                <button type="button" data-close-log-modal
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-gray-900 hover:bg-gray-50 dark:border-gray-700 dark:text-white dark:hover:bg-gray-700">
                    ×
                </button>
            </div>

            <div id="logModalBody" class="max-h-[75vh] overflow-y-auto p-4 text-gray-900 dark:text-white"></div>
        </div>
    </div>
</div>