{{-- Mobile bottom navigation (replaces the off-canvas sidebar on phones). --}}
@php
    $isHome     = request()->routeIs('admin.dashboard');
    $isProperty = request()->routeIs('admin.properties.*', 'admin.floors.*', 'admin.apartments.*');
    $isTenant   = request()->routeIs('admin.tenants.*');
    $isRevenue  = request()->routeIs('admin.revenue_expense.*');
    $isMore     = request()->routeIs('admin.users.*', 'admin.fiscalperiod.*', 'admin.billing.*', 'admin.settings.*');
@endphp

<div class="md:hidden" x-data="{ sheet: null, toggle(s) { this.sheet = this.sheet === s ? null : s } }">
    <style>
        .bn-bar {
            padding-bottom: env(safe-area-inset-bottom, 0px);
            box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.08);
            /* Theme-token chrome (out-specifies the Tailwind bg/border utilities). */
            background: var(--topbar-bg);
            border-top-color: var(--border-color);
        }
        .bn-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            flex: 1 1 0;
            padding: 8px 0 6px;
            color: var(--text-secondary);
            font-size: 10px;
            line-height: 1.1;
            font-weight: 600;
            transition: color .2s ease, transform .15s ease;
        }
        .bn-item:active { transform: scale(0.92); }
        .bn-item.active { color: var(--accent-color); }
        .bn-item.active .bn-icon {
            background: color-mix(in srgb, var(--accent-color) 16%, transparent);
            box-shadow: inset 0 0 0 1.5px color-mix(in srgb, var(--accent-color) 35%, transparent);
        }
        .bn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 30px;
            border-radius: 10px;
            transition: background .2s ease, box-shadow .2s ease;
        }
        .bn-label { max-width: 64px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        /* Bottom sheet item */
        .bn-sheet-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            transition: background .2s ease;
        }
        .bn-sheet-link:active { background: var(--hover-bg); }
        .bn-sheet-link.active { background: color-mix(in srgb, var(--accent-color) 13%, transparent); color: var(--accent-color); }
        .bn-sheet-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            flex-shrink: 0;
            background: color-mix(in srgb, var(--accent-color) 13%, transparent);
            color: var(--accent-color);
        }
    </style>

    {{-- Backdrop --}}
    <div x-show="sheet" x-cloak x-transition.opacity
         @click="sheet = null"
         class="fixed inset-0 bg-black/40 z-[55]"></div>

    {{-- Sheet wrapper (shared transition for all sheets) --}}
    <div x-show="sheet" x-cloak
         class="fixed inset-x-0 bottom-0 z-[60] px-2 pb-[calc(env(safe-area-inset-bottom,0px)+4px)]"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full">
            <div class="bg-white rounded-2xl shadow-2xl ring-1 ring-black/5 overflow-hidden">
                <div class="flex justify-center pt-2.5 pb-1">
                    <span class="h-1.5 w-10 rounded-full bg-gray-300"></span>
                </div>

                {{-- Property Management --}}
                <div x-show="sheet === 'property'" class="p-2">
                    <h3 class="px-3 pt-1 pb-2 text-xs font-bold uppercase tracking-wide text-gray-400">{{ __('messages.property_management') }}</h3>
                    <div class="space-y-1">
                        <a href="{{ route('admin.properties.index') }}" class="bn-sheet-link {{ request()->routeIs('admin.properties.*') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V7a2 2 0 00-2-2h-3V3H6v18m13 0H5m14 0h2M5 21H3m6-14h.01M9 11h.01M9 15h.01M13 11h.01M13 15h.01"/></svg>
                            </span>
                            <span>{{ __('messages.properties') }}</span>
                        </a>
                        <a href="{{ route('admin.floors.index') }}" class="bn-sheet-link {{ request()->routeIs('admin.floors.*', 'admin.apartments.*') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/></svg>
                            </span>
                            <span>{{ __('messages.floors_and_rooms') }}</span>
                        </a>
                    </div>
                </div>

                {{-- Tenant Management --}}
                <div x-show="sheet === 'tenant'" class="p-2">
                    <h3 class="px-3 pt-1 pb-2 text-xs font-bold uppercase tracking-wide text-gray-400">{{ __('messages.tenant_management') }}</h3>
                    <div class="space-y-1">
                        <a href="{{ route('admin.tenants.index') }}" class="bn-sheet-link {{ request()->routeIs('admin.tenants.index') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2m-10 2H2v-2a3 3 0 015.356-1.857M7 20v-2m5-4a4 4 0 100-8 4 4 0 000 8z"/></svg>
                            </span>
                            <span>{{ __('messages.active_tenants') }}</span>
                        </a>
                        <a href="{{ route('admin.tenants.archived') }}" class="bn-sheet-link {{ request()->routeIs('admin.tenants.archived') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                            </span>
                            <span>{{ __('messages.archived_tenants') }}</span>
                        </a>
                    </div>
                </div>

                {{-- Revenue & Expense --}}
                <div x-show="sheet === 'revenue'" class="p-2">
                    <h3 class="px-3 pt-1 pb-2 text-xs font-bold uppercase tracking-wide text-gray-400">{{ __('messages.revenue_expense') }}</h3>
                    <div class="space-y-1">
                        <a href="{{ route('admin.revenue_expense.index') }}" class="bn-sheet-link {{ request()->routeIs('admin.revenue_expense.index') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-12l2 2m-2-2v12a1 1 0 01-1 1h-4m-6 0H4a1 1 0 01-1-1V10m0 0l2-2"/></svg>
                            </span>
                            <span>{{ __('messages.dashboard') }}</span>
                        </a>
                        <a href="{{ route('admin.revenue_expense.record_income') }}" class="bn-sheet-link {{ request()->routeIs('admin.revenue_expense.record_income') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 12v-2m0-14a9 9 0 11-9 9 9 9 0 019-9z"/></svg>
                            </span>
                            <span>{{ __('messages.record_income') }}</span>
                        </a>
                        <a href="{{ route('admin.revenue_expense.record_expense') }}" class="bn-sheet-link {{ request()->routeIs('admin.revenue_expense.record_expense') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            </span>
                            <span>{{ __('messages.record_expense') }}</span>
                        </a>
                        <a href="{{ route('admin.revenue_expense.monthly_calendar') }}" class="bn-sheet-link {{ request()->routeIs('admin.revenue_expense.monthly_calendar') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                            <span>{{ __('messages.monthly_calendar') }}</span>
                        </a>
                        <a href="{{ route('admin.revenue_expense.break_even') }}" class="bn-sheet-link {{ request()->routeIs('admin.revenue_expense.break_even') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </span>
                            <span>{{ __('messages.break_even') }}</span>
                        </a>
                    </div>
                </div>

                {{-- More --}}
                <div x-show="sheet === 'more'" class="p-2">
                    <h3 class="px-3 pt-1 pb-2 text-xs font-bold uppercase tracking-wide text-gray-400">{{ __('messages.menu') }}</h3>
                    <div class="space-y-1">
                        <a href="{{ route('admin.users.index') }}" class="bn-sheet-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 8.646 4 4 0 010-8.646M3 20.394A9.963 9.963 0 0112 21c4.304 0 8.196-1.702 11-4.504"/></svg>
                            </span>
                            <span>{{ __('messages.user_management') }}</span>
                        </a>
                        <a href="{{ route('admin.fiscalperiod.index') }}" class="bn-sheet-link {{ request()->routeIs('admin.fiscalperiod.*') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                            <span>{{ __('messages.fiscal_period') }}</span>
                        </a>
                        <a href="{{ route('admin.billing.index') }}" class="bn-sheet-link {{ request()->routeIs('admin.billing.*') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </span>
                            <span>{{ __('messages.billing') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.payment') }}" class="bn-sheet-link {{ request()->routeIs('admin.settings.payment*') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 4h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </span>
                            <span>{{ __('messages.payment_settings') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.index') }}" class="bn-sheet-link {{ request()->routeIs('admin.settings.*') && ! request()->routeIs('admin.settings.payment*') ? 'active' : '' }}">
                            <span class="bn-sheet-icon">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </span>
                            <span>{{ __('messages.system_settings') }}</span>
                        </a>
                        <form method="POST" action="{{ route('logout') }}" class="pt-1">
                            @csrf
                            <button type="submit" class="bn-sheet-link w-full text-left" style="color:#dc2626;">
                                <span class="bn-sheet-icon" style="background:linear-gradient(135deg,rgba(239,68,68,.14)0%,rgba(239,68,68,.1)100%);color:#dc2626;">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                </span>
                                <span>{{ __('messages.logout') }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
    </div>

    {{-- Bottom bar --}}
    <nav class="bn-bar fixed bottom-0 inset-x-0 z-[58] flex items-stretch bg-white border-t border-gray-200">
        {{-- Home --}}
        <a href="{{ route('admin.dashboard') }}" class="bn-item {{ $isHome ? 'active' : '' }}">
            <span class="bn-icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-12l2 2m-2-2v12a1 1 0 01-1 1h-4m-6 0H4a1 1 0 01-1-1V10m0 0l2-2"/></svg>
            </span>
            <span class="bn-label">{{ __('messages.home') }}</span>
        </a>

        {{-- Property Management --}}
        <button type="button" @click="toggle('property')" class="bn-item {{ $isProperty ? 'active' : '' }}" :class="sheet === 'property' ? 'active' : ''">
            <span class="bn-icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </span>
            <span class="bn-label">{{ __('messages.nav_property') }}</span>
        </button>

        {{-- Tenant Management --}}
        <button type="button" @click="toggle('tenant')" class="bn-item {{ $isTenant ? 'active' : '' }}" :class="sheet === 'tenant' ? 'active' : ''">
            <span class="bn-icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2m-10 2H2v-2a3 3 0 015.356-1.857M7 20v-2m5-4a4 4 0 100-8 4 4 0 000 8z"/></svg>
            </span>
            <span class="bn-label">{{ __('messages.nav_tenants') }}</span>
        </button>

        {{-- Revenue & Expense --}}
        <button type="button" @click="toggle('revenue')" class="bn-item {{ $isRevenue ? 'active' : '' }}" :class="sheet === 'revenue' ? 'active' : ''">
            <span class="bn-icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <span class="bn-label">{{ __('messages.nav_finance') }}</span>
        </button>

        {{-- More (3-dot) --}}
        <button type="button" @click="toggle('more')" class="bn-item {{ $isMore ? 'active' : '' }}" :class="sheet === 'more' ? 'active' : ''">
            <span class="bn-icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
            </span>
            <span class="bn-label">{{ __('messages.more') }}</span>
        </button>
    </nav>
</div>
