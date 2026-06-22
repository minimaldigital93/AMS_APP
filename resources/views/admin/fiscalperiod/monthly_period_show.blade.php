@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8 max-w-4xl"
     x-data="{ closeOpen: false, withdrawal: '', available: {{ $monthlyPeriod->opening_balance + $financials['net_income'] }} }">
    {{-- Header with navigation --}}
    <div class="flex items-start justify-between mb-6 gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ $monthlyPeriod->name }}</h1>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $monthlyPeriod->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ ucfirst($monthlyPeriod->status) }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-1">
                {{ $monthlyPeriod->start_date->format('M d') }} – {{ $monthlyPeriod->end_date->format('M d, Y') }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2 justify-end">
            @if($monthlyPeriod->canClose())
                {{-- Close Month (opens withdrawal modal) --}}
                <button type="button" @click="closeOpen = true; withdrawal = ''"
                        class="text-sm bg-amber-600 text-white px-3 py-2 rounded-lg hover:bg-amber-700 flex items-center" title="{{ __('messages.close_month') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </button>
            @endif
            @if($monthlyPeriod->canReopen())
                {{-- Reopen Month --}}
                <form method="POST" action="{{ route('admin.fiscalperiod.monthly-period.reopen', [$fiscalperiod->id, $monthlyPeriod->id]) }}" data-confirm="Reopen {{ $monthlyPeriod->name }}?">
                    @csrf
                    <button class="text-sm bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 flex items-center" title="{{ __('messages.reopen_month') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                    </button>
                </form>
            @endif
            {{-- Print Monthly PDF --}}
            <a href="{{ route('admin.fiscalperiod.monthly-period.print', [$fiscalperiod->id, $monthlyPeriod->id]) }}" target="_blank"
               class="text-sm bg-gray-700 text-white px-3 py-2 rounded-lg hover:bg-gray-800 flex items-center" title="{{ __('messages.print_summary_pdf') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg></a>
            {{-- Back to period --}}
            <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}"
               class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200 flex items-center" title="{{ __('messages.back') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        </div>
    </div>


    {{-- Month navigator --}}
    <div class="flex items-center justify-center mb-6">
        <div class="inline-flex items-center bg-white rounded-xl border border-slate-100 px-2 py-1.5 gap-1">
            {{-- Previous Month --}}
            @if($previousMonth)
                <a href="{{ route('admin.fiscalperiod.monthly-period.show', [$fiscalperiod->id, $previousMonth->id]) }}"
                   class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ $previousMonth->name }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @else
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-300 cursor-not-allowed">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </span>
            @endif

            {{-- Current Month Display --}}
            <div class="px-4 py-2 min-w-[200px] text-center">
                <span class="text-lg font-bold text-slate-800">{{ $monthlyPeriod->name }}</span>
                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $monthlyPeriod->status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                    {{ ucfirst($monthlyPeriod->status) }}
                </span>
            </div>

            {{-- Next Month --}}
            @if($nextMonth)
                <a href="{{ route('admin.fiscalperiod.monthly-period.show', [$fiscalperiod->id, $nextMonth->id]) }}"
                   class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ $nextMonth->name }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-300 cursor-not-allowed">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            @endif
        </div>
    </div>

    {{-- Balance Flow --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">{{ __('messages.opening') }}</p>
            <p class="text-lg font-bold mt-1">${{ number_format($monthlyPeriod->opening_balance, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">{{ __('messages.income') }}</p>
            <p class="text-lg font-bold text-green-600 mt-1">+${{ number_format($financials['total_income'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">{{ __('messages.expenses_word') }}</p>
            <p class="text-lg font-bold text-red-600 mt-1">-${{ number_format($financials['total_expenses'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">{{ __('messages.net') }}</p>
            <p class="text-lg font-bold {{ $financials['net_income'] >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                {{ $financials['net_income'] >= 0 ? '+' : '' }}${{ number_format($financials['net_income'], 2) }}
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500">{{ __('messages.closing') }}</p>
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
                    <p class="text-sm font-semibold text-purple-800">{{ __('messages.owner_profit_withdrawal') }}</p>
                    <p class="text-lg font-bold text-purple-700">− ${{ number_format($monthlyPeriod->owner_withdrawal, 2) }}</p>
                </div>
                @if($monthlyPeriod->withdrawal_note)
                    <p class="text-sm text-purple-700/80 mt-1">{{ $monthlyPeriod->withdrawal_note }}</p>
                @endif
                <p class="text-xs text-purple-600/70 mt-1">{{ __('messages.owner_draw_help') }}</p>
            </div>
        </div>
    @endif

    {{-- Income & Expense Details --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-sm text-gray-700 mb-3">{{ __('messages.income') }}</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.rent') }}</span><span class="font-medium text-green-600">${{ number_format($financials['rent_income'], 2) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.late_fees') }}</span><span class="font-medium text-green-600">${{ number_format($financials['late_fees'], 2) }}</span></div>
                @if($financials['other_income'] > 0)
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.type_other') }}</span><span class="font-medium text-green-600">${{ number_format($financials['other_income'], 2) }}</span></div>
                @endif
                <div class="flex justify-between border-t pt-2 font-semibold"><span>{{ __('messages.total') }}</span><span class="text-green-700">${{ number_format($financials['total_income'], 2) }}</span></div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-sm text-gray-700 mb-3">{{ __('messages.expenses_word') }}</h3>
            <div class="space-y-2 text-sm">
                @forelse($financials['utility_expenses'] as $type => $amount)
                    <div class="flex justify-between"><span class="text-gray-600 capitalize">{{ str_replace('_', ' ', $type) }}</span><span class="font-medium text-red-600">${{ number_format($amount, 2) }}</span></div>
                @empty
                    <p class="text-gray-400 text-xs">{{ __('messages.no_utility_expenses') }}</p>
                @endforelse
                @if($financials['fixed_expenses'] > 0)
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.fixed_other') }}</span><span class="font-medium text-red-600">${{ number_format($financials['fixed_expenses'], 2) }}</span></div>
                @endif
                <div class="flex justify-between border-t pt-2 font-semibold"><span>{{ __('messages.total') }}</span><span class="text-red-700">${{ number_format($financials['total_expenses'], 2) }}</span></div>
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
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.assets') }}</p>
                <p class="text-lg font-bold text-sky-600">${{ number_format($balanceSheet['total_assets'], 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.liabilities') }}</p>
                <p class="text-lg font-bold text-red-600">${{ number_format($balanceSheet['total_liabilities'], 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase">{{ __('messages.equity') }}</p>
                <p class="text-lg font-bold text-green-600">${{ number_format($balanceSheet['total_equity'], 2) }}</p>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3 text-center">
            Opening equity ${{ number_format($balanceSheet['opening_equity'], 2) }}
            + retained earnings ${{ number_format($balanceSheet['retained_earnings'], 2) }}
            − owner draws ${{ number_format($balanceSheet['owner_withdrawals'], 2) }}.
        </p>
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
                    <p class="text-sm text-gray-500 mt-1">{{ __('messages.freeze_month_help') }}</p>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-500">{{ __('messages.net_income_profit') }}</p>
                            <p class="font-bold mt-0.5 {{ $financials['net_income'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $financials['net_income'] >= 0 ? '+' : '' }}${{ number_format($financials['net_income'], 2) }}
                            </p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-500">{{ __('messages.cash_available') }}</p>
                            <p class="font-bold mt-0.5">${{ number_format($monthlyPeriod->opening_balance + $financials['net_income'], 2) }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.owner_profit_withdrawal') }}</label>
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.note') }} <span class="text-gray-400 font-normal">(optional)</span></label>
                        <textarea name="withdrawal_note" rows="2" placeholder="{{ __('messages.eg_owner_dist') }}"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"></textarea>
                    </div>
                </div>

                <div class="px-6 py-4 border-t flex justify-end gap-2">
                    <button type="button" @click="closeOpen = false"
                            class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 text-sm font-semibold">{{ __('messages.cancel') }}</button>
                    <button type="submit"
                            :disabled="parseFloat(withdrawal || 0) > available"
                            class="px-4 py-2 rounded-lg bg-amber-600 text-white hover:bg-amber-700 text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ __('messages.close_month') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
