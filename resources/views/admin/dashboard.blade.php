@extends('layouts.admin')

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
            <p class="text-slate-400 text-sm mt-1">{{ now()->format('F Y') }} — Overview & Quick Recording</p>
        </div>
        @if($activePeriod)
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                {{ $activePeriod->name }}
            </span>
        </div>
        @endif
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Monthly Revenue --}}
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Revenue</p>
                    <p class="text-xl font-bold text-slate-800">${{ number_format($stats['revenue']['collected_this_month'] + $stats['revenue']['late_fees_this_month'] + ($stats['revenue']['archived_deposits'] ?? 0), 2) }}</p>
                </div>
            </div>
            <div>
            <p class="text-[11px] text-slate-400 mt-2 space-y-0.5">
                @php $byType = $stats['revenue']['by_type'] ?? []; @endphp
                @if(($byType['rent'] ?? 0) > 0)
                    <span class="flex justify-between"><span>Rent</span><span class="font-medium text-green-500">+${{ number_format($byType['rent'], 2) }}</span></span>
                @endif
                @if(($byType['deposit'] ?? 0) > 0)
                    <span class="flex justify-between"><span>Deposit</span><span class="font-medium text-green-500">+${{ number_format($byType['deposit'], 2) }}</span></span>
                @endif
                @if(($stats['revenue']['archived_deposits'] ?? 0) > 0)
                    <span class="flex justify-between"><span>Archived Deposits</span><span class="font-medium text-green-500">+${{ number_format($stats['revenue']['archived_deposits'], 2) }}</span></span>
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
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Expenses</p>
                    <p class="text-xl font-bold text-slate-800">${{ number_format($stats['expenses']['monthly_total'] ?? 0, 2) }}</p>
                </div>
            </div>
            <div>
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
            $netProfit = ($stats['revenue']['collected_this_month'] + $stats['revenue']['late_fees_this_month'] + ($stats['revenue']['archived_deposits'] ?? 0)) - ($stats['expenses']['monthly_total'] ?? 0);
        @endphp
        <div class="bg-white rounded-xl border border-slate-100 p-5">
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
            <div>
            <p class="text-[11px] text-slate-400 mt-2 space-y-0.5">
                <span class="flex justify-between"><span>Revenue</span><span class="font-medium text-emerald-500">+${{ number_format($stats['revenue']['collected_this_month'] + $stats['revenue']['late_fees_this_month'] + ($stats['revenue']['archived_deposits'] ?? 0), 2) }}</span></span>
                <span class="flex justify-between"><span>Expenses</span><span class="font-medium text-red-400">-${{ number_format($stats['expenses']['monthly_total'] ?? 0, 2) }}</span></span>
            </p>
                </div>
            </div>

        {{-- Occupancy --}}
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Occupied / Total</p>
                    <p class="text-xl font-bold text-slate-800">{{ $stats['apartments']['occupied'] }} / {{ $stats['apartments']['total'] }}</p>
                </div>
            </div>
            <div>
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
                <p class="text-sm text-yellow-800 mt-1">Create a fiscal period to start recording revenue and expenses.</p>
                <a href="{{ route('admin.fiscalperiod.create') }}" class="inline-block mt-3 bg-yellow-600 text-white px-5 py-2 rounded-lg hover:bg-yellow-700 transition text-sm font-medium">
                    Create Fiscal Period
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- Payment Status Quick View --}}
    @if($fiscalData['has_active_period'])
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-emerald-50/70 border border-emerald-100 rounded-xl p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700">Paid</p>
                <p class="text-2xl font-bold text-emerald-800">{{ $stats['payments']['paid'] }}</p>
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

    {{-- Per-Floor Revenue Chart --}}
    @if(!empty($apartmentRevenues))
    <div class="bg-white rounded-xl border border-slate-100 p-6" x-data="{ expandedFloor: null }">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-slate-800">Revenue by Floor</h2>
            <p class="text-xs text-slate-400 mt-1">Expected vs Collected — {{ now()->format('F Y') }}</p>
        </div>

        {{-- Summary Cards --}}
        @php
            $totalExpected = collect($apartmentRevenues)->sum('expected');
            $totalActual = collect($apartmentRevenues)->sum('actual');
            $overallPct = $totalExpected > 0 ? round(($totalActual / $totalExpected) * 100, 1) : 0;
            $outstanding = $totalExpected - $totalActual;
        @endphp
        <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- Expected Rent --}}
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-indigo-600">Expected Rent</p>
                        <p class="text-xl font-bold text-indigo-900">${{ number_format($totalExpected, 2) }}</p>
                    </div>
                </div>
                <p class="text-xs text-indigo-500 mt-2">{{ now()->format('F Y') }} — all occupied units</p>
            </div>

            {{-- Rent Collected --}}
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-emerald-600">Rent Collected</p>
                        <p class="text-xl font-bold text-emerald-900">${{ number_format($totalActual, 2) }}</p>
                    </div>
                </div>
                <p class="text-xs text-emerald-500 mt-2">
                    @if($outstanding > 0)
                        ${{ number_format($outstanding, 2) }} outstanding
                    @else
                        Fully collected
                    @endif
                </p>
            </div>

            {{-- Collection Rate --}}
            <div class="rounded-xl border {{ $overallPct >= 80 ? 'border-emerald-200 bg-emerald-50' : ($overallPct >= 50 ? 'border-yellow-200 bg-yellow-50' : 'border-red-200 bg-red-50') }} p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg {{ $overallPct >= 80 ? 'bg-emerald-100' : ($overallPct >= 50 ? 'bg-yellow-100' : 'bg-red-100') }} flex items-center justify-center">
                        <svg class="w-5 h-5 {{ $overallPct >= 80 ? 'text-emerald-600' : ($overallPct >= 50 ? 'text-yellow-600' : 'text-red-600') }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                    </div>
                    <div>
                        <p class="text-xs font-medium {{ $overallPct >= 80 ? 'text-emerald-600' : ($overallPct >= 50 ? 'text-yellow-600' : 'text-red-600') }}">Collection Rate</p>
                        <p class="text-xl font-bold {{ $overallPct >= 80 ? 'text-emerald-900' : ($overallPct >= 50 ? 'text-yellow-900' : 'text-red-900') }}">{{ $overallPct }}%</p>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="w-full bg-white/60 rounded-full h-2">
                        <div class="h-2 rounded-full {{ $overallPct >= 80 ? 'bg-emerald-500' : ($overallPct >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ min($overallPct, 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Floor Apartment Breakdown (click to expand) --}}
        <div class="mt-5 space-y-2">
            @foreach($apartmentRevenues as $idx => $floor)
            <div class="border border-slate-100 rounded-lg overflow-hidden">
                <button @click="expandedFloor === {{ $idx }} ? expandedFloor = null : expandedFloor = {{ $idx }}"
                    class="w-full flex items-center justify-between px-4 py-3 bg-slate-50/80 hover:bg-slate-50 transition text-left">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 text-slate-400 transition-transform" :class="{ 'rotate-90': expandedFloor === {{ $idx }} }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <span class="text-sm font-semibold text-slate-700">{{ $floor['floor'] }}</span>
                        <span class="text-xs text-slate-400">({{ count($floor['apartments']) }} units)</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs text-slate-400">${{ number_format($floor['actual'], 2) }} / ${{ number_format($floor['expected'], 2) }}</span>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full
                            {{ $floor['percentage'] >= 100 ? 'bg-emerald-100 text-emerald-700' : ($floor['percentage'] >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                            {{ $floor['percentage'] }}%
                        </span>
                    </div>
                </button>
                <div x-show="expandedFloor === {{ $idx }}" x-collapse>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50/80 text-left">
                                <th class="px-4 py-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider">Unit</th>
                                <th class="px-4 py-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider text-right">Expected</th>
                                <th class="px-4 py-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider text-right">Collected</th>
                                <th class="px-4 py-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider text-right">Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($floor['apartments'] as $apt)
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-2 font-medium text-slate-700">{{ $apt['apartment'] }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                                        {{ $apt['status'] === 'occupied' ? 'bg-emerald-50 text-emerald-700' : ($apt['status'] === 'available' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600') }}">
                                        {{ ucfirst($apt['status']) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right text-slate-500">${{ number_format($apt['expected'], 2) }}</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-700">${{ number_format($apt['actual'], 2) }}</td>
                                <td class="px-4 py-2 text-right">
                                    <span class="font-semibold {{ $apt['percentage'] >= 100 ? 'text-emerald-600' : ($apt['percentage'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $apt['percentage'] }}%
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Recent Transactions --}}
    @if($recentTransactions->isNotEmpty())
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="text-lg font-semibold text-slate-800 mb-2">Recent Transactions</h2>
        <p class="text-xs text-slate-400 mb-4">Latest 5 transactions — concise view</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-slate-100">
                        <th class="pb-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider">Date</th>
                        <th class="pb-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider">Description</th>
                        <th class="pb-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($recentTransactions->take(5) as $tx)
                    <tr class="hover:bg-slate-50/50">
                        <td class="py-2.5 text-slate-400">{{ $tx->transaction_date->format('M d') }}</td>
                        <td class="py-2.5 text-slate-600 max-w-[260px] truncate">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $tx->account_type === 'income' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $tx->account_type === 'income' ? 'Income' : 'Expense' }}</span>
                                <span class="truncate">{{ \Illuminate\Support\Str::limit($tx->description, 50) }}</span>
                            </div>
                        </td>
                        <td class="py-2.5 text-right font-medium {{ $tx->account_type === 'income' ? 'text-green-700' : 'text-red-700' }}">
                            {{ $tx->account_type === 'income' ? '+' : '-' }}${{ number_format($tx->amount, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

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
                            <a href="{{ route('admin.fiscalperiod.reports', $period->id) }}" class="text-sky-600 hover:text-sky-700 text-xs font-medium">Report</a>
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
    </script>
    @endpush

</div>
@endsection
