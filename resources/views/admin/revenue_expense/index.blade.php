@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Revenue & Expense Analysis</h1>
        <p class="text-gray-600 mt-2">Track your income and expense breakdown</p>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Income -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Total Income</p>
                    <p class="text-3xl font-bold text-green-600 mt-2">${{ number_format($income['total_income'], 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $income['payment_count'] }} payments collected</p>
                </div>
                <div class="text-4xl text-green-100">💵</div>
            </div>
        </div>

        <!-- Total Expenses -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Total Expenses</p>
                    <p class="text-3xl font-bold text-red-600 mt-2">${{ number_format($expenses['total_expenses'], 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $expenses['expense_count'] }} transactions recorded</p>
                </div>
                <div class="text-4xl text-red-100">💸</div>
            </div>
        </div>

        <!-- Net Profit -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 {{ $summary['is_profitable'] ? 'border-blue-500' : 'border-orange-500' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Net Profit</p>
                    <p class="text-3xl font-bold {{ $summary['is_profitable'] ? 'text-blue-600' : 'text-orange-600' }} mt-2">
                        ${{ number_format($summary['net_profit'], 2) }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">{{ $summary['profit_margin'] }}% margin</p>
                </div>
                <div class="text-4xl {{ $summary['is_profitable'] ? 'text-blue-100' : 'text-orange-100' }}">
                    {{ $summary['is_profitable'] ? '📈' : '📊' }}
                </div>
            </div>
        </div>

        <!-- Profit Status -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 {{ $summary['is_profitable'] ? 'border-green-500' : 'border-red-500' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Status</p>
                    <p class="text-lg font-bold mt-2 {{ $summary['is_profitable'] ? 'text-green-600' : 'text-red-600' }}">
                        {{ $summary['is_profitable'] ? '✓ PROFITABLE' : '✗ LOSS' }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $summary['is_profitable'] ? 'Positive' : 'Negative' }} cash flow
                    </p>
                </div>
                <div class="text-4xl {{ $summary['is_profitable'] ? 'text-green-100' : 'text-red-100' }}">
                    {{ $summary['is_profitable'] ? '✅' : '❌' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Income Breakdown -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6 pb-4 border-b-2 border-gray-200">
                Income Breakdown
            </h2>

            <div class="space-y-4">
                <!-- Rent Income -->
                <div class="flex justify-between items-center p-4 bg-green-50 rounded-lg">
                    <div>
                        <p class="text-gray-700 font-medium">Rent Income</p>
                        <p class="text-xs text-gray-600 mt-1">{{ $income['payment_count'] }} payments</p>
                    </div>
                    <p class="text-2xl font-bold text-green-600">${{ number_format($income['rent_income'], 2) }}</p>
                </div>

                <!-- Late Fees -->
                <div class="flex justify-between items-center p-4 bg-yellow-50 rounded-lg">
                    <div>
                        <p class="text-gray-700 font-medium">Late Fees</p>
                        <p class="text-xs text-gray-600 mt-1">Additional income</p>
                    </div>
                    <p class="text-2xl font-bold text-yellow-600">${{ number_format($income['late_fees'], 2) }}</p>
                </div>

                <!-- Total Income -->
                <div class="flex justify-between items-center p-4 bg-gray-100 rounded-lg border-2 border-green-500">
                    <p class="text-gray-900 font-bold">Total Income</p>
                    <p class="text-2xl font-bold text-green-600">${{ number_format($income['total_income'], 2) }}</p>
                </div>

                <!-- Average Payment -->
                <div class="text-center mt-4 p-3 bg-blue-50 rounded-lg">
                    <p class="text-xs text-gray-600">Average Payment per Transaction</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1">${{ number_format($income['average_payment'], 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Expense Breakdown -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6 pb-4 border-b-2 border-gray-200">
                Expense Breakdown
            </h2>

            <div class="space-y-4">
                <!-- Electricity -->
                <div class="flex justify-between items-center p-4 bg-yellow-50 rounded-lg">
                    <div>
                        <p class="text-gray-700 font-medium">⚡ Electricity</p>
                        <p class="text-xs text-gray-600 mt-1">Meter-based consumption</p>
                    </div>
                    <p class="text-2xl font-bold text-yellow-600">${{ number_format($expenses['electricity'], 2) }}</p>
                </div>

                <!-- Water -->
                <div class="flex justify-between items-center p-4 bg-blue-50 rounded-lg">
                    <div>
                        <p class="text-gray-700 font-medium">💧 Water</p>
                        <p class="text-xs text-gray-600 mt-1">Monthly water charges</p>
                    </div>
                    <p class="text-2xl font-bold text-blue-600">${{ number_format($expenses['water'], 2) }}</p>
                </div>

                <!-- Internet -->
                <div class="flex justify-between items-center p-4 bg-purple-50 rounded-lg">
                    <div>
                        <p class="text-gray-700 font-medium">📡 Internet</p>
                        <p class="text-xs text-gray-600 mt-1">Internet service provider</p>
                    </div>
                    <p class="text-2xl font-bold text-purple-600">${{ number_format($expenses['internet'], 2) }}</p>
                </div>

                <!-- Parking -->
                <div class="flex justify-between items-center p-4 bg-orange-50 rounded-lg">
                    <div>
                        <p class="text-gray-700 font-medium">🚗 Parking</p>
                        <p class="text-xs text-gray-600 mt-1">Parking facility charges</p>
                    </div>
                    <p class="text-2xl font-bold text-orange-600">${{ number_format($expenses['parking'], 2) }}</p>
                </div>

                <!-- Other Expenses -->
                @if($expenses['other_expenses'] > 0)
                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-gray-700 font-medium">🔧 Other Expenses</p>
                        <p class="text-xs text-gray-600 mt-1">Maintenance, repairs, etc.</p>
                    </div>
                    <p class="text-2xl font-bold text-gray-600">${{ number_format($expenses['other_expenses'], 2) }}</p>
                </div>
                @endif

                <!-- Total Expenses -->
                <div class="flex justify-between items-center p-4 bg-gray-100 rounded-lg border-2 border-red-500">
                    <p class="text-gray-900 font-bold">Total Expenses</p>
                    <p class="text-2xl font-bold text-red-600">${{ number_format($expenses['total_expenses'], 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Profit & Loss Summary -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-6 pb-4 border-b-2 border-gray-200">
            Profit & Loss Summary
        </h2>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Income -->
            <div class="text-center">
                <p class="text-gray-600 text-sm font-medium">TOTAL INCOME</p>
                <p class="text-4xl font-bold text-green-600 mt-3">${{ number_format($summary['total_income'], 2) }}</p>
                <div class="w-full h-2 bg-green-200 rounded-full mt-4"></div>
            </div>

            <!-- Minus Expenses -->
            <div class="text-center flex flex-col justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">MINUS</p>
                    <p class="text-4xl font-bold text-red-600 mt-3">${{ number_format($summary['total_expenses'], 2) }}</p>
                    <p class="text-xs text-gray-500 mt-2">TOTAL EXPENSES</p>
                </div>
            </div>

            <!-- Net Profit -->
            <div class="text-center">
                <p class="text-gray-600 text-sm font-medium">NET PROFIT/LOSS</p>
                <p class="text-4xl font-bold {{ $summary['is_profitable'] ? 'text-blue-600' : 'text-red-600' }} mt-3">
                    {{ $summary['is_profitable'] ? '+' : '-' }}${{ number_format(abs($summary['net_profit']), 2) }}
                </p>
                <div class="w-full h-2 {{ $summary['is_profitable'] ? 'bg-blue-200' : 'bg-red-200' }} rounded-full mt-4"></div>
            </div>
        </div>

        <!-- Profit Margin -->
        <div class="mt-8 p-4 bg-gray-50 rounded-lg text-center border border-gray-200">
            <p class="text-sm text-gray-600 font-medium">PROFIT MARGIN</p>
            <p class="text-3xl font-bold text-gray-900 mt-2">{{ $summary['profit_margin'] }}%</p>
            <p class="text-xs text-gray-600 mt-1">
                (Net Profit ÷ Total Income) × 100
            </p>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-wrap gap-4 justify-center">
        <a href="{{ route('admin.revenue_expense.break_even') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
            📊 View Break-Even Analysis
        </a>
        <a href="{{ route('admin.revenue_expense.record_income') }}" class="inline-flex items-center px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
            ➕ Record Income
        </a>
        <a href="{{ route('admin.revenue_expense.record_expense') }}" class="inline-flex items-center px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
            ➕ Record Expense
        </a>
        <button onclick="window.print()" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition font-medium">
            🖨 Print Report
        </button>
    </div>
</div>

<style>
    @media print {
        .container {
            box-shadow: none;
        }
        a, button {
            display: none;
        }
    }
</style>
@endsection
