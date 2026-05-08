@php
    use App\Helpers\MenuHelper;

    $menuGroups = MenuHelper::getMenuGroups($department ?? null);
    $currentPath = request()->path();
@endphp

<div
    class="sticky top-[64px] xl:top-[72px] z-40 border-b border-gray-200 bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95"
    x-data="{
        mobileOpen: false,
        openGroups: {},
        openSubmenus: {},

        init() {
            const currentPath = '{{ $currentPath }}';

            @foreach ($menuGroups as $groupIndex => $menuGroup)
                @foreach ($menuGroup['items'] as $itemIndex => $item)
                    @if (isset($item['subItems']) && count($item['subItems']) > 0)
                        @foreach ($item['subItems'] as $subItem)
                            if (
                                currentPath === '{{ ltrim($subItem['path'], '/') }}' ||
                                window.location.pathname === '{{ $subItem['path'] }}'
                            ) {
                                this.openGroups['{{ $groupIndex }}'] = true;
                                this.openSubmenus['{{ $groupIndex }}-{{ $itemIndex }}'] = true;
                            }
                        @endforeach
                    @else
                        if (
                            currentPath === '{{ ltrim($item['path'], '/') }}' ||
                            window.location.pathname === '{{ $item['path'] }}'
                        ) {
                            this.openGroups['{{ $groupIndex }}'] = true;
                        }
                    @endif
                @endforeach
            @endforeach
        },

        isActive(path) {
            if (!path) return false;
            return window.location.pathname === path || window.location.pathname === path.replace(/\/+$/, '');
        },

        toggleMobile() {
            this.mobileOpen = !this.mobileOpen;
        },

        toggleGroup(groupIndex) {
            const key = String(groupIndex);
            const newState = !this.openGroups[key];

            if (newState) {
                this.openGroups = {};
            }

            this.openGroups[key] = newState;
        },

        toggleSubmenu(groupIndex, itemIndex) {
            const key = groupIndex + '-' + itemIndex;
            const newState = !this.openSubmenus[key];

            if (newState) {
                this.openSubmenus = {};
                this.openGroups[String(groupIndex)] = true;
            }

            this.openSubmenus[key] = newState;
        },

        isGroupOpen(groupIndex) {
            return this.openGroups[String(groupIndex)] || false;
        },

        isSubmenuOpen(groupIndex, itemIndex) {
            return this.openSubmenus[groupIndex + '-' + itemIndex] || false;
        }
    }"
    x-init="init()"
    @keydown.escape.window="mobileOpen = false"
>
    <div class="mx-auto max-w-screen-2xl px-3 sm:px-4 lg:px-6">
        <!-- Mobile top bar -->
        <div class="flex items-center justify-between gap-3 py-3 xl:hidden">
            <div class="flex min-w-0 items-center gap-3">
                {{-- <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-700 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none">
                        <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </div> --}}
                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                        {{ __('messages.layout.departments') }}
                    </div>
                    <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ strtoupper(app()->getLocale()) }}
                    </div>
                </div>
            </div>

            <button
                type="button"
                @click="toggleMobile()"
                class="inline-flex h-10 items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                :aria-expanded="mobileOpen.toString()"
                aria-label="Open second menu"
            >
                <span x-text="mobileOpen ? '{{ __('messages.menu') }}' : '{{ __('messages.menu') }}'"></span>
                <svg class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': mobileOpen }" viewBox="0 0 20 20" fill="none">
                    <path d="M5 7l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <!-- Desktop nav -->
        <div class="hidden xl:block">
            <div class="flex items-center gap-3 overflow-x-auto no-scrollbar py-3">
                @foreach ($menuGroups as $groupIndex => $menuGroup)
                    <div class="flex items-center gap-3 whitespace-nowrap rounded-2xl border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <button
                            type="button"
                            @click="toggleGroup({{ $groupIndex }})"
                            class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-gray-400 transition hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        >
                            {{-- <span>{{ $menuGroup['title'] }}</span> --}}
                            <svg class="h-3.5 w-3.5 transition-transform duration-200" :class="{ 'rotate-180': isGroupOpen({{ $groupIndex }}) }" viewBox="0 0 20 20" fill="none">
                                <path d="M5 7l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </button>

                        <div class="flex items-center gap-2">
                            @foreach ($menuGroup['items'] as $itemIndex => $item)
                                @if (isset($item['subItems']) && count($item['subItems']) > 0)
                                    <div class="relative" x-data="{ open: false }" @click.away="open = false">
                                        <button
                                            type="button"
                                            @click="open = !open"
                                            class="inline-flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-medium transition"
                                            :class="isActive('{{ $item['path'] ?? '' }}')
                                                ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white'
                                                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                                        >
                                            <span>{{ $item['name'] }}</span>
                                            <svg class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="none">
                                                <path d="M5 7l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            </svg>
                                        </button>

                                        <div
                                            x-show="open"
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                                            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                            x-transition:leave="transition ease-in duration-100"
                                            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                                            **x-cloak**
                                            class="absolute left-0 mt-2 w-64 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900 z-50"
                                            style="display:none;"
                                        >
                                            <div class="p-2">
                                                @foreach ($item['subItems'] as $subItem)
                                                    <a
                                                        href="{{ $subItem['path'] }}"
                                                        class="flex items-center justify-between rounded-xl px-4 py-3 text-sm transition"
                                                        :class="isActive('{{ $subItem['path'] }}')
                                                            ? 'bg-gray-100 font-medium text-gray-900 dark:bg-gray-800 dark:text-white'
                                                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                                                    >
                                                        <span class="truncate">{{ $subItem['name'] }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <a
                                        href="{{ $item['path'] }}"
                                        class="inline-flex h-10 items-center rounded-xl px-4 text-sm font-medium transition"
                                        :class="isActive('{{ $item['path'] }}')
                                            ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-white'
                                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                                    >
                                        {{ $item['name'] }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Mobile menu panel -->
        <div
            x-show="mobileOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            **x-cloak**
            class="xl:hidden pb-3"
            style="display:none;"
        >
            <div class="space-y-3 rounded-3xl border border-gray-200 bg-gray-50 p-3 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                @foreach ($menuGroups as $groupIndex => $menuGroup)
                    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                        {{-- <button
                            type="button"
                            @click="toggleGroup({{ $groupIndex }})"
                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                        >
                            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">
                                {{ $menuGroup['title'] }}
                            </span>
                            <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform duration-200" :class="{ 'rotate-180': isGroupOpen({{ $groupIndex }}) }" viewBox="0 0 20 20" fill="none">
                                <path d="M5 7l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </button> --}}

                        <div x-show="isGroupOpen({{ $groupIndex }})" x-transition x-cloak style="display:none;">
                            <div class="border-t border-gray-100 p-2 dark:border-gray-800">
                                @foreach ($menuGroup['items'] as $itemIndex => $item)
                                    @if (isset($item['subItems']) && count($item['subItems']) > 0)
                                        <div class="mb-2 overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950">
                                            <button
                                                type="button"
                                                @click="toggleSubmenu({{ $groupIndex }}, {{ $itemIndex }})"
                                                class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                                            >
                                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                                    {{ $item['name'] }}
                                                </span>
                                                <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform duration-200" :class="{ 'rotate-180': isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }}) }" viewBox="0 0 20 20" fill="none">
                                                    <path d="M5 7l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </button>

                                            <div x-show="isSubmenuOpen({{ $groupIndex }}, {{ $itemIndex }})" x-transition x-cloak style="display:none;">
                                                <div class="border-t border-gray-100 p-2 dark:border-gray-800">
                                                    @foreach ($item['subItems'] as $subItem)
                                                        <a
                                                            href="{{ $subItem['path'] }}"
                                                            class="mb-1 flex items-center justify-between rounded-xl px-4 py-3 text-sm transition last:mb-0"
                                                            :class="isActive('{{ $subItem['path'] }}')
                                                                ? 'bg-gray-100 font-medium text-gray-900 dark:bg-gray-800 dark:text-white'
                                                                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                                                            @click="mobileOpen = false"
                                                        >
                                                            <span class="truncate">{{ $subItem['name'] }}</span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <a
                                            href="{{ $item['path'] }}"
                                            class="mb-2 flex items-center justify-between rounded-2xl px-4 py-3 text-sm transition last:mb-0"
                                            :class="isActive('{{ $item['path'] }}')
                                                ? 'bg-gray-100 font-medium text-gray-900 dark:bg-gray-800 dark:text-white'
                                                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                                            @click="mobileOpen = false"
                                        >
                                            <span class="truncate">{{ $item['name'] }}</span>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>