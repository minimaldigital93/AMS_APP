@extends('layouts.admin')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    @php
        $coveragePercent = (isset($break_even_revenue) && $break_even_revenue > 0)
            ? min(($current_revenue / $break_even_revenue) * 100, 100)
            : 0;
    @endphp

    {{-- ── Header ─────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800 tracking-tight">Break-Even Analysis</h1>
            <p class="text-xs text-slate-400 mt-0.5">How many units you need to rent to cover all costs</p>
        </div>
        <a href="{{ route('admin.revenue_expense.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back
        </a>
    </div>

    {{-- ── Hero Status + Key Metrics ───────────────────────────── --}}
    <div class="rounded-2xl overflow-hidden border {{ $is_above_break_even ? 'border-emerald-200' : 'border-red-200' }}">
        {{-- Status bar --}}
        <div class="{{ $is_above_break_even ? 'bg-emerald-600' : 'bg-red-600' }} px-6 py-4 flex items-center justify-between text-white">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full {{ $is_above_break_even ? 'bg-emerald-500' : 'bg-red-500' }} flex items-center justify-center text-lg font-bold">
                    {{ $is_above_break_even ? '✓' : '✗' }}
                </div>
                <div>
                    <p class="font-bold text-lg leading-tight">{{ $is_above_break_even ? 'Profitable' : 'Not Yet Profitable' }}</p>
                    <p class="text-xs {{ $is_above_break_even ? 'text-emerald-200' : 'text-red-200' }}">
                        Need <strong>{{ $break_even_units }}</strong> units to break even &mdash; {{ $current_occupancy }}/{{ $total_apartments }} currently occupied
                    </p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs {{ $is_above_break_even ? 'text-emerald-200' : 'text-red-200' }}">
                    {{ $is_above_break_even ? 'Surplus above BEP' : 'Still needed' }}
                </p>
                <p class="text-2xl font-extrabold">
                    @if($is_above_break_even)
                        +${{ number_format($safety_margin, 2) }}
                    @else
                        -${{ number_format($amount_needed, 2) }}
                    @endif
                </p>
                @if(!$is_above_break_even)
                    <p class="text-xs text-red-200">{{ $units_needed }} more unit(s) required</p>
                @endif
            </div>
        </div>

        {{-- 4 key metrics --}}
        <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-y md:divide-y-0 divide-slate-100 bg-white">
            <div class="px-5 py-4">
                <p class="text-[11px] text-slate-400 uppercase tracking-wide font-medium">Break-Even Point</p>
                <p class="text-2xl font-extrabold text-amber-500 mt-1">{{ $break_even_units }}<span class="text-sm font-medium text-slate-400 ml-1">units</span></p>
                <p class="text-[11px] text-slate-400 mt-0.5">${{ number_format($break_even_revenue, 2) }} required revenue</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-[11px] text-slate-400 uppercase tracking-wide font-medium">Current Revenue</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-1">${{ number_format($current_revenue, 2) }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ $current_occupancy }} of {{ $total_apartments }} units rented</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-[11px] text-slate-400 uppercase tracking-wide font-medium">Monthly Costs</p>
                <p class="text-2xl font-extrabold text-orange-500 mt-1">${{ number_format($fixed_costs, 2) }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">recurring regardless of occupancy</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-[11px] text-slate-400 uppercase tracking-wide font-medium">Safety Margin</p>
                <p class="text-2xl font-extrabold {{ $is_above_break_even ? 'text-emerald-600' : 'text-red-500' }} mt-1">{{ $safety_margin_percent }}%</p>
                <p class="text-[11px] text-slate-400 mt-0.5">${{ number_format(abs($safety_margin), 2) }} {{ $is_above_break_even ? 'above' : 'below' }} BEP</p>
            </div>
        </div>
    </div>

    {{-- ── Donut Charts ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        {{-- 1. Occupancy Progress --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5 flex flex-col items-center">
            <p class="text-sm font-semibold text-slate-700">Occupancy Progress</p>
            <p class="text-[11px] text-slate-400 mt-0.5 mb-4">Units occupied vs break-even target</p>
            <div class="relative w-44 h-44">
                <canvas id="occupancyChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-xl font-extrabold text-slate-800">{{ $current_occupancy }}<span class="text-slate-400 font-normal text-sm">/{{ $total_apartments }}</span></span>
                    <span class="text-[11px] text-slate-400">occupied</span>
                </div>
            </div>
            <div class="flex flex-wrap justify-center gap-3 mt-4 text-[11px]">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 inline-block"></span>Rented</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span>BEP target</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-slate-200 inline-block"></span>Vacant</span>
            </div>
        </div>

        {{-- 2. Cost Composition --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5 flex flex-col items-center">
            <p class="text-sm font-semibold text-slate-700">Cost Composition</p>
            <p class="text-[11px] text-slate-400 mt-0.5 mb-4">Monthly vs per-unit (at current occupancy)</p>
            <div class="relative w-44 h-44">
                <canvas id="costCompositionChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-xl font-extrabold text-slate-800">${{ number_format($fixed_costs + $variable_cost_per_unit * $current_occupancy, 0) }}</span>
                    <span class="text-[11px] text-slate-400">total costs</span>
                </div>
            </div>
            <div class="flex flex-wrap justify-center gap-3 mt-4 text-[11px]">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-orange-400 inline-block"></span>Monthly</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-purple-400 inline-block"></span>Per-Unit</span>
            </div>
        </div>

        {{-- 3. Revenue Coverage --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5 flex flex-col items-center">
            <p class="text-sm font-semibold text-slate-700">Revenue Coverage</p>
            <p class="text-[11px] text-slate-400 mt-0.5 mb-4">How much of break-even revenue is covered</p>
            <div class="relative w-44 h-44">
                <canvas id="revenueChart"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-xl font-extrabold {{ $is_above_break_even ? 'text-emerald-600' : 'text-red-500' }}">
                            <?php echo e(number_format($coveragePercent, 0)); ?>%
                        </span>
                    <span class="text-[11px] text-slate-400">covered</span>
                </div>
            </div>
            <div class="flex flex-wrap justify-center gap-3 mt-4 text-[11px]">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-sky-400 inline-block"></span>Revenue</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-rose-300 inline-block"></span>Gap</span>
            </div>
        </div>
    </div>

    {{-- ── Cost Details + Formula ───────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

        {{-- Monthly & Per-Unit cost lists --}}
        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-orange-400"></span>
                <span class="text-sm font-semibold text-slate-700">Monthly Costs</span>
                <span class="ml-auto text-sm font-bold text-orange-500">${{ number_format($fixed_costs, 2) }}</span>
            </div>
            <div class="px-5 py-3 space-y-1.5">
                @forelse($fixed_cost_breakdown as $item)
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>{{ $item['label'] }}</span>
                        <span class="font-medium text-slate-700">${{ number_format($item['amount'], 2) }}</span>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 italic">No monthly costs recorded</p>
                @endforelse
            </div>
            <div class="px-5 py-3 border-t border-slate-100 flex items-center gap-2 mt-1">
                <span class="w-2.5 h-2.5 rounded-full bg-purple-400"></span>
                <span class="text-sm font-semibold text-slate-700">Per-Unit Costs</span>
                <span class="ml-auto text-sm font-bold text-purple-500">${{ number_format($variable_cost_per_unit, 2) }}<span class="text-slate-400 font-normal text-[11px] ml-0.5">/unit</span></span>
            </div>
            <div class="px-5 pb-4 space-y-1.5">
                @forelse($variable_cost_breakdown as $item)
                    <div class="flex justify-between text-xs text-slate-500">
                        <span>{{ $item['label'] }}</span>
                        <span class="font-medium text-slate-700">${{ number_format($item['amount'], 2) }}</span>
                    </div>
                @empty
                    <p class="text-xs text-slate-400 italic">No per-unit costs recorded</p>
                @endforelse
            </div>
        </div>

        {{-- Break-Even Formula --}}
        <div class="bg-white rounded-2xl border border-slate-100 p-5 flex flex-col justify-between">
            <div>
                <p class="text-sm font-semibold text-slate-700 mb-4">Break-Even Formula</p>
                {{-- Formula visual --}}
                <div class="flex items-center justify-center gap-2 text-sm mb-5 flex-wrap">
                    <div class="text-center">
                        <div class="bg-orange-50 text-orange-600 font-semibold px-3 py-1.5 rounded-lg text-xs">${{ number_format($fixed_costs, 2) }}</div>
                        <div class="text-[10px] text-slate-400 mt-1">Monthly Costs</div>
                    </div>
                    <span class="text-slate-300 text-lg">÷</span>
                    <div class="text-center">
                        <div class="bg-sky-50 text-sky-600 font-semibold px-3 py-1.5 rounded-lg text-xs">${{ number_format($contribution_margin_per_unit, 2) }}</div>
                        <div class="text-[10px] text-slate-400 mt-1">Contribution Margin</div>
                    </div>
                    <span class="text-slate-300 text-lg">=</span>
                    <div class="text-center">
                        <div class="bg-amber-50 text-amber-600 font-bold px-3 py-1.5 rounded-lg text-xs">{{ $break_even_units }} units</div>
                        <div class="text-[10px] text-slate-400 mt-1">Break-Even Point</div>
                    </div>
                </div>
                <div class="text-[11px] text-center text-slate-400 mb-4">
                    Contribution Margin = Avg Rent (${{ number_format($avg_rent_per_apartment, 2) }}) − Per-unit cost (${{ number_format($variable_cost_per_unit, 2) }})
                </div>
            </div>
            {{-- Step summary --}}
            <div class="space-y-2 border-t border-slate-100 pt-4">
                <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Avg rent / unit</span>
                    <span class="font-semibold text-emerald-600">${{ number_format($avg_rent_per_apartment, 2) }}</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Per-unit cost</span>
                    <span class="font-semibold text-slate-700">${{ number_format($variable_cost_per_unit, 2) }}</span>
                </div>
                <div class="flex justify-between text-xs">
                    <span class="text-slate-500">Contribution margin / unit</span>
                    <span class="font-semibold text-sky-600">${{ number_format($contribution_margin_per_unit, 2) }}</span>
                </div>
                <div class="flex justify-between text-xs pt-2 border-t border-slate-100">
                    <span class="font-semibold text-slate-700">Break-Even Revenue</span>
                    <span class="font-bold text-amber-600">${{ number_format($break_even_revenue, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const fixedCosts      = {{ $fixed_costs }};
    const variableCosts   = {{ $variable_cost_per_unit * $current_occupancy }};
    const currentRevenue  = {{ $current_revenue }};
    const breakEvenRev    = {{ $break_even_revenue }};
    const occupied        = {{ $current_occupancy }};
    const breakEvenUnits  = {{ $break_even_units }};
    const totalApts       = {{ $total_apartments }};
    const isAbove         = {{ $is_above_break_even ? 'true' : 'false' }};

    const donutDefaults = {
        cutout: '72%',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const val = ctx.parsed;
                        return ` ${ctx.label}: $${val.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}`;
                    }
                }
            }
        }
    };

    // ── 1. Cost Composition ──────────────────────────────────────
    new Chart(document.getElementById('costCompositionChart'), {
        type: 'doughnut',
        data: {
            labels: ['Monthly Costs', 'Per-Unit Costs'],
            datasets: [{
                data: [fixedCosts, variableCosts],
                backgroundColor: ['#fb923c', '#c084fc'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            ...donutDefaults,
            plugins: {
                ...donutDefaults.plugins,
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: $${ctx.parsed.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}`
                    }
                }
            }
        }
    });

    // ── 2. Occupancy vs Break-Even ────────────────────────────────
    const aboveBreakEven = Math.max(0, occupied - breakEvenUnits);
    const atBreakEven    = Math.min(occupied, breakEvenUnits);
    const vacant         = Math.max(0, totalApts - occupied);

    new Chart(document.getElementById('occupancyChart'), {
        type: 'doughnut',
        data: {
            labels: ['Occupied (above BEP)', 'Break-Even Target', 'Vacant'],
            datasets: [{
                data: [aboveBreakEven, atBreakEven, vacant],
                backgroundColor: ['#34d399', '#fbbf24', '#e2e8f0'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.parsed} unit(s)`
                    }
                }
            }
        }
    });

    // ── 3. Revenue vs Break-Even Revenue ─────────────────────────
    const gap        = Math.max(0, breakEvenRev - currentRevenue);
    const revCovered = Math.min(currentRevenue, breakEvenRev);

    new Chart(document.getElementById('revenueChart'), {
        type: 'doughnut',
        data: {
            labels: ['Current Revenue', 'Remaining Gap'],
            datasets: [{
                data: [revCovered, gap > 0 ? gap : (currentRevenue - breakEvenRev)],
                backgroundColor: gap > 0 ? ['#38bdf8', '#fca5a5'] : ['#34d399', '#38bdf8'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            ...donutDefaults,
            plugins: {
                ...donutDefaults.plugins,
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: $${ctx.parsed.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}`
                    }
                }
            }
        }
    });
});
</script>
@endpush
