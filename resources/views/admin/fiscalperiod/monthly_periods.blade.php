@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold">Monthly Periods</h1>
            <p class="text-gray-600 mt-1">{{ $fiscalperiod->name }} &middot; {{ $fiscalperiod->opening_date->format('M d, Y') }} – {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('admin.fiscalperiod.recalculate-balances', $fiscalperiod->id) }}" 
                  onsubmit="return confirm('Recalculate all monthly balances? This will update opening/closing balances using carry-forward logic.')">
                @csrf
                <button type="submit" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition text-sm font-semibold">
                    ↻ Recalculate All
                </button>
            </form>
            <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="bg-gray-400 text-white px-4 py-2 rounded-lg hover:bg-gray-500 transition text-sm font-semibold">
                ← Back to Period
            </a>
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

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-600">
            <p class="text-xs text-gray-500 uppercase font-semibold">Total Months</p>
            <p class="text-2xl font-bold mt-1">{{ $monthlyPeriods->count() }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-600">
            <p class="text-xs text-gray-500 uppercase font-semibold">Open</p>
            <p class="text-2xl font-bold mt-1 text-green-600">{{ $monthlyPeriods->where('status', 'open')->count() }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-red-600">
            <p class="text-xs text-gray-500 uppercase font-semibold">Closed</p>
            <p class="text-2xl font-bold mt-1 text-red-600">{{ $monthlyPeriods->where('status', 'closed')->count() }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-5 border-l-4 border-indigo-600">
            <p class="text-xs text-gray-500 uppercase font-semibold">Opening Balance</p>
            <p class="text-2xl font-bold mt-1">${{ number_format($fiscalperiod->opening_balance, 2) }}</p>
        </div>
    </div>

    <!-- Monthly Periods Timeline -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h2 class="text-lg font-semibold">Monthly Period Timeline</h2>
        </div>

        @if($monthlyPeriods->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 border-b">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Month</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Period</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Opening Bal.</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Income</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Expenses</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Net</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Closing Bal.</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($monthlyPeriods as $month)
                            <tr class="hover:bg-gray-50 {{ $month->isClosed() ? 'bg-gray-50/50' : '' }}">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-sm">{{ $month->name }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $month->start_date->format('M d') }} – {{ $month->end_date->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-medium">
                                    ${{ number_format($month->opening_balance, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-green-600">
                                    +${{ number_format($month->live_income, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-medium text-red-600">
                                    -${{ number_format($month->live_expenses, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-bold {{ $month->live_net >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $month->live_net >= 0 ? '+' : '' }}${{ number_format($month->live_net, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-medium">
                                    @if($month->isClosed())
                                        ${{ number_format($month->closing_balance, 2) }}
                                    @else
                                        <span class="text-gray-400">${{ number_format($month->opening_balance + $month->live_net, 2) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($month->status === 'open')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Open</span>
                                    @elseif($month->status === 'closed')
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">Closed</span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-800">Locked</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- View -->
                                        <a href="{{ route('admin.fiscalperiod.monthly-period.show', [$fiscalperiod->id, $month->id]) }}" 
                                           class="p-1.5 rounded text-blue-600 hover:bg-blue-50 transition" title="View Details">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>

                                        @if($month->canClose())
                                            <!-- Close Month -->
                                            <form method="POST" action="{{ route('admin.fiscalperiod.monthly-period.close', [$fiscalperiod->id, $month->id]) }}"
                                                  onsubmit="return confirm('Close {{ $month->name }}? This will lock the closing balance and carry it forward.')">
                                                @csrf
                                                <button type="submit" class="p-1.5 rounded text-amber-600 hover:bg-amber-50 transition" title="Close Month">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                </button>
                                            </form>
                                        @endif

                                        @if($month->canReopen())
                                            <!-- Reopen Month -->
                                            <form method="POST" action="{{ route('admin.fiscalperiod.monthly-period.reopen', [$fiscalperiod->id, $month->id]) }}"
                                                  onsubmit="return confirm('Reopen {{ $month->name }}?')">
                                                @csrf
                                                <button type="submit" class="p-1.5 rounded text-green-600 hover:bg-green-50 transition" title="Reopen Month">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Carry-Forward Visual -->
            <div class="px-6 py-4 bg-gray-50 border-t">
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Closing balance of each month automatically becomes the opening balance of the next month when closed.</span>
                </div>
            </div>
        @else
            <div class="p-8 text-center">
                <p class="text-gray-500">No monthly periods found. This fiscal period may have been created before the monthly period feature was added.</p>
            </div>
        @endif
    </div>
</div>
@endsection
