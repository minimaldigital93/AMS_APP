@extends('layouts.supervisor')

@section('content')
<div class="space-y-6">

    {{-- Flash Messages --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
        <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        <span class="text-sm font-medium">{{ session('success') }}</span>
    </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Supervisor Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">{{ now()->format('F Y') }} — Your Property Overview</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
            {{ Auth::user()->name }}
        </span>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        {{-- Revenue Collected --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Collected</p>
                    <p class="text-xl font-bold text-gray-900">${{ number_format($stats['revenue']['collected_this_month'], 2) }}</p>
                </div>
            </div>
            <div class="mt-2">
                <div class="flex justify-between text-xs text-gray-500">
                    <span>of ${{ number_format($stats['revenue']['expected_monthly'], 2) }} expected</span>
                    <span class="font-medium {{ $stats['revenue']['collection_rate'] >= 80 ? 'text-green-600' : 'text-yellow-600' }}">{{ $stats['revenue']['collection_rate'] }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                    <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ min($stats['revenue']['collection_rate'], 100) }}%"></div>
                </div>
            </div>
        </div>

        {{-- Floors --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Floors</p>
                    <p class="text-xl font-bold text-gray-900">{{ $stats['floors'] ?? 0 }}</p>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">{{ $stats['apartments']['total'] }} apartments assigned</p>
        </div>

        {{-- Occupancy Rate --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Occupancy</p>
                    <p class="text-xl font-bold text-gray-900">{{ $stats['occupancy_rate'] }}%</p>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">{{ $stats['apartments']['occupied'] }} / {{ $stats['apartments']['total'] }} rooms</p>
        </div>

        {{-- Available Rooms --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Available</p>
                    <p class="text-xl font-bold text-gray-900">{{ $stats['apartments']['available'] }}</p>
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-2">{{ $stats['apartments']['maintenance'] }} in maintenance</p>
        </div>

        {{-- Active Tenants --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-medium">Active Tenants</p>
                    <p class="text-xl font-bold text-gray-900">{{ $stats['tenants']['active'] }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Payment Status --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-green-700">Paid (Period)</p>
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

    {{-- Fiscal Period Section --}}
    @if(!$fiscalData['has_active_period'])
    <div class="bg-yellow-50 border border-yellow-300 rounded-xl p-6">
        <div class="flex items-start gap-3">
            <svg class="w-6 h-6 text-yellow-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <div>
                <h3 class="font-bold text-yellow-900">No Active Fiscal Period</h3>
                <p class="text-sm text-yellow-800 mt-1">The admin has not created an active fiscal period yet. Revenue and expense tracking is unavailable until a fiscal period is opened.</p>
            </div>
        </div>
    </div>
    @else
    {{-- Fiscal Period Overview --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-lg font-bold text-gray-900">Fiscal Period Overview</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $fiscalData['period']->name ?? 'Active Period' }} &middot;
                    {{ \Carbon\Carbon::parse($fiscalData['period']->opening_date)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($fiscalData['period']->closing_date)->format('M d, Y') }}
                </p>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Active</span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            {{-- Opening Balance --}}
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <p class="text-xs text-gray-500 font-semibold uppercase">Opening Balance</p>
                <p class="text-xl font-bold text-gray-700 mt-1">${{ number_format($fiscalData['opening_balance'], 2) }}</p>
            </div>
            {{-- Current Balance --}}
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                <p class="text-xs text-indigo-600 font-semibold uppercase">Current Balance</p>
                <p class="text-xl font-bold text-indigo-700 mt-1">${{ number_format($fiscalData['current_balance'], 2) }}</p>
            </div>
            {{-- Late Fees --}}
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                <p class="text-xs text-amber-600 font-semibold uppercase">Late Fees</p>
                <p class="text-xl font-bold text-amber-700 mt-1">${{ number_format($fiscalData['late_fees'], 2) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Total Income --}}
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <p class="text-xs text-green-600 font-semibold uppercase">Total Income</p>
                <p class="text-xl font-bold text-green-700 mt-1">${{ number_format($fiscalData['total_income'], 2) }}</p>
            </div>
            {{-- Total Expenses --}}
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-xs text-red-600 font-semibold uppercase">Total Expenses</p>
                <p class="text-xl font-bold text-red-700 mt-1">${{ number_format($fiscalData['total_expenses'], 2) }}</p>
            </div>
            {{-- Net Profit --}}
            <div class="bg-{{ $fiscalData['is_profitable'] ? 'blue' : 'orange' }}-50 border border-{{ $fiscalData['is_profitable'] ? 'blue' : 'orange' }}-200 rounded-lg p-4">
                <p class="text-xs text-{{ $fiscalData['is_profitable'] ? 'blue' : 'orange' }}-600 font-semibold uppercase">Net Profit</p>
                <p class="text-xl font-bold text-{{ $fiscalData['is_profitable'] ? 'blue' : 'orange' }}-700 mt-1">
                    {{ $fiscalData['net_profit'] >= 0 ? '' : '-' }}${{ number_format(abs($fiscalData['net_profit']), 2) }}
                </p>
            </div>
            {{-- Profit Margin --}}
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                <p class="text-xs text-purple-600 font-semibold uppercase">Profit Margin</p>
                <p class="text-xl font-bold text-purple-700 mt-1">{{ $fiscalData['profit_margin'] }}%</p>
            </div>
        </div>

        {{-- Expense Breakdown --}}
        @if(count($fiscalData['expenses']) > 0)
        <div class="mt-5 pt-5 border-t border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Expense Breakdown</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach($fiscalData['expenses'] as $type => $amount)
                <div class="bg-gray-50 rounded-lg px-3 py-2">
                    <p class="text-xs text-gray-500 capitalize">{{ str_replace('_', ' ', $type) }}</p>
                    <p class="text-sm font-bold text-gray-700">${{ number_format($amount, 2) }}</p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Monthly Revenue vs Expenses Chart Data --}}
        @if(count($fiscalData['monthly_revenue']) > 0 || count($fiscalData['monthly_expenses']) > 0)
        <div class="mt-5 pt-5 border-t border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Monthly Breakdown</h3>
            @php
                $allMonths = array_unique(array_merge(array_keys($fiscalData['monthly_revenue']), array_keys($fiscalData['monthly_expenses'])));
                sort($allMonths);
            @endphp
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left pb-2 text-xs font-semibold text-gray-500">Month</th>
                            <th class="text-right pb-2 text-xs font-semibold text-green-600">Revenue</th>
                            <th class="text-right pb-2 text-xs font-semibold text-red-600">Expenses</th>
                            <th class="text-right pb-2 text-xs font-semibold text-gray-600">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($allMonths as $month)
                        @php
                            $rev = $fiscalData['monthly_revenue'][$month] ?? 0;
                            $exp = $fiscalData['monthly_expenses'][$month] ?? 0;
                            $net = $rev - $exp;
                        @endphp
                        <tr>
                            <td class="py-2 text-gray-700 font-medium">{{ $month }}</td>
                            <td class="py-2 text-right text-green-700">${{ number_format($rev, 2) }}</td>
                            <td class="py-2 text-right text-red-700">${{ number_format($exp, 2) }}</td>
                            <td class="py-2 text-right font-medium {{ $net >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ $net >= 0 ? '+' : '-' }}${{ number_format(abs($net), 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Recent Transactions (from Accounts Ledger) --}}
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

    {{-- Recent Activity Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Registrations --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">Recent Registrations</h3>
                <a href="{{ route('supervisor.tenants.index') }}" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">View All</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($recentRegistrations as $reg)
                <div class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $reg->name }}</p>
                        <p class="text-xs text-gray-500">{{ $reg->apartment?->apartment_number ?? 'N/A' }} &middot; {{ $reg->created_at->diffForHumans() }}</p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">New</span>
                </div>
                @empty
                <div class="px-5 py-6 text-center text-sm text-gray-400">No recent registrations</div>
                @endforelse
            </div>
        </div>

        {{-- Recent Departures --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">Recent Departures</h3>
                <a href="{{ route('supervisor.tenants.archived') }}" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">View All</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($recentDepartures as $dep)
                <div class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $dep->tenant?->name ?? 'Unknown' }}</p>
                        <p class="text-xs text-gray-500">Apt {{ $dep->apartment?->apartment_number ?? 'N/A' }} &middot; Left {{ \Carbon\Carbon::parse($dep->leave_date)->format('M d, Y') }}</p>
                    </div>
                    <span class="text-xs font-medium text-gray-500">${{ number_format($dep->total_amount_due, 2) }}</span>
                </div>
                @empty
                <div class="px-5 py-6 text-center text-sm text-gray-400">No recent departures</div>
                @endforelse
            </div>
        </div>

        {{-- Recent Payments --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm lg:col-span-2">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">Recent Payments</h3>
                <a href="{{ route('supervisor.payments.index') }}" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-gray-500">Tenant</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-gray-500">Apartment</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-gray-500">Type</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-gray-500">Amount</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-gray-500">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($recentPayments as $pay)
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $pay->rental->tenant->name ?? 'N/A' }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $pay->rental->apartment->apartment_number ?? 'N/A' }}</td>
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $pay->payment_type === 'rent' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst($pay->payment_type) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right font-medium text-green-700">${{ number_format($pay->amount, 2) }}</td>
                            <td class="px-5 py-3 text-right text-gray-500">{{ \Carbon\Carbon::parse($pay->paid_at)->format('M d') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-5 py-6 text-center text-gray-400">No recent payments</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection