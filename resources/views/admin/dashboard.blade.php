@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6" x-data="{ showRevenueForm: false, showExpenseForm: false }">

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
        <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        <span class="text-sm font-medium">{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-2">
        <svg class="w-5 h-5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v4a1 1 0 102 0V5zm-1 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
        <span class="text-sm font-medium">{{ session('error') }}</span>
    </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">{{ now()->format('F Y') }} — Overview & Quick Recording</p>
        </div>
        @if($activePeriod)
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                {{ $activePeriod->name }}
            </span>
            <button @click="showRevenueForm = true" class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Record Revenue
            </button>
            <button @click="showExpenseForm = true" class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                Record Expense
            </button>
        </div>
        @endif
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Monthly Revenue --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Revenue</p>
                    <p class="text-xl font-bold text-gray-900">${{ number_format($stats['revenue']['collected_this_month'] + $stats['revenue']['late_fees_this_month'], 2) }}</p>
                </div>
            </div>
        </div>

        {{-- Monthly Expenses --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Expenses</p>
                    <p class="text-xl font-bold text-gray-900">${{ number_format($stats['expenses']['monthly_total'] ?? 0, 2) }}</p>
                </div>
            </div>
        </div>

        {{-- Net Profit --}}
        @php
            $netProfit = ($stats['revenue']['collected_this_month'] + $stats['revenue']['late_fees_this_month']) - ($stats['expenses']['monthly_total'] ?? 0);
        @endphp
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg {{ $netProfit >= 0 ? 'bg-blue-100' : 'bg-orange-100' }} flex items-center justify-center">
                    <svg class="w-5 h-5 {{ $netProfit >= 0 ? 'text-blue-600' : 'text-orange-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Net Profit</p>
                    <p class="text-xl font-bold {{ $netProfit >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ $netProfit >= 0 ? '+' : '' }}${{ number_format($netProfit, 2) }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Occupancy --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Occupied / Total</p>
                    <p class="text-xl font-bold text-gray-900">{{ $stats['apartments']['occupied'] }} / {{ $stats['apartments']['total'] }}</p>
                </div>
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
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-green-700">Paid</p>
                <p class="text-2xl font-bold text-green-800">{{ $stats['payments']['paid'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-full bg-green-200 flex items-center justify-center">
                <svg class="w-5 h-5 text-green-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            </div>
        </div>
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-yellow-700">Pending</p>
                <p class="text-2xl font-bold text-yellow-800">{{ $stats['payments']['pending'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-full bg-yellow-200 flex items-center justify-center">
                <svg class="w-5 h-5 text-yellow-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
            </div>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-red-700">Overdue</p>
                <p class="text-2xl font-bold text-red-800">{{ $stats['payments']['overdue'] }}</p>
            </div>
            <div class="w-10 h-10 rounded-full bg-red-200 flex items-center justify-center">
                <svg class="w-5 h-5 text-red-700" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            </div>
        </div>
    </div>
    @endif

    {{-- Monthly Calendar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900">{{ $calendarData['startOfMonth']->format('F Y') }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">Daily revenue and expense overview</p>
            </div>
        </div>

        {{-- Calendar Summary --}}
        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="bg-green-50 rounded-lg border border-green-200 p-3 text-center">
                <p class="text-xs text-green-600 font-semibold uppercase">Income</p>
                <p class="text-lg font-bold text-green-700">${{ number_format($calendarData['monthTotalIncome'], 2) }}</p>
            </div>
            <div class="bg-red-50 rounded-lg border border-red-200 p-3 text-center">
                <p class="text-xs text-red-600 font-semibold uppercase">Expenses</p>
                <p class="text-lg font-bold text-red-700">${{ number_format($calendarData['monthTotalExpense'], 2) }}</p>
            </div>
            <div class="bg-blue-50 rounded-lg border border-blue-200 p-3 text-center">
                <p class="text-xs text-blue-600 font-semibold uppercase">Net</p>
                <p class="text-lg font-bold {{ $calendarData['monthNet'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                    {{ $calendarData['monthNet'] >= 0 ? '+' : '' }}${{ number_format($calendarData['monthNet'], 2) }}
                </p>
            </div>
        </div>

        {{-- Calendar Grid --}}
        <div class="rounded-lg border overflow-hidden">
            <div class="grid grid-cols-7 bg-gray-50 border-b">
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                    <div class="text-center text-xs font-semibold text-gray-500 py-2 uppercase tracking-wider">{{ $dayName }}</div>
                @endforeach
            </div>
            <div class="grid grid-cols-7">
                @for($i = 0; $i < $calendarData['firstDayOfWeek']; $i++)
                    <div class="border-b border-r min-h-[80px] bg-gray-50/50"></div>
                @endfor

                @for($d = 1; $d <= $calendarData['daysInMonth']; $d++)
                    @php
                        $dayData = $calendarData['calendarDays'][$d];
                        $hasData = $dayData['tx_count'] > 0;
                        $isToday = $dayData['is_today'];
                        $isFuture = $dayData['is_future'];
                    @endphp
                    <div class="border-b border-r min-h-[80px] p-1.5 transition {{ $isToday ? 'ring-2 ring-blue-500 ring-inset bg-blue-50/30' : ($isFuture ? 'bg-gray-50/30' : ($hasData ? 'hover:bg-gray-50' : '')) }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold {{ $isToday ? 'bg-blue-500 text-white w-5 h-5 rounded-full flex items-center justify-center' : ($isFuture ? 'text-gray-300' : 'text-gray-600') }}">
                                {{ $d }}
                            </span>
                            @if($hasData)
                                <span class="text-[10px] text-gray-400">{{ $dayData['tx_count'] }}tx</span>
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
                            <p class="text-[10px] text-gray-300 mt-2 text-center">—</p>
                        @endif
                    </div>
                @endfor

                @php $trailing = (7 - (($calendarData['firstDayOfWeek'] + $calendarData['daysInMonth']) % 7)) % 7; @endphp
                @for($i = 0; $i < $trailing; $i++)
                    <div class="border-b border-r min-h-[80px] bg-gray-50/50"></div>
                @endfor
            </div>
        </div>

        {{-- Legend --}}
        <div class="flex items-center gap-4 text-xs text-gray-500 mt-3">
            <span class="flex items-center gap-1"><span class="w-2 h-2 bg-green-500 rounded-full"></span> Income</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 bg-red-500 rounded-full"></span> Expense</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 border-2 border-blue-500 rounded"></span> Today</span>
        </div>
    </div>

    {{-- Recent Transactions --}}
    @if($recentTransactions->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Recent Transactions</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-gray-200">
                        <th class="pb-2 font-semibold text-gray-600">Date</th>
                        <th class="pb-2 font-semibold text-gray-600">Type</th>
                        <th class="pb-2 font-semibold text-gray-600">Description</th>
                        <th class="pb-2 font-semibold text-gray-600">Category</th>
                        <th class="pb-2 font-semibold text-gray-600 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentTransactions as $tx)
                    <tr class="hover:bg-gray-50">
                        <td class="py-2.5 text-gray-500">{{ $tx->transaction_date->format('M d') }}</td>
                        <td class="py-2.5">
                            @if($tx->account_type === 'income')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Income</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Expense</span>
                            @endif
                        </td>
                        <td class="py-2.5 text-gray-700 max-w-[200px] truncate">{{ $tx->description }}</td>
                        <td class="py-2.5 text-gray-500 text-xs">{{ str_replace('_', ' ', ucfirst($tx->category)) }}</td>
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
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Closed Periods</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="pb-2 font-semibold text-gray-600">Period</th>
                        <th class="pb-2 font-semibold text-gray-600">Dates</th>
                        <th class="pb-2 font-semibold text-gray-600 text-right">Opening</th>
                        <th class="pb-2 font-semibold text-gray-600 text-right">Closing</th>
                        <th class="pb-2 font-semibold text-gray-600 text-right">Change</th>
                        <th class="pb-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($fiscalData['recent_periods'] as $period)
                    <tr class="hover:bg-gray-50">
                        <td class="py-2.5 font-medium">{{ $period->name }}</td>
                        <td class="py-2.5 text-gray-500">{{ $period->opening_date->format('M d') }} - {{ $period->closing_date->format('M d, Y') }}</td>
                        <td class="py-2.5 text-right">${{ number_format($period->opening_balance, 2) }}</td>
                        <td class="py-2.5 text-right">${{ number_format($period->closing_balance, 2) }}</td>
                        @php $change = $period->closing_balance - $period->opening_balance; @endphp
                        <td class="py-2.5 text-right {{ $change >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $change >= 0 ? '+' : '' }}${{ number_format($change, 2) }}
                        </td>
                        <td class="py-2.5 text-right">
                            <a href="{{ route('admin.fiscalperiod.reports', $period->id) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Report</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- RECORD REVENUE MODAL                                       --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    @if($activePeriod)
    <div x-show="showRevenueForm" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
         @keydown.escape.window="showRevenueForm = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto"
             @click.outside="showRevenueForm = false">
            <div class="p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900">Record Revenue</h3>
                    <button @click="showRevenueForm = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.dashboard.quick_revenue') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Apartment / Tenant</label>
                        <select name="rental_id" required class="w-full border-gray-300 rounded-lg text-sm focus:ring-green-500 focus:border-green-500">
                            <option value="">Select apartment...</option>
                            @foreach($apartmentsWithRentals as $apt)
                                @foreach($apt->rentals as $rental)
                                <option value="{{ $rental->id }}">
                                    Apt {{ $apt->apartment_number }} — {{ $rental->tenant->name ?? 'N/A' }} (${{ number_format($rental->rent_amount, 2) }}/mo)
                                </option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount ($)</label>
                            <input type="number" name="amount" step="0.01" min="0.01" required
                                   class="w-full border-gray-300 rounded-lg text-sm focus:ring-green-500 focus:border-green-500"
                                   placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" name="transaction_date" value="{{ now()->toDateString() }}" required
                                   class="w-full border-gray-300 rounded-lg text-sm focus:ring-green-500 focus:border-green-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select name="payment_type" required class="w-full border-gray-300 rounded-lg text-sm focus:ring-green-500 focus:border-green-500">
                                <option value="rent">Rent</option>
                                <option value="utilities">Utilities</option>
                                <option value="deposit">Deposit</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Method</label>
                            <select name="payment_method" required class="w-full border-gray-300 rounded-lg text-sm focus:ring-green-500 focus:border-green-500">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note <span class="text-gray-400">(optional)</span></label>
                        <input type="text" name="note" maxlength="500"
                               class="w-full border-gray-300 rounded-lg text-sm focus:ring-green-500 focus:border-green-500"
                               placeholder="e.g. April rent payment">
                    </div>
                    <button type="submit" class="w-full bg-green-600 text-white py-2.5 rounded-lg font-medium hover:bg-green-700 transition">
                        Record Revenue
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- RECORD EXPENSE MODAL                                       --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div x-show="showExpenseForm" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
         @keydown.escape.window="showExpenseForm = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto"
             @click.outside="showExpenseForm = false">
            <div class="p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900">Record Expense</h3>
                    <button @click="showExpenseForm = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('admin.dashboard.quick_expense') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category" required class="w-full border-gray-300 rounded-lg text-sm focus:ring-red-500 focus:border-red-500">
                            <option value="">Select category...</option>
                            <option value="utilities_expense">Utilities (Electric, Water, etc.)</option>
                            <option value="maintenance">Maintenance & Repairs</option>
                            <option value="insurance">Insurance</option>
                            <option value="property_tax">Property Tax</option>
                            <option value="management">Management Fee</option>
                            <option value="business_fixed">Business Fixed (Salary, Recurring)</option>
                            <option value="business_variable">Business Variable (Supplies, Ads)</option>
                            <option value="other_expense">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <input type="text" name="description" required maxlength="500"
                               class="w-full border-gray-300 rounded-lg text-sm focus:ring-red-500 focus:border-red-500"
                               placeholder="e.g. Electricity bill for building">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount ($)</label>
                            <input type="number" name="amount" step="0.01" min="0.01" required
                                   class="w-full border-gray-300 rounded-lg text-sm focus:ring-red-500 focus:border-red-500"
                                   placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" name="transaction_date" value="{{ now()->toDateString() }}" required
                                   class="w-full border-gray-300 rounded-lg text-sm focus:ring-red-500 focus:border-red-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note <span class="text-gray-400">(optional)</span></label>
                        <input type="text" name="note" maxlength="500"
                               class="w-full border-gray-300 rounded-lg text-sm focus:ring-red-500 focus:border-red-500"
                               placeholder="Additional details...">
                    </div>
                    <button type="submit" class="w-full bg-red-600 text-white py-2.5 rounded-lg font-medium hover:bg-red-700 transition">
                        Record Expense
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
