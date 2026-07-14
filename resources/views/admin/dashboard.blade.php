@extends('layouts.admin')

@section('title', __('messages.dashboard'))

@section('content')
<div class="max-w-6xl mx-auto space-y-8">

    {{-- Subscription renewal alert (due within 3 days) --}}
    @if(!empty($subscriptionAlert))
    <div x-data="{ show: true }" x-show="show"
         class="rounded-lg px-4 py-3 text-sm flex items-center justify-between gap-3 border {{ $subscriptionAlert['color'] === 'red' ? 'bg-red-50 border-red-100 text-red-700' : 'bg-amber-50 border-amber-100 text-amber-700' }}">
        <div class="flex items-center gap-2.5">
            <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <span class="font-medium">{{ $subscriptionAlert['message'] }}</span>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ $subscriptionAlert['url'] }}"
               class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium text-white transition {{ $subscriptionAlert['color'] === 'red' ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-600 hover:bg-amber-700' }}">
                {{ __('messages.renew_now') }}
            </a>
            <button type="button" @click="show = false" class="opacity-60 hover:opacity-100 transition" aria-label="{{ __('messages.dismiss') }}">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        </div>
    </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-row items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.dashboard') }}</h1>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('admin.floors.plan3d') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-sm" title="{{ __('messages.floor_view_3d') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg></a>
            @if($activePeriod)
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                {{ $activePeriod->name }}
            </span>
            @endif
        </div>
    </div>

    @if($activePeriod && count($periodMonths) > 0)
    <div class="flex items-center justify-center">
        <div class="inline-flex max-w-full items-center bg-white rounded-xl border border-slate-100 px-1.5 sm:px-2 py-1.5 gap-0.5 sm:gap-1">
            @if($monthNavigation['previousMonth'])
            <a href="{{ route('admin.dashboard', ['month' => $monthNavigation['previousMonth']['month'], 'year' => $monthNavigation['previousMonth']['year']]) }}"
               class="inline-flex shrink-0 items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ __('messages.previous_month') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            @else
            <span class="inline-flex shrink-0 items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-lg text-slate-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </span>
            @endif

            <div class="px-2 sm:px-4 py-2 min-w-0 sm:min-w-[220px] text-center">
                @if($isFullPeriod)
                    <span class="text-base sm:text-lg font-bold text-slate-800">{{ __('messages.all_months') }}</span>
                    <span class="ml-2 hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">{{ __('messages.full_period') }}</span>
                @else
                <span class="text-base sm:text-lg font-bold text-slate-800 whitespace-nowrap">{{ $displayMonth->format('F') }}</span>
                <span class="text-base sm:text-lg text-slate-500 ml-1">{{ $displayMonth->format('Y') }}</span>
                @if($monthNavigation['isCurrentMonth'])
                    <span class="ml-2 hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">{{ __('messages.current') }}</span>
                @elseif($displayMonth->isFuture())
                    <span class="ml-2 hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">{{ __('messages.upcoming') }}</span>
                @else
                    <span class="ml-2 hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">{{ __('messages.past') }}</span>
                @endif
                @endif
            </div>

            @if($monthNavigation['nextMonth'])
            <a href="{{ route('admin.dashboard', ['month' => $monthNavigation['nextMonth']['month'], 'year' => $monthNavigation['nextMonth']['year']]) }}"
               class="inline-flex shrink-0 items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ __('messages.next_month') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @else
            <span class="inline-flex shrink-0 items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-lg text-slate-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
            @endif

                @if(!$isFullPeriod)
                <a href="{{ route('admin.dashboard', ['view' => 'all']) }}"
                    class="ml-0.5 sm:ml-1 shrink-0 inline-flex items-center px-2 sm:px-3 py-2 text-sm font-medium text-slate-600 bg-slate-50 rounded-lg hover:bg-slate-100 transition" title="{{ __('messages.view_full_fiscal_period') }}">
                     <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg></a>
                @endif

                @if(($isFullPeriod || !$monthNavigation['isCurrentMonth']) && $monthNavigation['currentMonthInPeriod'])
            <a href="{{ route('admin.dashboard', ['month' => now()->month, 'year' => now()->year]) }}"
               class="ml-0.5 sm:ml-1 shrink-0 inline-flex items-center px-2 sm:px-3 py-2 text-sm font-medium text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition" title="{{ __('messages.go_to_current_month') }}">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></a>
            @endif
        </div>
    </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Monthly Revenue --}}
        <div class="bg-white rounded-xl border border-slate-100 p-5 summary-card cursor-pointer" data-card="revenue">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-emerald-50 flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.revenue') }}</p>
                    <p class="text-xl font-bold text-slate-800">{{ money($stats['revenue']['total_monthly'] ?? 0) }}</p>
                </div>
            </div>
            <div class="summary-details hidden">
            <p class="text-[11px] text-slate-400 mt-2 space-y-0.5">
                @php $byType = $stats['revenue']['by_type'] ?? []; @endphp
                @if(($byType['rent'] ?? 0) > 0)
                    <span class="flex justify-between"><span>{{ __('messages.rent') }}</span><span class="font-medium text-green-500">+{{ money($byType['rent']) }}</span></span>
                @endif
                @if(($byType['deposit'] ?? 0) > 0)
                    <span class="flex justify-between"><span>{{ __('messages.deposit') }}</span><span class="font-medium text-green-500">+{{ money($byType['deposit']) }}</span></span>
                @endif
                @if(($byType['utilities'] ?? 0) > 0)
                    <span class="flex justify-between"><span>{{ __('messages.utilities') }}</span><span class="font-medium text-green-500">+{{ money($byType['utilities']) }}</span></span>
                @endif
                @if(($stats['revenue']['late_fees_this_month'] ?? 0) > 0)
                    <span class="flex justify-between"><span>{{ __('messages.late_fees') }}</span><span class="font-medium text-green-500">+{{ money($stats['revenue']['late_fees_this_month']) }}</span></span>
                @endif
                @if(($byType['other'] ?? 0) > 0)
                    <span class="flex justify-between"><span>{{ __('messages.other') }}</span><span class="font-medium text-green-500">+{{ money($byType['other']) }}</span></span>
                @endif
            </p>
            </div>
        </div>

        {{-- Monthly Expenses --}}
        <div class="bg-white rounded-xl border border-slate-100 p-5 summary-card cursor-pointer" data-card="expenses">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-red-50 flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.expense') }}</p>
                    <p class="text-xl font-bold text-slate-800">{{ money($stats['expenses']['monthly_total'] ?? 0) }}</p>
                </div>
            </div>
            <div class="summary-details hidden">
            <p class="text-[11px] text-slate-400 mt-2 space-y-0.5">
                @if(($stats['expenses']['utilities_total'] ?? 0) > 0)
                    <span class="flex justify-between"><span>{{ __('messages.utilities') }}</span><span class="font-medium text-red-400">-{{ money($stats['expenses']['utilities_total']) }}</span></span>
                @endif
                @foreach(($stats['expenses']['account_breakdown'] ?? []) as $cat => $amt)
                    @if($amt > 0)
                        <span class="flex justify-between"><span>{{ str_replace('_', ' ', ucfirst($cat)) }}</span><span class="font-medium text-red-400">-{{ money($amt) }}</span></span>
                    @endif
                @endforeach
            </p>
            </div>
        </div>

        {{-- Net Profit --}}
        @php
            $netProfit = ($stats['revenue']['total_monthly'] ?? 0) - ($stats['expenses']['monthly_total'] ?? 0);
        @endphp
        <div class="bg-white rounded-xl border border-slate-100 p-5 summary-card cursor-pointer" data-card="netprofit">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-full {{ $netProfit >= 0 ? 'bg-sky-50' : 'bg-orange-50' }} flex items-center justify-center">
                    <svg class="w-6 h-6 {{ $netProfit >= 0 ? 'text-blue-600' : 'text-orange-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.net_profit') }}</p>
                    <p class="text-xl font-bold {{ $netProfit >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ $netProfit >= 0 ? '+' : '' }}{{ money($netProfit) }}
                    </p>
                </div>
            </div>
            <div class="summary-details hidden">
            <p class="text-[11px] text-slate-400 mt-2 space-y-0.5">
                <span class="flex justify-between"><span>{{ __('messages.revenue') }}</span><span class="font-medium text-emerald-500">+{{ money($stats['revenue']['total_monthly'] ?? 0) }}</span></span>
                <span class="flex justify-between"><span>{{ __('messages.expense') }}</span><span class="font-medium text-red-400">-{{ money($stats['expenses']['monthly_total'] ?? 0) }}</span></span>
            </p>
                </div>
            </div>

        {{-- Occupancy --}}
        <div class="bg-white rounded-xl border border-slate-100 p-5 summary-card cursor-pointer" data-card="occupancy">
            <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-purple-50 flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.occupied_total') }}</p>
                    <p class="text-xl font-bold text-slate-800">{{ $stats['apartments']['occupied'] }} / {{ $stats['apartments']['total'] }}</p>
                </div>
            </div>
            <div class="summary-details hidden">
            @php $occRate = $stats['apartments']['total'] > 0 ? round(($stats['apartments']['occupied'] / $stats['apartments']['total']) * 100, 1) : 0; @endphp
            <p class="text-[11px] text-slate-400 mt-2 space-y-0.5">
                <span class="flex justify-between"><span>{{ __('messages.occupancy_rate') }}</span><span class="font-medium {{ $occRate >= 80 ? 'text-emerald-500' : ($occRate >= 50 ? 'text-amber-500' : 'text-red-500') }}">{{ $occRate }}%</span></span>
                <span class="flex justify-between"><span>{{ __('messages.available') }}</span><span class="font-medium text-slate-500">{{ $stats['apartments']['available'] }}</span></span>
            </p>
            </div>
        </div>
    </div>

    {{-- No Fiscal Period Warning --}}
    @if(!$fiscalData['has_active_period'])
    <div class="bg-yellow-50 border border-yellow-300 rounded-xl p-6">
        <div class="flex items-start gap-3">
            <svg class="w-6 h-6 text-yellow-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <div>
                <h3 class="font-bold text-yellow-900">{{ __('messages.no_active_fiscal_period') }}</h3>
                <p class="text-sm text-yellow-800 mt-1">{{ __('messages.create_fiscal_period_prompt') }}</p>
                <a href="{{ route('admin.fiscalperiod.create') }}" class="inline-block mt-3 bg-yellow-600 text-white px-5 py-2 rounded-lg hover:bg-yellow-700 transition text-sm font-medium">
                    {{ __('messages.create_fiscal_period') }}
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- Payment Status Quick View --}}
    @if($fiscalData['has_active_period'] && !$isFullPeriod)
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="{{ route('admin.revenue_expense.record_income', ['month' => $displayMonth->month, 'year' => $displayMonth->year, 'filter' => 'paid']) }}"
            class="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between hover:border-emerald-300 hover:shadow-sm transition">
            <div>
                <p class="flex items-center gap-2 text-sm font-medium text-slate-600">
                    <span class="p-1.5 rounded-full bg-emerald-50 inline-flex items-center justify-center">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    {{ __('messages.paid') }}
                </p>
                <p class="text-2xl font-bold text-slate-800">{{ $stats['payments']['paid'] }}</p>
                @if(!empty($stats['tenants_on_leave']) && $stats['tenants_on_leave'] > 0)
                    @php $leaveCount = (int) $stats['tenants_on_leave']; @endphp
                    <p class="flex items-center gap-2 text-sm text-slate-500 mt-2" title="{{ __('messages.tenants_on_leave', ['count' => $leaveCount]) }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-sky-500 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10A8 8 0 1110 2a8 8 0 018 8zm-9-3a1 1 0 102 0 1 1 0 00-2 0zm1 4a1 1 0 00-1 1v1a1 1 0 102 0v-1a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <span>{{ __('messages.tenants_on_leave', ['count' => $leaveCount]) }}</span>
                    </p>
                @endif
            </div>
            @php $paid = $stats['payments']['paid'] ?? 0; $assigned = $stats['apartments']['occupied'] ?? ($stats['apartments']['total'] ?? 0); @endphp
            <div class="relative w-16 h-16 flex items-center justify-center">
                <canvas id="paymentsDonutPaid" width="64" height="64"></canvas>
                <span class="absolute inset-0 flex items-center justify-center pointer-events-none text-xs font-medium text-slate-700">{{ $paid }} / {{ $assigned }}</span>
            </div>
        </a>

        <a href="{{ route('admin.revenue_expense.record_income', ['month' => $displayMonth->month, 'year' => $displayMonth->year, 'filter' => 'pending']) }}"
            class="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between hover:border-amber-300 hover:shadow-sm transition">
            <div>
                <p class="flex items-center gap-2 text-sm font-medium text-slate-600">
                    <span class="p-1.5 rounded-full bg-amber-50 inline-flex items-center justify-center">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    {{ __('messages.pending') }}
                </p>
                <p class="text-2xl font-bold text-slate-800">{{ $stats['payments']['pending'] }}</p>
            </div>
            @php $pending = $stats['payments']['pending'] ?? 0; @endphp
            <div class="relative w-16 h-16 flex items-center justify-center">
                <canvas id="paymentsDonutPending" width="64" height="64"></canvas>
                <span class="absolute inset-0 flex items-center justify-center pointer-events-none text-xs font-medium text-slate-700">{{ $pending }} / {{ $assigned }}</span>
            </div>
        </a>

        <a href="{{ route('admin.revenue_expense.record_income', ['month' => $displayMonth->month, 'year' => $displayMonth->year, 'filter' => 'overdue']) }}"
            class="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between hover:border-red-300 hover:shadow-sm transition">
            <div>
                <p class="flex items-center gap-2 text-sm font-medium text-slate-600">
                    <span class="p-1.5 rounded-full bg-red-50 inline-flex items-center justify-center">
                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </span>
                    {{ __('messages.overdue') }}
                </p>
                <p class="text-2xl font-bold text-slate-800">{{ $stats['payments']['overdue'] }}</p>
            </div>
            @php $overdue = $stats['payments']['overdue'] ?? 0; @endphp
            <div class="relative w-16 h-16 flex items-center justify-center">
                <canvas id="paymentsDonutOverdue" width="64" height="64"></canvas>
                <span class="absolute inset-0 flex items-center justify-center pointer-events-none text-xs font-medium text-slate-700">{{ $overdue }} / {{ $assigned }}</span>
            </div>
        </a>
    </div>
    @endif

    {{-- Monthly Calendar (hidden on phones) --}}
    @if(!$isFullPeriod && $calendarData)
    <div class="hidden md:block bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-800">{{ $calendarData['startOfMonth']->format('F Y') }}</h2>
            </div>
        </div>

        {{-- Calendar Grid --}}
        <div class="rounded-lg border border-slate-100 overflow-hidden">
            <div class="grid grid-cols-7 bg-slate-50/80 border-b border-slate-100">
                @foreach(['day_sun', 'day_mon', 'day_tue', 'day_wed', 'day_thu', 'day_fri', 'day_sat'] as $dayKey)
                    <div class="text-center text-[11px] font-medium text-slate-400 py-2 uppercase tracking-wider">{{ __('messages.' . $dayKey) }}</div>
                @endforeach
            </div>
            <div class="grid grid-cols-7">
                @for($i = 0; $i < $calendarData['firstDayOfWeek']; $i++)
                    <div class="border-b border-r border-slate-50 min-h-[80px] bg-slate-50/50"></div>
                @endfor

                @for($d = 1; $d <= $calendarData['daysInMonth']; $d++)
                    @php
                        $dayData = $calendarData['calendarDays'][$d];
                        $hasData = $dayData['tx_count'] > 0;
                        $isToday = $dayData['is_today'];
                        $isFuture = $dayData['is_future'];
                    @endphp
                    <div class="border-b border-r border-slate-50 min-h-[80px] p-1.5 transition {{ $isToday ? 'ring-2 ring-sky-500 ring-inset bg-sky-50/30' : ($isFuture ? 'bg-slate-50/30' : ($hasData ? 'hover:bg-slate-50' : '')) }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold {{ $isToday ? 'bg-sky-500 text-white w-5 h-5 rounded-full flex items-center justify-center' : ($isFuture ? 'text-slate-300' : 'text-slate-600') }}">
                                {{ $d }}
                            </span>
                            @if($hasData)
                                <span class="text-[10px] text-slate-400">{{ $dayData['tx_count'] }}tx</span>
                            @endif
                        </div>
                        @if($hasData)
                            @if($dayData['income'] > 0)
                                <div class="flex items-center gap-1 mb-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 shrink-0"></span>
                                    <span class="text-[11px] font-medium text-green-700 truncate">+{{ money($dayData['income'], 0) }}</span>
                                </div>
                            @endif
                            @if($dayData['expense'] > 0)
                                <div class="flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0"></span>
                                    <span class="text-[11px] font-medium text-red-700 truncate">-{{ money($dayData['expense'], 0) }}</span>
                                </div>
                            @endif
                        @elseif(!$isFuture)
                            <p class="text-[10px] text-slate-300 mt-2 text-center">—</p>
                        @endif
                    </div>
                @endfor

                @php $trailing = (7 - (($calendarData['firstDayOfWeek'] + $calendarData['daysInMonth']) % 7)) % 7; @endphp
                @for($i = 0; $i < $trailing; $i++)
                    <div class="border-b border-r border-slate-50 min-h-[80px] bg-slate-50/50"></div>
                @endfor
            </div>
        </div>

        {{-- Legend --}}
        <div class="flex items-center gap-4 text-xs text-slate-400 mt-3">
            <span class="flex items-center gap-1"><span class="w-3 h-3 border-2 border-sky-500 rounded"></span> {{ __('messages.today') }}</span>
        </div>
    </div>
    @endif


@push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
    window.ensureChart().then(function () {

        // ── Payment Status Donuts ──
        var paid = {{ json_encode($stats['payments']['paid'] ?? 0) }};
        var pending = {{ json_encode($stats['payments']['pending'] ?? 0) }};
        var overdue = {{ json_encode($stats['payments']['overdue'] ?? 0) }};
        var totalAssigned = {{ json_encode($stats['apartments']['occupied'] ?? ($stats['apartments']['total'] ?? 0)) }};

        function renderMini(id, value, color) {
            var el = document.getElementById(id);
            if (!el) return;
            var remainder = Math.max((totalAssigned || 0) - value, 0);
            new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: ['Value', 'Remaining'],
                    datasets: [{
                        data: [value, remainder],
                        backgroundColor: [color, '#e5e7eb'],
                        hoverBackgroundColor: [color, '#d1d5db'],
                        borderWidth: 0
                    }]
                },
                options: {
                    cutout: '70%',
                    maintainAspectRatio: false,
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.dataIndex !== 0) return null;
                                    var v = context.parsed || 0;
                                    var pct = totalAssigned > 0 ? (v / totalAssigned * 100).toFixed(1) : '0.0';
                                    return context.label + ': ' + v + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        renderMini('paymentsDonutPaid', paid, '#16a34a');
        renderMini('paymentsDonutPending', pending, '#f59e0b');
        renderMini('paymentsDonutOverdue', overdue, '#ef4444');
    });
    
    // Toggle summary card details on click
    document.addEventListener('DOMContentLoaded', function () {
        var cards = document.querySelectorAll('.summary-card');
        function hideAll() {
            document.querySelectorAll('.summary-details').forEach(function (el) {
                el.classList.add('hidden');
            });
        }

        cards.forEach(function (card) {
            card.addEventListener('click', function (e) {
                var details = card.querySelector('.summary-details');
                if (!details) return;
                var isHidden = details.classList.contains('hidden');
                // close others
                hideAll();
                if (isHidden) {
                    details.classList.remove('hidden');
                } else {
                    details.classList.add('hidden');
                }
            });
        });

        // close when clicking outside
        document.addEventListener('click', function (e) {
            if (e.target.closest('.summary-card')) return;
            hideAll();
        });
    });
    });
    </script>
    @endpush

</div>
@endsection
