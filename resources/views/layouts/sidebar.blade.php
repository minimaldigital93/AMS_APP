<nav class="space-y-2">
    <style>
        .sidebar-slide-enter {
            transform: translateX(-100%);
            opacity: 0;
        }
        .sidebar-slide-enter-active {
            transform: translateX(0);
            opacity: 1;
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s;
        }
        .sidebar-slide-leave {
            transform: translateX(0);
            opacity: 1;
        }
        .sidebar-slide-leave-active {
            transform: translateX(-100%);
            opacity: 0;
            transition: transform 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s;
        }
    </style>
    {{-- Dashboard --}}
    <a href="{{ route('admin.dashboard') }}"
           class="flex items-center gap-2 rounded px-2 py-2 text-sm font-medium transition-colors duration-200 {{ request()->routeIs('admin.dashboard') ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-blue-50 hover:text-blue-700' }}">
        <span class="inline-flex h-5 w-5 items-center justify-center flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-12l2 2m-2-2v12a1 1 0 01-1 1h-4m-6 0H4a1 1 0 01-1-1V10m0 0l2-2" />
            </svg>
        </span>
        <span class="truncate">Dashboard</span>
    </a>

    {{-- Management section --}}
    <div class="mt-6">
        <button @click="toggleSection('Management')" class="flex items-center justify-between w-full px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 transition">
            <span>Management</span>
            <svg class="h-4 w-4 transform transition-transform" :class="expandedSections['Management'] ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
        </button>
            <div x-show="expandedSections['Management']" x-transition:enter="sidebar-slide-enter" x-transition:enter-active="sidebar-slide-enter-active" x-transition:leave="sidebar-slide-leave" x-transition:leave-active="sidebar-slide-leave-active" class="mt-2 space-y-1">
            <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all">
                <span class="inline-flex h-5 w-5 items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10" />
                    </svg>
                </span>
                <span class="truncate">Floors</span>
            </a>

            <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all">
                <span class="inline-flex h-5 w-5 items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
                    </svg>
                </span>
                <span class="truncate">Apartments</span>
            </a>

            <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all">
                <span class="inline-flex h-5 w-5 items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2m-10 2H2v-2a3 3 0 015.356-1.857M7 20v-2m5-4a4 4 0 100-8 4 4 0 000 8z" />
                    </svg>
                </span>
                <span class="truncate">Tenants</span>
            </a>

            <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all">
                <span class="inline-flex h-5 w-5 items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h10m-9-9h8a2 2 0 012 2v10a2 2 0 01-2 2H8a2 2 0 01-2-2V6a1 1 0 011-1z" />
                    </svg>
                </span>
                <span class="truncate">Rentals</span>
            </a>

            <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all">
                <span class="inline-flex h-5 w-5 items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 12v-2m0-14a9 9 0 11-9 9 9 9 0 019-9z" />
                    </svg>
                </span>
                <span class="truncate">Payments</span>
            </a>
        </div>
    </div>

    {{-- Finance section --}}
    <div class="mt-6">
        <button @click="toggleSection('Finance')" class="flex items-center justify-between w-full px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 transition">
            <span>Finance</span>
            <svg class="h-4 w-4 transform transition-transform" :class="expandedSections['Finance'] ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
        </button>
            <div x-show="expandedSections['Finance']" x-transition:enter="sidebar-slide-enter" x-transition:enter-active="sidebar-slide-enter-active" x-transition:leave="sidebar-slide-leave" x-transition:leave-active="sidebar-slide-leave-active" class="mt-2 space-y-1">
            <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all">
                <span class="inline-flex h-5 w-5 items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2-2 4 4m0 0l4-4m-4 4V3" />
                    </svg>
                </span>
                <span class="truncate">Utilities</span>
            </a>

            <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all">
                <span class="inline-flex h-5 w-5 items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M5 6h14M7 14h10m-8 4h6" />
                    </svg>
                </span>
                <span class="truncate">Accounts</span>
            </a>

            <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all">
                <span class="inline-flex h-5 w-5 items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2v6a3 3 0 006 0v-6c0-1.105-1.343-2-3-2z" />
                    </svg>
                </span>
                <span class="truncate">Fiscal Periods</span>
            </a>
        </div>
    </div>

    {{-- System section --}}
    <div class="mt-6">
        <button @click="toggleSection('System')" class="flex items-center justify-between w-full px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-700 transition">
            <span>System</span>
            <svg class="h-4 w-4 transform transition-transform" :class="expandedSections['System'] ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
        </button>
            <div x-show="expandedSections['System']" x-transition:enter="sidebar-slide-enter" x-transition:enter-active="sidebar-slide-enter-active" x-transition:leave="sidebar-slide-leave" x-transition:leave-active="sidebar-slide-leave-active" class="mt-2 space-y-1">
            <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all">
                <span class="inline-flex h-5 w-5 items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 11V7a1 1 0 011-1h5m-6 6h4a1 1 0 011 1v4m-6-5H7a1 1 0 00-1 1v4m6-5V7m0 0H7a1 1 0 00-1 1v4m12-5v4a1 1 0 01-1 1h-4" />
                    </svg>
                </span>
                <span class="truncate">Settings</span>
            </a>

            <a href="#" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-all">
                <span class="inline-flex h-5 w-5 items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2m14 0V9a7 7 0 00-14 0v2" />
                    </svg>
                </span>
                <span class="truncate">Activity Logs</span>
            </a>
        </div>
    </div>
</nav>
