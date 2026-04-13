@extends('layouts.supervisor')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Payments</h1>
                <p class="text-sm text-gray-500 mt-1">
                    @if(isset($activePeriod) && $activePeriod)
                        Fiscal Period: {{ $activePeriod->name }} ({{ \Carbon\Carbon::parse($activePeriod->opening_date)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($activePeriod->closing_date)->format('M d, Y') }})
                    @else
                        Payment records for your assigned apartments
                    @endif
                </p>
            </div>
            <a href="{{ route('supervisor.payments.create') }}" class="mt-3 sm:mt-0 inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Record Payment
            </a>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
            <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span class="text-sm font-medium">{{ session('success') }}</span>
        </div>
        @endif

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow-md p-5">
                <p class="text-xs text-gray-500 uppercase font-medium">Total Collected</p>
                <p class="text-2xl font-bold text-green-600 mt-1">${{ number_format($stats['total_collected'], 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-5">
                <p class="text-xs text-gray-500 uppercase font-medium">This Period</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">${{ number_format($stats['this_period'], 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-5">
                <p class="text-xs text-gray-500 uppercase font-medium">Pending</p>
                <p class="text-2xl font-bold text-yellow-600 mt-1">${{ number_format($stats['pending'], 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-5">
                <p class="text-xs text-gray-500 uppercase font-medium">Overdue</p>
                <p class="text-2xl font-bold text-red-600 mt-1">{{ $stats['overdue_count'] }}</p>
            </div>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-lg shadow-md mb-6 p-6">
            <form method="GET" action="{{ route('supervisor.payments.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Apartment</label>
                    <select name="apartment" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        <option value="">All Apartments</option>
                        @foreach($apartments as $apt)
                            <option value="{{ $apt->id }}" {{ request('apartment') == $apt->id ? 'selected' : '' }}>{{ $apt->apartment_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Overdue</option>
                        <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                    <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        <option value="">All Types</option>
                        <option value="rent" {{ request('type') === 'rent' ? 'selected' : '' }}>Rent</option>
                        <option value="utilities" {{ request('type') === 'utilities' ? 'selected' : '' }}>Utilities</option>
                        <option value="deposit" {{ request('type') === 'deposit' ? 'selected' : '' }}>Deposit</option>
                        <option value="late_fee" {{ request('type') === 'late_fee' ? 'selected' : '' }}>Late Fee</option>
                        <option value="other" {{ request('type') === 'other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 border border-emerald-300 rounded-lg text-emerald-600 hover:bg-emerald-50 transition font-medium">Filter</button>
                    <a href="{{ route('supervisor.payments.index') }}" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium text-center">Reset</a>
                </div>
            </form>
        </div>

        {{-- Payments Table --}}
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Apartment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($payments as $payment)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $payment->rental?->tenant?->name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $payment->rental?->apartment?->apartment_number ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $payment->payment_type === 'rent' ? 'bg-blue-100 text-blue-800' :
                                       ($payment->payment_type === 'utilities' ? 'bg-purple-100 text-purple-800' :
                                       ($payment->payment_type === 'deposit' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800')) }}">
                                    {{ ucfirst(str_replace('_', ' ', $payment->payment_type)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                ${{ number_format($payment->amount, 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $payment->payment_status === 'paid' ? 'bg-green-100 text-green-800' :
                                       ($payment->payment_status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                       ($payment->payment_status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                                    {{ ucfirst($payment->payment_status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y') : ($payment->due_date ? \Carbon\Carbon::parse($payment->due_date)->format('M d, Y') : 'N/A') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="{{ route('supervisor.payments.show', $payment) }}" class="text-emerald-600 hover:text-emerald-900" title="View">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-sm">No payments found.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($payments->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $payments->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
