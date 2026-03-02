@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Record Expense</h1>
            <p class="text-gray-600 mt-2">Record utility expenses per apartment — Fiscal Period: <span class="font-semibold text-blue-600">{{ $activePeriod->name }}</span></p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.revenue_expense.fixed_expenses') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Fixed Expenses
            </a>
            <a href="{{ route('admin.revenue_expense.generate_bills') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Generate Monthly Bills
            </a>
            <a href="{{ route('admin.revenue_expense.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
        </div>
    </div>

    <!-- Success / Error Messages -->
    @if(session('success'))
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
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

    <!-- Total Expenses Summary -->
    <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500 mb-8">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm font-medium">Total Utility Expenses (This Period)</p>
                <p class="text-3xl font-bold text-red-600 mt-2">${{ number_format($totalExpenses, 2) }}</p>
            </div>
            <div class="text-4xl text-red-100">
                <svg class="w-12 h-12 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left: Per-Apartment Expense Table + Form -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Per-Apartment Expense Breakdown -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4 pb-3 border-b-2 border-red-500">
                    Expenses per Apartment
                </h2>

                @if(count($apartmentExpenses) > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Apartment</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">⚡ Electricity</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">💧 Water</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">📡 Internet</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">🚗 Parking</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($apartmentExpenses as $aptExp)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-gray-900">{{ $aptExp['apartment']->apartment_number }}</span>
                                    <span class="text-xs text-gray-500 block">Floor {{ $aptExp['apartment']->floor->floor_number ?? 'N/A' }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $aptExp['has_active_rental'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $aptExp['has_active_rental'] ? 'Occupied' : 'Vacant' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm {{ $aptExp['electricity'] > 0 ? 'font-semibold text-yellow-600' : 'text-gray-400' }}">
                                    ${{ number_format($aptExp['electricity'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm {{ $aptExp['water'] > 0 ? 'font-semibold text-blue-600' : 'text-gray-400' }}">
                                    ${{ number_format($aptExp['water'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm {{ $aptExp['internet'] > 0 ? 'font-semibold text-purple-600' : 'text-gray-400' }}">
                                    ${{ number_format($aptExp['internet'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-sm {{ $aptExp['parking'] > 0 ? 'font-semibold text-orange-600' : 'text-gray-400' }}">
                                    ${{ number_format($aptExp['parking'], 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-red-600">
                                    ${{ number_format($aptExp['total'], 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100">
                            <tr>
                                <td class="px-4 py-3 font-bold text-gray-900" colspan="2">Grand Total</td>
                                <td class="px-4 py-3 text-right font-bold text-yellow-600">${{ number_format(collect($apartmentExpenses)->sum('electricity'), 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-blue-600">${{ number_format(collect($apartmentExpenses)->sum('water'), 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-purple-600">${{ number_format(collect($apartmentExpenses)->sum('internet'), 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-orange-600">${{ number_format(collect($apartmentExpenses)->sum('parking'), 2) }}</td>
                                <td class="px-4 py-3 text-right font-bold text-red-600">${{ number_format($totalExpenses, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <p>No apartments found.</p>
                </div>
                @endif
            </div>

            <!-- Record Expense Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6 pb-4 border-b-2 border-red-500">
                    Record Utility Expense for Apartment
                </h2>

                <form action="{{ route('admin.revenue_expense.store_expense') }}" method="POST">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Apartment -->
                        <div>
                            <label for="rental_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Select Apartment <span class="text-red-500">*</span>
                            </label>
                            <select name="rental_id" id="rental_id" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                                <option value="">-- Select an apartment --</option>
                                @foreach($apartments as $apartment)
                                    @foreach($apartment->rentals as $rental)
                                    <option value="{{ $rental->id }}" {{ old('rental_id') == $rental->id ? 'selected' : '' }}>
                                        {{ $apartment->apartment_number }} (Floor {{ $apartment->floor->floor_number ?? 'N/A' }})
                                        — {{ $rental->tenant->name ?? 'N/A' }}
                                    </option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>

                        <!-- Utility Type -->
                        <div>
                            <label for="utility_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Utility Type <span class="text-red-500">*</span>
                            </label>
                            <select name="utility_type" id="utility_type" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                                <option value="">-- Select type --</option>
                                @foreach($utilityTypes as $key => $label)
                                <option value="{{ $key }}" {{ old('utility_type') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Charge Amount -->
                        <div>
                            <label for="charge_amount" class="block text-sm font-medium text-gray-700 mb-2">
                                Charge Amount ($) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="charge_amount" id="charge_amount" step="0.01" min="0.01" required
                                value="{{ old('charge_amount') }}" placeholder="0.00"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                        </div>

                        <!-- Transaction Date -->
                        <div>
                            <label for="transaction_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Expense Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="transaction_date" id="transaction_date" required
                                value="{{ old('transaction_date', date('Y-m-d')) }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                        </div>
                    </div>

                    <!-- Meter Readings (for electricity) -->
                    <div id="meter-readings" class="mt-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200" style="display: none;">
                        <h3 class="text-sm font-semibold text-yellow-800 mb-3">Meter Readings (Electricity)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="meter_reading_in" class="block text-sm font-medium text-gray-700 mb-1">Meter In (Previous)</label>
                                <input type="number" name="meter_reading_in" id="meter_reading_in" step="0.01" min="0"
                                    value="{{ old('meter_reading_in') }}" placeholder="0.00"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                            </div>
                            <div>
                                <label for="meter_reading_out" class="block text-sm font-medium text-gray-700 mb-1">Meter Out (Current)</label>
                                <input type="number" name="meter_reading_out" id="meter_reading_out" step="0.01" min="0"
                                    value="{{ old('meter_reading_out') }}" placeholder="0.00"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                            </div>
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="mt-6">
                        <label for="note" class="block text-sm font-medium text-gray-700 mb-2">Note</label>
                        <textarea name="note" id="note" rows="2" placeholder="Optional note about this expense..."
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">{{ old('note') }}</textarea>
                    </div>

                    <!-- Submit -->
                    <div class="mt-6 flex items-center gap-4">
                        <button type="submit" class="inline-flex items-center px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            Record Expense
                        </button>
                        <button type="reset" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">Reset</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Sidebar: Recent Expenses -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b-2 border-red-500">Recent Expense Records</h3>

                @if($recentExpenses->isEmpty())
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-gray-500 text-sm">No expenses recorded yet</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($recentExpenses as $record)
                        <div class="p-3 bg-red-50 rounded-lg border border-red-100">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ ucfirst(str_replace('_', ' ', $record->category)) }}</p>
                                    <p class="text-xs text-gray-500 mt-1">{{ $record->description }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ \Carbon\Carbon::parse($record->transaction_date)->format('M d, Y') }}</p>
                                </div>
                                <p class="text-lg font-bold text-red-600">${{ number_format($record->amount, 2) }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Utility Type Legend -->
            <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b-2 border-blue-500">Expense Types</h3>
                <div class="space-y-3">
                    <div class="flex items-center gap-3 p-2 bg-yellow-50 rounded">
                        <span class="text-xl">⚡</span>
                        <div>
                            <p class="text-sm font-medium">Electricity</p>
                            <p class="text-xs text-gray-500">Meter-based consumption charges</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-2 bg-blue-50 rounded">
                        <span class="text-xl">💧</span>
                        <div>
                            <p class="text-sm font-medium">Water</p>
                            <p class="text-xs text-gray-500">Monthly water supply charges</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-2 bg-purple-50 rounded">
                        <span class="text-xl">📡</span>
                        <div>
                            <p class="text-sm font-medium">Internet</p>
                            <p class="text-xs text-gray-500">Internet service provider fees</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-2 bg-orange-50 rounded">
                        <span class="text-xl">🚗</span>
                        <div>
                            <p class="text-sm font-medium">Parking</p>
                            <p class="text-xs text-gray-500">Parking facility charges</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Show/hide meter readings based on utility type
    document.getElementById('utility_type').addEventListener('change', function() {
        const meterSection = document.getElementById('meter-readings');
        meterSection.style.display = this.value === 'electricity' ? 'block' : 'none';
    });

    // Show on load if electricity was previously selected
    if (document.getElementById('utility_type').value === 'electricity') {
        document.getElementById('meter-readings').style.display = 'block';
    }
</script>
@endsection