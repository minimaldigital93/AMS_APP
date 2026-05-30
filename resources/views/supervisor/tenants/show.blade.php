@extends('layouts.supervisor')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="flex items-center gap-4 mb-8">
            <a href="{{ route('supervisor.tenants.index') }}" class="text-emerald-600 hover:text-emerald-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Tenant Details</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $tenant->name }}</p>
            </div>
        </div>

        {{-- Tenant Info Card --}}
        <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
            <div class="flex flex-col sm:flex-row items-start gap-6">
                <div class="h-20 w-20 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold text-2xl shrink-0">
                    {{ strtoupper(substr($tenant->name, 0, 1)) }}
                </div>
                <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase">Full Name</label>
                        <p class="text-sm font-semibold text-gray-900">{{ $tenant->name }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase">Email</label>
                        <p class="text-sm text-gray-700">{{ $tenant->email ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase">Phone</label>
                        <p class="text-sm text-gray-700">{{ $tenant->phone ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase">Address</label>
                        <p class="text-sm text-gray-700">{{ $tenant->address ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase">Status</label>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tenant->status === 'active' ? 'bg-green-100 text-green-800' : ($tenant->trashed() ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800') }}">
                            {{ $tenant->trashed() ? 'Departed' : ucfirst($tenant->status) }}
                        </span>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 uppercase">Date of Birth</label>
                        <p class="text-sm text-gray-700">{{ $tenant->date_of_birth ? \Carbon\Carbon::parse($tenant->date_of_birth)->format('M d, Y') : 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Apartment Info --}}
        <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Apartment Information</h2>
            @if($tenant->apartment)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Apartment</label>
                    <p class="text-sm font-semibold text-gray-900">{{ $tenant->apartment->apartment_number }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Monthly Rent</label>
                    <p class="text-sm text-gray-700">${{ number_format($tenant->apartment->monthly_rent, 2) }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Deposit</label>
                    <p class="text-sm text-gray-700">${{ number_format($tenant->deposit ?? 0, 2) }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Move In Date</label>
                    <p class="text-sm text-gray-700">{{ $tenant->move_in_date ? $tenant->move_in_date->format('M d, Y') : 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Move Out Date</label>
                    <p class="text-sm text-gray-700">{{ $tenant->move_out_date ? \Carbon\Carbon::parse($tenant->move_out_date)->format('M d, Y') : 'Not set' }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Floor</label>
                    <p class="text-sm text-gray-700">{{ $tenant->apartment->floor?->floor_name ?? 'N/A' }}</p>
                </div>
            </div>
            @else
            <p class="text-gray-400 text-sm">No apartment assigned.</p>
            @endif
        </div>

        {{-- Rental History --}}
        <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Rental History</h2>
            @if($tenant->rentals && $tenant->rentals->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Apartment</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Start Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">End Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rent</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($tenant->rentals as $rental)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $rental->apartment?->apartment_number ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ \Carbon\Carbon::parse($rental->start_date)->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $rental->end_date ? \Carbon\Carbon::parse($rental->end_date)->format('M d, Y') : 'Ongoing' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">${{ number_format($rental->rent_amount, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $rental->end_date && \Carbon\Carbon::parse($rental->end_date)->lt(now()) ? 'bg-gray-100 text-gray-600' : 'bg-green-100 text-green-800' }}">
                                    {{ $rental->end_date && \Carbon\Carbon::parse($rental->end_date)->lt(now()) ? 'Ended' : 'Active' }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p class="text-gray-400 text-sm">No rental history available.</p>
            @endif
        </div>

        {{-- Utilities --}}
        @if($tenant->utilities && $tenant->utilities->count() > 0)
        <div class="bg-white rounded-xl border border-slate-100 p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Utility Records</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($tenant->utilities as $utility)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-700">{{ ucfirst($utility->utility_type) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">${{ number_format($utility->amount, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $utility->billing_period ?? 'N/A' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $utility->status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ ucfirst($utility->status) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Actions --}}
        @if(!$tenant->trashed())
        <div class="flex items-center gap-3">
            <a href="{{ route('supervisor.tenants.edit', $tenant) }}" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit Tenant
            </a>
            <a href="{{ route('supervisor.tenants.leave', $tenant) }}" class="inline-flex items-center gap-2 bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Process Leave
            </a>
            <form action="{{ route('supervisor.tenants.destroy', $tenant) }}" method="POST" onsubmit="return confirm('Are you sure you want to remove this tenant? This action will free the apartment and archive the tenant.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Delete
                </button>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
