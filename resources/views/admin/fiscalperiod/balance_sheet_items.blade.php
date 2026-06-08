@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.balance_sheet') }}</h1>
        </div>
        <div class="flex items-center gap-2">
            @if($fiscalperiod->status === 'open')
                <a href="{{ route('admin.fiscalperiod.edit', $fiscalperiod->id) }}" class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200">{{ __('messages.edit_opening_figures') }}</a>
            @endif
            <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200">← Back</a>
        </div>
    </div>

    {{-- How it works --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-6 text-sm text-blue-800">
        This balance sheet is calculated automatically. You set the opening Assets, Liabilities and Equity when the
        period was created; the system rolls them forward every month from your recorded income and expenses.
    </div>

    {{-- Current (auto-calculated) balance sheet --}}
    <div class="grid grid-cols-3 gap-4 mb-2">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500 uppercase">{{ __('messages.assets') }}</p>
            <p class="text-lg font-bold text-blue-600">${{ number_format($summary['total_assets'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500 uppercase">{{ __('messages.liabilities') }}</p>
            <p class="text-lg font-bold text-red-600">${{ number_format($summary['total_liabilities'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500 uppercase">{{ __('messages.equity') }}</p>
            <p class="text-lg font-bold text-green-600">${{ number_format($summary['total_equity'], 2) }}</p>
        </div>
    </div>
    <p class="text-center text-xs mb-6 {{ $summary['balance_check'] ? 'text-green-600' : 'text-amber-600' }}">
        @if($summary['balance_check'])
            ✓ Balanced — Assets = Liabilities + Equity
        @else
            ⚠ Out of balance by ${{ number_format(abs($summary['total_assets'] - ($summary['total_liabilities'] + $summary['total_equity'])), 2) }}
        @endif
    </p>

    {{-- Opening → Current roll-forward --}}
    <div class="bg-white rounded-lg shadow p-5 mb-6">
        <h3 class="font-semibold text-sm text-gray-700 mb-3">{{ __('messages.how_calculated') }}</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase border-b">
                        <th class="py-2 text-left">&nbsp;</th>
                        <th class="py-2 text-right">{{ __('messages.opening') }}</th>
                        <th class="py-2 text-right">+ Retained Earnings</th>
                        <th class="py-2 text-right">− Owner Draws</th>
                        <th class="py-2 text-right">= Current</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr>
                        <td class="py-2 font-medium text-gray-700">{{ __('messages.assets') }}</td>
                        <td class="py-2 text-right">${{ number_format($summary['opening_assets'], 2) }}</td>
                        <td class="py-2 text-right text-green-600">+${{ number_format($summary['retained_earnings'], 2) }}</td>
                        <td class="py-2 text-right text-purple-600">−${{ number_format($summary['owner_withdrawals'], 2) }}</td>
                        <td class="py-2 text-right font-semibold text-blue-600">${{ number_format($summary['total_assets'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 font-medium text-gray-700">{{ __('messages.liabilities') }}</td>
                        <td class="py-2 text-right">${{ number_format($summary['opening_liabilities'], 2) }}</td>
                        <td class="py-2 text-right text-gray-400">—</td>
                        <td class="py-2 text-right text-gray-400">—</td>
                        <td class="py-2 text-right font-semibold text-red-600">${{ number_format($summary['total_liabilities'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 font-medium text-gray-700">{{ __('messages.equity') }}</td>
                        <td class="py-2 text-right">${{ number_format($summary['opening_equity'], 2) }}</td>
                        <td class="py-2 text-right text-green-600">+${{ number_format($summary['retained_earnings'], 2) }}</td>
                        <td class="py-2 text-right text-purple-600">−${{ number_format($summary['owner_withdrawals'], 2) }}</td>
                        <td class="py-2 text-right font-semibold text-green-600">${{ number_format($summary['total_equity'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-2">{{ __('messages.retained_earnings_help') }}</p>
    </div>

    {{-- Operating Performance & Retained Earnings --}}
    <div class="bg-white rounded-lg shadow p-5 mb-6">
        <h3 class="font-semibold text-sm text-gray-700 mb-3">{{ __('messages.operating_performance') }}</h3>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <h4 class="text-xs text-gray-500 uppercase font-semibold mb-2">{{ __('messages.revenue') }}</h4>
                <div class="space-y-1 text-sm">
                    @php $categoryLabels = [
                        'rent_income' => 'Rent Income',
                        'utility_income' => 'Utility Income',
                        'deposit_income' => 'Deposit Income',
                        'other_income' => 'Other Income',
                    ]; @endphp
                    @forelse($summary['income_by_category'] ?? [] as $cat => $amount)
                        <div class="flex justify-between">
                            <span class="text-gray-600">{{ $categoryLabels[$cat] ?? ucfirst(str_replace('_', ' ', $cat)) }}</span>
                            <span class="font-medium text-green-600">${{ number_format($amount, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-gray-400 text-xs">{{ __('messages.no_revenue_recorded') }}</p>
                    @endforelse
                    <div class="flex justify-between border-t pt-1 font-semibold">
                        <span>{{ __('messages.total_revenue') }}</span>
                        <span class="text-green-700">${{ number_format($summary['total_income'], 2) }}</span>
                    </div>
                </div>
            </div>
            <div>
                <h4 class="text-xs text-gray-500 uppercase font-semibold mb-2">{{ __('messages.expenses_word') }}</h4>
                <div class="space-y-1 text-sm">
                    @php $expLabels = [
                        'utilities_expense' => 'Utilities',
                        'business_fixed' => 'Business Fixed',
                        'business_variable' => 'Business Variable',
                        'maintenance' => 'Maintenance',
                        'insurance' => 'Insurance',
                        'property_tax' => 'Property Tax',
                        'management' => 'Management',
                        'other_expense' => 'Other',
                    ]; @endphp
                    @forelse($summary['expense_by_category'] ?? [] as $cat => $amount)
                        <div class="flex justify-between">
                            <span class="text-gray-600">{{ $expLabels[$cat] ?? ucfirst(str_replace('_', ' ', $cat)) }}</span>
                            <span class="font-medium text-red-600">${{ number_format($amount, 2) }}</span>
                        </div>
                    @empty
                        <p class="text-gray-400 text-xs">{{ __('messages.no_expenses_recorded') }}</p>
                    @endforelse
                    <div class="flex justify-between border-t pt-1 font-semibold">
                        <span>{{ __('messages.total_expenses') }}</span>
                        <span class="text-red-700">${{ number_format($summary['total_expenses'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-3 pt-3 border-t text-center">
            <div>
                <p class="text-xs text-gray-400">{{ __('messages.retained_earnings') }}</p>
                <p class="font-bold text-sm {{ ($summary['retained_earnings'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    ${{ number_format($summary['retained_earnings'] ?? 0, 2) }}
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-400">{{ __('messages.owner_draws') }}</p>
                <p class="font-bold text-sm text-purple-600">${{ number_format($summary['owner_withdrawals'] ?? 0, 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">{{ __('messages.net_worth') }}</p>
                <p class="font-bold text-sm text-blue-600">${{ number_format($summary['net_worth'] ?? 0, 2) }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
