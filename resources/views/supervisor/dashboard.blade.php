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
                        <p class="text-xl font-bold text-gray-900">No. {{ $stats['tenants']['active'] }}</p>
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

    {{-- Fiscal Period (removed for supervisors) --}}

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

    {{-- Recent Activity (removed for supervisors) --}}
</div>
@endsection