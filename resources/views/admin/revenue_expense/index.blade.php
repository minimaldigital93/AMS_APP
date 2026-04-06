@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8 space-y-4" x-data="revenueExpense()">

    {{-- Header --}}
    <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Revenue & Expense</h1>
            <p class="text-gray-600 mt-2">
                @if(isset($filterMonth) && $filterMonth)
                    Viewing <span class="font-semibold text-blue-600">{{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }}</span>
                @else
                    Full period overview
                @endif
                — Fiscal Period: <span class="font-semibold text-blue-600">{{ $activePeriod->name }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($fiscalPeriods->count() > 1)
            <form method="GET" action="{{ route('admin.revenue_expense.index') }}">
                <select name="period" onchange="this.form.submit()" class="text-sm border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    @foreach($fiscalPeriods as $fp)
                    <option value="{{ $fp->id }}" {{ $fp->id === $activePeriod->id ? 'selected' : '' }}>{{ $fp->name }}</option>
                    @endforeach
                </select>
            </form>
            @endif
            <button onclick="window.print()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg" title="Print">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            </button>
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
    <div class="mb-6 flex items-center justify-center">
        <div class="inline-flex items-center bg-white rounded-xl shadow-md border border-gray-200 px-2 py-1.5 gap-1">
            {{-- Previous Month --}}
            @if($prevMonth)
            <a href="{{ route('admin.revenue_expense.index', ['period' => $activePeriod->id, 'month' => $prevMonth['month'], 'year' => $prevMonth['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-blue-600 transition" title="Previous Month">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </span>
            @endif

            {{-- Current Month Display --}}
            <div class="px-4 py-2 min-w-[220px] text-center">
                @if($isFilterActive)
                    <span class="text-lg font-bold text-gray-900">{{ $selectedMonth->format('F') }}</span>
                    <span class="text-lg text-gray-500 ml-1">{{ $selectedMonth->format('Y') }}</span>
                    @if($isCurrentMonth)
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Current</span>
                    @elseif($selectedMonth->isFuture())
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Upcoming</span>
                    @else
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Past</span>
                    @endif
                @else
                    <span class="text-lg font-bold text-gray-900">All Months</span>
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Full Period</span>
                @endif
            </div>

            {{-- Next Month --}}
            @if($nextMonth)
            <a href="{{ route('admin.revenue_expense.index', ['period' => $activePeriod->id, 'month' => $nextMonth['month'], 'year' => $nextMonth['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-blue-600 transition" title="Next Month">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
            @endif

            {{-- Quick Actions --}}
            @if($isFilterActive)
            <a href="{{ route('admin.revenue_expense.index', ['period' => $activePeriod->id]) }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 bg-gray-50 rounded-lg hover:bg-gray-100 transition" title="View all months">
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
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition" title="Go to current month">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Today
            </a>
            @endif
            @endif
        </div>
    </div>
    @endif

    {{-- Fiscal Period Progress Bar --}}
    @php
        $periodStart = \Carbon\Carbon::parse($activePeriod->opening_date);
        $periodEnd = \Carbon\Carbon::parse($activePeriod->closing_date);
        $today = now();
        $totalDays = max(1, $periodStart->diffInDays($periodEnd));
        $daysPassed = max(0, (int) $periodStart->diffInDays($today));
        $daysLeft = max(0, (int) $today->diffInDays($periodEnd, false));
        $periodPercent = min(100, max(0, round(($daysPassed / $totalDays) * 100, 1)));

        if ($periodPercent >= 80) {
            $barColor = 'from-red-400 to-red-500';
            $bgColor = 'bg-red-100';
            $textColor = 'text-red-600';
        } elseif ($periodPercent >= 50) {
            $barColor = 'from-orange-400 to-orange-500';
            $bgColor = 'bg-orange-100';
            $textColor = 'text-orange-600';
        } else {
            $barColor = 'from-blue-400 to-blue-500';
            $bgColor = 'bg-blue-100';
            $textColor = 'text-blue-600';
        }
    @endphp
    <div class="bg-white rounded-xl shadow-md px-4 py-2.5">
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-semibold {{ $textColor }}">{{ $periodPercent }}%</span>
            <span class="text-xs font-medium text-gray-500">{{ $daysLeft }} days left</span>
        </div>
        <div class="w-full {{ $bgColor }} rounded-full h-1.5 overflow-hidden">
            <div class="bg-gradient-to-r {{ $barColor }} h-full rounded-full transition-all duration-500" style="width: {{ $periodPercent }}%"></div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Tab Navigation --}}
    <div class="bg-white rounded-xl shadow-md p-1 overflow-x-auto">
        <nav class="flex gap-1" aria-label="Tabs">
            <template x-for="t in tabs" :key="t.key">
                <template x-if="t.href">
                    <a :href="t.href"
                        class="whitespace-nowrap px-4 py-2.5 text-sm font-medium transition rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100"
                        x-text="t.label"></a>
                </template>
                <template x-if="!t.href">
                    <button @click="tab = t.key"
                        :class="tab === t.key
                            ? 'bg-blue-600 text-white shadow-sm'
                            : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'"
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
    <div x-show="tab === 'overview'" x-cloak>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Income</p>
                        <p class="text-2xl font-bold text-green-600 mt-1">${{ number_format($income['total_income'], 2) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">{{ $income['payment_count'] }} payment{{ $income['payment_count'] !== 1 ? 's' : '' }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Business Expenses</p>
                        <p class="text-2xl font-bold text-red-600 mt-1">${{ number_format($expenses['total_expenses'], 2) }}</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">{{ $expenses['expense_count'] }} transaction{{ $expenses['expense_count'] !== 1 ? 's' : '' }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 {{ $summary['net_profit'] >= 0 ? 'border-blue-500' : 'border-orange-500' }}">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Net Profit</p>
                        <p class="text-2xl font-bold {{ $summary['net_profit'] >= 0 ? 'text-blue-600' : 'text-orange-600' }} mt-1">
                            {{ $summary['net_profit'] >= 0 ? '+' : '' }}${{ number_format($summary['net_profit'], 2) }}
                        </p>
                    </div>
                    <div class="w-12 h-12 {{ $summary['net_profit'] >= 0 ? 'bg-blue-100' : 'bg-orange-100' }} rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 {{ $summary['net_profit'] >= 0 ? 'text-blue-600' : 'text-orange-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">{{ $summary['profit_margin'] }}% margin</p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Occupancy</p>
                        <p class="text-2xl font-bold text-purple-600 mt-1">{{ $occupiedCount }}/{{ $totalApartments }}</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">{{ $occupancyRate }}% occupied</p>
            </div>
        </div>

        {{-- Collection Progress --}}
        @if($expectedMonthlyRent > 0)
        <div class="bg-white rounded-xl shadow-md p-5 mb-6">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-medium text-gray-700">Monthly Rent Collection</p>
                <p class="text-sm font-semibold text-gray-900">
                    ${{ number_format($income['rent_income'], 2) }}
                    <span class="text-gray-400 font-normal">/ ${{ number_format($expectedMonthlyRent, 2) }}</span>
                </p>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2">
                <div class="bg-blue-500 h-2 rounded-full transition-all" style="width: {{ min(($income['rent_income'] / $expectedMonthlyRent) * 100, 100) }}%"></div>
            </div>
        </div>
        @endif

        {{-- Income & Expense Breakdown --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-5 py-4 border-b bg-gray-50"><h2 class="text-sm font-semibold text-gray-900">Income Breakdown <span class="text-xs font-normal text-gray-400">(Rent + Tenant Charges)</span></h2></div>
                <div class="divide-y">
                    <div class="flex justify-between px-5 py-2.5"><span class="text-sm text-gray-600">🏠 Rent</span><span class="text-sm font-semibold">${{ number_format($income['rent_income'], 2) }}</span></div>
                    @if(($income['late_fees'] ?? 0) > 0)
                    <div class="flex justify-between px-5 py-2.5"><span class="text-sm text-gray-600">⏰ Late Fees</span><span class="text-sm font-semibold">${{ number_format($income['late_fees'], 2) }}</span></div>
                    @endif
                    @if(($income['total_utility_income'] ?? 0) > 0)
                    <div class="flex justify-between px-5 py-2.5"><span class="text-sm text-gray-600">🔌 Utility Charges</span><span class="text-sm font-semibold">${{ number_format($income['total_utility_income'], 2) }}</span></div>
                    @endif
                    @if(($income['deposit_income'] ?? 0) > 0)
                    <div class="flex justify-between px-5 py-2.5"><span class="text-sm text-gray-600">🔑 Deposits</span><span class="text-sm font-semibold">${{ number_format($income['deposit_income'], 2) }}</span></div>
                    @endif
                    @if(($income['other_income'] ?? 0) > 0)
                    <div class="flex justify-between px-5 py-2.5"><span class="text-sm text-gray-600">📋 Other</span><span class="text-sm font-semibold">${{ number_format($income['other_income'], 2) }}</span></div>
                    @endif
                    <div class="flex justify-between px-5 py-2.5 bg-gray-50"><span class="text-sm font-semibold">Total Income</span><span class="text-sm font-bold text-green-600">${{ number_format($income['total_income'], 2) }}</span></div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="px-5 py-4 border-b bg-gray-50"><h2 class="text-sm font-semibold text-gray-900">Expense Breakdown <span class="text-xs font-normal text-gray-400">(All Expenses)</span></h2></div>
                <div class="divide-y">
                    @if(($expenses['fixed_expenses'] ?? 0) > 0)
                    <div class="flex justify-between px-5 py-2.5"><span class="text-sm text-gray-600">📌 Business Fixed</span><span class="text-sm font-semibold">${{ number_format($expenses['fixed_expenses'], 2) }}</span></div>
                    @endif
                    @if(($expenses['variable_expenses'] ?? 0) > 0)
                    <div class="flex justify-between px-5 py-2.5"><span class="text-sm text-gray-600">📊 Business Variable</span><span class="text-sm font-semibold">${{ number_format($expenses['variable_expenses'], 2) }}</span></div>
                    @endif
                    @if(($expenses['utility_expenses'] ?? 0) > 0)
                    <div class="flex justify-between px-5 py-2.5"><span class="text-sm text-gray-600">🔌 Utility Expenses</span><span class="text-sm font-semibold">${{ number_format($expenses['utility_expenses'], 2) }}</span></div>
                    @endif
                    @if(($expenses['other_expenses'] ?? 0) > 0)
                    <div class="flex justify-between px-5 py-2.5"><span class="text-sm text-gray-600">📋 Other Expenses</span><span class="text-sm font-semibold">${{ number_format($expenses['other_expenses'], 2) }}</span></div>
                    @endif
                    @if(!empty($expenses['by_category']))
                    <div class="px-5 py-2.5">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1.5">By Category</p>
                        @php
                            $categoryLabels = [
                                'business_fixed' => 'Business Fixed',
                                'business_variable' => 'Business Variable',
                                'utilities_expense' => 'Utility Costs',
                                'building_maintenance' => 'Building Maintenance',
                                'insurance' => 'Insurance',
                                'property_tax' => 'Property Tax',
                                'mortgage' => 'Mortgage / Loan',
                                'management_fee' => 'Management Fee',
                                'security' => 'Security',
                                'cleaning' => 'Cleaning',
                                'landscaping' => 'Landscaping',
                                'elevator' => 'Elevator',
                                'pest_control' => 'Pest Control',
                                'accounting' => 'Accounting',
                                'legal' => 'Legal Fees',
                                'marketing' => 'Marketing',
                                'supplies' => 'Supplies',
                                'license' => 'License & Permits',
                                'depreciation' => 'Depreciation',
                                'maintenance' => 'Maintenance & Repairs',
                                'miscellaneous' => 'Miscellaneous',
                                'other' => 'Other',
                            ];
                        @endphp
                        @foreach($expenses['by_category'] as $cat => $amount)
                        <div class="flex justify-between text-xs py-0.5">
                            <span class="text-gray-600">{{ $categoryLabels[$cat] ?? ucfirst(str_replace('_', ' ', $cat)) }}</span>
                            <span class="font-medium text-red-600">${{ number_format($amount, 2) }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                    <div class="flex justify-between px-5 py-2.5 bg-gray-50"><span class="text-sm font-semibold">Total Expenses</span><span class="text-sm font-bold text-red-600">${{ number_format($expenses['total_expenses'], 2) }}</span></div>
                </div>
            </div>
        </div>

        {{-- Per-Apartment Table --}}
        @if(isset($perApartment) && count($perApartment) > 0)
        <div class="bg-white rounded-xl shadow-md overflow-hidden" x-data="{ showAll: false, expenseForm: null }">
            <div class="px-5 py-3 border-b flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900">Per-Apartment Summary</h2>
                <button @click="showAll = !showAll" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                    <span x-text="showAll ? 'Occupied Only' : 'Show All'"></span>
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50 text-xs text-gray-500 uppercase">
                            <th class="text-left px-4 py-2 font-medium">Unit</th>
                            <th class="text-left px-4 py-2 font-medium">Tenant</th>
                            <th class="text-right px-4 py-2 font-medium">Rent</th>
                            <th class="text-left px-4 py-2 font-medium">This Month</th>
                            <th class="text-right px-4 py-2 font-medium">Income</th>
                            <th class="text-right px-4 py-2 font-medium">Utilities</th>
                            <th class="text-right px-4 py-2 font-medium" title="Income + Utilities = Total tenant pays to owner">Net<br><span class="text-[9px] normal-case text-gray-400">(Tenant → Owner)</span></th>
                            <th class="text-center px-4 py-2 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($perApartment as $aptIdx => $apt)
                        <tr class="{{ !$apt['has_active_rental'] ? 'text-gray-300' : 'text-gray-700' }}" x-show="showAll || {{ $apt['has_active_rental'] ? 'true' : 'false' }}">
                            <td class="px-4 py-2 font-medium {{ $apt['has_active_rental'] ? 'text-gray-900' : '' }}">{{ $apt['apartment_number'] }}</td>
                            <td class="px-4 py-2">{{ $apt['has_active_rental'] ? $apt['tenant'] : 'Vacant' }}</td>
                            <td class="px-4 py-2 text-right">${{ number_format($apt['monthly_rent'], 2) }}</td>
                            <td class="px-4 py-2">
                                @if($apt['rent_status'] !== 'none')
                                <div class="w-28">
                                    <div class="flex items-center justify-between mb-0.5">
                                        <span class="text-[10px] font-bold {{ $apt['rent_status'] === 'paid' ? 'text-green-600' : ($apt['rent_status'] === 'partial' ? 'text-yellow-600' : 'text-red-500') }}">{{ $apt['rent_percent'] }}%</span>
                                        <span class="text-[10px] text-gray-400">${{ number_format($apt['rent_paid'], 0) }}/${{ number_format($apt['rent_due'], 0) }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        @php
                                            $barColor = match($apt['rent_status']) {
                                                'paid' => 'bg-green-500',
                                                'partial' => 'bg-yellow-500',
                                                default => 'bg-red-300',
                                            };
                                        @endphp
                                        <div class="{{ $barColor }} h-1.5 rounded-full" style="width: {{ $apt['rent_percent'] }}%"></div>
                                    </div>
                                </div>
                                @else
                                <span class="text-[10px] text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right {{ $apt['income'] > 0 ? 'text-green-600 font-medium' : '' }}">${{ number_format($apt['income'], 2) }}</td>
                            <td class="px-4 py-2 text-right {{ $apt['expenses'] > 0 ? 'text-blue-600 font-medium' : '' }}">
                                @if($apt['expenses'] > 0 && isset($apt['expense_breakdown']))
                                <div x-data="{ showBreakdown: false }" class="relative inline-block">
                                    <button type="button" @click="showBreakdown = !showBreakdown" class="underline decoration-dotted cursor-pointer hover:text-blue-800">
                                        ${{ number_format($apt['expenses'], 2) }}
                                    </button>
                                    <div x-show="showBreakdown" x-cloak @click.away="showBreakdown = false"
                                        class="absolute right-0 top-full mt-1 z-20 bg-white border border-gray-200 rounded-lg shadow-lg p-3 w-48 text-left">
                                        <p class="text-[10px] font-semibold text-gray-500 uppercase mb-1.5">Expense Breakdown</p>
                                        @foreach($apt['expense_breakdown'] as $type => $amount)
                                            @if($amount > 0)
                                            <div class="flex justify-between text-xs py-0.5">
                                                <span class="text-gray-600">
                                                    @switch($type)
                                                        @case('electricity') ⚡ Electricity @break
                                                        @case('water') 💧 Water @break
                                                        @case('internet') 📡 Internet @break
                                                        @case('parking') 🚗 Parking @break
                                                        @default {{ ucfirst($type) }}
                                                    @endswitch
                                                </span>
                                                <span class="font-medium text-blue-600">${{ number_format($amount, 2) }}</span>
                                            </div>
                                            @endif
                                        @endforeach
                                        <div class="border-t mt-1 pt-1 flex justify-between text-xs font-semibold">
                                            <span>Total</span>
                                            <span class="text-blue-700">${{ number_format($apt['expenses'], 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                                @else
                                ${{ number_format($apt['expenses'], 2) }}
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right font-semibold {{ ($apt['tenant_net'] ?? ($apt['income'] + $apt['expenses'])) >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                @php $tenantNet = $apt['tenant_net'] ?? ($apt['income'] + $apt['expenses']); @endphp
                                ${{ number_format($tenantNet, 2) }}
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if($apt['has_active_rental'] && $apt['rental_id'])
                                <div class="flex items-center justify-center gap-1">
                                    <button @click="expenseForm = expenseForm === {{ $aptIdx }} ? null : {{ $aptIdx }}" title="Assign Expense"
                                        class="p-1 rounded transition"
                                        :class="expenseForm === {{ $aptIdx }} ? 'bg-red-100 text-red-600 hover:bg-red-200' : 'bg-orange-100 text-orange-600 hover:bg-orange-200'">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    </button>
                                    @if($apt['tenant_id'])
                                    <a href="{{ route('admin.tenants.show', $apt['tenant_id']) }}" title="View Tenant"
                                        class="p-1 rounded bg-blue-100 text-blue-600 hover:bg-blue-200 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    </a>
                                    @endif
                                    <a href="{{ route('admin.apartments.show', $apt['apartment_id']) }}" title="View Apartment"
                                        class="p-1 rounded bg-green-100 text-green-600 hover:bg-green-200 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4"/></svg>
                                    </a>
                                </div>
                                @else
                                <span class="text-gray-300 text-xs">—</span>
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
                                        <p class="font-semibold text-gray-700 mb-1">Assign Expense — {{ $apt['apartment_number'] }} <span class="text-gray-400 font-normal">({{ $apt['tenant'] }})</span></p>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Type</label>
                                        <select name="utility_type" required class="px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-orange-500 focus:border-orange-500 w-28">
                                            <option value="electricity">⚡ Electricity</option>
                                            <option value="water">💧 Water</option>
                                            <option value="internet">📡 Internet</option>
                                            <option value="parking">🚗 Parking</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Amount ($)</label>
                                        <input type="number" name="charge_amount" step="0.01" min="0.01" required
                                            class="px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-orange-500 focus:border-orange-500 w-24">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Date</label>
                                        <input type="date" name="transaction_date" value="{{ date('Y-m-d') }}" required
                                            class="px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-orange-500 focus:border-orange-500 w-32">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Meter In</label>
                                        <input type="number" name="meter_reading_in" step="0.01" min="0" placeholder="0"
                                            class="px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-orange-500 focus:border-orange-500 w-20">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Meter Out</label>
                                        <input type="number" name="meter_reading_out" step="0.01" min="0" placeholder="0"
                                            class="px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-orange-500 focus:border-orange-500 w-20">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-medium text-gray-500 mb-0.5">Note</label>
                                        <input type="text" name="note" placeholder="Optional" maxlength="1000"
                                            class="px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-orange-500 focus:border-orange-500 w-32">
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
                        <tr class="border-t-2 bg-gray-50 font-semibold text-gray-900">
                            <td class="px-4 py-2" colspan="4">Total</td>
                            <td class="px-4 py-2 text-right text-green-600">${{ number_format(collect($perApartment)->sum('income'), 2) }}</td>
                            <td class="px-4 py-2 text-right text-blue-600">${{ number_format(collect($perApartment)->sum('expenses'), 2) }}</td>
                            @php
                                $totalTenantNet = collect($perApartment)->sum(fn($a) => $a['tenant_net'] ?? ($a['income'] + $a['expenses']));
                            @endphp
                            <td class="px-4 py-2 text-right text-green-700">
                                ${{ number_format($totalTenantNet, 2) }}
                            </td>
                            <td class="px-4 py-2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- ================================================== --}}
    {{-- TAB 2: RECORD INCOME --}}
    {{-- ================================================== --}}
    <div x-show="tab === 'income'" x-cloak>

        {{-- Income Summary Row --}}
        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="bg-white rounded-lg border p-4 border-l-4 border-l-blue-500">
                <p class="text-xs text-gray-500 font-medium">Expected Rent</p>
                <p class="text-xl font-bold text-blue-600 mt-1">${{ number_format($totalRentExpected, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg border p-4 border-l-4 border-l-green-500">
                <p class="text-xs text-gray-500 font-medium">Collected</p>
                <p class="text-xl font-bold text-green-600 mt-1">${{ number_format($totalRentCollected, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg border p-4 border-l-4 {{ $totalRentCollected >= $totalRentExpected ? 'border-l-green-500' : 'border-l-orange-500' }}">
                <p class="text-xs text-gray-500 font-medium">Collection Rate</p>
                <p class="text-xl font-bold {{ $totalRentCollected >= $totalRentExpected ? 'text-green-600' : 'text-orange-600' }} mt-1">
                    {{ $totalRentExpected > 0 ? round(($totalRentCollected / $totalRentExpected) * 100, 1) : 0 }}%
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">

                {{-- Bulk Monthly Rent --}}
                <div class="bg-white rounded-lg border p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-1 pb-2 border-b">Auto Generate Monthly Rent</h2>
                    <p class="text-xs text-gray-500 mb-3">Select apartments and record rent for all at once.</p>

                    @if($apartmentSummary && count($apartmentSummary) > 0)
                    <form action="{{ route('admin.revenue_expense.store_income_bulk') }}" method="POST" id="bulkRentForm">
                        @csrf
                        <div class="grid grid-cols-2 gap-3 mb-4 p-3 bg-blue-50 rounded-lg">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Payment Date *</label>
                                <input type="date" name="payment_date" required value="{{ date('Y-m-d') }}" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Payment Method *</label>
                                <select name="payment_method" required class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank Transfer</option>
                                </select>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm" id="bulkRentTable">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-2 py-2 text-center w-8"><input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 rounded cursor-pointer" title="Select All"></th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Apartment</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Rent ($)</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Late Fee</th>
                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    @foreach($apartmentSummary as $index => $s)
                                    <tr class="{{ $s['paid_this_month'] ? 'bg-green-50/50' : '' }} hover:bg-gray-50">
                                        <td class="px-2 py-2 text-center">
                                            <input type="hidden" name="apartments[{{ $index }}][rental_id]" value="{{ $s['rental']->id }}">
                                            <input type="checkbox" name="apartments[{{ $index }}][selected]" value="1"
                                                class="apt-checkbox w-4 h-4 text-blue-600 rounded cursor-pointer"
                                                {{ $s['paid_this_month'] ? '' : 'checked' }}>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="font-medium text-gray-900">{{ $s['apartment']->apartment_number }}</span>
                                            <span class="text-xs text-gray-400 ml-1">F{{ $s['apartment']->floor->floor_number ?? '?' }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-600">{{ $s['rental']->tenant->name ?? 'N/A' }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" name="apartments[{{ $index }}][amount]" step="0.01" min="0.01"
                                                value="{{ $s['monthly_rent'] }}" class="w-24 px-2 py-1 text-right text-sm border rounded focus:ring-blue-500 focus:border-blue-500 font-semibold text-blue-600">
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <input type="number" name="apartments[{{ $index }}][late_fee]" step="0.01" min="0" value="0"
                                                class="w-20 px-2 py-1 text-right text-sm border rounded focus:ring-blue-500 focus:border-blue-500">
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($s['paid_this_month'])
                                            <div>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Paid</span>
                                                <div class="w-full bg-gray-200 rounded-full h-1 mt-1">
                                                    <div class="bg-green-500 h-1 rounded-full" style="width: 100%"></div>
                                                </div>
                                            </div>
                                            @else
                                            <div>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                                                <div class="w-full bg-gray-200 rounded-full h-1 mt-1">
                                                    @php
                                                        $bulkPercent = $s['monthly_rent'] > 0 ? min(round(($s['collected'] / $s['monthly_rent']) * 100, 1), 100) : 0;
                                                    @endphp
                                                    <div class="{{ $bulkPercent > 0 ? 'bg-yellow-500' : 'bg-red-300' }} h-1 rounded-full" style="width: {{ max($bulkPercent, 2) }}%"></div>
                                                </div>
                                            </div>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td class="px-2 py-2"></td>
                                        <td class="px-3 py-2 font-semibold text-gray-900" colspan="2">Total Selected</td>
                                        <td class="px-3 py-2 text-right font-bold text-blue-600" id="totalSelectedAmount">${{ number_format($totalRentExpected, 2) }}</td>
                                        <td class="px-3 py-2 text-right font-bold text-yellow-600" id="totalSelectedLateFee">$0.00</td>
                                        <td class="px-3 py-2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition font-medium">
                                Record Selected Rent
                            </button>
                            <span class="text-xs text-gray-500" id="selectedCount">{{ count($apartmentSummary) }} selected</span>
                        </div>
                    </form>
                    @else
                    <p class="text-center py-6 text-gray-400 text-sm">No apartments with active rentals.</p>
                    @endif
                </div>

                {{-- Record Other Income --}}
                <div class="bg-white rounded-lg border p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-3 pb-2 border-b">Record Other Income</h2>

                    <form action="{{ route('admin.revenue_expense.store_income') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Apartment *</label>
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
                                <label class="block text-xs font-medium text-gray-700 mb-1">Type *</label>
                                <select name="payment_type" id="payment_type" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                                    <option value="rent">Rent</option>
                                    <option value="utilities">Utilities</option>
                                    <option value="deposit">Deposit</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Amount ($) *</label>
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01" required value="{{ old('amount') }}" placeholder="0.00"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Method *</label>
                                <select name="payment_method" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank Transfer</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Date *</label>
                                <input type="date" name="transaction_date" required value="{{ old('transaction_date', date('Y-m-d')) }}" class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Late Fee ($)</label>
                                <input type="number" name="late_fee" step="0.01" min="0" value="{{ old('late_fee', '0') }}" class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Reference</label>
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

            {{-- Recent Income Sidebar --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg border p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3 pb-2 border-b">Recent Income</h3>
                    @if($recentIncome->isEmpty())
                    <p class="text-center py-4 text-gray-400 text-sm">No income recorded yet</p>
                    @else
                    <div class="space-y-2">
                        @foreach($recentIncome as $record)
                        <div class="p-2.5 bg-green-50 rounded-lg border border-green-100">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-medium text-gray-800">{{ ucfirst(str_replace('_', ' ', $record->category)) }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $record->description }}</p>
                                    <p class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($record->transaction_date)->format('M d, Y') }}</p>
                                </div>
                                <p class="text-sm font-bold text-green-600">${{ number_format($record->amount, 2) }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================== --}}
    {{-- TAB 3: RECORD EXPENSE --}}
    {{-- ================================================== --}}
    <div x-show="tab === 'expense'" x-cloak>

        {{-- Expense Summary --}}
        <div class="bg-white rounded-lg border p-4 border-l-4 border-l-red-500 mb-4">
            <p class="text-xs text-gray-500 font-medium">Total Utility Expenses (This Period)</p>
            <p class="text-2xl font-bold text-red-600 mt-1">${{ number_format($totalExpensesAmount, 2) }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">

                {{-- Expenses per Apartment Table --}}
                @if(count($apartmentExpenses) > 0)
                <div class="bg-white rounded-lg border p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-3 pb-2 border-b">Expenses per Apartment</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Apartment</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">⚡</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">💧</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">📡</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">🚗</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach($apartmentExpenses as $aptExp)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2">
                                        <span class="font-medium text-gray-900">{{ $aptExp['apartment']->apartment_number }}</span>
                                        <span class="text-xs text-gray-400 ml-1">F{{ $aptExp['apartment']->floor->floor_number ?? '?' }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span class="px-1.5 py-0.5 rounded-full text-xs font-medium {{ $aptExp['has_active_rental'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                            {{ $aptExp['has_active_rental'] ? 'Occupied' : 'Vacant' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right {{ $aptExp['electricity'] > 0 ? 'font-semibold text-yellow-600' : 'text-gray-300' }}">${{ number_format($aptExp['electricity'], 2) }}</td>
                                    <td class="px-3 py-2 text-right {{ $aptExp['water'] > 0 ? 'font-semibold text-blue-600' : 'text-gray-300' }}">${{ number_format($aptExp['water'], 2) }}</td>
                                    <td class="px-3 py-2 text-right {{ $aptExp['internet'] > 0 ? 'font-semibold text-purple-600' : 'text-gray-300' }}">${{ number_format($aptExp['internet'], 2) }}</td>
                                    <td class="px-3 py-2 text-right {{ $aptExp['parking'] > 0 ? 'font-semibold text-orange-600' : 'text-gray-300' }}">${{ number_format($aptExp['parking'], 2) }}</td>
                                    <td class="px-3 py-2 text-right font-bold text-red-600">${{ number_format($aptExp['total'], 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr class="font-semibold">
                                    <td class="px-3 py-2 text-gray-900" colspan="2">Grand Total</td>
                                    <td class="px-3 py-2 text-right text-yellow-600">${{ number_format(collect($apartmentExpenses)->sum('electricity'), 2) }}</td>
                                    <td class="px-3 py-2 text-right text-blue-600">${{ number_format(collect($apartmentExpenses)->sum('water'), 2) }}</td>
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
                <div class="bg-white rounded-lg border p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-3 pb-2 border-b">Record Utility Expense</h2>

                    <form action="{{ route('admin.revenue_expense.store_expense') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Apartment *</label>
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
                                <label class="block text-xs font-medium text-gray-700 mb-1">Type *</label>
                                <select name="utility_type" id="utility_type" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                                    <option value="">-- Select --</option>
                                    @foreach($utilityTypes as $key => $label)
                                    <option value="{{ $key }}" {{ old('utility_type') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Amount ($) *</label>
                                <input type="number" name="charge_amount" step="0.01" min="0.01" required value="{{ old('charge_amount') }}" placeholder="0.00"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Date *</label>
                                <input type="date" name="transaction_date" required value="{{ old('transaction_date', date('Y-m-d')) }}"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Meter In</label>
                                <input type="number" name="meter_reading_in" step="0.01" min="0" value="{{ old('meter_reading_in') }}" placeholder="0"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Meter Out</label>
                                <input type="number" name="meter_reading_out" step="0.01" min="0" value="{{ old('meter_reading_out') }}" placeholder="0"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Note</label>
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

            {{-- Recent Expenses Sidebar --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg border p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3 pb-2 border-b">Recent Expenses</h3>
                    @if($recentExpenses->isEmpty())
                    <p class="text-center py-4 text-gray-400 text-sm">No expenses recorded yet</p>
                    @else
                    <div class="space-y-2">
                        @foreach($recentExpenses as $record)
                        <div class="p-2.5 bg-red-50 rounded-lg border border-red-100">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-xs font-medium text-gray-800">{{ ucfirst(str_replace('_', ' ', $record->category)) }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $record->description }}</p>
                                    <p class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($record->transaction_date)->format('M d, Y') }}</p>
                                </div>
                                <p class="text-sm font-bold text-red-600">${{ number_format($record->amount, 2) }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
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
                <div class="bg-white rounded-lg border p-4">
                    <div class="flex items-center justify-between mb-2 pb-2 border-b">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">
                                {{ $fa->apartment_number }}
                                <span class="text-xs font-normal text-gray-400 ml-1">F{{ $fa->floor->floor_number ?? '?' }}</span>
                            </h3>
                            @if($fa->rentals->isNotEmpty())
                            @php $r = $fa->rentals->first(); @endphp
                            <p class="text-xs text-gray-500">{{ $r->tenant->name ?? 'N/A' }} — ${{ number_format($r->rent_amount, 2) }}/mo</p>
                            @else
                            <p class="text-xs text-gray-400 italic">No active tenant</p>
                            @endif
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $fa->status === 'occupied' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ ucfirst($fa->status) }}
                        </span>
                    </div>

                    @if($fa->fixedExpenses->isNotEmpty())
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-2 py-1.5 text-left text-xs font-medium text-gray-500">Expense</th>
                                <th class="px-2 py-1.5 text-left text-xs font-medium text-gray-500">Type</th>
                                <th class="px-2 py-1.5 text-right text-xs font-medium text-gray-500">Amount</th>
                                <th class="px-2 py-1.5 text-center text-xs font-medium text-gray-500">Status</th>
                                <th class="px-2 py-1.5 text-center text-xs font-medium text-gray-500 w-16">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($fa->fixedExpenses as $expense)
                            <tr class="{{ $expense->is_active ? '' : 'opacity-50' }}">
                                <td class="px-2 py-1.5 font-medium text-gray-800">{{ $expense->expense_name }}</td>
                                <td class="px-2 py-1.5">
                                    @php $icons = ['parking'=>'🚗','internet'=>'📡','trash'=>'🗑️','other'=>'📋']; @endphp
                                    <span class="text-xs">{{ $icons[$expense->expense_type] ?? '📋' }} {{ ucfirst($expense->expense_type) }}</span>
                                </td>
                                <td class="px-2 py-1.5 text-right font-semibold text-red-600">${{ number_format($expense->amount, 2) }}</td>
                                <td class="px-2 py-1.5 text-center">
                                    <span class="px-1.5 py-0.5 rounded-full text-xs font-medium {{ $expense->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $expense->is_active ? 'Active' : 'Off' }}
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                    <div class="flex justify-center gap-1">
                                        <form action="{{ route('admin.revenue_expense.toggle_fixed_expense', $expense) }}" method="POST" class="inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="p-1 rounded hover:bg-gray-100" title="{{ $expense->is_active ? 'Disable' : 'Enable' }}">
                                                <svg class="w-3.5 h-3.5 {{ $expense->is_active ? 'text-yellow-600' : 'text-green-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="px-2 py-1.5 font-semibold text-gray-900" colspan="2">Monthly Total</td>
                                <td class="px-2 py-1.5 text-right font-bold text-red-600">${{ number_format($fa->fixedExpenses->where('is_active', true)->sum('amount'), 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                    @else
                    <p class="text-center py-2 text-gray-400 text-xs">No fixed expenses assigned</p>
                    @endif
                </div>
                @empty
                <div class="bg-white rounded-lg border p-8 text-center text-gray-400 text-sm">No apartments found.</div>
                @endforelse
            </div>

            {{-- Add Fixed Expense Form --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg border p-5 sticky top-8">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3 pb-2 border-b">Add Fixed Expense</h3>

                    <form action="{{ route('admin.revenue_expense.store_fixed_expense') }}" method="POST">
                        @csrf
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Apartment *</label>
                                <select name="apartment_id" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                                    <option value="">-- Select --</option>
                                    @foreach($fixedApartments as $fa)
                                    <option value="{{ $fa->id }}" {{ old('apartment_id') == $fa->id ? 'selected' : '' }}>{{ $fa->apartment_number }} (F{{ $fa->floor->floor_number ?? '?' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Type *</label>
                                <select name="expense_type" id="expense_type" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                                    <option value="">-- Select --</option>
                                    <option value="parking" {{ old('expense_type') == 'parking' ? 'selected' : '' }}>🚗 Parking</option>
                                    <option value="internet" {{ old('expense_type') == 'internet' ? 'selected' : '' }}>📡 Internet</option>
                                    <option value="trash" {{ old('expense_type') == 'trash' ? 'selected' : '' }}>🗑️ Trash</option>
                                    <option value="other" {{ old('expense_type') == 'other' ? 'selected' : '' }}>📋 Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Name *</label>
                                <input type="text" name="expense_name" id="expense_name" required value="{{ old('expense_name') }}" placeholder="e.g. Parking A1"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Monthly Amount ($) *</label>
                                <input type="number" name="amount" step="0.01" min="0.01" required value="{{ old('amount') }}" placeholder="0.00"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Note</label>
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

            <div class="bg-white rounded-lg border p-4 mb-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Billing Date</label>
                        <input type="date" name="billing_date" required value="{{ date('Y-m-d') }}" class="px-3 py-1.5 text-sm border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-500">Total Monthly Fixed</p>
                        <p class="text-xl font-bold text-red-600">${{ number_format($totalMonthlyExpenses, 2) }}</p>
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="selectAllBills" class="w-4 h-4 text-blue-600 rounded" checked>
                    <span class="text-xs font-medium text-gray-700">Select All</span>
                </label>
            </div>

            <div class="space-y-3 mb-4">
                @foreach($billSummary as $bi => $bill)
                <div class="bg-white rounded-lg border overflow-hidden {{ $bill['has_unbilled'] ? '' : 'opacity-60' }}">
                    <div class="p-3 border-b bg-gray-50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="bills[{{ $bi }}][rental_id]" value="{{ $bill['rental']->id }}">
                            <input type="checkbox" name="bills[{{ $bi }}][selected]" value="1"
                                class="bill-checkbox w-4 h-4 text-blue-600 rounded cursor-pointer"
                                {{ $bill['has_unbilled'] ? 'checked' : '' }}>
                            <div>
                                <span class="text-sm font-bold text-gray-900">{{ $bill['apartment']->apartment_number }}</span>
                                <span class="text-xs text-gray-400 ml-1">F{{ $bill['apartment']->floor->floor_number ?? '?' }}</span>
                                <span class="text-xs text-gray-500 ml-2">{{ $bill['tenant_name'] }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-red-600">${{ number_format($bill['total_bill'], 2) }}</p>
                            <p class="text-xs text-gray-400">Rent ${{ number_format($bill['monthly_rent'], 2) }} + Fixed ${{ number_format($bill['total_fixed'], 2) }}</p>
                        </div>
                    </div>

                    @if(count($bill['fixed_expenses']) > 0)
                    <div class="p-3">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($bill['fixed_expenses'] as $ei => $exp)
                            <div class="border rounded p-2 {{ $exp['is_billed'] ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }}">
                                <input type="hidden" name="bills[{{ $bi }}][expenses][{{ $ei }}][expense_id]" value="{{ $exp['id'] }}">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-1">
                                        <input type="checkbox" name="bills[{{ $bi }}][expenses][{{ $ei }}][selected]" value="1"
                                            class="w-3.5 h-3.5 text-blue-600 rounded cursor-pointer"
                                            {{ $exp['is_billed'] ? 'disabled' : 'checked' }}>
                                        @php $icons = ['parking'=>'🚗','internet'=>'📡','trash'=>'🗑️','other'=>'📋']; @endphp
                                        <span class="text-xs font-medium">{{ $icons[$exp['type']] ?? '📋' }} {{ $exp['name'] }}</span>
                                    </div>
                                    @if($exp['is_billed'])
                                    <span class="text-xs text-green-700 font-medium">✓</span>
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
                    <p class="p-3 text-xs text-gray-400 text-center">No fixed expenses assigned</p>
                    @endif
                </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between bg-white rounded-lg border p-4">
                <p class="text-xs text-gray-500">Only unbilled expenses will be generated</p>
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition font-medium">
                    Generate Monthly Expenses
                </button>
            </div>
        </form>
        @else
        <div class="bg-white rounded-lg border p-8 text-center">
            <p class="text-gray-500 text-sm mb-2">No active rentals with fixed expenses found.</p>
            <button @click="tab = 'fixed'" class="text-blue-600 text-sm hover:underline">Set up fixed expenses first</button>
        </div>
        @endif
    </div>

    {{-- ================================================== --}}
    {{-- TAB 6: BREAK-EVEN --}}
    {{-- ================================================== --}}
    <div x-show="tab === 'breakeven'" x-cloak>

        {{-- Key Metrics --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="bg-white rounded-lg border p-4 border-l-4 border-l-blue-500">
                <p class="text-xs text-gray-500 font-medium">Total Apartments</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ $total_apartments }}</p>
            </div>
            <div class="bg-white rounded-lg border p-4 border-l-4 border-l-green-500">
                <p class="text-xs text-gray-500 font-medium">Avg. Rent/Unit</p>
                <p class="text-2xl font-bold text-green-600 mt-1">${{ number_format($avg_rent_per_apartment, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg border p-4 border-l-4 border-l-purple-500">
                <p class="text-xs text-gray-500 font-medium">Occupancy</p>
                <p class="text-2xl font-bold text-purple-600 mt-1">{{ $current_occupancy }}/{{ $total_apartments }}</p>
            </div>
            <div class="bg-white rounded-lg border p-4 border-l-4 {{ $is_above_break_even ? 'border-l-green-500' : 'border-l-red-500' }}">
                <p class="text-xs text-gray-500 font-medium">Status</p>
                <p class="text-lg font-bold mt-1 {{ $is_above_break_even ? 'text-green-600' : 'text-red-600' }}">
                    {{ $is_above_break_even ? '✓ ABOVE' : '✗ BELOW' }} Break-Even
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Break-Even Calculation --}}
            <div class="bg-white rounded-lg border p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-4 pb-2 border-b">Break-Even Calculation</h2>
                <div class="space-y-3">
                    <div class="bg-gray-50 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-gray-700">Fixed Costs (Monthly)</p><p class="text-xs text-gray-500">Internet, maintenance, insurance</p></div>
                        <span class="text-lg font-bold text-gray-900">${{ number_format($fixed_costs, 2) }}</span>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-gray-700">Variable Cost/Unit</p><p class="text-xs text-gray-500">Electricity, water, parking</p></div>
                        <span class="text-lg font-bold text-gray-900">${{ number_format($variable_cost_per_unit, 2) }}</span>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-gray-700">Contribution Margin/Unit</p><p class="text-xs text-gray-500">${{ number_format($avg_rent_per_apartment, 2) }} - ${{ number_format($variable_cost_per_unit, 2) }}</p></div>
                        <span class="text-lg font-bold text-blue-600">${{ number_format($contribution_margin_per_unit, 2) }}</span>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 p-3 rounded-lg flex justify-between items-center">
                        <p class="text-sm font-medium text-gray-700">Break-Even Units</p>
                        <span class="text-lg font-bold text-yellow-700">{{ $break_even_units }} units</span>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 p-3 rounded-lg flex justify-between items-center">
                        <p class="text-sm font-medium text-gray-700">Break-Even Revenue</p>
                        <span class="text-lg font-bold text-yellow-700">${{ number_format($break_even_revenue, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Current Performance --}}
            <div class="bg-white rounded-lg border p-5">
                <h2 class="text-sm font-semibold text-gray-900 mb-4 pb-2 border-b">Current Performance</h2>
                <div class="space-y-3">
                    <div class="bg-green-50 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-gray-700">Current Monthly Revenue</p><p class="text-xs text-gray-500">{{ $current_occupancy }} units × ${{ number_format($avg_rent_per_apartment, 2) }}</p></div>
                        <span class="text-lg font-bold text-green-600">${{ number_format($current_revenue, 2) }}</span>
                    </div>
                    <div class="bg-green-50 border border-green-200 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-gray-700">Safety Margin ($)</p><p class="text-xs text-gray-500">Cushion above break-even</p></div>
                        <span class="text-lg font-bold text-green-600">${{ number_format($safety_margin, 2) }}</span>
                    </div>
                    <div class="bg-green-50 border border-green-200 p-3 rounded-lg flex justify-between items-center">
                        <p class="text-sm font-medium text-gray-700">Safety Margin (%)</p>
                        <span class="text-lg font-bold text-green-600">{{ $safety_margin_percent }}%</span>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 p-3 rounded-lg">
                        <div class="flex justify-between items-center mb-2">
                            <p class="text-sm font-medium text-gray-700">Occupancy Rate</p>
                            <span class="text-lg font-bold text-blue-600">{{ $total_apartments > 0 ? round(($current_occupancy / $total_apartments) * 100, 1) : 0 }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $total_apartments > 0 ? (($current_occupancy / $total_apartments) * 100) : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Revenue vs Break-Even Comparison --}}
        <div class="bg-white rounded-lg border p-5 mt-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-4 pb-2 border-b">Revenue vs. Break-Even</h2>
            @php
                $maxVal = max($current_revenue, $break_even_revenue, 1);
            @endphp
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">Break-Even Required</span>
                        <span class="font-bold">${{ number_format($break_even_revenue, 2) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded h-6">
                        <div class="bg-yellow-500 h-full rounded flex items-center pl-2 text-white text-xs font-bold" style="width: {{ ($break_even_revenue / $maxVal) * 100 }}%">
                            ${{ number_format($break_even_revenue, 2) }}
                        </div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">Current Revenue</span>
                        <span class="font-bold">${{ number_format($current_revenue, 2) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded h-6">
                        <div class="bg-green-500 h-full rounded flex items-center pl-2 text-white text-xs font-bold" style="width: {{ ($current_revenue / $maxVal) * 100 }}%">
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
        tabs: [
            { key: 'overview', label: 'Overview' },
            { key: 'income', label: 'Income' },
            { key: 'expense', label: 'Expenses' },
            { key: 'fixed', label: 'Fixed Costs' },
            { key: 'bills', label: 'Bills' },
            { key: 'breakeven', label: 'Break-Even' },
            { key: 'calendar', label: '📅 Calendar', href: '{{ route("admin.revenue_expense.monthly_calendar") }}' },
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
</script>

@push('styles')
<style>
    [x-cloak] { display: none !important; }
    @media print { nav[aria-label="Tabs"], button[onclick], form select[onchange] { display: none !important; } }
</style>
@endpush
@endsection
