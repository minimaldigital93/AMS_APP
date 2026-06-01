<nav class="space-y-1" x-data="{ expandedSections: { 'Property': false, 'Tenant': false, 'Revenue': false, 'FiscalPeriod': false, 'Settings': false } }">
    <style>
        /* Modern sidebar styling */
        .sidebar-transition {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Creative nav link with modern design */
        .nav-link {
            position: relative;
            overflow: hidden;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, transparent 0%, transparent 100%);
            margin-bottom: 0.25rem;
        }

        /* Animated background gradient on hover */
        .nav-link::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.12) 0%, rgba(99, 102, 241, 0.12) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 0.75rem;
            box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.2);
        }

        .nav-link:hover::before {
            opacity: 1;
            box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.4);
        }

        /* Shine effect with gradient animation */
        .nav-link::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.7s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 0.75rem;
        }

        .nav-link:hover::after {
            left: 100%;
        }

        /* Icon with enhanced styling */
        .nav-icon {
            flex-shrink-0;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 0.625rem;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(99, 102, 241, 0.12) 100%);
            position: relative;
        }

        .nav-icon::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 0.625rem;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.2) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .nav-link:hover .nav-icon {
            transform: scale(1.25) rotate(-8deg);
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.28) 0%, rgba(99, 102, 241, 0.25) 100%);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .nav-link:hover .nav-icon::after {
            opacity: 1;
        }

        .nav-link:hover .nav-icon svg {
            filter: drop-shadow(0 2px 4px rgba(59, 130, 246, 0.3));
        }

        /* Active state indicator */
        .nav-link.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.22) 0%, rgba(99, 102, 241, 0.18) 100%);
            color: #3b82f6;
            font-weight: 600;
            box-shadow: inset 0 0 0 2px rgba(59, 130, 246, 0.35), 0 4px 12px rgba(59, 130, 246, 0.15);
            position: relative;
        }

        .nav-link.active::before {
            opacity: 1;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.18) 0%, rgba(99, 102, 241, 0.15) 100%);
        }

        .nav-link.active .nav-icon {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.35) 0%, rgba(99, 102, 241, 0.3) 100%);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        .nav-link.active .nav-icon svg {
            animation: iconBounce 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes iconBounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.3); }
        }

        /* Section header with creative styling */
        .section-header {
            position: relative;
            padding: 0.75rem 0.75rem;
            border-radius: 0.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            cursor: pointer;
        }

        .section-header::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: linear-gradient(to bottom, #3b82f6, #6366f1, #8b5cf6);
            opacity: 0;
            transition: opacity 0.3s ease, width 0.3s ease;
            border-radius: 3px;
        }

        .section-header:hover::before {
            opacity: 1;
            width: 4px;
        }

        .section-header::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 0.75rem;
        }

        .section-header:hover::after {
            opacity: 1;
        }

        .section-header:hover {
            color: #3b82f6;
            transform: translateX(4px);
        }


        /* Chevron rotation animation */
        .chevron {
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            transform-origin: center;
            color: #9ca3af;
        }

        .section-header:hover .chevron {
            color: #3b82f6;
            filter: drop-shadow(0 1px 2px rgba(59, 130, 246, 0.2));
        }

        /* Submenu container */
        .submenu-container {
            overflow: hidden;
        }

        /* Submenu items with stagger animation */
        .submenu-item {
            animation: slideInLeft 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            opacity: 0;
            position: relative;
            padding-left: 0.75rem;
            border-left: 3px solid rgba(59, 130, 246, 0.25);
            margin-left: 0.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .submenu-item::before {
            content: '';
            position: absolute;
            left: -3px;
            top: 0;
            height: 100%;
            width: 3px;
            background: linear-gradient(to bottom, rgba(59, 130, 246, 0.5), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .submenu-item:nth-child(1) { animation-delay: 0.05s; }
        .submenu-item:nth-child(2) { animation-delay: 0.1s; }
        .submenu-item:nth-child(3) { animation-delay: 0.15s; }
        .submenu-item:nth-child(4) { animation-delay: 0.2s; }

        .submenu-item:hover {
            border-left-color: #3b82f6;
            padding-left: 1rem;
        }

        .submenu-item:hover::before {
            opacity: 1;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-16px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Submenu link styling */
        .submenu-item .nav-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.12) 0%, rgba(139, 92, 246, 0.1) 100%);
        }

        .submenu-item:hover .nav-icon {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.25) 0%, rgba(139, 92, 246, 0.2) 100%);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.2);
            transform: scale(1.15) rotate(-8deg);
        }

        /* Section header icon styling */
        .section-icon {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 0.5rem;
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.12) 0%, rgba(107, 114, 128, 0.08) 100%);
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            color: #9ca3af;
        }

        .section-header:hover .section-icon {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(99, 102, 241, 0.15) 100%);
            color: #3b82f6;
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }

        /* Section header title styling */
        .section-title {
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: #9ca3af;
            position: relative;
            z-index: 1;
            transition: color 0.3s ease;
        }

        .section-header:hover .section-title {
            color: #3b82f6;
        }

        /* Separator line between sections */
        .section-separator {
            height: 1.5px;
            background: linear-gradient(to right, transparent 0%, rgba(59, 130, 246, 0.2) 50%, transparent 100%);
            margin: 1.25rem 0;
            border-radius: 1px;
            position: relative;
        }

        .section-separator::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.1), transparent);
            border-radius: 1px;
            filter: blur(0.5px);
        }

        /* Text styling */
        .nav-text {
            transition: all 0.2s ease;
            font-weight: 500;
            color: inherit;
        }

        /* Badge for coming soon or counts */
        .nav-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.5rem;
            height: 1.5rem;
            padding: 0 0.375rem;
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
            font-size: 0.625rem;
            font-weight: 700;
            border-radius: 0.375rem;
            margin-left: auto;
            box-shadow: 0 2px 8px rgba(251, 191, 36, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .nav-badge::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.3), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .nav-link:hover .nav-badge {
            box-shadow: 0 0 16px rgba(251, 191, 36, 0.6);
            transform: scale(1.1);
        }

        .nav-link:hover .nav-badge::before {
            opacity: 1;
        }

        /* Focused state for keyboard navigation */
        .nav-link:focus-visible {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }

        /* Mobile responsive collapse */
        @media (max-width: 768px) {
            .submenu-item {
                font-size: 0.875rem;
            }
            
            .nav-icon {
                width: 32px;
                height: 32px;
            }
        }

        /* Pulse animation for attention */
        @keyframes pulseGlow {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
            }
            50% {
                box-shadow: 0 0 0 6px rgba(59, 130, 246, 0);
            }
        }

        .nav-link.active::after {
            animation: pulseGlow 2s ease-in-out infinite;
        }
    </style>
    
    {{-- Dashboard --}}
    <a href="{{ route('admin.dashboard') }}"
       class="nav-link flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-all duration-200 {{ request()->routeIs('admin.dashboard') ? 'text-blue-700 active' : 'text-gray-700 hover:text-blue-700' }}">
        <span class="nav-icon sidebar-transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-12l2 2m-2-2v12a1 1 0 01-1 1h-4m-6 0H4a1 1 0 01-1-1V10m0 0l2-2" />
            </svg>
        </span>
        <span class="nav-text truncate sidebar-label">{{ __('messages.dashboard') }}</span>
    </a>

    {{-- User Management --}}
    <a href="{{ route('admin.users.index') }}" class="nav-link flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-all duration-200 {{ request()->routeIs('admin.users.*') ? 'text-blue-700 active' : 'text-gray-700 hover:text-blue-700' }}">
        <span class="nav-icon sidebar-transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 8.646 4 4 0 010-8.646M3 20.394A9.963 9.963 0 0112 21c4.304 0 8.196-1.702 11-4.504" />
            </svg>
        </span>
        <span class="nav-text truncate sidebar-label">{{ __('messages.user_management') }}</span>
    </a>

    <a href="{{ route('admin.billing.index') }}" class="nav-link flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium transition-all duration-200 {{ request()->routeIs('admin.billing.*') ? 'text-blue-700 active' : 'text-gray-700 hover:text-blue-700' }}">
        <span class="nav-icon sidebar-transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
        </span>
        <span class="nav-text truncate sidebar-label">{{ __('Billing') }}</span>
    </a>

    <!-- Section Separator -->
    <div class="section-separator"></div>

    {{-- Property Management Section --}}
    <div class="mt-4">
        <button @click="expandedSections['Property'] = !expandedSections['Property']" 
                class="section-header flex items-center justify-between w-full transition sidebar-transition">
            <span class="flex items-center gap-2.5">
                <span class="section-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </span>
                <span class="section-title sidebar-label">{{ __('messages.property_management') }}</span>
            </span>
            <svg class="chevron h-5 w-5 transition-transform flex-shrink-0" :class="expandedSections['Property'] ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>
        <div x-show="expandedSections['Property']" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="submenu-container mt-2 space-y-1">
            
            {{-- Floors --}}
            <a href="{{ route('admin.floors.index') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:text-blue-700 transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.floors') }}</span>
            </a>

            {{-- Apartments --}}
            <a href="{{ route('admin.apartments.index') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:text-blue-700 transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3-3h12l3 3M3 6v12a3 3 0 003 3h12a3 3 0 003-3V6M9 9h6m-6 4h6" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.apartments') }}</span>
            </a>
        </div>
    </div>

    {{-- Tenant Management Section --}}
    <div class="mt-4">
        <button @click="expandedSections['Tenant'] = !expandedSections['Tenant']" 
                class="section-header flex items-center justify-between w-full transition sidebar-transition">
            <span class="flex items-center gap-2.5">
                <span class="section-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2m-10 2H2v-2a3 3 0 015.356-1.857M7 20v-2m5-4a4 4 0 100-8 4 4 0 000 8z" />
                    </svg>
                </span>
                <span class="section-title sidebar-label">{{ __('messages.tenant_management') }}</span>
            </span>
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
            
            {{-- Active Tenants --}}
            <a href="{{ route('admin.tenants.index') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:text-blue-700 transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2m-10 2H2v-2a3 3 0 015.356-1.857M7 20v-2m5-4a4 4 0 100-8 4 4 0 000 8z" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.active_tenants') }}</span>
            </a>

            {{-- Archived Tenants --}}
            <a href="{{ route('admin.tenants.archived') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:text-blue-700 transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.archived_tenants') }}</span>
            </a>
        </div>
    </div>

    <!-- Section Separator -->
    <div class="section-separator"></div>

    {{-- Revenue & Expense Section --}}
    <div class="mt-4">
        <button @click="expandedSections['Revenue'] = !expandedSections['Revenue']" 
                class="section-header flex items-center justify-between w-full transition sidebar-transition">
            <span class="flex items-center gap-2.5">
                <span class="section-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <span class="section-title sidebar-label">{{ __('messages.revenue_expense') }}</span>
            </span>
            <svg class="chevron h-5 w-5 transition-transform flex-shrink-0" :class="expandedSections['Revenue'] ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>
        <div x-show="expandedSections['Revenue']"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="submenu-container mt-2 space-y-1">
            
            {{-- Dashboard --}}
            <a href="{{ route('admin.revenue_expense.index') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('admin.revenue_expense.index') ? 'text-blue-700 active' : 'text-gray-700 hover:text-blue-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-12l2 2m-2-2v12a1 1 0 01-1 1h-4m-6 0H4a1 1 0 01-1-1V10m0 0l2-2" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.dashboard') }}</span>
            </a>

            {{-- Income Records --}}
            <a href="{{ route('admin.revenue_expense.record_income') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('admin.revenue_expense.record_income') ? 'text-blue-700 active' : 'text-gray-700 hover:text-blue-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 12v-2m0-14a9 9 0 11-9 9 9 9 0 019-9z" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.record_income') }}</span>
            </a>

            {{-- Expense Records --}}
            <a href="{{ route('admin.revenue_expense.record_expense') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('admin.revenue_expense.record_expense') ? 'text-blue-700 active' : 'text-gray-700 hover:text-blue-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.record_expense') }}</span>
            </a>
            {{--Calendar View --}}
            <a href="{{ route('admin.revenue_expense.monthly_calendar') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('admin.revenue_expense.monthly_calendar') ? 'text-blue-700 active' : 'text-gray-700 hover:text-blue-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.monthly_calendar') }}</span>
            </a>

            {{-- Break-even Analysis --}}
            <a href="{{ route('admin.revenue_expense.break_even') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('admin.revenue_expense.break_even') ? 'text-blue-700 active' : 'text-gray-700 hover:text-blue-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.break_even') }}</span>
            </a>
        </div>
    </div>

    {{-- Fiscal Period Management Section --}}
    <div class="mt-4">
        <a href="{{ route('admin.fiscalperiod.index') }}" 
           class="section-header flex items-center justify-between w-full transition sidebar-transition {{ request()->routeIs('admin.fiscalperiod.*') ? 'text-blue-700' : '' }}">
            <span class="flex items-center gap-2.5">
                <span class="section-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </span>
                <span class="section-title sidebar-label">{{ __('messages.fiscal_period') }}</span>
            </span>
            <svg class="chevron h-5 w-5 transition-transform flex-shrink-0" :class="expandedSections['FiscalPeriod'] ? 'rotate-90' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </a>
        <div x-show="expandedSections['FiscalPeriod']"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="submenu-container mt-2 space-y-1">
            
            {{-- All Periods --}}
            <a href="{{ route('admin.fiscalperiod.index') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('admin.fiscalperiod.index') ? 'text-blue-700 active' : 'text-gray-700 hover:text-blue-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.all_periods') }}</span>
            </a>

            {{-- Create Period --}}
            <a href="{{ route('admin.fiscalperiod.create') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm {{ request()->routeIs('admin.fiscalperiod.create') ? 'text-blue-700 active' : 'text-gray-700 hover:text-blue-700' }} transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.create_period') }}</span>
            </a>

            {{-- Balance Sheet Items --}}
            <a href="{{ route('admin.fiscalperiod.index') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:text-blue-700 transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M5 6h14M7 14h10m-8 4h6" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.balance_sheet') }}</span>
            </a>

            {{-- Reports & Export --}}
            <a href="{{ route('admin.fiscalperiod.index') }}" class="submenu-item nav-link flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 hover:text-blue-700 transition-all sidebar-transition">
                <span class="nav-icon sidebar-transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </span>
                <span class="nav-text truncate sidebar-label">{{ __('messages.reports_export') }}</span>
            </a>
        </div>
    </div>

    {{-- System Settings Section (single-click to General Settings) --}}
    <div class="mt-4">
        <a href="{{ route('admin.settings.index') }}" 
           class="section-header flex items-center justify-between w-full transition sidebar-transition {{ request()->routeIs('admin.settings.*') ? 'text-blue-700' : '' }}">
            <span class="flex items-center gap-2.5">
                <span class="section-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </span>
                <span class="section-title sidebar-label">{{ __('messages.system_settings') }}</span>
            </span>
            <svg class="chevron h-5 w-5 transition-transform flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </a>
    </div>
</nav>
