@extends('layouts.supervisor')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Monthly Calendar</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $activePeriod->name }} &middot; {{ $startOfMonth->format('F Y') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('supervisor.revenue_expense.monthly_calendar', ['month' => $prevMonth->month, 'year' => $prevMonth->year]) }}"
               class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm text-gray-600 hover:bg-gray-50">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ $prevMonth->format('M') }}
            </a>
            <span class="text-sm font-semibold text-gray-800 px-2">{{ $startOfMonth->format('F Y') }}</span>
            <a href="{{ route('supervisor.revenue_expense.monthly_calendar', ['month' => $nextMonth->month, 'year' => $nextMonth->year]) }}"
               class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm text-gray-600 hover:bg-gray-50">
                {{ $nextMonth->format('M') }}
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('supervisor.revenue_expense.index') }}"
               class="inline-flex items-center px-3 py-1.5 bg-gray-100 border border-gray-300 rounded-md text-sm text-gray-600 hover:bg-gray-200 ml-2">
                ← Back to Dashboard
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-lg border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total Income</p>
            <p class="text-lg font-bold text-green-600">${{ number_format($monthTotalIncome, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total Expenses</p>
            <p class="text-lg font-bold text-red-600">${{ number_format($monthTotalExpense, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Net Profit/Loss</p>
            <p class="text-lg font-bold {{ $monthNet >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $monthNet >= 0 ? '+' : '' }}${{ number_format($monthNet, 2) }}
            </p>
        </div>
        <div class="bg-white rounded-lg border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Best Day</p>
            @if($bestDay)
                <p class="text-lg font-bold text-gray-800">{{ $startOfMonth->copy()->day($bestDay)->format('M d') }}</p>
                <p class="text-xs text-green-600">+${{ number_format($calendarDays[$bestDay]['net'], 2) }}</p>
            @else
                <p class="text-sm text-gray-400">No data</p>
            @endif
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex items-center gap-4 text-xs text-gray-500">
        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 bg-green-500 rounded-full inline-block"></span> Income</span>
        <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 bg-red-500 rounded-full inline-block"></span> Expense</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 border-2 border-blue-500 rounded inline-block"></span> Today</span>
    </div>

    {{-- Calendar Grid --}}
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        {{-- Day Headers --}}
        <div class="grid grid-cols-7 bg-gray-50 border-b">
            @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                <div class="text-center text-xs font-semibold text-gray-500 py-2 uppercase tracking-wider">{{ $dayName }}</div>
            @endforeach
        </div>

        {{-- Calendar Days --}}
        <div class="grid grid-cols-7">
            {{-- Empty cells for offset --}}
            @for($i = 0; $i < $firstDayOfWeek; $i++)
                <div class="border-b border-r min-h-[100px] bg-gray-50/50"></div>
            @endfor

            {{-- Actual days --}}
            @for($d = 1; $d <= $daysInMonth; $d++)
                @php
                    $dayData = $calendarDays[$d];
                    $hasData = $dayData['tx_count'] > 0;
                    $isToday = $dayData['is_today'];
                    $isFuture = $dayData['is_future'];
                    $cellClasses = 'border-b border-r min-h-[100px] p-1.5 transition';
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
                            <span class="text-[10px] text-gray-400">{{ $dayData['tx_count'] }} tx</span>
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
                            $maxDay = max(array_column($calendarDays, 'income') ?: [1]);
                            $maxExp = max(array_column($calendarDays, 'expense') ?: [1]);
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
            @php $trailing = (7 - (($firstDayOfWeek + $daysInMonth) % 7)) % 7; @endphp
            @for($i = 0; $i < $trailing; $i++)
                <div class="border-b border-r min-h-[100px] bg-gray-50/50"></div>
            @endfor
        </div>
    </div>

    {{-- Daily Breakdown List (scrollable) --}}
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50">
            <h3 class="text-sm font-semibold text-gray-700">Daily Breakdown</h3>
        </div>
        <div class="max-h-80 overflow-y-auto divide-y">
            @for($d = $daysInMonth; $d >= 1; $d--)
                @php $dayData = $calendarDays[$d]; @endphp
                @if($dayData['tx_count'] > 0)
                    <div class="flex items-center justify-between px-4 py-2.5 hover:bg-gray-50 {{ $dayData['is_today'] ? 'bg-blue-50/40' : '' }}">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-medium text-gray-700 w-20">
                                {{ \Carbon\Carbon::parse($dayData['date'])->format('M d, D') }}
                            </span>
                            <span class="text-xs text-gray-400">{{ $dayData['tx_count'] }} transactions</span>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            @if($dayData['income'] > 0)
                                <span class="text-green-600 font-medium">+${{ number_format($dayData['income'], 2) }}</span>
                            @endif
                            @if($dayData['expense'] > 0)
                                <span class="text-red-600 font-medium">-${{ number_format($dayData['expense'], 2) }}</span>
                            @endif
                            <span class="font-semibold {{ $dayData['net'] >= 0 ? 'text-green-700' : 'text-red-700' }} w-24 text-right">
                                {{ $dayData['net'] >= 0 ? '+' : '' }}${{ number_format($dayData['net'], 2) }}
                            </span>
                        </div>
                    </div>
                @endif
            @endfor
        </div>
    </div>
</div>
@endsection
