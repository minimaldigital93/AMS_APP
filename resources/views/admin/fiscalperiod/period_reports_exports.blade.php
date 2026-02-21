@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">Reports & Exports</h1>
        <p class="text-gray-600">{{ $fiscalperiod->name }} ({{ $fiscalperiod->opening_date->format('Y-m-d') }} - {{ $fiscalperiod->closing_date->format('Y-m-d') }})</p>
    </div>

    <!-- Balance Sheet Summary -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-600">
            <p class="text-sm text-gray-600 mb-1">Total Assets</p>
            <p class="text-3xl font-bold text-blue-600">${{ number_format($summary['total_assets'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-600">
            <p class="text-sm text-gray-600 mb-1">Total Liabilities</p>
            <p class="text-3xl font-bold text-red-600">${{ number_format($summary['total_liabilities'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-600">
            <p class="text-sm text-gray-600 mb-1">Total Equity</p>
            <p class="text-3xl font-bold text-green-600">${{ number_format($summary['total_equity'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6 border-l-4 {{ $summary['balance_check'] ? 'border-green-600' : 'border-yellow-600' }}">
            <p class="text-sm text-gray-600 mb-1">Balance Status</p>
            <p class="text-lg font-bold {{ $summary['balance_check'] ? 'text-green-600' : 'text-yellow-600' }}">
                {{ $summary['balance_check'] ? '✓ Balanced' : '✗ Unbalanced' }}
            </p>
        </div>
    </div>

    <!-- Export Options -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <!-- CSV Export -->
        <div class="bg-white rounded-lg shadow p-8">
            <div class="flex items-start gap-4 mb-6">
                <div class="bg-green-100 rounded-lg p-3">
                    <svg class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2H4a1 1 0 110-2V4zm3 3a1 1 0 100-2 1 1 0 000 2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Export as CSV</h3>
                    <p class="text-sm text-gray-600 mt-1">Download balance sheet data in Excel-compatible format</p>
                </div>
            </div>
            <ul class="space-y-2 mb-6 text-sm text-gray-700">
                <li>✓ All balance sheet items</li>
                <li>✓ Summary calculations</li>
                <li>✓ Easy to import into Excel/Google Sheets</li>
                <li>✓ Compatible with most accounting software</li>
            </ul>
            <a href="{{ route('admin.fiscalperiod.exportCSV', $fiscalperiod->id) }}" 
                class="w-full block text-center bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition">
                Download CSV
            </a>
        </div>

        <!-- Detailed Report View -->
        <div class="bg-white rounded-lg shadow p-8">
            <div class="flex items-start gap-4 mb-6">
                <div class="bg-blue-100 rounded-lg p-3">
                    <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 100 2H4a1 1 0 00-1 1v10a1 1 0 001 1h12a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 100 2h2v10H4V5zm2 3a1 1 0 100 2h6a1 1 0 100-2H6zm0 4a1 1 0 100 2h6a1 1 0 100-2H6z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Detailed Report</h3>
                    <p class="text-sm text-gray-600 mt-1">View comprehensive balance sheet report with all details</p>
                </div>
            </div>
            <ul class="space-y-2 mb-6 text-sm text-gray-700">
                <li>✓ Complete balance sheet breakdown</li>
                <li>✓ Assets, liabilities, and equity details</li>
                <li>✓ All reference numbers and notes</li>
                <li>✓ Printable format</li>
            </ul>
            <button onclick="window.print()" class="w-full block text-center bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                View & Print Report
            </button>
        </div>
    </div>

    <!-- Detailed Balance Sheet -->
    <div class="bg-white rounded-lg shadow p-8 mb-8">
        <h2 class="text-2xl font-semibold mb-8">Complete Balance Sheet</h2>

        <!-- Fiscal Period Info -->
        <div class="mb-8 p-6 bg-gray-50 rounded-lg">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-600">Period Name</p>
                    <p class="font-semibold">{{ $fiscalperiod->name }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Opening Date</p>
                    <p class="font-semibold">{{ $fiscalperiod->opening_date->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Closing Date</p>
                    <p class="font-semibold">{{ $fiscalperiod->closing_date->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Generated</p>
                    <p class="font-semibold">{{ now()->format('M d, Y H:i') }}</p>
                </div>
            </div>
        </div>

        <!-- Balance Sheet Items Table -->
        @if($balanceSheetItems->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-400">
                            <th class="px-4 py-3 text-left text-sm font-semibold">Item Type</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Sub Type</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Item Name</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Amount</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">As Of Date</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Reference</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($balanceSheetItems as $item)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $item->item_type === 'asset' ? 'bg-blue-100 text-blue-800' : ($item->item_type === 'liability' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800') }}">
                                        {{ ucfirst($item->item_type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ ucfirst(str_replace('_', ' ', $item->sub_type)) }}</td>
                                <td class="px-4 py-3 text-sm font-medium">{{ $item->name }}</td>
                                <td class="px-4 py-3 text-sm font-semibold">${{ number_format($item->amount, 2) }}</td>
                                <td class="px-4 py-3 text-sm">{{ $item->as_of_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-sm">{{ $item->reference_number ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ Str::limit($item->notes, 30) ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Subtotals -->
            <div class="mt-8 grid grid-cols-3 gap-4 p-6 bg-gray-50 rounded-lg border-2 border-gray-300">
                <div>
                    <p class="text-gray-600 font-semibold">Total Assets</p>
                    <p class="text-2xl font-bold text-blue-600">${{ number_format($summary['total_assets'], 2) }}</p>
                </div>
                <div>
                    <p class="text-gray-600 font-semibold">Total Liabilities</p>
                    <p class="text-2xl font-bold text-red-600">${{ number_format($summary['total_liabilities'], 2) }}</p>
                </div>
                <div>
                    <p class="text-gray-600 font-semibold">Total Equity</p>
                    <p class="text-2xl font-bold text-green-600">${{ number_format($summary['total_equity'], 2) }}</p>
                </div>
            </div>

            <!-- Balance Verification -->
            <div class="mt-6 p-6 {{ $summary['balance_check'] ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }} border rounded-lg">
                <h3 class="font-semibold {{ $summary['balance_check'] ? 'text-green-900' : 'text-yellow-900' }} mb-2">Balance Sheet Equation Verification</h3>
                <p class="{{ $summary['balance_check'] ? 'text-green-700' : 'text-yellow-700' }}">
                    <strong>Assets (${{ number_format($summary['total_assets'], 2) }})</strong> = 
                    <strong>Liabilities (${{ number_format($summary['total_liabilities'], 2) }})</strong> + 
                    <strong>Equity (${{ number_format($summary['total_equity'], 2) }})</strong>
                </p>
                <p class="text-sm {{ $summary['balance_check'] ? 'text-green-700' : 'text-yellow-700' }} mt-2">
                    {{ $summary['balance_check'] ? '✓ Balance sheet is correctly balanced!' : '⚠ Balance sheet is NOT balanced!' }}
                </p>
            </div>
        @else
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                <p class="text-yellow-900">No balance sheet items recorded yet. <a href="{{ route('admin.fiscalperiod.balance-sheet', $fiscalperiod->id) }}" class="text-blue-600 font-semibold">Add items now</a></p>
            </div>
        @endif
    </div>

    <!-- Period Summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
        <div class="bg-white rounded-lg shadow p-8">
            <h2 class="text-xl font-semibold mb-4">Opening & Closing Balances</h2>
            <div class="space-y-4">
                <div class="flex justify-between items-center pb-3 border-b">
                    <span class="text-gray-700">Opening Balance</span>
                    <span class="text-lg font-bold text-blue-600">${{ number_format($fiscalperiod->opening_balance, 2) }}</span>
                </div>
                <div class="flex justify-between items-center pb-3 border-b">
                    <span class="text-gray-700">Closing Balance</span>
                    <span class="text-lg font-bold text-green-600">${{ number_format($fiscalperiod->closing_balance, 2) }}</span>
                </div>
                <div class="flex justify-between items-center pt-3 bg-gray-50 px-3 py-2 rounded">
                    <span class="text-gray-900 font-semibold">Net Change</span>
                    <span class="text-lg font-bold {{ $fiscalperiod->closing_balance >= $fiscalperiod->opening_balance ? 'text-green-600' : 'text-red-600' }}">
                        {{ $fiscalperiod->closing_balance >= $fiscalperiod->opening_balance ? '+' : '' }}${{ number_format($fiscalperiod->closing_balance - $fiscalperiod->opening_balance, 2) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-8">
            <h2 class="text-xl font-semibold mb-4">Period Statistics</h2>
            <div class="space-y-4">
                <div class="flex justify-between items-center pb-3 border-b">
                    <span class="text-gray-700">Period Duration</span>
                    <span class="text-lg font-bold">{{ $fiscalperiod->opening_date->diffInDays($fiscalperiod->closing_date) }} days</span>
                </div>
                <div class="flex justify-between items-center pb-3 border-b">
                    <span class="text-gray-700">Total Items</span>
                    <span class="text-lg font-bold">{{ $balanceSheetItems->count() }}</span>
                </div>
                <div class="flex justify-between items-center pt-3 bg-gray-50 px-3 py-2 rounded">
                    <span class="text-gray-900 font-semibold">Period Status</span>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $fiscalperiod->status === 'open' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ ucfirst($fiscalperiod->status) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex gap-4">
        <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="flex-1 text-center bg-gray-400 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-500 transition">
            Back to Period
        </a>
        <a href="{{ route('admin.fiscalperiod.index') }}" class="flex-1 text-center bg-gray-400 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-500 transition">
            Back to List
        </a>
    </div>
</div>

<style media="print">
    .no-print { display: none; }
    button, a.no-print { display: none; }
</style>
@endsection
