@extends('layouts.admin')

@section('title', 'View Apartment')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Apartment {{ $apartment->apartment_number }}</h1>
            <p class="text-gray-600 mt-1">View complete apartment details</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.apartments.edit', $apartment->id) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
            </a>
            <a href="{{ route('admin.apartments.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition duration-200">
                Back to Apartments
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    @if ($message = Session::get('success'))
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700 flex items-center gap-3">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <span>{{ $message }}</span>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Apartment Information Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
                <h2 class="text-2xl font-semibold text-gray-900">Apartment Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Apartment Number -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Apartment Number</label>
                        <p class="text-lg font-semibold text-gray-900">{{ $apartment->apartment_number }}</p>
                    </div>

                    <!-- Floor -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Floor</label>
                        <p class="text-lg font-semibold text-gray-900">{{ $apartment->floor->floor_name ?? 'N/A' }}</p>
                    </div>

                    <!-- Monthly Rent -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Rent</label>
                        <p class="text-lg font-semibold text-gray-900">${{ number_format($apartment->monthly_rent, 2) }}</p>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <div>
                            @if($apartment->status === 'available')
                                <span class="inline-flex items-center bg-green-100 text-green-800 text-sm px-3 py-1 rounded-full font-medium">
                                    About Available
                                </span>
                            @elseif($apartment->status === 'occupied')
                                <span class="inline-flex items-center bg-blue-100 text-blue-800 text-sm px-3 py-1 rounded-full font-medium">
                                    Occupied
                                </span>
                            @else
                                <span class="inline-flex items-center bg-yellow-100 text-yellow-800 text-sm px-3 py-1 rounded-full font-medium">
                                    Maintenance
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Supervisor -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supervisor</label>
                        <p class="text-lg font-semibold text-gray-900">{{ $apartment->supervisor->name ?? 'Unassigned' }}</p>
                    </div>

                    <!-- Created Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Created Date</label>
                        <p class="text-lg font-semibold text-gray-900">{{ $apartment->created_at?->format('M d, Y') ?? 'N/A' }}</p>
                    </div>
                </div>

                <!-- Description -->
                @if($apartment->description)
                <div class="pt-4 border-t border-gray-200">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <p class="text-gray-700 whitespace-pre-wrap">{{ $apartment->description }}</p>
                </div>
                @endif
            </div>

            <!-- Tenant Information Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-2xl font-semibold text-gray-900 mb-6">Current Tenant</h2>
                
                @php
                    $tenant = $apartment->tenants()->latest()->first();
                @endphp

                @if($tenant)
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Tenant Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <p class="text-lg font-semibold text-gray-900">{{ $tenant->name }}</p>
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <p class="text-gray-700">
                                    <a href="mailto:{{ $tenant->email }}" class="text-blue-600 hover:underline">{{ $tenant->email }}</a>
                                </p>
                            </div>

                            <!-- Phone -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <p class="text-gray-700">
                                    <a href="tel:{{ $tenant->phone }}" class="text-blue-600 hover:underline">{{ $tenant->phone }}</a>
                                </p>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tenant Status</label>
                                <div>
                                    @if($tenant->status === 'active')
                                        <span class="inline-flex items-center gap-1 px-3 py-1 text-sm font-medium bg-blue-100 text-blue-800 rounded">
                                            <span class="w-2 h-2 rounded-full bg-blue-600"></span> Active
                                        </span>
                                    @elseif($tenant->status === 'pending')
                                        <span class="inline-flex items-center gap-1 px-3 py-1 text-sm font-medium bg-orange-100 text-orange-800 rounded">
                                            <span class="w-2 h-2 rounded-full bg-orange-600"></span> Pending
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-3 py-1 text-sm font-medium bg-gray-200 text-gray-800 rounded">
                                            <span class="w-2 h-2 rounded-full bg-gray-600"></span> Inactive
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Move In Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Move In Date</label>
                                <p class="text-gray-700">{{ $tenant->move_in_date?->format('M d, Y') ?? 'N/A' }}</p>
                            </div>

                            <!-- Deposit -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Deposit Amount</label>
                                <p class="text-gray-700">${{ number_format($tenant->deposit ?? 0, 2) }}</p>
                            </div>

                            <!-- Date of Birth -->
                            @if($tenant->date_of_birth)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                                <p class="text-gray-700">{{ $tenant->date_of_birth->format('M d, Y') }}</p>
                            </div>
                            @endif
                        </div>

                        <!-- Address -->
                        @if($tenant->address)
                        <div class="pt-4 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <p class="text-gray-700 whitespace-pre-wrap">{{ $tenant->address }}</p>
                        </div>
                        @endif

                        <!-- Notes -->
                        @if($tenant->notes)
                        <div class="pt-4 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <p class="text-gray-700 whitespace-pre-wrap">{{ $tenant->notes }}</p>
                        </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 8.646 4 4 0 010-8.646M9 9H3m18 0h-6m-2 5.5C10.5 15.5 8.97 16 7.5 16c-1.93 0-3.5 1.343-3.5 3" />
                        </svg>
                        <p class="text-gray-500 font-medium">No tenant assigned yet</p>
                        <p class="text-gray-400 text-sm mt-1">Assign a tenant to this apartment</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Quick Stats Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Quick Info</h3>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600 text-sm">Apartment ID</span>
                        <span class="font-semibold text-gray-900">{{ $apartment->id }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600 text-sm">Total Tenants</span>
                        <span class="font-semibold text-gray-900">{{ $apartment->tenants->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600 text-sm">Last Updated</span>
                        <span class="font-semibold text-gray-900">{{ $apartment->updated_at?->diffForHumans() ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Card -->
            @if($apartment->allTenants->count() > 0)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Tenants History</h3>
                
                <!-- Active Tenants -->
                @if($apartment->tenants->count() > 0)
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Active Tenants</h4>
                    <div class="space-y-3">
                        @foreach($apartment->tenants->take(5) as $historicalTenant)
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <p class="font-medium text-gray-900">{{ $historicalTenant->name }}</p>
                            <p class="text-xs text-gray-600 mt-1">From {{ $historicalTenant->move_in_date?->format('M d, Y') ?? 'Date unknown' }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                
                <!-- Archived Tenants -->
                @if($apartment->archivedTenants->count() > 0)
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Archived Tenants</h4>
                    <div class="space-y-3">
                        @foreach($apartment->archivedTenants->take(5) as $archivedTenant)
                        <div class="p-3 bg-amber-50 rounded-lg border border-amber-200">
                            <p class="font-medium text-gray-900">{{ $archivedTenant->name }}</p>
                            <p class="text-xs text-gray-600 mt-1">From {{ $archivedTenant->move_in_date?->format('M d, Y') ?? 'Date unknown' }} to {{ $archivedTenant->move_out_date?->format('M d, Y') ?? 'Unknown' }}</p>
                            <p class="text-xs text-amber-600 mt-1">Archived: {{ $archivedTenant->archived_at?->format('M d, Y') ?? 'N/A' }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
