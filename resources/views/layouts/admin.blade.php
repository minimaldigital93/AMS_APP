<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.admin_dashboard_title') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        /* Sidebar animations and styling */
        * {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

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
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.2), transparent);
        }

        /* Desktop / tablet collapse states */
        @media (min-width: 768px) {
            .sidebar-collapsed { width: 80px; }
            .sidebar-expanded { width: 240px; }
        }
        @media (min-width: 1024px) {
            .sidebar-expanded { width: 280px; }
        }

        /* Mobile: sidebar becomes an off-canvas drawer */
        @media (max-width: 767px) {
            .sidebar-container {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                width: 84vw;
                max-width: 320px;
                z-index: 50;
                transform: translateX(-100%);
            }
            .sidebar-container.mobile-open { transform: translateX(0); }
            /* On mobile, always show labels (expanded look) */
            .sidebar-container .sidebar-label { opacity: 1 !important; width: auto !important; overflow: visible !important; margin: inherit !important; }
        }

        .sidebar-collapse-button {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .sidebar-collapse-button:hover {
            background-color: rgba(59, 130, 246, 0.1);
            transform: scale(1.08);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15);
        }

        .sidebar-collapse-button:active {
            transform: scale(0.96);
        }

        .sidebar-collapse-button svg {
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.05));
        }

        /* Icon animation on hover */
        .sidebar-collapse-button:hover svg {
            filter: drop-shadow(0 2px 6px rgba(59, 130, 246, 0.3));
        }

        .logout-button {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .logout-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(239, 68, 68, 0.15), transparent);
            transition: left 0.6s ease;
        }

        .logout-button:hover::before {
            left: 100%;
        }

        .logout-button:hover {
            background-color: rgba(239, 68, 68, 0.12);
            transform: translateX(2px);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.15);
        }

        .logout-button:hover svg {
            filter: drop-shadow(0 2px 4px rgba(239, 68, 68, 0.3));
        }

        [x-cloak] {
            display: none;
        }

        /* Form submit spinner */
        .spinner {
            border: 2px solid rgba(0,0,0,0.08);
            border-top-color: rgba(59,130,246,1);
            border-radius: 9999px;
            width: 1rem;
            height: 1rem;
            display: inline-block;
            vertical-align: middle;
            margin-right: 0.5rem;
            animation: spin 0.9s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Smooth transitions for sidebar text */
        .sidebar-label {
            transition: opacity 0.2s ease-in-out;
        }

        .sidebar-collapsed .sidebar-label {
            opacity: 0;
            width: 0;
            overflow: hidden;
            margin: 0;
        }

        .sidebar-expanded .sidebar-label {
            opacity: 1;
        }

        /* Border accent animation */
        aside::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 2px;
            height: 0;
            background: linear-gradient(to bottom, rgba(59, 130, 246, 0.5), transparent);
            animation: heightGrow 0.8s ease-out forwards;
        }

        @keyframes heightGrow {
            to {
                height: 100%;
            }
        }
    </style>
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

        <!-- Animated Sidebar -->
        <aside
            class="sidebar-container"
            :class="[isCollapsed ? 'sidebar-collapsed' : 'sidebar-expanded', mobileOpen ? 'mobile-open' : '']">
            <div class="bg-white shadow-lg h-full flex flex-col overflow-y-auto">
                <!-- Collapse Toggle -->
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="font-bold text-xl text-gray-800 sidebar-label" :class="{'md:hidden': isCollapsed}">
                        {{ __('messages.ams') }}
                    </h2>
                    <!-- Desktop/tablet collapse toggle -->
                    <button
                        @click="toggleSidebar()"
                        class="hidden md:inline-flex p-2 hover:bg-gray-100 rounded-lg transition-all duration-300"
                        :class="{'justify-center w-full': isCollapsed}"
                    >
                        <svg 
                            class="w-5 h-5 text-gray-600 transition-transform duration-300" 
                            :class="{'rotate-180': isCollapsed}"
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7m0 0l-7 7m7-7H6" />
                        </svg>
                    </button>
                    <!-- Mobile close button -->
                    <button
                        @click="mobileOpen = false"
                        class="md:hidden p-2 hover:bg-gray-100 rounded-lg"
                        aria-label="Close menu">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 px-3 py-4 overflow-y-auto">
                    @include('layouts.sidebar')
                </nav>

                <!-- Footer Section -->
                <div class="p-4 border-t border-gray-200">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 text-sm text-gray-700 hover:bg-red-50 hover:text-red-700 rounded-lg logout-button transition-all">
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
            @php($flashStyles = [
                'success' => 'border-green-300 bg-green-50 text-green-800',
                'error' => 'border-red-300 bg-red-50 text-red-800',
                'warning' => 'border-yellow-300 bg-yellow-50 text-yellow-800',
            ])
            @foreach ($flashStyles as $flash => $classes)
                @if (session($flash))
                    <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}">
                        {{ session($flash) }}
                    </div>
                @endif
            @endforeach
            @yield('content')
        </main>
    </div>

    <script>
        function sidebarState() {
            return {
                isCollapsed: true,
                mobileOpen: false,
                expandedSections: {
                    'Property': false,
                    'Tenant': false,
                    'Revenue': false,
                    'FiscalPeriod': false,
                    'Settings': false
                },
                toggleSidebar() {
                    this.isCollapsed = !this.isCollapsed;
                    localStorage.setItem('sidebarCollapsed', this.isCollapsed);
                },
                toggleSection(section) {
                    this.expandedSections[section] = !this.expandedSections[section];
                },
                handleKeyboard(e) {
                    // Escape closes the mobile drawer
                    if (e.key === 'Escape' && this.mobileOpen) {
                        this.mobileOpen = false;
                        return;
                    }
                    // Left/Right arrow keys toggle the desktop sidebar (md+)
                    if ((e.key === 'ArrowLeft' || e.key === 'ArrowRight') && window.matchMedia('(min-width: 768px)').matches) {
                        this.toggleSidebar();
                    }
                },
                handleResize() {
                    // Auto-close mobile drawer when resizing up to tablet/desktop
                    if (window.matchMedia('(min-width: 768px)').matches) {
                        this.mobileOpen = false;
                    }
                },
                init() {
                    const collapsed = localStorage.getItem('sidebarCollapsed');
                    if (collapsed !== null) {
                        this.isCollapsed = collapsed === 'true';
                    }
                    document.addEventListener('keydown', (e) => this.handleKeyboard(e));
                    window.addEventListener('resize', () => this.handleResize());
                }
            }
        }
    </script>
    <script>
        // Global form submit handler: show spinner and disable submits
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('submit', function (e) {
                try {
                    var form = e.target;
                    if (!(form instanceof HTMLFormElement)) return;
                    // avoid double-handling
                    if (form.dataset.submitting) return;

                    // mark as submitting
                    form.dataset.submitting = '1';

                    // find the submitter (modern browsers set e.submitter)
                    var submitter = e.submitter || form.querySelector('button[type="submit"], input[type="submit"]');

                    // disable all submit buttons/inputs inside the form
                    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
                        try { btn.disabled = true; btn.setAttribute('aria-disabled', 'true'); } catch (ex) {}
                    });

                    if (submitter) {
                        // add spinner if not present
                        if (!submitter.querySelector('.spinner')) {
                            var spinner = document.createElement('span');
                            spinner.className = 'spinner';
                            spinner.setAttribute('aria-hidden', 'true');
                            submitter.prepend(spinner);
                        }
                    }

                } catch (err) {
                    console.error('Form submit spinner error', err);
                }
            }, { capture: true });
        });
    </script>
    @stack('scripts')
</body>
</html>