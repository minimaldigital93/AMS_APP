@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8 max-w-5xl">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold">{{ $fiscalperiod->name }}</h1>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $fiscalperiod->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ ucfirst($fiscalperiod->status) }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-1">
                {{ $fiscalperiod->opening_date->format('M d, Y') }} — {{ $fiscalperiod->closing_date->format('M d, Y') }}
                ({{ $fiscalperiod->opening_date->diffInDays($fiscalperiod->closing_date) }} days)
            </p>
        </div>
        <div class="flex gap-2">
            @if($fiscalperiod->status === 'open')
                <a href="{{ route('admin.fiscalperiod.edit', $fiscalperiod->id) }}" class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200">Edit</a>
                <a href="{{ route('admin.fiscalperiod.balance-sheet', $fiscalperiod->id) }}" class="text-sm bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700">Balance Sheet</a>
            @endif
            {{-- Annual PDF Print --}}
            <a href="{{ route('admin.fiscalperiod.exportPDF', $fiscalperiod->id) }}" target="_blank"
               class="text-sm bg-gray-700 text-white px-3 py-2 rounded-lg hover:bg-gray-800 flex items-center gap-1.5" title="Print Annual Summary PDF">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print Annual
            </a>
            <a href="{{ route('admin.fiscalperiod.index') }}" class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200">← Back</a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
            <p class="text-green-800 text-sm">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
            <p class="text-red-800 text-sm">{{ session('error') }}</p>
        </div>
    @endif



    {{-- Financial Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 uppercase">Total Income</p>
            <p class="text-xl font-bold text-green-600 mt-1">${{ number_format($financialData['total_income'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 uppercase">Total Expenses</p>
            <p class="text-xl font-bold text-red-600 mt-1">${{ number_format($financialData['total_expenses'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 uppercase">Net {{ $financialData['is_profitable'] ? 'Profit' : 'Loss' }}</p>
            <p class="text-xl font-bold {{ $financialData['is_profitable'] ? 'text-green-600' : 'text-red-600' }} mt-1">
                ${{ number_format(abs($financialData['net_income']), 2) }}
            </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-xs text-gray-500 uppercase">Opening Balance</p>
            <p class="text-xl font-bold mt-1">${{ number_format($fiscalperiod->opening_balance, 2) }}</p>
        </div>
    </div>

    {{-- Income & Expense Breakdown --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-sm text-gray-700 mb-3">Income</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-600">Rent</span><span class="font-medium text-green-600">${{ number_format($financialData['rent_income'], 2) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600">Late Fees</span><span class="font-medium text-green-600">${{ number_format($financialData['late_fees'], 2) }}</span></div>
                @if($financialData['other_income'] > 0)
                    <div class="flex justify-between"><span class="text-gray-600">Other</span><span class="font-medium text-green-600">${{ number_format($financialData['other_income'], 2) }}</span></div>
                @endif
                <div class="flex justify-between border-t pt-2 font-semibold"><span>Total</span><span class="text-green-700">${{ number_format($financialData['total_income'], 2) }}</span></div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-semibold text-sm text-gray-700 mb-3">Expenses</h3>
            <div class="space-y-2 text-sm">
                @forelse($financialData['utility_expenses'] as $type => $amount)
                    <div class="flex justify-between"><span class="text-gray-600 capitalize">{{ str_replace('_', ' ', $type) }}</span><span class="font-medium text-red-600">${{ number_format($amount, 2) }}</span></div>
                @empty
                    <p class="text-gray-400 text-xs">No utility expenses</p>
                @endforelse
                @if($financialData['fixed_expenses'] > 0)
                    <div class="flex justify-between"><span class="text-gray-600">Fixed/Other</span><span class="font-medium text-red-600">${{ number_format($financialData['fixed_expenses'], 2) }}</span></div>
                @endif
                <div class="flex justify-between border-t pt-2 font-semibold"><span>Total</span><span class="text-red-700">${{ number_format($financialData['total_expenses'], 2) }}</span></div>
            </div>
        </div>
    </div>

    {{-- Monthly Periods --}}
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="flex items-center justify-between px-5 py-3 border-b">
            <h3 class="font-semibold">Monthly Periods</h3>
            @if($fiscalperiod->status === 'open')
                <form method="POST" action="{{ route('admin.fiscalperiod.recalculate-balances', $fiscalperiod->id) }}" onsubmit="return confirm('Recalculate all balances?')">
                    @csrf
                    <button type="submit" class="text-xs bg-amber-100 text-amber-700 px-3 py-1 rounded hover:bg-amber-200">Recalculate</button>
                </form>
            @endif
        </div>
        @if($monthlyPeriods->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase">
                            <th class="px-4 py-2 text-left">Month</th>
                            <th class="px-4 py-2 text-right">Opening</th>
                            <th class="px-4 py-2 text-right">Income</th>
                            <th class="px-4 py-2 text-right">Expenses</th>
                            <th class="px-4 py-2 text-right">Net</th>
                            <th class="px-4 py-2 text-right">Closing</th>
                            <th class="px-4 py-2 text-center">Status</th>
                            <th class="px-4 py-2 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($monthlyPeriods as $month)
                            <tr class="hover:bg-gray-50">
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
                                    @else
                                        <span class="text-gray-400">${{ number_format($month->opening_balance + $month->live_net, 2) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $month->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                        {{ ucfirst($month->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="{{ route('admin.fiscalperiod.monthly-period.show', [$fiscalperiod->id, $month->id]) }}" class="text-blue-600 hover:underline text-xs">View</a>
                                        @if($month->canClose())
                                            <form method="POST" action="{{ route('admin.fiscalperiod.monthly-period.close', [$fiscalperiod->id, $month->id]) }}" onsubmit="return confirm('Close {{ $month->name }}?')" class="inline">
                                                @csrf
                                                <button class="text-amber-600 hover:underline text-xs ml-1">Close</button>
                                            </form>
                                        @endif
                                        @if($month->canReopen())
                                            <form method="POST" action="{{ route('admin.fiscalperiod.monthly-period.reopen', [$fiscalperiod->id, $month->id]) }}" onsubmit="return confirm('Reopen {{ $month->name }}?')" class="inline">
                                                @csrf
                                                <button class="text-green-600 hover:underline text-xs ml-1">Reopen</button>
                                            </form>
                                        @endif
                                        <a href="{{ route('admin.fiscalperiod.monthly-period.print', [$fiscalperiod->id, $month->id]) }}" target="_blank"
                                           class="text-gray-500 hover:text-gray-700 ml-1" title="Print Monthly PDF">
                                            <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="p-5 text-sm text-gray-400 text-center">No monthly periods.</p>
        @endif
    </div>


        <div class="mt-3 text-center">


    {{-- Close Period Section (only if open) --}}
    @if($fiscalperiod->status === 'open')
        <div class="bg-white rounded-lg shadow p-5 mb-6">
            <h3 class="font-semibold mb-3">Close This Period</h3>
            <form method="POST" action="{{ route('admin.fiscalperiod.closeperiod', $fiscalperiod->id) }}" onsubmit="return confirm('Close this fiscal period? This cannot be undone.')">
                @csrf
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <label class="block text-xs text-gray-500 mb-1">Closing Balance</label>
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

    {{-- Delete (only if open) --}}
    @if($fiscalperiod->status !== 'closed')
        <div class="text-center">
            <form method="POST" action="{{ route('admin.fiscalperiod.destroy', $fiscalperiod->id) }}" onsubmit="return confirm('Delete this fiscal period? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm text-red-500 hover:text-red-700 hover:underline">Delete this period</button>
            </form>
        </div>
    @endif
</div>
@endsection
