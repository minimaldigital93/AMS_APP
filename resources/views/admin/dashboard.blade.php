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
                <div style="position: relative; height: 300px;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Payment Status Distribution -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Status</h2>
                <div style="position: relative; height: 300px;">
                    <canvas id="paymentStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Occupancy by Building -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Occupancy by Floor</h2>
                <div style="position: relative; height: 300px;">
                    <canvas id="occupancyChart"></canvas>
                </div>
            </div>

            <!-- Utility Usage -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Utility Consumption (Monthly)</h2>
                <div style="position: relative; height: 300px;">
                    <canvas id="utilityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Charts Row 3 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Rental Income vs Expenses -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Income vs Expenses</h2>
                <div style="position: relative; height: 300px;">
                    <canvas id="incomeExpenseChart"></canvas>
                </div>
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

        <!-- Revenue & Expense Calendar -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Revenue & Expense Calendar</h2>
                    <p class="text-sm text-gray-500 mt-1">{{ $calendarData['startOfMonth']->format('F Y') }}</p>
                </div>
                <a href="{{ route('admin.revenue_expense.monthly_calendar') }}" 
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Full View
                </a>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg border border-green-200 p-3">
                    <p class="text-xs text-green-600 uppercase tracking-wide font-semibold">Total Income</p>
                    <p class="text-xl font-bold text-green-700 mt-1">${{ number_format($calendarData['monthTotalIncome'], 2) }}</p>
                </div>
                <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-lg border border-red-200 p-3">
                    <p class="text-xs text-red-600 uppercase tracking-wide font-semibold">Total Expenses</p>
                    <p class="text-xl font-bold text-red-700 mt-1">${{ number_format($calendarData['monthTotalExpense'], 2) }}</p>
                </div>
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg border border-blue-200 p-3">
                    <p class="text-xs text-blue-600 uppercase tracking-wide font-semibold">Net Profit/Loss</p>
                    <p class="text-xl font-bold {{ $calendarData['monthNet'] >= 0 ? 'text-green-700' : 'text-red-700' }} mt-1">
                        {{ $calendarData['monthNet'] >= 0 ? '+' : '' }}${{ number_format($calendarData['monthNet'], 2) }}
                    </p>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg border border-purple-200 p-3">
                    <p class="text-xs text-purple-600 uppercase tracking-wide font-semibold">Best Day</p>
                    @if($calendarData['bestDay'])
                        <p class="text-xl font-bold text-purple-700 mt-1">{{ $calendarData['startOfMonth']->copy()->day($calendarData['bestDay'])->format('M d') }}</p>
                        <p class="text-xs text-green-600">+${{ number_format($calendarData['calendarDays'][$calendarData['bestDay']]['net'], 2) }}</p>
                    @else
                        <p class="text-sm text-gray-400 mt-1">No data</p>
                    @endif
                </div>
            </div>

            <!-- Legend -->
            <div class="flex items-center gap-4 text-xs text-gray-500 mb-4">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 bg-green-500 rounded-full inline-block"></span> Income</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 bg-red-500 rounded-full inline-block"></span> Expense</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 border-2 border-blue-500 rounded inline-block"></span> Today</span>
            </div>

            <!-- Calendar Grid -->
            <div class="bg-white rounded-lg border overflow-hidden">
                <!-- Day Headers -->
                <div class="grid grid-cols-7 bg-gray-50 border-b">
                    @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                        <div class="text-center text-xs font-semibold text-gray-500 py-2 uppercase tracking-wider">{{ $dayName }}</div>
                    @endforeach
                </div>

                <!-- Calendar Days -->
                <div class="grid grid-cols-7">
                    {{-- Empty cells for offset --}}
                    @for($i = 0; $i < $calendarData['firstDayOfWeek']; $i++)
                        <div class="border-b border-r min-h-[90px] bg-gray-50/50"></div>
                    @endfor

                    {{-- Actual days --}}
                    @for($d = 1; $d <= $calendarData['daysInMonth']; $d++)
                        @php
                            $dayData = $calendarData['calendarDays'][$d];
                            $hasData = $dayData['tx_count'] > 0;
                            $isToday = $dayData['is_today'];
                            $isFuture = $dayData['is_future'];
                            $cellClasses = 'border-b border-r min-h-[90px] p-1.5 transition';
                            if ($isToday) $cellClasses .= ' ring-2 ring-blue-500 ring-inset bg-blue-50/30';
                            elseif ($isFuture) $cellClasses .= ' bg-gray-50/30';
                            elseif ($hasData) $cellClasses .= ' hover:bg-gray-50';
                        @endphp
                        <div class="{{ $cellClasses }}">
                            {{-- Day number --}}
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-semibold {{ $isToday ? 'bg-blue-500 text-white w-5 h-5 rounded-full flex items-center justify-center' : ($isFuture ? 'text-gray-300' : 'text-gray-600') }}">
                                    {{ $d }}
                                </span>
                                @if($hasData)
                                    <span class="text-[10px] text-gray-400">{{ $dayData['tx_count'] }}</span>
                                @endif
                            </div>

                            @if($hasData)
                                {{-- Income --}}
                                @if($dayData['income'] > 0)
                                    <div class="flex items-center gap-1 mb-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 flex-shrink-0"></span>
                                        <span class="text-[11px] font-medium text-green-700 truncate">+${{ number_format($dayData['income'], 0) }}</span>
                                    </div>
                                @endif

                                {{-- Expense --}}
                                @if($dayData['expense'] > 0)
                                    <div class="flex items-center gap-1 mb-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 flex-shrink-0"></span>
                                        <span class="text-[11px] font-medium text-red-700 truncate">-${{ number_format($dayData['expense'], 0) }}</span>
                                    </div>
                                @endif

                                {{-- Net indicator bar --}}
                                @php
                                    $maxDay = max(array_column($calendarData['calendarDays'], 'income') ?: [1]);
                                    $maxExp = max(array_column($calendarData['calendarDays'], 'expense') ?: [1]);
                                    $maxVal = max($maxDay, $maxExp, 1);
                                    $incWidth = min(($dayData['income'] / $maxVal) * 100, 100);
                                    $expWidth = min(($dayData['expense'] / $maxVal) * 100, 100);
                                @endphp
                                <div class="mt-1 space-y-0.5">
                                    @if($dayData['income'] > 0)
                                        <div class="h-1 rounded-full bg-green-200 overflow-hidden">
                                            <div class="h-full bg-green-500 rounded-full" style="width: {{ $incWidth }}%"></div>
                                        </div>
                                    @endif
                                    @if($dayData['expense'] > 0)
                                        <div class="h-1 rounded-full bg-red-200 overflow-hidden">
                                            <div class="h-full bg-red-500 rounded-full" style="width: {{ $expWidth }}%"></div>
                                        </div>
                                    @endif
                                </div>
                            @elseif(!$isFuture)
                                <p class="text-[10px] text-gray-300 mt-2 text-center">—</p>
                            @endif
                        </div>
                    @endfor

                    {{-- Trailing empty cells --}}
                    @php $trailing = (7 - (($calendarData['firstDayOfWeek'] + $calendarData['daysInMonth']) % 7)) % 7; @endphp
                    @for($i = 0; $i < $trailing; $i++)
                        <div class="border-b border-r min-h-[90px] bg-gray-50/50"></div>
                    @endfor
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
