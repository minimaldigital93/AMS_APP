@extends('layouts.superadmin')

@section('content')
<div class="mb-8">
    <h1 class="text-xl font-semibold text-gray-900">{{ __('Overview') }}</h1>
</div>

{{-- ── The few numbers a CEO checks ────────────────────────────────── --}}
<div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
    @php($net = $netMrrMovement)
    @php($cards = [
        [
            __('Monthly revenue'),
            '$'.number_format($mrr, 0),
            ($net >= 0 ? '+' : '−').'$'.number_format(abs($net), 0).' '.__('this month'),
            $net >= 0 ? 'text-emerald-600' : 'text-red-600',
        ],
        [
            __('Customers'),
            number_format($accountsCount),
            '+'.number_format($newAccountsThisMonth).' '.__('this month'),
            'text-emerald-600',
        ],
        [
            __('Active subscriptions'),
            number_format($activeSubscriptions),
            $churnRate.'% '.__('churn'),
            $churnRate > 5 ? 'text-red-600' : 'text-gray-400',
        ],
    ])
    @foreach ($cards as [$label, $value, $sub, $subColor])
        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $label }}</div>
            <div class="mt-3 text-3xl font-semibold text-gray-900">{{ $value }}</div>
            <div class="mt-1 text-sm {{ $subColor }}">{{ $sub }}</div>
        </div>
    @endforeach
</div>

{{-- ── One trend that matters: revenue ─────────────────────────────── --}}
<div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6">
    <div class="flex items-baseline justify-between">
        <h2 class="text-sm font-semibold text-gray-900">{{ __('Revenue') }}</h2>
        <span class="text-xs text-gray-400">{{ __('Last 12 months') }}</span>
    </div>
    <div class="mt-6 h-64"><canvas id="revenueChart"></canvas></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = 'ui-sans-serif, system-ui, sans-serif';
    Chart.defaults.color = '#9ca3af';

    var months  = @json($monthLabels);
    var revenue = @json($revenueSeries);

    var rc = document.getElementById('revenueChart');
    if (rc) {
        var ctx = rc.getContext('2d');
        var grad = ctx.createLinearGradient(0, 0, 0, 260);
        grad.addColorStop(0, 'rgba(16,185,129,0.18)');
        grad.addColorStop(1, 'rgba(16,185,129,0)');
        new Chart(rc, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    data: revenue,
                    borderColor: '#10b981',
                    backgroundColor: grad,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHoverBackgroundColor: '#10b981',
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (c) { return '$' + c.parsed.y.toLocaleString(); } } }
                },
                scales: {
                    y: { beginAtZero: true, border: { display: false }, ticks: { callback: function (v) { return '$' + v.toLocaleString(); } }, grid: { color: '#f3f4f6' } },
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } }
                }
            }
        });
    }
});
</script>
@endsection
