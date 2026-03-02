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
                        <p class="text-4xl font-bold text-gray-900 mt-2">${{ number_format($stats['revenue']['total_monthly_rent'], 2) }}</p>
                        <p class="text-xs text-gray-500 font-medium mt-2">From occupied units</p>
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
                        <p class="text-xs text-gray-500 font-medium mt-2">Utilities, maintenance & other</p>
                    </div>
                    <svg class="w-12 h-12 text-purple-200" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Fiscal Period Financial Summary -->
        @if($fiscalData['has_active_period'])
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Active Fiscal Period</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $fiscalData['period']->name }} 
                        ({{ $fiscalData['period']->opening_date->format('M d, Y') }} - {{ $fiscalData['period']->closing_date->format('M d, Y') }})
                    </p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('admin.fiscalperiod.show', $fiscalData['period']->id) }}" class="text-sm bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        View Details
                    </a>
                    <a href="{{ route('admin.fiscalperiod.reports', $fiscalData['period']->id) }}" class="text-sm bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        Reports
                    </a>
                </div>
            </div>

            <!-- Fiscal Period Financial Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <!-- Revenue -->
                <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                    <p class="text-green-600 text-xs font-semibold uppercase">Rent Revenue</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($fiscalData['revenue'], 2) }}</p>
                    @if($fiscalData['late_fees'] > 0)
                        <p class="text-xs text-green-600 mt-1">+ ${{ number_format($fiscalData['late_fees'], 2) }} late fees</p>
                    @endif
                </div>

                <!-- Total Income -->
                <div class="bg-emerald-50 rounded-lg p-4 border border-emerald-200">
                    <p class="text-emerald-600 text-xs font-semibold uppercase">Total Income</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($fiscalData['total_income'], 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Revenue + Late Fees</p>
                </div>

                <!-- Total Expenses -->
                <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                    <p class="text-red-600 text-xs font-semibold uppercase">Total Expenses</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($fiscalData['total_expenses'], 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ count($fiscalData['expenses']) }} categories</p>
                </div>

                <!-- Net Profit/Loss -->
                <div class="rounded-lg p-4 border {{ $fiscalData['is_profitable'] ? 'bg-blue-50 border-blue-200' : 'bg-orange-50 border-orange-200' }}">
                    <p class="{{ $fiscalData['is_profitable'] ? 'text-blue-600' : 'text-orange-600' }} text-xs font-semibold uppercase">
                        {{ $fiscalData['is_profitable'] ? 'Net Profit' : 'Net Loss' }}
                    </p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">
                        {{ $fiscalData['is_profitable'] ? '+' : '-' }}${{ number_format(abs($fiscalData['net_profit']), 2) }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">{{ $fiscalData['profit_margin'] }}% margin</p>
                </div>

                <!-- Current Balance -->
                <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-200">
                    <p class="text-indigo-600 text-xs font-semibold uppercase">Current Balance</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">${{ number_format($fiscalData['current_balance'], 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Opening: ${{ number_format($fiscalData['opening_balance'], 2) }}</p>
                </div>
            </div>

            <!-- Expense Breakdown & Balance Sheet -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Expense Breakdown by Category -->
                @if(count($fiscalData['expenses']) > 0)
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Expense Breakdown</h3>
                    <div class="space-y-2">
                        @foreach($fiscalData['expenses'] as $type => $amount)
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 capitalize">{{ str_replace('_', ' ', $type) }}</span>
                            <span class="text-sm font-semibold text-gray-900">${{ number_format($amount, 2) }}</span>
                        </div>
                        @endforeach
                        <div class="border-t pt-2 mt-2 flex justify-between items-center">
                            <span class="text-sm font-bold text-gray-700">Total</span>
                            <span class="text-sm font-bold text-red-600">${{ number_format($fiscalData['total_expenses'], 2) }}</span>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Balance Sheet Summary -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Balance Sheet Summary</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Total Assets</span>
                            <span class="text-sm font-semibold text-blue-600">${{ number_format($fiscalData['balance_sheet']['total_assets'], 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Total Liabilities</span>
                            <span class="text-sm font-semibold text-red-600">${{ number_format($fiscalData['balance_sheet']['total_liabilities'], 2) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Total Equity</span>
                            <span class="text-sm font-semibold text-green-600">${{ number_format($fiscalData['balance_sheet']['total_equity'], 2) }}</span>
                        </div>
                        <div class="border-t pt-2 mt-2 flex justify-between items-center">
                            <span class="text-sm font-bold text-gray-700">Net Worth</span>
                            <span class="text-sm font-bold text-indigo-600">${{ number_format($fiscalData['balance_sheet']['total_assets'] - $fiscalData['balance_sheet']['total_liabilities'], 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Occupancy by Building</h2>
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
            // 1. Revenue Trend Chart (from fiscal period data)
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            @if($fiscalData['has_active_period'] && count($fiscalData['monthly_revenue']) > 0)
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: {!! json_encode(array_keys($fiscalData['monthly_revenue'])) !!},
                    datasets: [{
                        label: 'Monthly Revenue ($)',
                        data: {!! json_encode(array_values($fiscalData['monthly_revenue'])) !!},
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
            @else
            new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: ['No Data'],
                datasets: [{
                    label: 'Monthly Revenue ($)',
                    data: [0],
                    borderColor: '#d1d5db',
                    backgroundColor: 'rgba(209, 213, 219, 0.1)',
                    borderWidth: 2,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: { display: true, text: 'No fiscal period revenue data yet' }
                }
            }
        });
            @endif

        // 2. Payment Status Chart
        const paymentStatusCtx = document.getElementById('paymentStatusChart').getContext('2d');
        new Chart(paymentStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Pending', 'Overdue'],
                datasets: [{
                    data: [78, 15, 7],
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

        // 3. Occupancy by Building
        const occupancyCtx = document.getElementById('occupancyChart').getContext('2d');
        new Chart(occupancyCtx, {
            type: 'bar',
            data: {
                labels: ['Building A', 'Building B', 'Building C', 'Building D'],
                datasets: [{
                    label: 'Occupancy %',
                    data: [95, 92, 96, 90],
                    backgroundColor: ['#3b82f6', '#06b6d4', '#8b5cf6', '#ec4899'],
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
        new Chart(utilityCtx, {
            type: 'bar',
            data: {
                labels: ['Water', 'Electricity', 'Gas', 'Internet'],
                datasets: [{
                    label: 'Usage Cost ($)',
                    data: [1200, 2800, 1500, 400],
                    backgroundColor: ['#06b6d4', '#f59e0b', '#ef4444', '#8b5cf6'],
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

        // 5. Income vs Expenses (from fiscal period)
        const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
        @if($fiscalData['has_active_period'] && (count($fiscalData['monthly_revenue']) > 0 || count($fiscalData['monthly_expenses']) > 0))
        @php
            // Merge all months from revenue and expenses
            $allMonths = array_unique(array_merge(
                array_keys($fiscalData['monthly_revenue']),
                array_keys($fiscalData['monthly_expenses'])
            ));
            sort($allMonths);
            $revenueData = [];
            $expenseData = [];
            foreach ($allMonths as $month) {
                $revenueData[] = $fiscalData['monthly_revenue'][$month] ?? 0;
                $expenseData[] = $fiscalData['monthly_expenses'][$month] ?? 0;
            }
        @endphp
        new Chart(incomeExpenseCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode(array_values($allMonths)) !!},
                datasets: [
                    {
                        label: 'Income',
                        data: {!! json_encode($revenueData) !!},
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.05)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4
                    },
                    {
                        label: 'Expenses',
                        data: {!! json_encode($expenseData) !!},
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
        @else
        new Chart(incomeExpenseCtx, {
            type: 'line',
            data: {
                labels: ['No Data'],
                datasets: [{
                    label: 'Income vs Expenses',
                    data: [0],
                    borderColor: '#d1d5db',
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Create a fiscal period to see income vs expenses' }
                }
            }
        });
        @endif
        });
    </script>
    @endpush
@endsection
