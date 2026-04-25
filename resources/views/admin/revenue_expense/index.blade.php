@extends('layouts.admin')

@section('content')
<style>[x-cloak] { display: none !important; }</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<div class="max-w-6xl mx-auto space-y-8" x-data="revenueExpense()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">Revenue & Expense</h1>
            <p class="text-slate-500 mt-2">
                @if(isset($filterMonth) && $filterMonth)
                    Viewing <span class="font-semibold text-sky-600">{{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }}</span>
                @else
                    Full period overview
                @endif
                — Fiscal Period: <span class="font-semibold text-sky-600">{{ $activePeriod->name }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($fiscalPeriods->count() > 1)
            <form method="GET" action="{{ route('admin.revenue_expense.index') }}">
                <select name="period" onchange="this.form.submit()" class="text-sm border-slate-200 rounded-lg focus:ring-sky-500 focus:border-sky-500">
                    @foreach($fiscalPeriods as $fp)
                    <option value="{{ $fp->id }}" {{ $fp->id === $activePeriod->id ? 'selected' : '' }}>{{ $fp->name }}</option>
                    @endforeach
                </select>
            </form>
            @endif
        </div>
    </div>

    {{-- Month Navigation --}}
    @if(isset($periodMonths) && count($periodMonths) > 0)
    @php
        // Build prev/next month links
        $currentIdx = null;
        foreach ($periodMonths as $idx => $pm) {
            if (isset($filterMonth) && $filterMonth == $pm['month'] && isset($filterYear) && $filterYear == $pm['year']) {
                $currentIdx = $idx;
                break;
            }
        }
        $selectedMonth = isset($filterMonth) && $filterMonth
            ? \Carbon\Carbon::create($filterYear, $filterMonth, 1)
            : now();
        $isCurrentMonth = $selectedMonth->month === now()->month && $selectedMonth->year === now()->year;
        $isFilterActive = isset($filterMonth) && $filterMonth;

        $prevMonth = ($currentIdx !== null && $currentIdx > 0) ? $periodMonths[$currentIdx - 1] : ($isFilterActive ? null : $periodMonths[count($periodMonths) - 1]);
        $nextMonth = ($currentIdx !== null && $currentIdx < count($periodMonths) - 1) ? $periodMonths[$currentIdx + 1] : null;

        // If no filter active, first entry = first month
        if (!$isFilterActive && count($periodMonths) > 0) {
            $prevMonth = null;
            $nextMonth = null;
        }
    @endphp
    <div class="flex items-center justify-center">
        <div class="inline-flex items-center bg-white rounded-xl border border-slate-100 px-2 py-1.5 gap-1">
            {{-- Previous Month --}}
            @if($prevMonth)
            <a href="{{ route('admin.revenue_expense.index', ['period' => $activePeriod->id, 'month' => $prevMonth['month'], 'year' => $prevMonth['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="Previous Month">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </span>
            @endif

            {{-- Current Month Display --}}
            <div class="px-4 py-2 min-w-[220px] text-center">
                @if($isFilterActive)
                    <span class="text-lg font-bold text-slate-800">{{ $selectedMonth->format('F') }}</span>
                    <span class="text-lg text-slate-500 ml-1">{{ $selectedMonth->format('Y') }}</span>
                    @if($isCurrentMonth)
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Current</span>
                    @elseif($selectedMonth->isFuture())
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">Upcoming</span>
                    @else
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Past</span>
                    @endif
                @else
                    <span class="text-lg font-bold text-slate-800">All Months</span>
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">Full Period</span>
                @endif
            </div>

            {{-- Next Month --}}
            @if($nextMonth)
            <a href="{{ route('admin.revenue_expense.index', ['period' => $activePeriod->id, 'month' => $nextMonth['month'], 'year' => $nextMonth['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="Next Month">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
            @endif

            {{-- Quick Actions --}}
            @if($isFilterActive)
            <a href="{{ route('admin.revenue_expense.index', ['period' => $activePeriod->id]) }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-slate-600 bg-slate-50 rounded-lg hover:bg-slate-100 transition" title="View all months">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                All
            </a>
            @endif

            @if(!$isCurrentMonth || !$isFilterActive)
            @php
                $nowMonth = now()->month;
                $nowYear = now()->year;
                $currentInPeriod = collect($periodMonths)->first(fn($pm) => $pm['month'] == $nowMonth && $pm['year'] == $nowYear);
            @endphp
            @if($currentInPeriod)
            <a href="{{ route('admin.revenue_expense.index', ['period' => $activePeriod->id, 'month' => $nowMonth, 'year' => $nowYear]) }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition" title="Go to current month">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Today
            </a>
            @endif
            @endif
        </div>
    </div>
    @endif

     {{-- Summary Cards  --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Income</p>
                    <p class="text-xl font-bold text-emerald-600">${{ number_format($income['total_income'], 2) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ $income['payment_count'] }} payment{{ $income['payment_count'] !== 1 ? 's' : '' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Business Expenses</p>
                    <p class="text-xl font-bold text-red-600">${{ number_format($expenses['total_expenses'], 2) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ $expenses['expense_count'] }} transaction{{ $expenses['expense_count'] !== 1 ? 's' : '' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg {{ $summary['net_profit'] >= 0 ? 'bg-sky-50' : 'bg-orange-50' }} flex items-center justify-center">
                    <svg class="w-5 h-5 {{ $summary['net_profit'] >= 0 ? 'text-sky-600' : 'text-orange-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Net Profit</p>
                    <p class="text-xl font-bold {{ $summary['net_profit'] >= 0 ? 'text-sky-600' : 'text-orange-600' }}">
                        {{ $summary['net_profit'] >= 0 ? '+' : '' }}${{ number_format($summary['net_profit'], 2) }}
                    </p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ $summary['profit_margin'] }}% margin</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Occupancy</p>
                    <p class="text-xl font-bold text-purple-600">{{ $occupiedCount }}/{{ $totalApartments }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ $occupancyRate }}% occupied</p>
        </div>
    </div>

    {{-- Fiscal Period Progress + Monthly Rent Collection (Side by Side) --}}
    @php
        $periodStart = \Carbon\Carbon::parse($activePeriod->opening_date);
        $periodEnd = \Carbon\Carbon::parse($activePeriod->closing_date);
        $today = now();
        $totalDays = max(1, $periodStart->diffInDays($periodEnd));
        $daysPassed = max(0, (int) $periodStart->diffInDays($today));
        $periodPercent = min(100, max(0, round(($daysPassed / $totalDays) * 100, 1)));
        $percentLabel = $periodPercent . '%';
        $r = 40;
        $circ = 2 * pi() * $r;
        $offset = $circ * (1 - ($periodPercent / 100));
    @endphp
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Fiscal Period Progress (condensed) --}}
        <div class="bg-white rounded-xl border border-slate-100 p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-700">Fiscal Period</p>
                <p class="text-xs text-slate-400">{{ $activePeriod->name }}</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="text-right">
                    <div class="text-lg font-bold text-slate-800">{{ $periodPercent }}%</div>
                </div>
                <div class="w-20 h-20">
                    <svg viewBox="0 0 100 100" class="w-20 h-20">
                        <defs>
                            <linearGradient id="periodGrad" x1="0%" x2="100%">
                                <stop offset="0%" stop-color="#F59E0B" />
                                <stop offset="100%" stop-color="#D97706" />
                            </linearGradient>
                        </defs>
                        <circle cx="50" cy="50" r="40" stroke="#F3F4F6" stroke-width="12" fill="none" />
                        <circle cx="50" cy="50" r="40" stroke="url(#periodGrad)" stroke-width="12" fill="none"
                            stroke-dasharray="{{ $circ }}" stroke-dashoffset="{{ $offset }}" transform="rotate(-90 50 50)" stroke-linecap="round" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Monthly Rent Collection (condensed) --}}
        @if($expectedMonthlyRent > 0)
        @php
            $collected = $income['rent_income'] ?? 0;
            $collectionPercent = $expectedMonthlyRent > 0 ? min(100, round(($collected / $expectedMonthlyRent) * 100, 1)) : 0;
            $rc = 36;
            $rcCirc = 2 * pi() * $rc;
            $rcOffset = $rcCirc * (1 - ($collectionPercent / 100));
        @endphp
        <div class="bg-white rounded-xl border border-slate-100 p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-slate-700">Collected</p>
                <p class="text-lg font-bold text-slate-800">${{ number_format($collected, 2) }}</p>
            </div>
            <div class="flex items-center gap-4">
                <div class="w-20 h-20">
                    <svg viewBox="0 0 100 100" class="w-20 h-20">
                        <defs>
                            <linearGradient id="collectGrad" x1="0%" x2="100%">
                                <stop offset="0%" stop-color="#34D399" />
                                <stop offset="100%" stop-color="#059669" />
                            </linearGradient>
                        </defs>
                        <circle cx="50" cy="50" r="36" stroke="#E5E7EB" stroke-width="12" fill="none" />
                        <circle cx="50" cy="50" r="36" stroke="url(#collectGrad)" stroke-width="12" fill="none"
                            stroke-dasharray="{{ $rcCirc }}" stroke-dashoffset="{{ $rcOffset }}" transform="rotate(-90 50 50)" stroke-linecap="round" />
                        <text x="50" y="56" text-anchor="middle" font-size="12" fill="#064E3B" class="font-semibold">{{ $collectionPercent }}%</text>
                    </svg>
                </div>
                <div class="text-sm text-slate-600">
                    <div class="font-semibold {{ $paidTenantCount === $expectedTenantCount ? 'text-emerald-600' : 'text-amber-600' }}">{{ $paidTenantCount }}/{{ $expectedTenantCount }}</div>
                    <div class="text-xs text-slate-400">tenants paid</div>
                </div>
            </div>
        </div>
        @else
        <div class="bg-white rounded-xl border border-slate-100 p-4 flex items-center justify-center text-slate-400 text-sm">No expected rent this month</div>
        @endif

        
    </div>

   

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-2">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-2">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Tab Navigation --}}
    <div class="bg-white rounded-xl border border-slate-100 p-1 overflow-x-auto">
        <nav class="flex gap-1" aria-label="Tabs">
            <template x-for="t in tabs" :key="t.key">
                <template x-if="t.href">
                    <a :href="t.href"
                        class="whitespace-nowrap px-4 py-2.5 text-sm font-medium transition rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-50"
                        x-text="t.label"></a>
                </template>
                <template x-if="!t.href">
                    <button @click="tab = t.key"
                        :class="tab === t.key
                            ? 'bg-slate-800 text-white shadow-sm'
                            : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'"
                        class="whitespace-nowrap px-4 py-2.5 text-sm font-medium transition rounded-lg"
                        x-text="t.label">
                    </button>
                </template>
            </template>
        </nav>
    </div>

    {{-- ================================================== --}}
    {{-- TAB 1: OVERVIEW --}}
    {{-- ================================================== --}}
    <div x-show="tab === 'overview'" x-init="$nextTick(() => { if(typeof createOrUpdateCharts==='function') createOrUpdateCharts(); })">

        {{-- Income & Expense Breakdown Charts --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">Income Breakdown</h2>
                <div class="relative" style="height:260px;">
                    <canvas id="incomeChart"></canvas>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">Expense Breakdown</h2>
                <div class="relative" style="height:260px;">
                    <canvas id="expenseChart"></canvas>
                </div>
            </div>
        </div>

       
        {{-- Per-Apartment Table with Grouping Toggle (now with sub-tabs) --}}
        @if(isset($perApartment) && count($perApartment) > 0)
        @php
            // Attempt to group by floor number from available keys
            $groupedByFloor = collect($perApartment)->groupBy(function($a) {
                return $a['floor_number'] ?? $a['floor'] ?? $a['apartment_floor'] ?? 'Unspecified';
            });
        @endphp
        <div class="mt-4 space-y-3">
            <div class="bg-white rounded-xl border border-slate-100 p-2">
                <nav class="flex gap-1" aria-label="Sub tabs">
                    <button @click="subtab = 'apartments'" :class="subtab === 'apartments' ? 'bg-slate-800 text-white' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'" class="whitespace-nowrap px-4 py-2.5 text-sm font-medium transition rounded-lg">Apartment Summary</button>
                    <button @click="subtab = 'transactions'" :class="subtab === 'transactions' ? 'bg-slate-800 text-white' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'" class="whitespace-nowrap px-4 py-2.5 text-sm font-medium transition rounded-lg">Recent Transactions</button>
                </nav>
            </div>

            <div x-show="subtab === 'apartments'" x-cloak>
                <div class="bg-white rounded-xl border border-slate-100 overflow-hidden" x-data="{ showAll: false, expenseForm: null, groupBy: 'apartment' }">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h2 class="text-sm font-semibold text-slate-800">Per-Apartment Summary</h2>
                    <div class="text-xs text-slate-400">·</div>
                    <div class="text-xs text-slate-400">Group by:</div>
                    <div class="inline-flex bg-slate-50 rounded-lg p-1">
                        <button @click="groupBy = 'apartment'" :class="groupBy === 'apartment' ? 'bg-slate-800 text-white' : 'text-slate-600'" class="px-2 py-1 text-xs rounded">Apartment</button>
                        <button @click="groupBy = 'floor'" :class="groupBy === 'floor' ? 'bg-slate-800 text-white' : 'text-slate-600'" class="px-2 py-1 text-xs rounded">Floor</button>
                    </div>
                </div>
                <div>
                    <button @click="showAll = !showAll" class="text-xs text-sky-600 hover:text-sky-800 font-medium mr-3">
                        <span x-text="showAll ? 'Occupied Only' : 'Show All'"></span>
                    </button>
                    <button type="button" onclick="openSummaryPreview()" class="p-1 text-slate-400 hover:text-slate-600 rounded-lg" title="Preview apartment summary before export">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    </button>
                </div>
            </div>

            {{-- Default: Apartment list (existing table) --}}
            <div class="overflow-x-auto" x-show="groupBy === 'apartment'" x-cloak>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-slate-50/80 text-[11px] text-slate-400 uppercase tracking-wider">
                            <th class="text-left px-4 py-2 font-medium">Unit</th>
                            <th class="text-left px-4 py-2 font-medium">Tenant</th>
                            <th class="text-right px-4 py-2 font-medium">Rent</th>
                            <th class="text-right px-4 py-2 font-medium">Income</th>
                            <th class="text-right px-4 py-2 font-medium">Utilities</th>
                            <th class="text-right px-4 py-2 font-medium" title="Income + Utilities = Total tenant pays to owner">Net Profit<br><span class="text-[9px] normal-case text-slate-400"></span></th>
                            <th class="text-center px-4 py-2 font-medium">Status</th>
                            <th class="text-center px-4 py-2 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($perApartment as $aptIdx => $apt)
                        <tr class="{{ !$apt['has_active_rental'] ? 'text-slate-300' : 'text-slate-700' }}" x-show="showAll || {{ $apt['has_active_rental'] ? 'true' : 'false' }}">
                            <td class="px-4 py-2 font-medium {{ $apt['has_active_rental'] ? 'text-slate-800' : '' }}">{{ $apt['apartment_number'] }}</td>
                            <td class="px-4 py-2">{{ $apt['has_active_rental'] ? $apt['tenant'] : 'Vacant' }}</td>
                            <td class="px-4 py-2 text-right">${{ number_format($apt['monthly_rent'], 2) }}</td>
                            {{-- Income moved up; Status column added before Action --}}
                            <td class="px-4 py-2 text-right {{ $apt['income'] > 0 ? 'text-emerald-600 font-medium' : '' }}">${{ number_format($apt['income'], 2) }}</td>
                            <td class="px-4 py-2 text-right {{ $apt['expenses'] > 0 ? 'text-sky-600 font-medium' : '' }}">
                                @if($apt['expenses'] > 0 && isset($apt['expense_breakdown']))
                                <div x-data="{ showBreakdown: false }" class="relative inline-block">
                                    <button type="button" @click="showBreakdown = !showBreakdown" class="underline decoration-dotted cursor-pointer hover:text-sky-800">
                                        ${{ number_format($apt['expenses'], 2) }}
                                    </button>
                                    <div x-show="showBreakdown" x-cloak @click.away="showBreakdown = false"
                                        class="absolute right-0 top-full mt-1 z-20 bg-white border border-slate-100 rounded-lg shadow-lg p-3 w-48 text-left">
                                        <p class="text-[10px] font-semibold text-slate-400 uppercase mb-1.5">Expense Breakdown</p>
                                        @foreach($apt['expense_breakdown'] as $type => $amount)
                                            @if($amount > 0)
                                            <div class="flex justify-between text-xs py-0.5">
                                                <span class="text-slate-500">
                                                    @switch($type)
                                                        @case('electricity') ⚡ Electricity @break
                                                        @case('water') 💧 Water @break
                                                        @case('internet') 📡 Internet @break
                                                        @case('parking') 🚗 Parking @break
                                                        @default {{ ucfirst($type) }}
                                                    @endswitch
                                                </span>
                                                <span class="font-medium text-sky-600">${{ number_format($amount, 2) }}</span>
                                            </div>
                                            @endif
                                        @endforeach
                                        <div class="border-t mt-1 pt-1 flex justify-between text-xs font-semibold">
                                            <span>Total</span>
                                            <span class="text-sky-700">${{ number_format($apt['expenses'], 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                                @else
                                ${{ number_format($apt['expenses'], 2) }}
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right font-semibold {{ ($apt['tenant_net'] ?? ($apt['income'] + $apt['expenses'])) >= 0 ? 'text-emerald-700' : 'text-red-600' }}">
                                @php $tenantNet = $apt['tenant_net'] ?? ($apt['income'] + $apt['expenses']); @endphp
                                ${{ number_format($tenantNet, 2) }}
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if(!$apt['has_active_rental'])
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500">Vacant</span>
                                @else
                                    @php
                                        $isPaid = $apt['paid_this_month'] ?? ($apt['rent_status'] === 'paid');
                                        $collected = $apt['collected'] ?? ($apt['income'] ?? 0);
                                        $due = $apt['prorated_rent'] ?? $apt['monthly_rent'] ?? 0;
                                        $progress = $due > 0 ? min(round(($collected / $due) * 100, 1), 100) : 0;
                                        $occWidth = $apt['occupancy_percent'] ?? $progress;
                                    @endphp

                                    @if($isPaid)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Paid</span>
                                    @else
                                        @if(($apt['rent_status'] ?? '') === 'partial')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Partial</span>
                                        @elseif(($apt['rent_status'] ?? '') === 'overdue')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Overdue</span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Pending</span>
                                        @endif
                                    @endif
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if($apt['has_active_rental'] && $apt['rental_id'])
                                <div class="flex items-center justify-center gap-1">
                                    @if($apt['tenant_id'])
                                    <a href="{{ route('admin.tenants.show', $apt['tenant_id']) }}" title="View Tenant"
                                        class="p-1 rounded bg-sky-100 text-sky-600 hover:bg-sky-200 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    </a>
                                    @endif
                                    <a href="{{ route('admin.apartments.show', $apt['apartment_id']) }}" title="View Apartment"
                                        class="p-1 rounded bg-emerald-100 text-emerald-600 hover:bg-green-200 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4"/></svg>
                                    </a>
                                </div>
                                @else
                                <span class="text-slate-300 text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                        {{-- Inline Expense Form Row --}}
                        @if($apt['has_active_rental'] && $apt['rental_id'])
                        <tr x-show="expenseForm === {{ $aptIdx }}" x-cloak x-transition.opacity>
                            <td colspan="8" class="px-4 py-3 bg-orange-50/50">
                                <form action="{{ route('admin.revenue_expense.store_expense') }}" method="POST" class="flex flex-wrap items-end gap-3">
                                    @csrf
                                    <input type="hidden" name="rental_id" value="{{ $apt['rental_id'] }}">
                                    <div class="text-xs">
                                        <p class="font-semibold text-slate-700 mb-1">Assign Expense — {{ $apt['apartment_number'] }} <span class="text-slate-400 font-normal">({{ $apt['tenant'] }})</span></p>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Type</label>
                                        <select name="utility_type" required class="px-2 py-1.5 text-xs border border-slate-200 rounded-md focus:ring-orange-500 focus:border-orange-500 w-28">
                                            <option value="electricity">⚡ Electricity</option>
                                            <option value="water">💧 Water</option>
                                            <option value="internet">📡 Internet</option>
                                            <option value="parking">🚗 Parking</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Amount ($)</label>
                                        <input type="number" name="charge_amount" step="0.01" min="0.01" required
                                            class="px-2 py-1.5 text-xs border border-slate-200 rounded-md focus:ring-orange-500 focus:border-orange-500 w-24">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Date</label>
                                        <input type="date" name="transaction_date" value="{{ date('Y-m-d') }}" required
                                            class="px-2 py-1.5 text-xs border border-slate-200 rounded-md focus:ring-orange-500 focus:border-orange-500 w-32 bg-white appearance-none h-10">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Meter In</label>
                                        <input type="number" name="meter_reading_in" step="0.01" min="0" placeholder="0"
                                            class="px-2 py-1.5 text-xs border border-slate-200 rounded-md focus:ring-orange-500 focus:border-orange-500 w-20">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Meter Out</label>
                                        <input type="number" name="meter_reading_out" step="0.01" min="0" placeholder="0"
                                            class="px-2 py-1.5 text-xs border border-slate-200 rounded-md focus:ring-orange-500 focus:border-orange-500 w-20">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-slate-400 mb-0.5">Note</label>
                                        <input type="text" name="note" placeholder="Optional" maxlength="1000"
                                            class="px-2 py-1.5 text-xs border border-slate-200 rounded-md focus:ring-orange-500 focus:border-orange-500 w-32">
                                    </div>
                                    <button type="submit" class="px-3 py-1.5 bg-orange-600 text-white text-xs font-medium rounded-md hover:bg-orange-700 transition">
                                        Save Expense
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 bg-slate-50/80 font-semibold text-slate-800">
                            <td class="px-4 py-2" colspan="3">Total</td>
                            <td class="px-4 py-2 text-right text-emerald-600">${{ number_format(collect($perApartment)->sum('income'), 2) }}</td>
                            <td class="px-4 py-2 text-right text-sky-600">${{ number_format(collect($perApartment)->sum('expenses'), 2) }}</td>
                            @php
                                $totalTenantNet = collect($perApartment)->sum(fn($a) => $a['tenant_net'] ?? ($a['income'] + $a['expenses']));
                            @endphp
                            <td class="px-4 py-2 text-right text-emerald-700">
                                ${{ number_format($totalTenantNet, 2) }}
                            </td>
                            <td class="px-4 py-2"></td>
                            <td class="px-4 py-2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Grouped by Floor view --}}
            <div class="overflow-x-auto" x-show="groupBy === 'floor'" x-cloak>
                @foreach($groupedByFloor as $floor => $items)
                <div class="p-4 border-b border-slate-100">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-800">Floor: {{ $floor }}</h3>
                            <p class="text-xs text-slate-400 mt-0.5">{{ count($items) }} unit{{ count($items) !== 1 ? 's' : '' }}</p>
                        </div>
                        <div class="text-xs text-slate-400">Floor summary — Rent: ${{ number_format(collect($items)->sum('monthly_rent'), 2) }} · Income: ${{ number_format(collect($items)->sum('income'), 2) }} · Utilities: ${{ number_format(collect($items)->sum('expenses'), 2) }}</div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-slate-50/80 text-[11px] text-slate-400 uppercase tracking-wider">
                                    <th class="text-left px-4 py-2 font-medium">Unit</th>
                                    <th class="text-left px-4 py-2 font-medium">Tenant</th>
                                    <th class="text-right px-4 py-2 font-medium">Rent</th>
                                    <th class="text-right px-4 py-2 font-medium">Income</th>
                                    <th class="text-right px-4 py-2 font-medium">Utilities</th>
                                    <th class="text-right px-4 py-2 font-medium">Net</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach($items as $aptIdx => $apt)
                                <tr class="{{ !$apt['has_active_rental'] ? 'text-slate-300' : 'text-slate-700' }}">
                                    <td class="px-4 py-2 font-medium">{{ $apt['apartment_number'] }}</td>
                                    <td class="px-4 py-2">{{ $apt['has_active_rental'] ? $apt['tenant'] : 'Vacant' }}</td>
                                    <td class="px-4 py-2 text-right">${{ number_format($apt['monthly_rent'], 2) }}</td>
                                    <td class="px-4 py-2 text-right">${{ number_format($apt['income'], 2) }}</td>
                                    <td class="px-4 py-2 text-right">${{ number_format($apt['expenses'], 2) }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">${{ number_format($apt['tenant_net'] ?? ($apt['income'] + $apt['expenses']), 2) }}</td>
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
    </div>

<script>
    function openSummaryPreview() {
        const start = '{{ now()->startOfMonth()->toDateString() }}';
        const end = '{{ now()->endOfMonth()->toDateString() }}';
        const url = '{{ route('admin.revenue_expense.apartment_summary_preview') }}' + '?start=' + encodeURIComponent(start) + '&end=' + encodeURIComponent(end);
        window.open(url, '_blank');
    }
</script>

    {{-- ================================================== --}}
    {{-- TAB 2: RECORD INCOME --}}
    {{-- ================================================== --}}
    {{-- Recent Transactions (bottom, per-apartment style) --}}
    <div class="mt-6" x-show="subtab === 'transactions'" x-cloak>
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h2 class="text-sm font-semibold text-slate-800">Recent Transactions</h2>
                    <p class="text-xs text-slate-400">· Income & Expenses</p>
                </div>
                <div class="text-xs text-slate-400">Showing recent activity across apartments</div>
            </div>

            <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                    <h3 class="text-xs font-medium text-slate-500 mb-2">Income</h3>
                            @if($recentIncome->isEmpty())
                                <p class="text-sm text-slate-400">No income recorded yet</p>
                            @else
                                <div class="space-y-2">
                                    @foreach($recentIncome as $record)
                                    <div class="p-2 rounded-lg border border-emerald-100 bg-emerald-50 flex items-start justify-between">
                                        <div class="text-xs text-slate-700">
                                            <div class="font-medium">{{ ucfirst(str_replace('_', ' ', $record->category)) }} — {{ $record->description }}</div>
                                            <div class="text-[11px] text-slate-400">{{ \Carbon\Carbon::parse($record->transaction_date)->format('M d, Y') }}</div>
                                        </div>
                                        <div class="font-semibold text-emerald-600">${{ number_format($record->amount, 2) }}</div>
                                    </div>
                                    @endforeach
                                </div>
                            @endif
                </div>

                <div>
                    <h3 class="text-xs font-medium text-slate-500 mb-2">Expenses</h3>
                    @if($recentExpenses->isEmpty())
                        <p class="text-sm text-slate-400">No expenses recorded yet</p>
                    @else
                        <div class="space-y-2">
                            @foreach($recentExpenses as $record)
                            <div class="p-2 rounded-lg border border-red-100 bg-red-50 flex items-start justify-between">
                                <div class="text-xs text-slate-700">
                                    <div class="font-medium">{{ ucfirst(str_replace('_', ' ', $record->category)) }} — {{ $record->description }}</div>
                                    <div class="text-[11px] text-slate-400">{{ \Carbon\Carbon::parse($record->transaction_date)->format('M d, Y') }}</div>
                                </div>
                                <div class="font-semibold text-red-600">${{ number_format($record->amount, 2) }}</div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div x-show="tab === 'income'" x-cloak>

        {{-- Income Summary Row --}}
        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">Expected Rent</p>
                <p class="text-xl font-bold text-sky-600 mt-1">${{ number_format($totalRentExpected, 2) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">Collected</p>
                <p class="text-xl font-bold text-emerald-600 mt-1">${{ number_format($totalRentCollected, 2) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">Collection Rate</p>
                <p class="text-xl font-bold {{ $totalRentCollected >= $totalRentExpected ? 'text-emerald-600' : 'text-orange-600' }} mt-1">
                    {{ $totalRentExpected > 0 ? round(($totalRentCollected / $totalRentExpected) * 100, 1) : 0 }}%
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">

                {{-- Bulk Monthly Rent --}}
                <div class="bg-white rounded-xl border border-slate-100 p-5">
                    <h2 class="text-sm font-semibold text-slate-800 mb-1 pb-2 border-b border-slate-100">Auto Generate Monthly Rent</h2>
                    <p class="text-xs text-slate-400 mb-3">Select apartments and record rent for all at once.</p>

                    @if($apartmentSummary && $apartmentSummary->total() > 0)
                    <form action="{{ route('admin.revenue_expense.store_income_bulk') }}" method="POST" id="bulkRentForm">
                        @csrf
                        <div class="grid grid-cols-2 gap-3 mb-4 p-3 bg-sky-50 rounded-lg">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Payment Date *</label>
                                <input type="date" name="payment_date" required value="{{ date('Y-m-d') }}" class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded focus:ring-sky-500 focus:border-sky-500 bg-white appearance-none h-10">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Payment Method *</label>
                                <select name="payment_method" required class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded focus:ring-sky-500 focus:border-sky-500">
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank Transfer</option>
                                </select>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm" id="bulkRentTable">
                                <thead class="bg-slate-50/80">
                                    <tr>
                                        <th class="px-2 py-2 text-center w-8"><input type="checkbox" id="selectAll" class="w-4 h-4 text-sky-600 rounded cursor-pointer" title="Select All"></th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-400 uppercase">Apartment</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-400 uppercase">Tenant</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">Rent ($)</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">Late Fee</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-slate-400 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach($apartmentSummary as $index => $s)
                                    <tr class="{{ $s['paid_this_month'] ? 'bg-emerald-50/50' : '' }} hover:bg-slate-50">
                                        <td class="px-2 py-2 text-center">
                                            <input type="hidden" name="apartments[{{ $index }}][rental_id]" value="{{ $s['rental']->id }}">
                                            <input type="checkbox" name="apartments[{{ $index }}][selected]" value="1"
                                                class="apt-checkbox w-4 h-4 text-sky-600 rounded cursor-pointer"
                                                {{ $s['paid_this_month'] ? '' : 'checked' }}>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="font-medium text-slate-800">{{ $s['apartment']->apartment_number }}</span>
                                            <span class="text-xs text-slate-400 ml-1">F{{ $s['apartment']->floor->floor_number ?? '?' }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-slate-500">{{ $s['rental']->tenant->name ?? 'N/A' }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" name="apartments[{{ $index }}][amount]" step="0.01" min="0.01"
                                                value="{{ $s['monthly_rent'] }}" class="w-24 px-2 py-1 text-right text-sm border rounded focus:ring-sky-500 focus:border-sky-500 font-semibold text-sky-600">
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" name="apartments[{{ $index }}][late_fee]" step="0.01" min="0" value="0"
                                                class="w-20 px-2 py-1 text-right text-sm border rounded focus:ring-sky-500 focus:border-sky-500">
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($s['paid_this_month'])
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Paid</span>
                                            @else
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-slate-50/80">
                                    <tr>
                                        <td class="px-2 py-2"></td>
                                        <td class="px-3 py-2 font-semibold text-slate-800" colspan="2">Total Selected</td>
                                        <td class="px-3 py-2 text-right font-bold text-sky-600" id="totalSelectedAmount">${{ number_format($totalRentExpected, 2) }}</td>
                                        <td class="px-3 py-2 text-right font-bold text-amber-600" id="totalSelectedLateFee">$0.00</td>
                                        <td class="px-3 py-2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <button type="submit" class="px-4 py-2 bg-slate-800 text-white text-sm rounded-lg hover:bg-slate-700 transition font-medium">
                                Record Selected Rent
                            </button>
                            <span class="text-xs text-slate-400" id="selectedCount">{{ $apartmentSummary->total() }} selected</span>
                        </div>
                    </form>
                    <div class="mt-3">{{ $apartmentSummary->withQueryString()->links() }}</div>
                    @else
                    <p class="text-center py-6 text-slate-400 text-sm">No apartments with active rentals.</p>
                    @endif
                </div>

                {{-- Record Other Income --}}
                <div class="bg-white rounded-xl border border-slate-100 p-5">
                    <h2 class="text-sm font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-100">Record Other Income</h2>

                    <form action="{{ route('admin.revenue_expense.store_income') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Apartment *</label>
                            <select name="rental_id" id="rental_id" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                                <option value="">-- Select apartment --</option>
                                @foreach($apartments as $apartment)
                                    @foreach($apartment->rentals as $rental)
                                    <option value="{{ $rental->id }}" data-rent="{{ $rental->rent_amount }}" {{ old('rental_id') == $rental->id ? 'selected' : '' }}>
                                        {{ $apartment->apartment_number }} (F{{ $apartment->floor->floor_number ?? '?' }}) — {{ $rental->tenant->name ?? 'N/A' }}
                                    </option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Type *</label>
                                <select name="payment_type" id="payment_type" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                                    <option value="rent">Rent</option>
                                    <option value="utilities">Utilities</option>
                                    <option value="deposit">Deposit</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Amount ($) *</label>
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01" required value="{{ old('amount') }}" placeholder="0.00"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Method *</label>
                                <select name="payment_method" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank Transfer</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Date *</label>
                                <input type="date" name="transaction_date" required value="{{ old('transaction_date', date('Y-m-d')) }}" class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500 bg-white appearance-none h-10">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Late Fee ($)</label>
                                <input type="number" name="late_fee" step="0.01" min="0" value="{{ old('late_fee', '0') }}" class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Reference</label>
                                <input type="text" name="transaction_reference" value="{{ old('transaction_reference') }}" placeholder="TXN-001234"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                            </div>
                        </div>
                        <div class="mt-3">
                            <textarea name="note" rows="1" placeholder="Optional note..." class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">{{ old('note') }}</textarea>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition font-medium">Record Income</button>
                        </div>
                    </form>
                </div>
            </div>

            
        </div>
    </div>

    {{-- ================================================== --}}
    {{-- TAB 3: RECORD EXPENSE --}}
    {{-- ================================================== --}}
    <div x-show="tab === 'expense'" x-cloak>

        {{-- Expense Summary --}}
        <div class="bg-white rounded-xl border border-slate-100 p-5 mb-4">
            <p class="text-xs text-slate-400 font-medium">Total Utility Expenses (This Period)</p>
            <p class="text-xl font-bold text-red-600 mt-1">${{ number_format($totalExpensesAmount, 2) }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">

                {{-- Expenses per Apartment Table --}}
                @if(count($apartmentExpenses) > 0)
                <div class="bg-white rounded-xl border border-slate-100 p-5">
                    <h2 class="text-sm font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-100">Expenses per Apartment</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50/80">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-400 uppercase">Apartment</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-slate-400 uppercase">Status</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">⚡</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">💧</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">📡</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">🚗</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach($apartmentExpenses as $aptExp)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2">
                                        <span class="font-medium text-slate-800">{{ $aptExp['apartment']->apartment_number }}</span>
                                        <span class="text-xs text-slate-400 ml-1">F{{ $aptExp['apartment']->floor->floor_number ?? '?' }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 rounded-full text-xs font-medium {{ $aptExp['has_active_rental'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">
                                            {{ $aptExp['has_active_rental'] ? 'Occupied' : 'Vacant' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right {{ $aptExp['electricity'] > 0 ? 'font-semibold text-amber-600' : 'text-slate-300' }}">${{ number_format($aptExp['electricity'], 2) }}</td>
                                    <td class="px-3 py-2 text-right {{ $aptExp['water'] > 0 ? 'font-semibold text-sky-600' : 'text-slate-300' }}">${{ number_format($aptExp['water'], 2) }}</td>
                                    <td class="px-3 py-2 text-right {{ $aptExp['internet'] > 0 ? 'font-semibold text-purple-600' : 'text-slate-300' }}">${{ number_format($aptExp['internet'], 2) }}</td>
                                    <td class="px-3 py-2 text-right {{ $aptExp['parking'] > 0 ? 'font-semibold text-orange-600' : 'text-slate-300' }}">${{ number_format($aptExp['parking'], 2) }}</td>
                                    <td class="px-3 py-2 text-right font-bold text-red-600">${{ number_format($aptExp['total'], 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-slate-50/80">
                                <tr class="font-semibold">
                                    <td class="px-3 py-2 text-slate-800" colspan="2">Grand Total</td>
                                    <td class="px-3 py-2 text-right text-amber-600">${{ number_format(collect($apartmentExpenses)->sum('electricity'), 2) }}</td>
                                    <td class="px-3 py-2 text-right text-sky-600">${{ number_format(collect($apartmentExpenses)->sum('water'), 2) }}</td>
                                    <td class="px-3 py-2 text-right text-purple-600">${{ number_format(collect($apartmentExpenses)->sum('internet'), 2) }}</td>
                                    <td class="px-3 py-2 text-right text-orange-600">${{ number_format(collect($apartmentExpenses)->sum('parking'), 2) }}</td>
                                    <td class="px-3 py-2 text-right text-red-600">${{ number_format($totalExpensesAmount, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                @endif

                {{-- Record Expense Form --}}
                <div class="bg-white rounded-xl border border-slate-100 p-5">
                    <h2 class="text-sm font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-100">Record Utility Expense</h2>

                    <form action="{{ route('admin.revenue_expense.store_expense') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-slate-700 mb-1">Apartment *</label>
                                <select name="rental_id" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                                    <option value="">-- Select --</option>
                                    @foreach($expenseApartments as $apartment)
                                        @foreach($apartment->rentals as $rental)
                                        <option value="{{ $rental->id }}" {{ old('rental_id') == $rental->id ? 'selected' : '' }}>
                                            {{ $apartment->apartment_number }} (F{{ $apartment->floor->floor_number ?? '?' }}) — {{ $rental->tenant->name ?? 'N/A' }}
                                        </option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Type *</label>
                                <select name="utility_type" id="utility_type" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                                    <option value="">-- Select --</option>
                                    @foreach($utilityTypes as $key => $label)
                                    <option value="{{ $key }}" {{ old('utility_type') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Amount ($) *</label>
                                <input type="number" name="charge_amount" step="0.01" min="0.01" required value="{{ old('charge_amount') }}" placeholder="0.00"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Date *</label>
                                <input type="date" name="transaction_date" required value="{{ old('transaction_date', date('Y-m-d')) }}"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Meter In</label>
                                <input type="number" name="meter_reading_in" step="0.01" min="0" value="{{ old('meter_reading_in') }}" placeholder="0"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Meter Out</label>
                                <input type="number" name="meter_reading_out" step="0.01" min="0" value="{{ old('meter_reading_out') }}" placeholder="0"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Note</label>
                                <input type="text" name="note" value="{{ old('note') }}" placeholder="Optional..."
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition font-medium">Record Expense</button>
                        </div>
                    </form>
                </div>
            </div>

            
        </div>
    </div>

    {{-- ================================================== --}}
    {{-- TAB 4: FIXED COSTS --}}
    {{-- ================================================== --}}
    <div x-show="tab === 'fixed'" x-cloak>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Apartment Fixed Expenses List --}}
            <div class="lg:col-span-2 space-y-3">
                @forelse($fixedApartments as $fa)
                <div class="bg-white rounded-xl border border-slate-100 p-4">
                    <div class="flex items-center justify-between mb-2 pb-2 border-b border-slate-100">
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">
                                {{ $fa->apartment_number }}
                                <span class="text-xs font-normal text-slate-400 ml-1">F{{ $fa->floor->floor_number ?? '?' }}</span>
                            </h3>
                            @if($fa->rentals->isNotEmpty())
                            @php $r = $fa->rentals->first(); @endphp
                            <p class="text-xs text-slate-400">{{ $r->tenant->name ?? 'N/A' }} — ${{ number_format($r->rent_amount, 2) }}/mo</p>
                            @else
                            <p class="text-xs text-slate-400 italic">No active tenant</p>
                            @endif
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $fa->status === 'occupied' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">
                            {{ ucfirst($fa->status) }}
                        </span>
                    </div>

                    @if($fa->fixedExpenses->isNotEmpty())
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-2 py-1.5 text-left text-xs font-medium text-slate-400">Expense</th>
                                <th class="px-2 py-1.5 text-left text-xs font-medium text-slate-400">Type</th>
                                <th class="px-2 py-1.5 text-right text-xs font-medium text-slate-400">Amount</th>
                                <th class="px-2 py-1.5 text-center text-xs font-medium text-slate-400">Status</th>
                                <th class="px-2 py-1.5 text-center text-xs font-medium text-slate-400 w-16">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($fa->fixedExpenses as $expense)
                            <tr class="{{ $expense->is_active ? '' : 'opacity-50' }}">
                                <td class="px-2 py-1.5 font-medium text-slate-700">{{ $expense->expense_name }}</td>
                                <td class="px-2 py-1.5">
                                    @php $icons = ['parking'=>'🚗','internet'=>'📡','trash'=>'🗑️','other'=>'📋']; @endphp
                                    <span class="text-xs">{{ $icons[$expense->expense_type] ?? '📋' }} {{ ucfirst($expense->expense_type) }}</span>
                                </td>
                                <td class="px-2 py-1.5 text-right font-semibold text-red-600">${{ number_format($expense->amount, 2) }}</td>
                                <td class="px-2 py-1.5 text-center">
                                    <span class="px-1.5 py-0.5 rounded-full text-xs font-medium {{ $expense->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">
                                        {{ $expense->is_active ? 'Active' : 'Off' }}
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                    <div class="flex justify-center gap-1">
                                        <form action="{{ route('admin.revenue_expense.toggle_fixed_expense', $expense) }}" method="POST" class="inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="p-1 rounded hover:bg-slate-100" title="{{ $expense->is_active ? 'Disable' : 'Enable' }}">
                                                <svg class="w-3.5 h-3.5 {{ $expense->is_active ? 'text-amber-600' : 'text-emerald-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    @if($expense->is_active)
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                    @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    @endif
                                                </svg>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.revenue_expense.delete_fixed_expense', $expense) }}" method="POST" class="inline" onsubmit="return confirm('Remove?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-1 rounded hover:bg-red-50"><svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50/80">
                            <tr>
                                <td class="px-2 py-1.5 font-semibold text-slate-800" colspan="2">Monthly Total</td>
                                <td class="px-2 py-1.5 text-right font-bold text-red-600">${{ number_format($fa->fixedExpenses->where('is_active', true)->sum('amount'), 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                    @else
                    <p class="text-center py-2 text-slate-400 text-xs">No fixed expenses assigned</p>
                    @endif
                </div>
                @empty
                <div class="bg-white rounded-xl border border-slate-100 p-8 text-center text-slate-400 text-sm">No apartments found.</div>
                @endforelse
            </div>

            {{-- Add Fixed Expense Form --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-slate-100 p-5 sticky top-8">
                    <h3 class="text-sm font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-100">Add Fixed Expense</h3>

                    <form action="{{ route('admin.revenue_expense.store_fixed_expense') }}" method="POST">
                        @csrf
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Apartment *</label>
                                <select name="apartment_id" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                                    <option value="">-- Select --</option>
                                    @foreach($fixedApartments as $fa)
                                    <option value="{{ $fa->id }}" {{ old('apartment_id') == $fa->id ? 'selected' : '' }}>{{ $fa->apartment_number }} (F{{ $fa->floor->floor_number ?? '?' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Type *</label>
                                <select name="expense_type" id="expense_type" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                                    <option value="">-- Select --</option>
                                    <option value="parking" {{ old('expense_type') == 'parking' ? 'selected' : '' }}>🚗 Parking</option>
                                    <option value="internet" {{ old('expense_type') == 'internet' ? 'selected' : '' }}>📡 Internet</option>
                                    <option value="trash" {{ old('expense_type') == 'trash' ? 'selected' : '' }}>🗑️ Trash</option>
                                    <option value="other" {{ old('expense_type') == 'other' ? 'selected' : '' }}>📋 Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Name *</label>
                                <input type="text" name="expense_name" id="expense_name" required value="{{ old('expense_name') }}" placeholder="e.g. Parking A1"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Monthly Amount ($) *</label>
                                <input type="number" name="amount" step="0.01" min="0.01" required value="{{ old('amount') }}" placeholder="0.00"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Note</label>
                                <textarea name="note" rows="2" class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">{{ old('note') }}</textarea>
                            </div>
                            <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition font-medium">
                                Assign Fixed Expense
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================== --}}
    {{-- TAB 5: GENERATE BILLS --}}
    {{-- ================================================== --}}
    <div x-show="tab === 'bills'" x-cloak>

        @if(count($billSummary) > 0)
        <form action="{{ route('admin.revenue_expense.process_bills') }}" method="POST" id="billForm">
            @csrf

            <div class="bg-white rounded-xl border border-slate-100 p-4 mb-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Billing Date</label>
                        <input type="date" name="billing_date" required value="{{ date('Y-m-d') }}" class="px-3 py-1.5 text-sm border rounded-lg focus:ring-sky-500 focus:border-sky-500 bg-white appearance-none h-10">
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-slate-400">Total Monthly Fixed</p>
                        <p class="text-xl font-bold text-red-600">${{ number_format($totalMonthlyExpenses, 2) }}</p>
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="selectAllBills" class="w-4 h-4 text-sky-600 rounded" checked>
                    <span class="text-xs font-medium text-slate-700">Select All</span>
                </label>
            </div>

            <div class="space-y-3 mb-4">
                @foreach($billSummary as $bi => $bill)
                <div class="bg-white rounded-xl border border-slate-100 overflow-hidden {{ $bill['has_unbilled'] ? '' : 'opacity-60' }}">
                    <div class="p-3 border-b bg-slate-50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="bills[{{ $bi }}][rental_id]" value="{{ $bill['rental']->id }}">
                            <input type="checkbox" name="bills[{{ $bi }}][selected]" value="1"
                                class="bill-checkbox w-4 h-4 text-sky-600 rounded cursor-pointer"
                                {{ $bill['has_unbilled'] ? 'checked' : '' }}>
                            <div>
                                <span class="text-sm font-bold text-slate-800">{{ $bill['apartment']->apartment_number }}</span>
                                <span class="text-xs text-slate-400 ml-1">F{{ $bill['apartment']->floor->floor_number ?? '?' }}</span>
                                <span class="text-xs text-slate-400 ml-2">{{ $bill['tenant_name'] }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-red-600">${{ number_format($bill['total_bill'], 2) }}</p>
                            <p class="text-xs text-slate-400">Rent ${{ number_format($bill['monthly_rent'], 2) }} + Fixed ${{ number_format($bill['total_fixed'], 2) }}</p>
                        </div>
                    </div>

                    @if(count($bill['fixed_expenses']) > 0)
                    <div class="p-3">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($bill['fixed_expenses'] as $ei => $exp)
                            <div class="border rounded p-2 {{ $exp['is_billed'] ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200' }}">
                                <input type="hidden" name="bills[{{ $bi }}][expenses][{{ $ei }}][expense_id]" value="{{ $exp['id'] }}">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-1">
                                        <input type="checkbox" name="bills[{{ $bi }}][expenses][{{ $ei }}][selected]" value="1"
                                            class="w-3.5 h-3.5 text-sky-600 rounded cursor-pointer"
                                            {{ $exp['is_billed'] ? 'disabled' : 'checked' }}>
                                        @php $icons = ['parking'=>'🚗','internet'=>'📡','trash'=>'🗑️','other'=>'📋']; @endphp
                                        <span class="text-xs font-medium">{{ $icons[$exp['type']] ?? '📋' }} {{ $exp['name'] }}</span>
                                    </div>
                                    @if($exp['is_billed'])
                                    <span class="text-xs text-emerald-700 font-medium">✓</span>
                                    @endif
                                </div>
                                <input type="number" name="bills[{{ $bi }}][expenses][{{ $ei }}][amount]"
                                    step="0.01" min="0" value="{{ $exp['amount'] }}"
                                    class="w-full px-2 py-1 text-xs text-right border rounded font-semibold text-red-600"
                                    {{ $exp['is_billed'] ? 'readonly' : '' }}>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @else
                    <p class="p-3 text-xs text-slate-400 text-center">No fixed expenses assigned</p>
                    @endif
                </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between bg-white rounded-xl border border-slate-100 p-4">
                <p class="text-xs text-slate-400">Only unbilled expenses will be generated</p>
                <button type="submit" class="px-5 py-2 bg-slate-800 text-white text-sm rounded-lg hover:bg-slate-700 transition font-medium">
                    Generate Monthly Expenses
                </button>
            </div>
        </form>
        @else
        <div class="bg-white rounded-xl border border-slate-100 p-8 text-center">
            <p class="text-slate-400 text-sm mb-2">No active rentals with fixed expenses found.</p>
            <button @click="tab = 'fixed'" class="text-sky-600 text-sm hover:underline">Set up fixed expenses first</button>
        </div>
        @endif
    </div>

    {{-- ================================================== --}}
    {{-- TAB 6: BREAK-EVEN --}}
    {{-- ================================================== --}}
    <div x-show="tab === 'breakeven'" x-cloak>

        {{-- Key Metrics --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">Total Apartments</p>
                <p class="text-xl font-bold text-sky-600 mt-1">{{ $total_apartments }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">Avg. Rent/Unit</p>
                <p class="text-xl font-bold text-emerald-600 mt-1">${{ number_format($avg_rent_per_apartment, 2) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">Occupancy</p>
                <p class="text-xl font-bold text-purple-600 mt-1">{{ $current_occupancy }}/{{ $total_apartments }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">Status</p>
                <p class="text-lg font-bold mt-1 {{ $is_above_break_even ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $is_above_break_even ? '✓ ABOVE' : '✗ BELOW' }} Break-Even
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Break-Even Calculation --}}
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <h2 class="text-sm font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">Break-Even Calculation</h2>
                <div class="space-y-3">
                    <div class="bg-slate-50 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-slate-700">Fixed Costs (Monthly)</p><p class="text-xs text-slate-400">Internet, maintenance, insurance</p></div>
                        <span class="text-lg font-bold text-slate-800">${{ number_format($fixed_costs, 2) }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-slate-700">Variable Cost/Unit</p><p class="text-xs text-slate-400">Electricity, water, parking</p></div>
                        <span class="text-lg font-bold text-slate-800">${{ number_format($variable_cost_per_unit, 2) }}</span>
                    </div>
                    <div class="bg-sky-50 border border-sky-200 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-slate-700">Contribution Margin/Unit</p><p class="text-xs text-slate-400">${{ number_format($avg_rent_per_apartment, 2) }} - ${{ number_format($variable_cost_per_unit, 2) }}</p></div>
                        <span class="text-lg font-bold text-sky-600">${{ number_format($contribution_margin_per_unit, 2) }}</span>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 p-3 rounded-lg flex justify-between items-center">
                        <p class="text-sm font-medium text-slate-700">Break-Even Units</p>
                        <span class="text-lg font-bold text-amber-700">{{ $break_even_units }} units</span>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 p-3 rounded-lg flex justify-between items-center">
                        <p class="text-sm font-medium text-slate-700">Break-Even Revenue</p>
                        <span class="text-lg font-bold text-amber-700">${{ number_format($break_even_revenue, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Current Performance --}}
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <h2 class="text-sm font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">Current Performance</h2>
                <div class="space-y-3">
                    <div class="bg-emerald-50 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-slate-700">Current Monthly Revenue</p><p class="text-xs text-slate-400">{{ $current_occupancy }} units × ${{ number_format($avg_rent_per_apartment, 2) }}</p></div>
                        <span class="text-lg font-bold text-emerald-600">${{ number_format($current_revenue, 2) }}</span>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-200 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-slate-700">Safety Margin ($)</p><p class="text-xs text-slate-400">Cushion above break-even</p></div>
                        <span class="text-lg font-bold text-emerald-600">${{ number_format($safety_margin, 2) }}</span>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-200 p-3 rounded-lg flex justify-between items-center">
                        <p class="text-sm font-medium text-slate-700">Safety Margin (%)</p>
                        <span class="text-lg font-bold text-emerald-600">{{ $safety_margin_percent }}%</span>
                    </div>
                    <div class="bg-sky-50 border border-sky-200 p-3 rounded-lg">
                        <div class="flex justify-between items-center mb-2">
                            <p class="text-sm font-medium text-slate-700">Occupancy Rate</p>
                            <span class="text-lg font-bold text-sky-600">{{ $total_apartments > 0 ? round(($current_occupancy / $total_apartments) * 100, 1) : 0 }}%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="bg-slate-800 h-2 rounded-full" style="width: {{ $total_apartments > 0 ? (($current_occupancy / $total_apartments) * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Revenue vs Break-Even Comparison --}}
        <div class="bg-white rounded-xl border border-slate-100 p-5 mt-4">
            <h2 class="text-sm font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">Revenue vs. Break-Even</h2>
            @php
                $maxVal = max($current_revenue, $break_even_revenue, 1);
            @endphp
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-slate-500">Break-Even Required</span>
                        <span class="font-bold">${{ number_format($break_even_revenue, 2) }}</span>
                    </div>
                    <div class="w-full bg-slate-200 rounded h-6">
                        <div class="bg-amber-500 h-full rounded flex items-center pl-2 text-white text-xs font-bold" style="width: {{ ($break_even_revenue / $maxVal) * 100 }}%">
                            ${{ number_format($break_even_revenue, 2) }}
                        </div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-slate-500">Current Revenue</span>
                        <span class="font-bold">${{ number_format($current_revenue, 2) }}</span>
                    </div>
                    <div class="w-full bg-slate-200 rounded h-6">
                        <div class="bg-emerald-500 h-full rounded flex items-center pl-2 text-white text-xs font-bold" style="width: {{ ($current_revenue / $maxVal) * 100 }}%">
                            ${{ number_format($current_revenue, 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function revenueExpense() {
    const hash = window.location.hash.replace('#', '');
    const validTabs = ['overview', 'income', 'expense', 'fixed', 'bills', 'breakeven'];
    return {
        tab: validTabs.includes(hash) ? hash : 'overview',
        subtab: 'apartments',
        tabs: [
            { key: 'overview', label: 'Overview' },
            { key: 'income', label: 'Income' },
            { key: 'expense', label: 'Expenses' },
            { key: 'fixed', label: 'Fixed Costs' },
            { key: 'bills', label: 'Bills' },
            { key: 'breakeven', label: 'Break-Even' },
        ],
        init() {
            this.$watch('tab', (val) => { window.location.hash = val; });
        }
    };
}

document.addEventListener('DOMContentLoaded', function() {
    // ===== Bulk Rent - Select All / Totals =====
    const selectAll = document.getElementById('selectAll');
    const aptCbs = document.querySelectorAll('.apt-checkbox');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            aptCbs.forEach(cb => { cb.checked = this.checked; });
            updateBulkTotals();
        });
        aptCbs.forEach(cb => {
            cb.addEventListener('change', function() {
                selectAll.checked = [...aptCbs].every(c => c.checked);
                selectAll.indeterminate = !selectAll.checked && [...aptCbs].some(c => c.checked);
                updateBulkTotals();
            });
        });
    }

    function updateBulkTotals() {
        let total = 0, fees = 0, count = 0;
        aptCbs.forEach(cb => {
            if (cb.checked) {
                const row = cb.closest('tr');
                total += parseFloat(row.querySelector('input[name$="[amount]"]').value) || 0;
                fees += parseFloat(row.querySelector('input[name$="[late_fee]"]').value) || 0;
                count++;
            }
        });
        const fmt = n => '$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        const el = id => document.getElementById(id);
        if (el('totalSelectedAmount')) el('totalSelectedAmount').textContent = fmt(total);
        if (el('totalSelectedLateFee')) el('totalSelectedLateFee').textContent = fmt(fees);
        if (el('selectedCount')) el('selectedCount').textContent = count + ' selected';
    }

    document.querySelectorAll('#bulkRentTable input[type="number"]').forEach(input => {
        input.addEventListener('input', updateBulkTotals);
    });
    if (selectAll) updateBulkTotals();

    // ===== Individual Income - Auto-fill rent =====
    const rentalSel = document.getElementById('rental_id');
    const payType = document.getElementById('payment_type');
    if (rentalSel) {
        rentalSel.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (opt.dataset.rent && payType && payType.value === 'rent') {
                document.getElementById('amount').value = parseFloat(opt.dataset.rent).toFixed(2);
            }
        });
    }
    if (payType) {
        payType.addEventListener('change', function() {
            if (this.value === 'rent' && rentalSel) {
                const opt = rentalSel.options[rentalSel.selectedIndex];
                if (opt.dataset.rent) document.getElementById('amount').value = parseFloat(opt.dataset.rent).toFixed(2);
            }
        });
    }

    // ===== Bills - Select All =====
    const selBills = document.getElementById('selectAllBills');
    const billCbs = document.querySelectorAll('.bill-checkbox');
    if (selBills) {
        selBills.addEventListener('change', function() { billCbs.forEach(cb => { cb.checked = this.checked; }); });
        billCbs.forEach(cb => {
            cb.addEventListener('change', function() {
                selBills.checked = [...billCbs].every(c => c.checked);
                selBills.indeterminate = !selBills.checked && [...billCbs].some(c => c.checked);
            });
        });
    }

    // ===== Fixed Expense - Auto-fill name =====
    const expType = document.getElementById('expense_type');
    const expName = document.getElementById('expense_name');
    if (expType && expName) {
        expType.addEventListener('change', function() {
            const names = { parking: 'Parking', internet: 'Internet', trash: 'Trash Collection', other: '' };
            if (!expName.value || Object.values(names).includes(expName.value)) {
                expName.value = names[this.value] || '';
            }
        });
    }
});

// ===== Chart.js - Income & Expense Breakdown =====
var incomeChartObj = null;
var expenseChartObj = null;

// Data injected directly from PHP — no DOM scraping needed
var incomeData = {
    labels: [
        @if(($income['rent_income'] ?? 0) > 0) 'Rent', @endif
        @if(($income['total_utility_income'] ?? 0) > 0) 'Utilities', @endif
        @if(($income['deposit_income'] ?? 0) > 0) 'Deposits', @endif
        @if(($income['late_fees'] ?? 0) > 0) 'Late Fees', @endif
        @if(($income['other_income'] ?? 0) > 0) 'Other', @endif
    ],
    values: [
        @if(($income['rent_income'] ?? 0) > 0) {{ $income['rent_income'] }}, @endif
        @if(($income['total_utility_income'] ?? 0) > 0) {{ $income['total_utility_income'] }}, @endif
        @if(($income['deposit_income'] ?? 0) > 0) {{ $income['deposit_income'] }}, @endif
        @if(($income['late_fees'] ?? 0) > 0) {{ $income['late_fees'] }}, @endif
        @if(($income['other_income'] ?? 0) > 0) {{ $income['other_income'] }}, @endif
    ]
};

var expenseData = {
    labels: [
        @if(($expenses['fixed_expenses'] ?? 0) > 0) 'Fixed', @endif
        @if(($expenses['variable_expenses'] ?? 0) > 0) 'Variable', @endif
        @if(($expenses['utility_expenses'] ?? 0) > 0) 'Utilities', @endif
        @if(($expenses['other_expenses'] ?? 0) > 0) 'Other', @endif
    ],
    values: [
        @if(($expenses['fixed_expenses'] ?? 0) > 0) {{ $expenses['fixed_expenses'] }}, @endif
        @if(($expenses['variable_expenses'] ?? 0) > 0) {{ $expenses['variable_expenses'] }}, @endif
        @if(($expenses['utility_expenses'] ?? 0) > 0) {{ $expenses['utility_expenses'] }}, @endif
        @if(($expenses['other_expenses'] ?? 0) > 0) {{ $expenses['other_expenses'] }}, @endif
    ]
};

var chartOpts = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '55%',
    plugins: {
        legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, pointStyle: 'circle', font: { size: 11 } } },
        tooltip: {
            callbacks: {
                label: function(ctx) {
                    var val = ctx.parsed;
                    var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                    var pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                    return ctx.label + ': $' + val.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' (' + pct + '%)';
                }
            }
        }
    }
};

function createOrUpdateCharts() {
    if (typeof Chart === 'undefined') return false;
    var incomeCtx = document.getElementById('incomeChart');
    var expenseCtx = document.getElementById('expenseChart');
    if (!incomeCtx || incomeCtx.clientWidth === 0) return false;

    if (incomeData.values.length > 0) {
        if (incomeChartObj) { incomeChartObj.destroy(); }
        incomeChartObj = new Chart(incomeCtx, {
            type: 'doughnut',
            data: { labels: incomeData.labels, datasets: [{ data: incomeData.values, backgroundColor: ['#10B981','#F59E0B','#6366F1','#3B82F6','#8B5CF6'], borderWidth: 0, hoverOffset: 6 }] },
            options: chartOpts
        });
    }

    if (expenseCtx && expenseData.values.length > 0) {
        if (expenseChartObj) { expenseChartObj.destroy(); }
        expenseChartObj = new Chart(expenseCtx, {
            type: 'doughnut',
            data: { labels: expenseData.labels, datasets: [{ data: expenseData.values, backgroundColor: ['#EF4444','#F97316','#6366F1','#EC4899','#14B8A6','#8B5CF6','#F59E0B','#64748B'], borderWidth: 0, hoverOffset: 6 }] },
            options: chartOpts
        });
    }
    return true;
}

// Init charts when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    requestAnimationFrame(function() { createOrUpdateCharts(); });
});
</script>
@endsection
