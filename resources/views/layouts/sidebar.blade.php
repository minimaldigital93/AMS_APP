<nav class="px-3 space-y-1 text-sm">
    {{-- Dashboard --}}
    <a href="{{ route('admin.dashboard') }}"
       class="flex items-center gap-2 rounded-md px-3 py-2 font-medium
              {{ request()->routeIs('admin.dashboard') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
        <span class="inline-flex h-5 w-5 items-center justify-center text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-12l2 2m-2-2v12a1 1 0 01-1 1h-4m-6 0H4a1 1 0 01-1-1V10m0 0l2-2" />
            </svg>
        </span>
        <span>Dashboard</span>
    </a>

    {{-- Management section --}}
    <div class="mt-4 px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
        Management
    </div>

    <a href="#" class="flex items-center gap-2 rounded-md px-3 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900">
        <span class="inline-flex h-5 w-5 items-center justify-center text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10" />
            </svg>
        </span>
        <span>Floors</span>
    </a>

    <a href="#" class="flex items-center gap-2 rounded-md px-3 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900">
        <span class="inline-flex h-5 w-5 items-center justify-center text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
            </svg>
        </span>
        <span>Apartments</span>
    </a>

    <a href="#" class="flex items-center gap-2 rounded-md px-3 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900">
        <span class="inline-flex h-5 w-5 items-center justify-center text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2m-10 2H2v-2a3 3 0 015.356-1.857M7 20v-2m5-4a4 4 0 100-8 4 4 0 000 8z" />
            </svg>
        </span>
        <span>Tenants</span>
    </a>

    <a href="#" class="flex items-center gap-2 rounded-md px-3 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900">
        <span class="inline-flex h-5 w-5 items-center justify-center text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h10m-9-9h8a2 2 0 012 2v10a2 2 0 01-2 2H8a2 2 0 01-2-2V6a1 1 0 011-1z" />
            </svg>
        </span>
        <span>Rentals</span>
    </a>

    <a href="#" class="flex items-center gap-2 rounded-md px-3 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900">
        <span class="inline-flex h-5 w-5 items-center justify-center text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 12v-2m0-14a9 9 0 11-9 9 9 9 0 019-9z" />
            </svg>
        </span>
        <span>Payments</span>
    </a>

    {{-- Finance section --}}
    <div class="mt-4 px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
        Finance
    </div>

    <a href="#" class="flex items-center gap-2 rounded-md px-3 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900">
        <span class="inline-flex h-5 w-5 items-center justify-center text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2-2 4 4m0 0l4-4m-4 4V3" />
            </svg>
        </span>
        <span>Utilities</span>
    </a>

    <a href="#" class="flex items-center gap-2 rounded-md px-3 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900">
        <span class="inline-flex h-5 w-5 items-center justify-center text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M5 6h14M7 14h10m-8 4h6" />
            </svg>
        </span>
        <span>Accounts</span>
    </a>

    <a href="#" class="flex items-center gap-2 rounded-md px-3 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900">
        <span class="inline-flex h-5 w-5 items-center justify-center text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2v6a3 3 0 006 0v-6c0-1.105-1.343-2-3-2z" />
            </svg>
        </span>
        <span>Fiscal Periods</span>
    </a>

    {{-- System section --}}
    <div class="mt-4 px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
        System
    </div>

    <a href="#" class="flex items-center gap-2 rounded-md px-3 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900">
        <span class="inline-flex h-5 w-5 items-center justify-center text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 11V7a1 1 0 011-1h5m-6 6h4a1 1 0 011 1v4m-6-5H7a1 1 0 00-1 1v4m6-5V7m0 0H7a1 1 0 00-1 1v4m12-5v4a1 1 0 01-1 1h-4" />
            </svg>
        </span>
        <span>Settings</span>
    </a>

    <a href="#" class="flex items-center gap-2 rounded-md px-3 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900">
        <span class="inline-flex h-5 w-5 items-center justify-center text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2m14 0V9a7 7 0 00-14 0v2" />
            </svg>
        </span>
        <span>Activity Logs</span>
    </a>
</nav>
