@extends('layouts.driver-check')

@section('title', __('driver_check.profile.title'))

@section('content')

@php
    $initial = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N} ]/u', '', $user->name ?? '') ?: 'U', 0, 1));
    $locale = app()->getLocale();
    $passwordErrors = $errors->getBag('password');
@endphp

<x-driver-check.shell>
    <x-driver-check.page-header
        :title="__('driver_check.profile.title')"
        :description="__('driver_check.profile.subtitle')"
    />

    <div
        class="grid items-start gap-5 lg:grid-cols-[320px_minmax(0,1fr)]"
        x-data="{
            preview: @js($user->avatar?->path ? asset('storage/' . $user->avatar->path) : null),
            removeAvatar: false,
            pick(event) {
                const file = event.target.files?.[0];

                if (! file) return;

                this.removeAvatar = false;
                this.preview = URL.createObjectURL(file);
            },
            clear() {
                this.removeAvatar = true;
                this.preview = null;
                this.$refs.file.value = '';
            },
        }"
    >
        {{-- ============ Identity ============ --}}
        <aside class="dc-surface overflow-hidden rounded-2xl lg:sticky lg:top-[calc(var(--dc-bar-h)+var(--dc-sub-h)+20px)]">
            <div class="h-16 bg-[var(--dc-ink)]" style="background-image: radial-gradient(120% 140% at 100% 0%, rgb(31 162 179 / .45), transparent 60%)"></div>

            <div class="px-5 pb-5">
                <div class="-mt-9 flex items-end justify-between">
                    <template x-if="preview">
                        <img :src="preview" alt="" class="h-[72px] w-[72px] rounded-2xl border-4 border-white object-cover dark:border-[var(--dc-surface)]">
                    </template>
                    <template x-if="! preview">
                        <span class="grid h-[72px] w-[72px] place-items-center rounded-2xl border-4 border-white bg-gray-900 text-2xl font-bold text-white dark:border-[var(--dc-surface)] dark:bg-white dark:text-gray-900">{{ $initial }}</span>
                    </template>
                </div>

                <h2 class="mt-3 truncate text-lg font-semibold tracking-tight text-gray-950 dark:text-white">{{ $user->name }}</h2>
                <p class="truncate text-[13px] text-gray-500">{{ $user->email }}</p>

                <dl class="mt-5 divide-y divide-gray-100 border-t border-gray-100 text-[13px] dark:divide-gray-800 dark:border-gray-800">
                    <div class="flex items-center justify-between py-2.5">
                        <dt class="text-gray-500">{{ __('driver_check.profile.role') }}</dt>
                        <dd class="rounded-md bg-brand-50 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">{{ $user->role->name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between py-2.5">
                        <dt class="text-gray-500">{{ __('driver_check.profile.member_since') }}</dt>
                        <dd class="font-medium tabular-nums text-gray-800 dark:text-gray-200">{{ optional($user->created_at)->format('d.m.Y') ?? '—' }}</dd>
                    </div>
                </dl>

                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <x-driver-check.button type="submit" icon="logout" class="w-full">
                        {{ __('driver_check.profile.logout') }}
                    </x-driver-check.button>
                </form>
            </div>
        </aside>

        {{-- ============ Settings ============ --}}
        <div class="flex min-w-0 flex-col gap-5">

            {{-- Account --}}
            <form method="POST" action="{{ route('driver-check.profile.update') }}" enctype="multipart/form-data">
                @csrf

                <x-driver-check.surface class="overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('driver_check.profile.account') }}</h2>
                    </div>

                    <div class="grid gap-4 p-5 sm:grid-cols-2">
                        <x-driver-check.field :label="__('driver_check.profile.name')">
                            <x-driver-check.input name="name" :value="old('name', $user->name)" required maxlength="255" :data-invalid="$errors->has('name') ? '' : null" />
                            @error('name')<p class="mt-1.5 text-xs text-error-600">{{ $message }}</p>@enderror
                        </x-driver-check.field>

                        <x-driver-check.field :label="__('driver_check.profile.email')">
                            <x-driver-check.input type="email" name="email" :value="old('email', $user->email)" maxlength="255" :data-invalid="$errors->has('email') ? '' : null" />
                            @error('email')<p class="mt-1.5 text-xs text-error-600">{{ $message }}</p>@enderror
                        </x-driver-check.field>

                        <div class="sm:col-span-2">
                            <p class="mb-1.5 text-[12px] font-semibold text-gray-600 dark:text-gray-400">{{ __('driver_check.profile.photo') }}</p>

                            <div class="flex flex-wrap items-center gap-2">
                                <input x-ref="file" type="file" name="avatar" accept="image/*" class="sr-only" id="dc-avatar" x-on:change="pick($event)">
                                <input type="hidden" name="remove_avatar" :value="removeAvatar ? 1 : 0">

                                <label for="dc-avatar" class="dc-tap inline-flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 text-[13px] font-medium text-gray-800 transition hover:border-gray-400 hover:bg-gray-50 sm:h-9 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-100">
                                    <x-driver-check.icon name="upload" class="h-4 w-4" />
                                    {{ __('driver_check.profile.photo_change') }}
                                </label>

                                <x-driver-check.button variant="ghost" icon="trash" x-show="preview" x-cloak x-on:click="clear()">
                                    {{ __('driver_check.profile.photo_remove') }}
                                </x-driver-check.button>

                                <span class="text-[11.5px] text-gray-400">{{ __('driver_check.profile.photo_hint') }}</span>
                            </div>
                            @error('avatar')<p class="mt-1.5 text-xs text-error-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 bg-gray-25 px-5 py-3 dark:border-gray-800 dark:bg-white/[0.015]">
                        <x-driver-check.button type="submit" variant="primary">{{ __('driver_check.profile.save') }}</x-driver-check.button>
                    </div>
                </x-driver-check.surface>
            </form>

            {{-- Appearance: applied at once, kept in this browser --}}
            <x-driver-check.surface class="overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('driver_check.profile.preferences') }}</h2>
                </div>

                <div class="grid gap-5 p-5 sm:grid-cols-2">
                    <div>
                        <p class="mb-1.5 text-[12px] font-semibold text-gray-600 dark:text-gray-400">{{ __('driver_check.profile.language') }}</p>
                        <div class="inline-flex gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-white/[0.06]">
                            @foreach (['uz' => 'O\'zbekcha', 'ru' => 'Русский', 'en' => 'English'] as $code => $label)
                                <a href="{{ url('/lang/' . $code) }}" class="h-8 rounded-md px-3 text-[13px] font-medium leading-8 transition sm:h-7 sm:leading-7 {{ $locale === $code ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}">{{ $label }}</a>
                            @endforeach
                        </div>
                    </div>

                    <div x-data>
                        <p class="mb-1.5 text-[12px] font-semibold text-gray-600 dark:text-gray-400">{{ __('driver_check.profile.theme') }}</p>
                        <div class="inline-flex gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-white/[0.06]">
                            <button type="button" x-on:click="$store.dcTheme.theme === 'dark' && $store.dcTheme.toggle()" :class="$store.dcTheme.theme === 'light' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'" class="inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-[13px] font-medium transition sm:h-7">
                                <x-driver-check.icon name="sun" class="h-3.5 w-3.5" /> {{ __('driver_check.profile.theme_light') }}
                            </button>
                            <button type="button" x-on:click="$store.dcTheme.theme === 'light' && $store.dcTheme.toggle()" :class="$store.dcTheme.theme === 'dark' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'" class="inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-[13px] font-medium transition sm:h-7">
                                <x-driver-check.icon name="moon" class="h-3.5 w-3.5" /> {{ __('driver_check.profile.theme_dark') }}
                            </button>
                        </div>
                    </div>
                </div>
            </x-driver-check.surface>

            {{-- Security --}}
            <form method="POST" action="{{ route('driver-check.profile.password') }}">
                @csrf

                <x-driver-check.surface class="overflow-hidden">
                    <div class="border-b border-gray-100 px-5 py-3.5 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('driver_check.profile.security') }}</h2>
                    </div>

                    <div class="grid gap-4 p-5 sm:grid-cols-3">
                        <x-driver-check.field :label="__('driver_check.profile.password_current')">
                            <x-driver-check.input type="password" name="current_password" autocomplete="current-password" required :data-invalid="$passwordErrors->has('current_password') ? '' : null" />
                            @if ($passwordErrors->has('current_password'))<p class="mt-1.5 text-xs text-error-600">{{ $passwordErrors->first('current_password') }}</p>@endif
                        </x-driver-check.field>

                        <x-driver-check.field :label="__('driver_check.profile.password_new')" :hint="__('driver_check.profile.password_hint')">
                            <x-driver-check.input type="password" name="password" autocomplete="new-password" required minlength="6" :data-invalid="$passwordErrors->has('password') ? '' : null" />
                            @if ($passwordErrors->has('password'))<p class="mt-1.5 text-xs text-error-600">{{ $passwordErrors->first('password') }}</p>@endif
                        </x-driver-check.field>

                        <x-driver-check.field :label="__('driver_check.profile.password_confirm')">
                            <x-driver-check.input type="password" name="password_confirmation" autocomplete="new-password" required minlength="6" />
                        </x-driver-check.field>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 bg-gray-25 px-5 py-3 dark:border-gray-800 dark:bg-white/[0.015]">
                        <x-driver-check.button type="submit" variant="primary">{{ __('driver_check.profile.password_save') }}</x-driver-check.button>
                    </div>
                </x-driver-check.surface>
            </form>
        </div>
    </div>
</x-driver-check.shell>

@endsection
