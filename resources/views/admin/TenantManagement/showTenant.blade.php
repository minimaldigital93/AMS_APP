@extends('layouts.admin')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">Tenant Details</h1>
                <p class="text-slate-400 text-sm mt-1">View tenant information and rental history</p>
            </div>
            <a href="{{ route('admin.tenants.index') }}" class="text-slate-400 hover:text-slate-600 text-sm font-medium py-2 px-4 rounded-lg border border-slate-200 hover:border-slate-300 transition">
                Back to Tenants
            </a>
        </div>

        <!-- Tenant Card -->
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden mb-6">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <div class="flex items-center">
                    @if($tenant->photo_path && !str_ends_with($tenant->photo_path, '.pdf'))
                        <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}" class="h-12 w-12 rounded-full object-cover mr-4 border border-gray-300">
                    @else
                        <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center mr-4">
                            <span class="text-blue-600 font-semibold text-lg">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                        </div>
                    @endif
                    <div>
                        <h2 class="text-lg font-semibold text-slate-800">{{ $tenant->name }}</h2>
                        <p class="text-sm text-slate-500">{{ $tenant->email }}</p>
                    </div>
                    {{-- document link moved to Attached Document section below --}}
                </div>
                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full {{ $tenant->status === 'active' ? 'bg-emerald-50 text-emerald-600' : ($tenant->status === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600') }}">
                    {{ ucfirst($tenant->status) }}
                </span>
            </div>

            <div class="p-6 space-y-6">
                <!-- Tenant Photo (if available) -->
                @if($tenant->photo_path && !str_ends_with($tenant->photo_path, '.pdf'))
                    <div>
                        <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">Photo</h3>
                        <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}" class="max-w-sm h-auto rounded-lg shadow-md border border-gray-300">
                    </div>
                @endif

                <!-- Personal Information -->
                <div>
                    <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wide">Full Name</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $tenant->name }}</p>
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
                <div class="border-t border-slate-100 pt-6">
                    <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">Tenancy Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-xs text-slate-400 uppercase tracking-wide">Apartment</p>
                            <p class="text-sm font-medium text-slate-800 mt-1">{{ $tenant->apartment?->apartment_number ?? 'N/A' }}</p>
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
                    <div class="border-t border-slate-100 pt-6">
                        <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">Notes</h3>
                        <p class="text-sm text-slate-700">{{ $tenant->notes }}</p>
                    </div>
                @endif

                <!-- Attached Document -->
                @if($tenant->document_path)
                    <div class="border-t border-slate-100 pt-6">
                        <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">Attached Document</h3>
                        <a href="{{ asset('storage/' . $tenant->document_path) }}" target="_blank" class="inline-flex items-center gap-3 px-4 py-3 bg-slate-50 hover:bg-slate-100 rounded-lg border border-slate-200 hover:border-slate-300 transition group">
                            <div class="h-10 w-10 rounded-lg bg-red-50 flex items-center justify-center">
                                <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-slate-700 group-hover:text-slate-900">View Document</p>
                                <p class="text-xs text-slate-400">{{ basename($tenant->document_path) }}</p>
                            </div>
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </a>
                    </div>
                @endif

                <!-- Active Rentals -->
                @if($tenant->rentals->count() > 0)
                    <div class="border-t border-slate-100 pt-6">
                        <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">Active Rentals</h3>
                        <div class="space-y-2">
                            @foreach($tenant->rentals as $rental)
                                <div class="bg-slate-50 rounded p-3">
                                    <p class="text-sm font-medium text-slate-800">{{ $rental->apartment?->apartment_number ?? 'Unknown Apartment' }}</p>
                                    <p class="text-xs text-slate-500 mt-1">Start: {{ $rental->start_date->format('M d, Y') }} | End: {{ $rental->end_date?->format('M d, Y') ?? 'Ongoing' }}</p>
                                    <p class="text-xs text-slate-500">Rent: ${{ number_format($rental->monthly_rent ?? 0, 2) }}/month</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

     
    </div>
</div>
@endsection
