@extends('layouts.admin')

@section('title','Floor Management')

@section('content')
<div class="space-y-6">
    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Floor Management</h1>
            <p class="text-gray-600 mt-1">Manage building floors and assign apartment units</p>
        </div>
        <button onclick="openAddFloorModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 flex items-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            Add Floor
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


    <!-- Search and Filter -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form method="GET" action="{{ route('admin.propertymanagement.floors.index') }}" class="flex gap-4 flex-wrap items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" 
                       placeholder="Search by floor name or description..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                    Search
                </button>
                <a href="{{ route('admin.propertymanagement.floors.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition duration-200">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Floors Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($floors as $floor)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition duration-200">
            <!-- Floor Card Header -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-t-lg">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-xl font-bold">{{ $floor->floor_name }}</h3>
                        <p class="text-blue-100 text-sm mt-1">
                            {{ $floor->apartments_count }} {{ Str::plural('Apartment', $floor->apartments_count) }}
                        </p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-2">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Floor Card Body -->
            <div class="p-6">
                @if($floor->description)
                <p class="text-gray-600 text-sm mb-4">{{ Str::limit($floor->description, 100) }}</p>
                @else
                <p class="text-gray-400 text-sm italic mb-4">No description</p>
                @endif

                <!-- Floor Stats -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-500 uppercase">Total Units</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $floor->apartments_count }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-500 uppercase">Floor ID</p>
                        <p class="text-2xl font-bold text-gray-900">#{{ $floor->id }}</p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-3">
                    <button onclick="viewApartments({{ $floor->id }}, '{{ addslashes($floor->floor_name) }}')" 
                            class="text-green-600 hover:text-green-900">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                    <button onclick="openEditFloorModal({{ $floor->id }}, '{{ addslashes($floor->floor_name) }}', '{{ addslashes($floor->description) }}')" 
                            class="text-blue-600 hover:text-blue-900">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                    <button onclick="deleteFloor({{ $floor->id }}, '{{ addslashes($floor->floor_name) }}')" 
                            class="text-red-600 hover:text-red-900">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>

                <!-- Created Date -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <p class="text-xs text-gray-500">
                        Created: {{ $floor->created_at->format('M d, Y') }}
                    </p>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-full bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
            <div class="text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <p class="font-medium text-lg">No floors found</p>
                <p class="text-sm mt-2">Click "Add Floor" to create your first floor</p>
            </div>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($floors->hasPages())
    <div class="flex justify-center">
        {{ $floors->links() }}
    </div>
    @endif
</div>

<!-- Add Floor Modal -->
<div id="addFloorModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 sticky top-0 bg-white z-10">
            <h2 class="text-xl font-bold text-gray-900">Add New Floor</h2>
            <p class="text-sm text-gray-600 mt-1">Create a floor and optionally assign apartment units</p>
        </div>
        <form method="POST" action="{{ route('admin.propertymanagement.floors.store') }}" class="p-6 space-y-6">
            @csrf
            
            <!-- Floor Information -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                    </svg>
                    Floor Information
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Floor Name <span class="text-red-500">*</span></label>
                        <input type="text" name="floor_name" required 
                               placeholder="e.g., 1st Floor, Ground Floor, Floor A"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <input type="text" name="description"
                               placeholder="Optional description of the floor..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <!-- Apartments Section -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2 mb-3">
                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                        </svg>
                        Assign Apartment Units
                        <span class="text-sm font-normal text-gray-600">(Optional)</span>
                    </h3>
                    
                    <div class="bg-white rounded-lg p-4 space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Unit Numbers</label>
                                <textarea id="unitNumbers" rows="4" 
                                          placeholder="Enter unit numbers (one per line)&#10;Example:&#10;101&#10;102&#10;103&#10;104"
                                          class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 font-mono"></textarea>
                                <p class="text-xs text-gray-500 mt-1">💡 Tip: Enter one unit number per line</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Default Monthly Rent</label>
                                <input type="number" id="defaultRent" step="0.01" min="0" 
                                       placeholder="e.g., 1200.00"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                                <p class="text-xs text-gray-500 mt-1">This rent will apply to all units (can be edited later)</p>
                                
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Default Status</label>
                                    <select id="defaultStatus" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                                        <option value="available">Available</option>
                                        <option value="occupied">Occupied</option>
                                        <option value="maintenance">Maintenance</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                            <div id="unitPreview" class="text-sm text-gray-600">
                                <span class="font-medium">0 units</span> will be created
                            </div>
                            <button type="button" onclick="previewUnits()" 
                                    class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 flex items-center gap-2 text-sm">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                </svg>
                                Preview Units
                            </button>
                        </div>
                    </div>
                </div>

                <div id="apartmentsContainer" class="space-y-2 mt-4">
                    <!-- Preview of units will be shown here -->
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3 pt-4 border-t border-gray-200">
                <button type="submit" onclick="return prepareFormSubmit()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition">
                    Create Floor
                </button>
                <button type="button" onclick="closeAddFloorModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-3 px-4 rounded-lg transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Floor Modal -->
<div id="editFloorModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">Edit Floor</h2>
            <p class="text-sm text-gray-600 mt-1">Update floor information</p>
        </div>
        <form id="editFloorForm" method="POST" class="p-6 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Floor Name <span class="text-red-500">*</span></label>
                <input type="text" id="editFloorName" name="floor_name" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea id="editFloorDescription" name="description" rows="3" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                <p class="text-xs text-yellow-800">
                    <strong>Note:</strong> To manage apartments, use the "View Units" button or visit the Apartment Management page.
                </p>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                    Update Floor
                </button>
                <button type="button" onclick="closeEditFloorModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Apartments Modal -->
<div id="viewApartmentsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 sticky top-0 bg-white z-10">
            <h2 class="text-xl font-bold text-gray-900">Apartments in <span id="viewFloorName"></span></h2>
        </div>
        <div class="p-6">
            <div id="viewApartmentsContainer" class="space-y-4">
                <!-- Will be populated dynamically -->
            </div>
        </div>
        <div class="p-6 border-t border-gray-200 sticky bottom-0 bg-white">
            <button type="button" onclick="closeViewApartmentsModal()" class="w-full bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition">
                Close
            </button>
        </div>
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
                Delete Floor
            </h2>
        </div>
        <div class="p-6">
            <p class="text-gray-700">Are you sure you want to delete <span id="deleteFloorName" class="font-bold"></span>? This action cannot be undone.</p>
            <p class="text-red-600 text-sm mt-2"><strong>Warning:</strong> All apartments in this floor will also be deleted permanently.</p>
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

<script>
// Add Floor Modal Functions
function openAddFloorModal() {
    document.getElementById('addFloorModal').classList.remove('hidden');
    // Reset form
    document.getElementById('unitNumbers').value = '';
    document.getElementById('defaultRent').value = '';
    document.getElementById('defaultStatus').value = 'available';
    document.getElementById('apartmentsContainer').innerHTML = '';
    document.getElementById('unitPreview').innerHTML = '<span class="font-medium">0 units</span> will be created';
}

function closeAddFloorModal() {
    document.getElementById('addFloorModal').classList.add('hidden');
}

// Preview units based on input
function previewUnits() {
    const unitNumbersText = document.getElementById('unitNumbers').value.trim();
    const defaultRent = document.getElementById('defaultRent').value;
    const defaultStatus = document.getElementById('defaultStatus').value;
    const container = document.getElementById('apartmentsContainer');
    
    // Clear previous preview
    container.innerHTML = '';
    
    if (!unitNumbersText) {
        document.getElementById('unitPreview').innerHTML = '<span class="font-medium">0 units</span> will be created';
        return;
    }
    
    // Split by newlines and filter empty lines
    const unitNumbers = unitNumbersText.split('\n').map(u => u.trim()).filter(u => u.length > 0);
    
    if (unitNumbers.length === 0) {
        document.getElementById('unitPreview').innerHTML = '<span class="font-medium">0 units</span> will be created';
        return;
    }
    
    // Update preview count
    document.getElementById('unitPreview').innerHTML = `<span class="font-medium text-green-600">${unitNumbers.length} unit(s)</span> will be created`;
    
    // Create hidden inputs and preview
    const previewHTML = `
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Preview - ${unitNumbers.length} Unit(s)
            </h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 max-h-48 overflow-y-auto">
                ${unitNumbers.map((unitNum, index) => `
                    <div class="bg-gray-50 border border-gray-200 rounded px-3 py-2 text-sm">
                        <div class="font-medium text-gray-900">${unitNum}</div>
                        <div class="text-xs text-gray-600">${defaultRent ? '$' + parseFloat(defaultRent).toFixed(2) : 'No rent set'}</div>
                        <input type="hidden" name="apartments[${index}][apartment_number]" value="${unitNum}">
                        <input type="hidden" name="apartments[${index}][monthly_rent]" value="${defaultRent || 0}">
                        <input type="hidden" name="apartments[${index}][status]" value="${defaultStatus}">
                    </div>
                `).join('')}
            </div>
            <p class="text-xs text-gray-500 mt-3">
                💡 These units will be created with the default rent. You can edit individual units later in Apartment Management.
            </p>
        </div>
    `;
    
    container.innerHTML = previewHTML;
}

// Prepare form submission - automatically generate hidden inputs
function prepareFormSubmit() {
    const unitNumbersText = document.getElementById('unitNumbers').value.trim();
    const defaultRent = document.getElementById('defaultRent').value;
    const defaultStatus = document.getElementById('defaultStatus').value;
    const container = document.getElementById('apartmentsContainer');
    
    // Clear any existing hidden inputs
    container.innerHTML = '';
    
    // If no units specified, continue with just the floor
    if (!unitNumbersText) {
        return true;
    }
    
    // Split by newlines and filter empty lines
    const unitNumbers = unitNumbersText.split('\n').map(u => u.trim()).filter(u => u.length > 0);
    
    // Create hidden inputs for each unit
    unitNumbers.forEach((unitNum, index) => {
        const div = document.createElement('div');
        div.innerHTML = `
            <input type="hidden" name="apartments[${index}][apartment_number]" value="${unitNum}">
            <input type="hidden" name="apartments[${index}][monthly_rent]" value="${defaultRent || 0}">
            <input type="hidden" name="apartments[${index}][status]" value="${defaultStatus}">
        `;
        container.appendChild(div);
    });
    
    console.log(`Prepared ${unitNumbers.length} apartments for submission`);
    return true; // Allow form to submit
}

// Auto-preview on input change
document.addEventListener('DOMContentLoaded', function() {
    const unitNumbersField = document.getElementById('unitNumbers');
    const defaultRentField = document.getElementById('defaultRent');
    const defaultStatusField = document.getElementById('defaultStatus');
    
    if (unitNumbersField) {
        unitNumbersField.addEventListener('input', function() {
            const text = this.value.trim();
            if (text) {
                const count = text.split('\n').filter(u => u.trim().length > 0).length;
                document.getElementById('unitPreview').innerHTML = `<span class="font-medium">${count} unit(s)</span> ready to create`;
            } else {
                document.getElementById('unitPreview').innerHTML = '<span class="font-medium">0 units</span> will be created';
            }
        });
    }
});

// Edit Floor Modal Functions
function openEditFloorModal(floorId, floorName, description) {
    document.getElementById('editFloorName').value = floorName;
    document.getElementById('editFloorDescription').value = description || '';
    document.getElementById('editFloorForm').action = `/admin/floors/${floorId}`;
    document.getElementById('editFloorModal').classList.remove('hidden');
}

function closeEditFloorModal() {
    document.getElementById('editFloorModal').classList.add('hidden');
}

// View Apartments Modal Functions
function viewApartments(floorId, floorName) {
    document.getElementById('viewFloorName').textContent = floorName;
    const container = document.getElementById('viewApartmentsContainer');
    
    // Show loading state
    container.innerHTML = `
        <div class="text-center py-8">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <p class="text-gray-600 mt-2">Loading apartments...</p>
        </div>
    `;
    
    document.getElementById('viewApartmentsModal').classList.remove('hidden');
    
    // Fetch apartments for this floor
    fetch(`/admin/floors/${floorId}/apartments`)
        .then(response => response.json())
        .then(data => {
            console.log('Apartments data:', data); // Debug log
            
            if (data.success && data.data && data.data.length > 0) {
                container.innerHTML = `
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Unit #</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Monthly Rent</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Supervisor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                ${data.data.map(apartment => `
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <span class="font-medium text-gray-900">${apartment.apartment_number}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-gray-900">$${parseFloat(apartment.monthly_rent).toFixed(2)}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium
                                                ${apartment.status === 'available' ? 'bg-green-100 text-green-800' : 
                                                  apartment.status === 'occupied' ? 'bg-blue-100 text-blue-800' : 
                                                  'bg-yellow-100 text-yellow-800'}">
                                                ${apartment.status.charAt(0).toUpperCase() + apartment.status.slice(1)}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-gray-600 text-sm">
                                                ${apartment.supervisor ? apartment.supervisor.name : 'N/A'}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                console.log('No apartments found or invalid data structure');
                container.innerHTML = `
                    <div class="text-center py-12">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <p class="text-gray-600 font-medium">No apartments assigned to this floor</p>
                        <p class="text-gray-500 text-sm mt-2">Add apartments from the Apartment Management page</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error fetching apartments:', error);
            container.innerHTML = `
                <div class="text-center py-12 text-red-600">
                    <svg class="w-12 h-12 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <p class="font-medium">Failed to load apartments</p>
                    <p class="text-sm mt-2">Please try again later</p>
                </div>
            `;
        });
}

function closeViewApartmentsModal() {
    document.getElementById('viewApartmentsModal').classList.add('hidden');
}

// Delete Floor Modal Functions
function deleteFloor(floorId, floorName) {
    document.getElementById('deleteFloorName').textContent = floorName;
    document.getElementById('deleteForm').action = `/admin/floors/${floorId}`;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const modals = ['addFloorModal', 'editFloorModal', 'viewApartmentsModal', 'deleteModal'];
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
        const modals = ['addFloorModal', 'editFloorModal', 'viewApartmentsModal', 'deleteModal'];
        modals.forEach(modalId => {
            document.getElementById(modalId).classList.add('hidden');
        });
    }
});
</script>
@endsection
