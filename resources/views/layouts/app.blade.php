<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | Postix</title>

    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const theme = savedTheme || systemTheme;

            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                document.body.classList.add('dark', 'bg-gray-900');
            } else {
                document.documentElement.classList.remove('dark');
                document.body.classList.remove('dark', 'bg-gray-900');
            }
        })();
    </script>

    @stack('styles')
</head>

<body x-data="{ loaded: true }" class="h-full antialiased text-gray-800 dark:text-white">
    <script>
        document.documentElement.setAttribute('data-alpine-cloak', 'true');
    </script>

    @include('layouts.toast')

    @php
        use App\Helpers\MenuHelper;
        $isSuperAdmin = auth()->check() && optional(auth()->user()->role)->name === 'superadmin';
        
        $department = $department ?? null;
        $hasSidebar = true;
    @endphp

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const saved = localStorage.getItem('theme');
                    const system = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    this.theme = saved || system;
                    this.apply();
                },
                theme: 'light',
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.apply();
                },
                apply() {
                    if (this.theme === 'dark') {
                        document.documentElement.classList.add('dark');
                        document.body.classList.add('dark', 'bg-gray-900');
                    } else {
                        document.documentElement.classList.remove('dark');
                        document.body.classList.remove('dark', 'bg-gray-900');
                    }
                }
            });

            Alpine.store('sidebar', {
                isExpanded: @js($hasSidebar) && window.innerWidth >= 1280,
                isMobileOpen: false,
                isHovered: false,
                toggleExpanded() {
                    if (!@js($hasSidebar)) return;
                    this.isExpanded = !this.isExpanded;
                },
                toggleMobileOpen() {
                    if (!@js($hasSidebar)) return;
                    this.isMobileOpen = !this.isMobileOpen;
                },
                setMobileOpen(v) {
                    if (!@js($hasSidebar)) return;
                    this.isMobileOpen = v;
                },
                setHovered(v) {
                    if (!@js($hasSidebar)) return;
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = v;
                    }
                }
            });
        });
    </script>

    <div class="min-h-screen xl:flex">
        {{-- backdrop / mobile overlay --}}
        @include('layouts.backdrop')

        {{-- sidebar always loaded --}}
        @include('layouts.sidebar')

        {{-- main content area --}}
        <div class="flex-1 transition-all duration-300 ease-in-out"
            :class="{
                'xl:ml-[290px]': @js($hasSidebar) && ($store.sidebar.isExpanded || $store.sidebar.isHovered),
                'xl:ml-[90px]': @js($hasSidebar) && (!$store.sidebar.isExpanded && !$store.sidebar.isHovered),
                'ml-0': !@js($hasSidebar) || $store.sidebar.isMobileOpen
            }">

            {{-- header with theme/profile/logout --}}
            @include('layouts.app-header')

            @if ($isSuperAdmin)
                @include('layouts.second-header')
            @endif

            {{-- page content wrapper --}}
            <main class="w-full min-h-screen">
                @if (session('success'))
                    <div class="mb-4 p-3 rounded-md bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-200">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 p-3 rounded-md bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-200">
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <footer class="sr-only" aria-hidden="true">
        @yield('footer')
    </footer>

    @stack('scripts')
</body>
</html>