<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Postix AI'))</title>

    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --bg: #071427;
            --card: #0f2233;
            --text: #e7f4ff;
            --muted: #9fb7dd;
            --accent: #3b82f6;
            --accent-2: #facc15;
            --muted-2: rgba(255, 255, 255, 0.06);
        }

        /* Light theme overrides */
        body.light {
            --bg: #f6f8fb;
            --card: #ffffff;
            --text: #0b1220;
            --muted: #6b7280;
            --accent: #2563eb;
            --accent-2: #d97706;
            --muted-2: rgba(0, 0, 0, 0.06);
        }

        /* Base */
        html,
        body {
            height: 100%
        }

        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            background: var(--bg);
            color: var(--text);
            transition: background .22s ease, color .22s ease;
            -webkit-font-smoothing: antialiased;
        }

        /* Layout */
        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar: doim ko'rinib turadi va ichida scroll bo'ladi */
        .sidebar {
            width: 260px;
            position: sticky;
            /* <-- bu qator qo'shiladi */
            top: 0;
            /* yuqoridan yopishib turadi */
            height: 100vh;
            /* butun oynani egallaydi */
            overflow: auto;
            /* agar menu uzun bo'lsa — ichida scroll paydo bo'ladi */
            padding: 20px;
            box-sizing: border-box;
            border-right: 1px solid var(--muted-2);
            background: linear-gradient(180deg, var(--card), rgba(0, 0, 0, 0.05));
        }

        /* Mobile uchun sticky olib tashlaymiz (mobilda sidebar yuqoriga aylanadi) */
        @media (max-width: 900px) {
            .sidebar {
                position: static;
                height: auto;
                overflow: visible;
            }
        }


        .sidebar .brand {
            font-weight: 800;
            font-size: 1.1rem;
            margin-bottom: 14px;
            color: var(--text);
        }

        .sidebar .nav-link {
            display: block;
            color: var(--muted);
            padding: 10px 12px;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 8px;
        }

        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.01));
            color: var(--text);
            border-left: 3px solid var(--accent);
        }

        /* Mobile collapse */
        .sidebar-collapsed {
            display: none;
        }

        @media (max-width: 900px) {
            .layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                display: flex;
                gap: 8px;
                align-items: center;
            }

            .sidebar .nav {
                display: flex;
                gap: 8px;
                overflow: auto;
            }

            .sidebar .brand {
                margin-right: auto;
            }
        }

        /* Content */
        .content {
            flex: 1;
            padding: 20px;
            box-sizing: border-box;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;

            position: sticky;
            /* sticky qilamiz */
            top: 0;
            /* tepada yopishadi */
            background: var(--bg);
            /* fonini saqlaymiz, scrollda aralashmasligi uchun */
            padding: 12px 20px;
            /* biroz ichki padding */
            z-index: 1000;
            /* boshqa elementlar ustida turishi uchun */
            border-bottom: 1px solid var(--muted-2);
            /* vizual ajratish uchun */
        }


        /* Cards */
        .card {
            background: var(--card);
            color: var(--text);
            border: 1px solid var(--muted-2);
            border-radius: 12px;
            box-shadow: 0 8px 26px rgba(2, 6, 23, 0.45);
        }

        /* Small helpers */
        .text-muted {
            color: var(--muted) !important;
        }

        .btn-theme {
            background: transparent;
            border: 1px solid var(--muted-2);
            color: var(--text);
        }

        .lang-btn {
            background: transparent;
            border: 0;
            color: var(--muted);
            padding: 6px 8px;
            border-radius: 6px;
        }

        .profile-avatar {
            background: var(--bs-secondary-bg);
            color: var(--bs-body-color);
            font-size: 14px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 8px;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.06);
            background: transparent;
            color: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: transform .08s ease, opacity .12s ease;
        }

        .back-btn:active {
            transform: translateY(1px);
        }

        .back-btn[aria-disabled="true"] {
            opacity: 0.6;
            pointer-events: none;
        }

        /* small / compact on mobile */
        @media (max-width:900px) {
            .back-btn {
                padding: 6px 8px;
                gap: 6px;
                font-size: 14px;
            }
        }
    </style>
    <style>
        /* ===== Theme variables (keep in sync with your other vars) ===== */
        :root {
            --bg: #071427;
            --card: #0f2233;
            --card-2: #122a3f;
            --card-3: #163650;
            --text: #eaf3ff;
            --muted: #9fb7dd;
            --accent: #3b82f6;
            --accent2: #facc15;

            /* peer / inline defaults */
            --peer-bg: rgba(255, 255, 255, 0.02);
            --peer-border: rgba(255, 255, 255, 0.04);
            --inline-bg: rgba(255, 255, 255, 0.02);
            --inline-border: rgba(255, 255, 255, 0.04);
            --chip-bg: rgba(255, 255, 255, 0.02);
        }

        /* LIGHT THEME overrides */
        body.light {
            --bg: #f4f6fb;
            --card: #ffffff;
            --card-2: #f8fafc;
            --card-3: #ffffff;
            --text: #0b1220;
            --muted: #6b7280;
            --accent: #2563eb;
            --accent2: #d97706;

            --peer-bg: rgba(11, 17, 32, 0.03);
            --peer-border: rgba(11, 17, 32, 0.06);
            --inline-bg: rgba(11, 17, 32, 0.03);
            --inline-border: rgba(11, 17, 32, 0.06);
            --chip-bg: rgba(11, 17, 32, 0.03);
        }

        /* ===== Elements that must adapt to theme ===== */
        .message-group {
            background: var(--card-2) !important;
            border: 1px solid rgba(255, 255, 255, 0.04);
            color: var(--text);
        }

        /* message text panel */
        .message-text {
            background: var(--card-3) !important;
            color: var(--text) !important;
            border-left: 4px solid var(--accent);
        }

        /* peer rows (was inline rgba) */
        .peer-row {
            background: var(--peer-bg) !important;
            border: 1px solid var(--peer-border) !important;
            color: var(--text) !important;
        }

        /* override any inline styles that used rgba(...) to a theme-aware variable */
        *[style*="background:rgba(255,255,255,0.02)"],
        *[style*="background: rgba(255,255,255,0.02)"] {
            background: var(--inline-bg) !important;
            /* try to preserve border if present */
            border-color: var(--inline-border) !important;
            color: var(--text) !important;
        }

        /* also override other tiny inline backgrounds if present */
        *[style*="background:rgba(255,255,255,0.01)"],
        *[style*="background: rgba(255,255,255,0.01)"] {
            background: var(--inline-bg) !important;
            border-color: var(--inline-border) !important;
            color: var(--text) !important;
        }

        /* status badges - keep color cues but ensure readable in light mode */
        .status-badge {
            color: #062;
        }

        /* fallback */

        .status-badge.status-sent {
            background: #bbf7d0;
            color: #064e3b;
        }

        .status-badge.status-failed {
            background: #fecaca;
            color: #7f1d1d;
        }

        .status-badge.status-canceled {
            background: #e9d5ff;
            color: #5b21b6;
        }

        .status-badge.status-scheduled {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.status-pending {
            background: #dbeafe;
            color: #1e3a8a;
        }

        /* small chips inside peer rows */
        .status-chip {
            background: var(--chip-bg) !important;
            color: var(--text) !important;
            border-radius: 8px;
            padding: 4px 8px;
            font-weight: 700;
        }

        /* ensure text-muted adapts */
        .text-muted.small {
            color: var(--muted) !important;
        }

        /* scrollbar cosmetic */
        .peer-row::-webkit-scrollbar,
        .card::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }

        .peer-row::-webkit-scrollbar-thumb,
        .card::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 6px;
        }

        /* safety: override any other inline color that hides text */
        /* *[style*="color:var(--text-disabled)"] {
            color: var(--text) !important;
        }

        /* Toast styling */
        .layout-topbar {
            position: sticky;
            /* agar siz uni sticky qilmoqchi bo'lsangiz */
            top: 0;
            z-index: 3000;
            /* juda yuqori — dropdown va boshqa overlaylardan ustun turadi */
            overflow: visible;
            /* dropdown kesilishi oldini olish */
        }

        /* 2) Page-level topbar (sarlavha, qidiruv, Add user) - pastroq z-index */
        .page-topbar {
            position: relative;
            /* sticky bo'lishi shart emas; agar sticky kerak bo'lsa, use z-index pastroq */
            z-index: 100;
            overflow: visible;
            /* kesilishni oldini oladi */
        }

        /* 3) Dropdown menyular har doim yuqorida chiqsin */
        .dropdown-menu {
            position: absolute !important;
            z-index: 4000 !important;
        }

        /* 4) Agar card yoki boshqa containerlar transform ishlatayotgan bo'lsa,
   ularni pastroq z-index bilan cheklash (agar kerak bo'lsa) */
        .content .card,
        .container-fluid {
            z-index: 0;
        }

        .toast-alert {
            min-width: 260px;
            max-width: 380px;
            padding: 12px 14px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(2, 6, 23, 0.35);
            color: var(--text);
            font-weight: 600;
            opacity: 1;
            transition: transform .25s ease, opacity .25s ease;
        }

        /* slide-in animation */
        .toast-enter {
            transform: translateY(-8px) scale(.98);
            opacity: 0;
        }


        .sidebar {
            width: 260px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
            border-right: 1px solid var(--muted-2);
            background: linear-gradient(180deg, var(--card), rgba(0, 0, 0, 0.05));
            z-index: 1000;
            transition: transform .28s ease;
        }

        /* Topbar z-index biroz pastroq bo'lsin, shunda mobilda sidebar ustida turishi mumkin */
        .topbar {
            z-index: 1100;
        }

        /* --- Mobile / Tablet: sidebar tepaga chiqsin (gorizontal nav) --- */
        @media (max-width: 900px) {

            /* Layout: sidebar va main ustma-ust bo'ladi */
            .layout {
                flex-direction: column;
            }

            /* Desktop yon sidebarni yashiramiz (agar oldingi kodda d-none d-md-block bo'lsa) */
            .sidebar {
                width: 100%;
                height: auto;
                position: sticky;
                /* tepaga yopishadi */
                top: 0;
                left: 0;
                display: flex;
                /* gorizontal nav */
                flex-direction: row;
                align-items: center;
                gap: 8px;
                padding: 8px 12px;
                /* mobilga mos padding */
                overflow-x: auto;
                /* gorizontal scroll */
                white-space: nowrap;
                /* linklar bir qatorda qoladi */
                border-right: none;
                /* yon border bekor qilish */
                border-bottom: 1px solid var(--muted-2);
                /* pastki chiziq */
                background: linear-gradient(180deg, var(--card), rgba(0, 0, 0, 0.03));
                -webkit-overflow-scrolling: touch;
                z-index: 1200;
                /* topda tursin */
            }

            /* nav-linklarni inline ko'rinishga o'tkazish */
            .sidebar .nav {
                display: flex;
                gap: 8px;
                align-items: center;
            }

            .sidebar .nav-link {
                display: inline-flex;
                align-items: center;
                padding: 8px 12px;
                border-radius: 8px;
                margin-bottom: 0;
                /* mobilda qator orasida bo'lmasin */
                white-space: nowrap;
            }

            .sidebar .brand {
                /* agar brandni saqlamoqchi bo'lsangiz kichikroq qiling yoki yashiring */
                margin-right: 8px;
                font-size: 0.95rem;
                flex: 0 0 auto;
            }

            /* Agar siz hamburger ishlatishni xohlamasangiz uni yashiramiz */
            .hamburger {
                display: none !important;
            }

            /* Topbar ostida joylashishini ta'minlaymiz: topbar sticky bo'lsa, pastga margin qo'yish mumkin */
            .topbar {
                position: sticky;
                top: 48px;
            }

            /* agar sidebar balandligi taxminan 48px bo'lsa */
            /* Eslatma: agar sidebar balandligi dinamik bo'lsa top qiymatini sozlang yoki topbar sticky'ni o'chiring */
        }
    </style>


</head>

<body>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            @if (session('success'))
                showSuccess(@json(session('success')));
            @endif

            @if (session('error'))
                showError(@json(session('error')));
            @endif

            @if ($errors->any())
                @foreach ($errors->all() as $err)
                    showError(@json($err));
                @endforeach
            @endif

        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('backBtn');
            if (!btn) return;

            btn.addEventListener('click', function(e) {
                e.preventDefault();

                if (this.getAttribute('aria-disabled') === 'true') return;

                // lock to prevent double/back-flood
                this.setAttribute('aria-disabled', 'true');

                // small visual feedback
                this.style.opacity = '.85';

                // try history.back(); if it doesn't change (after timeout), fallback to href
                let went = false;
                const onPop = () => {
                    went = true;
                    window.removeEventListener('popstate', onPop);
                };
                window.addEventListener('popstate', onPop);

                // do the back navigation
                try {
                    history.back();
                } catch (err) {
                    went = false;
                }

                // after short delay, if nothing happened, go to previous URL (fallback)
                setTimeout(() => {
                    window.removeEventListener('popstate', onPop);
                    if (!went) {
                        const href = btn.getAttribute('href');
                        if (href && href !== window.location.href) {
                            window.location.href = href;
                        } else {
                            // agar hammasi muvaffaqiyatsiz bo'lsa, xohlasangiz route orqali defaultga yuborish:
                            // window.location.href = "{{ route('departments.users', $department ?? 1) }}";
                            // yoki shunchaki unlock:
                            btn.removeAttribute('aria-disabled');
                            btn.style.opacity = '';
                        }
                    }
                }, 250); // 250ms yetarli; xohlasangiz oshiring
            });
        });
    </script>


    <div class="layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <!-- Brand / logo -->
            <div class="brand">{{ config('app.name', 'Postix AI') }}</div>

            <!-- Navigation links -->
            @if ($department)
                <nav class="nav flex-column mb-3">
                    <a href="{{ route('departments.dashboard', $department->id) }}"
                        class="nav-link {{ request()->routeIs('departments.dashboard') ? 'active' : '' }}">
                        🏠 {{ __('messages.admin.dashboard') }}
                    </a>

                    <a href="{{ route('departments.users', $department->id) }}"
                        class="nav-link {{ request()->routeIs('departments.users') ? 'active' : '' }}">
                        👤 {{ __('messages.admin.users') }}
                    </a>

                    <a href="{{ route('departments.operations', $department->id) }}"
                        class="nav-link {{ request()->routeIs('departments.operations') ? 'active' : '' }}">
                        📊 {{ __('messages.admin.operations') }}
                    </a>

                    {{-- Agar kerak bo'lsa sozlamalar linki --}}
                    {{-- <a href="{{ route('settings.index') ?? '#' }}" class="nav-link @if (request()->routeIs('settings.*')) active @endif">
            ⚙️ {{ __('layout.menu.settings') }}
        </a> --}}
                </nav>
            @endif

        </aside>


        <!-- MAIN CONTENT -->
        <main class="content">
            <div class="topbar layout-topbar d-flex align-items-center gap-2">


                <div class="d-flex align-items-center">
                    @hasSection('show-back')
                        <a id="backBtn" href="{{ url()->previous() }}" class="back-btn me-2"
                            aria-label="Orqaga qaytish" title="Orqaga qaytish" role="button">
                            <!-- oddiy belgi, xohlasangiz SVG qo‘shing -->
                            ←{{ __('messages.users.back_to_list') }}
                        </a>
                    @endif

                    <div>
                        <h4 class="mb-0">@yield('page-title', __('messages.layout.page_title'))</h4>
                        <div class="text-muted small">@yield('page-subtitle')</div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">

                    {{-- Theme toggle --}}
                    <button id="themeToggleBtn" class="btn btn-theme btn-sm"
                        title="{{ __('messages.layout.toggle_theme') }}">☀️</button>

                    {{-- Language dropdown (mirror) --}}
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="langMenuBtn"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            {{ strtoupper(app()->getLocale()) }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="langMenuBtn">
                            <li>
                                <a class="dropdown-item" href="{{ url('/lang/uz') }}">
                                    Oʻzbekcha
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ url('/lang/en') }}">
                                    English
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ url('/lang/ru') }}">
                                    Русский
                                </a>
                            </li>
                            {{-- <li>
                                <a class="dropdown-item" href="{{ url('/lang/ko') }}">
                                    한국어
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ url('/lang/zh') }}">
                                    中文
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ url('/lang/ar') }}">
                                    العربية
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ url('/lang/es') }}">
                                    Español
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ url('/lang/fr') }}">
                                    Français
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ url('/lang/de') }}">
                                    Deutsch
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ url('/lang/ja') }}">
                                    日本語
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ url('/lang/tr') }}">
                                    Türkçe
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ url('/lang/hi') }}">
                                    हिंदी
                                </a>
                            </li> --}}
                        </ul>

                    </div>

                    {{-- Profile dropdown --}}
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2"
                            type="button" id="profileMenuBtn" data-bs-toggle="dropdown" aria-expanded="false">
                            {{-- Avatar --}}
                            @if (auth()->user()->avatar)
                                <img src="{{ asset('storage/' . auth()->user()->avatar->path) }}" alt="Avatar"
                                    class="rounded-circle" style="width:28px;height:28px;object-fit:cover">
                            @else
                                <span
                                    class="profile-avatar d-flex align-items-center justify-content-center rounded-circle"
                                    style="width:28px;height:28px;font-weight:700;">
                                    {{ strtoupper(mb_substr(auth()->user()->name ?? (auth()->user()->username ?? 'U'), 0, 1)) }}
                                </span>
                            @endif

                            {{-- Name --}}
                            <span class="d-none d-md-inline">
                                {{ auth()->user()->name ?? (auth()->user()->username ?? __('messages.layout.profile')) }}
                            </span>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenuBtn">
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.users.show', auth()->user()->id) }}">
                                    {{ __('messages.layout.profile') }}
                                </a>
                            </li>

                            <li>
                                <a class="dropdown-item" href="#">
                                    {{ __('messages.layout.settings') }}
                                </a>
                            </li>

                            <li>
                                <hr class="dropdown-divider">
                            </li>

                            <li>
                                <form action="{{ route('logout') }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        {{ __('messages.layout.logout') }}
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>


                </div>
            </div>

            {{-- Content area --}}
            <div class="container-fluid p-0">
                @yield('content')
            </div>

        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        /*
                                      Theme toggle: stores 'light'|'dark' in localStorage under key 'app_theme'
                                      Usage: body.classList.toggle('light', true) -> light theme
                                    */
        (function() {
            const THEME_KEY = 'app_theme';
            const body = document.body;
            const btn = document.getElementById('themeToggleBtn');

            function applyTheme(theme) {
                body.classList.toggle('light', theme === 'light');
                // button label/icon/text
                if (btn) btn.textContent = theme === 'light' ? '🌙' : '☀️';
            }

            // get saved or detect
            const saved = localStorage.getItem(THEME_KEY);
            let theme = saved || (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ?
                'light' : 'dark');

            applyTheme(theme);

            if (btn) {
                btn.addEventListener('click', function() {
                    theme = body.classList.contains('light') ? 'dark' : 'light';
                    localStorage.setItem(THEME_KEY, theme);
                    applyTheme(theme);
                });
            }
        })();
    </script>

</body>

</html>
