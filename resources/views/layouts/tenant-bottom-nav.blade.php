{{-- Mobile bottom navigation for the tenant panel (replaces the off-canvas sidebar on phones).

     The tenant portal is primarily a phone experience, and until this existed
     the only way to any page but the dashboard was the hamburger drawer — so a
     new page could render perfectly and still be unreachable where it is
     actually used. Two destinations is the whole panel, so there are no
     sheets: every item is a direct link. --}}
@php
    $isHome     = request()->routeIs('tenant.dashboard');
    $isPayments = request()->routeIs('tenant.payments.*');
@endphp

<div class="md:hidden no-print">
    <style>
        /* Tenant panel identity: indigo accent; neutrals follow the active
           theme's tokens so non-default themes carry through on phones. */
        .bn-bar {
            --bn-accent: #4f46e5;
            padding-bottom: env(safe-area-inset-bottom, 0px);
            box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.08);
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
        .bn-item.active { color: var(--bn-accent, #4f46e5); }
        .bn-item.active .bn-icon {
            background: color-mix(in srgb, var(--bn-accent, #4f46e5) 16%, transparent);
            box-shadow: inset 0 0 0 1.5px color-mix(in srgb, var(--bn-accent, #4f46e5) 35%, transparent);
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
        .bn-label { max-width: 96px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    </style>

    <nav class="bn-bar fixed inset-x-0 bottom-0 z-50 flex items-stretch border-t">
        <a href="{{ route('tenant.dashboard') }}" class="bn-item {{ $isHome ? 'active' : '' }}">
            <span class="bn-icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-9 2v8m4-8v8m5-12l2 2m-2-2v12a1 1 0 01-1 1h-4m-6 0H4a1 1 0 01-1-1V10m0 0l2-2"/></svg>
            </span>
            <span class="bn-label">{{ __('messages.dashboard') }}</span>
        </a>

        <a href="{{ route('tenant.payments.index') }}" class="bn-item {{ $isPayments ? 'active' : '' }}">
            <span class="bn-icon">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </span>
            <span class="bn-label">{{ __('messages.my_payments') }}</span>
        </a>
    </nav>

    {{-- Clearance so the last card is never trapped under the bar. --}}
    <div aria-hidden="true" style="height: calc(64px + env(safe-area-inset-bottom, 0px));"></div>
</div>
