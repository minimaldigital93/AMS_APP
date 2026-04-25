@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold">Balance Sheet</h1>
            <p class="text-sm text-gray-500">{{ $fiscalperiod->name }}</p>
        </div>
        <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200">← Back</a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
            <p class="text-green-800 text-sm">{{ session('success') }}</p>
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
            <ul class="text-red-700 text-sm space-y-1">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Summary --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500 uppercase">Assets</p>
            <p class="text-lg font-bold text-blue-600">${{ number_format($summary['total_assets'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500 uppercase">Liabilities</p>
            <p class="text-lg font-bold text-red-600">${{ number_format($summary['total_liabilities'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <p class="text-xs text-gray-500 uppercase">Equity</p>
            <p class="text-lg font-bold text-green-600">${{ number_format($summary['total_equity'], 2) }}</p>
        </div>
    </div>

    {{-- Operating Performance & Retained Earnings --}}
    <div class="bg-white rounded-lg shadow p-5 mb-6">
        <h3 class="font-semibold text-sm text-gray-700 mb-3">Operating Performance (Revenue & Expenses)</h3>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <h4 class="text-xs text-gray-500 uppercase font-semibold mb-2">Revenue</h4>
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
                        <p class="text-gray-400 text-xs">No revenue recorded yet</p>
                    @endforelse
                    <div class="flex justify-between border-t pt-1 font-semibold">
                        <span>Total Revenue</span>
                        <span class="text-green-700">${{ number_format($summary['total_income'], 2) }}</span>
                    </div>
                </div>
            </div>
            <div>
                <h4 class="text-xs text-gray-500 uppercase font-semibold mb-2">Expenses</h4>
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
                        <p class="text-gray-400 text-xs">No expenses recorded yet</p>
                    @endforelse
                    <div class="flex justify-between border-t pt-1 font-semibold">
                        <span>Total Expenses</span>
                        <span class="text-red-700">${{ number_format($summary['total_expenses'], 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-3 pt-3 border-t text-center">
            <div>
                <p class="text-xs text-gray-400">Retained Earnings</p>
                <p class="font-bold text-sm {{ ($summary['retained_earnings'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    ${{ number_format($summary['retained_earnings'] ?? 0, 2) }}
                </p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Adjusted Equity</p>
                <p class="font-bold text-sm text-purple-600">${{ number_format($summary['adjusted_equity'] ?? $summary['total_equity'], 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Cash from Operations</p>
                <p class="font-bold text-sm text-blue-600">${{ number_format($summary['cash_from_operations'] ?? 0, 2) }}</p>
            </div>
        </div>
    </div>

    {{-- Add Item Form --}}
    <div class="bg-white rounded-lg shadow p-5 mb-6">
        <h3 class="font-semibold mb-4">Add Item</h3>
        <form method="POST" action="{{ route('admin.fiscalperiod.storeBalanceItem', $fiscalperiod->id) }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Type</label>
                    <select id="item_type" name="item_type" required onchange="updateSubTypes()"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option value="">Select...</option>
                        <option value="asset" {{ old('item_type') === 'asset' ? 'selected' : '' }}>Asset</option>
                        <option value="liability" {{ old('item_type') === 'liability' ? 'selected' : '' }}>Liability</option>
                        <option value="equity" {{ old('item_type') === 'equity' ? 'selected' : '' }}>Equity</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Sub Type</label>
                    <select id="sub_type" name="sub_type" required
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option value="">Select...</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g., Cash in Bank"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Amount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2 text-gray-500 text-sm">$</span>
                        <input type="number" name="amount" value="{{ old('amount') }}" required step="0.01" min="0"
                            class="w-full pl-7 pr-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Date</label>
                    <input type="date" name="as_of_date" value="{{ old('as_of_date') }}" required
                        min="{{ $fiscalperiod->opening_date->format('Y-m-d') }}" max="{{ $fiscalperiod->closing_date->format('Y-m-d') }}"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white appearance-none h-10">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Reference (optional)</label>
                    <input type="text" name="reference_number" value="{{ old('reference_number') }}" placeholder="e.g., INV-001"
                        class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Notes (optional)</label>
                <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Any additional notes"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg font-semibold hover:bg-blue-700 text-sm">
                Add Item
            </button>
        </form>
    </div>

    {{-- Items List --}}
    @php
        $assets = $balanceSheetItems->get('asset', collect());
        $liabilities = $balanceSheetItems->get('liability', collect());
        $equity = $balanceSheetItems->get('equity', collect());
        $groups = [
            ['label' => 'Assets', 'items' => $assets, 'color' => 'blue'],
            ['label' => 'Liabilities', 'items' => $liabilities, 'color' => 'red'],
            ['label' => 'Equity', 'items' => $equity, 'color' => 'green'],
        ];
    @endphp

    @foreach($groups as $group)
        @if($group['items']->count())
            <div class="bg-white rounded-lg shadow mb-4">
                <div class="px-5 py-3 border-b">
                    <h3 class="font-semibold text-{{ $group['color'] }}-700 text-sm">{{ $group['label'] }}</h3>
                </div>
                <div class="divide-y">
                    @foreach($group['items'] as $item)
                        <div class="flex items-center justify-between px-5 py-3">
                            <div>
                                <p class="font-medium text-sm">{{ $item->name }}</p>
                                <p class="text-xs text-gray-500">{{ $item->as_of_date->format('M d, Y') }}{{ $item->reference_number ? ' · ' . $item->reference_number : '' }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-bold text-sm text-{{ $group['color'] }}-600">${{ number_format($item->amount, 2) }}</span>
                                <form method="POST" action="{{ route('admin.fiscalperiod.deleteBalanceItem', [$fiscalperiod->id, $item->id]) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button onclick="return confirm('Delete this item?')" class="text-red-400 hover:text-red-600 text-xs">Delete</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

    @if($balanceSheetItems->isEmpty())
        <div class="bg-white rounded-lg shadow p-6 text-center">
            <p class="text-sm text-gray-400">No items yet. Use the form above to add balance sheet items.</p>
        </div>
    @endif
</div>

<script>
const subtypes = {
    'asset': ['Cash', 'Accounts Receivable', 'Property', 'Equipment', 'Other Asset'],
    'liability': ['Accounts Payable', 'Loans', 'Deposits Held', 'Other Liability'],
    'equity': ['Retained Earnings', 'Capital', 'Other Equity']
};
function updateSubTypes() {
    const type = document.getElementById('item_type').value;
    const sel = document.getElementById('sub_type');
    sel.innerHTML = '<option value="">Select...</option>';
    if (type && subtypes[type]) {
        subtypes[type].forEach(s => {
            const o = document.createElement('option');
            o.value = s.toLowerCase().replace(/ /g, '_');
            o.textContent = s;
            sel.appendChild(o);
        });
    }
}
document.addEventListener('DOMContentLoaded', updateSubTypes);
</script>
@endsection
