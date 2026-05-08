@extends('layouts.app')

@section('title', __('messages.logs.title') . ' — Postix')
@section('page-title', __('messages.logs.title'))

@section('content')
<div class="mx-auto max-w-screen-2xl px-4 py-5 md:px-6 lg:py-6 space-y-4">
    {{-- @include('pages.logs.partials._header') --}}
    {{-- @include('pages.logs.partials._stats') --}}
    {{-- @include('pages.logs.partials._filters') --}}

    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                {{ __('messages.logs.journal') }}
            </h2>
            <p class="text-xs text-gray-600 dark:text-gray-400">
                {{ __('messages.logs.table_hint') }}
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">#</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ __('messages.logs.date') }}</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ __('messages.logs.event') }}</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ __('messages.logs.causer') }}</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ __('messages.logs.details') }}</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                    @forelse($logs as $log)
                        @include('pages.logs.partials._row', ['log' => $log])
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-14 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('messages.logs.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
            {{ $logs->withQueryString()->links() }}
        </div>
    </div>
</div>

@include('pages.logs.partials._modal')
@endsection

@push('scripts')
<script>
window.LOG_MODAL_LABELS = {!! json_encode([
    'summary' => __('messages.logs.summary_label'),
    'date' => __('messages.logs.modal.date'),
    'type' => __('messages.logs.modal.log_name'),
    'event' => __('messages.logs.modal.event'),
    'subject' => __('messages.logs.modal.model'),
    'subject_id' => __('messages.logs.modal.subject_id'),
    'causer' => __('messages.logs.modal.causer'),
    'changes' => __('messages.logs.details'),
    'old' => __('messages.logs.old_value'),
    'new' => __('messages.logs.new_value'),
    'close' => __('messages.common.close'),
    'empty' => __('messages.logs.empty'),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};



</script>
@vite('resources/js/pages/logs.js')
@endpush