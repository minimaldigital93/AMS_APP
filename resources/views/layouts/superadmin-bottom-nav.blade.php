{{-- Mobile bottom navigation for the superadmin platform panel (replaces the off-canvas sidebar on phones). --}}
@php
    $isHome     = request()->routeIs('superadmin.dashboard');
    $isAccounts = request()->routeIs('superadmin.accounts.*');
    $isFinance  = request()->routeIs('superadmin.finance.*');
    $isPlans    = request()->routeIs('superadmin.plans.*');
@endphp

<div class="md:hidden" x-data="{ sheet: null, toggle(s) { this.sheet = this.sheet === s ? null : s } }">
    <style>
        .bn-bar {
            padding-bottom: env(safe-area-inset-bottom, 0px);
            box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.08);
        }
        .bn-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            flex: 1 1 0;
            padding: 8px 0 6px;
            color: #94a3b8;
            font-size: 10px;
            line-height: 1.1;
            font-weight: 600;
            transition: color .2s ease, transform .15s ease;
        }
        .bn-item:active { transform: scale(0.92); }
        .bn-item.active { color: #818cf8; }
        .bn-item.active .bn-icon {
            background: linear-gradient(135deg, rgba(99,102,241,.28) 0%, rgba(129,140,248,.22) 100%);
            box-shadow: inset 0 0 0 1.5px rgba(129,140,248,.45);
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
            color: #e2e8f0;
            font-size: 14px;
            font-weight: 500;
            transition: background .2s ease;
        }
        .bn-sheet-link:active { background: rgba(255,255,255,.06); }
        .bn-sheet-link.active { background: linear-gradient(135deg, rgba(99,102,241,.28) 0%, rgba(129,140,248,.22) 100%); color: #c7d2fe; }
        .bn-sheet-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            flex-shrink: 0;
            background: linear-gradient(135deg, rgba(99,102,241,.28) 0%, rgba(129,140,248,.22) 100%);
            color: #c7d2fe;
        }
    </style>

    {{-- Backdrop --}}
    <div x-show="sheet" x-cloak x-transition.opacity
         @click="sheet = null"
         class="fixed inset-0 bg-black/50 z-[55]"></div>

    {{-- Sheet wrapper --}}
    <div x-show="sheet" x-cloak
         class="fixed inset-x-0 bottom-0 z-[60] px-2 pb-[calc(env(safe-area-inset-bottom,0px)+4px)]"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="translate-y-full">
        <div class="bg-gray-900 rounded-2xl shadow-2xl ring-1 ring-white/10 overflow-hidden">
            <div class="flex justify-center pt-2.5 pb-1">
                <span class="h-1.5 w-10 rounded-full bg-gray-600"></span>
            </div>

            {{-- More menu --}}
            <div x-show="sheet === 'more'" class="p-2">
                <h3 class="px-3 pt-1 pb-2 text-xs font-bold uppercase tracking-wide text-gray-500">{{ __('Menu') }}</h3>
                <div class="space-y-1">
                    <form method="POST" action="{{ route('logout') }}" class="pt-1">
                        @csrf
                        <button type="submit" class="bn-sheet-link w-full text-left" style="color:#f87171;">
                            <span class="bn-sheet-icon" style="background:linear-gradient(135deg,rgba(239,68,68,.22)0%,rgba(239,68,68,.16)100%);color:#f87171;">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            </span>
                            <span>{{ __('Log out') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom bar --}}
    <nav class="bn-bar fixed bottom-0 inset-x-0 z-[58] flex items-stretch bg-gray-900 border-t border-gray-800">
        {{-- Dashboard --}}
        <a href="{{ route('superadmin.dashboard') }}" class="bn-item {{ $isHome ? 'active' : '' }}">
            <span class="bn-icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            </span>
            <span class="bn-label">{{ __('Dashboard') }}</span>
        </a>

        {{-- Accounts --}}
        <a href="{{ route('superadmin.accounts.index') }}" class="bn-item {{ $isAccounts ? 'active' : '' }}">
            <span class="bn-icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8z"/></svg>
            </span>
            <span class="bn-label">{{ __('Accounts') }}</span>
        </a>

        {{-- Finance --}}
        <a href="{{ route('superadmin.finance.index') }}" class="bn-item {{ $isFinance ? 'active' : '' }}">
            <span class="bn-icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <span class="bn-label">{{ __('Finance') }}</span>
        </a>

        {{-- Plans --}}
        <a href="{{ route('superadmin.plans.index') }}" class="bn-item {{ $isPlans ? 'active' : '' }}">
            <span class="bn-icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </span>
            <span class="bn-label">{{ __('Plans') }}</span>
        </a>

        {{-- More (logout) --}}
        <button type="button" @click="toggle('more')" class="bn-item" :class="sheet === 'more' ? 'active' : ''">
            <span class="bn-icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
            </span>
            <span class="bn-label">{{ __('More') }}</span>
        </button>
    </nav>
</div>
