<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ __('messages.tenant_portal_title') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * { scroll-behavior: smooth; }
        body { margin: 0; padding: 0; background: linear-gradient(135deg, #eff6ff 0%, #eef2ff 100%); }
        .sidebar-container {
            background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 50%, #f3f4f6 100%);
            box-shadow: 3px 0 12px rgba(15, 23, 42, 0.1);
            transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .sidebar-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.2), transparent);
        }
        @media (min-width: 768px) {
            .sidebar-collapsed { width: 80px; }
            .sidebar-expanded { width: 240px; }
        }
        @media (min-width: 1024px) {
            .sidebar-expanded { width: 280px; }
        }
        @media (max-width: 767px) {
            .sidebar-container {
                position: fixed;
                top: 0; bottom: 0; left: 0;
                width: 84vw;
                max-width: 320px;
                z-index: 50;
                transform: translateX(-100%);
            }
            .sidebar-container.mobile-open { transform: translateX(0); }
            .sidebar-container .sidebar-label { opacity: 1 !important; width: auto !important; overflow: visible !important; margin: inherit !important; }
        }
        .sidebar-label { transition: opacity 0.2s ease-in-out; }
        .sidebar-collapsed .sidebar-label { opacity: 0; width: 0; overflow: hidden; margin: 0; }
        .sidebar-expanded .sidebar-label { opacity: 1; }
        .logout-button { transition: all 0.3s; }
        .logout-button:hover { background-color: rgba(239, 68, 68, 0.12); transform: translateX(2px); }
        [x-cloak] { display: none; }
        aside::after {
            content: '';
            position: absolute; top: 0; right: 0;
            width: 2px; height: 0;
            background: linear-gradient(to bottom, rgba(99, 102, 241, 0.5), transparent);
            animation: heightGrow 0.8s ease-out forwards;
        }
        @keyframes heightGrow { to { height: 100%; } }
    </style>
    @include('partials.pwa-head')
</head>
<body class="bg-gray-50 flex flex-col h-screen" x-data="sidebarState()" @init="init()">
    <!-- Top Bar -->
    @include('layouts.topbar')

    <div class="flex flex-1 overflow-hidden relative">
        <!-- Mobile backdrop -->
        <div
            x-show="mobileOpen"
            x-cloak
            x-transition.opacity
            @click="mobileOpen = false"
            class="md:hidden fixed inset-0 bg-black/50 z-40"></div>

        <!-- Sidebar -->
        <aside
            class="sidebar-container"
            :class="[isCollapsed ? 'sidebar-collapsed' : 'sidebar-expanded', mobileOpen ? 'mobile-open' : '']">
            <div class="bg-white shadow-lg h-full flex flex-col overflow-y-auto">
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="font-bold text-xl text-indigo-700 sidebar-label" :class="{'md:hidden': isCollapsed}">
                        My Portal
                    </h2>
                    <button @click="toggleSidebar()"
                        class="hidden md:inline-flex p-2 hover:bg-gray-100 rounded-lg transition-all duration-300"
                        :class="{'justify-center w-full': isCollapsed}">
                        <svg class="w-5 h-5 text-gray-600 transition-transform duration-300" :class="{'rotate-180': isCollapsed}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7m0 0l-7 7m7-7H6" />
                        </svg>
                    </button>
                    <button @click="mobileOpen = false"
                        class="md:hidden p-2 hover:bg-gray-100 rounded-lg"
                        aria-label="Close menu">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 px-3 py-4 overflow-y-auto">
                    @include('layouts.tenant-sidebar')
                </nav>

                <div class="p-4 border-t border-gray-200">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="logout-button w-full flex items-center gap-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 rounded-lg transition-all">
                            <span class="inline-flex h-5 w-5 items-center justify-center flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </span>
                            <span class="sidebar-label">{{ __('messages.logout') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-3 sm:p-4 md:p-6 lg:p-8 overflow-auto h-full w-full min-w-0">
            @yield('content')
        </main>
    </div>

    <script>
        function sidebarState() {
            return {
                isCollapsed: true,
                mobileOpen: false,
                toggleSidebar() {
                    this.isCollapsed = !this.isCollapsed;
                    localStorage.setItem('tenantSidebarCollapsed', this.isCollapsed);
                },
                handleResize() {
                    if (window.matchMedia('(min-width: 768px)').matches) {
                        this.mobileOpen = false;
                    }
                },
                init() {
                    const collapsed = localStorage.getItem('tenantSidebarCollapsed');
                    if (collapsed !== null) this.isCollapsed = collapsed === 'true';
                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape' && this.mobileOpen) this.mobileOpen = false;
                    });
                    window.addEventListener('resize', () => this.handleResize());
                }
            }
        }
    </script>
    @stack('scripts')
</body>
</html>
