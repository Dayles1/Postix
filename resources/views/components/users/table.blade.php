@props([
    'users',
    'me' => auth()->user(),
    'canManageUsers' => false,
])

@php
    $firstItem = method_exists($users, 'firstItem') ? ($users->firstItem() ?? 0) : 0;
@endphp

<div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
    {{-- Desktop table --}}
    <div class="hidden xl:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-950">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="w-16 px-6 py-4">#</th>
                        <th class="px-6 py-4">{{ __('superadmin.users.name') }}</th>
                        <th class="px-6 py-4">{{ __('superadmin.users.email') }}</th>
                        <th class="px-6 py-4">{{ __('superadmin.users.permissions') }}</th>
                        <th class="w-[260px] px-6 py-4 text-center">{{ __('superadmin.users.actions') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                    @forelse($users as $user)
                        @php
                            $userHasNavUsers = $user?->hasPermission('nav:users') ?? false;
                            $canEdit = $canManageUsers && ! $userHasNavUsers;
                            $canDelete = $canManageUsers && $me?->id !== $user->id && ! $userHasNavUsers;
                        @endphp

                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-6 py-4 text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ $firstItem + $loop->index }}
                            </td>

                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ $user->name ?? '—' }}
                                </div>
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                {{ $user->email ?? '—' }}
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @forelse($user->permissions as $perm)
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                            {{ \App\Services\PermissionService::label($perm->key) }}
                                        </span>
                                    @empty
                                        <span class="text-sm text-gray-400">—</span>
                                    @endforelse
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center gap-2">
                                    <button
                                        type="button"
                                        @click="openView({{ $user->id }})"
                                        class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/5"
                                    >
                                        {{ __('superadmin.common.view') }}
                                    </button>

                                    @if($canEdit)
                                        <button
                                            type="button"
                                            @click="openEdit({{ $user->id }})"
                                            class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-indigo-700"
                                        >
                                            {{ __('superadmin.common.edit') }}
                                        </button>
                                    @endif

                                    @if($canDelete)
                                        <button
                                            type="button"
                                            @click="openDelete({{ $user->id }})"
                                            class="rounded-xl bg-red-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-red-700"
                                        >
                                            {{ __('superadmin.common.delete') }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center text-sm text-gray-400 dark:text-gray-500">
                                {{ __('superadmin.users.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Mobile cards --}}
    <div class="xl:hidden divide-y divide-gray-100 dark:divide-gray-800">
        @forelse($users as $user)
            @php
                $userHasNavUsers = $user?->hasPermission('nav:users') ?? false;
                $canEdit = $canManageUsers && ! $userHasNavUsers;
                $canDelete = $canManageUsers && $me?->id !== $user->id && ! $userHasNavUsers;
            @endphp

            <article class="bg-white p-5 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-gray-400">
                                #{{ $firstItem + $loop->index }}
                            </span>
                        </div>

                        <h4 class="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                            {{ $user->name ?? '—' }}
                        </h4>

                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $user->email ?? '—' }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @forelse($user->permissions as $perm)
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">
                            {{ \App\Services\PermissionService::label($perm->key) }}
                        </span>
                    @empty
                        <span class="text-sm text-gray-400">—</span>
                    @endforelse
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="openView({{ $user->id }})"
                        class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200"
                    >
                        {{ __('superadmin.common.view') }}
                    </button>

                    @if($canEdit)
                        <button
                            type="button"
                            @click="openEdit({{ $user->id }})"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-medium text-white"
                        >
                            {{ __('superadmin.common.edit') }}
                        </button>
                    @endif

                    @if($canDelete)
                        <button
                            type="button"
                            @click="openDelete({{ $user->id }})"
                            class="rounded-xl bg-red-600 px-4 py-2 text-xs font-medium text-white"
                        >
                            {{ __('superadmin.common.delete') }}
                        </button>
                    @endif
                </div>
            </article>
        @empty
            <div class="bg-white p-8 text-center text-sm text-gray-400 dark:bg-gray-900 dark:text-gray-500">
                {{ __('superadmin.users.empty') }}
            </div>
        @endforelse
    </div>
</div>