<div
    x-show="deleteModalOpen"
    x-cloak
    x-transition.opacity
    class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/50 p-4"
>
    <div @click.outside="closeDelete()" class="w-full max-w-lg rounded-3xl bg-white p-5 shadow-2xl dark:bg-gray-900">
        <div class="mb-5">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ __('superadmin.common.delete') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                O‘chirishni tasdiqlang
            </p>
        </div>

        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-200">
            <div class="font-medium">
                <span x-text="deleteTarget?.name || '—'"></span>
            </div>
            <div class="mt-1 text-xs opacity-80" x-text="deleteTarget?.email || ''"></div>
        </div>

        <div class="mt-5 flex justify-end gap-3">
            <button type="button" @click="closeDelete()" class="rounded-2xl border border-gray-300 bg-white px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                {{ __('superadmin.common.cancel') }}
            </button>

            <button type="button" @click="confirmDelete()" class="rounded-2xl bg-red-600 px-5 py-3 text-sm font-semibold text-white hover:bg-red-700">
                {{ __('superadmin.common.delete') }}
            </button>
        </div>
    </div>
</div>