@extends('layouts.supervisor')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">

    @php
        $break_even_feasible = $break_even_feasible ?? true;
        $extraUnitProfit   = max(0, $contribution_margin_per_unit);
        $vacantUnits       = max(0, $total_apartments - $current_occupancy);
        $maxPossibleProfit = ($total_apartments * $avg_rent_per_apartment)
                           - ($total_apartments * $variable_cost_per_unit)
                           - $business_expenses;
        $unitsAhead        = $break_even_feasible
                             ? max(0, $current_occupancy - (int) ceil($break_even_units))
                             : 0;

        $totalVariableCost = $current_occupancy * $variable_cost_per_unit;
        $totalCosts        = $business_expenses + $totalVariableCost;
        $maxBar            = max($current_revenue, $totalCosts, 0.01);
        $revenuePct        = ($current_revenue / $maxBar) * 100;
        $bizExpensePct     = ($business_expenses / $maxBar) * 100;
        $varCostPct        = ($totalVariableCost / $maxBar) * 100;

        $breakEvenPct      = $total_apartments > 0 ? min(100, ($break_even_units / $total_apartments) * 100) : 0;
        $occupancyPct      = $total_apartments > 0 ? min(100, ($current_occupancy / $total_apartments) * 100) : 0;

        $rentBase          = max($avg_rent_per_apartment, 0.01);
        $costShare         = min(100, ($variable_cost_per_unit / $rentBase) * 100);
        $marginShare       = max(0, 100 - $costShare);

        $palette    = ['bg-orange-500','bg-amber-500','bg-rose-500','bg-pink-500','bg-fuchsia-500','bg-violet-500','bg-indigo-500','bg-sky-500'];
        $varPalette = ['bg-purple-500','bg-violet-500','bg-fuchsia-500','bg-pink-500','bg-indigo-500'];
        $bizTotal   = max(array_sum(array_column($business_expense_breakdown, 'amount')), 0.01);
        $varTotal   = max(array_sum(array_column($variable_cost_breakdown, 'amount')), 0.01);
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-slate-800 tracking-tight">Break-Even Analysis</h1>
        <a href="{{ route('supervisor.revenue_expense.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back
        </a>
    </div>

    {{-- Month navigation --}}
    <div class="bg-white rounded-2xl border border-slate-100 p-3 flex items-center justify-between gap-2">
        @if($hasPrev)
            <a href="{{ route('supervisor.revenue_expense.break_even', ['month' => $prevMonth, 'year' => $prevYear]) }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg transition"
               title="{{ \Carbon\Carbon::create($prevYear, $prevMonth, 1)->format('F Y') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                <span class="hidden sm:inline">{{ \Carbon\Carbon::create($prevYear, $prevMonth, 1)->format('M Y') }}</span>
            </a>
        @else
            <span class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-300 bg-slate-50 rounded-lg cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </span>
        @endif

        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="text-base font-bold text-slate-800">{{ $selectedDate->format('F Y') }}</span>
            @if($selectedMonth === now()->month && $selectedYear === now()->year)
                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 uppercase">Current</span>
            @endif
        </div>

        @if($hasNext)
            <a href="{{ route('supervisor.revenue_expense.break_even', ['month' => $nextMonth, 'year' => $nextYear]) }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg transition"
               title="{{ \Carbon\Carbon::create($nextYear, $nextMonth, 1)->format('F Y') }}">
                <span class="hidden sm:inline">{{ \Carbon\Carbon::create($nextYear, $nextMonth, 1)->format('M Y') }}</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        @else
            <span class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-300 bg-slate-50 rounded-lg cursor-not-allowed">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </span>
        @endif
    </div>

    {{-- Hero --}}
    <div class="rounded-2xl p-6 text-white {{ $is_above_break_even ? 'bg-gradient-to-br from-emerald-500 to-emerald-600' : 'bg-gradient-to-br from-red-500 to-rose-600' }}">
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="text-[11px] uppercase tracking-wider opacity-80">
                    {{ $is_above_break_even ? 'Profit this month' : 'Short this month' }}
                </div>
                <div class="text-4xl font-extrabold mt-1 leading-none">
                    {{ $is_above_break_even ? '+' : '−' }}${{ number_format(abs($is_above_break_even ? $safety_margin : $amount_needed), 2) }}
                </div>
                <div class="text-sm opacity-90 mt-2">
                    @if(!$break_even_feasible)
                        Variable cost per unit exceeds rent — break-even not reachable
                    @elseif($is_above_break_even)
                        {{ $unitsAhead }} unit(s) ahead of break-even
                    @else
                        {{ $units_needed }} more unit(s) to break even
                    @endif
                </div>
            </div>
            <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center text-3xl font-bold shrink-0">
                {{ $is_above_break_even ? '✓' : '!' }}
            </div>
        </div>
    </div>

    {{-- ── 3 Donut Charts ──────────────────────────────────── --}}
    @php
        // Occupancy donut
        $occColor    = $is_above_break_even ? '#10b981' : '#ef4444';
        $beAngle     = $break_even_feasible && $total_apartments > 0
                       ? min(360, ($break_even_units / $total_apartments) * 360)
                       : 0;

        // Money flow donut: slices sum to 100% of revenue (or costs if loss)
        $mfDenom     = $is_above_break_even ? max($current_revenue, 0.01) : max($totalCosts, 0.01);
        $mfBizPct    = ($business_expenses / $mfDenom) * 100;
        $mfVarPct    = ($totalVariableCost / $mfDenom) * 100;
        $mfNetPct    = $is_above_break_even ? max(0, 100 - $mfBizPct - $mfVarPct) : 0;
        $netAmount   = $current_revenue - $totalCosts;

        // Per-apartment donut
        $paCostPct   = min(100, ($variable_cost_per_unit / $rentBase) * 100);
        $paMarginPct = max(0, 100 - $paCostPct);
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        {{-- Occupancy Donut --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700 mb-3 text-center">Occupancy vs Break-Even</p>

            <div class="relative w-40 h-40 mx-auto">
                <svg viewBox="0 0 36 36" class="w-full h-full">
                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#e2e8f0" stroke-width="3.8"/>
                    <circle cx="18" cy="18" r="15.9155" fill="none"
                            stroke="{{ $occColor }}" stroke-width="3.8"
                            stroke-dasharray="{{ $occupancyPct }} 100"
                            transform="rotate(-90 18 18)" stroke-linecap="butt"/>
                    {{-- Break-even tick (hidden when not feasible) --}}
                    @if($break_even_feasible)
                        <line x1="18" y1="0.3" x2="18" y2="3.9"
                              stroke="#f59e0b" stroke-width="1.2"
                              transform="rotate({{ $beAngle }} 18 18)"/>
                    @endif
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <div class="text-3xl font-extrabold {{ $is_above_break_even ? 'text-emerald-600' : 'text-red-500' }} leading-none">
                        {{ $current_occupancy }}
                    </div>
                    <div class="text-[10px] text-slate-500 uppercase mt-1">of {{ $total_apartments }}</div>
                </div>
            </div>

            <div class="mt-4 space-y-1.5 text-xs">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-sm" style="background:{{ $occColor }}"></span>
                    <span class="text-slate-600 flex-1">Rented</span>
                    <span class="font-semibold text-slate-700">{{ $current_occupancy }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-sm bg-slate-200"></span>
                    <span class="text-slate-600 flex-1">Vacant</span>
                    <span class="font-semibold text-slate-700">{{ $vacantUnits }}</span>
                </div>
                <div class="flex items-center gap-2 pt-1.5 border-t border-slate-100">
                    <span class="w-2.5 h-0.5 bg-amber-500"></span>
                    <span class="text-slate-600 flex-1">Break-even</span>
                    <span class="font-semibold text-amber-600">
                        {{ $break_even_feasible ? $break_even_units : '—' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Money Flow Donut --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700 mb-3 text-center">Money Flow This Month</p>

            <div class="relative w-40 h-40 mx-auto">
                <svg viewBox="0 0 36 36" class="w-full h-full">
                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#e2e8f0" stroke-width="3.8"/>
                    {{-- Business expense slice --}}
                    <circle cx="18" cy="18" r="15.9155" fill="none"
                            stroke="#f97316" stroke-width="3.8"
                            stroke-dasharray="{{ $mfBizPct }} 100"
                            stroke-dashoffset="0"
                            transform="rotate(-90 18 18)" stroke-linecap="butt"/>
                    {{-- Variable cost slice --}}
                    <circle cx="18" cy="18" r="15.9155" fill="none"
                            stroke="#a855f7" stroke-width="3.8"
                            stroke-dasharray="{{ $mfVarPct }} 100"
                            stroke-dashoffset="{{ -$mfBizPct }}"
                            transform="rotate(-90 18 18)" stroke-linecap="butt"/>
                    {{-- Profit slice (only if above break-even) --}}
                    @if($mfNetPct > 0)
                        <circle cx="18" cy="18" r="15.9155" fill="none"
                                stroke="#10b981" stroke-width="3.8"
                                stroke-dasharray="{{ $mfNetPct }} 100"
                                stroke-dashoffset="{{ -($mfBizPct + $mfVarPct) }}"
                                transform="rotate(-90 18 18)" stroke-linecap="butt"/>
                    @endif
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <div class="text-[10px] text-slate-500 uppercase">Net</div>
                    <div class="text-xl font-extrabold {{ $netAmount >= 0 ? 'text-emerald-600' : 'text-red-500' }} leading-none mt-0.5">
                        {{ $netAmount >= 0 ? '+' : '−' }}${{ number_format(abs($netAmount), 0) }}
                    </div>
                </div>
            </div>

            <div class="mt-4 space-y-1.5 text-xs">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-sm bg-orange-500"></span>
                    <span class="text-slate-600 flex-1">Business</span>
                    <span class="font-semibold text-slate-700">${{ number_format($business_expenses, 0) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-sm bg-purple-500"></span>
                    <span class="text-slate-600 flex-1">Variable</span>
                    <span class="font-semibold text-slate-700">${{ number_format($totalVariableCost, 0) }}</span>
                </div>
                <div class="flex items-center gap-2 pt-1.5 border-t border-slate-100">
                    <span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span>
                    <span class="text-slate-600 flex-1">Revenue</span>
                    <span class="font-semibold text-emerald-700">${{ number_format($current_revenue, 0) }}</span>
                </div>
            </div>
        </div>

        {{-- Per-Apartment Donut --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700 mb-3 text-center">Each Apartment Contributes</p>

            <div class="relative w-40 h-40 mx-auto">
                <svg viewBox="0 0 36 36" class="w-full h-full">
                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#e2e8f0" stroke-width="3.8"/>
                    {{-- Variable cost slice --}}
                    <circle cx="18" cy="18" r="15.9155" fill="none"
                            stroke="#a855f7" stroke-width="3.8"
                            stroke-dasharray="{{ $paCostPct }} 100"
                            stroke-dashoffset="0"
                            transform="rotate(-90 18 18)" stroke-linecap="butt"/>
                    {{-- Margin slice --}}
                    <circle cx="18" cy="18" r="15.9155" fill="none"
                            stroke="#0ea5e9" stroke-width="3.8"
                            stroke-dasharray="{{ $paMarginPct }} 100"
                            stroke-dashoffset="{{ -$paCostPct }}"
                            transform="rotate(-90 18 18)" stroke-linecap="butt"/>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <div class="text-[10px] text-slate-500 uppercase">Margin</div>
                    <div class="text-xl font-extrabold text-sky-600 leading-none mt-0.5">
                        ${{ number_format($contribution_margin_per_unit, 0) }}
                    </div>
                </div>
            </div>

            <div class="mt-4 space-y-1.5 text-xs">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span>
                    <span class="text-slate-600 flex-1">Rent</span>
                    <span class="font-semibold text-emerald-700">${{ number_format($avg_rent_per_apartment, 2) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-sm bg-purple-500"></span>
                    <span class="text-slate-600 flex-1">Variable cost</span>
                    <span class="font-semibold text-purple-700">${{ number_format($variable_cost_per_unit, 2) }}</span>
                </div>
                <div class="flex items-center gap-2 pt-1.5 border-t border-slate-100">
                    <span class="w-2.5 h-2.5 rounded-sm bg-sky-500"></span>
                    <span class="text-slate-600 flex-1">Margin</span>
                    <span class="font-semibold text-sky-700">${{ number_format($contribution_margin_per_unit, 2) }}</span>
                </div>
            </div>
        </div>

    </div>

    {{-- What If --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-xl border border-slate-100 p-4 text-center">
            <div class="text-emerald-500 text-2xl leading-none">↑</div>
            <div class="text-[10px] text-slate-500 uppercase mt-1">+1 Unit</div>
            <div class="text-base font-extrabold text-emerald-600 mt-1">+${{ number_format($extraUnitProfit, 2) }}</div>
            @if($vacantUnits > 0)
                <div class="text-[10px] text-slate-400 mt-0.5">{{ $vacantUnits }} vacant</div>
            @endif
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 text-center">
            <div class="text-rose-500 text-2xl leading-none">↓</div>
            <div class="text-[10px] text-slate-500 uppercase mt-1">−1 Unit</div>
            <div class="text-base font-extrabold text-rose-500 mt-1">−${{ number_format($extraUnitProfit, 2) }}</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 text-center">
            <div class="text-sky-500 text-2xl leading-none">★</div>
            <div class="text-[10px] text-slate-500 uppercase mt-1">Full</div>
            <div class="text-base font-extrabold {{ $maxPossibleProfit >= 0 ? 'text-emerald-600' : 'text-rose-500' }} mt-1">
                {{ $maxPossibleProfit >= 0 ? '+' : '−' }}${{ number_format(abs($maxPossibleProfit), 2) }}
            </div>
        </div>
    </div>

    {{-- Cost Breakdown --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Business Expenses --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-semibold text-slate-700">Business Expenses</p>
                <p class="text-sm font-bold text-orange-500">${{ number_format($business_expenses, 2) }}</p>
            </div>
            @if(count($business_expense_breakdown) > 0)
                <div class="h-3 bg-slate-100 rounded-full overflow-hidden flex mb-3">
                    @foreach($business_expense_breakdown as $i => $item)
                        @php $w = ($item['amount'] / $bizTotal) * 100; @endphp
                        <div class="{{ $palette[$i % count($palette)] }} h-full" style="width: {{ $w }}%"></div>
                    @endforeach
                </div>
                <div class="space-y-1.5">
                    @foreach($business_expense_breakdown as $i => $item)
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-2 h-2 rounded-full {{ $palette[$i % count($palette)] }} shrink-0"></span>
                            <span class="text-slate-600 flex-1 truncate">{{ $item['label'] }}</span>
                            <span class="font-medium text-slate-700">${{ number_format($item['amount'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-slate-400 italic">None recorded this month</p>
            @endif
        </div>

        {{-- Variable Costs --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-semibold text-slate-700">Per-Apartment Costs</p>
                <p class="text-sm font-bold text-purple-500">${{ number_format($variable_cost_per_unit, 2) }}<span class="text-[10px] text-slate-400 font-normal">/apt</span></p>
            </div>
            @if(count($variable_cost_breakdown) > 0)
                <div class="h-3 bg-slate-100 rounded-full overflow-hidden flex mb-3">
                    @foreach($variable_cost_breakdown as $i => $item)
                        @php $w = ($item['amount'] / $varTotal) * 100; @endphp
                        <div class="{{ $varPalette[$i % count($varPalette)] }} h-full" style="width: {{ $w }}%"></div>
                    @endforeach
                </div>
                <div class="space-y-1.5">
                    @foreach($variable_cost_breakdown as $i => $item)
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-2 h-2 rounded-full {{ $varPalette[$i % count($varPalette)] }} shrink-0"></span>
                            <span class="text-slate-600 flex-1 truncate">{{ $item['label'] }}</span>
                            <span class="font-medium text-slate-700">${{ number_format($item['amount'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-slate-400 italic">None recorded</p>
            @endif
        </div>
    </div>

</div>
@endsection
