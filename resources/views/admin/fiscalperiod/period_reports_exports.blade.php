@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8" x-data="{ activeTab: 'overview' }">
    <!-- Header -->
    <div class="flex items-start justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold">{{ __('messages.reports_exports') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $fiscalperiod->name }} &middot; {{ $fiscalperiod->opening_date->format('M d, Y') }} – {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2 justify-end no-print">
            <a href="{{ route('admin.fiscalperiod.exportCSV', $fiscalperiod->id) }}" class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200 flex items-center gap-1.5" title="{{ __('messages.export_csv') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></a>
            <button onclick="window.print()" class="text-sm bg-gray-700 text-white px-3 py-2 rounded-lg hover:bg-gray-800 flex items-center gap-1.5" title="{{ __('messages.print') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg></button>
            <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200">← Back</a>
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
            {{ __('messages.income_statement_title') }}
        </button>
        <button @click="activeTab = 'balance_sheet'" :class="activeTab === 'balance_sheet' ? 'border-b-2 border-blue-600 text-blue-700 bg-blue-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            {{ __('messages.balance_sheet') }}
        </button>
        <button @click="activeTab = 'cash_flow'" :class="activeTab === 'cash_flow' ? 'border-b-2 border-blue-600 text-blue-700 bg-blue-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            Cash Flow
        </button>
        <button @click="activeTab = 'trial_balance'" :class="activeTab === 'trial_balance' ? 'border-b-2 border-blue-600 text-blue-700 bg-blue-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            {{ __('messages.trial_balance') }}
        </button>
        <button @click="activeTab = 'monthly'" :class="activeTab === 'monthly' ? 'border-b-2 border-blue-600 text-blue-700 bg-blue-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            {{ __('messages.monthly_breakdown') }}
        </button>
    </div>

    <!-- ==================== OVERVIEW TAB ==================== -->
    <div x-show="activeTab === 'overview'" class="bg-white rounded-b-lg shadow p-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.total_revenue') }}</p>
                <p class="text-xl font-bold text-green-600 mt-1">${{ number_format($periodFinancials['total_income'], 2) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ $periodFinancials['payment_count'] }} payments</p>
            </div>
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.total_expenses') }}</p>
                <p class="text-xl font-bold text-red-600 mt-1">${{ number_format($periodFinancials['total_expenses'], 2) }}</p>
            </div>
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ $periodFinancials['is_profitable'] ? 'Net Profit' : 'Net Loss' }}</p>
                <p class="text-xl font-bold {{ $periodFinancials['is_profitable'] ? 'text-green-600' : 'text-red-600' }} mt-1">
                    {{ $periodFinancials['is_profitable'] ? '+' : '' }}${{ number_format($periodFinancials['net_income'], 2) }}
                </p>
            </div>
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.period_balance') }}</p>
                <p class="text-xl font-bold mt-1">${{ number_format($fiscalperiod->opening_balance + $periodFinancials['net_income'], 2) }}</p>
                <p class="text-xs text-gray-400 mt-1">Open: ${{ number_format($fiscalperiod->opening_balance, 2) }}</p>
            </div>
        </div>

        <!-- Revenue vs Expenses Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="border border-gray-100 rounded-lg p-5">
                <h3 class="font-semibold text-sm text-gray-700 mb-3">{{ __('messages.revenue_sources') }}</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.rent_income') }}</span><span class="font-medium text-green-600">${{ number_format($periodFinancials['rent_income'], 2) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.late_fees') }}</span><span class="font-medium text-green-600">${{ number_format($periodFinancials['late_fees'], 2) }}</span></div>
                    @if($periodFinancials['other_income'] > 0)
                        <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.other_income') }}</span><span class="font-medium text-green-600">${{ number_format($periodFinancials['other_income'], 2) }}</span></div>
                    @endif
                    <div class="flex justify-between border-t pt-2 font-semibold"><span>{{ __('messages.total') }}</span><span class="text-green-700">${{ number_format($periodFinancials['total_income'], 2) }}</span></div>
                </div>
            </div>
            <div class="border border-gray-100 rounded-lg p-5">
                <h3 class="font-semibold text-sm text-gray-700 mb-3">{{ __('messages.expense_categories') }}</h3>
                <div class="space-y-2 text-sm">
                    @forelse($periodFinancials['utility_expenses'] as $type => $amount)
                        <div class="flex justify-between"><span class="text-gray-600 capitalize">{{ str_replace('_', ' ', $type) }}</span><span class="font-medium text-red-600">${{ number_format($amount, 2) }}</span></div>
                    @empty
                        <p class="text-gray-400 text-xs">{{ __('messages.no_utility_expenses') }}</p>
                    @endforelse
                    @if($periodFinancials['fixed_expenses'] > 0)
                        <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.fixed_other') }}</span><span class="font-medium text-red-600">${{ number_format($periodFinancials['fixed_expenses'], 2) }}</span></div>
                    @endif
                    <div class="flex justify-between border-t pt-2 font-semibold"><span>{{ __('messages.total') }}</span><span class="text-red-700">${{ number_format($periodFinancials['total_expenses'], 2) }}</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== INCOME STATEMENT TAB ==================== -->
    <div x-show="activeTab === 'income_statement'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <h2 class="text-xl font-bold mb-1">{{ __('messages.income_statement_title') }}</h2>
        <p class="text-sm text-gray-500 mb-6">{{ $fiscalperiod->opening_date->format('M d, Y') }} – {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>

        @if(count($incomeStatement['months']) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-300">
                            <th class="px-3 py-2 text-left font-semibold">{{ __('messages.account') }}</th>
                            @foreach($incomeStatement['months'] as $m)
                                <th class="px-3 py-2 text-right font-semibold">{{ $m['short'] }}</th>
                            @endforeach
                            <th class="px-3 py-2 text-right font-bold bg-gray-200">{{ __('messages.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Revenue Section -->
                        <tr class="bg-green-50 border-b">
                            <td class="px-3 py-2 font-bold text-green-800" colspan="{{ count($incomeStatement['months']) + 2 }}">{{ __('messages.revenue_caps') }}</td>
                        </tr>
                        <tr class="border-b">
                            <td class="px-3 py-2 pl-6">{{ __('messages.rent_income') }}</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right">{{ $m['data']['rent_income'] > 0 ? '$'.number_format($m['data']['rent_income'], 2) : '-' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold bg-gray-50">${{ number_format($incomeStatement['totals']['rent_income'], 2) }}</td>
                        </tr>
                        <tr class="border-b">
                            <td class="px-3 py-2 pl-6">{{ __('messages.late_fees') }}</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right">{{ $m['data']['late_fees'] > 0 ? '$'.number_format($m['data']['late_fees'], 2) : '-' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold bg-gray-50">${{ number_format($incomeStatement['totals']['late_fees'], 2) }}</td>
                        </tr>
                        <tr class="border-b">
                            <td class="px-3 py-2 pl-6">{{ __('messages.other_income') }}</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right">{{ $m['data']['other_income'] > 0 ? '$'.number_format($m['data']['other_income'], 2) : '-' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold bg-gray-50">${{ number_format($incomeStatement['totals']['other_income'], 2) }}</td>
                        </tr>
                        <tr class="border-b-2 border-green-400 bg-green-50">
                            <td class="px-3 py-2 font-bold text-green-800">{{ __('messages.total_revenue') }}</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right font-bold text-green-700">${{ number_format($m['data']['total_income'], 2) }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold text-green-800 bg-green-100">${{ number_format($incomeStatement['totals']['total_income'], 2) }}</td>
                        </tr>

                        <!-- Expense Section -->
                        <tr class="bg-red-50 border-b">
                            <td class="px-3 py-2 font-bold text-red-800" colspan="{{ count($incomeStatement['months']) + 2 }}">{{ __('messages.expenses_caps') }}</td>
                        </tr>
                        <tr class="border-b">
                            <td class="px-3 py-2 pl-6">{{ __('messages.total_expenses') }}</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right text-red-600">{{ $m['data']['total_expenses'] > 0 ? '$'.number_format($m['data']['total_expenses'], 2) : '-' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold text-red-700 bg-gray-50">${{ number_format($incomeStatement['totals']['total_expenses'], 2) }}</td>
                        </tr>

                        <!-- Net Income -->
                        <tr class="border-t-2 border-gray-400 bg-gray-100">
                            <td class="px-3 py-3 font-bold text-lg">{{ __('messages.net_income_caps') }}</td>
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
            <div class="text-center py-8 text-gray-500">{{ __('messages.no_monthly_periods_fp') }}</div>
        @endif
    </div>

    <!-- ==================== BALANCE SHEET TAB ==================== -->
    <div x-show="activeTab === 'balance_sheet'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <h2 class="text-xl font-bold mb-1">{{ __('messages.balance_sheet') }}</h2>
        <p class="text-sm text-gray-500 mb-6">As of {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.total_assets') }}</p>
                <p class="text-xl font-bold mt-1">${{ number_format($summary['total_assets'], 2) }}</p>
            </div>
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.total_liabilities') }}</p>
                <p class="text-xl font-bold text-red-600 mt-1">${{ number_format($summary['total_liabilities'], 2) }}</p>
            </div>
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.total_equity') }}</p>
                <p class="text-xl font-bold mt-1">${{ number_format($summary['total_equity'], 2) }}</p>
            </div>
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.status') }}</p>
                <p class="text-lg font-bold mt-1 {{ $summary['balance_check'] ? 'text-green-600' : 'text-amber-600' }}">
                    {{ $summary['balance_check'] ? 'Balanced' : 'Unbalanced' }}
                </p>
            </div>
        </div>

        <!-- Balance Sheet Items -->
        @if($balanceSheetItems->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-300">
                            <th class="px-4 py-2 text-left font-semibold">{{ __('messages.type') }}</th>
                            <th class="px-4 py-2 text-left font-semibold">{{ __('messages.sub_type') }}</th>
                            <th class="px-4 py-2 text-left font-semibold">{{ __('messages.name') }}</th>
                            <th class="px-4 py-2 text-right font-semibold">{{ __('messages.amount') }}</th>
                            <th class="px-4 py-2 text-left font-semibold">{{ __('messages.date') }}</th>
                            <th class="px-4 py-2 text-left font-semibold">{{ __('messages.ref') }}</th>
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
            <div class="mt-6 p-4 {{ $summary['balance_check'] ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200' }} border rounded-lg">
                <p class="text-sm {{ $summary['balance_check'] ? 'text-green-700' : 'text-amber-700' }}">
                    <strong>Assets (${{ number_format($summary['total_assets'], 2) }})</strong> =
                    <strong>Liabilities (${{ number_format($summary['total_liabilities'], 2) }})</strong> +
                    <strong>Equity (${{ number_format($summary['total_equity'], 2) }})</strong>
                    &mdash; {{ $summary['balance_check'] ? 'Balanced' : 'Not balanced' }}
                </p>
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                No balance sheet items recorded. 
                <a href="{{ route('admin.fiscalperiod.balance-sheet', $fiscalperiod->id) }}" class="text-blue-600 font-semibold">{{ __('messages.add_items') }}</a>
            </div>
        @endif
    </div>

    <!-- ==================== CASH FLOW TAB ==================== -->
    <div x-show="activeTab === 'cash_flow'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <h2 class="text-xl font-bold mb-1">{{ __('messages.cash_flow_statement') }}</h2>
        <p class="text-sm text-gray-500 mb-6">{{ $fiscalperiod->opening_date->format('M d, Y') }} – {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>

        <!-- Cash Flow Summary -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.opening_cash') }}</p>
                <p class="text-xl font-bold mt-1">${{ number_format($cashFlow['opening_balance'], 2) }}</p>
            </div>
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.total_cash_in') }}</p>
                <p class="text-xl font-bold text-green-600 mt-1">+${{ number_format($cashFlow['total_cash_in'], 2) }}</p>
            </div>
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.total_cash_out') }}</p>
                <p class="text-xl font-bold text-red-600 mt-1">-${{ number_format($cashFlow['total_cash_out'], 2) }}</p>
            </div>
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.owner_draws') }}</p>
                <p class="text-xl font-bold text-purple-600 mt-1">-${{ number_format($cashFlow['total_withdrawals'], 2) }}</p>
            </div>
            <div class="border border-gray-100 rounded-lg p-4">
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.closing_cash') }}</p>
                <p class="text-xl font-bold mt-1">${{ number_format($cashFlow['closing_balance'], 2) }}</p>
            </div>
        </div>

        @if(count($cashFlow['months']) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-300">
                            <th class="px-3 py-2 text-left font-semibold">{{ __('messages.month') }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ __('messages.opening_balance') }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ __('messages.cash_in') }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ __('messages.cash_out') }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ __('messages.net_cash_flow') }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ __('messages.owner_draw') }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ __('messages.closing_balance') }}</th>
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
                                <td class="px-3 py-2 text-right text-purple-700 font-medium">{{ $m['owner_withdrawal'] > 0 ? '-$'.number_format($m['owner_withdrawal'], 2) : '—' }}</td>
                                <td class="px-3 py-2 text-right font-semibold">${{ number_format($m['closing_balance'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 border-t-2 border-gray-400">
                            <td class="px-3 py-3 font-bold">{{ __('messages.total_caps') }}</td>
                            <td class="px-3 py-3 text-right font-bold">${{ number_format($cashFlow['opening_balance'], 2) }}</td>
                            <td class="px-3 py-3 text-right font-bold text-green-700">+${{ number_format($cashFlow['total_cash_in'], 2) }}</td>
                            <td class="px-3 py-3 text-right font-bold text-red-700">-${{ number_format($cashFlow['total_cash_out'], 2) }}</td>
                            <td class="px-3 py-3 text-right font-bold {{ $cashFlow['net_change'] >= 0 ? 'text-green-800' : 'text-red-800' }}">
                                {{ $cashFlow['net_change'] >= 0 ? '+' : '' }}${{ number_format($cashFlow['net_change'], 2) }}
                            </td>
                            <td class="px-3 py-3 text-right font-bold text-purple-700">{{ $cashFlow['total_withdrawals'] > 0 ? '-$'.number_format($cashFlow['total_withdrawals'], 2) : '—' }}</td>
                            <td class="px-3 py-3 text-right font-bold">${{ number_format($cashFlow['closing_balance'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Cash Flow Visual Bar -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('messages.cash_position_trend') }}</h3>
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
            <div class="text-center py-8 text-gray-500">{{ __('messages.no_monthly_periods') }}</div>
        @endif
    </div>

    <!-- ==================== TRIAL BALANCE TAB ==================== -->
    <div x-show="activeTab === 'trial_balance'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <h2 class="text-xl font-bold mb-1">{{ __('messages.trial_balance') }}</h2>
        <p class="text-sm text-gray-500 mb-1">As of {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>
        <p class="text-xs text-gray-400 mb-6">Post-closing: net income (retained earnings of ${{ number_format($trialBalance['retained_earnings'], 2) }}) is folded into equity, not listed as a separate account.</p>

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
                        <div class="px-4 py-3 text-gray-400 text-sm">{{ __('messages.no_debit_entries') }}</div>
                    @endforelse
                    <div class="flex justify-between items-center px-4 py-3 bg-blue-100 border-t-2 border-blue-300">
                        <span class="font-bold">{{ __('messages.total_debits') }}</span>
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
                        <div class="px-4 py-3 text-gray-400 text-sm">{{ __('messages.no_credit_entries') }}</div>
                    @endforelse
                    <div class="flex justify-between items-center px-4 py-3 bg-green-100 border-t-2 border-green-300">
                        <span class="font-bold">{{ __('messages.total_credits') }}</span>
                        <span class="font-bold text-green-700">${{ number_format($trialBalance['total_credits'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Check -->
        <div class="p-4 rounded-lg border {{ $trialBalance['is_balanced'] ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold {{ $trialBalance['is_balanced'] ? 'text-green-900' : 'text-amber-900' }}">
                        {{ $trialBalance['is_balanced'] ? 'Trial Balance is balanced' : 'Trial Balance discrepancy' }}
                    </p>
                    @if(!$trialBalance['is_balanced'])
                        <p class="text-sm text-amber-700 mt-1">
                            Difference: ${{ number_format(abs($trialBalance['difference']), 2) }}
                        </p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">{{ __('messages.debits_label') }} <strong>${{ number_format($trialBalance['total_debits'], 2) }}</strong></p>
                    <p class="text-sm text-gray-600">{{ __('messages.credits_label') }} <strong>${{ number_format($trialBalance['total_credits'], 2) }}</strong></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== MONTHLY BREAKDOWN TAB ==================== -->
    <div x-show="activeTab === 'monthly'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold">{{ __('messages.monthly_breakdown') }}</h2>
                <p class="text-sm text-gray-500">{{ __('messages.month_by_month') }}</p>
            </div>
            <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="text-blue-600 text-sm font-semibold hover:underline">
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
                                    <p class="text-gray-500 text-xs">{{ __('messages.opening_bal') }}</p>
                                    <p class="font-semibold">${{ number_format($mp->opening_balance, 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">{{ __('messages.income') }}</p>
                                    <p class="font-semibold text-green-600">+${{ number_format($mf['total_income'], 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">{{ __('messages.expenses_word') }}</p>
                                    <p class="font-semibold text-red-600">-${{ number_format($mf['total_expenses'], 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">{{ __('messages.net_income') }}</p>
                                    <p class="font-bold {{ $mf['net_income'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $mf['net_income'] >= 0 ? '+' : '' }}${{ number_format($mf['net_income'], 2) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">{{ __('messages.closing_bal') }}</p>
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
            <div class="text-center py-8 text-gray-500">{{ __('messages.no_monthly_periods') }}</div>
        @endif
    </div>
</div>

<style media="print">
    .no-print { display: none !important; }
    [x-cloak] { display: block !important; }
</style>
@endsection
