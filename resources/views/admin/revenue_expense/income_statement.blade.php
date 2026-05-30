@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">

    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">📊 Income Statement</h1>
            <p class="text-gray-600 mt-1">
                @if($filterMonth)
                    <span class="font-semibold text-blue-600">{{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }}</span> —
                @else
                    Full Period —
                @endif
                Fiscal Period: <span class="font-semibold text-blue-600">{{ $activePeriod->name }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($fiscalPeriods->count() > 1)
            <form method="GET" action="{{ route('admin.revenue_expense.income_statement') }}">
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
    @if(count($periodMonths) > 0)
    @php
        $currentIdx = null;
        foreach ($periodMonths as $idx => $pm) {
            if ($filterMonth == $pm['month'] && $filterYear == $pm['year']) {
                $currentIdx = $idx;
                break;
            }
        }
        $prevMonth = ($currentIdx !== null && $currentIdx > 0) ? $periodMonths[$currentIdx - 1] : null;
        $nextMonth = ($currentIdx !== null && $currentIdx < count($periodMonths) - 1) ? $periodMonths[$currentIdx + 1] : null;
    @endphp
    <div class="mb-6 flex items-center justify-center">
        <div class="inline-flex items-center bg-white rounded-xl shadow border border-gray-200 px-2 py-1.5 gap-1">
            @if($prevMonth)
            <a href="{{ route('admin.revenue_expense.income_statement', ['period' => $activePeriod->id, 'month' => $prevMonth['month'], 'year' => $prevMonth['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-blue-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </span>
            @endif

            <div class="px-4 py-2 min-w-[200px] text-center">
                @if($filterMonth)
                    <span class="text-lg font-bold text-gray-900">{{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }}</span>
                @else
                    <span class="text-lg font-bold text-gray-900">{{ __('messages.all_months') }}</span>
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">{{ __('messages.full_period') }}</span>
                @endif
            </div>

            @if($nextMonth)
            <a href="{{ route('admin.revenue_expense.income_statement', ['period' => $activePeriod->id, 'month' => $nextMonth['month'], 'year' => $nextMonth['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-blue-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
            @endif

            {{-- View All link --}}
            @if($filterMonth)
            <a href="{{ route('admin.revenue_expense.income_statement', ['period' => $activePeriod->id]) }}"
               class="ml-2 text-xs text-blue-600 hover:text-blue-800 font-medium">{{ __('messages.view_all') }}</a>
            @endif
        </div>
    </div>

    {{-- Quick Month Selector --}}
    <div class="mb-6 flex flex-wrap justify-center gap-1">
        @foreach($periodMonths as $pm)
            @php
                $isActive = ($filterMonth == $pm['month'] && $filterYear == $pm['year']);
            @endphp
            <a href="{{ route('admin.revenue_expense.income_statement', ['period' => $activePeriod->id, 'month' => $pm['month'], 'year' => $pm['year']]) }}"
               class="px-3 py-1 text-xs rounded-full border transition {{ $isActive ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:border-blue-300 hover:text-blue-600' }}">
                {{ \Carbon\Carbon::create($pm['year'], $pm['month'], 1)->format('M') }}
            </a>
        @endforeach
    </div>
    @endif

    {{-- Income Statement Card --}}
    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">

        {{-- ========== REVENUE SECTION ========== --}}
        <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-green-100">
            <h2 class="text-lg font-bold text-green-800 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V6m0 12v-2"/></svg>
                {{ __('messages.revenue') }}
            </h2>
            <p class="text-xs text-green-600 mt-0.5">{{ __('messages.income_earned_owner') }}</p>
        </div>
        <div class="divide-y divide-gray-50">
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center text-green-600">🏠</span>
                    <span class="text-gray-700">{{ __('messages.monthly_rent') }}</span>
                </div>
                <span class="font-semibold text-gray-900">${{ number_format($rentIncome, 2) }}</span>
            </div>
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center text-yellow-600">⏰</span>
                    <span class="text-gray-700">{{ __('messages.late_fees') }}</span>
                </div>
                <span class="font-semibold text-gray-900">${{ number_format($lateFees, 2) }}</span>
            </div>
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center text-orange-600">🚪</span>
                    <span class="text-gray-700">{{ __('messages.early_leave_fees') }}</span>
                </div>
                <span class="font-semibold text-gray-900">${{ number_format($earlyLeaveIncome, 2) }}</span>
            </div>
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600">🅿️</span>
                    <span class="text-gray-700">{{ __('messages.type_parking') }}</span>
                </div>
                <span class="font-semibold text-gray-900">${{ number_format($parkingRevenue, 2) }}</span>
            </div>
            {{-- Revenue Total --}}
            <div class="flex items-center justify-between px-6 py-3 bg-green-50">
                <span class="font-bold text-green-800">{{ __('messages.total_revenue') }}</span>
                <span class="font-bold text-green-800 text-lg">${{ number_format($totalRevenue, 2) }}</span>
            </div>
        </div>

        {{-- ========== GROSS REVENUE (Pass-through) SECTION ========== --}}
        <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-blue-100 border-t border-t-gray-200">
            <h2 class="text-lg font-bold text-blue-800 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                Gross Revenue <span class="text-xs font-normal text-blue-500">(Collected for Vendors)</span>
            </h2>
            <p class="text-xs text-blue-600 mt-0.5">Collected from tenants, paid to utility vendors — not your profit</p>
        </div>
        <div class="divide-y divide-gray-50">
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600">⚡</span>
                    <span class="text-gray-700">{{ __('messages.electricity_collected') }}</span>
                </div>
                <span class="font-semibold text-gray-900">${{ number_format($electricityCollected, 2) }}</span>
            </div>
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-cyan-100 flex items-center justify-center text-cyan-600">💧</span>
                    <span class="text-gray-700">{{ __('messages.water_collected') }}</span>
                </div>
                <span class="font-semibold text-gray-900">${{ number_format($waterCollected, 2) }}</span>
            </div>
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600">🌐</span>
                    <span class="text-gray-700">{{ __('messages.internet_collected') }}</span>
                </div>
                <span class="font-semibold text-gray-900">${{ number_format($internetCollected, 2) }}</span>
            </div>
            <div class="flex items-center justify-between px-6 py-3 bg-blue-50">
                <span class="font-bold text-blue-800">{{ __('messages.total_gross_revenue') }}</span>
                <span class="font-bold text-blue-800 text-lg">${{ number_format($totalGrossRevenue, 2) }}</span>
            </div>
        </div>

        {{-- Total All Collected --}}
        <div class="flex items-center justify-between px-6 py-3 bg-gradient-to-r from-green-100 to-blue-100 border-t border-gray-200">
            <span class="font-bold text-gray-800">{{ __('messages.total_collected_label') }}</span>
            <span class="font-bold text-gray-900 text-lg">${{ number_format($totalAllCollected, 2) }}</span>
        </div>

        {{-- ========== EXPENSES SECTION ========== --}}
        <div class="px-6 py-4 bg-gradient-to-r from-red-50 to-pink-50 border-b border-red-100 border-t border-t-gray-200">
            <h2 class="text-lg font-bold text-red-800 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
                {{ __('messages.expenses_word') }}
            </h2>
            <p class="text-xs text-red-600 mt-0.5">{{ __('messages.costs_owner_pays') }}</p>
        </div>
        <div class="divide-y divide-gray-50">
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600">🛡️</span>
                    <span class="text-gray-700">{{ __('messages.security') }}</span>
                </div>
                <span class="font-semibold text-red-600">-${{ number_format($securityExpense, 2) }}</span>
            </div>
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600">⚡</span>
                    <span class="text-gray-700">{{ __('messages.electric') }} <span class="text-xs text-gray-400">(paid to vendor)</span></span>
                </div>
                <span class="font-semibold text-red-600">-${{ number_format($electricityExpense, 2) }}</span>
            </div>
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-cyan-100 flex items-center justify-center text-cyan-600">💧</span>
                    <span class="text-gray-700">{{ __('messages.water') }} <span class="text-xs text-gray-400">(paid to vendor)</span></span>
                </div>
                <span class="font-semibold text-red-600">-${{ number_format($waterExpense, 2) }}</span>
            </div>
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600">🌐</span>
                    <span class="text-gray-700">{{ __('messages.type_internet') }} <span class="text-xs text-gray-400">(paid to vendor)</span></span>
                </div>
                <span class="font-semibold text-red-600">-${{ number_format($internetExpense, 2) }}</span>
            </div>
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-rose-100 flex items-center justify-center text-rose-600">🏛️</span>
                    <span class="text-gray-700">{{ __('messages.tax') }}</span>
                </div>
                <span class="font-semibold text-red-600">-${{ number_format($taxExpense, 2) }}</span>
            </div>
            @if($otherExpense > 0)
            <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50 transition">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600">📋</span>
                    <span class="text-gray-700">{{ __('messages.other_expenses') }}</span>
                </div>
                <span class="font-semibold text-red-600">-${{ number_format($otherExpense, 2) }}</span>
            </div>
            @endif
            {{-- Expense Total --}}
            <div class="flex items-center justify-between px-6 py-3 bg-red-50">
                <span class="font-bold text-red-800">{{ __('messages.total_expenses') }}</span>
                <span class="font-bold text-red-800 text-lg">-${{ number_format($totalExpenses, 2) }}</span>
            </div>
        </div>

        {{-- ========== NET INCOME ========== --}}
        <div class="px-6 py-6 border-t-4 {{ $netIncome >= 0 ? 'border-green-500 bg-gradient-to-r from-green-50 to-emerald-50' : 'border-red-500 bg-gradient-to-r from-red-50 to-pink-50' }}">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold {{ $netIncome >= 0 ? 'text-green-800' : 'text-red-800' }}">
                        {{ __('messages.net_income') }}
                    </h3>
                    <p class="text-xs {{ $netIncome >= 0 ? 'text-green-600' : 'text-red-600' }} mt-0.5">
                        Revenue (${{ number_format($totalRevenue, 2) }}) − Expenses (${{ number_format($totalExpenses, 2) }})
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-3xl font-bold {{ $netIncome >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ $netIncome >= 0 ? '' : '-' }}${{ number_format(abs($netIncome), 2) }}
                    </span>
                    @if($totalRevenue > 0)
                    <p class="text-sm {{ $netIncome >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                        {{ round(($netIncome / $totalRevenue) * 100, 1) }}% margin
                    </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- ========== VENDOR BALANCE ========== --}}
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <span class="font-semibold text-gray-700">{{ __('messages.vendor_balance') }}</span>
                    <p class="text-xs text-gray-500">{{ __('messages.utility_minus_vendors') }}</p>
                </div>
                <span class="font-bold {{ $vendorBalance >= 0 ? 'text-blue-700' : 'text-red-600' }} text-lg">
                    ${{ number_format($vendorBalance, 2) }}
                </span>
            </div>
            @if($vendorBalance > 0)
            <p class="text-xs text-amber-600 mt-2 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                You still owe ${{ number_format($vendorBalance, 2) }} to utility vendors
            </p>
            @elseif($vendorBalance < 0)
            <p class="text-xs text-red-600 mt-2 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                Overpaid vendors by ${{ number_format(abs($vendorBalance), 2) }}
            </p>
            @else
            <p class="text-xs text-green-600 mt-2">✓ All vendor payments are balanced</p>
            @endif
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
        <div class="bg-white rounded-xl shadow border border-gray-100 p-5 text-center">
            <p class="text-sm text-gray-500">{{ __('messages.total_revenue') }}</p>
            <p class="text-2xl font-bold text-green-700 mt-1">${{ number_format($totalRevenue, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow border border-gray-100 p-5 text-center">
            <p class="text-sm text-gray-500">{{ __('messages.total_expenses') }}</p>
            <p class="text-2xl font-bold text-red-600 mt-1">${{ number_format($totalExpenses, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow border border-gray-100 p-5 text-center {{ $netIncome >= 0 ? '' : 'ring-2 ring-red-200' }}">
            <p class="text-sm text-gray-500">{{ __('messages.net_income') }}</p>
            <p class="text-2xl font-bold {{ $netIncome >= 0 ? 'text-green-700' : 'text-red-600' }} mt-1">
                {{ $netIncome >= 0 ? '' : '-' }}${{ number_format(abs($netIncome), 2) }}
            </p>
        </div>
    </div>

    {{-- How it works info --}}
    <div class="mt-6 bg-white rounded-xl shadow border border-gray-100 p-5">
        <h3 class="font-semibold text-gray-800 mb-3">{{ __('messages.how_it_works') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
            <div>
                <p class="font-medium text-green-700 mb-1">{{ __('messages.revenue_your_income') }}</p>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    <li><strong>{{ __('messages.monthly_rent') }}</strong> — Rent collected from tenants</li>
                    <li><strong>{{ __('messages.late_fees') }}</strong> — Penalties for late payment</li>
                    <li><strong>{{ __('messages.early_leave') }}</strong> — Fees when tenant breaks lease early</li>
                    <li><strong>{{ __('messages.type_parking') }}</strong> — Parking space revenue</li>
                </ul>
            </div>
            <div>
                <p class="font-medium text-blue-700 mb-1">{{ __('messages.gross_revenue_pass') }}</p>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    <li><strong>{{ __('messages.elec_water_internet') }}</strong> — Collected from tenants but you pay these to the utility vendors. Not your profit.</li>
                </ul>
            </div>
            <div>
                <p class="font-medium text-red-700 mb-1">{{ __('messages.expenses_you_pay') }}</p>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    <li><strong>{{ __('messages.security') }}</strong> — Security guard/service costs</li>
                    <li><strong>{{ __('messages.elec_water_internet') }}</strong> — Paid to vendors</li>
                    <li><strong>{{ __('messages.tax') }}</strong> — Property or business tax</li>
                </ul>
            </div>
            <div>
                <p class="font-medium text-gray-700 mb-1">{{ __('messages.net_income') }}</p>
                <ul class="list-disc list-inside space-y-0.5 text-xs">
                    <li><strong>{{ __('messages.formula_label') }}</strong> {{ __('messages.formula_text') }}</li>
                    <li>{{ __('messages.gross_revenue_is') }} <strong>not</strong> included in Net Income since it's pass-through money</li>
                </ul>
            </div>
        </div>
    </div>

</div>
@endsection
