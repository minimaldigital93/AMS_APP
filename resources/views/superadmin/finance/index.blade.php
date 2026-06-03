@extends('layouts.superadmin')

@php
    $money = fn ($v) => '$' . number_format((float) $v, 2);
@endphp

@section('content')
<div x-data="{
        expenseOpen: {{ $errors->any() ? 'true' : 'false' }},
        close: null,
        decision: 'carry',
        withdrawAmount: 0,
        openClose(m) {
            this.close = m;
            this.decision = 'carry';
            this.withdrawAmount = m.available;
        },
        money(v) { return '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
     }">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Platform finance') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Subscription revenue vs platform expenses — your SaaS profit & loss.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <form method="GET" class="flex items-center gap-2">
                <label class="text-sm text-gray-500">{{ __('Year') }}</label>
                <select name="year" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            <button type="button" @click="window.print()" title="{{ __('Print report') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 print:hidden">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                {{ __('Print') }}
            </button>
            <button type="button" @click="expenseOpen = true"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 print:hidden">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                {{ __('Add expense') }}
            </button>
        </div>
    </div>

    {{-- Yearly summary cards --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">{{ __('Revenue') }} ({{ $year }})</div>
            <div class="mt-1 text-2xl font-bold text-green-600">{{ $money($pnl['revenue']) }}</div>
            <div class="mt-1 text-xs text-gray-400">{{ __('Confirmed subscription payments') }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">{{ __('Expenses') }} ({{ $year }})</div>
            <div class="mt-1 text-2xl font-bold text-red-600">{{ $money($pnl['expense']) }}</div>
            <div class="mt-1 text-xs text-gray-400">{{ __('Recorded platform expenses') }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">{{ __('Net profit') }} ({{ $year }})</div>
            <div class="mt-1 text-2xl font-bold {{ $pnl['profit'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">{{ $money($pnl['profit']) }}</div>
            <div class="mt-1 text-xs text-gray-400">{{ __('Revenue − expenses') }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-gray-500">{{ __('Carried forward') }}</div>
            <div class="mt-1 text-2xl font-bold text-indigo-600">{{ $money($pnl['carried_total']) }}</div>
            <div class="mt-1 text-xs text-gray-400">{{ __('Cash kept after :amount withdrawn', ['amount' => $money($pnl['withdrawn_total'])]) }}</div>
        </div>
    </div>

    {{-- Monthly breakdown --}}
    <div class="mt-6 bg-white rounded-lg shadow">
        <div class="flex items-center justify-between px-5 py-3 border-b">
            <div>
                <h3 class="font-semibold text-gray-900">{{ __('Monthly breakdown') }} · {{ $year }}</h3>
                <p class="text-xs text-gray-400">{{ __('Close each month to withdraw its profit or carry it forward.') }}</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase">
                        <th class="px-4 py-2 text-left">{{ __('No') }}</th>
                        <th class="px-4 py-2 text-left">{{ __('Month') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Revenue') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Expense') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Profit') }}</th>
                        <th class="px-4 py-2 text-right">{{ __('Carried fwd') }}</th>
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
                            <td class="px-4 py-2.5 text-right {{ $m['revenue'] > 0 ? 'text-green-600' : 'text-gray-400' }}">+${{ number_format((float) $m['revenue'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right {{ $m['expense'] > 0 ? 'text-red-600' : 'text-gray-400' }}">-${{ number_format((float) $m['expense'], 2) }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold {{ $m['profit'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ $m['profit'] >= 0 ? '+' : '-' }}${{ number_format(abs((float) $m['profit']), 2) }}
                            </td>
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
                                                    month: {{ $m['month'] }},
                                                    label: '{{ $m['label'] }} {{ $year }}',
                                                    opening: {{ $m['opening'] }},
                                                    profit: {{ $m['profit'] }},
                                                    available: {{ max(0, $m['available']) }}
                                                })"
                                                class="p-1.5 rounded text-amber-600 hover:bg-amber-50 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        </button>
                                    @elseif ($m['closeable'])
                                        {{-- Auto close: empty month, nothing to withdraw — one-click carry forward. --}}
                                        <form method="POST" action="{{ route('superadmin.finance.months.close') }}"
                                              onsubmit="return confirm('{{ __('Auto-close :month? It has no activity, so the balance simply carries forward.', ['month' => $m['label'].' '.$year]) }}')">
                                            @csrf
                                            <input type="hidden" name="year" value="{{ $year }}">
                                            <input type="hidden" name="month" value="{{ $m['month'] }}">
                                            <input type="hidden" name="decision" value="carry">
                                            <button type="submit" class="p-1.5 rounded text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition" title="{{ __('Auto-close (no activity)') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                                            </button>
                                        </form>
                                    @elseif ($m['reopenable'])
                                        <form method="POST" action="{{ route('superadmin.finance.months.reopen') }}"
                                              onsubmit="return confirm('{{ __('Reopen this month? The carried balance and any withdrawal will be undone.') }}')">
                                            @csrf
                                            <input type="hidden" name="year" value="{{ $year }}">
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
                        <td class="px-4 py-2.5 text-right text-green-700">+${{ number_format((float) $pnl['revenue'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right text-red-700">-${{ number_format((float) $pnl['expense'], 2) }}</td>
                        <td class="px-4 py-2.5 text-right {{ $pnl['profit'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            {{ $pnl['profit'] >= 0 ? '+' : '-' }}${{ number_format(abs((float) $pnl['profit']), 2) }}
                        </td>
                        <td class="px-4 py-2.5 text-right">${{ number_format((float) $pnl['carried_total'], 2) }}</td>
                        <td class="px-4 py-2.5 text-center text-xs font-normal text-gray-400">{{ __('Withdrawn') }}: ${{ number_format((float) $pnl['withdrawn_total'], 2) }}</td>
                        <td class="px-4 py-2.5 print:hidden"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Expense list --}}
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">{{ __('Expenses') }} · {{ $year }}</h2>
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
                            <button class="text-gray-400 hover:text-red-600" title="{{ __('Delete') }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400">{{ __('No expenses recorded for :year.', ['year' => $year]) }}</p>
            @endforelse
        </div>
        <div class="mt-4">{{ $expenses->links() }}</div>
    </div>

    {{-- ===== Add expense modal ===== --}}
    <div x-show="expenseOpen" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 sm:items-center"
         x-transition.opacity @keydown.escape.window="expenseOpen = false">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" @click.outside="expenseOpen = false">
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
                        <input type="date" name="spent_at" value="{{ old('spent_at', now()->toDateString()) }}" required
                               class="mt-1 w-full rounded-lg border-gray-300 text-sm">
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
         x-transition.opacity @keydown.escape.window="close = null">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @click.outside="close = null">
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

            <form method="POST" action="{{ route('superadmin.finance.months.close') }}" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">
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
</div>
@endsection
