@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Record Income</h1>
            <p class="text-gray-600 mt-2">Auto-generate monthly rent or record other income — Fiscal Period: <span class="font-semibold text-blue-600">{{ $activePeriod->name }}</span></p>
        </div>
        <a href="{{ route('admin.revenue_expense.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back
        </a>
    </div>

    <!-- Success / Error Messages -->
    @if(session('success'))
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
            <p class="text-gray-600 text-sm font-medium">Total Monthly Rent Expected</p>
            <p class="text-3xl font-bold text-blue-600 mt-2">${{ number_format($totalRentExpected, 2) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ count($apartmentSummary) }} active rental(s)</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
            <p class="text-gray-600 text-sm font-medium">Total Rent Collected (This Period)</p>
            <p class="text-3xl font-bold text-green-600 mt-2">${{ number_format($totalRentCollected, 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-l-4 {{ $totalRentCollected >= $totalRentExpected ? 'border-green-500' : 'border-orange-500' }}">
            <p class="text-gray-600 text-sm font-medium">Collection Rate</p>
            <p class="text-3xl font-bold {{ $totalRentCollected >= $totalRentExpected ? 'text-green-600' : 'text-orange-600' }} mt-2">
                {{ $totalRentExpected > 0 ? round(($totalRentCollected / $totalRentExpected) * 100, 1) : 0 }}%
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left: Bulk + Individual Forms -->
        <div class="lg:col-span-2 space-y-6">

            <!-- ============================================ -->
            <!-- BULK MONTHLY RENT GENERATOR                  -->
            <!-- ============================================ -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-2 pb-3 border-b-2 border-blue-500 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Auto Generate Monthly Rent
                </h2>
                <p class="text-sm text-gray-500 mb-4">Select apartments and record monthly rent for all at once. Amounts are auto-filled from rental agreements.</p>

                @if($apartmentSummary && count($apartmentSummary) > 0)
                <form action="{{ route('admin.revenue_expense.store_income_bulk') }}" method="POST" id="bulkRentForm">
                    @csrf

                    <!-- Bulk Settings -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <div>
                            <label for="bulk_payment_date" class="block text-sm font-medium text-gray-700 mb-1">Payment Date <span class="text-red-500">*</span></label>
                            <input type="date" name="payment_date" id="bulk_payment_date" required value="{{ date('Y-m-d') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="bulk_payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method <span class="text-red-500">*</span></label>
                            <select name="payment_method" id="bulk_payment_method" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>
                    </div>

                    <!-- Apartment Table with Checkboxes -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full" id="bulkRentTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-center">
                                        <input type="checkbox" id="selectAll" class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500 cursor-pointer" title="Select/Deselect All">
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Apartment</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tenant</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Rent Amount ($)</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Late Fee ($)</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Collected</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($apartmentSummary as $index => $summary)
                                <tr class="hover:bg-gray-50 {{ $summary['paid_this_month'] ? 'bg-green-50' : '' }}">
                                    <td class="px-3 py-3 text-center">
                                        <input type="hidden" name="apartments[{{ $index }}][rental_id]" value="{{ $summary['rental']->id }}">
                                        <input type="checkbox" name="apartments[{{ $index }}][selected]" value="1"
                                            class="apt-checkbox w-4 h-4 text-blue-600 rounded focus:ring-blue-500 cursor-pointer"
                                            {{ $summary['paid_this_month'] ? '' : 'checked' }}>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold text-gray-900">{{ $summary['apartment']->apartment_number }}</span>
                                        <span class="text-xs text-gray-500 block">Floor {{ $summary['apartment']->floor->floor_number ?? 'N/A' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        {{ $summary['rental']->tenant->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <input type="number" name="apartments[{{ $index }}][amount]" step="0.01" min="0.01"
                                            value="{{ $summary['monthly_rent'] }}"
                                            class="w-28 px-2 py-1 text-right border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm font-semibold text-blue-600">
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <input type="number" name="apartments[{{ $index }}][late_fee]" step="0.01" min="0" value="0"
                                            class="w-24 px-2 py-1 text-right border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($summary['paid_this_month'])
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                Paid
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="text-sm font-medium text-green-600">${{ number_format($summary['collected'], 2) }}</span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-100">
                                <tr>
                                    <td class="px-3 py-3"></td>
                                    <td class="px-4 py-3 font-bold text-gray-900" colspan="2">Total Selected</td>
                                    <td class="px-4 py-3 text-right font-bold text-blue-600" id="totalSelectedAmount">${{ number_format($totalRentExpected, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-yellow-600" id="totalSelectedLateFee">$0.00</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3 text-right font-bold text-green-600">${{ number_format($totalRentCollected, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Bulk Action Buttons -->
                    <div class="mt-6 flex items-center gap-4">
                        <button type="submit" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium shadow-md">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            Record Selected Rent Payments
                        </button>
                        <span class="text-sm text-gray-500" id="selectedCount">{{ count($apartmentSummary) }} apartment(s) selected</span>
                    </div>
                </form>
                @else
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <p>No apartments with active rentals found.</p>
                </div>
                @endif
            </div>

            <!-- ============================================ -->
            <!-- RECORD OTHER INCOME (Individual)             -->
            <!-- ============================================ -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-2 pb-3 border-b-2 border-green-500 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    Record Other Income
                </h2>
                <p class="text-sm text-gray-500 mb-4">Manually record individual payments — utilities, deposits, or other income per apartment.</p>

                <form action="{{ route('admin.revenue_expense.store_income') }}" method="POST">
                    @csrf

                    <div class="mb-6">
                        <label for="rental_id" class="block text-sm font-medium text-gray-700 mb-2">Select Apartment <span class="text-red-500">*</span></label>
                        <select name="rental_id" id="rental_id" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition">
                            <option value="">-- Select an apartment --</option>
                            @foreach($apartments as $apartment)
                                @foreach($apartment->rentals as $rental)
                                <option value="{{ $rental->id }}" data-rent="{{ $rental->rent_amount }}" {{ old('rental_id') == $rental->id ? 'selected' : '' }}>
                                    {{ $apartment->apartment_number }} (Floor {{ $apartment->floor->floor_number ?? 'N/A' }})
                                    — {{ $rental->tenant->name ?? 'N/A' }}
                                    — Rent: ${{ number_format($rental->rent_amount, 2) }}
                                </option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="payment_type" class="block text-sm font-medium text-gray-700 mb-2">Payment Type <span class="text-red-500">*</span></label>
                            <select name="payment_type" id="payment_type" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="rent" {{ old('payment_type', 'rent') == 'rent' ? 'selected' : '' }}>Rent</option>
                                <option value="utilities" {{ old('payment_type') == 'utilities' ? 'selected' : '' }}>Utilities</option>
                                <option value="deposit" {{ old('payment_type') == 'deposit' ? 'selected' : '' }}>Deposit</option>
                                <option value="other" {{ old('payment_type') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">Amount ($) <span class="text-red-500">*</span></label>
                            <input type="number" name="amount" id="amount" step="0.01" min="0.01" required value="{{ old('amount') }}" placeholder="0.00"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                        <div>
                            <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">Payment Method <span class="text-red-500">*</span></label>
                            <select name="payment_method" id="payment_method" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="cash" {{ old('payment_method', 'cash') == 'cash' ? 'selected' : '' }}>Cash</option>
                                <option value="bank" {{ old('payment_method') == 'bank' ? 'selected' : '' }}>Bank Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label for="transaction_date" class="block text-sm font-medium text-gray-700 mb-2">Payment Date <span class="text-red-500">*</span></label>
                            <input type="date" name="transaction_date" id="transaction_date" required value="{{ old('transaction_date', date('Y-m-d')) }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                        <div>
                            <label for="late_fee" class="block text-sm font-medium text-gray-700 mb-2">Late Fee ($)</label>
                            <input type="number" name="late_fee" id="late_fee" step="0.01" min="0" value="{{ old('late_fee', '0') }}" placeholder="0.00"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                        <div>
                            <label for="transaction_reference" class="block text-sm font-medium text-gray-700 mb-2">Reference Number</label>
                            <input type="text" name="transaction_reference" id="transaction_reference" value="{{ old('transaction_reference') }}" placeholder="e.g. TXN-001234"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                    </div>

                    <div class="mt-6">
                        <label for="note" class="block text-sm font-medium text-gray-700 mb-2">Note</label>
                        <textarea name="note" id="note" rows="2" placeholder="Optional note about this payment..."
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">{{ old('note') }}</textarea>
                    </div>

                    <div class="mt-6 flex items-center gap-4">
                        <button type="submit" class="inline-flex items-center px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            Record Income
                        </button>
                        <button type="reset" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">Reset</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b-2 border-green-500">Recent Income Records</h3>

                @if($recentIncome->isEmpty())
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-gray-500 text-sm">No income recorded yet</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($recentIncome as $record)
                        <div class="p-3 bg-green-50 rounded-lg border border-green-100">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ ucfirst(str_replace('_', ' ', $record->category)) }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $record->description }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ \Carbon\Carbon::parse($record->transaction_date)->format('M d, Y') }}</p>
                                </div>
                                <p class="text-lg font-bold text-green-600">${{ number_format($record->amount, 2) }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    // ==========================================
    // Bulk Rent - Select All / Deselect All
    // ==========================================
    const selectAllCheckbox = document.getElementById('selectAll');
    const aptCheckboxes = document.querySelectorAll('.apt-checkbox');

    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            aptCheckboxes.forEach(cb => { cb.checked = this.checked; });
            updateBulkTotals();
        });

        aptCheckboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                selectAllCheckbox.checked = [...aptCheckboxes].every(c => c.checked);
                selectAllCheckbox.indeterminate = !selectAllCheckbox.checked && [...aptCheckboxes].some(c => c.checked);
                updateBulkTotals();
            });
        });
    }

    // ==========================================
    // Update total amounts when selection changes
    // ==========================================
    function updateBulkTotals() {
        let totalAmount = 0;
        let totalLateFee = 0;
        let selectedCount = 0;

        aptCheckboxes.forEach((cb, index) => {
            if (cb.checked) {
                const row = cb.closest('tr');
                const amountInput = row.querySelector('input[name$="[amount]"]');
                const lateFeeInput = row.querySelector('input[name$="[late_fee]"]');
                totalAmount += parseFloat(amountInput.value) || 0;
                totalLateFee += parseFloat(lateFeeInput.value) || 0;
                selectedCount++;
            }
        });

        document.getElementById('totalSelectedAmount').textContent = '$' + totalAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('totalSelectedLateFee').textContent = '$' + totalLateFee.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('selectedCount').textContent = selectedCount + ' apartment(s) selected';
    }

    // Listen to amount/late_fee input changes on the bulk table
    document.querySelectorAll('#bulkRentTable input[type="number"]').forEach(input => {
        input.addEventListener('input', updateBulkTotals);
    });

    // Initial total calculation
    if (selectAllCheckbox) {
        updateBulkTotals();
    }

    // ==========================================
    // Individual Form - Auto-fill rent amount
    // ==========================================
    const rentalSelect = document.getElementById('rental_id');
    const paymentTypeSelect = document.getElementById('payment_type');

    if (rentalSelect) {
        rentalSelect.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            const rentAmount = selected.getAttribute('data-rent');
            if (rentAmount && paymentTypeSelect.value === 'rent') {
                document.getElementById('amount').value = parseFloat(rentAmount).toFixed(2);
            }
        });
    }

    if (paymentTypeSelect) {
        paymentTypeSelect.addEventListener('change', function () {
            const selected = rentalSelect.options[rentalSelect.selectedIndex];
            const rentAmount = selected.getAttribute('data-rent');
            if (this.value === 'rent' && rentAmount) {
                document.getElementById('amount').value = parseFloat(rentAmount).toFixed(2);
            }
        });
    }
</script>
@endsection