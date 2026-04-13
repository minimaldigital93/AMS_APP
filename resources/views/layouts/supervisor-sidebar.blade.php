<nav class="space-y-1" x-data="{ expandedSections: { 'Rooms': false, 'Tenant': false, 'Payments': false } }">
    <style>
        .sidebar-transition { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-link { position: relative; overflow: hidden; border-radius: 0.75rem; margin-bottom: 0.25rem; }
        .nav-link::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(5, 150, 105, 0.12) 100%); opacity: 0; transition: opacity 0.3s ease; border-radius: 0.75rem; box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.2); }
        .nav-link:hover::before { opacity: 1; box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.4); }
        .nav-link::after { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent); transition: left 0.7s cubic-bezier(0.4, 0, 0.2, 1); border-radius: 0.75rem; }
        .nav-link:hover::after { left: 100%; }
        .nav-icon { flex-shrink: 0; transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1); display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 0.625rem; background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.12) 100%); }
        .nav-link:hover .nav-icon { transform: scale(1.25) rotate(-8deg); background: linear-gradient(135deg, rgba(16, 185, 129, 0.28) 0%, rgba(5, 150, 105, 0.25) 100%); box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3); }
        .nav-link.active { background: linear-gradient(135deg, rgba(16, 185, 129, 0.22) 0%, rgba(5, 150, 105, 0.18) 100%); color: #059669; font-weight: 600; box-shadow: inset 0 0 0 2px rgba(16, 185, 129, 0.35), 0 4px 12px rgba(16, 185, 129, 0.15); }
        .nav-link.active .nav-icon { background: linear-gradient(135deg, rgba(16, 185, 129, 0.35) 0%, rgba(5, 150, 105, 0.3) 100%); box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4); }
        .section-header { position: relative; padding: 0.75rem; border-radius: 0.75rem; transition: all 0.3s; cursor: pointer; }
        .section-header::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px; background: linear-gradient(to bottom, #10b981, #059669); opacity: 0; transition: opacity 0.3s ease; border-radius: 3px; }
        .section-header:hover::before { opacity: 1; }
        .section-header:hover { color: #059669; transform: translateX(4px); }
        .section-title { font-size: 0.75rem; letter-spacing: 0.05em; font-weight: 700; color: #9ca3af; transition: color 0.3s ease; }
        .section-header:hover .section-title { color: #059669; }
        .chevron { transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); color: #9ca3af; }
        .section-header:hover .chevron { color: #059669; }
        .section-separator { height: 1.5px; background: linear-gradient(to right, transparent, rgba(16, 185, 129, 0.2), transparent); margin: 1.25rem 0; }
        .submenu-item { animation: slideInLeft 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards; opacity: 0; padding-left: 0.75rem; border-left: 3px solid rgba(16, 185, 129, 0.25); margin-left: 0.75rem; transition: all 0.3s; }
        .submenu-item:nth-child(1) { animation-delay: 0.05s; }
        .submenu-item:nth-child(2) { animation-delay: 0.1s; }
        .submenu-item:nth-child(3) { animation-delay: 0.15s; }
        .submenu-item:hover { border-left-color: #10b981; padding-left: 1rem; }
        .submenu-item .nav-icon { width: 32px; height: 32px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(5, 150, 105, 0.1) 100%); }
        .submenu-item:hover .nav-icon { background: linear-gradient(135deg, rgba(16, 185, 129, 0.25) 0%, rgba(5, 150, 105, 0.2) 100%); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25); transform: scale(1.15) rotate(-8deg); }
        .nav-text { transition: all 0.2s ease; font-weight: 500; }
        @keyframes slideInLeft { from { opacity: 0; transform: translateX(-16px); } to { opacity: 1; transform: translateX(0); } }
    </style>
    
    {{-- Dashboard --}}
    <a href="{{ route('supervisor.dashboard') }}"
       class="nav-link flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-all duration-200 {{ request()->routeIs('supervisor.dashboard') ? 'text-emerald-700 active' : 'text-gray-700 hover:text-emerald-700' }}">
        <span class="nav-icon sidebar-transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-12l2 2m-2-2v12a1 1 0 01-1 1h-4m-6 0H4a1 1 0 01-1-1V10m0 0l2-2" />
            </svg>
        </span>
        <span class="nav-text truncate sidebar-label">{{ __('messages.dashboard') }}</span>
    </a>

    <!-- Section Separator -->
    <div class="section-separator"></div>

    {{-- Room Management Section --}}
    <div class="mt-4">
        <button @click="expandedSections['Rooms'] = !expandedSections['Rooms']" 
                class="section-header flex items-center justify-between w-full transition sidebar-transition">
            <span class="section-title sidebar-label">🏠 {{ __('messages.apartments') }}</span>
            <svg class="chevron h-5 w-5 transition-transform flex-shrink-0" :class="expandedSections['Rooms'] ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>
        <div x-show="expandedSections['Rooms']" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="submenu-container mt-2 space-y-1">
            
            <a href="{{ route('supervisor.apartments.index') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('supervisor.apartments.*') ? 'text-emerald-700 active' : 'text-gray-700 hover:text-emerald-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3-3h12l3 3M3 6v12a3 3 0 003 3h12a3 3 0 003-3V6M9 9h6m-6 4h6" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">My Rooms</span>
            </a>
        </div>
    </div>

    {{-- Tenant Management Section --}}
    <div class="mt-4">
        <button @click="expandedSections['Tenant'] = !expandedSections['Tenant']" 
                class="section-header flex items-center justify-between w-full transition sidebar-transition">
            <span class="section-title sidebar-label">👥 {{ __('messages.tenant_management') }}</span>
            <svg class="chevron h-5 w-5 transition-transform flex-shrink-0" :class="expandedSections['Tenant'] ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>
        <div x-show="expandedSections['Tenant']"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="submenu-container mt-2 space-y-1">
            
            <a href="{{ route('supervisor.tenants.index') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('supervisor.tenants.index') ? 'text-emerald-700 active' : 'text-gray-700 hover:text-emerald-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2m-10 2H2v-2a3 3 0 015.356-1.857M7 20v-2m5-4a4 4 0 100-8 4 4 0 000 8z" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.active_tenants') }}</span>
            </a>

            <a href="{{ route('supervisor.tenants.create') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('supervisor.tenants.create') ? 'text-emerald-700 active' : 'text-gray-700 hover:text-emerald-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">Register Tenant</span>
            </a>

            <a href="{{ route('supervisor.tenants.archived') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('supervisor.tenants.archived') ? 'text-emerald-700 active' : 'text-gray-700 hover:text-emerald-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">Departures</span>
            </a>
        </div>
    </div>

    <!-- Section Separator -->
    <div class="section-separator"></div>

    {{-- Payments Section --}}
    <div class="mt-4">
        <button @click="expandedSections['Payments'] = !expandedSections['Payments']" 
                class="section-header flex items-center justify-between w-full transition sidebar-transition">
            <span class="section-title sidebar-label">💰 Payments</span>
            <svg class="chevron h-5 w-5 transition-transform flex-shrink-0" :class="expandedSections['Payments'] ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>
        <div x-show="expandedSections['Payments']"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="submenu-container mt-2 space-y-1">
            
            <a href="{{ route('supervisor.payments.index') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('supervisor.payments.index') ? 'text-emerald-700 active' : 'text-gray-700 hover:text-emerald-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">All Payments</span>
            </a>

            <a href="{{ route('supervisor.payments.create') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('supervisor.payments.create') ? 'text-emerald-700 active' : 'text-gray-700 hover:text-emerald-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">Record Payment</span>
            </a>
        </div>
    </div>
</nav>
