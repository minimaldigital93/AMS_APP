@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold">{{ $monthlyPeriod->name }}</h1>
            <p class="text-gray-600 mt-1">
                {{ $fiscalperiod->name }} &middot;
                {{ $monthlyPeriod->start_date->format('M d') }} – {{ $monthlyPeriod->end_date->format('M d, Y') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($previousMonth)
                <a href="{{ route('admin.fiscalperiod.monthly-period.show', [$fiscalperiod->id, $previousMonth->id]) }}"
                   class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition" title="{{ $previousMonth->name }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @endif
            <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $monthlyPeriod->status === 'open' ? 'bg-green-100 text-green-800' : ($monthlyPeriod->status === 'closed' ? 'bg-red-100 text-red-800' : 'bg-gray-200 text-gray-800') }}">
                {{ ucfirst($monthlyPeriod->status) }}
            </span>
            @if($nextMonth)
                <a href="{{ route('admin.fiscalperiod.monthly-period.show', [$fiscalperiod->id, $nextMonth->id]) }}"
                   class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition" title="{{ $nextMonth->name }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-green-800">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <p class="text-red-800">{{ session('error') }}</p>
        </div>
    @endif

    <!-- Balance Flow -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-5 text-center border-t-4 border-blue-600">
            <p class="text-xs text-gray-500 uppercase font-semibold">Opening Balance</p>
            <p class="text-xl font-bold mt-2">${{ number_format($monthlyPeriod->opening_balance, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 text-center border-t-4 border-green-600">
            <p class="text-xs text-gray-500 uppercase font-semibold">Total Income</p>
            <p class="text-xl font-bold mt-2 text-green-600">+${{ number_format($financials['total_income'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 text-center border-t-4 border-red-600">
            <p class="text-xs text-gray-500 uppercase font-semibold">Total Expenses</p>
            <p class="text-xl font-bold mt-2 text-red-600">-${{ number_format($financials['total_expenses'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 text-center border-t-4 {{ $financials['net_income'] >= 0 ? 'border-emerald-600' : 'border-orange-600' }}">
            <p class="text-xs text-gray-500 uppercase font-semibold">Net Income</p>
            <p class="text-xl font-bold mt-2 {{ $financials['net_income'] >= 0 ? 'text-emerald-600' : 'text-orange-600' }}">
                {{ $financials['net_income'] >= 0 ? '+' : '' }}${{ number_format($financials['net_income'], 2) }}
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 text-center border-t-4 border-indigo-600">
            <p class="text-xs text-gray-500 uppercase font-semibold">Closing Balance</p>
            <p class="text-xl font-bold mt-2">
                @if($monthlyPeriod->isClosed())
                    ${{ number_format($monthlyPeriod->closing_balance, 2) }}
                @else
                    <span class="text-gray-400">${{ number_format($monthlyPeriod->opening_balance + $financials['net_income'], 2) }}</span>
                    <span class="text-xs text-gray-400 block">(projected)</span>
                @endif
            </p>
        </div>
    </div>

    <!-- Income & Expense Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Income Breakdown -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-green-500"></span>
                Income Breakdown
            </h2>
            <div class="space-y-3">
                <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                    <span class="text-sm text-gray-700">Rent Payments</span>
                    <div class="text-right">
                        <span class="text-sm font-bold text-green-600">${{ number_format($financials['rent_income'], 2) }}</span>
                        <span class="text-xs text-gray-500 block">{{ $financials['payment_count'] }} payments</span>
                    </div>
                </div>
                <div class="flex justify-between items-center p-3 bg-yellow-50 rounded">
                    <span class="text-sm text-gray-700">Late Fees</span>
                    <span class="text-sm font-bold text-yellow-600">${{ number_format($financials['late_fees'], 2) }}</span>
                </div>
                @if($financials['other_income'] > 0)
                    <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                        <span class="text-sm text-gray-700">Other Income</span>
                        <span class="text-sm font-bold text-blue-600">${{ number_format($financials['other_income'], 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between items-center p-3 bg-white rounded border-2 border-green-500 mt-2">
                    <span class="text-sm font-bold">Total Income</span>
                    <span class="text-lg font-bold text-green-700">${{ number_format($financials['total_income'], 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Expense Breakdown -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-red-500"></span>
                Expense Breakdown
            </h2>
            <div class="space-y-3">
                @forelse($financials['utility_expenses'] as $type => $amount)
                    <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                        <span class="text-sm text-gray-700">
                            @switch($type)
                                @case('electricity') 🔌 Electricity @break
                                @case('water') 💧 Water @break
                                @case('internet') 📡 Internet @break
                                @case('parking') 🅿️ Parking @break
                                @default {{ ucfirst($type) }}
                            @endswitch
                        </span>
                        <span class="text-sm font-bold text-red-600">${{ number_format($amount, 2) }}</span>
                    </div>
                @empty
                    <div class="text-center py-2 text-gray-400 text-sm">No utility expenses</div>
                @endforelse
                @if($financials['fixed_expenses'] > 0)
                    <div class="flex justify-between items-center p-3 bg-orange-50 rounded">
                        <span class="text-sm text-gray-700">📋 Fixed/Other Expenses</span>
                        <span class="text-sm font-bold text-orange-600">${{ number_format($financials['fixed_expenses'], 2) }}</span>
                    </div>
                @endif
                <div class="flex justify-between items-center p-3 bg-white rounded border-2 border-red-500 mt-2">
                    <span class="text-sm font-bold">Total Expenses</span>
                    <span class="text-lg font-bold text-red-700">${{ number_format($financials['total_expenses'], 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Period Status Info -->
    @if($monthlyPeriod->isClosed())
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-5 mb-8">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="font-semibold text-blue-900">Month Closed</p>
                    <p class="text-sm text-blue-700 mt-1">
                        Closed on {{ $monthlyPeriod->closed_at->format('M d, Y \a\t h:i A') }}.
                        Closing balance of <strong>${{ number_format($monthlyPeriod->closing_balance, 2) }}</strong>
                        @if($nextMonth)
                            was carried forward to {{ $nextMonth->name }}.
                        @else
                            (final month).
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Actions -->
    <div class="flex flex-wrap gap-3">
        @if($monthlyPeriod->canClose())
            <form method="POST" action="{{ route('admin.fiscalperiod.monthly-period.close', [$fiscalperiod->id, $monthlyPeriod->id]) }}"
                  onsubmit="return confirm('Close {{ $monthlyPeriod->name }}?')">
                @csrf
                <button type="submit" class="bg-amber-600 text-white px-5 py-2.5 rounded-lg hover:bg-amber-700 transition font-semibold text-sm">
                    Close This Month
                </button>
            </form>
        @endif
        @if($monthlyPeriod->canReopen())
            <form method="POST" action="{{ route('admin.fiscalperiod.monthly-period.reopen', [$fiscalperiod->id, $monthlyPeriod->id]) }}"
                  onsubmit="return confirm('Reopen {{ $monthlyPeriod->name }}?')">
                @csrf
                <button type="submit" class="bg-green-600 text-white px-5 py-2.5 rounded-lg hover:bg-green-700 transition font-semibold text-sm">
                    Reopen This Month
                </button>
            </form>
        @endif
        <a href="{{ route('admin.fiscalperiod.monthly-periods', $fiscalperiod->id) }}" class="bg-gray-400 text-white px-5 py-2.5 rounded-lg hover:bg-gray-500 transition font-semibold text-sm">
            ← All Monthly Periods
        </a>
        <a href="{{ route('admin.fiscalperiod.reports', $fiscalperiod->id) }}" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition font-semibold text-sm">
            View Reports
        </a>
    </div>
</div>
@endsection
