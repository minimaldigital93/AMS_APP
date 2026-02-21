@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8">
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold mb-2">{{ $fiscalperiod->name }}</h1>
        <p class="text-xl text-gray-600">Balance Sheet Report</p>
        <p class="text-gray-500 text-sm mt-2">{{ now()->format('F d, Y') }}</p>
    </div>

    <!-- Period Details -->
    <div class="mb-12 p-8 bg-gray-50 rounded-lg">
        <h2 class="text-lg font-semibold mb-6">Period Details</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-sm">
            <div>
                <p class="text-gray-600 font-medium">Opening Date</p>
                <p class="text-gray-900 font-semibold">{{ $fiscalperiod->opening_date->format('M d, Y') }}</p>
            </div>
            <div>
                <p class="text-gray-600 font-medium">Closing Date</p>
                <p class="text-gray-900 font-semibold">{{ $fiscalperiod->closing_date->format('M d, Y') }}</p>
            </div>
            <div>
                <p class="text-gray-600 font-medium">Opening Balance</p>
                <p class="text-gray-900 font-semibold">${{ number_format($fiscalperiod->opening_balance, 2) }}</p>
            </div>
            <div>
                <p class="text-gray-600 font-medium">Closing Balance</p>
                <p class="text-gray-900 font-semibold">${{ number_format($fiscalperiod->closing_balance, 2) }}</p>
            </div>
        </div>
    </div>

    <!-- Balance Sheet -->
    <div class="mb-12">
        <h2 class="text-2xl font-bold mb-6">Balance Sheet</h2>
        
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-200 border-2 border-gray-400">
                        <th class="border border-gray-400 px-4 py-3 text-left font-semibold">Item Type</th>
                        <th class="border border-gray-400 px-4 py-3 text-left font-semibold">Name</th>
                        <th class="border border-gray-400 px-4 py-3 text-center font-semibold">Amount</th>
                        <th class="border border-gray-400 px-4 py-3 text-left font-semibold">As Of Date</th>
                        <th class="border border-gray-400 px-4 py-3 text-left font-semibold">Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($balanceSheetItems as $item)
                        <tr class="border">
                            <td class="border border-gray-400 px-4 py-3 font-semibold">{{ ucfirst($item->item_type) }}</td>
                            <td class="border border-gray-400 px-4 py-3">{{ $item->name }}</td>
                            <td class="border border-gray-400 px-4 py-3 text-right">${{ number_format($item->amount, 2) }}</td>
                            <td class="border border-gray-400 px-4 py-3">{{ $item->as_of_date->format('Y-m-d') }}</td>
                            <td class="border border-gray-400 px-4 py-3">{{ $item->reference_number ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr class="border">
                            <td colspan="5" class="border border-gray-400 px-4 py-3 text-center text-gray-600">No items recorded</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Summary -->
    <div class="mb-12">
        <h2 class="text-2xl font-bold mb-6">Summary</h2>
        <div class="grid grid-cols-3 gap-6">
            <div class="border-2 border-blue-300 rounded-lg p-6">
                <p class="text-gray-700 font-semibold">Total Assets</p>
                <p class="text-3xl font-bold text-blue-600 mt-2">${{ number_format($summary['total_assets'], 2) }}</p>
            </div>
            <div class="border-2 border-red-300 rounded-lg p-6">
                <p class="text-gray-700 font-semibold">Total Liabilities</p>
                <p class="text-3xl font-bold text-red-600 mt-2">${{ number_format($summary['total_liabilities'], 2) }}</p>
            </div>
            <div class="border-2 border-green-300 rounded-lg p-6">
                <p class="text-gray-700 font-semibold">Total Equity</p>
                <p class="text-3xl font-bold text-green-600 mt-2">${{ number_format($summary['total_equity'], 2) }}</p>
            </div>
        </div>
    </div>

    <!-- Balance Sheet Equation -->
    <div class="mb-12 p-6 {{ $summary['balance_check'] ? 'bg-green-50' : 'bg-yellow-50' }} border {{ $summary['balance_check'] ? 'border-green-300' : 'border-yellow-300' }} rounded-lg">
        <h3 class="font-bold {{ $summary['balance_check'] ? 'text-green-900' : 'text-yellow-900' }} mb-3">Balance Sheet Equation</h3>
        <p class="text-sm {{ $summary['balance_check'] ? 'text-green-700' : 'text-yellow-700' }}">
            <strong>Assets (${{ number_format($summary['total_assets'], 2) }})</strong> = 
            <strong>Liabilities (${{ number_format($summary['total_liabilities'], 2) }})</strong> + 
            <strong>Equity (${{ number_format($summary['total_equity'], 2) }})</strong>
        </p>
        <p class="text-xs {{ $summary['balance_check'] ? 'text-green-700' : 'text-yellow-700' }} mt-2">
            {{ $summary['balance_check'] ? '✓ Balanced' : '✗ Not Balanced' }}
        </p>
    </div>

    <div class="text-center text-gray-500 text-xs mt-16 pt-8 border-t">
        <p>This document was generated on {{ now()->format('F d, Y \a\t H:i:s') }}</p>
        <p>For official use only</p>
    </div>
</div>

<style media="print">
    body {
        background: white;
    }
    .no-print {
        display: none !important;
    }
</style>

<script>
    window.addEventListener('load', function() {
        window.print();
    });
</script>
@endsection
