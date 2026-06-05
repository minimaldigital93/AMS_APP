@extends('layouts.superadmin')

@php
    $money = fn ($v) => '$' . number_format((float) $v, 2);
    $startDefault = now()->startOfMonth()->toDateString();
    $endDefault = now()->addMonths(11)->endOfMonth()->toDateString();
@endphp

@section('content')
<div x-data="{
        expenseOpen: {{ old('description') !== null ? 'true' : 'false' }},
        periodCreateOpen: {{ $errors->hasAny(['name', 'start_date', 'end_date', 'opening_balance']) ? 'true' : 'false' }},
        periodEdit: null,
        close: null,
        decision: 'carry',
        withdrawAmount: 0,
        withdrawOpen: {{ $errors->hasAny(['amount', 'note']) ? 'true' : 'false' }},
        periodAvailable: {{ $period ? (float) $pnl['available_to_withdraw'] : 0 }},
        cashOut: 0,
        openClose(m) {
            this.close = m;
            this.decision = 'carry';
            this.withdrawAmount = m.available;
        },
        openPeriodEdit(p) { this.periodEdit = p; },
        money(v) { return '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
     }">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Platform finance') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Subscription revenue vs platform expenses — your SaaS profit & loss.') }}</p>
        </div>
        <div class="flex items-center gap-2 print:hidden">
            @if ($period)
                <form method="GET" class="flex items-center gap-2">
                    <label class="text-sm text-gray-500">{{ __('Period') }}</label>
                    <select name="period" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                        @foreach ($periods as $p)
                            <option value="{{ $p->id }}" @selected($p->id === $period->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('superadmin.finance.statement', $period) }}" target="_blank" title="{{ __('Income statement (PDF)') }}"
                   class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-700 shadow-sm hover:bg-gray-50">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                </a>
                <button type="button" @click="expenseOpen = true" title="{{ __('Add expense') }}"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 p-2 text-white shadow-sm hover:bg-indigo-500">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </button>
            @endif
            <button type="button" @click="periodCreateOpen = true" title="{{ __('New period') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-700 shadow-sm hover:bg-gray-50">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            </button>
        </div>
    </div>

    @if (! $period)
        {{-- ===== Empty state: no fiscal period yet ===== --}}
        <div class="mt-10 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-200 text-gray-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <h3 class="mt-4 text-lg font-semibold text-gray-900">{{ __('No fiscal period yet') }}</h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">{{ __('Create a fiscal period — pick its start and end month — and the monthly breakdown will follow that range.') }}</p>
            <button type="button" @click="periodCreateOpen = true"
                    class="mt-5 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('Create fiscal period') }}
            </button>
        </div>
    @else
    @php
        $periodLabel = $period->start_date->format('M j, Y') . ' — ' . $period->end_date->format('M j, Y');
        $expenseMin = $period->start_date->toDateString();
        $expenseMax = $period->end_date->toDateString();
        $expenseDefault = now()->betweenIncluded($period->start_date, $period->end_date)
            ? now()->toDateString() : $expenseMin;
    @endphp

    {{-- Period summary cards --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">{{ __('Revenue') }}</div>
            <div class="mt-1 text-2xl font-bold text-green-600">{{ $money($pnl['revenue']) }}</div>
            <div class="mt-1 text-xs text-gray-400">{{ __('Confirmed subscription payments') }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">{{ __('Expenses') }}</div>
            <div class="mt-1 text-2xl font-bold text-red-600">{{ $money($pnl['expense']) }}</div>
            <div class="mt-1 text-xs text-gray-400">{{ __('Recorded platform expenses') }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">{{ __('Net profit') }}</div>
            <div class="mt-1 text-2xl font-bold {{ $pnl['profit'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">{{ $money($pnl['profit']) }}</div>
            <div class="mt-1 text-xs text-gray-400">{{ __('Revenue − expenses') }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">{{ __('Carried forward') }}</div>
            <div class="mt-1 text-2xl font-bold text-indigo-600">{{ $money($pnl['carried_total']) }}</div>
            <div class="mt-1 text-xs text-gray-400">{{ __('Cash kept after :amount withdrawn', ['amount' => $money($pnl['withdrawn_total'])]) }}</div>
        </div>
    </div>

    {{-- Fiscal period: status + edit / delete / close --}}
    <div class="mt-6 rounded-2xl border {{ $pnl['period_closed'] ? 'border-gray-200 bg-gray-50' : 'border-indigo-100 bg-indigo-50/40' }} p-5 print:hidden">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-start gap-3">
                <div class="mt-0.5 flex h-9 w-9 items-center justify-center rounded-full {{ $pnl['period_closed'] ? 'bg-gray-200 text-gray-600' : 'bg-indigo-100 text-indigo-600' }}">
                    @if ($pnl['period_closed'])
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    @else
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                    @endif
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-gray-900">{{ $period->name }}</h3>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $pnl['period_closed'] ? 'bg-gray-200 text-gray-700' : 'bg-green-100 text-green-700' }}">
                            {{ $pnl['period_closed'] ? __('Closed') : __('Open') }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-sm text-gray-500">
                        {{ $periodLabel }}
                        <span class="text-gray-300 mx-1">·</span>
                        {{ __('Opening balance') }}: {{ $money($pnl['opening_balance']) }}
                        <span class="text-gray-300 mx-1">·</span>
                        @if ($pnl['period_closed'])
                            {{ __('Locked — final balance carried forward: :amount.', ['amount' => $money($pnl['carried_total'])]) }}
                        @elseif ($pnl['period_closeable'])
                            {{ __('All months closed — ready to close the period.') }}
                        @else
                            {{ __(':count month(s) still open before you can close the period.', ['count' => $pnl['open_months']]) }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if ($pnl['period_closed'])
                    <form method="POST" action="{{ route('superadmin.finance.period.reopen', $period) }}"
                          onsubmit="return confirm('{{ __('Reopen this period? Its months will be editable again.') }}')">
                        @csrf
                        <button type="submit" title="{{ __('Reopen period') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-700 shadow-sm hover:bg-gray-50">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                        </button>
                    </form>
                @else
                    {{-- Withdraw cash --}}
                    <button type="button" @click="withdrawOpen = true; cashOut = periodAvailable" title="{{ __('Withdraw') }}"
                            class="inline-flex items-center justify-center rounded-lg border border-purple-200 bg-purple-50 p-2 text-purple-700 shadow-sm hover:bg-purple-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </button>
                    {{-- Edit --}}
                    <button type="button" title="{{ __('Edit period') }}"
                            @click="openPeriodEdit({ id: {{ $period->id }}, name: @js($period->name), start_date: '{{ $period->start_date->toDateString() }}', end_date: '{{ $period->end_date->toDateString() }}', opening_balance: {{ (float) $period->opening_balance }} })"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-700 shadow-sm hover:bg-gray-50">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    {{-- Close period --}}
                    <form method="POST" action="{{ route('superadmin.finance.period.close', $period) }}"
                          onsubmit="return confirm('{{ __('Close this period? Its months lock and the carried balance moves into the next period. You can reopen later.') }}')">
                        @csrf
                        <button type="submit" @disabled(! $pnl['period_closeable']) title="{{ __('Close period') }}"
                                class="inline-flex items-center justify-center rounded-lg p-2 text-white shadow-sm
                                       {{ $pnl['period_closeable'] ? 'bg-indigo-600 hover:bg-indigo-500' : 'cursor-not-allowed bg-gray-300' }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </button>
                    </form>
                @endif
                {{-- Delete --}}
                <form method="POST" action="{{ route('superadmin.finance.period.destroy', $period) }}"
                      onsubmit="return confirm('{{ __('Delete the fiscal period :name? Monthly figures stay; only the period wrapper is removed.', ['name' => $period->name]) }}')">
                    @csrf @method('DELETE')
                    <button type="submit" title="{{ __('Delete period') }}"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white p-2 text-gray-400 shadow-sm hover:bg-gray-50 hover:text-red-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Monthly breakdown --}}
    <div class="mt-6 bg-white rounded-lg shadow">
        <div class="flex items-center justify-between px-5 py-3 border-b">
            <div>
                <h3 class="font-semibold text-gray-900">{{ __('Monthly breakdown') }} · {{ $period->name }}</h3>
                <p class="text-xs text-gray-400">{{ $periodLabel }} — {{ __('close each month to withdraw its profit or carry it forward.') }}</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase">
                        <th class="px-4 py-2 text-left">{{ __('No') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('Month') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Opening balance') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Revenue') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Expense') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Profit') }}</th>
                        <th class="px-4 py-2 text-center">{{ __('Status') }}</th>
                        <th class="px-4 py-2 text-center print:hidden">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($pnl['months'] as $m)
                        @php $empty = $m['revenue'] == 0 && $m['expense'] == 0 && ! $m['closed']; @endphp
                        <tr class="hover:bg-gray-50 {{ $m['closed'] ? 'bg-gray-50/40' : '' }}">
                            <td class="px-4 py-2.5 text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-4 py-2.5 font-medium {{ $empty ? 'text-gray-400' : 'text-gray-900' }}">{{ $m['label'] }}</td>
                            <td class="px-4 py-2.5 text-right">
                                @if ($m['closed'])
                                    ${{ number_format((float) $m['carried'], 2) }}
                                    @if ($m['owner_withdrawal'] > 0)
                                        <div class="text-xs text-purple-600 font-normal" title="{{ __('Owner withdrawal') }}">
                                            − ${{ number_format((float) $m['owner_withdrawal'], 2) }} {{ __('drawn') }}
                                        </div>
                                    @endif
                                @elseif ($m['opening'] != 0)
                                    {{-- Running balance carried in from the previous closed month. --}}
                                    <span class="font-medium text-gray-700">${{ number_format((float) $m['available'], 2) }}</span>
                                    <div class="text-xs text-gray-400 font-normal">{{ __('incl. :amount carried in', ['amount' => '$'.number_format((float) $m['opening'], 2)]) }}</div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right {{ $m['revenue'] > 0 ? 'text-green-600' : 'text-gray-400' }}">+${{ number_format((float) $m['revenue'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right {{ $m['expense'] > 0 ? 'text-red-600' : 'text-gray-400' }}">-${{ number_format((float) $m['expense'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold {{ $m['profit'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ $m['profit'] >= 0 ? '+' : '-' }}${{ number_format(abs((float) $m['profit']), 2) }}
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $m['closed'] ? 'bg-gray-200 text-gray-700' : 'bg-green-100 text-green-700' }}">
                                    {{ $m['closed'] ? __('Closed') : __('Open') }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 print:hidden">
                                <div class="flex items-center justify-center gap-1">
                                    @if ($m['closeable'] && ($m['revenue'] > 0 || $m['expense'] > 0))
                                        {{-- Manual close: the month has profit/loss to withdraw or carry forward. --}}
                                        <button type="button" title="{{ __('Close month') }}"
                                                @click="openClose({
                                                    year: {{ $m['year'] }},
                                                    month: {{ $m['month'] }},
                                                    label: '{{ $m['label'] }}',
                                                    opening: {{ $m['opening'] }},
                                                    profit: {{ $m['profit'] }},
                                                    available: {{ max(0, $m['available']) }}
                                                })"
                                                class="p-1.5 rounded text-amber-600 hover:bg-amber-50 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        </button>
                                    @elseif ($m['closeable'])
                                        {{-- Auto close: empty month, nothing to withdraw — one-click carry forward. --}}
                                        <form method="POST" action="{{ route('superadmin.finance.months.close', $period) }}"
                                              onsubmit="return confirm('{{ __('Auto-close :month? It has no activity, so the balance simply carries forward.', ['month' => $m['label']]) }}')">
                                            @csrf
                                            <input type="hidden" name="year" value="{{ $m['year'] }}">
                                            <input type="hidden" name="month" value="{{ $m['month'] }}">
                                            <input type="hidden" name="decision" value="carry">
                                            <button type="submit" class="p-1.5 rounded text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition" title="{{ __('Auto-close (no activity)') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                                            </button>
                                        </form>
                                    @elseif ($m['reopenable'])
                                        <form method="POST" action="{{ route('superadmin.finance.months.reopen', $period) }}"
                                              onsubmit="return confirm('{{ __('Reopen this month? The carried balance and any withdrawal will be undone.') }}')">
                                            @csrf
                                            <input type="hidden" name="year" value="{{ $m['year'] }}">
                                            <input type="hidden" name="month" value="{{ $m['month'] }}">
                                            <button type="submit" class="p-1.5 rounded text-green-600 hover:bg-green-50 transition" title="{{ __('Reopen month') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 border-t-2 font-bold text-gray-900">
                        <td class="px-4 py-2.5"></td>
                        <td class="px-4 py-2.5">{{ __('Total') }}</td>
                        <td class="px-4 py-2.5 text-right">${{ number_format((float) $pnl['carried_total'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right text-green-700">+${{ number_format((float) $pnl['revenue'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right text-red-700">-${{ number_format((float) $pnl['expense'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right {{ $pnl['profit'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            {{ $pnl['profit'] >= 0 ? '+' : '-' }}${{ number_format(abs((float) $pnl['profit']), 2) }}
                        </td>
                        <td class="px-4 py-2.5 text-center text-xs font-normal text-gray-400">{{ __('Withdrawn') }}: ${{ number_format((float) $pnl['withdrawn_total'], 2) }}</td>
                        <td class="px-4 py-2.5 print:hidden"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Expense list --}}
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('Expenses') }} · {{ $period->name }}</h2>
        <div class="mt-4 space-y-2">
            @forelse ($expenses as $expense)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-100 px-4 py-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-900">{{ $expense->description }}</span>
                            @if ($expense->is_recurring)
                                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-medium text-indigo-600">{{ __('Recurring') }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ __($expense->categoryLabel()) }} · {{ $expense->spent_at->format('M j, Y') }}
                            @if ($expense->notes) · <span class="text-gray-400">{{ $expense->notes }}</span> @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-red-600">${{ number_format((float) $expense->amount, 2) }}</span>
                        <form method="POST" action="{{ route('superadmin.finance.expenses.destroy', $expense) }}"
                              onsubmit="return confirm('{{ __('Delete this expense?') }}')">
                            @csrf @method('DELETE')
                            <input type="hidden" name="period" value="{{ $period->id }}">
                            <button class="text-gray-400 hover:text-red-600" title="{{ __('Delete') }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400">{{ __('No expenses recorded for this period.') }}</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $expenses->links() }}</div>
    </div>

    {{-- Owner withdrawals --}}
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('Owner withdrawals') }} · {{ $period->name }}</h2>
            @unless ($pnl['period_closed'])
                <button type="button" @click="withdrawOpen = true; cashOut = periodAvailable" title="{{ __('Withdraw') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-purple-200 bg-purple-50 p-2 text-purple-700 hover:bg-purple-100 print:hidden">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                </button>
            @endunless
        </div>
        <p class="mt-0.5 text-sm text-gray-500">{{ __('Cash taken out of the period — available now: :amount.', ['amount' => $money($pnl['available_to_withdraw'])]) }}</p>
        <div class="mt-4 space-y-2">
            @forelse ($period->withdrawals as $withdrawal)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-100 px-4 py-3">
                    <div class="min-w-0">
                        <div class="font-medium text-gray-900">{{ $withdrawal->note ?: __('Owner withdrawal') }}</div>
                        <div class="text-xs text-gray-500">{{ $withdrawal->withdrawn_at->format('M j, Y') }}</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-purple-600">− ${{ number_format((float) $withdrawal->amount, 2) }}</span>
                        @unless ($pnl['period_closed'])
                            <form method="POST" action="{{ route('superadmin.finance.withdrawals.destroy', $withdrawal) }}"
                                  onsubmit="return confirm('{{ __('Remove this withdrawal? The cash returns to the carried balance.') }}')">
                                @csrf @method('DELETE')
                                <button class="text-gray-400 hover:text-red-600" title="{{ __('Remove') }}">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        @endunless
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400">{{ __('No withdrawals taken from this period.') }}</p>
            @endforelse
        </div>
    </div>

    {{-- ===== Withdraw cash modal ===== --}}
    <div x-show="withdrawOpen" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 sm:items-center"
         x-transition.opacity @click.self="withdrawOpen = false" @keydown.escape.window="withdrawOpen = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Withdraw cash') }}</h2>
                    <p class="mt-0.5 text-sm text-gray-500">{{ __('Take money out of this period\'s carried-forward balance.') }}</p>
                </div>
                <button type="button" @click="withdrawOpen = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <dl class="mt-4 space-y-1 rounded-xl bg-gray-50 p-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Available now') }}</dt><dd class="font-bold text-gray-900" x-text="money(periodAvailable)"></dd></div>
                <div class="flex justify-between border-t border-gray-200 pt-1"><dt class="text-gray-500">{{ __('Carries forward') }}</dt><dd class="font-medium text-indigo-600" x-text="money(Math.max(0, periodAvailable - (Number(cashOut) || 0)))"></dd></div>
            </dl>

            <form method="POST" action="{{ route('superadmin.finance.withdrawals.store', $period) }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Amount to withdraw') }}</label>
                    <input type="number" step="0.01" min="0.01" :max="periodAvailable" name="amount" required
                           x-model="cashOut" value="{{ old('amount') }}"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="0.00">
                    @error('amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Note') }} ({{ __('optional') }})</label>
                    <input type="text" name="note" maxlength="255" value="{{ old('note') }}"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="{{ __('e.g. Owner distribution') }}">
                    @error('note') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="withdrawOpen = false" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Cancel') }}</button>
                    <button class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-500">{{ __('Withdraw') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Add expense modal ===== --}}
    <div x-show="expenseOpen" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 sm:items-center"
         x-transition.opacity @click.self="expenseOpen = false" @keydown.escape.window="expenseOpen = false">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Record platform expense') }}</h2>
                    <p class="mt-0.5 text-sm text-gray-500">{{ __('Servers, salaries, ads — the cost side of your platform.') }}</p>
                </div>
                <button type="button" @click="expenseOpen = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('superadmin.finance.expenses.store') }}" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="period" value="{{ $period->id }}">
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Category') }}</label>
                    <select name="category" class="mt-1 w-full rounded-lg border-gray-300 text-sm" required>
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Description') }}</label>
                    <input type="text" name="description" maxlength="255" required value="{{ old('description') }}"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="{{ __('e.g. AWS hosting — June') }}">
                    @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">{{ __('Amount') }}</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="0.00">
                        @error('amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">{{ __('Date') }}</label>
                        <input type="date" name="spent_at" value="{{ old('spent_at', $expenseDefault) }}" min="{{ $expenseMin }}" max="{{ $expenseMax }}" required
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        <p class="mt-1 text-xs text-gray-400">{{ __('Within the period: :range', ['range' => $periodLabel]) }}</p>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="is_recurring" value="1" class="rounded border-gray-300" @checked(old('is_recurring'))>
                    {{ __('Recurring expense') }}
                </label>
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Notes') }} ({{ __('optional') }})</label>
                    <textarea name="notes" rows="2" maxlength="1000" class="mt-1 w-full rounded-lg border-gray-300 text-sm">{{ old('notes') }}</textarea>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="expenseOpen = false" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Cancel') }}</button>
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Save expense') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Close month modal ===== --}}
    <div x-show="close" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 sm:items-center"
         x-transition.opacity @click.self="close = null" @keydown.escape.window="close = null">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Close') }} <span x-text="close?.label"></span></h2>
                    <p class="mt-0.5 text-sm text-gray-500">{{ __('What should happen to this month\'s profit?') }}</p>
                </div>
                <button type="button" @click="close = null" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Money summary --}}
            <dl class="mt-4 space-y-1 rounded-xl bg-gray-50 p-3 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Carried in') }}</dt><dd class="font-medium text-gray-700" x-text="money(close?.opening || 0)"></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('Profit this month') }}</dt><dd class="font-medium text-gray-700" x-text="money(close?.profit || 0)"></dd></div>
                <div class="flex justify-between border-t border-gray-200 pt-1"><dt class="font-semibold text-gray-700">{{ __('Available') }}</dt><dd class="font-bold text-gray-900" x-text="money(close?.available || 0)"></dd></div>
            </dl>

            <form method="POST" action="{{ route('superadmin.finance.months.close', $period) }}" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="year" :value="close?.year">
                <input type="hidden" name="month" :value="close?.month">
                <input type="hidden" name="decision" :value="decision">

                {{-- Carry forward option --}}
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3"
                       :class="decision === 'carry' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'">
                    <input type="radio" x-model="decision" value="carry" class="mt-0.5 border-gray-300 text-indigo-600">
                    <span>
                        <span class="block text-sm font-medium text-gray-900">{{ __('Carry forward') }}</span>
                        <span class="block text-xs text-gray-500">{{ __('Keep the full amount as cash for next month.') }}</span>
                    </span>
                </label>

                {{-- Withdraw option --}}
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3"
                       :class="decision === 'withdraw' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'">
                    <input type="radio" x-model="decision" value="withdraw" class="mt-0.5 border-gray-300 text-indigo-600">
                    <span class="flex-1">
                        <span class="block text-sm font-medium text-gray-900">{{ __('Withdraw profit') }}</span>
                        <span class="block text-xs text-gray-500">{{ __('Take some or all of it out. The rest carries forward.') }}</span>
                        <div x-show="decision === 'withdraw'" class="mt-2 space-y-2" @click.stop>
                            <div>
                                <label class="block text-xs font-medium text-gray-500">{{ __('Amount to withdraw') }}</label>
                                <input type="number" step="0.01" min="0" name="owner_withdrawal"
                                       x-model="withdrawAmount" :max="close?.available"
                                       class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ __('Carries forward') }}:
                                    <span class="font-medium text-indigo-600" x-text="money(Math.max(0, (close?.available || 0) - (Number(withdrawAmount) || 0)))"></span>
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500">{{ __('Note') }} ({{ __('optional') }})</label>
                                <input type="text" name="withdrawal_note" maxlength="255"
                                       class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="{{ __('e.g. Owner distribution') }}">
                            </div>
                        </div>
                    </span>
                </label>

                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="close = null" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Cancel') }}</button>
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Close month') }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- ===== Create fiscal period modal ===== --}}
    <div x-show="periodCreateOpen" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 sm:items-center"
         x-transition.opacity @click.self="periodCreateOpen = false" @keydown.escape.window="periodCreateOpen = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('New fiscal period') }}</h2>
                    <p class="mt-0.5 text-sm text-gray-500">{{ __('Pick the start and end date — the breakdown covers that range.') }}</p>
                </div>
                <button type="button" @click="periodCreateOpen = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('superadmin.finance.period.store') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Name') }}</label>
                    <input type="text" name="name" maxlength="255" required value="{{ old('name', 'FY '.now()->year) }}"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="{{ __('e.g. FY :year', ['year' => now()->year]) }}">
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">{{ __('Start date') }}</label>
                        <input type="date" name="start_date" required value="{{ old('start_date', $startDefault) }}"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        @error('start_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">{{ __('End date') }}</label>
                        <input type="date" name="end_date" required value="{{ old('end_date', $endDefault) }}"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        @error('end_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Opening balance') }}</label>
                    <input type="number" step="0.01" min="0" name="opening_balance" value="{{ old('opening_balance', '0.00') }}"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="0.00">
                    <p class="mt-1 text-xs text-gray-400">{{ __('Starting cash carried into the first month.') }}</p>
                    @error('opening_balance') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="periodCreateOpen = false" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Cancel') }}</button>
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Create period') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Edit fiscal period modal ===== --}}
    <div x-show="periodEdit" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 sm:items-center"
         x-transition.opacity @click.self="periodEdit = null" @keydown.escape.window="periodEdit = null">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Edit fiscal period') }}</h2>
                    <p class="mt-0.5 text-sm text-gray-500">{{ __('Rename it, change the date range, or adjust the opening cash.') }}</p>
                </div>
                <button type="button" @click="periodEdit = null" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" x-bind:action="'{{ url('superadmin/finance/period') }}/' + (periodEdit?.id || '')" class="mt-4 space-y-3">
                @csrf @method('PUT')
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Name') }}</label>
                    <input type="text" name="name" maxlength="255" required x-model="periodEdit.name"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">{{ __('Start date') }}</label>
                        <input type="date" name="start_date" required x-model="periodEdit.start_date"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">{{ __('End date') }}</label>
                        <input type="date" name="end_date" required x-model="periodEdit.end_date"
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">{{ __('Opening balance') }}</label>
                    <input type="number" step="0.01" min="0" name="opening_balance" x-model="periodEdit.opening_balance"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm" placeholder="0.00">
                    <p class="mt-1 text-xs text-gray-400">{{ __('Starting cash carried into the first month.') }}</p>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="periodEdit = null" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Cancel') }}</button>
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('Save changes') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
