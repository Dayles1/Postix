<div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
    {{-- Header --}}
    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
            {{ __('messages.logs.journal') }}
        </h2>
        <p class="text-xs text-gray-600 dark:text-gray-400">
            {{ __('messages.logs.table_hint') }}
        </p>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">#</th>
                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ __('messages.logs.date') }}</th>
                    {{-- <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ __('messages.logs.log_name') }}</th> --}}
                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ __('messages.logs.event') }}</th>
                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ __('messages.logs.causer') }}</th>
                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ __('messages.logs.details') }}</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                @forelse($activities as $activity)
                    @include('pages.logs.partials._row', ['activity' => $activity])
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

    {{-- Pagination --}}
    <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
        {{ $activities->withQueryString()->links() }}
    </div>
</div>