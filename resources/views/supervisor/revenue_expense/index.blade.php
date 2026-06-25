@extends('layouts.supervisor')

@section('content')
<style>[x-cloak] { display: none !important; }</style>
<div class="max-w-6xl mx-auto space-y-8" x-data="revenueExpense()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.revenue_expense') }}</h1>
        </div>
        <div class="flex items-center gap-2">
            @if($fiscalPeriods->count() > 1)
            <form method="GET" action="{{ route('supervisor.revenue_expense.index') }}">
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
            <a href="{{ route('supervisor.revenue_expense.index', ['period' => $activePeriod->id, 'month' => $prevMonth['month'], 'year' => $prevMonth['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ __('messages.previous_month') }}">
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
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">{{ __('messages.current') }}</span>
                    @elseif($selectedMonth->isFuture())
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">{{ __('messages.upcoming') }}</span>
                    @else
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">{{ __('messages.past') }}</span>
                    @endif
                @else
                    <span class="text-lg font-bold text-slate-800">{{ __('messages.all_months') }}</span>
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">{{ __('messages.full_period') }}</span>
                @endif
            </div>

            {{-- Next Month --}}
            @if($nextMonth)
            <a href="{{ route('supervisor.revenue_expense.index', ['period' => $activePeriod->id, 'month' => $nextMonth['month'], 'year' => $nextMonth['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ __('messages.next_month') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
            @endif

            {{-- Quick Actions --}}
            @if($isFilterActive)
            <a href="{{ route('supervisor.revenue_expense.index', ['period' => $activePeriod->id]) }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-slate-600 bg-slate-50 rounded-lg hover:bg-slate-100 transition" title="{{ __('messages.view_all_months') }}">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg></a>
            @endif

            @if(!$isCurrentMonth || !$isFilterActive)
            @php
                $nowMonth = now()->month;
                $nowYear = now()->year;
                $currentInPeriod = collect($periodMonths)->first(fn($pm) => $pm['month'] == $nowMonth && $pm['year'] == $nowYear);
            @endphp
            @if($currentInPeriod)
            <a href="{{ route('supervisor.revenue_expense.index', ['period' => $activePeriod->id, 'month' => $nowMonth, 'year' => $nowYear]) }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition" title="{{ __('messages.go_to_current_month') }}">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></a>
            @endif
            @endif
        </div>
    </div>
    @endif

     {{-- Summary Cards  --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.income') }}</p>
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
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.business_expenses') }}</p>
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
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.net_profit') }}</p>
                    <p class="text-xl font-bold {{ $summary['net_profit'] >= 0 ? 'text-sky-600' : 'text-orange-600' }}">
                        {{ $summary['net_profit'] >= 0 ? '+' : '' }}${{ number_format($summary['net_profit'], 2) }}
                    </p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ $summary['profit_margin'] }}% margin</p>
        </div>
        @php
            $deposit_income = $income['deposit_income'] ?? 0;
            $deposit_refunds = $expenses['deposit_expenses'] ?? 0;
            $deposit_net = $deposit_income - $deposit_refunds;
        @endphp
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.deposits') }}</p>
                    <p class="text-xl font-bold {{ $deposit_net >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ $deposit_net >= 0 ? '+' : '' }}${{ number_format($deposit_net, 2) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">Received: ${{ number_format($deposit_income, 2) }} · Refunds: ${{ number_format($deposit_refunds, 2) }}</p>
        </div>
    </div>

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
                <h2 class="text-sm font-semibold text-slate-800 mb-3">{{ __('messages.income_breakdown') }}</h2>
                <div class="relative" style="height:220px;">
                    <canvas id="incomeChart"></canvas>
                </div>
                {{-- Per-type income legend rows --}}
                <div class="mt-4 space-y-1 border-t border-slate-100 pt-3">
                    {{-- Rent --}}
                    @if(($income['rent_income'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#10B981"></span><span class="text-slate-500">{{ __('messages.rent') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($income['rent_income'], 2) }}</span>
                    </div>
                    @endif
                    {{-- Utilities Income: electricity, water --}}
                    @if(($income['total_utility_income'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs font-medium text-slate-600 pt-1">
                        <span class="uppercase tracking-wider text-[10px] text-slate-400">{{ __('messages.utilities_income') }}</span>
                        <span class="text-slate-500">${{ number_format($income['total_utility_income'], 2) }}</span>
                    </div>
                    @if(($income['utility_breakdown']['electricity'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs pl-3">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#F59E0B"></span><span class="text-slate-500">{{ __('messages.electric') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($income['utility_breakdown']['electricity'], 2) }}</span>
                    </div>
                    @endif
                    @if(($income['utility_breakdown']['water'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs pl-3">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#38BDF8"></span><span class="text-slate-500">{{ __('messages.water') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($income['utility_breakdown']['water'], 2) }}</span>
                    </div>
                    @endif
                    @endif
                    {{-- Other Income: internet, parking, trash, other --}}
                    @if(($income['other_income'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs font-medium text-slate-600 pt-1">
                        <span class="uppercase tracking-wider text-[10px] text-slate-400">{{ __('messages.other_income') }}</span>
                        <span class="text-slate-500">${{ number_format($income['other_income'], 2) }}</span>
                    </div>
                    @if(($income['other_income_breakdown']['internet'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs pl-3">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#8B5CF6"></span><span class="text-slate-500">{{ __('messages.type_internet') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($income['other_income_breakdown']['internet'], 2) }}</span>
                    </div>
                    @endif
                    @if(($income['other_income_breakdown']['parking'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs pl-3">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#F97316"></span><span class="text-slate-500">{{ __('messages.type_parking') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($income['other_income_breakdown']['parking'], 2) }}</span>
                    </div>
                    @endif
                    @if(($income['other_income_breakdown']['trash'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs pl-3">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#14B8A6"></span><span class="text-slate-500">{{ __('messages.type_trash') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($income['other_income_breakdown']['trash'], 2) }}</span>
                    </div>
                    @endif
                    @if(($income['other_income_breakdown']['other'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs pl-3">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#EC4899"></span><span class="text-slate-500">{{ __('messages.type_other') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($income['other_income_breakdown']['other'], 2) }}</span>
                    </div>
                    @endif
                    @endif
                    {{-- Deposits & Late Fees --}}
                    @if(($income['deposit_income'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#6366F1"></span><span class="text-slate-500">{{ __('messages.deposits') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($income['deposit_income'], 2) }}</span>
                    </div>
                    @endif
                    @if(($income['late_fees'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#EF4444"></span><span class="text-slate-500">{{ __('messages.late_fees') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($income['late_fees'], 2) }}</span>
                    </div>
                    @endif
                    {{-- Total --}}
                    <div class="flex items-center justify-between text-xs font-bold border-t border-slate-100 pt-2 mt-1">
                        <span class="text-slate-700">{{ __('messages.total_income') }}</span>
                        <span class="text-emerald-600">${{ number_format($income['total_income'], 2) }}</span>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">{{ __('messages.expense_breakdown') }}</h2>
                <div class="relative" style="height:220px;">
                    <canvas id="expenseChart"></canvas>
                </div>
                {{-- Expense legend rows --}}
                <div class="mt-4 space-y-1 border-t border-slate-100 pt-3">
                    @php $businessExpensesTotal = ($expenses['fixed_expenses'] ?? 0) + ($expenses['variable_expenses'] ?? 0); @endphp
                    @if($businessExpensesTotal > 0)
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#F97316"></span><span class="text-slate-500">{{ __('messages.business_word') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($businessExpensesTotal, 2) }}</span>
                    </div>
                    @endif
                    @if(($expenses['utility_expenses'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#6366F1"></span><span class="text-slate-500">{{ __('messages.utilities') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($expenses['utility_expenses'], 2) }}</span>
                    </div>
                    @endif
                    @if(($expenses['deposit_expenses'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#EC4899"></span><span class="text-slate-500">{{ __('messages.deposit_refunds') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($expenses['deposit_expenses'], 2) }}</span>
                    </div>
                    @endif
                    @if(($expenses['other_expenses'] ?? 0) > 0)
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#64748B"></span><span class="text-slate-500">{{ __('messages.type_other') }}</span></div>
                        <span class="font-semibold text-slate-700">${{ number_format($expenses['other_expenses'], 2) }}</span>
                    </div>
                    @endif
                    <div class="flex items-center justify-between text-xs font-bold border-t border-slate-100 pt-2 mt-1">
                        <span class="text-slate-700">{{ __('messages.total_expenses') }}</span>
                        <span class="text-red-600">${{ number_format($expenses['total_expenses'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div x-show="tab === 'income'" x-cloak>

        {{-- Income Summary Row --}}
        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">{{ __('messages.expected_rent') }}</p>
                <p class="text-xl font-bold text-sky-600 mt-1">${{ number_format($totalRentExpected, 2) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">{{ __('messages.collected') }}</p>
                <p class="text-xl font-bold text-emerald-600 mt-1">${{ number_format($totalRentCollected, 2) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">{{ __('messages.collection_rate') }}</p>
                <p class="text-xl font-bold {{ $totalRentCollected >= $totalRentExpected ? 'text-emerald-600' : 'text-orange-600' }} mt-1">
                    {{ $totalRentExpected > 0 ? round(($totalRentCollected / $totalRentExpected) * 100, 1) : 0 }}%
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">

                {{-- Bulk Monthly Rent --}}
                <div class="bg-white rounded-xl border border-slate-100 p-5">
                    <h2 class="text-sm font-semibold text-slate-800 mb-1 pb-2 border-b border-slate-100">{{ __('messages.auto_generate_rent') }}</h2>
                    <p class="text-xs text-slate-400 mb-3">{{ __('messages.select_apts_record') }}</p>

                    @if($apartmentSummary && $apartmentSummary->total() > 0)
                    <form action="{{ route('supervisor.revenue_expense.store_income_bulk') }}" method="POST" id="bulkRentForm">
                        @csrf
                        <div class="grid grid-cols-2 gap-3 mb-4 p-3 bg-sky-50 rounded-lg">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.payment_date') }} *</label>
                                <input type="date" name="payment_date" required value="{{ date('Y-m-d') }}" class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded focus:ring-sky-500 focus:border-sky-500 bg-white appearance-none h-10">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.payment_method') }} *</label>
                                <select name="payment_method" required class="w-full px-2 py-1.5 text-sm border border-slate-200 rounded focus:ring-sky-500 focus:border-sky-500">
                                    <option value="cash">{{ __('messages.cash') }}</option>
                                    <option value="bank">{{ __('messages.bank_transfer') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm" id="bulkRentTable">
                                <thead class="bg-slate-50/80">
                                    <tr>
                                        <th class="px-2 py-2 text-center w-8"><input type="checkbox" id="selectAll" class="w-4 h-4 text-sky-600 rounded cursor-pointer" title="{{ __('messages.select_all') }}"></th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-400 uppercase">{{ __('messages.apartment') }}</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-slate-400 uppercase">{{ __('messages.tenant') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">{{ __('messages.rent_dollar') }}</th>
                                        <th class="hidden sm:table-cell px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">{{ __('messages.late_fee') }}</th>
                                        <th class="hidden sm:table-cell px-3 py-2 text-center text-xs font-medium text-slate-400 uppercase">{{ __('messages.status') }}</th>
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
                                        <td class="hidden sm:table-cell px-3 py-2 text-right">
                                            <input type="number" name="apartments[{{ $index }}][late_fee]" step="0.01" min="0" value="0"
                                                class="w-20 px-2 py-1 text-right text-sm border rounded focus:ring-sky-500 focus:border-sky-500">
                                        </td>
                                        <td class="hidden sm:table-cell px-3 py-2 text-center">
                                            @if($s['paid_this_month'])
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">{{ __('messages.paid') }}</span>
                                            @else
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">{{ __('messages.pending') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-slate-50/80">
                                    <tr>
                                        <td class="px-2 py-2"></td>
                                        <td class="px-3 py-2 font-semibold text-slate-800" colspan="2">{{ __('messages.total_selected') }}</td>
                                        <td class="px-3 py-2 text-right font-bold text-sky-600" id="totalSelectedAmount">${{ number_format($totalRentExpected, 2) }}</td>
                                        <td class="hidden sm:table-cell px-3 py-2 text-right font-bold text-amber-600" id="totalSelectedLateFee">$0.00</td>
                                        <td class="hidden sm:table-cell px-3 py-2"></td>
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
                    <p class="text-center py-6 text-slate-400 text-sm">{{ __('messages.no_apts_active_rentals') }}</p>
                    @endif
                </div>

                {{-- Record Other Income --}}
                <div class="bg-white rounded-xl border border-slate-100 p-5">
                    <h2 class="text-sm font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-100">{{ __('messages.record_other_income') }}</h2>

                    <form action="{{ route('supervisor.revenue_expense.store_income') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.apartment') }} *</label>
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
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.type') }} *</label>
                                <select name="payment_type" id="payment_type" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                                    <option value="rent">{{ __('messages.rent') }}</option>
                                    <option value="utilities">{{ __('messages.utilities') }}</option>
                                    <option value="deposit">{{ __('messages.deposit') }}</option>
                                    <option value="other">{{ __('messages.type_other') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.amount_dollar') }} *</label>
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01" required value="{{ old('amount') }}" placeholder="0.00"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.method') }} *</label>
                                <select name="payment_method" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                                    <option value="cash">{{ __('messages.cash') }}</option>
                                    <option value="bank">{{ __('messages.bank_transfer') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.date') }} *</label>
                                <input type="date" name="transaction_date" required value="{{ old('transaction_date', date('Y-m-d')) }}" class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500 bg-white appearance-none h-10">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.late_fee_dollar') }}</label>
                                <input type="number" name="late_fee" step="0.01" min="0" value="{{ old('late_fee', '0') }}" class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.reference') }}</label>
                                <input type="text" name="transaction_reference" value="{{ old('transaction_reference') }}" placeholder="TXN-001234"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">
                            </div>
                        </div>
                        <div class="mt-3">
                            <textarea name="note" rows="1" placeholder="{{ __('messages.optional_note') }}" class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-green-500 focus:border-green-500">{{ old('note') }}</textarea>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition font-medium">{{ __('messages.record_income') }}</button>
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
            <p class="text-xs text-slate-400 font-medium">{{ __('messages.total_utility_expenses') }}</p>
            <p class="text-xl font-bold text-red-600 mt-1">${{ number_format($totalExpensesAmount, 2) }}</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">

                {{-- Expenses per Apartment Table --}}
                @if(count($apartmentExpenses) > 0)
                <div class="bg-white rounded-xl border border-slate-100 p-5">
                    <h2 class="text-sm font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-100">{{ __('messages.expenses_per_apartment') }}</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50/80">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-400 uppercase">{{ __('messages.apartment') }}</th>
                                    <th class="px-3 py-2 text-center text-xs font-medium text-slate-400 uppercase">{{ __('messages.status') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">⚡</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">💧</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">📡</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">🚗</th>
                                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-400 uppercase">{{ __('messages.total') }}</th>
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
                                            {{ $aptExp['has_active_rental'] ? __('messages.occupied') : __('messages.vacant') }}
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
                                    <td class="px-3 py-2 text-slate-800" colspan="2">{{ __('messages.grand_total') }}</td>
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
                    <h2 class="text-sm font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-100">{{ __('messages.record_utility_expense') }}</h2>

                    <form action="{{ route('supervisor.revenue_expense.store_expense') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.apartment') }} *</label>
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
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.type') }} *</label>
                                <select name="utility_type" id="utility_type" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                                    <option value="">-- Select --</option>
                                    @foreach($utilityTypes as $key => $label)
                                    <option value="{{ $key }}" {{ old('utility_type') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.amount_dollar') }} *</label>
                                <input type="number" name="charge_amount" step="0.01" min="0.01" required value="{{ old('charge_amount') }}" placeholder="0.00"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.date') }} *</label>
                                <input type="date" name="transaction_date" required value="{{ old('transaction_date', date('Y-m-d')) }}"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.meter_in') }}</label>
                                <input type="number" name="meter_reading_in" step="0.01" min="0" value="{{ old('meter_reading_in') }}" placeholder="0"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.meter_out') }}</label>
                                <input type="number" name="meter_reading_out" step="0.01" min="0" value="{{ old('meter_reading_out') }}" placeholder="0"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.note') }}</label>
                                <input type="text" name="note" value="{{ old('note') }}" placeholder="{{ __('messages.optional_dots') }}"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition font-medium">{{ __('messages.record_expense') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            
        </div>
    </div>

    {{-- ================================================== --}}
    {{-- TAB 4: APARTMENT COSTS --}}
    {{-- ================================================== --}}
    <div x-show="tab === 'fixed'" x-cloak>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Apartment Costs List --}}
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
                            <p class="text-xs text-slate-400 italic">{{ __('messages.no_active_tenant') }}</p>
                            @endif
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $fa->status === 'occupied' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">
                            {{ status_label($fa->status) }}
                        </span>
                    </div>

                    @if($fa->fixedExpenses->isNotEmpty())
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50/80">
                            <tr>
                                <th class="px-2 py-1.5 text-left text-xs font-medium text-slate-400">{{ __('messages.expense') }}</th>
                                <th class="px-2 py-1.5 text-left text-xs font-medium text-slate-400">{{ __('messages.type') }}</th>
                                <th class="px-2 py-1.5 text-right text-xs font-medium text-slate-400">{{ __('messages.amount') }}</th>
                                <th class="px-2 py-1.5 text-center text-xs font-medium text-slate-400">{{ __('messages.status') }}</th>
                                <th class="px-2 py-1.5 text-center text-xs font-medium text-slate-400 w-16">{{ __('messages.actions') }}</th>
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
                                        {{ $expense->is_active ? __('messages.active') : __('messages.off') }}
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                    <div class="flex justify-center gap-1">
                                        <form action="{{ route('supervisor.revenue_expense.toggle_fixed_expense', $expense) }}" method="POST" class="inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="p-1 rounded hover:bg-slate-100" title="{{ $expense->is_active ? __('messages.disable') : __('messages.enable') }}">
                                                <svg class="w-3.5 h-3.5 {{ $expense->is_active ? 'text-amber-600' : 'text-emerald-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    @if($expense->is_active)
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                    @else
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    @endif
                                                </svg>
                                            </button>
                                        </form>
                                        <form action="{{ route('supervisor.revenue_expense.delete_fixed_expense', $expense) }}" method="POST" class="inline" data-confirm="{{ __('messages.remove_q') }}">
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
                                <td class="px-2 py-1.5 font-semibold text-slate-800" colspan="2">{{ __('messages.monthly_total') }}</td>
                                <td class="px-2 py-1.5 text-right font-bold text-red-600">${{ number_format($fa->fixedExpenses->where('is_active', true)->sum('amount'), 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                    @else
                    <p class="text-center py-2 text-slate-400 text-xs">{{ __('messages.no_apt_costs_assigned_short') }}</p>
                    @endif
                </div>
                @empty
                <div class="bg-white rounded-xl border border-slate-100 p-8 text-center text-slate-400 text-sm">{{ __('messages.no_apartments_found') }}</div>
                @endforelse
            </div>

            {{-- Add Apartment Cost Form --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-slate-100 p-5 sticky top-8">
                    <h3 class="text-sm font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-100">{{ __('messages.add_apartment_cost') }}</h3>

                    <form action="{{ route('supervisor.revenue_expense.store_fixed_expense') }}" method="POST">
                        @csrf
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.apartment') }} *</label>
                                <select name="apartment_id" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                                    <option value="">-- Select --</option>
                                    @foreach($fixedApartments as $fa)
                                    <option value="{{ $fa->id }}" {{ old('apartment_id') == $fa->id ? 'selected' : '' }}>{{ $fa->apartment_number }} (F{{ $fa->floor->floor_number ?? '?' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.type') }} *</label>
                                <select name="expense_type" id="expense_type" required class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                                    <option value="">-- Select --</option>
                                    <option value="parking" {{ old('expense_type') == 'parking' ? 'selected' : '' }}>🚗 Parking</option>
                                    <option value="internet" {{ old('expense_type') == 'internet' ? 'selected' : '' }}>📡 Internet</option>
                                    <option value="trash" {{ old('expense_type') == 'trash' ? 'selected' : '' }}>🗑️ Trash</option>
                                    <option value="other" {{ old('expense_type') == 'other' ? 'selected' : '' }}>📋 Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.name') }} *</label>
                                <input type="text" name="expense_name" id="expense_name" required value="{{ old('expense_name') }}" placeholder="{{ __('messages.eg_parking_a1') }}"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.monthly_amount') }} *</label>
                                <input type="number" name="amount" step="0.01" min="0.01" required value="{{ old('amount') }}" placeholder="0.00"
                                    class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.note') }}</label>
                                <textarea name="note" rows="2" class="w-full px-3 py-2 text-sm border rounded-lg focus:ring-red-500 focus:border-red-500">{{ old('note') }}</textarea>
                            </div>
                            <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition font-medium">
                                Assign Apartment Cost
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
        <form action="{{ route('supervisor.revenue_expense.process_bills') }}" method="POST" id="billForm">
            @csrf

            <div class="bg-white rounded-xl border border-slate-100 p-4 mb-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">{{ __('messages.billing_date') }}</label>
                        <input type="date" name="billing_date" required value="{{ date('Y-m-d') }}" class="px-3 py-1.5 text-sm border rounded-lg focus:ring-sky-500 focus:border-sky-500 bg-white appearance-none h-10">
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-slate-400">{{ __('messages.total_monthly') }}</p>
                        <p class="text-xl font-bold text-red-600">${{ number_format($totalMonthlyExpenses, 2) }}</p>
                    </div>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="selectAllBills" class="w-4 h-4 text-sky-600 rounded" checked>
                    <span class="text-xs font-medium text-slate-700">{{ __('messages.select_all') }}</span>
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
                            <p class="text-xs text-slate-400">Rent ${{ number_format($bill['monthly_rent'], 2) }} + Costs ${{ number_format($bill['total_fixed'], 2) }}</p>
                        </div>
                    </div>

                    @if(count($bill['fixed_expenses']) > 0)
                    <div class="p-3 hidden md:block">
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
                    <p class="p-3 text-xs text-slate-400 text-center hidden md:block">{{ __('messages.no_apt_costs_assigned_short') }}</p>
                    @endif
                </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between bg-white rounded-xl border border-slate-100 p-4">
                <p class="text-xs text-slate-400">{{ __('messages.only_unbilled_short') }}</p>
                <button type="submit" class="px-5 py-2 bg-slate-800 text-white text-sm rounded-lg hover:bg-slate-700 transition font-medium">
                    Generate Monthly Expenses
                </button>
            </div>
        </form>
        @else
        <div class="bg-white rounded-xl border border-slate-100 p-8 text-center">
            <p class="text-slate-400 text-sm mb-2">{{ __('messages.no_active_rentals_costs') }}</p>
            <button @click="tab = 'fixed'" class="text-sky-600 text-sm hover:underline">{{ __('messages.setup_costs_first') }}</button>
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
                <p class="text-xs text-slate-400 font-medium">{{ __('messages.total_apartments') }}</p>
                <p class="text-xl font-bold text-sky-600 mt-1">{{ $total_apartments }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">{{ __('messages.avg_rent_unit') }}</p>
                <p class="text-xl font-bold text-emerald-600 mt-1">${{ number_format($avg_rent_per_apartment, 2) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">{{ __('messages.occupancy') }}</p>
                <p class="text-xl font-bold text-purple-600 mt-1">{{ $current_occupancy }}/{{ $total_apartments }}</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <p class="text-xs text-slate-400 font-medium">{{ __('messages.status') }}</p>
                <p class="text-lg font-bold mt-1 {{ $is_above_break_even ? 'text-emerald-600' : 'text-red-600' }}">
                    {{ $is_above_break_even ? '✓ ABOVE' : '✗ BELOW' }} Break-Even
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Break-Even Calculation --}}
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <h2 class="text-sm font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">{{ __('messages.break_even_calc') }}</h2>
                <div class="space-y-3">
                    <div class="bg-slate-50 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-slate-700">{{ __('messages.business_expenses') }}</p><p class="text-xs text-slate-400">{{ __('messages.recurring_business_costs') }}</p></div>
                        <span class="text-lg font-bold text-slate-800">${{ number_format($business_expenses, 2) }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-slate-700">{{ __('messages.per_unit_cost') }}</p><p class="text-xs text-slate-400">{{ __('messages.elec_water_parking') }}</p></div>
                        <span class="text-lg font-bold text-slate-800">${{ number_format($variable_cost_per_unit, 2) }}</span>
                    </div>
                    <div class="bg-sky-50 border border-sky-200 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-slate-700">{{ __('messages.contribution_margin') }}</p><p class="text-xs text-slate-400">${{ number_format($avg_rent_per_apartment, 2) }} - ${{ number_format($variable_cost_per_unit, 2) }}</p></div>
                        <span class="text-lg font-bold text-sky-600">${{ number_format($contribution_margin_per_unit, 2) }}</span>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 p-3 rounded-lg flex justify-between items-center">
                        <p class="text-sm font-medium text-slate-700">{{ __('messages.break_even_units_label') }}</p>
                        <span class="text-lg font-bold text-amber-700">{{ $break_even_units }} units</span>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 p-3 rounded-lg flex justify-between items-center">
                        <p class="text-sm font-medium text-slate-700">{{ __('messages.break_even_revenue') }}</p>
                        <span class="text-lg font-bold text-amber-700">${{ number_format($break_even_revenue, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Current Performance --}}
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <h2 class="text-sm font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">{{ __('messages.current_performance') }}</h2>
                <div class="space-y-3">
                    <div class="bg-emerald-50 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-slate-700">{{ __('messages.current_monthly_revenue') }}</p><p class="text-xs text-slate-400">{{ $current_occupancy }} units × ${{ number_format($avg_rent_per_apartment, 2) }}</p></div>
                        <span class="text-lg font-bold text-emerald-600">${{ number_format($current_revenue, 2) }}</span>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-200 p-3 rounded-lg flex justify-between items-center">
                        <div><p class="text-sm font-medium text-slate-700">{{ __('messages.safety_margin_dollar') }}</p><p class="text-xs text-slate-400">{{ __('messages.cushion_above') }}</p></div>
                        <span class="text-lg font-bold text-emerald-600">${{ number_format($safety_margin, 2) }}</span>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-200 p-3 rounded-lg flex justify-between items-center">
                        <p class="text-sm font-medium text-slate-700">{{ __('messages.safety_margin_pct') }}</p>
                        <span class="text-lg font-bold text-emerald-600">{{ $safety_margin_percent }}%</span>
                    </div>
                    <div class="bg-sky-50 border border-sky-200 p-3 rounded-lg">
                        <div class="flex justify-between items-center mb-2">
                            <p class="text-sm font-medium text-slate-700">{{ __('messages.occupancy_rate') }}</p>
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
            <h2 class="text-sm font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-100">{{ __('messages.revenue_vs_breakeven') }}</h2>
            @php
                $maxVal = max($current_revenue, $break_even_revenue, 1);
            @endphp
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-slate-500">{{ __('messages.break_even_required') }}</span>
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
                        <span class="text-slate-500">{{ __('messages.current_revenue') }}</span>
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
        tabs: [
            { key: 'overview', label: '{{ __('messages.overview') }}' },
            { key: 'income', label: '{{ __('messages.income') }}' },
            { key: 'expense', label: '{{ __('messages.expenses_word') }}' },
            { key: 'fixed', label: '{{ __('messages.apartment_costs') }}' },
            { key: 'bills', label: '{{ __('messages.bills') }}' },
            { key: 'breakeven', label: '{{ __('messages.break_even') }}' },
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
            const names = { parking: '{{ __('messages.type_parking') }}', internet: '{{ __('messages.type_internet') }}', trash: '{{ __('messages.trash_collection') }}', other: '' };
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
        @if(($income['rent_income'] ?? 0) > 0) '{{ __('messages.rent') }}', @endif
        @if(($income['utility_breakdown']['electricity'] ?? 0) > 0) '{{ __('messages.electric') }}', @endif
        @if(($income['utility_breakdown']['water'] ?? 0) > 0) '{{ __('messages.water') }}', @endif
        @if(($income['other_income_breakdown']['internet'] ?? 0) > 0) '{{ __('messages.type_internet') }}', @endif
        @if(($income['other_income_breakdown']['parking'] ?? 0) > 0) '{{ __('messages.type_parking') }}', @endif
        @if(($income['other_income_breakdown']['trash'] ?? 0) > 0) '{{ __('messages.type_trash') }}', @endif
        @if(($income['other_income_breakdown']['other'] ?? 0) > 0) '{{ __('messages.type_other') }}', @endif
        @if(($income['deposit_income'] ?? 0) > 0) '{{ __('messages.deposits') }}', @endif
        @if(($income['late_fees'] ?? 0) > 0) '{{ __('messages.late_fees') }}', @endif
    ],
    values: [
        @if(($income['rent_income'] ?? 0) > 0) {{ $income['rent_income'] }}, @endif
        @if(($income['utility_breakdown']['electricity'] ?? 0) > 0) {{ $income['utility_breakdown']['electricity'] }}, @endif
        @if(($income['utility_breakdown']['water'] ?? 0) > 0) {{ $income['utility_breakdown']['water'] }}, @endif
        @if(($income['other_income_breakdown']['internet'] ?? 0) > 0) {{ $income['other_income_breakdown']['internet'] }}, @endif
        @if(($income['other_income_breakdown']['parking'] ?? 0) > 0) {{ $income['other_income_breakdown']['parking'] }}, @endif
        @if(($income['other_income_breakdown']['trash'] ?? 0) > 0) {{ $income['other_income_breakdown']['trash'] }}, @endif
        @if(($income['other_income_breakdown']['other'] ?? 0) > 0) {{ $income['other_income_breakdown']['other'] }}, @endif
        @if(($income['deposit_income'] ?? 0) > 0) {{ $income['deposit_income'] }}, @endif
        @if(($income['late_fees'] ?? 0) > 0) {{ $income['late_fees'] }}, @endif
    ]
};

@php $businessExpensesTotal = ($expenses['fixed_expenses'] ?? 0) + ($expenses['variable_expenses'] ?? 0); @endphp
var expenseData = {
    labels: [
        @if($businessExpensesTotal > 0) '{{ __('messages.business_word') }}', @endif
        @if(($expenses['utility_expenses'] ?? 0) > 0) '{{ __('messages.utilities') }}', @endif
        @if(($expenses['deposit_expenses'] ?? 0) > 0) '{{ __('messages.deposit_refunds') }}', @endif
        @if(($expenses['other_expenses'] ?? 0) > 0) '{{ __('messages.type_other') }}', @endif
    ],
    values: [
        @if($businessExpensesTotal > 0) {{ $businessExpensesTotal }}, @endif
        @if(($expenses['utility_expenses'] ?? 0) > 0) {{ $expenses['utility_expenses'] }}, @endif
        @if(($expenses['deposit_expenses'] ?? 0) > 0) {{ $expenses['deposit_expenses'] }}, @endif
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
            data: { labels: incomeData.labels, datasets: [{ data: incomeData.values, backgroundColor: ['#10B981','#F59E0B','#38BDF8','#8B5CF6','#F97316','#14B8A6','#EC4899','#6366F1','#EF4444'], borderWidth: 0, hoverOffset: 6 }] },
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
