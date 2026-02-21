@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Break-Even Point Analysis</h1>
        <p class="text-gray-600 mt-2">Analyze your business profitability threshold and safety margins</p>
    </div>

    <!-- Key Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Apartments -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Total Apartments</p>
                    <p class="text-3xl font-bold text-blue-600 mt-2">{{ $total_apartments }}</p>
                </div>
                <div class="text-4xl text-blue-100">🏢</div>
            </div>
        </div>

        <!-- Average Rent -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Avg. Rent/Unit</p>
                    <p class="text-3xl font-bold text-green-600 mt-2">${{ number_format($avg_rent_per_apartment, 2) }}</p>
                </div>
                <div class="text-4xl text-green-100">💰</div>
            </div>
        </div>

        <!-- Current Occupancy -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Current Occupancy</p>
                    <p class="text-3xl font-bold text-purple-600 mt-2">{{ $current_occupancy }}/{{ $total_apartments }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $total_apartments > 0 ? round(($current_occupancy / $total_apartments) * 100, 1) : 0 }}%
                    </p>
                </div>
                <div class="text-4xl text-purple-100">👥</div>
            </div>
        </div>

        <!-- Status -->
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 {{ $is_above_break_even ? 'border-green-500' : 'border-red-500' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 text-sm font-medium">Break-Even Status</p>
                    <p class="text-lg font-bold mt-2 {{ $is_above_break_even ? 'text-green-600' : 'text-red-600' }}">
                        {{ $is_above_break_even ? '✓ ABOVE' : '✗ BELOW' }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Break-Even Point</p>
                </div>
                <div class="text-4xl {{ $is_above_break_even ? 'text-green-100' : 'text-red-100' }}">
                    {{ $is_above_break_even ? '📈' : '📉' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Main Analysis Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Break-Even Point Details -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6 pb-4 border-b-2 border-gray-200">
                Break-Even Calculation
            </h2>

            <div class="space-y-4">
                <!-- Fixed Costs -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 font-medium">Fixed Costs (Monthly)</span>
                        <span class="text-lg font-bold text-gray-900">${{ number_format($fixed_costs, 2) }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Internet, maintenance, insurance</p>
                </div>

                <!-- Variable Cost Per Unit -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 font-medium">Variable Cost/Unit</span>
                        <span class="text-lg font-bold text-gray-900">${{ number_format($variable_cost_per_unit, 2) }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Electricity, water, parking per apartment</p>
                </div>

                <!-- Contribution Margin -->
                <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 font-medium">Contribution Margin/Unit</span>
                        <span class="text-lg font-bold text-blue-600">${{ number_format($contribution_margin_per_unit, 2) }}</span>
                    </div>
                    <p class="text-xs text-gray-600 mt-2">
                        Rent ({{ number_format($avg_rent_per_apartment, 2) }}) - Variable Cost ({{ number_format($variable_cost_per_unit, 2) }})
                    </p>
                </div>

                <!-- Break-Even Units -->
                <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 font-medium">Break-Even Units</span>
                        <span class="text-lg font-bold text-yellow-700">{{ $break_even_units }} units</span>
                    </div>
                    <p class="text-xs text-gray-600 mt-2">
                        Fixed Costs ÷ Contribution Margin
                    </p>
                </div>

                <!-- Break-Even Revenue -->
                <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 font-medium">Break-Even Revenue</span>
                        <span class="text-lg font-bold text-yellow-700">${{ number_format($break_even_revenue, 2) }}</span>
                    </div>
                    <p class="text-xs text-gray-600 mt-2">
                        Minimum monthly revenue to cover all costs
                    </p>
                </div>
            </div>
        </div>

        <!-- Current Business Performance -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6 pb-4 border-b-2 border-gray-200">
                Current Performance
            </h2>

            <div class="space-y-4">
                <!-- Current Monthly Revenue -->
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 font-medium">Current Monthly Revenue</span>
                        <span class="text-lg font-bold text-green-600">${{ number_format($current_revenue, 2) }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">{{ $current_occupancy }} occupied units × ${{ number_format($avg_rent_per_apartment, 2) }}</p>
                </div>

                <!-- Safety Margin $ -->
                <div class="bg-green-50 border border-green-200 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 font-medium">Safety Margin ($)</span>
                        <span class="text-lg font-bold text-green-600">${{ number_format($safety_margin, 2) }}</span>
                    </div>
                    <p class="text-xs text-gray-600 mt-2">
                        Cushion above break-even point
                    </p>
                </div>

                <!-- Safety Margin % -->
                <div class="bg-green-50 border border-green-200 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 font-medium">Safety Margin (%)</span>
                        <span class="text-lg font-bold text-green-600">{{ $safety_margin_percent }}%</span>
                    </div>
                    <p class="text-xs text-gray-600 mt-2">
                        Flexibility before reaching break-even
                    </p>
                </div>

                <!-- Occupancy Rate -->
                <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700 font-medium">Occupancy Rate</span>
                        <span class="text-lg font-bold text-blue-600">{{ $total_apartments > 0 ? round(($current_occupancy / $total_apartments) * 100, 1) : 0 }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                             style="width: {{ $total_apartments > 0 ? (($current_occupancy / $total_apartments) * 100) : 0 }}%">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Comparison Chart -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-6 pb-4 border-b-2 border-gray-200">
            Revenue vs. Break-Even Comparison
        </h2>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Visual Comparison -->
            <div class="lg:w-1/2">
                <div class="space-y-6">
                    <!-- Break-Even Revenue Bar -->
                    <div>
                        <div class="flex justify-between items-cent mb-2">
                            <span class="text-sm font-medium text-gray-700">Break-Even Required</span>
                            <span class="text-sm font-bold text-gray-900">${{ number_format($break_even_revenue, 2) }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-lg h-8 overflow-hidden">
                            <div class="bg-yellow-500 h-full flex items-center pl-2 text-white font-bold text-sm" 
                                 style="width: 100%">
                                Break-Even
                            </div>
                        </div>
                    </div>

                    <!-- Current Revenue Bar -->
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-gray-700">Current Revenue</span>
                            <span class="text-sm font-bold text-gray-900">${{ number_format($current_revenue, 2) }}</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-lg h-8 overflow-hidden">
                            <div class="bg-green-500 h-full flex items-center pl-2 text-white font-bold text-sm" 
                                 style="width: {{ $break_even_revenue > 0 ? min((($current_revenue / $break_even_revenue) * 100), 100) : 0 }}%">
                                {{ number_format((($current_revenue / $break_even_revenue) * 100), 1) }}%
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metrics Summary -->
            <div class="lg:w-1/2">
                <div class="space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Calculation Formula</p>
                        <div class="mt-3 space-y-2 text-sm text-gray-700 font-mono bg-gray-100 p-3 rounded">
                            <p>Break-Even Units = Fixed Costs ÷ Contribution Margin per Unit</p>
                            <p class="text-xs text-gray-600 mt-2">
                                = ${{ number_format($fixed_costs, 2) }} ÷ ${{ number_format($contribution_margin_per_unit, 2) }}
                            </p>
                            <p class="text-xs text-gray-600">
                                = {{ $break_even_units }} units
                            </p>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg">
                        <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide">Key Insight</p>
                        <p class="mt-2 text-sm text-gray-700">
                            @if($is_above_break_even)
                                <span class="text-green-600 font-bold">✓ Your business is PROFITABLE</span><br>
                                You are operating {{ $safety_margin_percent }}% above the break-even point with a safety margin of ${{ number_format($safety_margin, 2) }}.
                            @else
                                <span class="text-red-600 font-bold">⚠ Your business is NOT PROFITABLE</span><br>
                                You need to increase occupancy or reduce costs by ${{ number_format(abs($safety_margin), 2) }} to break even.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex gap-4 justify-center mb-8">
        <a href="{{ route('admin.revenue_expense.index') }}" class="inline-flex items-center px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            <span>← Back to Revenue & Expense</span>
        </a>
        <button onclick="window.print()" class="inline-flex items-center px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            <span>🖨 Print Report</span>
        </button>
    </div>
</div>

<style>
    @media print {
        .container {
            box-shadow: none;
        }
        button, a {
            display: none;
        }
    }
</style>
@endsection
