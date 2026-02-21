@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">Balance Sheet Items</h1>
        <p class="text-gray-600">{{ $fiscalperiod->name }} ({{ $fiscalperiod->opening_date->format('Y-m-d') }} - {{ $fiscalperiod->closing_date->format('Y-m-d') }})</p>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <p class="text-green-800">{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-red-900 mb-2">Validation Errors:</h3>
            <ul class="text-red-700 space-y-1">
                @foreach($errors->all() as $error)
                    <li>• {{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Summary Panel -->
        <div>
            <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                <h2 class="text-xl font-semibold mb-6">Summary</h2>
                <div class="space-y-4">
                    <div class="border-l-4 border-blue-600 pl-4">
                        <p class="text-sm text-gray-600">Total Assets</p>
                        <p class="text-2xl font-bold text-blue-600">${{ number_format($summary['total_assets'], 2) }}</p>
                    </div>
                    <div class="border-l-4 border-red-600 pl-4">
                        <p class="text-sm text-gray-600">Total Liabilities</p>
                        <p class="text-2xl font-bold text-red-600">${{ number_format($summary['total_liabilities'], 2) }}</p>
                    </div>
                    <div class="border-l-4 border-green-600 pl-4">
                        <p class="text-sm text-gray-600">Total Equity</p>
                        <p class="text-2xl font-bold text-green-600">${{ number_format($summary['total_equity'], 2) }}</p>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 rounded p-3 mt-4">
                        <p class="text-xs text-yellow-900 font-semibold">Balance Check</p>
                        <p class="text-sm {{ $summary['balance_check'] ? 'text-green-700' : 'text-red-700' }}">
                            {{ $summary['balance_check'] ? '✓ Balanced' : '✗ Unbalanced' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2">
            <!-- Add New Item Form -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h2 class="text-xl font-semibold mb-6">Add Balance Sheet Item</h2>
                <form method="POST" action="{{ route('admin.fiscalperiod.storeBalanceItem', $fiscalperiod->id) }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="item_type" class="block text-sm font-semibold text-gray-700 mb-2">Item Type</label>
                            <select id="item_type" name="item_type" required onchange="updateSubTypes()"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Type...</option>
                                <option value="asset" {{ old('item_type') === 'asset' ? 'selected' : '' }}>Asset</option>
                                <option value="liability" {{ old('item_type') === 'liability' ? 'selected' : '' }}>Liability</option>
                                <option value="equity" {{ old('item_type') === 'equity' ? 'selected' : '' }}>Equity</option>
                            </select>
                            @error('item_type')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sub_type" class="block text-sm font-semibold text-gray-700 mb-2">Sub Type</label>
                            <select id="sub_type" name="sub_type" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Sub Type...</option>
                                <!-- Options will be populated dynamically -->
                            </select>
                            @error('sub_type')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Item Name</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g., Cash in Bank, Equipment">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">Description (Optional)</label>
                        <textarea id="description" name="description" rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Add any notes about this item"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="amount" class="block text-sm font-semibold text-gray-700 mb-2">Amount</label>
                            <div class="relative">
                                <span class="absolute left-4 top-2 text-gray-600">$</span>
                                <input type="number" id="amount" name="amount" value="{{ old('amount') }}" 
                                    required step="0.01" min="0"
                                    class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            @error('amount')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="as_of_date" class="block text-sm font-semibold text-gray-700 mb-2">As Of Date</label>
                            <input type="date" id="as_of_date" name="as_of_date" value="{{ old('as_of_date') }}" required
                                min="{{ $fiscalperiod->opening_date->format('Y-m-d') }}"
                                max="{{ $fiscalperiod->closing_date->format('Y-m-d') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @error('as_of_date')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="reference_number" class="block text-sm font-semibold text-gray-700 mb-2">Reference Number (Optional)</label>
                            <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="e.g., INV-001">
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-semibold text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="2"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Additional notes"></textarea>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition">
                        Add Item
                    </button>
                </form>
            </div>

            <!-- Balance Sheet Items List -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-6">Current Items</h2>

                @php
                    // Group items by type
                    $assets = $balanceSheetItems->get('asset', collect());
                    $liabilities = $balanceSheetItems->get('liability', collect());
                    $equity = $balanceSheetItems->get('equity', collect());
                @endphp

                @if($assets->count() > 0)
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-blue-900 mb-4 pb-2 border-b-2 border-blue-300">Assets</h3>
                        <div class="space-y-2">
                            @foreach($assets as $item)
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <div>
                                        <p class="font-medium">{{ $item->name }}</p>
                                        <p class="text-sm text-gray-600">{{ $item->sub_type ?? '-' }} • {{ $item->as_of_date->format('Y-m-d') }}</p>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <p class="text-lg font-bold text-blue-600">${{ number_format($item->amount, 2) }}</p>
                                        <form method="POST" action="{{ route('admin.fiscalperiod.deleteBalanceItem', [$fiscalperiod->id, $item->id]) }}" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium" onclick="return confirm('Delete this item?')">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($liabilities->count() > 0)
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-red-900 mb-4 pb-2 border-b-2 border-red-300">Liabilities</h3>
                        <div class="space-y-2">
                            @foreach($liabilities as $item)
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <div>
                                        <p class="font-medium">{{ $item->name }}</p>
                                        <p class="text-sm text-gray-600">{{ $item->sub_type ?? '-' }} • {{ $item->as_of_date->format('Y-m-d') }}</p>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <p class="text-lg font-bold text-red-600">${{ number_format($item->amount, 2) }}</p>
                                        <form method="POST" action="{{ route('admin.fiscalperiod.deleteBalanceItem', [$fiscalperiod->id, $item->id]) }}" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium" onclick="return confirm('Delete this item?')">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($equity->count() > 0)
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-green-900 mb-4 pb-2 border-b-2 border-green-300">Equity</h3>
                        <div class="space-y-2">
                            @foreach($equity as $item)
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <div>
                                        <p class="font-medium">{{ $item->name }}</p>
                                        <p class="text-sm text-gray-600">{{ $item->sub_type ?? '-' }} • {{ $item->as_of_date->format('Y-m-d') }}</p>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <p class="text-lg font-bold text-green-600">${{ number_format($item->amount, 2) }}</p>
                                        <form method="POST" action="{{ route('admin.fiscalperiod.deleteBalanceItem', [$fiscalperiod->id, $item->id]) }}" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800 font-medium" onclick="return confirm('Delete this item?')">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($balanceSheetItems->isEmpty())
                    <p class="text-gray-600 text-center py-8">No balance sheet items added yet. Use the form above.</p>
                @endif

                <div class="mt-8 flex gap-4">
                    <a href="{{ route('admin.fiscalperiod.open-close-balances', $fiscalperiod->id) }}" class="flex-1 text-center bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition">
                        Set Closing Balance
                    </a>
                    <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="flex-1 text-center bg-gray-400 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-500 transition">
                        Back
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const subtypes = {
    'asset': ['Cash', 'Accounts Receivable', 'Property', 'Equipment', 'Other Asset'],
    'liability': ['Accounts Payable', 'Loans', 'Deposits Held', 'Other Liability'],
    'equity': ['Retained Earnings', 'Capital', 'Other Equity']
};

function updateSubTypes() {
    const itemType = document.getElementById('item_type').value;
    const subTypeSelect = document.getElementById('sub_type');
    subTypeSelect.innerHTML = '<option value="">Select Sub Type...</option>';

    if (itemType && subtypes[itemType]) {
        subtypes[itemType].forEach(subtype => {
            const option = document.createElement('option');
            option.value = subtype.toLowerCase().replace(/ /g, '_');
            option.textContent = subtype;
            subTypeSelect.appendChild(option);
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSubTypes();
});
</script>
@endsection
