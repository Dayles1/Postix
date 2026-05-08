@props([
    'user',
    'canEdit' => false,
    'canEditRole' => false,
    'roles' => collect(),
    'userLimit' => 0,
    'canEditLimit' => false,
    'dU' => false,
])

@php
    $auth = auth()->user();
    $superadmin = $auth && $auth->role?->name === 'superadmin';
    $isSelf = $auth && $auth->id === $user->id;

    $nameValue = old('name', $user->name);
    $emailValue = old('email', $user->email);
    $telegramValue = old('telegram_id', $user->telegram_id);
    $roleValue = old('role_id', $user->role_id);
    $minutePackageValue = old('minute_package', $user->minuteAccess->is_active ?? false);
    $userLimitValue = old('user_limit', $userLimit ?? 0);
    $removeAvatarValue = old('remove_avatar', false);
@endphp

<div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800 bg-white dark:bg-gray-800">
    <form id="accountForm" action="{{ route('users.profile.update', $user->id) }}" enctype="multipart/form-data"
        method="POST" novalidate>
        @csrf

        <div class="mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ __('messages.account') }}
            </h3>
        </div>

        {{-- @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif --}}

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">

            <div>
                <label class="block text-sm text-gray-600 mb-1">{{ __('messages.users.name') }}</label>

                @if ($canEdit)
                    <input name="name" value="{{ $nameValue }}"
                        class="w-full rounded-md border px-3 py-2 text-sm @error('name') border-red-500 @enderror"
                        placeholder="{{ __('messages.users.name') }}">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600 flex items-center gap-1">
                            <span>⚠</span> {{ $message }}
                        </p>
                    @enderror
                @else
                    <input type="text" value="{{ $user->name }}" disabled
                        class="w-full rounded-md border px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-200">
                @endif
            </div>

            <div>
                <label class="block text-sm text-gray-600 mb-1">{{ __('messages.login.email') }}</label>

                @if ($canEdit)
                    <input name="email" type="email" value="{{ $emailValue }}"
                        class="w-full rounded-md border px-3 py-2 text-sm @error('email') border-red-500 @enderror"
                        placeholder="email@example.com">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600 flex items-center gap-1">
                            <span>⚠</span> {{ $message }}
                        </p>
                    @enderror
                @else
                    <input type="text" value="{{ $user->email ?? '—' }}" disabled
                        class="w-full rounded-md border px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-200">
                @endif
            </div>

            {{-- <div>
                <label class="block text-sm text-gray-600 mb-1">{{ __('messages.users.telegram_id') }}</label>

                @if ($canEdit)
                    <input name="telegram_id" value="{{ $telegramValue }}"
                        class="w-full rounded-md border px-3 py-2 text-sm @error('telegram_id') border-red-500 @enderror"
                        placeholder="{{ __('messages.users.telegram_id') }}">
                    @error('telegram_id')
                        <p class="mt-1 text-sm text-red-600 flex items-center gap-1">
    <span>⚠</span> {{ $message }}
</p>

                    @enderror
                @else
                    <input type="text" value="{{ $user->telegram_id ?? '—' }}" disabled
                        class="w-full rounded-md border px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-200">
                @endif
            </div> --}}

            @if (!$dU)
                <div>
                    <label class="block text-sm text-gray-600 mb-1">{{ __('messages.users.role') }}</label>

                    @if ($canEditRole)
                        <select name="role_id"
                            class="w-full rounded-md border px-3 py-2 text-sm @error('role_id') border-red-500 @enderror">
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}"
                                    {{ (string) $roleValue === (string) $role->id ? 'selected' : '' }}>
                                    {{ ucfirst($role->name) }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_id')
                            <p class="mt-1 text-sm text-red-600 flex items-center gap-1">
                                <span>⚠</span> {{ $message }}
                            </p>
                        @enderror
                    @else
                        <input type="text" value="{{ $user->role?->name ?? '—' }}" disabled
                            class="w-full rounded-md border px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-200">
                    @endif
                </div>
            @endif

            <div>
                <label class="block text-sm text-gray-600 mb-1">{{ __('messages.login.password') }}</label>

                @if ($canEdit)
                    <input type="password" name="password"
                        class="w-full rounded-md border px-3 py-2 text-sm @error('password') border-red-500 @enderror"
                        placeholder="{{ __('messages.login.password_placeholder') }}">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600 flex items-center gap-1">
                            <span>⚠</span> {{ $message }}
                        </p>
                    @enderror
                @else
                    <input type="text" value="{{ __('messages.users.read_only') }}" disabled
                        class="w-full rounded-md border px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-200">
                @endif
            </div>

            <div class="lg:col-span-2">
                <label class="block text-sm text-gray-600 mb-1">{{ __('messages.users.avatar') }}</label>

                @if ($canEdit)
                    <div class="flex flex-wrap items-center gap-3">
                        <input id="avatarInput" name="avatar" type="file" accept="image/*" class="hidden">
                        <button id="btnChooseAvatar" type="button" class="px-3 py-2 rounded-md border text-sm">
                            {{ __('messages.choose_file') }}
                        </button>

                        <label class="inline-flex items-center text-sm">
                            <input id="removeAvatar" type="checkbox" name="remove_avatar" value="1" class="mr-2"
                                {{ $removeAvatarValue ? 'checked' : '' }}>
                            {{ __('messages.remove') }}
                        </label>
                    </div>

                    @error('avatar')
                        <p class="mt-1 text-sm text-red-600 flex items-center gap-1">
                            <span>⚠</span> {{ $message }}
                        </p>
                    @enderror

                    <div id="avatarPreview" class="mt-3"></div>

                    <p class="mt-2 text-xs text-gray-500">{{ __('messages.avatar_hint') }}</p>
                @else
                    <input type="text" value="—" disabled
                        class="w-full rounded-md border px-3 py-2 text-sm bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-200">
                @endif
            </div>
        </div>

        @if ($superadmin)
            <div class="mt-4">
                <label class="inline-flex items-center text-sm">
                    <input type="hidden" name="minute_package" value="0">
                    <input type="checkbox" name="minute_package" value="1" class="mr-2"
                        {{ $minutePackageValue ? 'checked' : '' }}>
                    {{ __('messages.add_minute') }}
                </label>
                @error('minute_package')
                    <p class="mt-1 text-sm text-red-600 flex items-center gap-1">
                        <span>⚠</span> {{ $message }}
                    </p>
                @enderror
            </div>
        @endif

        @if (!$dU)
            @if (($canEditLimit ?? false) && $user->role?->name === 'admin')
                <div class="space-y-2 mt-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('messages.users.user_limit') }}
                    </label>

                    <input type="number" name="user_limit" min="0" value="{{ $userLimitValue }}"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white @error('user_limit') border-red-500 @enderror">

                    @error('user_limit')
                        <p class="text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <div
                    class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-gray-900 dark:text-gray-300">
                    {{ __('messages.users.user_limit') }}: {{ $userLimitValue ?? 0 }}
                </div>
            @endif
        @endif

        <div class="mt-4">
            @if ($canEdit)
                <button type="submit" class="w-full rounded-2xl bg-green-600 px-4 py-2 text-white font-medium">
                    {{ __('messages.users.save_changes') }}
                </button>
            @endif
        </div>
    </form>
</div>

<script>
    (function() {
        const form = document.getElementById('accountForm');
        if (!form) return;

        const avatarInput = document.getElementById('avatarInput');
        const btnChoose = document.getElementById('btnChooseAvatar');
        const preview = document.getElementById('avatarPreview');

        btnChoose?.addEventListener('click', (e) => {
            e.preventDefault();
            avatarInput?.click();
        });

        avatarInput?.addEventListener('change', function() {
            preview.innerHTML = '';
            const f = this.files?.[0];
            if (!f) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                preview.innerHTML =
                    `<img src="${e.target.result}" class="h-24 w-24 rounded-full object-cover border">`;
            };
            reader.readAsDataURL(f);
        });

        // scroll positionni saqlash
        window.addEventListener('beforeunload', () => {
            localStorage.setItem('scrollY', window.scrollY);
        });

        // qayta tiklash
        window.addEventListener('load', () => {
            const scrollY = localStorage.getItem('scrollY');
            if (scrollY !== null) {
                window.scrollTo(0, parseInt(scrollY));
                localStorage.removeItem('scrollY');
            }
        });

    })();
</script>
