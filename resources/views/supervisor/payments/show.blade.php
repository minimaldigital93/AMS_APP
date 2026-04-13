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
                <h1 class="text-3xl font-bold text-gray-900">Payment Details</h1>
                <p class="text-sm text-gray-500 mt-1">Payment #{{ $payment->id }}</p>
            </div>
        </div>

        {{-- Payment Info --}}
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-bold text-gray-900">Payment Information</h2>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    {{ $payment->payment_status === 'paid' ? 'bg-green-100 text-green-800' :
                       ($payment->payment_status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                       ($payment->payment_status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                    {{ ucfirst($payment->payment_status) }}
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Amount</label>
                    <p class="text-2xl font-bold text-gray-900">${{ number_format($payment->amount, 2) }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Payment Type</label>
                    <p class="text-sm font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $payment->payment_type)) }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Payment Method</label>
                    <p class="text-sm text-gray-700">{{ ucfirst(str_replace('_', ' ', $payment->payment_method ?? 'N/A')) }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Paid Date</label>
                    <p class="text-sm text-gray-700">{{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y h:i A') : 'Not paid yet' }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Due Date</label>
                    <p class="text-sm text-gray-700">{{ $payment->due_date ? \Carbon\Carbon::parse($payment->due_date)->format('M d, Y') : 'N/A' }}</p>
                </div>
                @if($payment->late_fee > 0)
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Late Fee</label>
                    <p class="text-sm text-red-600 font-semibold">${{ number_format($payment->late_fee, 2) }}</p>
                </div>
                @endif
                @if($payment->note)
                <div class="md:col-span-2">
                    <label class="text-xs font-medium text-gray-500 uppercase">Note</label>
                    <p class="text-sm text-gray-700">{{ $payment->note }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Tenant & Apartment --}}
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Tenant & Apartment</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Tenant</label>
                    <p class="text-sm font-semibold text-gray-900">{{ $payment->rental?->tenant?->name ?? 'N/A' }}</p>
                    <p class="text-xs text-gray-500">{{ $payment->rental?->tenant?->email ?? '' }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Apartment</label>
                    <p class="text-sm font-semibold text-gray-900">{{ $payment->rental?->apartment?->apartment_number ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Monthly Rent</label>
                    <p class="text-sm text-gray-700">${{ number_format($payment->rental?->rent_amount ?? 0, 2) }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Rental Period</label>
                    <p class="text-sm text-gray-700">
                        {{ $payment->rental ? \Carbon\Carbon::parse($payment->rental->start_date)->format('M d, Y') : 'N/A' }}
                        —
                        {{ $payment->rental?->end_date ? \Carbon\Carbon::parse($payment->rental->end_date)->format('M d, Y') : 'Ongoing' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Timestamps --}}
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Record Info</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Created</label>
                    <p class="text-sm text-gray-700">{{ $payment->created_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Last Updated</label>
                    <p class="text-sm text-gray-700">{{ $payment->updated_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
