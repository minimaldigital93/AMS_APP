@extends('layouts.admin')

@section('title', 'Apartment Management')

@section('content')
<div class="space-y-6">
    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Apartment Management</h1>
            <p class="text-gray-600 mt-1">Edit apartment unit names, prices, and manage apartment details</p>
        </div>
        <button onclick="openAddApartmentModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 flex items-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            Add Apartment
        </button>
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

    @if ($message = Session::get('error'))
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 flex items-center gap-3">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span>{{ $message }}</span>
    </div>
    @endif


    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form method="GET" action="{{ route('admin.apartments.index') }}" class="flex gap-4 flex-wrap items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" 
                       placeholder="Search by apartment number..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="min-w-[150px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">Floor</label>
                <select name="floor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Floors</option>
                    @foreach($floors as $floor)
                    <option value="{{ $floor->id }}" {{ request('floor_id') == $floor->id ? 'selected' : '' }}>
                        {{ $floor->floor_name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[150px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="available" {{ request('status') == 'available' ? 'selected' : '' }}>Available</option>
                    <option value="occupied" {{ request('status') == 'occupied' ? 'selected' : '' }}>Occupied</option>
                    <option value="maintenance" {{ request('status') == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                    Filter
                </button>
                <a href="{{ route('admin.apartments.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition duration-200">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Apartments by Floor -->
    <div class="space-y-6">
        @forelse($floorsWithApartments as $floor)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            @php
                $totalApartments = $floor->apartments->count();
                $availableCount = $floor->apartments->where('status', 'available')->count();
                $occupiedCount = $floor->apartments->where('status', 'occupied')->count();
                $maintenanceCount = $floor->apartments->where('status', 'maintenance')->count();
            @endphp
            <div class="px-6 py-5 bg-gradient-to-r from-blue-50 via-white to-indigo-50 border-b border-gray-200">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-widest text-blue-700">Floor</div>
                        <h2 class="text-2xl font-semibold text-gray-900">{{ $floor->floor_name }}</h2>
                        <p class="text-sm text-gray-600 mt-1">{{ $totalApartments }} apartments</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium bg-white border border-blue-100 text-blue-700 px-3 py-1 rounded-full">
                            Total: {{ $totalApartments }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium bg-green-50 border border-green-200 text-green-700 px-3 py-1 rounded-full">
                            Available: {{ $availableCount }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium bg-blue-50 border border-blue-200 text-blue-700 px-3 py-1 rounded-full">
                            Occupied: {{ $occupiedCount }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium bg-yellow-50 border border-yellow-200 text-yellow-700 px-3 py-1 rounded-full">
                            Maintenance: {{ $maintenanceCount }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="p-6">
                @if($floor->apartments->isEmpty())
                <div class="text-sm text-gray-500">No apartments found for this floor.</div>
                @else
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    @foreach($floor->apartments as $apartment)
                    <div class="border border-gray-200 rounded-xl p-5 bg-white hover:shadow-md hover:border-blue-100 transition" data-apartment-id="{{ $apartment->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-xs uppercase tracking-wider text-gray-500">Unit {{ $loop->iteration }}</div>
                                <div class="flex items-center gap-2">
                                    <!-- Tenant Status Indicator Dot -->
                                    @php
                                        $tenant = $apartment->tenants()->latest()->first();
                                        $tenantStatus = $tenant ? $tenant->status : null;
                                    @endphp
                                    <span class="w-3 h-3 rounded-full {{ 
                                        $tenantStatus === 'active' ? 'bg-blue-500' : 
                                        ($tenantStatus === 'pending' ? 'bg-orange-500' : 'bg-green-500') 
                                    }}" data-tenant-status-dot title="{{ $tenantStatus === 'active' ? 'Occupied' : ($tenantStatus === 'pending' ? 'Pending' : 'Available') }}"></span>
                                    <div class="text-xl font-semibold text-gray-900">{{ $apartment->apartment_number }}</div>
                                </div>
                            </div>
                            <div>
                                @if($apartment->status === 'available')
                                <span class="inline-flex items-center bg-green-100 text-green-800 text-xs px-2.5 py-0.5 rounded-full font-medium">
                                    Available
                                </span>
                                @elseif($apartment->status === 'occupied')
                                <span class="inline-flex items-center bg-blue-100 text-blue-800 text-xs px-2.5 py-0.5 rounded-full font-medium">
                                    Occupied
                                </span>
                                @else
                                <span class="inline-flex items-center bg-yellow-100 text-yellow-800 text-xs px-2.5 py-0.5 rounded-full font-medium">
                                    Maintenance
                                </span>
                                @endif
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg bg-gray-50 border border-gray-200 p-2">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Rent</div>
                                <div class="font-semibold text-gray-900">${{ number_format($apartment->monthly_rent, 2) }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-50 border border-gray-200 p-2">
                                <div class="text-xs text-gray-500 uppercase tracking-wider">Supervisor</div>
                                <div class="font-semibold text-gray-900">{{ $apartment->supervisor->name ?? 'Unassigned' }}</div>
                            </div>
                        </div>

                        <!-- Tenant Information -->
                        @php
                            $tenant = $apartment->tenants()->latest()->first();
                        @endphp
                        <div data-tenant-info>
                            @if($tenant)
                            <div class="mt-4 p-3 rounded-lg bg-gray-50 border border-gray-200">
                                <div class="text-xs text-gray-500 uppercase tracking-wider font-medium">Tenant</div>
                                <div class="mt-2 space-y-1">
                                    <div class="font-semibold text-gray-900">{{ $tenant->name }}</div>
                                    <div class="text-xs text-gray-600">{{ $tenant->email }}</div>
                                    <div class="text-xs text-gray-600">{{ $tenant->phone }}</div>
                                    <div class="mt-2 flex items-center gap-2">
                                        @if($tenant->status === 'active')
                                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded">
                                                <span class="w-2 h-2 rounded-full bg-blue-600"></span> Active
                                            </span>
                                        @elseif($tenant->status === 'pending')
                                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded">
                                                <span class="w-2 h-2 rounded-full bg-orange-600"></span> Pending
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-gray-200 text-gray-800 rounded">
                                                <span class="w-2 h-2 rounded-full bg-gray-600"></span> Inactive
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @else
                            <div class="mt-4 p-3 rounded-lg bg-gray-50 border border-gray-200 text-center">
                                <div class="text-xs text-gray-500 uppercase tracking-wider font-medium mb-2">Status</div>
                                <div class="text-sm text-gray-600">No tenant assigned</div>
                            </div>
                            @endif
                        </div>
                        <div class="mt-4 flex gap-2">
                            <button onclick="openAssignTenantModal({{ $apartment->id }}, '{{ $apartment->apartment_number }}')" 
                                    title="Assign tenant"
                                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-green-600 hover:bg-green-50 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                            </button>
                            <button onclick='openEditApartmentModal(@json($apartment))' 
                                    title="Edit apartment"
                                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-blue-600 hover:bg-blue-50 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button onclick="deleteApartment({{ $apartment->id }}, '{{ addslashes($apartment->apartment_number) }}')" 
                                    title="Delete apartment"
                                    class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-red-600 hover:bg-red-50 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
            <div class="text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <p class="font-medium">No floors found</p>
            </div>
        </div>
        @endforelse
    </div>
</div>

<!-- Add Apartment Modal -->
<div id="addApartmentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">Add New Apartment</h2>
        </div>
        <form method="POST" action="{{ route('admin.apartments.store') }}" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Apartment Number <span class="text-red-500">*</span></label>
                <input type="text" name="apartment_number" required 
                       placeholder="e.g., A101, 102, Unit 5B"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Floor <span class="text-red-500">*</span></label>
                <select name="floor_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select a floor</option>
                    @foreach($floors as $floor)
                    <option value="{{ $floor->id }}">{{ $floor->floor_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Monthly Rent <span class="text-red-500">*</span></label>
                <input type="number" name="monthly_rent" step="0.01" min="0" required 
                       placeholder="0.00"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
                <select name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Supervisor</label>
                <select name="supervisor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">No supervisor</option>
                    @foreach($supervisors as $supervisor)
                    <option value="{{ $supervisor->id }}">{{ $supervisor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="3" 
                          placeholder="Optional description..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                    Create Apartment
                </button>
                <button type="button" onclick="closeAddApartmentModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Apartment Modal -->
<div id="editApartmentModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">Edit Apartment</h2>
        </div>
        <form id="editApartmentForm" method="POST" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Apartment Number <span class="text-red-500">*</span></label>
                <input type="text" id="editApartmentNumber" name="apartment_number" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Floor <span class="text-red-500">*</span></label>
                <select id="editFloorId" name="floor_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select a floor</option>
                    @foreach($floors as $floor)
                    <option value="{{ $floor->id }}">{{ $floor->floor_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Monthly Rent <span class="text-red-500">*</span></label>
                <input type="number" id="editMonthlyRent" name="monthly_rent" step="0.01" min="0" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
                <select id="editStatus" name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Supervisor</label>
                <select id="editSupervisorId" name="supervisor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">No supervisor</option>
                    @foreach($supervisors as $supervisor)
                    <option value="{{ $supervisor->id }}">{{ $supervisor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea id="editDescription" name="description" rows="3" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                    Update Apartment
                </button>
                <button type="button" onclick="closeEditApartmentModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-3">
                <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                Delete Apartment
            </h2>
        </div>
        <div class="p-6">
            <p class="text-gray-700">Are you sure you want to delete apartment <span id="deleteApartmentNumber" class="font-bold"></span>? This action cannot be undone.</p>
        </div>
        <form id="deleteForm" method="POST" class="p-6 border-t border-gray-200">
            @csrf
            @method('DELETE')
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                    Delete
                </button>
                <button type="button" onclick="closeDeleteModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Tenant Modal -->
<div id="assignTenantModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-lg w-full">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">Assign Tenant to Apartment <span id="assignToApartment" class="text-blue-600"></span></h2>
            <p class="text-gray-600 text-sm mt-1">Create a new tenant and assign them to this apartment</p>
        </div>
        <form id="assignTenantForm" onsubmit="submitAssignTenant(event)" class="p-6 space-y-4">
            @csrf
            <input type="hidden" id="apartmentIdForTenant" name="apartment_id">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tenant Name *</label>
                    <input type="text" name="name" required placeholder="Full Name" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" required placeholder="Email Address" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                    <input type="tel" name="phone" required placeholder="Phone Number" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                    <select name="status" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Move In Date *</label>
                    <input type="date" name="move_in_date" required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deposit Amount</label>
                    <input type="number" name="deposit" step="0.01" min="0" placeholder="0.00" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                <input type="date" name="date_of_birth" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea name="address" rows="2" placeholder="Street address" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" placeholder="Additional notes about the tenant" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                    Assign Tenant
                </button>
                <button type="button" onclick="closeAssignTenantModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Assign Tenant Modal Functions
function openAssignTenantModal(apartmentId, apartmentNumber) {
    document.getElementById('apartmentIdForTenant').value = apartmentId;
    document.getElementById('assignToApartment').textContent = apartmentNumber;
    document.getElementById('assignTenantForm').reset();
    document.getElementById('assignTenantModal').classList.remove('hidden');
}

function closeAssignTenantModal() {
    document.getElementById('assignTenantModal').classList.add('hidden');
}

async function submitAssignTenant(event) {
    event.preventDefault();
    
    const formData = new FormData(document.getElementById('assignTenantForm'));
    const data = Object.fromEntries(formData);
    const apartmentId = data.apartment_id;

    console.log('📋 Form data being sent:', data);
    console.log('🏠 Apartment ID:', apartmentId);

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                         document.querySelector('input[name="_token"]')?.value;
        
        console.log('🔐 CSRF Token:', csrfToken ? 'Found' : 'NOT FOUND');
        
        if (!csrfToken) {
            alert('CSRF token not found. Please refresh the page and try again.');
            return;
        }

        console.log('📤 Sending POST request to /api/admin/tenants...');
        
        const response = await fetch('/api/admin/tenants', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(data)
        });

        console.log('✓ Response status:', response.status, response.statusText);
        
        const responseData = await response.json();
        
        console.log('✓ Response data:', responseData);

        if (response.ok) {
            alert('✓ Tenant assigned successfully!');
            closeAssignTenantModal();
            
            // Update the apartment card immediately without full reload
            const tenantData = responseData.data ? responseData.data : responseData;
            console.log('✓ Tenant data for card update:', tenantData);
            updateApartmentCard(apartmentId, tenantData);
            
            // Then reload page after a short delay to ensure consistency
            setTimeout(() => {
                console.log('🔄 Reloading page to ensure consistency...');
                window.location.reload();
            }, 1000);
        } else {
            console.error('✗ Error response:', responseData);
            alert('✗ Error: ' + (responseData.message || JSON.stringify(responseData.errors || responseData)));
        }
    } catch (error) {
        console.error('✗ Fetch error:', error);
        alert('✗ Error assigning tenant: ' + error.message);
    }
}

// Update apartment card with new tenant info immediately
function updateApartmentCard(apartmentId, tenantData) {
    console.log('🎨 Updating apartment card for apt ID:', apartmentId);
    console.log('📊 Tenant data:', tenantData);
    
    // Find all apartment cards and update the matching one
    const apartmentCards = document.querySelectorAll('[data-apartment-id]');
    console.log('🔎 Found apartment cards:', apartmentCards.length);
    
    let found = false;
    apartmentCards.forEach(card => {
        const cardAptId = parseInt(card.getAttribute('data-apartment-id'));
        console.log('  Checking card with apt ID:', cardAptId, 'vs target:', apartmentId);
        
        if (cardAptId === parseInt(apartmentId)) {
            found = true;
            console.log('  ✓ Match found! Updating...');
            
            // Update tenant status indicator dot
            const statusDot = card.querySelector('[data-tenant-status-dot]');
            console.log('    Status dot element:', statusDot ? 'Found' : 'NOT FOUND');
            
            if (statusDot) {
                statusDot.className = 'w-3 h-3 rounded-full';
                if (tenantData.status === 'active') {
                    statusDot.classList.add('bg-blue-500');
                    statusDot.setAttribute('title', 'Occupied');
                    console.log('    ✓ Status dot set to BLUE');
                } else if (tenantData.status === 'pending') {
                    statusDot.classList.add('bg-orange-500');
                    statusDot.setAttribute('title', 'Pending');
                    console.log('    ✓ Status dot set to ORANGE');
                } else {
                    statusDot.classList.add('bg-green-500');
                    statusDot.setAttribute('title', 'Available');
                    console.log('    ✓ Status dot set to GREEN');
                }
            }
            
            // Update tenant information display
            const tenantInfoDiv = card.querySelector('[data-tenant-info]');
            console.log('    Tenant info div:', tenantInfoDiv ? 'Found' : 'NOT FOUND');
            
            if (tenantInfoDiv) {
                tenantInfoDiv.innerHTML = `
                    <div class="mt-4 p-3 rounded-lg bg-gray-50 border border-gray-200">
                        <div class="text-xs text-gray-500 uppercase tracking-wider font-medium">Tenant</div>
                        <div class="mt-2 space-y-1">
                            <div class="font-semibold text-gray-900">${tenantData.name}</div>
                            <div class="text-xs text-gray-600">${tenantData.email}</div>
                            <div class="text-xs text-gray-600">${tenantData.phone}</div>
                            <div class="mt-2 flex items-center gap-2">
                                <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium ${
                                    tenantData.status === 'active' ? 'bg-blue-100 text-blue-800' :
                                    tenantData.status === 'pending' ? 'bg-orange-100 text-orange-800' :
                                    'bg-gray-200 text-gray-800'
                                } rounded">
                                    <span class="w-2 h-2 rounded-full ${
                                        tenantData.status === 'active' ? 'bg-blue-600' :
                                        tenantData.status === 'pending' ? 'bg-orange-600' :
                                        'bg-gray-600'
                                    }"></span> 
                                    ${tenantData.status.charAt(0).toUpperCase() + tenantData.status.slice(1)}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
                console.log('    ✓ Tenant info updated');
            }
        }
    });
    
    if (!found) {
        console.warn('✗ No apartment card found with ID:', apartmentId);
    }
}
                    </div>
                `;
            }
        }
    });
}

// Add Apartment Modal Functions
function openAddApartmentModal() {
    document.getElementById('addApartmentModal').classList.remove('hidden');
}

function closeAddApartmentModal() {
    document.getElementById('addApartmentModal').classList.add('hidden');
}

// Edit Apartment Modal Functions
function openEditApartmentModal(apartment) {
    document.getElementById('editApartmentNumber').value = apartment.apartment_number;
    document.getElementById('editFloorId').value = apartment.floor_id;
    document.getElementById('editMonthlyRent').value = apartment.monthly_rent;
    document.getElementById('editStatus').value = apartment.status;
    document.getElementById('editSupervisorId').value = apartment.supervisor_id || '';
    document.getElementById('editDescription').value = apartment.description || '';
    document.getElementById('editApartmentForm').action = `/admin/apartments/${apartment.id}`;
    document.getElementById('editApartmentModal').classList.remove('hidden');
}

function closeEditApartmentModal() {
    document.getElementById('editApartmentModal').classList.add('hidden');
}

// Delete Apartment Modal Functions
function deleteApartment(apartmentId, apartmentNumber) {
    document.getElementById('deleteApartmentNumber').textContent = apartmentNumber;
    document.getElementById('deleteForm').action = `/admin/apartments/${apartmentId}`;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const modals = ['addApartmentModal', 'editApartmentModal', 'deleteModal', 'assignTenantModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = ['addApartmentModal', 'editApartmentModal', 'deleteModal', 'assignTenantModal'];
        modals.forEach(modalId => {
            document.getElementById(modalId).classList.add('hidden');
        });
    }
});
</script>
@endsection
