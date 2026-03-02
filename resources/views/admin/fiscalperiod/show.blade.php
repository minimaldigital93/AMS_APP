@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8">
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">{{ $fiscalperiod->name }}</h1>
                <p class="text-gray-600">
                    {{ $fiscalperiod->opening_date->format('F d, Y') }} - {{ $fiscalperiod->closing_date->format('F d, Y') }}
                </p>
            </div>
            <span class="px-4 py-2 rounded-full text-lg font-semibold {{ $fiscalperiod->status === 'open' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ ucfirst($fiscalperiod->status) }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Period Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Period Information</h2>
            <div class="space-y-3">
                <div>
                    <p class="text-gray-600 text-sm">Opening Balance</p>
                    <p class="text-2xl font-bold text-green-600">${{ number_format($fiscalperiod->opening_balance, 2) }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Closing Balance</p>
                    <p class="text-2xl font-bold text-blue-600">${{ number_format($fiscalperiod->closing_balance, 2) }}</p>
                </div>
                <div class="border-t pt-3">
                    <p class="text-gray-600 text-sm">Change in Balance</p>
                    <p class="text-lg font-semibold {{ $fiscalperiod->closing_balance >= $fiscalperiod->opening_balance ? 'text-green-600' : 'text-red-600' }}">
                        {{ $fiscalperiod->closing_balance >= $fiscalperiod->opening_balance ? '+' : '' }}${{ number_format($fiscalperiod->closing_balance - $fiscalperiod->opening_balance, 2) }}
                    </p>
                </div>
                <div class="border-t pt-3">
                    <p class="text-gray-600 text-sm">Period Duration</p>
                    <p class="text-lg font-semibold text-gray-700">{{ $fiscalperiod->opening_date->diffInDays($fiscalperiod->closing_date) }} days</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Actions</h2>
            <div class="space-y-2">
                @if($fiscalperiod->status === 'open')
                    <a href="{{ route('admin.fiscalperiod.balance-sheet', $fiscalperiod->id) }}" 
                        class="w-full flex items-center gap-3 bg-purple-600 text-white px-4 py-2.5 rounded-lg hover:bg-purple-700 transition">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span>Manage Balance Sheet</span>
                    </a>
                    <a href="{{ route('admin.fiscalperiod.open-close-balances', $fiscalperiod->id) }}" 
                        class="w-full flex items-center gap-3 bg-indigo-600 text-white px-4 py-2.5 rounded-lg hover:bg-indigo-700 transition">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        <span>Set Closing Balance</span>
                    </a>
                    <a href="{{ route('admin.fiscalperiod.edit', $fiscalperiod->id) }}" 
                        class="w-full flex items-center gap-3 bg-blue-600 text-white px-4 py-2.5 rounded-lg hover:bg-blue-700 transition">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <span>Edit Period</span>
                    </a>
                @endif
                <a href="{{ route('admin.fiscalperiod.reports', $fiscalperiod->id) }}" 
                    class="w-full flex items-center gap-3 bg-green-600 text-white px-4 py-2.5 rounded-lg hover:bg-green-700 transition">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>View Reports & Export</span>
                </a>
                <a href="{{ route('admin.fiscalperiod.index') }}" 
                    class="w-full flex items-center gap-3 bg-gray-400 text-white px-4 py-2.5 rounded-lg hover:bg-gray-500 transition">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Back to List</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Revenue & Expense Tracking Summary -->
    <div class="bg-white rounded-lg shadow p-8 mb-8">
        <h2 class="text-2xl font-semibold mb-6">Revenue & Expense Tracking</h2>
        <p class="text-gray-600 text-sm mb-6">All transactions recorded within this fiscal period ({{ $fiscalperiod->opening_date->format('M d, Y') }} - {{ $fiscalperiod->closing_date->format('M d, Y') }})</p>

        <!-- Financial Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Revenue from Rent -->
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <p class="text-green-600 text-xs font-semibold uppercase">Rent Revenue</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($financialData['revenue'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $financialData['payment_count'] }} payments collected</p>
            </div>

            <!-- Late Fees -->
            <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                <p class="text-yellow-600 text-xs font-semibold uppercase">Late Fees</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($financialData['late_fees'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">Additional income</p>
            </div>

            <!-- Total Expenses -->
            <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                <p class="text-red-600 text-xs font-semibold uppercase">Total Expenses</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($financialData['total_expenses'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ count($financialData['expenses']) }} expense categories</p>
            </div>

            <!-- Net Profit/Loss -->
            <div class="rounded-lg p-4 border {{ $financialData['is_profitable'] ? 'bg-blue-50 border-blue-200' : 'bg-orange-50 border-orange-200' }}">
                <p class="{{ $financialData['is_profitable'] ? 'text-blue-600' : 'text-orange-600' }} text-xs font-semibold uppercase">
                    {{ $financialData['is_profitable'] ? 'Net Profit' : 'Net Loss' }}
                </p>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                    {{ $financialData['is_profitable'] ? '+' : '-' }}${{ number_format(abs($financialData['net_profit']), 2) }}
                </p>
                <p class="text-xs text-gray-500 mt-1">Income - Expenses</p>
            </div>
        </div>

        <!-- Income & Expense Details Side by Side -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Income Breakdown -->
            <div class="bg-gray-50 rounded-lg p-5">
                <h3 class="font-semibold text-gray-700 mb-4">Income Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                        <span class="text-sm text-gray-700">Rent Payments</span>
                        <span class="text-sm font-bold text-green-600">${{ number_format($financialData['revenue'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-yellow-50 rounded">
                        <span class="text-sm text-gray-700">Late Fees</span>
                        <span class="text-sm font-bold text-yellow-600">${{ number_format($financialData['late_fees'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-white rounded border-2 border-green-500">
                        <span class="text-sm font-bold text-gray-900">Total Income</span>
                        <span class="text-sm font-bold text-green-600">${{ number_format($financialData['total_income'], 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Expense Breakdown by Category -->
            <div class="bg-gray-50 rounded-lg p-5">
                <h3 class="font-semibold text-gray-700 mb-4">Expense Breakdown</h3>
                @if(count($financialData['expenses']) > 0)
                <div class="space-y-3">
                    @foreach($financialData['expenses'] as $type => $amount)
                    <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                        <span class="text-sm text-gray-700 capitalize">{{ str_replace('_', ' ', $type) }}</span>
                        <span class="text-sm font-bold text-red-600">${{ number_format($amount, 2) }}</span>
                    </div>
                    @endforeach
                    <div class="flex justify-between items-center p-3 bg-white rounded border-2 border-red-500">
                        <span class="text-sm font-bold text-gray-900">Total Expenses</span>
                        <span class="text-sm font-bold text-red-600">${{ number_format($financialData['total_expenses'], 2) }}</span>
                    </div>
                </div>
                @else
                <p class="text-gray-500 text-sm text-center py-4">No expenses recorded in this period yet.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Balance Sheet Summary -->
    <div class="bg-white rounded-lg shadow p-8 mb-8">
        <h2 class="text-2xl font-semibold mb-6">Balance Sheet Items</h2>
        
        @php
            $balanceSheetItems = $fiscalperiod->balanceSheets()->get()->groupBy('item_type');
            $totalAssets = $balanceSheetItems->get('asset', collect())->sum('amount');
            $totalLiabilities = $balanceSheetItems->get('liability', collect())->sum('amount');
            $totalEquity = $balanceSheetItems->get('equity', collect())->sum('amount');
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Assets -->
            <div class="border-l-4 border-blue-600 pl-4">
                <h3 class="text-lg font-semibold text-blue-900 mb-3">Assets</h3>
                <p class="text-3xl font-bold text-blue-600">${{ number_format($totalAssets, 2) }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ $balanceSheetItems->get('asset', collect())->count() }} items</p>
            </div>

            <!-- Liabilities -->
            <div class="border-l-4 border-red-600 pl-4">
                <h3 class="text-lg font-semibold text-red-900 mb-3">Liabilities</h3>
                <p class="text-3xl font-bold text-red-600">${{ number_format($totalLiabilities, 2) }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ $balanceSheetItems->get('liability', collect())->count() }} items</p>
            </div>

            <!-- Equity -->
            <div class="border-l-4 border-green-600 pl-4">
                <h3 class="text-lg font-semibold text-green-900 mb-3">Equity</h3>
                <p class="text-3xl font-bold text-green-600">${{ number_format($totalEquity, 2) }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ $balanceSheetItems->get('equity', collect())->count() }} items</p>
            </div>
        </div>

        @if($balanceSheetItems->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b">
                            <th class="px-4 py-3 text-left text-sm font-semibold">Type</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Name</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Amount</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Date</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($balanceSheetItems as $type => $items)
                            @foreach($items as $item)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm">
                                        <span class="px-2 py-1 rounded text-xs font-semibold {{ $type === 'asset' ? 'bg-blue-100 text-blue-800' : ($type === 'liability' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800') }}">
                                            {{ ucfirst($type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium">{{ $item->name }}</td>
                                    <td class="px-4 py-3 text-sm font-semibold">${{ number_format($item->amount, 2) }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $item->as_of_date->format('Y-m-d') }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $item->reference_number ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-600 text-center py-8">No balance sheet items added yet. <a href="{{ route('admin.fiscalperiod.balance-sheet', $fiscalperiod->id) }}" class="text-blue-600 font-semibold">Add items now</a></p>
        @endif
    </div>
</div>
@endsection
