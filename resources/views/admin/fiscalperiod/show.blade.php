@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8 max-w-5xl"
     x-data="{ closeModal: { open: false, action: '', name: '', net: 0, available: 0, amount: '' }, closePeriodOpen: false }">
    {{-- Header --}}
    <div class="flex items-start justify-between mb-6 gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ $fiscalperiod->name }}</h1>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $fiscalperiod->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ status_label($fiscalperiod->status) }}
                </span>
            </div>
          
        </div>
        <div class="flex flex-wrap gap-2 justify-end">
            @if($fiscalperiod->status === 'open')
                <a href="{{ route('admin.fiscalperiod.edit', $fiscalperiod->id) }}" class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200 flex items-center" title="{{ __('messages.edit') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></a>
            @endif
            <a href="{{ route('admin.fiscalperiod.reports', $fiscalperiod->id) }}" class="text-sm bg-slate-800 text-white px-3 py-2 rounded-lg hover:bg-slate-700 flex items-center" title="{{ __('messages.reports') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg></a>
            <a href="{{ route('admin.fiscalperiod.exportPDF', $fiscalperiod->id) }}" target="_blank"
               class="text-sm bg-gray-700 text-white px-3 py-2 rounded-lg hover:bg-gray-800 flex items-center gap-1.5" title="{{ __('messages.print_annual_summary_pdf') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg></a>
            <a href="{{ route('admin.fiscalperiod.index') }}" class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200 flex items-center" title="{{ __('messages.back') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
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
            <div class="h-2.5 rounded-full {{ $periodPercent >= 100 ? 'bg-red-500' : ($periodPercent >= 80 ? 'bg-amber-500' : 'bg-sky-500') }}"
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
                <span class="text-xl font-bold text-green-600">{{ money($financialData['total_income']) }}</span>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.rent') }}</span><span class="font-medium">{{ money($financialData['rent_income']) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.late_fees') }}</span><span class="font-medium">{{ money($financialData['late_fees']) }}</span></div>
                @if($financialData['other_income'] > 0)
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.type_other') }}</span><span class="font-medium">{{ money($financialData['other_income']) }}</span></div>
                @endif
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <div class="flex items-baseline justify-between mb-3">
                <h3 class="font-semibold text-sm text-gray-700">{{ __('messages.expenses_word') }}</h3>
                <span class="text-xl font-bold text-red-600">{{ money($financialData['total_expenses']) }}</span>
            </div>
            <div class="space-y-2 text-sm">
                @forelse($financialData['utility_expenses'] as $type => $amount)
                    <div class="flex justify-between"><span class="text-gray-600 capitalize">{{ str_replace('_', ' ', $type) }}</span><span class="font-medium">{{ money($amount) }}</span></div>
                @empty
                    <p class="text-gray-400 text-xs">{{ __('messages.no_utility_expenses') }}</p>
                @endforelse
                @if($financialData['fixed_expenses'] > 0)
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.fixed_other') }}</span><span class="font-medium">{{ money($financialData['fixed_expenses']) }}</span></div>
                @endif
            </div>
        </div>
    </div>

    {{-- Opening balance + net result --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 uppercase">{{ __('messages.opening_balance') }}</p>
            <p class="text-xl font-bold mt-1">{{ money($periodOpening) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 uppercase">Net {{ $financialData['is_profitable'] ? 'Profit' : 'Loss' }}</p>
            <p class="text-xl font-bold {{ $financialData['is_profitable'] ? 'text-green-600' : 'text-red-600' }} mt-1">{{ money(abs($financialData['net_income'])) }}</p>
        </div>
    </div>

    @if($showingAll)
        {{-- Consolidated view: figures span every property and the cash
             carry-forward / owner draws / month-close are account-wide. --}}
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6 flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div class="flex-1">
                <p class="text-sm font-semibold text-amber-800">{{ __('messages.all_properties_consolidated') }}</p>
                <p class="text-sm text-amber-700/90 mt-1">{{ __('messages.mp_consolidated_notice') }}</p>
            </div>
        </div>
    @endif

    {{-- Monthly Periods --}}
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="flex items-center justify-between px-5 py-3 border-b">
            <h3 class="font-semibold">{{ __('messages.monthly_periods') }}</h3>
            @if($consolidated && $fiscalperiod->status === 'open')
                <form method="POST" action="{{ route('admin.fiscalperiod.recalculate-balances', $fiscalperiod->id) }}" data-confirm="Recalculate all monthly balances using carry-forward logic?">
                    @csrf
                    <button type="submit" class="p-2 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200" title="Recalculate">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </button>
                </form>
            @endif
        </div>
        @if($monthlyPeriods->count())
            {{-- Mobile: compact card per month with close + view-detail actions --}}
            <div class="md:hidden divide-y">
                @foreach($monthlyPeriods as $month)
                    <div class="flex items-center justify-between gap-3 px-4 py-3 {{ $month->isClosed() ? 'bg-gray-50/40' : '' }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-sm">{{ $month->name }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $month->status === 'open' ? 'bg-green-100 text-green-700' : ($month->status === 'closed' ? 'bg-red-100 text-red-700' : 'bg-gray-200 text-gray-700') }}">
                                    {{ status_label($month->status) }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ __('messages.net') }}:
                                <span class="font-semibold {{ $month->live_net >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $month->live_net >= 0 ? '+' : '' }}{{ money($month->live_net) }}
                                </span>
                                <span class="mx-1 text-gray-300">·</span>
                                {{ __('messages.closing') }}:
                                <span class="font-semibold {{ ($consolidated && $month->isClosed()) ? 'text-gray-700' : 'text-gray-400' }}">{{ money($month->live_closing) }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            @if($consolidated && $month->canClose())
                                {{-- Close Month (opens withdrawal modal) --}}
                                <button type="button"
                                        @click="closeModal = { open: true, action: '{{ route('admin.fiscalperiod.monthly-period.close', [$fiscalperiod->id, $month->id]) }}', name: @js($month->name), net: {{ $month->live_net }}, available: {{ $month->opening_balance + $month->live_net }}, amount: '' }"
                                        class="p-2 rounded-lg text-amber-600 bg-amber-50 active:bg-amber-100" title="{{ __('messages.close_month') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </button>
                            @endif
                            {{-- View detail --}}
                            <a href="{{ route('admin.fiscalperiod.monthly-period.show', [$fiscalperiod->id, $month->id]) }}"
                               class="p-2 rounded-lg text-sky-600 bg-sky-50 active:bg-sky-100" title="{{ __('messages.view_details') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop: full table --}}
            <div class="hidden md:block overflow-x-auto">
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
                                <td class="px-4 py-2.5 text-right">{{ money($month->live_opening) }}</td>
                                <td class="px-4 py-2.5 text-right text-green-600">+{{ money($month->live_income) }}</td>
                                <td class="px-4 py-2.5 text-right text-red-600">-{{ money($month->live_expenses) }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold {{ $month->live_net >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $month->live_net >= 0 ? '+' : '' }}{{ money($month->live_net) }}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    @if($consolidated && $month->isClosed())
                                        {{ money($month->live_closing) }}
                                        @if($month->owner_withdrawal > 0)
                                            <div class="text-xs text-purple-600 font-normal" title="{{ $month->withdrawal_note }}">
                                                − {{ money($month->owner_withdrawal) }} drawn
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-gray-400">{{ money($month->live_closing) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $month->status === 'open' ? 'bg-green-100 text-green-700' : ($month->status === 'closed' ? 'bg-red-100 text-red-700' : 'bg-gray-200 text-gray-700') }}">
                                        {{ status_label($month->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center justify-center gap-1">
                                        {{-- View --}}
                                        <a href="{{ route('admin.fiscalperiod.monthly-period.show', [$fiscalperiod->id, $month->id]) }}"
                                           class="p-1.5 rounded text-sky-600 hover:bg-sky-50 transition" title="{{ __('messages.view_details') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>

                                        @if($consolidated && $month->canClose())
                                            {{-- Close Month (opens withdrawal modal) --}}
                                            <button type="button"
                                                    @click="closeModal = { open: true, action: '{{ route('admin.fiscalperiod.monthly-period.close', [$fiscalperiod->id, $month->id]) }}', name: @js($month->name), net: {{ $month->live_net }}, available: {{ $month->opening_balance + $month->live_net }}, amount: '' }"
                                                    class="p-1.5 rounded text-amber-600 hover:bg-amber-50 transition" title="{{ __('messages.close_month') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            </button>
                                        @endif

                                        @if($consolidated && $month->canReopen())
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
    @if($consolidated && $fiscalperiod->status === 'open')
        <div class="text-center mb-6">
            <button type="button" @click="closePeriodOpen = true"
                    class="bg-orange-600 text-white px-5 py-2 rounded-lg hover:bg-orange-700 text-sm font-semibold">
                {{ __('messages.close_period') }}
            </button>
        </div>
    @endif

    {{-- Delete (only if not closed) --}}
    @if($consolidated && $fiscalperiod->status !== 'closed')
        <div class="text-center">
            <form method="POST" action="{{ route('admin.fiscalperiod.destroy', $fiscalperiod->id) }}" data-confirm="Delete this fiscal period? This cannot be undone.">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm text-red-500 hover:text-red-700 hover:underline">{{ __('messages.delete_this_period') }}</button>
            </form>
        </div>
    @endif

    {{-- Close-period modal: confirm closing balance --}}
    @if($consolidated && $fiscalperiod->status === 'open')
        <div x-show="closePeriodOpen" x-cloak
             class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:items-center"
             @keydown.escape.window="closePeriodOpen = false">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md my-auto" @click.outside="closePeriodOpen = false">
                <form method="POST" action="{{ route('admin.fiscalperiod.closeperiod', $fiscalperiod->id) }}" data-confirm="Close this fiscal period? This cannot be undone.">
                    @csrf
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-semibold">{{ __('messages.close_this_period') }}</h3>
                    </div>

                    <div class="px-6 py-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('messages.closing_balance') }}</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                            <input type="number" name="closing_balance" step="0.01" required
                                value="{{ $balanceSummary['total_assets'] - $balanceSummary['total_liabilities'] }}"
                                class="w-full pl-7 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Suggested: {{ money($balanceSummary['total_assets'] - $balanceSummary['total_liabilities']) }} (Assets − Liabilities)</p>
                    </div>

                    <div class="px-6 py-4 border-t flex justify-end gap-2">
                        <button type="button" @click="closePeriodOpen = false"
                                class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 text-sm font-semibold">{{ __('messages.cancel') }}</button>
                        <button type="submit"
                                class="px-4 py-2 rounded-lg bg-orange-600 text-white hover:bg-orange-700 text-sm font-semibold">
                            {{ __('messages.close_period') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Close-month modal: capture owner profit withdrawal (account-wide) --}}
    @if($consolidated)
    <div x-show="closeModal.open" x-cloak
         class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:items-center"
         @keydown.escape.window="closeModal.open = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md my-auto" @click.outside="closeModal.open = false">
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
    @endif
</div>
@endsection
