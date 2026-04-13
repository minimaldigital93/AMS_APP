@extends('layouts.supervisor')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="flex items-center gap-4 mb-8">
            <a href="{{ route('supervisor.payments.index') }}" class="text-emerald-600 hover:text-emerald-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Record Payment</h1>
                <p class="text-sm text-gray-500 mt-1">Record a payment for a tenant in your apartments</p>
            </div>
        </div>

        @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 text-sm">{{ session('error') }}</div>
        @endif

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
            <ul class="list-disc pl-4 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('supervisor.payments.store') }}" class="space-y-6">
            @csrf

            {{-- Rental Selection --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Select Tenant</h2>
                @if($rentals->count() > 0)
                <div>
                    <label for="rental_id" class="block text-sm font-medium text-gray-700 mb-2">Active Rental <span class="text-red-500">*</span></label>
                    <select name="rental_id" id="rental_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        <option value="">Select a tenant...</option>
                        @foreach($rentals as $rental)
                            <option value="{{ $rental->id }}" {{ old('rental_id') == $rental->id ? 'selected' : '' }}>
                                {{ $rental->tenant?->name ?? 'N/A' }} — {{ $rental->apartment?->apartment_number ?? 'N/A' }} (${{ number_format($rental->rent_amount, 2) }}/mo)
                            </option>
                        @endforeach
                    </select>
                </div>
                @else
                <div class="bg-yellow-50 border border-yellow-200 px-4 py-3 rounded-lg text-yellow-800 text-sm">
                    No active rentals found in your assigned apartments.
                </div>
                @endif
            </div>

            {{-- Payment Details --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Payment Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">Amount <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-gray-500">$</span>
                            <input type="number" name="amount" id="amount" value="{{ old('amount') }}" min="0.01" step="0.01" required
                                class="w-full pl-7 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        </div>
                    </div>
                    <div>
                        <label for="payment_type" class="block text-sm font-medium text-gray-700 mb-2">Payment Type <span class="text-red-500">*</span></label>
                        <select name="payment_type" id="payment_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                            <option value="rent" {{ old('payment_type') === 'rent' ? 'selected' : '' }}>Rent</option>
                            <option value="utilities" {{ old('payment_type') === 'utilities' ? 'selected' : '' }}>Utilities</option>
                            <option value="deposit" {{ old('payment_type') === 'deposit' ? 'selected' : '' }}>Deposit</option>
                            <option value="late_fee" {{ old('payment_type') === 'late_fee' ? 'selected' : '' }}>Late Fee</option>
                            <option value="other" {{ old('payment_type') === 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">Payment Method <span class="text-red-500">*</span></label>
                        <select name="payment_method" id="payment_method" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                            <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                            <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                            <option value="mobile_payment" {{ old('payment_method') === 'mobile_payment' ? 'selected' : '' }}>Mobile Payment</option>
                        </select>
                    </div>
                    <div>
                        <label for="transaction_date" class="block text-sm font-medium text-gray-700 mb-2">Transaction Date <span class="text-red-500">*</span></label>
                        <input type="date" name="transaction_date" id="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                    <div class="md:col-span-2">
                        <label for="note" class="block text-sm font-medium text-gray-700 mb-2">Note</label>
                        <textarea name="note" id="note" rows="2" placeholder="Optional payment note..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">{{ old('note') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('supervisor.payments.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-sm font-medium">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition text-sm font-medium" {{ $rentals->count() === 0 ? 'disabled' : '' }}>
                    Record Payment
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
