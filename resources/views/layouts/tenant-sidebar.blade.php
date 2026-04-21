<nav class="space-y-1">
    <style>
        .tenant-nav-link { position: relative; overflow: hidden; border-radius: 0.75rem; margin-bottom: 0.25rem; }
        .tenant-nav-link::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(79, 70, 229, 0.12) 100%); opacity: 0; transition: opacity 0.3s ease; border-radius: 0.75rem; box-shadow: inset 0 0 0 1px rgba(99, 102, 241, 0.2); }
        .tenant-nav-link:hover::before { opacity: 1; }
        .tenant-nav-icon { flex-shrink: 0; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 0.625rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(79, 70, 229, 0.12) 100%); }
        .tenant-nav-link:hover .tenant-nav-icon { transform: scale(1.25) rotate(-8deg); background: linear-gradient(135deg, rgba(99, 102, 241, 0.28) 0%, rgba(79, 70, 229, 0.25) 100%); box-shadow: 0 6px 20px rgba(99, 102, 241, 0.3); }
        .tenant-nav-link.active { background: linear-gradient(135deg, rgba(99, 102, 241, 0.22) 0%, rgba(79, 70, 229, 0.18) 100%); color: #4f46e5; font-weight: 600; box-shadow: inset 0 0 0 2px rgba(99, 102, 241, 0.35), 0 4px 12px rgba(99, 102, 241, 0.15); }
        .tenant-nav-link.active .tenant-nav-icon { background: linear-gradient(135deg, rgba(99, 102, 241, 0.35) 0%, rgba(79, 70, 229, 0.3) 100%); box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4); }
        .nav-separator { height: 1.5px; background: linear-gradient(to right, transparent, rgba(99, 102, 241, 0.2), transparent); margin: 1.25rem 0; }
    </style>

    {{-- Dashboard --}}
    <a href="{{ route('tenant.dashboard') }}"
       class="tenant-nav-link flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-all duration-200 {{ request()->routeIs('tenant.dashboard') ? 'text-indigo-700 active' : 'text-gray-700 hover:text-indigo-700' }}">
        <span class="tenant-nav-icon">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-12l2 2m-2-2v12a1 1 0 01-1 1h-4m-6 0H4a1 1 0 01-1-1V10m0 0l2-2" />
            </svg>
        </span>
        <span class="truncate sidebar-label">{{ __('messages.dashboard') }}</span>
    </a>

    <div class="nav-separator"></div>

    {{-- My Payments --}}
    <a href="{{ route('tenant.payments.index') }}"
       class="tenant-nav-link flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-all duration-200 {{ request()->routeIs('tenant.payments.*') ? 'text-indigo-700 active' : 'text-gray-700 hover:text-indigo-700' }}">
        <span class="tenant-nav-icon">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
        </span>
        <span class="truncate sidebar-label">My Payments</span>
    </a>
</nav>
