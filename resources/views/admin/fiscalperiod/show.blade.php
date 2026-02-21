@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8">
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">{{ $fiscalperiod->name }}</h1>
                <p class="text-gray-600">
                    {{ $fiscalperiod->opening_date->format('F d, Y') }} - {{ $fiscalperiod->closing_date->format('F d, Y') }}
                </p>
            </div>
            <span class="px-4 py-2 rounded-full text-lg font-semibold {{ $fiscalperiod->status === 'open' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ ucfirst($fiscalperiod->status) }}
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Period Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Period Information</h2>
            <div class="space-y-3">
                <div>
                    <p class="text-gray-600 text-sm">Opening Balance</p>
                    <p class="text-2xl font-bold text-green-600">${{ number_format($fiscalperiod->opening_balance, 2) }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Closing Balance</p>
                    <p class="text-2xl font-bold text-blue-600">${{ number_format($fiscalperiod->closing_balance, 2) }}</p>
                </div>
                <div class="border-t pt-3">
                    <p class="text-gray-600 text-sm">Change in Balance</p>
                    <p class="text-lg font-semibold {{ $fiscalperiod->closing_balance >= $fiscalperiod->opening_balance ? 'text-green-600' : 'text-red-600' }}">
                        {{ $fiscalperiod->closing_balance >= $fiscalperiod->opening_balance ? '+' : '' }}${{ number_format($fiscalperiod->closing_balance - $fiscalperiod->opening_balance, 2) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Actions</h2>
            <div class="space-y-2">
                @if($fiscalperiod->status === 'open')
                    <a href="{{ route('admin.fiscalperiod.balance-sheet', $fiscalperiod->id) }}" 
                        class="w-full block text-center bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition">
                        Manage Balance Sheet Items
                    </a>
                    <a href="{{ route('admin.fiscalperiod.open-close-balances', $fiscalperiod->id) }}" 
                        class="w-full block text-center bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition">
                        Set Closing Balance
                    </a>
                    <a href="{{ route('admin.fiscalperiod.edit', $fiscalperiod->id) }}" 
                        class="w-full block text-center bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                        Edit Period
                    </a>
                @endif
                <a href="{{ route('admin.fiscalperiod.reports', $fiscalperiod->id) }}" 
                    class="w-full block text-center bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                    View Reports & Export
                </a>
                <a href="{{ route('admin.fiscalperiod.index') }}" 
                    class="w-full block text-center bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 transition">
                    Back to List
                </a>
            </div>
        </div>
    </div>

    <!-- Balance Sheet Summary -->
    <div class="bg-white rounded-lg shadow p-8 mb-8">
        <h2 class="text-2xl font-semibold mb-6">Balance Sheet Items</h2>
        
        @php
            $balanceSheetItems = $fiscalperiod->balanceSheets()->get()->groupBy('item_type');
            $totalAssets = $balanceSheetItems->get('asset', collect())->sum('amount');
            $totalLiabilities = $balanceSheetItems->get('liability', collect())->sum('amount');
            $totalEquity = $balanceSheetItems->get('equity', collect())->sum('amount');
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Assets -->
            <div class="border-l-4 border-blue-600 pl-4">
                <h3 class="text-lg font-semibold text-blue-900 mb-3">Assets</h3>
                <p class="text-3xl font-bold text-blue-600">${{ number_format($totalAssets, 2) }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ $balanceSheetItems->get('asset', collect())->count() }} items</p>
            </div>

            <!-- Liabilities -->
            <div class="border-l-4 border-red-600 pl-4">
                <h3 class="text-lg font-semibold text-red-900 mb-3">Liabilities</h3>
                <p class="text-3xl font-bold text-red-600">${{ number_format($totalLiabilities, 2) }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ $balanceSheetItems->get('liability', collect())->count() }} items</p>
            </div>

            <!-- Equity -->
            <div class="border-l-4 border-green-600 pl-4">
                <h3 class="text-lg font-semibold text-green-900 mb-3">Equity</h3>
                <p class="text-3xl font-bold text-green-600">${{ number_format($totalEquity, 2) }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ $balanceSheetItems->get('equity', collect())->count() }} items</p>
            </div>
        </div>

        @if($balanceSheetItems->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b">
                            <th class="px-4 py-3 text-left text-sm font-semibold">Type</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Name</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Amount</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Date</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold">Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($balanceSheetItems as $type => $items)
                            @foreach($items as $item)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm">
                                        <span class="px-2 py-1 rounded text-xs font-semibold {{ $type === 'asset' ? 'bg-blue-100 text-blue-800' : ($type === 'liability' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800') }}">
                                            {{ ucfirst($type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium">{{ $item->name }}</td>
                                    <td class="px-4 py-3 text-sm font-semibold">${{ number_format($item->amount, 2) }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $item->as_of_date->format('Y-m-d') }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $item->reference_number ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-600 text-center py-8">No balance sheet items added yet. <a href="{{ route('admin.fiscalperiod.balance-sheet', $fiscalperiod->id) }}" class="text-blue-600 font-semibold">Add items now</a></p>
        @endif
    </div>
</div>
@endsection
