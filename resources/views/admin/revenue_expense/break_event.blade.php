@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Break-Even Analysis</h1>
            <p class="text-gray-600 mt-2">How many units you need to rent to cover all costs</p>
        </div>
        <a href="{{ route('admin.revenue_expense.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back
        </a>
    </div>

    <!-- Status Banner -->
    <div class="rounded-xl shadow-md p-6 mb-8 {{ $is_above_break_even ? 'bg-gradient-to-r from-green-600 to-green-700' : 'bg-gradient-to-r from-red-600 to-red-700' }} text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium {{ $is_above_break_even ? 'text-green-200' : 'text-red-200' }}">Break-Even Status</p>
                <p class="text-2xl font-bold mt-1">
                    @if($is_above_break_even)
                        ✓ Profitable
                    @else
                        ✗ Not Yet Profitable
                    @endif
                </p>
                <p class="text-sm mt-2 {{ $is_above_break_even ? 'text-green-200' : 'text-red-200' }}">
                    Need <strong>{{ $break_even_units }} units</strong> rented to break even. Currently <strong>{{ $current_occupancy }}/{{ $total_apartments }}</strong> occupied.
                </p>
            </div>
            <div class="text-right">
                @if(!$is_above_break_even)
                    <p class="text-sm text-red-200">Amount needed</p>
                    <p class="text-3xl font-bold">${{ number_format($amount_needed, 2) }}</p>
                    <p class="text-sm text-red-200">{{ $units_needed }} more unit(s)</p>
                @else
                    <p class="text-sm text-green-200">Surplus</p>
                    <p class="text-3xl font-bold">${{ number_format($safety_margin, 2) }}</p>
                    <p class="text-sm text-green-200">above break-even</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Key Numbers -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-500">
            <p class="text-gray-500 text-sm font-medium">Break-Even Point</p>
            <p class="text-2xl font-bold text-yellow-600 mt-1">{{ $break_even_units }} units</p>
            <p class="text-xs text-gray-400 mt-1">${{ number_format($break_even_revenue, 2) }} required</p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <p class="text-gray-500 text-sm font-medium">Current Revenue</p>
            <p class="text-2xl font-bold text-green-600 mt-1">${{ number_format($current_revenue, 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $current_occupancy }} units occupied</p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
            <p class="text-gray-500 text-sm font-medium">Total Fixed Costs</p>
            <p class="text-2xl font-bold text-orange-600 mt-1">${{ number_format($fixed_costs, 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">monthly recurring</p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 {{ $is_above_break_even ? 'border-green-500' : 'border-red-500' }}">
            <p class="text-gray-500 text-sm font-medium">Safety Margin</p>
            <p class="text-2xl font-bold {{ $is_above_break_even ? 'text-green-600' : 'text-red-600' }} mt-1">{{ $safety_margin_percent }}%</p>
            <p class="text-xs text-gray-400 mt-1">${{ number_format(abs($safety_margin), 2) }} {{ $is_above_break_even ? 'above' : 'below' }}</p>
        </div>
    </div>

    <!-- Cost Breakdown (Fixed + Variable Details) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        {{-- Fixed Cost Breakdown --}}
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-orange-50">
                <h2 class="text-lg font-bold text-gray-900">Fixed Costs</h2>
                <p class="text-xs text-gray-500">Recurring monthly costs regardless of occupancy</p>
            </div>
            <div class="p-4">
                @if(!empty($fixed_cost_breakdown))
                    <div class="space-y-2">
                        @foreach($fixed_cost_breakdown as $item)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">{{ $item['label'] }}</span>
                                <span class="font-semibold text-gray-900">${{ number_format($item['amount'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400">No fixed costs recorded this month</p>
                @endif
                <div class="flex justify-between text-sm font-bold border-t mt-3 pt-3">
                    <span class="text-orange-700">Total Fixed Costs</span>
                    <span class="text-orange-700">${{ number_format($fixed_costs, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Variable Cost Breakdown --}}
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                <h2 class="text-lg font-bold text-gray-900">Variable Costs</h2>
                <p class="text-xs text-gray-500">Costs that change with occupancy (per unit)</p>
            </div>
            <div class="p-4">
                @if(!empty($variable_cost_breakdown))
                    <div class="space-y-2">
                        @foreach($variable_cost_breakdown as $item)
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">{{ $item['label'] }}</span>
                                <span class="font-semibold text-gray-900">${{ number_format($item['amount'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400">No variable costs recorded this month</p>
                @endif
                <div class="flex justify-between text-sm font-bold border-t mt-3 pt-3">
                    <span class="text-purple-700">Variable Cost / Unit</span>
                    <span class="text-purple-700">${{ number_format($variable_cost_per_unit, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Break-Even Formula -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-bold text-gray-900">Break-Even Calculation</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Item</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr>
                        <td class="px-6 py-3 text-sm text-gray-800">Fixed Costs (monthly)</td>
                        <td class="px-6 py-3 text-right font-semibold text-gray-900">${{ number_format($fixed_costs, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-3 text-sm text-gray-800">Avg Rent per Unit</td>
                        <td class="px-6 py-3 text-right font-semibold text-green-600">${{ number_format($avg_rent_per_apartment, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-3 text-sm text-gray-800">Variable Cost per Unit</td>
                        <td class="px-6 py-3 text-right font-semibold text-gray-900">${{ number_format($variable_cost_per_unit, 2) }}</td>
                    </tr>
                    <tr class="bg-blue-50">
                        <td class="px-6 py-3 text-sm font-bold text-blue-800">Contribution Margin (Rent − Variable)</td>
                        <td class="px-6 py-3 text-right font-bold text-blue-600">${{ number_format($contribution_margin_per_unit, 2) }}</td>
                    </tr>
                    <tr class="bg-yellow-50">
                        <td class="px-6 py-3 text-sm font-bold text-yellow-800">Break-Even Units (Fixed ÷ Margin)</td>
                        <td class="px-6 py-3 text-right font-bold text-yellow-600">{{ $break_even_units }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
