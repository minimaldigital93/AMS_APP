@extends('layouts.'.$panel)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

    @php
        $vacantUnits  = max(0, $total_apartments - $current_occupancy);
        $occupancyPct = $total_apartments > 0 ? ($current_occupancy / $total_apartments) * 100 : 0;

        $util     = $utility_analysis ?? ['total' => 0, 'avg_per_room' => 0, 'rooms_used' => 0, 'by_type' => [], 'top_apartment' => null];
        $utilByType = $util['by_type'] ?? [];
        $utilTotal  = max($util['total'] ?? 0, 0.01);

        $palette = ['#6366f1','#0ea5e9','#14b8a6','#f59e0b','#f43f5e','#8b5cf6','#f97316','#ec4899'];
    @endphp

    {{-- Header --}}
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.break_even') }}</h1>
        <a href="{{ route($panel.'.revenue_expense.index') }}" class="inline-flex items-center justify-center h-10 w-10 bg-slate-800 hover:bg-slate-700 text-white rounded-lg transition flex-shrink-0" title="{{ __('messages.back') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
    </div>

    {{-- Month navigation --}}
    <div class="flex flex-wrap items-center justify-center gap-3">
        <div class="inline-flex items-center bg-white rounded-xl border border-slate-100 px-2 py-1.5 gap-1">
            @if($hasPrev)
                <a href="{{ route($panel.'.revenue_expense.break_even', ['month' => $prevMonth, 'year' => $prevYear]) }}"
                   class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ __('messages.previous_month') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @else
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-200 cursor-not-allowed">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </span>
            @endif

            <div class="px-4 py-2 min-w-[180px] text-center">
                <span class="text-lg font-bold text-slate-800">{{ $selectedDate->format('F') }}</span>
                <span class="text-lg text-slate-400 ml-1">{{ $selectedDate->format('Y') }}</span>
                @if($selectedMonth === now()->month && $selectedYear === now()->year)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">{{ __('messages.current') }}</span>
                @endif
            </div>

            @if($hasNext)
                <a href="{{ route($panel.'.revenue_expense.break_even', ['month' => $nextMonth, 'year' => $nextYear]) }}"
                   class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ __('messages.next_month') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-200 cursor-not-allowed">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            @endif
        </div>
    </div>

    {{-- ── Headline: revenue vs expense ─────────────────────── --}}
    <div class="rounded-2xl p-6 text-white {{ $is_above_break_even ? 'bg-gradient-to-br from-emerald-500 to-emerald-600' : 'bg-gradient-to-br from-rose-500 to-red-600' }}">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center shrink-0">
                @if($is_above_break_even)
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941"/></svg>
                @else
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                @endif
            </div>
            <div>
                <div class="text-lg font-bold leading-tight">
                    {{ $is_above_break_even ? "Yes — you're making money" : "Not yet — you're short" }}
                </div>
                <div class="text-3xl font-extrabold mt-1 leading-none">
                    {{ $is_above_break_even ? '+' : '−' }}{{ money(abs($is_above_break_even ? $safety_margin : $amount_needed), 0) }}
                    <span class="text-sm font-medium opacity-80">{{ __('messages.this_month') }}</span>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 mt-5">
            <div class="rounded-xl bg-white/15 px-4 py-3">
                <div class="text-xs opacity-80">{{ __('messages.money_in_rent') }}</div>
                <div class="text-xl font-bold mt-0.5">{{ money($current_revenue, 0) }}</div>
            </div>
            <div class="rounded-xl bg-white/15 px-4 py-3">
                <div class="text-xs opacity-80">{{ __('messages.money_out_costs') }}</div>
                <div class="text-xl font-bold mt-0.5">{{ money($total_expenses, 0) }}</div>
            </div>
        </div>
    </div>

    {{-- ── Two donuts: occupancy + utilities ────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Occupancy: exist vs rented --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700">{{ __('messages.apartments_rented') }}</p>
            <p class="text-[11px] text-slate-400">{{ __('messages.units_bringing_rent') }}</p>

            <div class="relative w-40 h-40 mx-auto my-4">
                <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                    <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#f1f5f9" stroke-width="3.8"/>
                    @if($total_apartments > 0)
                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="{{ $is_above_break_even ? '#10b981' : '#f43f5e' }}"
                                stroke-width="3.8" stroke-linecap="round"
                                stroke-dasharray="{{ $occupancyPct }} {{ 100 - $occupancyPct }}"/>
                    @endif
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-3xl font-extrabold text-slate-800 leading-none">{{ $current_occupancy }}</span>
                    <span class="text-xs text-slate-400">{{ __('messages.of_rented', ['total' => $total_apartments]) }}</span>
                </div>
            </div>

            <div class="flex items-center justify-center gap-4 text-xs">
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full {{ $is_above_break_even ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                    <span class="text-slate-600">{{ __('messages.rented_word') }} {{ $current_occupancy }}</span>
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-200"></span>
                    <span class="text-slate-600">{{ __('messages.empty_word') }} {{ $vacantUnits }}</span>
                </span>
            </div>

            @if($break_even_feasible)
                <p class="text-xs text-center text-slate-500 mt-4 pt-4 border-t border-slate-100">
                    @if($is_above_break_even)
                        {{ __('messages.covering_costs_at_pre') }} <span class="font-semibold text-emerald-600">{{ $break_even_units }}</span> {{ __('messages.covering_costs_at_post') }}
                    @else
                        {{ __('messages.rent_more_pre') }} <span class="font-semibold text-rose-600">{{ $units_needed }}</span> {{ __('messages.rent_more_post') }}
                    @endif
                </p>
            @endif
        </div>

        {{-- Utilities by type --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700">{{ __('messages.utility_money_goes') }}</p>
            <p class="text-[11px] text-slate-400">{{ __('messages.total_billed_rooms') }}</p>

            @if(count($utilByType) > 0)
                <div class="relative w-40 h-40 mx-auto my-4">
                    <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="#f1f5f9" stroke-width="3.8"/>
                        @php $offset = 0; @endphp
                        @foreach($utilByType as $i => $item)
                            @php $pct = ($item['amount'] / $utilTotal) * 100; @endphp
                            <circle cx="18" cy="18" r="15.9155" fill="none"
                                    stroke="{{ $palette[$i % count($palette)] }}" stroke-width="3.8"
                                    stroke-dasharray="{{ $pct }} {{ 100 - $pct }}"
                                    stroke-dashoffset="{{ -$offset }}"/>
                            @php $offset += $pct; @endphp
                        @endforeach
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-2xl font-extrabold text-slate-800 leading-none">{{ money($util['total'], 0) }}</span>
                        <span class="text-xs text-slate-400">{{ __('messages.total') }}</span>
                    </div>
                </div>

                <div class="space-y-1.5">
                    @foreach($utilByType as $i => $item)
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: {{ $palette[$i % count($palette)] }}"></span>
                            <span class="text-slate-600 flex-1 truncate">{{ $item['label'] }}</span>
                            <span class="font-medium text-slate-700">{{ money($item['amount'], 0) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <svg class="w-8 h-8 text-slate-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18"/></svg>
                    <p class="text-sm text-slate-400">{{ __('messages.no_utility_bills') }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Quick insights ───────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        {{-- Average utilities per room --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <div class="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-sky-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7Z"/></svg>
            </div>
            <p class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">{{ __('messages.avg_utilities_room') }}</p>
            <p class="text-2xl font-extrabold text-slate-800 mt-1">{{ money($util['avg_per_room'], 0) }}</p>
            <p class="text-[11px] text-slate-400 mt-1">{{ __('messages.across_rooms_used', ['count' => $util['rooms_used']]) }}</p>
        </div>

        {{-- Most utility-hungry apartment --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
            </div>
            <p class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">{{ __('messages.highest_utility_room') }}</p>
            @if($util['top_apartment'])
                <p class="text-2xl font-extrabold text-slate-800 mt-1">{{ __('messages.apt_short') }} {{ $util['top_apartment']['label'] }}</p>
                <p class="text-[11px] text-slate-400 mt-1">{{ money($util['top_apartment']['amount'], 0) }} {{ __('messages.in_utilities') }}</p>
            @else
                <p class="text-2xl font-extrabold text-slate-300 mt-1">—</p>
                <p class="text-[11px] text-slate-400 mt-1">{{ __('messages.no_data_yet') }}</p>
            @endif
        </div>

        {{-- Biggest expense to cut --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center mb-3">
                <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.848 8.25 9.384 9.137m-1.536-.887a3 3 0 1 1-5.196-3 3 3 0 0 1 5.196 3Zm1.536.887a2.165 2.165 0 0 1 1.083 1.839c.005.351.054.695.14 1.024m-1.223-2.863 2.077 1.199M7.848 15.75l1.536-.887m-1.536.887a3 3 0 1 1-5.196 3 3 3 0 0 1 5.196-3Zm1.536-.887a2.165 2.165 0 0 0 1.083-1.838c.005-.352.054-.695.14-1.025m-1.223 2.863 2.077-1.199m0-3.328a4.323 4.323 0 0 1 2.068-1.379l5.325-1.628a4.5 4.5 0 0 1 2.48-.044l.803.215-7.794 4.5m-2.882-1.664A4.331 4.331 0 0 0 10.607 12m3.736 0 7.794 4.5-.802.215a4.5 4.5 0 0 1-2.48-.043l-5.326-1.629a4.324 4.324 0 0 1-2.068-1.379M14.343 12l-2.882 1.664"/></svg>
            </div>
            <p class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">{{ __('messages.biggest_cost_cut') }}</p>
            @if($biggest_expense)
                <p class="text-2xl font-extrabold text-slate-800 mt-1 truncate" title="{{ $biggest_expense['label'] }}">{{ $biggest_expense['label'] }}</p>
                <p class="text-[11px] text-slate-400 mt-1">{{ money($biggest_expense['amount'], 0) }} {{ __('messages.this_month') }}</p>
            @else
                <p class="text-2xl font-extrabold text-slate-300 mt-1">—</p>
                <p class="text-[11px] text-slate-400 mt-1">{{ __('messages.no_expenses_yet') }}</p>
            @endif
        </div>
    </div>

    {{-- ── Business health: charts only ─────────────────────── --}}
    @php
        $healthLabels = [
            'occupancy' => __('messages.occupancy'),
            'profitability' => __('messages.profitability'),
            'break_even_coverage' => __('messages.be_coverage'),
            'cost_efficiency' => __('messages.cost_efficiency'),
            'collection' => __('messages.collection_rate'),
        ];
        $mixLabels = [
            'rent_income' => __('messages.rent_income'),
            'utilities' => __('messages.utilities'),
            'late_fees' => __('messages.late_fees'),
            'deposit' => __('messages.deposit'),
            'fixed_expenses' => __('messages.fixed_expenses'),
            'variable_expenses' => __('messages.variable_expenses'),
            'deposit_refunds' => __('messages.deposit_refunds'),
            'other' => __('messages.other'),
        ];
        $revenueMix = collect($health['revenue_mix'])->mapWithKeys(fn ($v, $k) => [($mixLabels[$k] ?? $k) => $v]);
        $expenseMix = collect($health['expense_mix'])->mapWithKeys(fn ($v, $k) => [($mixLabels[$k] ?? $k) => $v]);
        $overallScore = $health['scores']['overall'];
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Overall health gauge --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700">{{ __('messages.business_health') }}</p>
            <p class="text-[11px] text-slate-400">{{ __('messages.overall_score') }}</p>
            <div class="relative h-40 mt-2">
                <canvas id="healthGauge"></canvas>
                <div class="absolute inset-x-0 bottom-3 flex flex-col items-center pointer-events-none">
                    <span class="text-4xl font-extrabold leading-none {{ $overallScore >= 70 ? 'text-emerald-600' : ($overallScore >= 40 ? 'text-amber-500' : 'text-rose-600') }}">{{ $overallScore }}</span>
                    <span class="text-xs text-slate-400">/ 100</span>
                </div>
            </div>
            <div class="grid grid-cols-5 gap-1 mt-4 pt-4 border-t border-slate-100">
                @foreach($healthLabels as $key => $label)
                    <div class="text-center">
                        <div class="text-sm font-bold {{ $health['scores'][$key] >= 70 ? 'text-emerald-600' : ($health['scores'][$key] >= 40 ? 'text-amber-500' : 'text-rose-600') }}">{{ $health['scores'][$key] }}</div>
                        <div class="text-[10px] text-slate-400 leading-tight">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Health radar --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700">{{ __('messages.health_factors') }}</p>
            <div class="h-60 mt-2"><canvas id="healthRadar"></canvas></div>
        </div>
    </div>

    {{-- 6-month trend --}}
    <div class="bg-white rounded-2xl border border-slate-100 p-5">
        <p class="text-sm font-semibold text-slate-700">{{ __('messages.six_month_trend') }}</p>
        <div class="h-64 mt-3"><canvas id="healthTrend"></canvas></div>
    </div>

    {{-- Break-even chart --}}
    @if($break_even_feasible && $avg_rent_per_apartment > 0)
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700">{{ __('messages.break_even_chart') }}</p>
            <div class="h-64 mt-3"><canvas id="breakEvenChart"></canvas></div>
        </div>
    @endif

    {{-- Revenue mix + cost structure --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700">{{ __('messages.revenue_mix') }}</p>
            @if($revenueMix->isNotEmpty())
                <div class="h-52 mt-3"><canvas id="revenueMixChart"></canvas></div>
            @else
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <svg class="w-8 h-8 text-slate-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                    <p class="text-sm text-slate-400">{{ __('messages.no_data_yet') }}</p>
                </div>
            @endif
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700">{{ __('messages.cost_structure') }}</p>
            @if($expenseMix->isNotEmpty())
                <div class="h-52 mt-3"><canvas id="expenseMixChart"></canvas></div>
            @else
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <svg class="w-8 h-8 text-slate-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                    <p class="text-sm text-slate-400">{{ __('messages.no_data_yet') }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Per-unit economics --}}
    <div class="bg-white rounded-2xl border border-slate-100 p-5">
        <p class="text-sm font-semibold text-slate-700">{{ __('messages.per_unit_economics') }}</p>
        <div class="h-44 mt-3"><canvas id="unitEconomicsChart"></canvas></div>
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function mk(id, cfg) {
        var el = document.getElementById(id);
        if (el) new Chart(el, cfg);
    }
    function money(v) { return '$' + Number(v).toLocaleString(); }

    // ── Overall gauge ──
    var overall = {{ (int) $overallScore }};
    var gaugeColor = overall >= 70 ? '#10b981' : (overall >= 40 ? '#f59e0b' : '#f43f5e');
    mk('healthGauge', {
        type: 'doughnut',
        data: { datasets: [{ data: [overall, 100 - overall], backgroundColor: [gaugeColor, '#f1f5f9'], borderWidth: 0 }] },
        options: {
            rotation: -90, circumference: 180, cutout: '72%',
            maintainAspectRatio: false, responsive: true,
            plugins: { legend: { display: false }, tooltip: { enabled: false } }
        }
    });

    // ── Radar: health factors ──
    mk('healthRadar', {
        type: 'radar',
        data: {
            labels: {!! json_encode(array_values($healthLabels)) !!},
            datasets: [{
                data: {!! json_encode(array_map(fn ($k) => $health['scores'][$k], array_keys($healthLabels))) !!},
                backgroundColor: 'rgba(99,102,241,.18)', borderColor: '#6366f1',
                pointBackgroundColor: '#6366f1', borderWidth: 2
            }]
        },
        options: {
            maintainAspectRatio: false, responsive: true,
            scales: { r: {
                min: 0, max: 100,
                ticks: { stepSize: 25, backdropColor: 'transparent', color: '#94a3b8', font: { size: 9 } },
                pointLabels: { color: '#475569', font: { size: 10 } },
                grid: { color: '#e2e8f0' }
            } },
            plugins: { legend: { display: false } }
        }
    });

    // ── 6-month trend: revenue / expenses bars + net & occupancy lines ──
    var trend = {!! json_encode($health['trend']) !!};
    mk('healthTrend', {
        data: {
            labels: trend.map(function (m) { return m.label; }),
            datasets: [
                { type: 'bar', label: {!! json_encode(__('messages.revenue')) !!}, data: trend.map(function (m) { return m.revenue; }), backgroundColor: '#10b981', borderRadius: 6, maxBarThickness: 26 },
                { type: 'bar', label: {!! json_encode(__('messages.expenses_word')) !!}, data: trend.map(function (m) { return m.expenses; }), backgroundColor: '#f43f5e', borderRadius: 6, maxBarThickness: 26 },
                { type: 'line', label: {!! json_encode(__('messages.net')) !!}, data: trend.map(function (m) { return m.net; }), borderColor: '#6366f1', backgroundColor: '#6366f1', tension: .35, pointRadius: 3 },
                { type: 'line', label: {!! json_encode(__('messages.occupancy_rate')) !!}, data: trend.map(function (m) { return m.occupancy_pct; }), borderColor: '#f59e0b', backgroundColor: '#f59e0b', borderDash: [5, 4], tension: .35, pointRadius: 3, yAxisID: 'y1' }
            ]
        },
        options: {
            maintainAspectRatio: false, responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { ticks: { callback: money, color: '#94a3b8', font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                y1: { position: 'right', min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; }, color: '#f59e0b', font: { size: 10 } }, grid: { drawOnChartArea: false } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 } } }
            },
            plugins: { legend: { labels: { boxWidth: 10, font: { size: 10 } } } }
        }
    });

    // ── Classic break-even chart: revenue vs total cost over occupied units ──
    var avgRent = {{ (float) $avg_rent_per_apartment }};
    var varCost = {{ (float) $variable_cost_per_unit }};
    var fixedCost = {{ (float) $business_expenses }};
    var beUnits = {{ (float) $break_even_units }};
    var occupied = {{ (int) $current_occupancy }};
    var totalUnits = {{ (int) $total_apartments }};
    var maxU = Math.max(totalUnits, Math.ceil(beUnits) + 1, occupied, 1);
    var revLine = [], costLine = [];
    for (var u = 0; u <= maxU; u++) {
        revLine.push({ x: u, y: u * avgRent });
        costLine.push({ x: u, y: fixedCost + u * varCost });
    }
    mk('breakEvenChart', {
        type: 'line',
        data: { datasets: [
            { label: {!! json_encode(__('messages.revenue')) !!}, data: revLine, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.08)', fill: true, pointRadius: 0, borderWidth: 2 },
            { label: {!! json_encode(__('messages.total_cost')) !!}, data: costLine, borderColor: '#f43f5e', pointRadius: 0, borderWidth: 2 },
            { label: {!! json_encode(__('messages.break_even')) !!}, data: [{ x: beUnits, y: beUnits * avgRent }], borderColor: '#6366f1', backgroundColor: '#6366f1', pointRadius: 6, pointStyle: 'rectRot', showLine: false },
            { label: {!! json_encode(__('messages.current')) !!}, data: [{ x: occupied, y: occupied * avgRent }], borderColor: '#0ea5e9', backgroundColor: '#0ea5e9', pointRadius: 6, showLine: false }
        ] },
        options: {
            maintainAspectRatio: false, responsive: true,
            scales: {
                x: { type: 'linear', min: 0, max: maxU, title: { display: true, text: {!! json_encode(__('messages.unit')) !!}, color: '#94a3b8', font: { size: 10 } }, ticks: { stepSize: 1, color: '#94a3b8', font: { size: 10 } }, grid: { display: false } },
                y: { ticks: { callback: money, color: '#94a3b8', font: { size: 10 } }, grid: { color: '#f1f5f9' } }
            },
            plugins: { legend: { labels: { boxWidth: 10, font: { size: 10 } } } }
        }
    });

    // ── Revenue mix / cost structure donuts ──
    function donut(id, mix, palette) {
        var labels = Object.keys(mix);
        if (!labels.length) return;
        mk(id, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: labels.map(function (k) { return mix[k]; }), backgroundColor: palette, borderWidth: 0 }] },
            options: {
                cutout: '62%', maintainAspectRatio: false, responsive: true,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } },
                    tooltip: { callbacks: { label: function (c) { return c.label + ': ' + money(c.parsed); } } }
                }
            }
        });
    }
    donut('revenueMixChart', {!! json_encode($revenueMix) !!}, ['#10b981', '#0ea5e9', '#f59e0b', '#8b5cf6', '#64748b']);
    donut('expenseMixChart', {!! json_encode($expenseMix) !!}, ['#f43f5e', '#f97316', '#0ea5e9', '#8b5cf6', '#64748b']);

    // ── Per-unit economics ──
    var cm = {{ (float) $contribution_margin_per_unit }};
    mk('unitEconomicsChart', {
        type: 'bar',
        data: {
            labels: [
                {!! json_encode(__('messages.avg_rent_unit')) !!},
                {!! json_encode(__('messages.variable_cost_unit')) !!},
                {!! json_encode(__('messages.contribution_margin')) !!}
            ],
            datasets: [{
                data: [avgRent, varCost, cm],
                backgroundColor: ['#0ea5e9', '#f43f5e', cm >= 0 ? '#10b981' : '#f43f5e'],
                borderRadius: 6, maxBarThickness: 30
            }]
        },
        options: {
            indexAxis: 'y', maintainAspectRatio: false, responsive: true,
            scales: {
                x: { ticks: { callback: money, color: '#94a3b8', font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                y: { ticks: { color: '#475569', font: { size: 11 } }, grid: { display: false } }
            },
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return money(c.parsed.x); } } } }
        }
    });
});
</script>
@endpush
@endsection
