<div
    x-show="createModalOpen"
    x-cloak
    x-transition.opacity
    class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/50 p-4"
>
    <div 
        @click.outside="closeCreate()"
        class="w-full max-w-3xl rounded-t-3xl sm:rounded-3xl bg-white dark:bg-gray-900 shadow-2xl
               max-h-[95vh] overflow-hidden flex flex-col"
    >

        <!-- HEADER -->
        <div class="flex items-center justify-between p-4 sm:p-5 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-white">
                    {{ __('superadmin.common.create') }}
                </h2>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                    {{ __('superadmin.users.title') }}
                </p>
            </div>

            <button type="button" @click="closeCreate()" class="text-gray-400 hover:text-gray-600 text-lg">
                ✕
            </button>
        </div>

        <!-- BODY -->
        <div class="overflow-y-auto p-4 sm:p-5 space-y-5">

            <!-- FORM -->
            <div class="grid gap-4 sm:grid-cols-2">
                
                <!-- NAME -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('superadmin.users.name') }}
                    </label>
                    <input
                        type="text"
                        x-model="createForm.name"
                        :class="createErrors.name ? 'border-red-500 focus:border-red-500' : 'border-gray-200 focus:border-indigo-500'"
                        class="w-full rounded-2xl bg-white px-4 py-3 text-sm outline-none border dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                    <p class="mt-1 text-xs text-red-500" x-text="createErrors.name?.[0]"></p>
                </div>

                <!-- EMAIL -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('superadmin.users.email') }}
                    </label>
                    <input
                        type="email"
                        x-model="createForm.email"
                        :class="createErrors.email ? 'border-red-500 focus:border-red-500' : 'border-gray-200 focus:border-indigo-500'"
                        class="w-full rounded-2xl bg-white px-4 py-3 text-sm outline-none border dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                    <p class="mt-1 text-xs text-red-500" x-text="createErrors.email?.[0]"></p>
                </div>

                <!-- PASSWORD -->
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('messages.login.password') }}
                    </label>
                    <input
                        type="password"
                        x-model="createForm.password"
                        :class="createErrors.password ? 'border-red-500 focus:border-red-500' : 'border-gray-200 focus:border-indigo-500'"
                        class="w-full rounded-2xl bg-white px-4 py-3 text-sm outline-none border dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    >
                    <p class="mt-1 text-xs text-red-500" x-text="createErrors.password?.[0]"></p>
                </div>
            </div>

            <!-- PERMISSIONS -->
            <div class="rounded-3xl border border-gray-200 p-4 dark:border-gray-700">
                <div class="mb-3 text-sm font-semibold text-gray-800 dark:text-white">
                    {{ __('superadmin.users.permissions') }}
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($permissionsList as $perm)
                        <label class="flex items-center gap-3 rounded-2xl border px-4 py-3 cursor-pointer transition
                                      border-gray-200 dark:border-gray-700
                                      hover:bg-gray-50 dark:hover:bg-gray-800"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-gray-300"
                                :checked="createForm.permissions.includes(@js($perm['key']))"
                                :disabled="createForm.permissions.includes('nav:users') && @js($perm['key']) !== 'nav:users'"
                                @change="
                                    togglePermission('createForm', @js($perm['key']), $event.target.checked);

                                    if (@js($perm['key']) === 'nav:users') {
                                        createForm.permissions = $event.target.checked 
                                            ? permissionsAll.map(p => p.key)
                                            : [];
                                    }
                                "
                            >

                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $perm['text'] }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="p-4 sm:p-5 border-t border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row gap-3 sm:justify-end">
            <button
                type="button"
                @click="closeCreate()"
                class="w-full sm:w-auto rounded-2xl border border-gray-300 bg-white px-5 py-3 text-sm font-medium text-gray-700 
                       dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            >
                {{ __('superadmin.common.cancel') }}
            </button>

            <button
                type="button"
                @click="submitCreate()"
                class="w-full sm:w-auto rounded-2xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700"
            >
                {{ __('superadmin.common.save') }}
            </button>
        </div>
    </div>
</div>