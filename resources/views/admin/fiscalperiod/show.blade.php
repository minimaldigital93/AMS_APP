@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8 max-w-5xl"
     x-data="{ closeModal: { open: false, action: '', name: '', net: 0, available: 0, amount: '' } }">
    {{-- Header --}}
    <div class="flex items-start justify-between mb-6 gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ $fiscalperiod->name }}</h1>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $fiscalperiod->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ ucfirst($fiscalperiod->status) }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-1">
                {{ $fiscalperiod->opening_date->format('M d, Y') }} — {{ $fiscalperiod->closing_date->format('M d, Y') }}
                ({{ $fiscalperiod->opening_date->diffInDays($fiscalperiod->closing_date) }} days)
            </p>
        </div>
        <div class="flex flex-wrap gap-2 justify-end">
            @if($fiscalperiod->status === 'open')
                <a href="{{ route('admin.fiscalperiod.edit', $fiscalperiod->id) }}" class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200">{{ __('messages.edit') }}</a>
            @endif
            <a href="{{ route('admin.fiscalperiod.reports', $fiscalperiod->id) }}" class="text-sm bg-indigo-600 text-white px-3 py-2 rounded-lg hover:bg-indigo-700">{{ __('messages.reports') }}</a>
            <a href="{{ route('admin.fiscalperiod.exportPDF', $fiscalperiod->id) }}" target="_blank"
               class="text-sm bg-gray-700 text-white px-3 py-2 rounded-lg hover:bg-gray-800 flex items-center gap-1.5" title="{{ __('messages.print_annual_summary_pdf') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg></a>
            <a href="{{ route('admin.fiscalperiod.index') }}" class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200">← Back</a>
        </div>
    </div>


    {{-- Fiscal period time progress --}}
    @php
        $periodStart   = $fiscalperiod->opening_date;
        $periodEnd     = $fiscalperiod->closing_date;
        $periodToday   = now();
        $totalDays     = max(1, $periodStart->diffInDays($periodEnd));
        $elapsedDays   = $periodToday->lt($periodStart)
            ? 0
            : min($totalDays, $periodStart->diffInDays($periodToday->gt($periodEnd) ? $periodEnd : $periodToday));
        $periodPercent = round(($elapsedDays / $totalDays) * 100);
        $remainingDays = max(0, round($totalDays - $elapsedDays));
    @endphp
    <div class="bg-white rounded-lg shadow p-5 mb-4">
        <div class="flex items-center justify-between mb-2">
            <h3 class="font-semibold text-sm text-gray-700">{{ __('messages.period_progress') }}</h3>
            <span class="text-sm font-bold text-gray-700">{{ $periodPercent }}%</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
            <div class="h-2.5 rounded-full {{ $periodPercent >= 100 ? 'bg-red-500' : ($periodPercent >= 80 ? 'bg-amber-500' : 'bg-indigo-500') }}"
                 style="width: {{ $periodPercent }}%"></div>
        </div>
        <div class="flex items-center justify-between mt-2 text-xs text-gray-500">
            <span>{{ $periodStart->format('M d, Y') }}</span>
            <span>
                @if($periodToday->gt($periodEnd))
                    {{ __('messages.period_ended') }}
                @else
                    {{ $remainingDays }} {{ __('messages.days_remaining') }}
                @endif
            </span>
            <span>{{ $periodEnd->format('M d, Y') }}</span>
        </div>
    </div>

    {{-- Income & Expense Breakdown (totals shown inline in each header) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div class="bg-white rounded-lg shadow p-5">
            <div class="flex items-baseline justify-between mb-3">
                <h3 class="font-semibold text-sm text-gray-700">{{ __('messages.income') }}</h3>
                <span class="text-xl font-bold text-green-600">${{ number_format($financialData['total_income'], 2) }}</span>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.rent') }}</span><span class="font-medium">${{ number_format($financialData['rent_income'], 2) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.late_fees') }}</span><span class="font-medium">${{ number_format($financialData['late_fees'], 2) }}</span></div>
                @if($financialData['other_income'] > 0)
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.type_other') }}</span><span class="font-medium">${{ number_format($financialData['other_income'], 2) }}</span></div>
                @endif
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <div class="flex items-baseline justify-between mb-3">
                <h3 class="font-semibold text-sm text-gray-700">{{ __('messages.expenses_word') }}</h3>
                <span class="text-xl font-bold text-red-600">${{ number_format($financialData['total_expenses'], 2) }}</span>
            </div>
            <div class="space-y-2 text-sm">
                @forelse($financialData['utility_expenses'] as $type => $amount)
                    <div class="flex justify-between"><span class="text-gray-600 capitalize">{{ str_replace('_', ' ', $type) }}</span><span class="font-medium">${{ number_format($amount, 2) }}</span></div>
                @empty
                    <p class="text-gray-400 text-xs">{{ __('messages.no_utility_expenses') }}</p>
                @endforelse
                @if($financialData['fixed_expenses'] > 0)
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.fixed_other') }}</span><span class="font-medium">${{ number_format($financialData['fixed_expenses'], 2) }}</span></div>
                @endif
            </div>
        </div>
    </div>

    {{-- Opening balance + net result --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 uppercase">{{ __('messages.opening_balance') }}</p>
            <p class="text-xl font-bold mt-1">${{ number_format($fiscalperiod->opening_balance, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 uppercase">Net {{ $financialData['is_profitable'] ? 'Profit' : 'Loss' }}</p>
            <p class="text-xl font-bold {{ $financialData['is_profitable'] ? 'text-green-600' : 'text-red-600' }} mt-1">${{ number_format(abs($financialData['net_income']), 2) }}</p>
        </div>
    </div>

    {{-- Monthly Periods --}}
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="flex items-center justify-between px-5 py-3 border-b">
            <div>
                <h3 class="font-semibold">{{ __('messages.monthly_periods') }}</h3>
                <p class="text-xs text-gray-400">{{ __('messages.close_each_month_help') }}</p>
            </div>
            @if($fiscalperiod->status === 'open')
                <form method="POST" action="{{ route('admin.fiscalperiod.recalculate-balances', $fiscalperiod->id) }}" data-confirm="Recalculate all monthly balances using carry-forward logic?">
                    @csrf
                    <button type="submit" class="text-xs bg-amber-100 text-amber-700 px-3 py-1.5 rounded hover:bg-amber-200 font-semibold">↻ Recalculate</button>
                </form>
            @endif
        </div>
        @if($monthlyPeriods->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase">
                            <th class="px-4 py-2 text-left">{{ __('messages.month') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('messages.opening') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('messages.income') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('messages.expenses_word') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('messages.net') }}</th>
                            <th class="px-4 py-2 text-right">{{ __('messages.closing') }}</th>
                            <th class="px-4 py-2 text-center">{{ __('messages.status') }}</th>
                            <th class="px-4 py-2 text-center">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($monthlyPeriods as $month)
                            <tr class="hover:bg-gray-50 {{ $month->isClosed() ? 'bg-gray-50/40' : '' }}">
                                <td class="px-4 py-2.5 font-medium">{{ $month->name }}</td>
                                <td class="px-4 py-2.5 text-right">${{ number_format($month->opening_balance, 2) }}</td>
                                <td class="px-4 py-2.5 text-right text-green-600">+${{ number_format($month->live_income, 2) }}</td>
                                <td class="px-4 py-2.5 text-right text-red-600">-${{ number_format($month->live_expenses, 2) }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold {{ $month->live_net >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $month->live_net >= 0 ? '+' : '' }}${{ number_format($month->live_net, 2) }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    @if($month->isClosed())
                                        ${{ number_format($month->closing_balance, 2) }}
                                        @if($month->owner_withdrawal > 0)
                                            <div class="text-xs text-purple-600 font-normal" title="{{ $month->withdrawal_note }}">
                                                − ${{ number_format($month->owner_withdrawal, 2) }} drawn
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-gray-400">${{ number_format($month->opening_balance + $month->live_net, 2) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $month->status === 'open' ? 'bg-green-100 text-green-700' : ($month->status === 'closed' ? 'bg-red-100 text-red-700' : 'bg-gray-200 text-gray-700') }}">
                                        {{ ucfirst($month->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center justify-center gap-1">
                                        {{-- View --}}
                                        <a href="{{ route('admin.fiscalperiod.monthly-period.show', [$fiscalperiod->id, $month->id]) }}"
                                           class="p-1.5 rounded text-blue-600 hover:bg-blue-50 transition" title="{{ __('messages.view_details') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>

                                        @if($month->canClose())
                                            {{-- Close Month (opens withdrawal modal) --}}
                                            <button type="button"
                                                    @click="closeModal = { open: true, action: '{{ route('admin.fiscalperiod.monthly-period.close', [$fiscalperiod->id, $month->id]) }}', name: @js($month->name), net: {{ $month->live_net }}, available: {{ $month->opening_balance + $month->live_net }}, amount: '' }"
                                                    class="p-1.5 rounded text-amber-600 hover:bg-amber-50 transition" title="{{ __('messages.close_month') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            </button>
                                        @endif

                                        @if($month->canReopen())
                                            {{-- Reopen Month --}}
                                            <form method="POST" action="{{ route('admin.fiscalperiod.monthly-period.reopen', [$fiscalperiod->id, $month->id]) }}"
                                                  data-confirm="Reopen {{ $month->name }}? This clears the recorded owner withdrawal.">
                                                @csrf
                                                <button type="submit" class="p-1.5 rounded text-green-600 hover:bg-green-50 transition" title="{{ __('messages.reopen_month') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Print Monthly PDF --}}
                                        <a href="{{ route('admin.fiscalperiod.monthly-period.print', [$fiscalperiod->id, $month->id]) }}" target="_blank"
                                           class="p-1.5 rounded text-gray-500 hover:bg-gray-100 transition" title="{{ __('messages.print_summary_pdf') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-5 text-sm text-gray-400 text-center">{{ __('messages.no_monthly_periods') }}</p>
        @endif
    </div>

    {{-- Close Period (only if open) --}}
    @if($fiscalperiod->status === 'open')
        <div class="bg-white rounded-lg shadow p-5 mb-6">
            <h3 class="font-semibold mb-3">{{ __('messages.close_this_period') }}</h3>
            <form method="POST" action="{{ route('admin.fiscalperiod.closeperiod', $fiscalperiod->id) }}" data-confirm="Close this fiscal period? This cannot be undone.">
                @csrf
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <label class="block text-xs text-gray-500 mb-1">{{ __('messages.closing_balance') }}</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input type="number" name="closing_balance" step="0.01" required
                                value="{{ $balanceSummary['total_assets'] - $balanceSummary['total_liabilities'] }}"
                                class="w-full pl-7 pr-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Suggested: ${{ number_format($balanceSummary['total_assets'] - $balanceSummary['total_liabilities'], 2) }} (Assets − Liabilities)</p>
                    </div>
                    <button type="submit" class="bg-orange-600 text-white px-5 py-2 rounded-lg hover:bg-orange-700 text-sm font-semibold whitespace-nowrap">
                        Close Period
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Delete (only if not closed) --}}
    @if($fiscalperiod->status !== 'closed')
        <div class="text-center">
            <form method="POST" action="{{ route('admin.fiscalperiod.destroy', $fiscalperiod->id) }}" data-confirm="Delete this fiscal period? This cannot be undone.">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm text-red-500 hover:text-red-700 hover:underline">{{ __('messages.delete_this_period') }}</button>
            </form>
        </div>
    @endif

    {{-- Close-month modal: capture owner profit withdrawal --}}
    <div x-show="closeModal.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @keydown.escape.window="closeModal.open = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md" @click.outside="closeModal.open = false">
            <form method="POST" :action="closeModal.action">
                @csrf
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold">{{ __('messages.close') }} <span x-text="closeModal.name"></span></h3>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-500">{{ __('messages.net_income_profit') }}</p>
                            <p class="font-bold mt-0.5" :class="closeModal.net >= 0 ? 'text-green-600' : 'text-red-600'"
                               x-text="(closeModal.net >= 0 ? '+$' : '-$') + Math.abs(closeModal.net).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-500">{{ __('messages.cash_available') }}</p>
                            <p class="font-bold mt-0.5" x-text="'$' + closeModal.available.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.owner_withdrawal') }} <span class="text-gray-400 font-normal">(optional)</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                            <input type="number" name="owner_withdrawal" step="0.01" min="0" :max="Math.max(0, closeModal.available)"
                                   x-model="closeModal.amount" placeholder="0.00"
                                   class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <p class="text-xs text-red-600 mt-1" x-show="parseFloat(closeModal.amount || 0) > closeModal.available">
                            Exceeds available cash.
                        </p>
                    </div>

                    <div>
                        <input type="text" name="withdrawal_note" placeholder="{{ __('messages.note_optional') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                    </div>
                </div>

                <div class="px-6 py-4 border-t flex justify-end gap-2">
                    <button type="button" @click="closeModal.open = false"
                            class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 text-sm font-semibold">{{ __('messages.cancel') }}</button>
                    <button type="submit"
                            :disabled="parseFloat(closeModal.amount || 0) > closeModal.available"
                            class="px-4 py-2 rounded-lg bg-amber-600 text-white hover:bg-amber-700 text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ __('messages.close_month') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
