@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8 max-w-4xl"
     x-data="{ closeOpen: false, withdrawal: '', available: {{ $monthlyPeriod->opening_balance + $financials['net_income'] }} }">
    {{-- Header with navigation --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold">{{ $monthlyPeriod->name }}</h1>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $monthlyPeriod->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ ucfirst($monthlyPeriod->status) }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-1">
                {{ $monthlyPeriod->start_date->format('M d') }} – {{ $monthlyPeriod->end_date->format('M d, Y') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($previousMonth)
                <a href="{{ route('admin.fiscalperiod.monthly-period.show', [$fiscalperiod->id, $previousMonth->id]) }}" class="px-3 py-1.5 bg-gray-100 rounded-lg hover:bg-gray-200 text-sm">← {{ $previousMonth->name }}</a>
            @endif
            @if($nextMonth)
                <a href="{{ route('admin.fiscalperiod.monthly-period.show', [$fiscalperiod->id, $nextMonth->id]) }}" class="px-3 py-1.5 bg-gray-100 rounded-lg hover:bg-gray-200 text-sm">{{ $nextMonth->name }} →</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4"><p class="text-green-800 text-sm">{{ session('success') }}</p></div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4"><p class="text-red-800 text-sm">{{ session('error') }}</p></div>
    @endif

    {{-- Balance Flow --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">Opening</p>
            <p class="text-lg font-bold mt-1">${{ number_format($monthlyPeriod->opening_balance, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">Income</p>
            <p class="text-lg font-bold text-green-600 mt-1">+${{ number_format($financials['total_income'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">Expenses</p>
            <p class="text-lg font-bold text-red-600 mt-1">-${{ number_format($financials['total_expenses'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">Net</p>
            <p class="text-lg font-bold {{ $financials['net_income'] >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                {{ $financials['net_income'] >= 0 ? '+' : '' }}${{ number_format($financials['net_income'], 2) }}
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">Closing</p>
            <p class="text-lg font-bold mt-1">
                @if($monthlyPeriod->isClosed())
                    ${{ number_format($monthlyPeriod->closing_balance, 2) }}
                @else
                    <span class="text-gray-400">${{ number_format($monthlyPeriod->opening_balance + $financials['net_income'], 2) }}</span>
                @endif
            </p>
        </div>
    </div>

    @if($monthlyPeriod->isClosed() && $monthlyPeriod->owner_withdrawal > 0)
        {{-- Owner profit withdrawal (a draw — reduces carried cash, not net income) --}}
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6 flex items-start gap-3">
            <svg class="w-5 h-5 text-purple-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2-4h10a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6a2 2 0 012-2zm7 5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <div class="flex-1">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-purple-800">Owner Profit Withdrawal</p>
                    <p class="text-lg font-bold text-purple-700">− ${{ number_format($monthlyPeriod->owner_withdrawal, 2) }}</p>
                </div>
                @if($monthlyPeriod->withdrawal_note)
                    <p class="text-sm text-purple-700/80 mt-1">{{ $monthlyPeriod->withdrawal_note }}</p>
                @endif
                <p class="text-xs text-purple-600/70 mt-1">Recorded as an owner's draw: it lowers the carried-forward cash balance but is not a business expense and does not change net income.</p>
            </div>
        </div>
    @endif

    {{-- Income & Expense Details --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-sm text-gray-700 mb-3">Income</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-600">Rent</span><span class="font-medium text-green-600">${{ number_format($financials['rent_income'], 2) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600">Late Fees</span><span class="font-medium text-green-600">${{ number_format($financials['late_fees'], 2) }}</span></div>
                @if($financials['other_income'] > 0)
                    <div class="flex justify-between"><span class="text-gray-600">Other</span><span class="font-medium text-green-600">${{ number_format($financials['other_income'], 2) }}</span></div>
                @endif
                <div class="flex justify-between border-t pt-2 font-semibold"><span>Total</span><span class="text-green-700">${{ number_format($financials['total_income'], 2) }}</span></div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-sm text-gray-700 mb-3">Expenses</h3>
            <div class="space-y-2 text-sm">
                @forelse($financials['utility_expenses'] as $type => $amount)
                    <div class="flex justify-between"><span class="text-gray-600 capitalize">{{ str_replace('_', ' ', $type) }}</span><span class="font-medium text-red-600">${{ number_format($amount, 2) }}</span></div>
                @empty
                    <p class="text-gray-400 text-xs">No utility expenses</p>
                @endforelse
                @if($financials['fixed_expenses'] > 0)
                    <div class="flex justify-between"><span class="text-gray-600">Fixed/Other</span><span class="font-medium text-red-600">${{ number_format($financials['fixed_expenses'], 2) }}</span></div>
                @endif
                <div class="flex justify-between border-t pt-2 font-semibold"><span>Total</span><span class="text-red-700">${{ number_format($financials['total_expenses'], 2) }}</span></div>
            </div>
        </div>
    </div>

    {{-- Balance Sheet as of this month end (auto-calculated) --}}
    <div class="bg-white rounded-lg shadow p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="font-semibold text-sm text-gray-700">Balance Sheet — as of {{ $monthlyPeriod->end_date->format('M d, Y') }}</h3>
            <span class="text-xs {{ $balanceSheet['balance_check'] ? 'text-green-600' : 'text-amber-600' }}">
                {{ $balanceSheet['balance_check'] ? '✓ Balanced' : '⚠ Out of balance' }}
            </span>
        </div>
        <div class="grid grid-cols-3 gap-4 text-center">
            <div>
                <p class="text-xs text-gray-500 uppercase">Assets</p>
                <p class="text-lg font-bold text-blue-600">${{ number_format($balanceSheet['total_assets'], 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase">Liabilities</p>
                <p class="text-lg font-bold text-red-600">${{ number_format($balanceSheet['total_liabilities'], 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase">Equity</p>
                <p class="text-lg font-bold text-green-600">${{ number_format($balanceSheet['total_equity'], 2) }}</p>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3 text-center">
            Opening equity ${{ number_format($balanceSheet['opening_equity'], 2) }}
            + retained earnings ${{ number_format($balanceSheet['retained_earnings'], 2) }}
            − owner draws ${{ number_format($balanceSheet['owner_withdrawals'], 2) }}.
        </p>
    </div>

    {{-- Actions --}}
    <div class="flex flex-wrap gap-2">
        @if($monthlyPeriod->canClose())
            <button type="button" @click="closeOpen = true; withdrawal = ''"
                    class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 text-sm font-semibold">Close Month</button>
        @endif
        @if($monthlyPeriod->canReopen())
            <form method="POST" action="{{ route('admin.fiscalperiod.monthly-period.reopen', [$fiscalperiod->id, $monthlyPeriod->id]) }}" onsubmit="return confirm('Reopen {{ $monthlyPeriod->name }}?')">
                @csrf
                <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-semibold">Reopen</button>
            </form>
        @endif
        {{-- Print Monthly PDF --}}
        <a href="{{ route('admin.fiscalperiod.monthly-period.print', [$fiscalperiod->id, $monthlyPeriod->id]) }}" target="_blank"
           class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 text-sm font-semibold flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Print Summary
        </a>
        <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 text-sm font-semibold">← Back to Period</a>
    </div>

    {{-- Close-month modal: capture owner profit withdrawal --}}
    @if($monthlyPeriod->canClose())
    <div x-show="closeOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @keydown.escape.window="closeOpen = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md" @click.outside="closeOpen = false">
            <form method="POST" action="{{ route('admin.fiscalperiod.monthly-period.close', [$fiscalperiod->id, $monthlyPeriod->id]) }}">
                @csrf
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold">Close {{ $monthlyPeriod->name }}</h3>
                    <p class="text-sm text-gray-500 mt-1">Freeze this month's totals and carry the closing balance forward.</p>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-500">Net Income (Profit)</p>
                            <p class="font-bold mt-0.5 {{ $financials['net_income'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $financials['net_income'] >= 0 ? '+' : '' }}${{ number_format($financials['net_income'], 2) }}
                            </p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-500">Cash Available</p>
                            <p class="font-bold mt-0.5">${{ number_format($monthlyPeriod->opening_balance + $financials['net_income'], 2) }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Owner Profit Withdrawal</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                            <input type="number" name="owner_withdrawal" step="0.01" min="0" :max="Math.max(0, available)"
                                   x-model="withdrawal" placeholder="0.00"
                                   class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Money paid out to the owner. This is recorded as an owner's draw — it lowers the carried-forward
                            cash balance but does <span class="font-semibold">not</span> count as a business expense or change net income.
                        </p>
                        <p class="text-xs text-red-600 mt-1" x-show="parseFloat(withdrawal || 0) > available">
                            Amount exceeds the cash available for this month.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note <span class="text-gray-400 font-normal">(optional)</span></label>
                        <textarea name="withdrawal_note" rows="2" placeholder="e.g. Monthly profit distribution to owner"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"></textarea>
                    </div>
                </div>

                <div class="px-6 py-4 border-t flex justify-end gap-2">
                    <button type="button" @click="closeOpen = false"
                            class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 text-sm font-semibold">Cancel</button>
                    <button type="submit"
                            :disabled="parseFloat(withdrawal || 0) > available"
                            class="px-4 py-2 rounded-lg bg-amber-600 text-white hover:bg-amber-700 text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                        Close Month
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
