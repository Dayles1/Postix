@extends('layouts.fullscreen-layout')

@section('content')
<div class="relative z-1 bg-white p-6 sm:p-0 dark:bg-gray-900">
<div class="relative flex h-screen w-full flex-col justify-center lg:flex-row dark:bg-gray-900">

    <!-- LEFT SIDE (FORM) -->
    <div class="flex w-full flex-1 flex-col lg:w-1/2">
        
        <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">

            <div class="mb-8">
                <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800 dark:text-white">
                    {{ __('messages.login.welcome') }}
                </h1>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('messages.login.subtitle') }}
                </p>
            </div>

            {{-- ERRORS --}}
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-400">
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>• {{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="space-y-5">

                    <!-- EMAIL -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            {{ __('messages.login.email') }}
                        </label>

                        <input
                            type="text"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="{{ __('messages.login.email_placeholder') }}"
                            required
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm
                            text-gray-800 placeholder:text-gray-400
                            focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20
                            dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
                        >
                    </div>

                    <!-- PASSWORD -->
                    <div x-data="{ showPassword:false }">

                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            {{ __('messages.login.password') }}
                        </label>

                        <div class="relative">

                            <input
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                placeholder="{{ __('messages.login.password_placeholder') }}"
                                required
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-11 text-sm
                                text-gray-800 placeholder:text-gray-400
                                focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20
                                dark:border-gray-700 dark:text-white dark:placeholder:text-gray-500"
                            >

                            <span
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 cursor-pointer text-gray-400"
                            >
                                👁
                            </span>

                        </div>
                    </div>

                    <!-- LOGIN BUTTON -->
                    <div>
                        <button
                            type="submit"
                            class="flex w-full items-center justify-center rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white transition hover:bg-brand-600"
                        >
                            {{ __('messages.login.submit') }}
                        </button>
                    </div>

                </div>
            </form>

            <p class="mt-6 text-center text-xs text-gray-400">
                {{ __('messages.login.footer') }}
            </p>

        </div>
    </div>


    <!-- RIGHT SIDE (LOGO / DESIGN) -->
    <div class="bg-brand-950 relative hidden h-full w-full items-center lg:grid lg:w-1/2 dark:bg-white/5">

        <div class="flex flex-col items-center">

            <a href="/" class="mb-4 block">
    <img src="/images/logo/logo.jpg" alt="Logo" class="w-24 mx-auto">
</a>


            <p class="max-w-xs text-center text-gray-400 dark:text-white/60">
                {{ __('messages.login.title') }}
            </p>

        </div>

    </div>

</div>
</div>
@endsection
