@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8" x-data="{ activeTab: 'overview' }">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold">Reports & Exports</h1>
            <p class="text-gray-600 mt-1">{{ $fiscalperiod->name }} &middot; {{ $fiscalperiod->opening_date->format('M d, Y') }} – {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.fiscalperiod.exportCSV', $fiscalperiod->id) }}" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm font-semibold">
                ↓ CSV
            </a>
            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-semibold">
                🖨 Print
            </button>
            <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="bg-gray-400 text-white px-4 py-2 rounded-lg hover:bg-gray-500 transition text-sm font-semibold">
                ← Back
            </a>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white rounded-t-lg shadow-sm border-b flex overflow-x-auto no-print">
        <button @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'border-b-2 border-blue-600 text-blue-700 bg-blue-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            Overview
        </button>
        <button @click="activeTab = 'income_statement'" :class="activeTab === 'income_statement' ? 'border-b-2 border-blue-600 text-blue-700 bg-blue-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            Income Statement
        </button>
        <button @click="activeTab = 'balance_sheet'" :class="activeTab === 'balance_sheet' ? 'border-b-2 border-blue-600 text-blue-700 bg-blue-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            Balance Sheet
        </button>
        <button @click="activeTab = 'cash_flow'" :class="activeTab === 'cash_flow' ? 'border-b-2 border-blue-600 text-blue-700 bg-blue-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            Cash Flow
        </button>
        <button @click="activeTab = 'trial_balance'" :class="activeTab === 'trial_balance' ? 'border-b-2 border-blue-600 text-blue-700 bg-blue-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            Trial Balance
        </button>
        <button @click="activeTab = 'monthly'" :class="activeTab === 'monthly' ? 'border-b-2 border-blue-600 text-blue-700 bg-blue-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            Monthly Breakdown
        </button>
    </div>

    <!-- ==================== OVERVIEW TAB ==================== -->
    <div x-show="activeTab === 'overview'" class="bg-white rounded-b-lg shadow p-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="rounded-lg p-5 border-l-4 border-green-600 bg-green-50">
                <p class="text-xs text-gray-600 uppercase font-semibold">Total Revenue</p>
                <p class="text-2xl font-bold text-green-700 mt-1">${{ number_format($periodFinancials['total_income'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $periodFinancials['payment_count'] }} payments</p>
            </div>
            <div class="rounded-lg p-5 border-l-4 border-red-600 bg-red-50">
                <p class="text-xs text-gray-600 uppercase font-semibold">Total Expenses</p>
                <p class="text-2xl font-bold text-red-700 mt-1">${{ number_format($periodFinancials['total_expenses'], 2) }}</p>
            </div>
            <div class="rounded-lg p-5 border-l-4 {{ $periodFinancials['is_profitable'] ? 'border-blue-600 bg-blue-50' : 'border-orange-600 bg-orange-50' }}">
                <p class="text-xs text-gray-600 uppercase font-semibold">{{ $periodFinancials['is_profitable'] ? 'Net Profit' : 'Net Loss' }}</p>
                <p class="text-2xl font-bold {{ $periodFinancials['is_profitable'] ? 'text-blue-700' : 'text-orange-700' }} mt-1">
                    {{ $periodFinancials['is_profitable'] ? '+' : '' }}${{ number_format($periodFinancials['net_income'], 2) }}
                </p>
            </div>
            <div class="rounded-lg p-5 border-l-4 border-indigo-600 bg-indigo-50">
                <p class="text-xs text-gray-600 uppercase font-semibold">Period Balance</p>
                <p class="text-2xl font-bold text-indigo-700 mt-1">${{ number_format($fiscalperiod->opening_balance + $periodFinancials['net_income'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">Open: ${{ number_format($fiscalperiod->opening_balance, 2) }}</p>
            </div>
        </div>

        <!-- Revenue vs Expenses Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-gray-50 rounded-lg p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Revenue Sources</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded border">
                        <span class="text-sm">Rent Income</span>
                        <span class="font-bold text-green-600">${{ number_format($periodFinancials['rent_income'], 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-yellow-50 rounded border">
                        <span class="text-sm">Late Fees</span>
                        <span class="font-bold text-yellow-600">${{ number_format($periodFinancials['late_fees'], 2) }}</span>
                    </div>
                    @if($periodFinancials['other_income'] > 0)
                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded border">
                            <span class="text-sm">Other Income</span>
                            <span class="font-bold text-blue-600">${{ number_format($periodFinancials['other_income'], 2) }}</span>
                        </div>
                    @endif
                </div>
            </div>
            <div class="bg-gray-50 rounded-lg p-5">
                <h3 class="font-semibold text-gray-800 mb-4">Expense Categories</h3>
                <div class="space-y-3">
                    @foreach($periodFinancials['utility_expenses'] as $type => $amount)
                        <div class="flex justify-between items-center p-3 bg-red-50 rounded border">
                            <span class="text-sm">
                                @switch($type)
                                    @case('electricity') 🔌 Electricity @break
                                    @case('water') 💧 Water @break
                                    @case('internet') 📡 Internet @break
                                    @case('parking') 🅿️ Parking @break
                                    @default {{ ucfirst($type) }}
                                @endswitch
                            </span>
                            <span class="font-bold text-red-600">${{ number_format($amount, 2) }}</span>
                        </div>
                    @endforeach
                    @if($periodFinancials['fixed_expenses'] > 0)
                        <div class="flex justify-between items-center p-3 bg-orange-50 rounded border">
                            <span class="text-sm">📋 Fixed/Other Expenses</span>
                            <span class="font-bold text-orange-600">${{ number_format($periodFinancials['fixed_expenses'], 2) }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== INCOME STATEMENT TAB ==================== -->
    <div x-show="activeTab === 'income_statement'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <h2 class="text-xl font-bold mb-1">Income Statement</h2>
        <p class="text-sm text-gray-500 mb-6">{{ $fiscalperiod->opening_date->format('M d, Y') }} – {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>

        @if(count($incomeStatement['months']) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-300">
                            <th class="px-3 py-2 text-left font-semibold">Account</th>
                            @foreach($incomeStatement['months'] as $m)
                                <th class="px-3 py-2 text-right font-semibold">{{ $m['short'] }}</th>
                            @endforeach
                            <th class="px-3 py-2 text-right font-bold bg-gray-200">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Revenue Section -->
                        <tr class="bg-green-50 border-b">
                            <td class="px-3 py-2 font-bold text-green-800" colspan="{{ count($incomeStatement['months']) + 2 }}">REVENUE</td>
                        </tr>
                        <tr class="border-b">
                            <td class="px-3 py-2 pl-6">Rent Income</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right">{{ $m['data']['rent_income'] > 0 ? '$'.number_format($m['data']['rent_income'], 2) : '-' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold bg-gray-50">${{ number_format($incomeStatement['totals']['rent_income'], 2) }}</td>
                        </tr>
                        <tr class="border-b">
                            <td class="px-3 py-2 pl-6">Late Fees</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right">{{ $m['data']['late_fees'] > 0 ? '$'.number_format($m['data']['late_fees'], 2) : '-' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold bg-gray-50">${{ number_format($incomeStatement['totals']['late_fees'], 2) }}</td>
                        </tr>
                        <tr class="border-b">
                            <td class="px-3 py-2 pl-6">Other Income</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right">{{ $m['data']['other_income'] > 0 ? '$'.number_format($m['data']['other_income'], 2) : '-' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold bg-gray-50">${{ number_format($incomeStatement['totals']['other_income'], 2) }}</td>
                        </tr>
                        <tr class="border-b-2 border-green-400 bg-green-50">
                            <td class="px-3 py-2 font-bold text-green-800">Total Revenue</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right font-bold text-green-700">${{ number_format($m['data']['total_income'], 2) }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold text-green-800 bg-green-100">${{ number_format($incomeStatement['totals']['total_income'], 2) }}</td>
                        </tr>

                        <!-- Expense Section -->
                        <tr class="bg-red-50 border-b">
                            <td class="px-3 py-2 font-bold text-red-800" colspan="{{ count($incomeStatement['months']) + 2 }}">EXPENSES</td>
                        </tr>
                        <tr class="border-b">
                            <td class="px-3 py-2 pl-6">Total Expenses</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right text-red-600">{{ $m['data']['total_expenses'] > 0 ? '$'.number_format($m['data']['total_expenses'], 2) : '-' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold text-red-700 bg-gray-50">${{ number_format($incomeStatement['totals']['total_expenses'], 2) }}</td>
                        </tr>

                        <!-- Net Income -->
                        <tr class="border-t-2 border-gray-400 bg-gray-100">
                            <td class="px-3 py-3 font-bold text-lg">NET INCOME</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-3 text-right font-bold {{ $m['data']['net_income'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $m['data']['net_income'] >= 0 ? '+' : '' }}${{ number_format($m['data']['net_income'], 2) }}
                                </td>
                            @endforeach
                            <td class="px-3 py-3 text-right font-bold text-lg {{ $incomeStatement['totals']['net_income'] >= 0 ? 'text-green-800' : 'text-red-800' }} bg-gray-200">
                                {{ $incomeStatement['totals']['net_income'] >= 0 ? '+' : '' }}${{ number_format($incomeStatement['totals']['net_income'], 2) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8 text-gray-500">No monthly periods found for this fiscal period.</div>
        @endif
    </div>

    <!-- ==================== BALANCE SHEET TAB ==================== -->
    <div x-show="activeTab === 'balance_sheet'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <h2 class="text-xl font-bold mb-1">Balance Sheet</h2>
        <p class="text-sm text-gray-500 mb-6">As of {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="rounded-lg p-4 border-l-4 border-blue-600 bg-blue-50">
                <p class="text-xs text-gray-600 font-semibold">Total Assets</p>
                <p class="text-2xl font-bold text-blue-600">${{ number_format($summary['total_assets'], 2) }}</p>
            </div>
            <div class="rounded-lg p-4 border-l-4 border-red-600 bg-red-50">
                <p class="text-xs text-gray-600 font-semibold">Total Liabilities</p>
                <p class="text-2xl font-bold text-red-600">${{ number_format($summary['total_liabilities'], 2) }}</p>
            </div>
            <div class="rounded-lg p-4 border-l-4 border-green-600 bg-green-50">
                <p class="text-xs text-gray-600 font-semibold">Total Equity</p>
                <p class="text-2xl font-bold text-green-600">${{ number_format($summary['total_equity'], 2) }}</p>
            </div>
            <div class="rounded-lg p-4 border-l-4 {{ $summary['balance_check'] ? 'border-green-600 bg-green-50' : 'border-yellow-600 bg-yellow-50' }}">
                <p class="text-xs text-gray-600 font-semibold">Status</p>
                <p class="text-lg font-bold {{ $summary['balance_check'] ? 'text-green-600' : 'text-yellow-600' }}">
                    {{ $summary['balance_check'] ? '✓ Balanced' : '✗ Unbalanced' }}
                </p>
            </div>
        </div>

        <!-- Balance Sheet Items -->
        @if($balanceSheetItems->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-300">
                            <th class="px-4 py-2 text-left font-semibold">Type</th>
                            <th class="px-4 py-2 text-left font-semibold">Sub Type</th>
                            <th class="px-4 py-2 text-left font-semibold">Name</th>
                            <th class="px-4 py-2 text-right font-semibold">Amount</th>
                            <th class="px-4 py-2 text-left font-semibold">Date</th>
                            <th class="px-4 py-2 text-left font-semibold">Ref</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($balanceSheetItems->groupBy('item_type') as $type => $items)
                            <tr class="bg-gray-50 border-b">
                                <td class="px-4 py-2 font-bold uppercase" colspan="6">{{ $type }}</td>
                            </tr>
                            @foreach($items as $item)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $item->item_type === 'asset' ? 'bg-blue-100 text-blue-800' : ($item->item_type === 'liability' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800') }}">
                                            {{ ucfirst($item->item_type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">{{ ucfirst(str_replace('_', ' ', $item->sub_type)) }}</td>
                                    <td class="px-4 py-2 font-medium">{{ $item->name }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">${{ number_format($item->amount, 2) }}</td>
                                    <td class="px-4 py-2">{{ $item->as_of_date->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $item->reference_number ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Equation Verification -->
            <div class="mt-6 p-4 {{ $summary['balance_check'] ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }} border rounded-lg">
                <p class="text-sm {{ $summary['balance_check'] ? 'text-green-700' : 'text-yellow-700' }}">
                    <strong>Assets (${{ number_format($summary['total_assets'], 2) }})</strong> =
                    <strong>Liabilities (${{ number_format($summary['total_liabilities'], 2) }})</strong> +
                    <strong>Equity (${{ number_format($summary['total_equity'], 2) }})</strong>
                    &mdash; {{ $summary['balance_check'] ? '✓ Balanced' : '⚠ Not balanced' }}
                </p>
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                No balance sheet items recorded. 
                <a href="{{ route('admin.fiscalperiod.balance-sheet', $fiscalperiod->id) }}" class="text-blue-600 font-semibold">Add items</a>
            </div>
        @endif
    </div>

    <!-- ==================== CASH FLOW TAB ==================== -->
    <div x-show="activeTab === 'cash_flow'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <h2 class="text-xl font-bold mb-1">Cash Flow Statement</h2>
        <p class="text-sm text-gray-500 mb-6">{{ $fiscalperiod->opening_date->format('M d, Y') }} – {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>

        <!-- Cash Flow Summary -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="rounded-lg p-4 border-l-4 border-blue-600 bg-blue-50">
                <p class="text-xs text-gray-600 font-semibold">Opening Cash</p>
                <p class="text-xl font-bold">${{ number_format($cashFlow['opening_balance'], 2) }}</p>
            </div>
            <div class="rounded-lg p-4 border-l-4 border-green-600 bg-green-50">
                <p class="text-xs text-gray-600 font-semibold">Total Cash In</p>
                <p class="text-xl font-bold text-green-600">+${{ number_format($cashFlow['total_cash_in'], 2) }}</p>
            </div>
            <div class="rounded-lg p-4 border-l-4 border-red-600 bg-red-50">
                <p class="text-xs text-gray-600 font-semibold">Total Cash Out</p>
                <p class="text-xl font-bold text-red-600">-${{ number_format($cashFlow['total_cash_out'], 2) }}</p>
            </div>
            <div class="rounded-lg p-4 border-l-4 border-indigo-600 bg-indigo-50">
                <p class="text-xs text-gray-600 font-semibold">Closing Cash</p>
                <p class="text-xl font-bold">${{ number_format($cashFlow['closing_balance'], 2) }}</p>
            </div>
        </div>

        @if(count($cashFlow['months']) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-300">
                            <th class="px-3 py-2 text-left font-semibold">Month</th>
                            <th class="px-3 py-2 text-right font-semibold">Opening Balance</th>
                            <th class="px-3 py-2 text-right font-semibold">Cash In</th>
                            <th class="px-3 py-2 text-right font-semibold">Cash Out</th>
                            <th class="px-3 py-2 text-right font-semibold">Net Cash Flow</th>
                            <th class="px-3 py-2 text-right font-semibold">Closing Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cashFlow['months'] as $m)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-3 py-2 font-medium">{{ $m['name'] }}</td>
                                <td class="px-3 py-2 text-right">${{ number_format($m['opening_balance'], 2) }}</td>
                                <td class="px-3 py-2 text-right text-green-600 font-medium">+${{ number_format($m['cash_in'], 2) }}</td>
                                <td class="px-3 py-2 text-right text-red-600 font-medium">-${{ number_format($m['cash_out'], 2) }}</td>
                                <td class="px-3 py-2 text-right font-bold {{ $m['net_cash_flow'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $m['net_cash_flow'] >= 0 ? '+' : '' }}${{ number_format($m['net_cash_flow'], 2) }}
                                </td>
                                <td class="px-3 py-2 text-right font-semibold">${{ number_format($m['closing_balance'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 border-t-2 border-gray-400">
                            <td class="px-3 py-3 font-bold">TOTAL</td>
                            <td class="px-3 py-3 text-right font-bold">${{ number_format($cashFlow['opening_balance'], 2) }}</td>
                            <td class="px-3 py-3 text-right font-bold text-green-700">+${{ number_format($cashFlow['total_cash_in'], 2) }}</td>
                            <td class="px-3 py-3 text-right font-bold text-red-700">-${{ number_format($cashFlow['total_cash_out'], 2) }}</td>
                            <td class="px-3 py-3 text-right font-bold {{ $cashFlow['net_change'] >= 0 ? 'text-green-800' : 'text-red-800' }}">
                                {{ $cashFlow['net_change'] >= 0 ? '+' : '' }}${{ number_format($cashFlow['net_change'], 2) }}
                            </td>
                            <td class="px-3 py-3 text-right font-bold">${{ number_format($cashFlow['closing_balance'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Cash Flow Visual Bar -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Cash Position Trend</h3>
                <div class="flex items-end gap-1" style="height: 120px;">
                    @php
                        $maxBal = max(array_column($cashFlow['months'], 'closing_balance'));
                        $maxBal = $maxBal > 0 ? $maxBal : 1;
                    @endphp
                    @foreach($cashFlow['months'] as $m)
                        @php
                            $height = $maxBal > 0 ? max(($m['closing_balance'] / $maxBal) * 100, 5) : 5;
                        @endphp
                        <div class="flex-1 flex flex-col items-center group">
                            <div class="w-full rounded-t {{ $m['net_cash_flow'] >= 0 ? 'bg-green-400' : 'bg-red-400' }} transition-all relative"
                                 style="height: {{ $height }}%"
                                 title="${{ number_format($m['closing_balance'], 2) }}">
                            </div>
                            <span class="text-[10px] text-gray-500 mt-1">{{ $m['short'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="text-center py-8 text-gray-500">No monthly periods found.</div>
        @endif
    </div>

    <!-- ==================== TRIAL BALANCE TAB ==================== -->
    <div x-show="activeTab === 'trial_balance'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <h2 class="text-xl font-bold mb-1">Trial Balance</h2>
        <p class="text-sm text-gray-500 mb-6">As of {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Debits -->
            <div>
                <h3 class="text-sm font-bold text-gray-700 uppercase mb-3 flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-blue-500"></span> Debit Accounts
                </h3>
                <div class="bg-gray-50 rounded-lg overflow-hidden">
                    @forelse($trialBalance['debits'] as $debit)
                        <div class="flex justify-between items-center px-4 py-3 border-b">
                            <span class="text-sm">{{ $debit['account'] }}</span>
                            <span class="text-sm font-semibold">${{ number_format($debit['amount'], 2) }}</span>
                        </div>
                    @empty
                        <div class="px-4 py-3 text-gray-400 text-sm">No debit entries</div>
                    @endforelse
                    <div class="flex justify-between items-center px-4 py-3 bg-blue-100 border-t-2 border-blue-300">
                        <span class="font-bold">Total Debits</span>
                        <span class="font-bold text-blue-700">${{ number_format($trialBalance['total_debits'], 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Credits -->
            <div>
                <h3 class="text-sm font-bold text-gray-700 uppercase mb-3 flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-green-500"></span> Credit Accounts
                </h3>
                <div class="bg-gray-50 rounded-lg overflow-hidden">
                    @forelse($trialBalance['credits'] as $credit)
                        <div class="flex justify-between items-center px-4 py-3 border-b">
                            <span class="text-sm">{{ $credit['account'] }}</span>
                            <span class="text-sm font-semibold">${{ number_format($credit['amount'], 2) }}</span>
                        </div>
                    @empty
                        <div class="px-4 py-3 text-gray-400 text-sm">No credit entries</div>
                    @endforelse
                    <div class="flex justify-between items-center px-4 py-3 bg-green-100 border-t-2 border-green-300">
                        <span class="font-bold">Total Credits</span>
                        <span class="font-bold text-green-700">${{ number_format($trialBalance['total_credits'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Check -->
        <div class="p-4 rounded-lg border {{ $trialBalance['is_balanced'] ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold {{ $trialBalance['is_balanced'] ? 'text-green-900' : 'text-yellow-900' }}">
                        {{ $trialBalance['is_balanced'] ? '✓ Trial Balance is Balanced' : '⚠ Trial Balance Discrepancy' }}
                    </p>
                    @if(!$trialBalance['is_balanced'])
                        <p class="text-sm text-yellow-700 mt-1">
                            Difference: ${{ number_format(abs($trialBalance['difference']), 2) }}
                        </p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">Debits: <strong>${{ number_format($trialBalance['total_debits'], 2) }}</strong></p>
                    <p class="text-sm text-gray-600">Credits: <strong>${{ number_format($trialBalance['total_credits'], 2) }}</strong></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== MONTHLY BREAKDOWN TAB ==================== -->
    <div x-show="activeTab === 'monthly'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold">Monthly Breakdown</h2>
                <p class="text-sm text-gray-500">Month-by-month performance analysis</p>
            </div>
            <a href="{{ route('admin.fiscalperiod.monthly-periods', $fiscalperiod->id) }}" class="text-blue-600 text-sm font-semibold hover:underline">
                Manage Monthly Periods →
            </a>
        </div>

        @if(count($monthlyData) > 0)
            <div class="space-y-4">
                @foreach($monthlyData as $md)
                    @php $mp = $md['period']; $mf = $md['financials']; @endphp
                    <div class="border rounded-lg overflow-hidden {{ $mp->isClosed() ? 'border-gray-200' : 'border-blue-200' }}">
                        <div class="flex items-center justify-between px-5 py-3 {{ $mp->isClosed() ? 'bg-gray-50' : 'bg-blue-50' }}">
                            <div class="flex items-center gap-3">
                                <h3 class="font-semibold">{{ $mp->name }}</h3>
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $mp->status === 'open' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($mp->status) }}
                                </span>
                            </div>
                            <span class="text-sm text-gray-600">{{ $mp->start_date->format('M d') }} – {{ $mp->end_date->format('M d') }}</span>
                        </div>
                        <div class="px-5 py-4">
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500 text-xs">Opening Bal.</p>
                                    <p class="font-semibold">${{ number_format($mp->opening_balance, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Income</p>
                                    <p class="font-semibold text-green-600">+${{ number_format($mf['total_income'], 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Expenses</p>
                                    <p class="font-semibold text-red-600">-${{ number_format($mf['total_expenses'], 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Net Income</p>
                                    <p class="font-bold {{ $mf['net_income'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $mf['net_income'] >= 0 ? '+' : '' }}${{ number_format($mf['net_income'], 2) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">Closing Bal.</p>
                                    <p class="font-semibold">
                                        @if($mp->isClosed())
                                            ${{ number_format($mp->closing_balance, 2) }}
                                        @else
                                            <span class="text-gray-400">${{ number_format($mp->opening_balance + $mf['net_income'], 2) }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8 text-gray-500">No monthly periods found.</div>
        @endif
    </div>
</div>

<style media="print">
    .no-print { display: none !important; }
    [x-cloak] { display: block !important; }
</style>
@endsection
