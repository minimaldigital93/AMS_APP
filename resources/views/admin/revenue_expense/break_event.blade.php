@extends('layouts.admin')

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
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold text-slate-800 tracking-tight">Are you covering your costs?</h1>
        <a href="{{ route('admin.revenue_expense.index') }}"
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
            <a href="{{ route('admin.revenue_expense.break_even', ['month' => $prevMonth, 'year' => $prevYear]) }}"
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
            <a href="{{ route('admin.revenue_expense.break_even', ['month' => $nextMonth, 'year' => $nextYear]) }}"
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

    {{-- ── Headline: revenue vs expense ─────────────────────── --}}
    <div class="rounded-2xl p-6 text-white {{ $is_above_break_even ? 'bg-gradient-to-br from-emerald-500 to-emerald-600' : 'bg-gradient-to-br from-rose-500 to-red-600' }}">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center text-3xl shrink-0">
                {{ $is_above_break_even ? '🎉' : '⚠️' }}
            </div>
            <div>
                <div class="text-lg font-bold leading-tight">
                    {{ $is_above_break_even ? "Yes — you're making money" : "Not yet — you're short" }}
                </div>
                <div class="text-3xl font-extrabold mt-1 leading-none">
                    {{ $is_above_break_even ? '+' : '−' }}${{ number_format(abs($is_above_break_even ? $safety_margin : $amount_needed), 0) }}
                    <span class="text-sm font-medium opacity-80">this month</span>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 mt-5">
            <div class="rounded-xl bg-white/15 px-4 py-3">
                <div class="text-xs opacity-80">Money in (rent)</div>
                <div class="text-xl font-bold mt-0.5">${{ number_format($current_revenue, 0) }}</div>
            </div>
            <div class="rounded-xl bg-white/15 px-4 py-3">
                <div class="text-xs opacity-80">Money out (costs)</div>
                <div class="text-xl font-bold mt-0.5">${{ number_format($total_expenses, 0) }}</div>
            </div>
        </div>
    </div>

    {{-- ── Two donuts: occupancy + utilities ────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Occupancy: exist vs rented --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700">Apartments rented</p>
            <p class="text-[11px] text-slate-400">How many of your units are bringing in rent</p>

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
                    <span class="text-xs text-slate-400">of {{ $total_apartments }} rented</span>
                </div>
            </div>

            <div class="flex items-center justify-center gap-4 text-xs">
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full {{ $is_above_break_even ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                    <span class="text-slate-600">Rented {{ $current_occupancy }}</span>
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-200"></span>
                    <span class="text-slate-600">Empty {{ $vacantUnits }}</span>
                </span>
            </div>

            @if($break_even_feasible)
                <p class="text-xs text-center text-slate-500 mt-4 pt-4 border-t border-slate-100">
                    @if($is_above_break_even)
                        Covering costs at <span class="font-semibold text-emerald-600">{{ $break_even_units }}</span> rented — you're past it. 👍
                    @else
                        Rent <span class="font-semibold text-rose-600">{{ $units_needed }}</span> more to cover your costs.
                    @endif
                </p>
            @endif
        </div>

        {{-- Utilities by type --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <p class="text-sm font-semibold text-slate-700">Where utility money goes</p>
            <p class="text-[11px] text-slate-400">Total billed across all rooms this month</p>

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
                        <span class="text-2xl font-extrabold text-slate-800 leading-none">${{ number_format($util['total'], 0) }}</span>
                        <span class="text-xs text-slate-400">total</span>
                    </div>
                </div>

                <div class="space-y-1.5">
                    @foreach($utilByType as $i => $item)
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: {{ $palette[$i % count($palette)] }}"></span>
                            <span class="text-slate-600 flex-1 truncate">{{ $item['label'] }}</span>
                            <span class="font-medium text-slate-700">${{ number_format($item['amount'], 0) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="text-3xl mb-2">💡</div>
                    <p class="text-sm text-slate-400">No utility bills recorded this month</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ── Quick insights ───────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        {{-- Average utilities per room --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <div class="w-10 h-10 rounded-xl bg-sky-50 flex items-center justify-center text-xl mb-3">💧</div>
            <p class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Avg utilities / room</p>
            <p class="text-2xl font-extrabold text-slate-800 mt-1">${{ number_format($util['avg_per_room'], 0) }}</p>
            <p class="text-[11px] text-slate-400 mt-1">across {{ $util['rooms_used'] }} {{ \Illuminate\Support\Str::plural('room', $util['rooms_used']) }} used</p>
        </div>

        {{-- Most utility-hungry apartment --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-xl mb-3">🏠</div>
            <p class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Highest utility room</p>
            @if($util['top_apartment'])
                <p class="text-2xl font-extrabold text-slate-800 mt-1">Apt {{ $util['top_apartment']['label'] }}</p>
                <p class="text-[11px] text-slate-400 mt-1">${{ number_format($util['top_apartment']['amount'], 0) }} in utilities</p>
            @else
                <p class="text-2xl font-extrabold text-slate-300 mt-1">—</p>
                <p class="text-[11px] text-slate-400 mt-1">No data yet</p>
            @endif
        </div>

        {{-- Biggest expense to cut --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5">
            <div class="w-10 h-10 rounded-xl bg-rose-50 flex items-center justify-center text-xl mb-3">✂️</div>
            <p class="text-[11px] uppercase tracking-wide text-slate-400 font-semibold">Biggest cost to cut</p>
            @if($biggest_expense)
                <p class="text-2xl font-extrabold text-slate-800 mt-1 truncate" title="{{ $biggest_expense['label'] }}">{{ $biggest_expense['label'] }}</p>
                <p class="text-[11px] text-slate-400 mt-1">${{ number_format($biggest_expense['amount'], 0) }} this month</p>
            @else
                <p class="text-2xl font-extrabold text-slate-300 mt-1">—</p>
                <p class="text-[11px] text-slate-400 mt-1">No expenses yet</p>
            @endif
        </div>
    </div>

</div>
@endsection
