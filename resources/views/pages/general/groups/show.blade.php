@extends('layouts.app')

@section('title', __('messages.op_show.title', ['id' => $operation->id ?? '']))

@section('page-title')
    {{ __('messages.op_show.title', ['id' => $operation->id ?? '']) }}
@endsection

@section('page-subtitle')
    {{ __('messages.op_show.subtitle') }}
@endsection

@section('content')
    <div class="min-h-screen py-6 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto space-y-6">

            @include('pages.general.groups.partials.header', [
                'operation' => $operation,
                'department' => $department,
                'user' => $user,
                'stats' => $stats,
                'statusMap' => $statusMap,
                'firstSendAt' => $firstSendAt,
                'topRightTimeValue' => $topRightTimeValue,
                'isPending' => $isPending,
                'senderPhone' => $senderPhone,
                'departmentName' => $departmentName,
            ])

            @include('pages.general.groups.partials.full-message', [
                'operation' => $operation,
            ])
            @if ($isPending)
                @include('pages.general.groups.partials.update-modal', [
                    'operation' => $operation,
                ])
            @endif

            @include('pages.general.groups.partials.filters', [
                'currentStatus' => $currentStatus,
                'statusMap' => $statusMap,
            ])

            <div id="peers-container" class="space-y-4" data-peers-url="{{ route('operations.peers', $operation->id) }}"
                data-peer-messages-url="{{ route('operations.peer-messages', $operation->id) }}"
                data-operation-id="{{ $operation->id }}">
                <div
                    class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('messages.loading') }}
                </div>
            </div>
        </div>

        <div x-data="{ open: false, text: '' }" x-init="window.showToast = (t) => { text = t;
            open = true;
            setTimeout(() => open = false, 3500) }" class="fixed top-5 right-5 z-[99999] pointer-events-none">

            <div x-show="open" x-transition
                class="px-4 py-2 bg-green-600 text-white rounded-lg shadow-lg pointer-events-auto" x-text="text">
            </div>
        </div>
    </div>



    <div id="peerRemoveModal"
    class="fixed inset-0 z-[9999] hidden flex items-center justify-center bg-black/50 px-4 py-6 overflow-y-auto"
    aria-hidden="true">
    <div
        class="w-full max-w-md rounded-2xl bg-white dark:bg-gray-800 shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
            <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ __('messages.remove_peer.modal_title') }}
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ __('messages.remove_peer.modal_description') }}
            </div>
        </div>

        <div class="px-5 py-4">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                {!! __('messages.remove_peer.selected_text', [
                    'peer' => '<span id="peerRemoveName" class="font-semibold text-gray-900 dark:text-gray-100"></span>'
                ]) !!}
            </div>
        </div>

        <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-2">
            <button type="button" id="peerRemoveCancel"
                class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                {{ __('messages.remove_peer.cancel_text') }}
            </button>

            <button type="button" id="peerRemoveConfirm"
                class="px-4 py-2 rounded-xl bg-red-600 text-white hover:bg-red-700 transition">
                {{ __('messages.remove_peer.confirm_text') }}
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    @include('pages.general.groups.partials.scripts')
@endpush
