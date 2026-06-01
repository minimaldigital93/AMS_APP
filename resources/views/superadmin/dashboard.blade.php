@extends('layouts.superadmin')

@php
    $deltaBadge = function ($value) {
        $up = $value >= 0;
        $cls = $up ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600';
        $arrow = $up ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7';
        return ['cls' => $cls, 'arrow' => $arrow, 'text' => ($up ? '+' : '').$value.'%'];
    };
@endphp

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">{{ __('Platform overview') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ __('Subscriptions, revenue and account growth across every customer.') }}</p>
    </div>
    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600">{{ now()->format('M j, Y') }}</span>
</div>

{{-- ── Headline KPI cards (with month-over-month deltas) ──────────────── --}}
<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @php($kpis = [
        [
            'label' => __('MRR'),
            'value' => '$'.number_format($mrr, 2),
            'sub'   => '$'.number_format($arr, 0).' '.__('ARR'),
            'delta' => $subsDelta,
            'icon'  => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1',
            'tint'  => 'bg-emerald-50 text-emerald-600',
        ],
        [
            'label' => __('Revenue this month'),
            'value' => '$'.number_format($revenueThisMonth, 2),
            'sub'   => '$'.number_format($platformRevenue, 2).' '.__('all time'),
            'delta' => $revenueDelta,
            'icon'  => 'M3 13h2l1 7h12l1-7h2M5 13L4 6m16 7l1-7M9 6V4a3 3 0 016 0v2',
            'tint'  => 'bg-amber-50 text-amber-600',
        ],
        [
            'label' => __('Admin accounts'),
            'value' => number_format($accountsCount),
            'sub'   => '+'.number_format($newAccountsThisMonth).' '.__('this month'),
            'delta' => $accountsDelta,
            'icon'  => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8z',
            'tint'  => 'bg-indigo-50 text-indigo-600',
        ],
        [
            'label' => __('Active subscriptions'),
            'value' => number_format($activeSubscriptions),
            'sub'   => number_format($pendingSubscriptions).' '.__('pending'),
            'delta' => null,
            'icon'  => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'tint'  => 'bg-green-50 text-green-600',
        ],
    ])
    @foreach ($kpis as $kpi)
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between">
                <div class="text-sm text-gray-500">{{ $kpi['label'] }}</div>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl {{ $kpi['tint'] }}">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $kpi['icon'] }}"/></svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900">{{ $kpi['value'] }}</div>
            <div class="mt-1 flex items-center gap-2">
                @if (!is_null($kpi['delta']))
                    @php($b = $deltaBadge($kpi['delta']))
                    <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs font-semibold {{ $b['cls'] }}">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $b['arrow'] }}"/></svg>
                        {{ $b['text'] }}
                    </span>
                @endif
                <span class="text-xs font-medium text-gray-400">{{ $kpi['sub'] }}</span>
            </div>
        </div>
    @endforeach
</div>

{{-- ── MRR movement strip ──────────────────────────────────────────── --}}
<div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @php($movement = [
        [__('New MRR'), '+$'.number_format($newMrr, 2), 'text-emerald-600'],
        [__('Churned MRR'), '−$'.number_format($churnedMrr, 2), 'text-red-600'],
        [__('Net MRR movement'), ($netMrrMovement >= 0 ? '+' : '−').'$'.number_format(abs($netMrrMovement), 2), $netMrrMovement >= 0 ? 'text-emerald-600' : 'text-red-600'],
        [__('Churn rate'), $churnRate.'%', $churnRate > 5 ? 'text-red-600' : 'text-gray-900'],
    ])
    @foreach ($movement as [$label, $value, $color])
        <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
            <div class="text-xs uppercase tracking-wide text-gray-400">{{ $label }}</div>
            <div class="mt-1 text-xl font-bold {{ $color }}">{{ $value }}</div>
        </div>
    @endforeach
</div>

{{-- ── Charts row: revenue by plan + plan distribution ─────────────── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('Revenue by plan') }}</h2>
            <span class="text-xs text-gray-400">{{ __('last 12 months') }}</span>
        </div>
        <div class="mt-4 h-64"><canvas id="revenueChart"></canvas></div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('Active plans') }}</h2>
        <div class="mt-4 h-44"><canvas id="planChart"></canvas></div>
        <div class="mt-4 space-y-2">
            @foreach ($planDistribution as $i => $p)
                <div class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2 text-gray-600">
                        <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6'][$i % 5] }}"></span>
                        {{ $p['name'] }}
                    </span>
                    <span class="font-semibold text-gray-900">{{ number_format($p['count']) }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── Account growth + status breakdown ───────────────────────────── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('New accounts') }}</h2>
            <span class="text-xs text-gray-400">{{ __('last 12 months') }}</span>
        </div>
        <div class="mt-4 h-56"><canvas id="accountsChart"></canvas></div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('Subscription status') }}</h2>
        @php($statusRows = [
            [__('Active'), $activeSubscriptions, 'bg-green-500'],
            [__('Pending'), $pendingSubscriptions, 'bg-yellow-500'],
            [__('Expired / cancelled'), $expiredSubscriptions, 'bg-gray-400'],
        ])
        @php($statusTotal = max($activeSubscriptions + $pendingSubscriptions + $expiredSubscriptions, 1))
        <div class="mt-4 space-y-4">
            @foreach ($statusRows as [$label, $count, $bar])
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">{{ $label }}</span>
                        <span class="font-semibold text-gray-900">{{ number_format($count) }}</span>
                    </div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full {{ $bar }}" style="width: {{ round($count / $statusTotal * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── Engagement + capacity + top accounts ────────────────────────── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-3">
    {{-- Account engagement --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('Account engagement') }}</h2>
        <p class="mt-1 text-xs text-gray-400">{{ __('logged in within 30 days') }}</p>
        <div class="mt-4 grid grid-cols-3 gap-3 text-center">
            <div class="rounded-xl bg-emerald-50 p-3">
                <div class="text-2xl font-bold text-emerald-600">{{ number_format($activeAccounts) }}</div>
                <div class="mt-0.5 text-xs text-emerald-700">{{ __('Active') }}</div>
            </div>
            <div class="rounded-xl bg-gray-50 p-3">
                <div class="text-2xl font-bold text-gray-500">{{ number_format($dormantAccounts) }}</div>
                <div class="mt-0.5 text-xs text-gray-500">{{ __('Dormant') }}</div>
            </div>
            <div class="rounded-xl bg-red-50 p-3">
                <div class="text-2xl font-bold text-red-600">{{ number_format($suspendedAccounts) }}</div>
                <div class="mt-0.5 text-xs text-red-700">{{ __('Suspended') }}</div>
            </div>
        </div>
    </div>

    {{-- Platform capacity --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('Platform capacity') }}</h2>
        <p class="mt-1 text-xs text-gray-400">{{ __('managed across all accounts') }}</p>
        <div class="mt-4 flex items-center justify-between">
            <div>
                <div class="text-2xl font-bold text-gray-900">{{ number_format($totalApartments) }}</div>
                <div class="text-xs text-gray-500">{{ __('apartments') }} · {{ number_format($totalFloors) }} {{ __('floors') }}</div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold text-indigo-600">{{ $occupancyRate }}%</div>
                <div class="text-xs text-gray-500">{{ __('occupancy') }}</div>
            </div>
        </div>
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
            <div class="h-full bg-indigo-500" style="width: {{ $occupancyRate }}%"></div>
        </div>
        <div class="mt-2 text-xs text-gray-400">{{ number_format($occupiedApartments) }} {{ __('occupied') }}</div>
    </div>

    {{-- Top accounts by revenue --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('Top accounts by revenue') }}</h2>
        <div class="mt-3 divide-y divide-gray-100">
            @forelse ($topAccounts as $i => $acc)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span class="flex items-center gap-2 text-gray-700">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">{{ $i + 1 }}</span>
                        {{ $acc->name ?? '—' }}
                    </span>
                    <span class="font-semibold text-gray-900">${{ number_format($acc->total, 2) }}</span>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-400">{{ __('No revenue yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>

{{-- ── Expiring soon (actionable) ──────────────────────────────────── --}}
<div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('Expiring within 7 days') }}</h2>
        <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-600">{{ $expiringSoon->count() }}</span>
    </div>
    <div class="mt-3 divide-y divide-gray-100">
        @forelse ($expiringSoon as $sub)
            <div class="flex items-center justify-between py-2.5 text-sm">
                <div>
                    <span class="font-medium text-gray-900">{{ $sub->account?->name ?? '—' }}</span>
                    <span class="text-gray-400">· {{ $sub->plan?->name ?? '—' }}</span>
                </div>
                <span class="font-medium text-red-600">{{ $sub->expires_at?->diffForHumans() }}</span>
            </div>
        @empty
            <p class="py-4 text-center text-sm text-gray-400">{{ __('Nothing expiring soon. 🎉') }}</p>
        @endforelse
    </div>
</div>

{{-- ── Recent subscriptions ────────────────────────────────────────── --}}
<h2 class="mt-8 text-lg font-semibold text-gray-900">{{ __('Recent subscriptions') }}</h2>
<div class="mt-3 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-4 py-3">{{ __('Account') }}</th>
                <th class="px-4 py-3">{{ __('Plan') }}</th>
                <th class="px-4 py-3">{{ __('Status') }}</th>
                <th class="px-4 py-3">{{ __('Expires') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($recentSubscriptions as $sub)
                <tr>
                    <td class="px-4 py-3">{{ $sub->account?->name ?? '—' }} <span class="text-gray-400">{{ $sub->account?->phone }}</span></td>
                    <td class="px-4 py-3">{{ $sub->plan?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                            {{ $sub->status === 'active' ? 'bg-green-100 text-green-700' : ($sub->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ ucfirst($sub->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">{{ $sub->expires_at?->format('M j, Y') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">{{ __('No subscriptions yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif';
    Chart.defaults.color = '#9ca3af';

    var months = @json($monthLabels);
    var accounts = @json($accountsSeries);
    var planLabels = @json($planDistribution->pluck('name'));
    var planData = @json($planDistribution->pluck('count'));
    var revenueByPlan = @json($revenueByPlanSeries);
    var palette = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

    var usd = function (v) { return '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

    // Revenue by plan (stacked bars)
    var rc = document.getElementById('revenueChart');
    if (rc) {
        new Chart(rc, {
            type: 'bar',
            data: {
                labels: months,
                datasets: revenueByPlan.map(function (s, i) {
                    return {
                        label: s.name,
                        data: s.data,
                        backgroundColor: palette[i % palette.length],
                        borderRadius: 4,
                        maxBarThickness: 26,
                    };
                })
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } },
                    tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + usd(c.parsed.y); } } }
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { callback: function (v) { return '$' + v; } }, grid: { color: '#f3f4f6' } }
                }
            }
        });
    }

    // New accounts (bar)
    var ac = document.getElementById('accountsChart');
    if (ac) {
        new Chart(ac, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [{ label: 'New accounts', data: accounts, backgroundColor: '#10b981', borderRadius: 6, maxBarThickness: 28 }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f3f4f6' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // Plan distribution (doughnut)
    var pc = document.getElementById('planChart');
    if (pc) {
        new Chart(pc, {
            type: 'doughnut',
            data: { labels: planLabels, datasets: [{ data: planData, backgroundColor: palette, borderWidth: 0 }] },
            options: { cutout: '62%', maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    }
});
</script>
@endsection
