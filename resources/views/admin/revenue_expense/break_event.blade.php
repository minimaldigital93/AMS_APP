@extends('layouts.admin')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">Break-Even Analysis</h1>
            <p class="text-slate-500 mt-2">How many units you need to rent to cover all costs</p>
        </div>
        <a href="{{ route('admin.revenue_expense.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700 transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back
        </a>
    </div>

    <!-- Status Banner -->
    <div class="rounded-xl p-6 {{ $is_above_break_even ? 'bg-gradient-to-r from-emerald-600 to-emerald-700' : 'bg-gradient-to-r from-red-600 to-red-700' }} text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium {{ $is_above_break_even ? 'text-emerald-200' : 'text-red-200' }}">Break-Even Status</p>
                <p class="text-2xl font-bold mt-1">
                    @if($is_above_break_even)
                        ✓ Profitable
                    @else
                        ✗ Not Yet Profitable
                    @endif
                </p>
                <p class="text-sm mt-2 {{ $is_above_break_even ? 'text-emerald-200' : 'text-red-200' }}">
                    Need <strong>{{ $break_even_units }} units</strong> rented to break even. Currently <strong>{{ $current_occupancy }}/{{ $total_apartments }}</strong> occupied.
                </p>
            </div>
            <div class="text-right">
                @if(!$is_above_break_even)
                    <p class="text-sm text-red-200">Amount needed</p>
                    <p class="text-3xl font-bold">${{ number_format($amount_needed, 2) }}</p>
                    <p class="text-sm text-red-200">{{ $units_needed }} more unit(s)</p>
                @else
                    <p class="text-sm text-emerald-200">Surplus</p>
                    <p class="text-3xl font-bold">${{ number_format($safety_margin, 2) }}</p>
                    <p class="text-sm text-emerald-200">above break-even</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Key Numbers -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Break-Even Point</p>
                    <p class="text-xl font-bold text-amber-600">{{ $break_even_units }} units</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">${{ number_format($break_even_revenue, 2) }} required</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Current Revenue</p>
                    <p class="text-xl font-bold text-emerald-600">${{ number_format($current_revenue, 2) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ $current_occupancy }} units occupied</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Total Fixed Costs</p>
                    <p class="text-xl font-bold text-orange-600">${{ number_format($fixed_costs, 2) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">monthly recurring</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg {{ $is_above_break_even ? 'bg-emerald-50' : 'bg-red-50' }} flex items-center justify-center">
                    <svg class="w-5 h-5 {{ $is_above_break_even ? 'text-emerald-600' : 'text-red-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Safety Margin</p>
                    <p class="text-xl font-bold {{ $is_above_break_even ? 'text-emerald-600' : 'text-red-600' }}">{{ $safety_margin_percent }}%</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">${{ number_format(abs($safety_margin), 2) }} {{ $is_above_break_even ? 'above' : 'below' }}</p>
        </div>
    </div>

    <!-- Cost Breakdown (Fixed + Variable Details) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Fixed Cost Breakdown --}}
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-orange-50">
                <h2 class="text-lg font-semibold text-slate-800">Fixed Costs</h2>
                <p class="text-xs text-slate-400">Recurring monthly costs regardless of occupancy</p>
            </div>
            <div class="p-4">
                @if(!empty($fixed_cost_breakdown))
                    <div class="space-y-2">
                        @foreach($fixed_cost_breakdown as $item)
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-500">{{ $item['label'] }}</span>
                                <span class="font-semibold text-slate-800">${{ number_format($item['amount'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-400">No fixed costs recorded this month</p>
                @endif
                <div class="flex justify-between text-sm font-bold border-t border-slate-100 mt-3 pt-3">
                    <span class="text-orange-700">Total Fixed Costs</span>
                    <span class="text-orange-700">${{ number_format($fixed_costs, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Variable Cost Breakdown --}}
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-purple-50">
                <h2 class="text-lg font-semibold text-slate-800">Variable Costs</h2>
                <p class="text-xs text-slate-400">Costs that change with occupancy (per unit)</p>
            </div>
            <div class="p-4">
                @if(!empty($variable_cost_breakdown))
                    <div class="space-y-2">
                        @foreach($variable_cost_breakdown as $item)
                            <div class="flex justify-between text-sm">
                                <span class="text-slate-500">{{ $item['label'] }}</span>
                                <span class="font-semibold text-slate-800">${{ number_format($item['amount'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-400">No variable costs recorded this month</p>
                @endif
                <div class="flex justify-between text-sm font-bold border-t border-slate-100 mt-3 pt-3">
                    <span class="text-purple-700">Variable Cost / Unit</span>
                    <span class="text-purple-700">${{ number_format($variable_cost_per_unit, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Break-Even Formula -->
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-lg font-semibold text-slate-800">Break-Even Calculation</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50/80">
                    <tr>
                        <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">Item</th>
                        <th class="px-6 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <tr>
                        <td class="px-6 py-3 text-sm text-slate-700">Fixed Costs (monthly)</td>
                        <td class="px-6 py-3 text-right font-semibold text-slate-800">${{ number_format($fixed_costs, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-3 text-sm text-slate-700">Avg Rent per Unit</td>
                        <td class="px-6 py-3 text-right font-semibold text-emerald-600">${{ number_format($avg_rent_per_apartment, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="px-6 py-3 text-sm text-slate-700">Variable Cost per Unit</td>
                        <td class="px-6 py-3 text-right font-semibold text-slate-800">${{ number_format($variable_cost_per_unit, 2) }}</td>
                    </tr>
                    <tr class="bg-sky-50/50">
                        <td class="px-6 py-3 text-sm font-bold text-sky-800">Contribution Margin (Rent − Variable)</td>
                        <td class="px-6 py-3 text-right font-bold text-sky-600">${{ number_format($contribution_margin_per_unit, 2) }}</td>
                    </tr>
                    <tr class="bg-amber-50/50">
                        <td class="px-6 py-3 text-sm font-bold text-amber-800">Break-Even Units (Fixed ÷ Margin)</td>
                        <td class="px-6 py-3 text-right font-bold text-amber-600">{{ $break_even_units }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
