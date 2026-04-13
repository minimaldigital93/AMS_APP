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
    <div class="rounded-xl shadow-md p-6 mb-8 flex items-center justify-between {{ $is_above_break_even ? 'bg-gradient-to-r from-green-600 to-green-700' : 'bg-gradient-to-r from-red-600 to-red-700' }} text-white">
        <div>
            <p class="text-sm font-medium {{ $is_above_break_even ? 'text-green-200' : 'text-red-200' }}">Break-Even Status</p>
            <p class="text-2xl font-bold mt-1">
                @if($is_above_break_even)
                    Profitable — ${{ number_format($safety_margin, 2) }} above break-even
                @else
                    Not Profitable — need ${{ number_format(abs($safety_margin), 2) }} more to break even
                @endif
            </p>
            <p class="text-sm mt-1 {{ $is_above_break_even ? 'text-green-200' : 'text-red-200' }}">
                You need <strong>{{ $break_even_units }} units</strong> rented to break even. Currently <strong>{{ $current_occupancy }}/{{ $total_apartments }}</strong> occupied.
            </p>
        </div>
        <div class="text-6xl opacity-30">
            {{ $is_above_break_even ? '▲' : '▼' }}
        </div>
    </div>

    <!-- Key Numbers -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-yellow-500">
            <p class="text-gray-500 text-sm font-medium">Break-Even Point</p>
            <p class="text-2xl font-bold text-yellow-600 mt-1">{{ $break_even_units }} units</p>
            <p class="text-xs text-gray-400 mt-1">${{ number_format($break_even_revenue, 2) }}/month required</p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <p class="text-gray-500 text-sm font-medium">Current Revenue</p>
            <p class="text-2xl font-bold text-green-600 mt-1">${{ number_format($current_revenue, 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $current_occupancy }} units × ${{ number_format($avg_rent_per_apartment, 2) }} avg rent</p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 {{ $is_above_break_even ? 'border-green-500' : 'border-red-500' }}">
            <p class="text-gray-500 text-sm font-medium">Safety Margin</p>
            <p class="text-2xl font-bold {{ $is_above_break_even ? 'text-green-600' : 'text-red-600' }} mt-1">{{ $safety_margin_percent }}%</p>
            <p class="text-xs text-gray-400 mt-1">${{ number_format($safety_margin, 2) }} {{ $is_above_break_even ? 'above' : 'below' }} break-even</p>
        </div>
    </div>

    <!-- Revenue vs Break-Even Visual -->
    <div class="bg-white rounded-xl shadow-md p-6 mb-8">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Revenue vs Break-Even</h2>
        <div class="space-y-4">
            @php
                $maxVal = max($break_even_revenue, $current_revenue, 1);
                $bePercent = ($break_even_revenue / $maxVal) * 100;
                $crPercent = ($current_revenue / $maxVal) * 100;
            @endphp
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="font-medium text-gray-700">Break-Even Required</span>
                    <span class="font-bold text-yellow-600">${{ number_format($break_even_revenue, 2) }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-6 overflow-hidden">
                    <div class="bg-yellow-500 h-full rounded-full flex items-center justify-end pr-2 text-white text-xs font-bold" style="width: {{ $bePercent }}%">
                        {{ $break_even_units }} units
                    </div>
                </div>
            </div>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="font-medium text-gray-700">Your Revenue</span>
                    <span class="font-bold {{ $is_above_break_even ? 'text-green-600' : 'text-red-600' }}">${{ number_format($current_revenue, 2) }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-6 overflow-hidden">
                    <div class="{{ $is_above_break_even ? 'bg-green-500' : 'bg-red-500' }} h-full rounded-full flex items-center justify-end pr-2 text-white text-xs font-bold" style="width: {{ $crPercent }}%">
                        {{ $current_occupancy }} units
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cost Breakdown -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-bold text-gray-900">Cost Breakdown</h2>
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
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm text-gray-800">Fixed Costs (monthly)</td>
                        <td class="px-6 py-3 text-right font-semibold text-gray-900">${{ number_format($fixed_costs, 2) }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm text-gray-800">Variable Cost per Unit</td>
                        <td class="px-6 py-3 text-right font-semibold text-gray-900">${{ number_format($variable_cost_per_unit, 2) }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm text-gray-800">Avg Rent per Unit</td>
                        <td class="px-6 py-3 text-right font-semibold text-green-600">${{ number_format($avg_rent_per_apartment, 2) }}</td>
                    </tr>
                    <tr class="bg-blue-50">
                        <td class="px-6 py-3 text-sm font-bold text-blue-800">Contribution Margin per Unit</td>
                        <td class="px-6 py-3 text-right font-bold text-blue-600">${{ number_format($contribution_margin_per_unit, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 bg-gray-50 text-xs text-gray-500">
            Contribution Margin = Rent (${{ number_format($avg_rent_per_apartment, 2) }}) − Variable Cost (${{ number_format($variable_cost_per_unit, 2) }}) &nbsp;|&nbsp;
            Break-Even = Fixed Costs (${{ number_format($fixed_costs, 2) }}) ÷ Margin (${{ number_format($contribution_margin_per_unit, 2) }}) = <strong>{{ $break_even_units }} units</strong>
        </div>
    </div>
</div>
@endsection
