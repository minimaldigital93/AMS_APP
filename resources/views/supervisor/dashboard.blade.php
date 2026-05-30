@extends('layouts.supervisor')

@section('title', 'Dashboard')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-100 rounded-lg px-4 py-3 text-emerald-700 text-sm flex items-center gap-2.5">
        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-red-600 text-sm flex items-center gap-2.5">
        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
        {{ session('error') }}
    </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">Dashboard</h1>
            <p class="text-slate-400 text-sm mt-1">
                {{ $isFullPeriod ? $activePeriod->name . ' — Full Fiscal Period Overview' : $displayMonth->format('F Y') . ' — Overview & Quick Recording' }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('supervisor.floors.plan3d') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                3D Floor View
            </a>
            @if($activePeriod)
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                {{ $activePeriod->name }}
            </span>
            @endif
        </div>
    </div>

    @if($activePeriod && count($periodMonths) > 0)
    <div class="flex items-center justify-center">
        <div class="inline-flex items-center bg-white rounded-xl border border-slate-100 px-2 py-1.5 gap-1">
            @if($monthNavigation['previousMonth'])
            <a href="{{ route('supervisor.dashboard', ['month' => $monthNavigation['previousMonth']['month'], 'year' => $monthNavigation['previousMonth']['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="Previous Month">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </span>
            @endif

            <div class="px-4 py-2 min-w-[220px] text-center">
                @if($isFullPeriod)
                    <span class="text-lg font-bold text-slate-800">All Months</span>
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">Full Period</span>
                @else
                <span class="text-lg font-bold text-slate-800">{{ $displayMonth->format('F') }}</span>
                <span class="text-lg text-slate-500 ml-1">{{ $displayMonth->format('Y') }}</span>
                @if($monthNavigation['isCurrentMonth'])
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Current</span>
                @elseif($displayMonth->isFuture())
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">Upcoming</span>
                @else
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Past</span>
                @endif
                @endif
            </div>

            @if($monthNavigation['nextMonth'])
            <a href="{{ route('supervisor.dashboard', ['month' => $monthNavigation['nextMonth']['month'], 'year' => $monthNavigation['nextMonth']['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="Next Month">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
            @endif

                @if(!$isFullPeriod)
                <a href="{{ route('supervisor.dashboard', ['view' => 'all']) }}"
                    class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-slate-600 bg-slate-50 rounded-lg hover:bg-slate-100 transition" title="View full fiscal period">
                     <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                     All
                </a>
                @endif

                @if(($isFullPeriod || !$monthNavigation['isCurrentMonth']) && $monthNavigation['currentMonthInPeriod'])
            <a href="{{ route('supervisor.dashboard', ['month' => now()->month, 'year' => now()->year]) }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition" title="Go to current month">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Today
            </a>
            @endif
        </div>
    </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Monthly Revenue --}}
        <div class="bg-white rounded-xl border border-slate-100 p-5 summary-card cursor-pointer" data-card="revenue">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Revenue</p>
                    <p class="text-xl font-bold text-slate-800">${{ number_format($stats['revenue']['total_monthly'] ?? 0, 2) }}</p>
                </div>
            </div>
            <div class="summary-details hidden">
            <p class="text-[11px] text-slate-400 mt-2 space-y-0.5">
                @php $byType = $stats['revenue']['by_type'] ?? []; @endphp
                @if(($byType['rent'] ?? 0) > 0)
                    <span class="flex justify-between"><span>Rent</span><span class="font-medium text-green-500">+${{ number_format($byType['rent'], 2) }}</span></span>
                @endif
                @if(($byType['deposit'] ?? 0) > 0)
                    <span class="flex justify-between"><span>Deposit</span><span class="font-medium text-green-500">+${{ number_format($byType['deposit'], 2) }}</span></span>
                @endif
                @if(($byType['utilities'] ?? 0) > 0)
                    <span class="flex justify-between"><span>Utilities</span><span class="font-medium text-green-500">+${{ number_format($byType['utilities'], 2) }}</span></span>
                @endif
                @if(($stats['revenue']['late_fees_this_month'] ?? 0) > 0)
                    <span class="flex justify-between"><span>Late Fees</span><span class="font-medium text-green-500">+${{ number_format($stats['revenue']['late_fees_this_month'], 2) }}</span></span>
                @endif
                @if(($byType['other'] ?? 0) > 0)
                    <span class="flex justify-between"><span>Other</span><span class="font-medium text-green-500">+${{ number_format($byType['other'], 2) }}</span></span>
                @endif
            </p>
            </div>
        </div>

        {{-- Monthly Expenses --}}
        <div class="bg-white rounded-xl border border-slate-100 p-5 summary-card cursor-pointer" data-card="expenses">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Expenses</p>
                    <p class="text-xl font-bold text-slate-800">${{ number_format($stats['expenses']['monthly_total'] ?? 0, 2) }}</p>
                </div>
            </div>
            <div class="summary-details hidden">
            <p class="text-[11px] text-slate-400 mt-2 space-y-0.5">
                @if(($stats['expenses']['utilities_total'] ?? 0) > 0)
                    <span class="flex justify-between"><span>Utilities</span><span class="font-medium text-red-400">-${{ number_format($stats['expenses']['utilities_total'], 2) }}</span></span>
                @endif
                @foreach(($stats['expenses']['account_breakdown'] ?? []) as $cat => $amt)
                    @if($amt > 0)
                        <span class="flex justify-between"><span>{{ str_replace('_', ' ', ucfirst($cat)) }}</span><span class="font-medium text-red-400">-${{ number_format($amt, 2) }}</span></span>
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
                <div class="w-10 h-10 rounded-lg {{ $netProfit >= 0 ? 'bg-sky-50' : 'bg-orange-50' }} flex items-center justify-center">
                    <svg class="w-5 h-5 {{ $netProfit >= 0 ? 'text-blue-600' : 'text-orange-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Net Profit</p>
                    <p class="text-xl font-bold {{ $netProfit >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ $netProfit >= 0 ? '+' : '' }}${{ number_format($netProfit, 2) }}
                    </p>
                </div>
            </div>
            <div class="summary-details hidden">
            <p class="text-[11px] text-slate-400 mt-2 space-y-0.5">
                <span class="flex justify-between"><span>Revenue</span><span class="font-medium text-emerald-500">+${{ number_format($stats['revenue']['total_monthly'] ?? 0, 2) }}</span></span>
                <span class="flex justify-between"><span>Expenses</span><span class="font-medium text-red-400">-${{ number_format($stats['expenses']['monthly_total'] ?? 0, 2) }}</span></span>
            </p>
                </div>
            </div>

        {{-- Occupancy --}}
        <div class="bg-white rounded-xl border border-slate-100 p-5 summary-card cursor-pointer" data-card="occupancy">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Occupied / Total</p>
                    <p class="text-xl font-bold text-slate-800">{{ $stats['apartments']['occupied'] }} / {{ $stats['apartments']['total'] }}</p>
                </div>
            </div>
            <div class="summary-details hidden">
            @php $occRate = $stats['apartments']['total'] > 0 ? round(($stats['apartments']['occupied'] / $stats['apartments']['total']) * 100, 1) : 0; @endphp
            <p class="text-[11px] text-slate-400 mt-2 space-y-0.5">
                <span class="flex justify-between"><span>Occupancy Rate</span><span class="font-medium {{ $occRate >= 80 ? 'text-emerald-500' : ($occRate >= 50 ? 'text-amber-500' : 'text-red-500') }}">{{ $occRate }}%</span></span>
                <span class="flex justify-between"><span>Available</span><span class="font-medium text-slate-500">{{ $stats['apartments']['available'] }}</span></span>
                @if(($stats['apartments']['maintenance'] ?? 0) > 0)
                    <span class="flex justify-between"><span>Maintenance</span><span class="font-medium text-slate-500">{{ $stats['apartments']['maintenance'] }}</span></span>
                @endif
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
                <h3 class="font-bold text-yellow-900">No Active Fiscal Period</h3>
                <p class="text-sm text-yellow-800 mt-1">An admin must open a fiscal period before revenue and expenses can be recorded.</p>
            </div>
        </div>
    </div>
    @endif

    {{-- Payment Status Quick View --}}
    @if($fiscalData['has_active_period'] && !$isFullPeriod)
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-emerald-50/70 border border-emerald-100 rounded-xl p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700">Paid</p>
                <p class="text-2xl font-bold text-emerald-800">{{ $stats['payments']['paid'] }}</p>
                @if(!empty($stats['tenants_on_leave']) && $stats['tenants_on_leave'] > 0)
                    @php $leaveCount = (int) $stats['tenants_on_leave']; @endphp
                    <p class="flex items-center gap-2 text-sm text-slate-500 mt-2" title="{{ $leaveCount === 1 ? '1 tenant is on leave' : $leaveCount . ' tenants on leave' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-sky-500 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10A8 8 0 1110 2a8 8 0 018 8zm-9-3a1 1 0 102 0 1 1 0 00-2 0zm1 4a1 1 0 00-1 1v1a1 1 0 102 0v-1a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <span>{{ $leaveCount === 1 ? '1 tenant is on leave' : $leaveCount . ' tenants on leave' }}</span>
                    </p>
                @endif
            </div>
            @php $paid = $stats['payments']['paid'] ?? 0; $assigned = $stats['apartments']['occupied'] ?? ($stats['apartments']['total'] ?? 0); @endphp
            <div class="relative w-16 h-16 flex items-center justify-center">
                <canvas id="paymentsDonutPaid" width="64" height="64"></canvas>
                <span class="absolute inset-0 flex items-center justify-center pointer-events-none text-xs font-medium text-slate-700">{{ $paid }} / {{ $assigned }}</span>
            </div>
        </div>

        <div class="bg-amber-50/70 border border-amber-100 rounded-xl p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-amber-700">Pending</p>
                <p class="text-2xl font-bold text-amber-800">{{ $stats['payments']['pending'] }}</p>
            </div>
            @php $pending = $stats['payments']['pending'] ?? 0; @endphp
            <div class="relative w-16 h-16 flex items-center justify-center">
                <canvas id="paymentsDonutPending" width="64" height="64"></canvas>
                <span class="absolute inset-0 flex items-center justify-center pointer-events-none text-xs font-medium text-slate-700">{{ $pending }} / {{ $assigned }}</span>
            </div>
        </div>

        <div class="bg-red-50/70 border border-red-100 rounded-xl p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-red-600">Overdue</p>
                <p class="text-2xl font-bold text-red-700">{{ $stats['payments']['overdue'] }}</p>
            </div>
            @php $overdue = $stats['payments']['overdue'] ?? 0; @endphp
            <div class="relative w-16 h-16 flex items-center justify-center">
                <canvas id="paymentsDonutOverdue" width="64" height="64"></canvas>
                <span class="absolute inset-0 flex items-center justify-center pointer-events-none text-xs font-medium text-slate-700">{{ $overdue }} / {{ $assigned }}</span>
            </div>
        </div>
    </div>
    @endif

    {{-- Monthly Calendar --}}
    @if(!$isFullPeriod && $calendarData)
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-800">{{ $calendarData['startOfMonth']->format('F Y') }}</h2>
            </div>
        </div>

        {{-- Calendar Grid --}}
        <div class="rounded-lg border border-slate-100 overflow-hidden">
            <div class="grid grid-cols-7 bg-slate-50/80 border-b border-slate-100">
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                    <div class="text-center text-[11px] font-medium text-slate-400 py-2 uppercase tracking-wider">{{ $dayName }}</div>
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
                                    <span class="text-[11px] font-medium text-green-700 truncate">+${{ number_format($dayData['income'], 0) }}</span>
                                </div>
                            @endif
                            @if($dayData['expense'] > 0)
                                <div class="flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0"></span>
                                    <span class="text-[11px] font-medium text-red-700 truncate">-${{ number_format($dayData['expense'], 0) }}</span>
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
            <span class="flex items-center gap-1"><span class="w-3 h-3 border-2 border-sky-500 rounded"></span> Today</span>
        </div>
    </div>
    @endif


    {{-- Recent Transactions removed per design request --}}

    {{-- Recent Closed Fiscal Periods --}}
    @if($fiscalData['has_active_period'] && $fiscalData['recent_periods']->count() > 0)
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-4">Closed Periods</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-slate-100">
                        <th class="pb-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider">Period</th>
                        <th class="pb-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider">Dates</th>
                        <th class="pb-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider text-right">Opening</th>
                        <th class="pb-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider text-right">Closing</th>
                        <th class="pb-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider text-right">Change</th>
                        <th class="pb-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($fiscalData['recent_periods'] as $period)
                    <tr class="hover:bg-slate-50/50">
                        <td class="py-2.5 font-medium text-slate-700">{{ $period->name }}</td>
                        <td class="py-2.5 text-slate-400">{{ $period->opening_date->format('M d') }} - {{ $period->closing_date->format('M d, Y') }}</td>
                        <td class="py-2.5 text-right">${{ number_format($period->opening_balance, 2) }}</td>
                        <td class="py-2.5 text-right">${{ number_format($period->closing_balance, 2) }}</td>
                        @php $change = $period->closing_balance - $period->opening_balance; @endphp
                        <td class="py-2.5 text-right {{ $change >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $change >= 0 ? '+' : '' }}${{ number_format($change, 2) }}
                        </td>
                        <td class="py-2.5 text-right">
                            <span class="text-slate-400 text-xs">{{ $period->status === 'closed' ? 'Closed' : ucfirst($period->status) }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {

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
    </script>
    @endpush

</div>
@endsection
