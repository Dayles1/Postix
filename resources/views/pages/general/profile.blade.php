@extends('layouts.app')

@section('title', $user->name . ' — ' . __('messages.users.title'))
@section('show-back', true)
@section('page-title', $user->name)

@section('content')

    @php
        $auth = auth()->user();
        $dU=$department?->type == 'user';
    @endphp

    <div class="mx-auto max-w-6xl p-4 space-y-6">

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">

            <h3 class="mb-6 text-lg font-semibold text-gray-800 dark:text-white/90">
                {{ __('messages.users.profile') }}
            </h3>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Profile --}}
                <x-profile.profile-card :user="$user" :operations-count="$operationsCount" :messages-count="$messagesCount" :can-ban="$canBan" />

                {{-- Account settings --}}
                <div class="lg:col-span-2 space-y-6">

                    <x-profile.account-card :user="$user" :can-edit="$canEdit" :can-edit-role="$canEditRole" :roles="$roles"
                        :user-limit="$userLimit" :can-edit-limit="$canEditLimit" :dU="$dU" />
                    @if ($user->role->name == 'user' && (!$departmentBan || $auth->role->name === 'superadmin'))
                        <x-profile.phones-card :user="$user" :can-edit="$canEdit" :canLogout="$canLogout" />
                    @endif


                </div>

            </div>

        </div>

    </div>

@endsection
