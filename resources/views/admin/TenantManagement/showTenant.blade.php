@extends('layouts.admin')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('admin.tenants.index') }}" class="text-blue-600 hover:text-blue-900 flex items-center mb-4">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Tenants
            </a>
            <h1 class="text-3xl font-bold text-gray-900">Tenant Details</h1>
        </div>

        <!-- Tenant Card -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <div class="flex items-center">
                    <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                        <span class="text-blue-600 font-semibold text-lg">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">{{ $tenant->name }}</h2>
                        <p class="text-sm text-gray-600">{{ $tenant->email }}</p>
                    </div>
                </div>
                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $tenant->status === 'active' ? 'bg-green-100 text-green-800' : ($tenant->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                    {{ ucfirst($tenant->status) }}
                </span>
            </div>

            <div class="p-6 space-y-6">
                <!-- Personal Information -->
                <div>
                    <h3 class="text-sm font-medium text-gray-600 uppercase tracking-wide mb-4">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Full Name</p>
                            <p class="text-sm font-medium text-gray-900 mt-1">{{ $tenant->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Email</p>
                            <p class="text-sm font-medium text-gray-900 mt-1">{{ $tenant->email }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Phone</p>
                            <p class="text-sm font-medium text-gray-900 mt-1">{{ $tenant->phone }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Date of Birth</p>
                            <p class="text-sm font-medium text-gray-900 mt-1">{{ $tenant->date_of_birth ? $tenant->date_of_birth->format('M d, Y') : 'Not provided' }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Address</p>
                            <p class="text-sm font-medium text-gray-900 mt-1">{{ $tenant->address ?: 'Not provided' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Tenancy Information -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-sm font-medium text-gray-600 uppercase tracking-wide mb-4">Tenancy Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Apartment</p>
                            <p class="text-sm font-medium text-gray-900 mt-1">{{ $tenant->apartment?->apartment_number ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Move In Date</p>
                            <p class="text-sm font-medium text-gray-900 mt-1">{{ $tenant->move_in_date ? $tenant->move_in_date->format('M d, Y') : 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Move Out Date</p>
                            <p class="text-sm font-medium text-gray-900 mt-1">{{ $tenant->move_out_date ? $tenant->move_out_date->format('M d, Y') : 'Not set' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Status</p>
                            <p class="text-sm font-medium text-gray-900 mt-1">{{ ucfirst($tenant->status) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Deposit Amount</p>
                            <p class="text-sm font-medium text-gray-900 mt-1">${{ number_format($tenant->deposit ?? 0, 2) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                @if($tenant->notes)
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-sm font-medium text-gray-600 uppercase tracking-wide mb-4">Notes</h3>
                        <p class="text-sm text-gray-700">{{ $tenant->notes }}</p>
                    </div>
                @endif

                <!-- Active Rentals -->
                @if($tenant->rentals->count() > 0)
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-sm font-medium text-gray-600 uppercase tracking-wide mb-4">Active Rentals</h3>
                        <div class="space-y-2">
                            @foreach($tenant->rentals as $rental)
                                <div class="bg-gray-50 rounded p-3">
                                    <p class="text-sm font-medium text-gray-900">{{ $rental->apartment?->apartment_number ?? 'Unknown Apartment' }}</p>
                                    <p class="text-xs text-gray-600 mt-1">Start: {{ $rental->start_date->format('M d, Y') }} | End: {{ $rental->end_date?->format('M d, Y') ?? 'Ongoing' }}</p>
                                    <p class="text-xs text-gray-600">Rent: ${{ number_format($rental->monthly_rent ?? 0, 2) }}/month</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-3">
            <a href="{{ route('admin.tenants.edit', $tenant->id) }}" class="flex-1 px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition text-center">
                Edit Tenant
            </a>
            @if($tenant->status === 'active')
                <a href="{{ route('admin.tenants.leave', $tenant->id) }}" class="flex-1 px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 transition text-center">
                    Process Leave
                </a>
            @endif
            <a href="{{ route('admin.tenants.index') }}" class="flex-1 px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 hover:bg-gray-50 transition text-center">
                Back
            </a>
        </div>
    </div>
</div>
@endsection
