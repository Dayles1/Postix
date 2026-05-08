<div 
    x-show="viewModalOpen"
    x-cloak
    x-transition.opacity
    class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/60 p-3 sm:p-4"
>
    <div 
        @click.outside="closeView()" 
        class="w-full max-w-4xl rounded-3xl bg-white shadow-2xl dark:bg-gray-900 
               max-h-[90vh] overflow-y-auto"
    >
        <!-- HEADER -->
        <div class="sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div>
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-white">
                    <span x-text="modalMode === 'edit' ? '{{ __('superadmin.common.edit') }}' : '{{ __('superadmin.common.view') }}'"></span>
                </h2>
                <p class="mt-1 text-xs sm:text-sm text-gray-500 dark:text-gray-400" x-text="currentUser?.email || ''"></p>
            </div>

            <button type="button" @click="closeView()" class="text-gray-400 hover:text-gray-600 text-lg">
                ✕
            </button>
        </div>

        <!-- LOADING -->
        <template x-if="loadingUser">
            <div class="py-12 text-center text-gray-500 text-sm">
                {{ __('superadmin.common.loading') ?? 'Loading...' }}
            </div>
        </template>

        <!-- VIEW MODE -->
        <template x-if="!loadingUser && currentUser && modalMode === 'view'">
            <div class="p-4 sm:p-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-gray-500">{{ __('superadmin.users.name') }}</label>
                        <div class="mt-1 rounded-xl border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:text-gray-200" x-text="currentUser.name || '—'"></div>
                    </div>

                    <div>
                        <label class="text-xs text-gray-500">{{ __('superadmin.users.email') }}</label>
                        <div class="mt-1 rounded-xl border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:text-gray-200" x-text="currentUser.email || '—'"></div>
                    </div>
                </div>

                <!-- PERMISSIONS -->
                <div class="mt-5 rounded-2xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="mb-3 text-sm font-semibold text-gray-800 dark:text-white">
                        {{ __('superadmin.users.permissions') }}
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <template x-for="perm in (currentUser.permissions || [])" :key="perm">
                            <span 
                                class="rounded-full bg-indigo-50 px-3 py-1 text-xs text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300"
                                x-text="getPermissionLabel(perm)"
                            ></span>
                        </template>
                    </div>
                </div>

                <!-- ACTIONS -->
                <div class="mt-5 flex flex-col sm:flex-row justify-end gap-2">
                    <button @click="closeView()" class="w-full sm:w-auto rounded-xl border px-4 py-2 text-sm">
                        {{ __('superadmin.common.close') }}
                    </button>

                    <button 
                        x-show="canEditTarget(currentUser)"
                        @click="openEdit(currentUser.id)"
                        class="w-full sm:w-auto rounded-xl bg-indigo-600 px-4 py-2 text-sm text-white"
                    >
                        {{ __('superadmin.common.edit') }}
                    </button>

                    <button 
                        x-show="canDeleteTarget(currentUser)"
                        @click="closeView(); openDelete(currentUser.id)"
                        class="w-full sm:w-auto rounded-xl bg-red-600 px-4 py-2 text-sm text-white"
                    >
                        {{ __('superadmin.common.delete') }}
                    </button>
                </div>
            </div>
        </template>

        <!-- EDIT MODE -->
        <template x-if="!loadingUser && currentUser && modalMode === 'edit'">
            <div class="p-4 sm:p-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <!-- NAME -->
                    <div>
                        <label class="text-xs text-gray-500">{{ __('superadmin.users.name') }}</label>
                        <input
                            type="text"
                            x-model="editForm.name"
                            :class="editErrors.name ? 'border-red-500' : ''"
                            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm dark:bg-gray-800"
                        >
                        <p class="text-xs text-red-500 mt-1" x-text="editErrors.name?.[0]"></p>
                    </div>

                    <!-- EMAIL -->
                    <div>
                        <label class="text-xs text-gray-500">{{ __('superadmin.users.email') }}</label>
                        <input
                            type="email"
                            x-model="editForm.email"
                            :class="editErrors.email ? 'border-red-500' : ''"
                            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm dark:bg-gray-800"
                        >
                        <p class="text-xs text-red-500 mt-1" x-text="editErrors.email?.[0]"></p>
                    </div>

                    <!-- PASSWORD -->
                    <div class="md:col-span-2">
                        <label class="text-xs text-gray-500">{{ __('messages.login.password') }}</label>
                        <input
                            type="password"
                            x-model="editForm.password"
                            :class="editErrors.password ? 'border-red-500' : ''"
                            class="mt-1 w-full rounded-xl border px-3 py-2 text-sm dark:bg-gray-800"
                        >
                        <p class="text-xs text-gray-400 mt-1">
                            {{ __('superadmin.common.optional') ?? 'Optional' }}
                        </p>
                        <p class="text-xs text-red-500 mt-1" x-text="editErrors.password?.[0]"></p>
                    </div>
                </div>

                <!-- PERMISSIONS -->
                <div class="mt-5 rounded-2xl border p-4 dark:border-gray-700">
                    <div class="mb-3 text-sm font-semibold">
                        {{ __('superadmin.users.permissions') }}
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($permissionsList as $perm)
                            <label class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm">
                                <input
                                    type="checkbox"
                                    :checked="editForm.permissions.includes(@js($perm['key']))"
                                    @change="togglePermission('editForm', @js($perm['key']), $event.target.checked)"
                                >
                                <span>{{ $perm['text'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- ACTIONS -->
                <div class="mt-5 flex flex-col sm:flex-row justify-end gap-2">
                    <button @click="closeView()" class="w-full sm:w-auto rounded-xl border px-4 py-2 text-sm">
                        {{ __('superadmin.common.cancel') }}
                    </button>

                    <button @click="submitUpdate()" class="w-full sm:w-auto rounded-xl bg-indigo-600 px-4 py-2 text-sm text-white">
                        {{ __('superadmin.common.save') }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>