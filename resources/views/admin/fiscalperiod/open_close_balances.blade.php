@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">Set Closing Balance</h1>
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
            <div class="bg-white rounded-lg shadow p-6 sticky top-4 space-y-6">
                <!-- Opening Balance -->
                <div>
                    <p class="text-sm text-gray-600">Opening Balance</p>
                    <p class="text-3xl font-bold text-blue-600">${{ number_format($fiscalperiod->opening_balance, 2) }}</p>
                </div>

                <!-- Calculated Closing Balance -->
                <div class="border-t pt-6">
                    <p class="text-sm text-gray-600 mb-2">Balance Sheet Derived Closing Balance</p>
                    <p class="text-3xl font-bold text-green-600">${{ number_format($assets - $liabilities, 2) }}</p>
                    <p class="text-xs text-gray-500 mt-1">Assets (${{ number_format($assets, 2) }}) - Liabilities (${{ number_format($liabilities, 2) }})</p>
                </div>

                <!-- Balance Change -->
                <div class="border-t pt-6 bg-yellow-50 rounded p-4">
                    <p class="text-sm text-yellow-900 font-semibold">Net Change</p>
                    <p class="text-2xl font-bold {{ ($assets - $liabilities) >= $fiscalperiod->opening_balance ? 'text-green-600' : 'text-red-600' }}">
                        {{ ($assets - $liabilities) >= $fiscalperiod->opening_balance ? '+' : '' }}${{ number_format(($assets - $liabilities) - $fiscalperiod->opening_balance, 2) }}
                    </p>
                </div>

                <!-- Equity Summary -->
                <div class="border-t pt-6">
                    <p class="text-sm text-gray-600 mb-2">Total Equity</p>
                    <p class="text-2xl font-bold text-indigo-600">${{ number_format($equity, 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-8 mb-8">
                <h2 class="text-2xl font-semibold mb-8">Balance Closing Summary</h2>

                <!-- Balance Sheet Summary -->
                <div class="grid grid-cols-3 gap-4 mb-8 p-6 bg-gray-50 rounded-lg">
                    <div class="text-center">
                        <p class="text-gray-600 text-sm font-semibold">Total Assets</p>
                        <p class="text-2xl font-bold text-blue-600">${{ number_format($assets, 2) }}</p>
                    </div>
                    <div class="text-center border-l border-r border-gray-300">
                        <p class="text-gray-600 text-sm font-semibold">Total Liabilities</p>
                        <p class="text-2xl font-bold text-red-600">${{ number_format($liabilities, 2) }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-gray-600 text-sm font-semibold">Total Equity</p>
                        <p class="text-2xl font-bold text-green-600">${{ number_format($equity, 2) }}</p>
                    </div>
                </div>

                <!-- Balance Sheet Equation Check -->
                <div class="mb-8 p-4 {{ ($assets == ($liabilities + $equity)) ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }} border rounded-lg">
                    <h3 class="font-semibold {{ ($assets == ($liabilities + $equity)) ? 'text-green-900' : 'text-yellow-900' }} mb-2">Balance Sheet Equation</h3>
                    <p class="{{ ($assets == ($liabilities + $equity)) ? 'text-green-700' : 'text-yellow-700' }}">
                        Assets (${{ number_format($assets, 2) }}) = Liabilities (${{ number_format($liabilities, 2) }}) + Equity (${{ number_format($equity, 2) }})
                    </p>
                    @if($assets == ($liabilities + $equity))
                        <p class="text-sm text-green-700 mt-2">✓ Balance sheet is balanced!</p>
                    @else
                        <p class="text-sm text-yellow-700 mt-2">⚠ Balance sheet is not balanced. Difference: ${{ number_format(abs($assets - ($liabilities + $equity)), 2) }}</p>
                    @endif
                </div>

                <!-- Form to Set Closing Balance -->
                <form method="POST" action="{{ route('admin.fiscalperiod.closeperiod', $fiscalperiod->id) }}" class="space-y-6">
                    @csrf

                    <div>
                        <label for="closing_balance" class="block text-sm font-semibold text-gray-700 mb-3">
                            Closing Balance
                            <span class="text-red-500">*</span>
                        </label>
                        <p class="text-sm text-gray-600 mb-3">This is the final balance at the end of your fiscal period</p>
                        
                        <div class="relative mb-2">
                            <span class="absolute left-4 top-3 text-gray-600 text-lg">$</span>
                            <input type="number" 
                                id="closing_balance" 
                                name="closing_balance" 
                                value="{{ old('closing_balance', $assets - $liabilities) }}"
                                required 
                                step="0.01"
                                class="w-full pl-8 pr-4 py-3 text-lg border-2 border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"
                                onchange="validateClosingBalance()">
                        </div>

                        <div class="text-sm text-gray-600 p-3 bg-blue-50 rounded">
                            <p><strong>Suggested Closing Balance:</strong> ${{ number_format($assets - $liabilities, 2) }}</p>
                            <p class="mt-1 text-xs">This is calculated from your balance sheet items: Assets - Liabilities</p>
                        </div>

                        @error('closing_balance')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Period Status Change -->
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                        <h3 class="font-semibold text-orange-900 mb-2">Important Notice</h3>
                        <ul class="text-orange-800 text-sm space-y-1">
                            <li>✓ This will close the fiscal period</li>
                            <li>✗ You will not be able to add or edit balance items after closing</li>
                            <li>✓ You can still view reports and export data</li>
                            <li>✓ A new fiscal period can be created for the next cycle</li>
                        </ul>
                    </div>

                    <div class="flex gap-4">
                        <button type="submit" class="flex-1 bg-orange-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-orange-700 transition">
                            Close Fiscal Period
                        </button>
                        <a href="{{ route('admin.fiscalperiod.balance-sheet', $fiscalperiod->id) }}" class="flex-1 text-center bg-gray-400 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-500 transition">
                            Back to Items
                        </a>
                    </div>
                </form>
            </div>

            <!-- Period Review -->
            <div class="bg-white rounded-lg shadow p-8">
                <h2 class="text-xl font-semibold mb-6">Period Review</h2>
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600">Period Name</p>
                        <p class="text-lg font-semibold">{{ $fiscalperiod->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Period Duration</p>
                        <p class="text-lg font-semibold">{{ $fiscalperiod->opening_date->diffInDays($fiscalperiod->closing_date) }} days</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Opening Date</p>
                        <p class="text-lg font-semibold">{{ $fiscalperiod->opening_date->format('F d, Y') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Closing Date</p>
                        <p class="text-lg font-semibold">{{ $fiscalperiod->closing_date->format('F d, Y') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function validateClosingBalance() {
    const closingBalance = parseFloat(document.getElementById('closing_balance').value);
    const suggestedBalance = {{ $assets - $liabilities }};
    
    if (Math.abs(closingBalance - suggestedBalance) > 0.01) {
        console.warn('Closing balance differs from balance sheet derived balance');
    }
}
</script>
@endsection
