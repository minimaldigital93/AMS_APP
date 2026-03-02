@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Tenants -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-6 border border-blue-200 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-600 text-sm font-semibold">Active Tenants</p>
                        <p class="text-4xl font-bold text-gray-900 mt-2">{{ $stats['tenants']['active'] }}</p>
                        <p class="text-xs text-gray-500 font-medium mt-2">Total: {{ $stats['tenants']['total'] }}</p>
                    </div>
                    <svg class="w-12 h-12 text-blue-200" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 12a3 3 0 100-6 3 3 0 000 6zm0 2c-3.315 0-6 1.343-6 3v2h12v-2c0-1.657-2.685-3-6-3z"/>
                    </svg>
                </div>
            </div>

            <!-- Occupied Apartments -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-6 border border-green-200 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-600 text-sm font-semibold">Occupied Units</p>
                        <p class="text-4xl font-bold text-gray-900 mt-2">{{ $stats['apartments']['occupied'] }}</p>
                        <p class="text-xs text-orange-600 font-medium mt-2">{{ $stats['apartments']['available'] }} vacant available</p>
                    </div>
                    <svg class="w-12 h-12 text-green-200" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    </svg>
                </div>
            </div>

            <!-- Monthly Revenue -->
            <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-lg p-6 border border-emerald-200 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-emerald-600 text-sm font-semibold">Monthly Revenue</p>
                        <p class="text-4xl font-bold text-gray-900 mt-2">${{ number_format($stats['revenue']['collected_this_month'] + $stats['revenue']['late_fees_this_month'], 2) }}</p>
                        <p class="text-xs text-gray-500 font-medium mt-2">Collected in {{ now()->format('M Y') }}</p>
                    </div>
                    <svg class="w-12 h-12 text-emerald-200" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
                    </svg>
                </div>
            </div>

            <!-- Monthly Expenses -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-6 border border-purple-200 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-600 text-sm font-semibold">Monthly Expenses</p>
                        <p class="text-4xl font-bold text-gray-900 mt-2">${{ number_format($stats['expenses']['monthly_total'] ?? 0, 2) }}</p>
                        <p class="text-xs text-gray-500 font-medium mt-2">Spent in {{ now()->format('M Y') }}</p>
                    </div>
                    <svg class="w-12 h-12 text-purple-200" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/>
                    </svg>
                </div>
            </div>
        </div>

        @if($fiscalData['has_active_period'])

        @if($fiscalData['recent_periods']->count() > 0)
        <!-- Recent Closed Fiscal Periods -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Closed Periods</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="pb-2 font-semibold text-gray-600">Period</th>
                            <th class="pb-2 font-semibold text-gray-600">Dates</th>
                            <th class="pb-2 font-semibold text-gray-600 text-right">Opening Balance</th>
                            <th class="pb-2 font-semibold text-gray-600 text-right">Closing Balance</th>
                            <th class="pb-2 font-semibold text-gray-600 text-right">Change</th>
                            <th class="pb-2 font-semibold text-gray-600"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fiscalData['recent_periods'] as $period)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3 font-medium">{{ $period->name }}</td>
                            <td class="py-3 text-gray-500">{{ $period->opening_date->format('M d') }} - {{ $period->closing_date->format('M d, Y') }}</td>
                            <td class="py-3 text-right">${{ number_format($period->opening_balance, 2) }}</td>
                            <td class="py-3 text-right">${{ number_format($period->closing_balance, 2) }}</td>
                            <td class="py-3 text-right {{ ($period->closing_balance - $period->opening_balance) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ ($period->closing_balance - $period->opening_balance) >= 0 ? '+' : '' }}${{ number_format($period->closing_balance - $period->opening_balance, 2) }}
                            </td>
                            <td class="py-3 text-right">
                                <a href="{{ route('admin.fiscalperiod.reports', $period->id) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">View Report</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @else
        <!-- No Active Fiscal Period Warning -->
        <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-6">
            <div class="flex items-start gap-4">
                <svg class="w-8 h-8 text-yellow-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
                <div>
                    <h3 class="text-lg font-bold text-yellow-900">No Active Fiscal Period</h3>
                    <p class="text-yellow-800 mt-1">
                        You need to create a fiscal period before recording transactions. A fiscal period helps you track rent revenue, 
                        expenses, and manage your financial records for a specific time frame.
                    </p>
                    <a href="{{ route('admin.fiscalperiod.create') }}" class="inline-block mt-3 bg-yellow-600 text-white px-6 py-2 rounded-lg hover:bg-yellow-700 transition font-medium">
                        Create Fiscal Period
                    </a>
                </div>
            </div>
        </div>
        @endif

        <!-- Charts Row 1 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Monthly Revenue Trend -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Revenue Trend (6 Months)</h2>
                <canvas id="revenueChart" class="w-full" style="max-height: 300px;"></canvas>
            </div>

            <!-- Payment Status Distribution -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Status</h2>
                <canvas id="paymentStatusChart" class="w-full" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Occupancy by Building -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Occupancy by Floor</h2>
                <canvas id="occupancyChart" class="w-full" style="max-height: 300px;"></canvas>
            </div>

            <!-- Utility Usage -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Utility Consumption (Monthly)</h2>
                <canvas id="utilityChart" class="w-full" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Charts Row 3 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Rental Income vs Expenses -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Income vs Expenses</h2>
                <canvas id="incomeExpenseChart" class="w-full" style="max-height: 300px;"></canvas>
            </div>

            <!-- Pending Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Pending Actions</h2>
                <div class="space-y-3">
                    @if($stats['payments']['overdue'] > 0)
                    <div class="flex items-center justify-between p-3 bg-red-50 border-l-4 border-red-500 rounded">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $stats['payments']['overdue'] }} Overdue Payments</p>
                            <p class="text-xs text-gray-500">Total: ${{ number_format($stats['payments']['total_pending'], 2) }}</p>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold text-red-700 bg-red-200 rounded-full">Urgent</span>
                    </div>
                    @endif
                    
                    @if($stats['apartments']['available'] > 0)
                    <div class="flex items-center justify-between p-3 bg-yellow-50 border-l-4 border-yellow-500 rounded">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $stats['apartments']['available'] }} Available Units</p>
                            <p class="text-xs text-gray-500">Ready for lease</p>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold text-yellow-700 bg-yellow-200 rounded-full">Available</span>
                    </div>
                    @endif
                    
                    @if($stats['apartments']['maintenance'] > 0)
                    <div class="flex items-center justify-between p-3 bg-blue-50 border-l-4 border-blue-500 rounded">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $stats['apartments']['maintenance'] }} Units in Maintenance</p>
                            <p class="text-xs text-gray-500">Requiring attention</p>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold text-blue-700 bg-blue-200 rounded-full">In Progress</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Revenue Trend Chart (last 6 months from actual data)
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: {!! json_encode($monthlyChartData['labels']) !!},
                    datasets: [{
                        label: 'Monthly Revenue ($)',
                        data: {!! json_encode($monthlyChartData['revenue']) !!},
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: function(value) { return '$' + value.toLocaleString(); } }
                        }
                    }
                }
            });

        // 2. Payment Status Chart
        const paymentStatusCtx = document.getElementById('paymentStatusChart').getContext('2d');
        new Chart(paymentStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Pending', 'Overdue'],
                datasets: [{
                    data: [{{ $stats['payments']['paid'] }}, {{ $stats['payments']['pending'] }}, {{ $stats['payments']['overdue'] }}],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderColor: '#fff',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 15, font: { size: 12 } }
                    }
                }
            }
        });

        // 3. Occupancy by Floor
        const occupancyCtx = document.getElementById('occupancyChart').getContext('2d');
        new Chart(occupancyCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($stats['floor_labels']) !!},
                datasets: [{
                    label: 'Occupancy %',
                    data: {!! json_encode($stats['floor_occupancy']) !!},
                    backgroundColor: ['#3b82f6', '#06b6d4', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981'],
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: function(value) { return value + '%'; } }
                    }
                }
            }
        });

        // 4. Utility Consumption
        const utilityCtx = document.getElementById('utilityChart').getContext('2d');
        @php
            $uBreakdown = $stats['expenses']['utility_breakdown'] ?? [];
            $uLabels = [];
            $uData = [];
            $uColors = ['#06b6d4', '#f59e0b', '#8b5cf6', '#ec4899'];
            $typeMap = ['electricity' => 'Electricity', 'water' => 'Water', 'internet' => 'Internet', 'parking' => 'Parking'];
            foreach ($typeMap as $key => $label) {
                $uLabels[] = $label;
                $uData[] = round($uBreakdown[$key] ?? 0, 2);
            }
        @endphp
        new Chart(utilityCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($uLabels) !!},
                datasets: [{
                    label: 'Cost ($)',
                    data: {!! json_encode($uData) !!},
                    backgroundColor: {!! json_encode(array_slice($uColors, 0, count($uLabels))) !!},
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: function(value) { return '$' + value; } }
                    }
                }
            }
        });

        // 5. Income vs Expenses (last 6 months from actual data)
        const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
        new Chart(incomeExpenseCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($monthlyChartData['labels']) !!},
                datasets: [
                    {
                        label: 'Income',
                        data: {!! json_encode($monthlyChartData['revenue']) !!},
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.05)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4
                    },
                    {
                        label: 'Expenses',
                        data: {!! json_encode($monthlyChartData['expenses']) !!},
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.05)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 15, font: { size: 12 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: function(value) { return '$' + value.toLocaleString(); } }
                    }
                }
            }
        });
        });
    </script>
    @endpush
@endsection
