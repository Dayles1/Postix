@extends('layouts.app')

@section('content')
    <div class="container mx-auto px-4 py-6">
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 p-4 md:p-6">
            {{-- Header --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ __('messages.warehouse.kazakhstan_uzbekistan') }}
                </h1>

                <a href="{{ route('warehouse.chekedQozoqExport', request()->query()) }}"
                    class="inline-flex items-center justify-center px-4 py-2 rounded-md bg-green-600 text-white hover:bg-green-700 transition-colors">
                    {{ __('messages.warehouse.excel_download') }}
                </a>
            </div>

            {{-- Filters Form --}}
            <form method="GET" action="{{ route('warehouse.importQozoqPage') }}"
                class="grid grid-cols-1 md:grid-cols-6 gap-3 mb-6">
                <input type="text" name="boundary_name" value="{{ request('boundary_name') }}"
                    placeholder="{{ __('messages.warehouse.boundary_name') }}"
                    class="border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400">

                <input type="text" name="car_number" value="{{ request('car_number') }}"
                    placeholder="{{ __('messages.warehouse.car_number') }}"
                    class="border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400">

                {{-- <input type="text" name="phone" value="{{ request('phone') }}"
                    placeholder="{{ __('messages.warehouse.phone') }}"
                    class="border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400"> --}}

                <input type="date" name="date" value="{{ request('date') }}"
                    class="border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400">

                <input type="text" name="status" value="{{ request('status') }}"
                    placeholder="{{ __('messages.warehouse.status') }}"
                    class="border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400">

                <input type="text" name="company_name" value="{{ request('company_name') }}"
                    placeholder="{{ __('messages.warehouse.company_name') }}"
                    class="border border-gray-300 dark:border-gray-600 rounded px-3 py-2 bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400">

                <div class="flex gap-2 md:col-span-1">
                    <button type="submit"
                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        {{ __('messages.filter') }}
                    </button>

                    <a href="{{ route('warehouse.importQozoqPage') }}"
                        class="w-full text-center px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                        {{ __('messages.clear_filters') }}
                    </a>
                </div>
            </form>

            {{-- Table --}}
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                <table class="min-w-full border-collapse">
                    <thead class="bg-gray-100 dark:bg-gray-800">
                        <tr>
                            <th
                                class="text-left px-4 py-3 border-b border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200">
                                #</th>
                            <th
                                class="text-left px-4 py-3 border-b border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200">
                                {{ __('messages.warehouse.boundary_name') }}</th>
                            <th
                                class="text-left px-4 py-3 border-b border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200">
                                {{ __('messages.warehouse.car_number') }}</th>
                            <th
                                class="text-left px-4 py-3 border-b border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200">
                                {{ __('messages.warehouse.phone') }}</th>
                            <th
                                class="text-left px-4 py-3 border-b border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200">
                                {{ __('messages.warehouse.date_and_time') }}</th>
                            <th
                                class="text-left px-4 py-3 border-b border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200">
                                {{ __('messages.warehouse.status') }}</th>
                            <th
                                class="text-left px-4 py-3 border-b border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200">
                                {{ __('messages.warehouse.company_name') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($items as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $item['id'] }}</td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $item['boundary_name'] ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $item['car_number'] ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $item['phone'] ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $item['date_and_time'] ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $item['status'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $item['company_name'] ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                    {{ __('messages.no_data_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @php
                $currentPage = (int) ($pagination['current_page'] ?? 1);
                $lastPage = (int) ($pagination['last_page'] ?? 1);

                $pages = [];

                for ($i = 1; $i <= min(3, $lastPage); $i++) {
                    $pages[] = $i;
                }

                $middleStart = max(4, $currentPage - 1);
                $middleEnd = min($lastPage - 1, $currentPage + 1);

                if ($middleStart <= $middleEnd) {
                    if ($middleStart > 4) {
                        $pages[] = '...';
                    }
                    for ($i = $middleStart; $i <= $middleEnd; $i++) {
                        $pages[] = $i;
                    }
                    if ($middleEnd < $lastPage - 1) {
                        $pages[] = '...';
                    }
                } else {
                    if ($lastPage > 4) {
                        $pages[] = '...';
                    }
                }

                if ($lastPage > 3) {
                    $pages[] = $lastPage;
                }
                $pages = array_values(array_unique($pages, SORT_REGULAR));
            @endphp

            <div class="flex items-center justify-center gap-2 mt-6 flex-wrap">
                @if ($currentPage > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $currentPage - 1]) }}"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        {{ __('messages.prev') }}
                    </a>
                @endif

                @foreach ($pages as $page)
                    @if ($page === '...')
                        <span class="px-3 py-2 text-gray-500 dark:text-gray-400">...</span>
                    @else
                        <a href="{{ request()->fullUrlWithQuery(['page' => $page]) }}"
                            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md transition-colors
                       {{ $page == $currentPage
                           ? 'bg-blue-600 text-white border-blue-600'
                           : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach

                @if ($currentPage < $lastPage)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $currentPage + 1]) }}"
                        class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        {{ __('messages.next') }}
                    </a>
                @endif
            </div>
        </div>
    </div>
@endsection
