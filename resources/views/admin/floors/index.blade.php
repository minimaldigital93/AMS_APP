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
        <a href="{{ route('admin.floors.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 flex items-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            Add Floor
        </a>
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
        <form method="GET" action="{{ route('admin.floors.index') }}" class="flex gap-4 flex-wrap items-end">
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
                <a href="{{ route('admin.floors.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition duration-200">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Floors List View -->
    <div class="space-y-6">
        @forelse($floors as $floor)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <!-- Floor Header -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <h3 class="text-2xl font-bold">{{ $floor->floor_name }}</h3>
                        @if($floor->description)
                        <p class="text-blue-100 mt-2">{{ $floor->description }}</p>
                        @endif
                        <div class="flex items-center gap-6 mt-4">
                            @php
                                $total = $floor->apartments->count();
                                $available = $floor->apartments->where('status', 'available')->count();
                                $occupied = $floor->apartments->where('status', 'occupied')->count();
                                $maintenance = $floor->apartments->where('status', 'maintenance')->count();
                            @endphp
                            <div>
                                <p class="text-blue-100 text-sm">Total Units</p>
                                <p class="text-2xl font-bold">{{ $total }}</p>
                            </div>
                            <div>
                                <p class="text-green-100 text-sm">Available</p>
                                <p class="text-2xl font-bold">{{ $available }}</p>
                            </div>
                            <div>
                                <p class="text-blue-100 text-sm">Occupied</p>
                                <p class="text-2xl font-bold">{{ $occupied }}</p>
                            </div>
                            <div>
                                <p class="text-yellow-100 text-sm">Maintenance</p>
                                <p class="text-2xl font-bold">{{ $maintenance }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.floors.edit', $floor) }}" 
                           class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white p-3 rounded-lg transition" title="Edit Floor">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('admin.floors.destroy', $floor) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete {{ addslashes($floor->floor_name) }}? This action cannot be undone. All apartments will also be deleted.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white p-3 rounded-lg transition" title="Delete Floor">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Floor Body -->
            <div class="px-6 py-4 flex items-center justify-between">
                <button onclick="openApartmentsModal('modal-floor-{{ $floor->id }}')" 
                        class="inline-flex items-center gap-2 text-green-600 hover:text-green-900 font-medium py-2 px-4 rounded-lg hover:bg-green-50 transition" title="View Apartments">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    View Apartments ({{ $floor->apartments->count() }})
                </button>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
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
    <div class="flex justify-center mt-8">
        {{ $floors->links() }}
    </div>
    @endif
</div>

<!-- Floor Apartments Modals -->
@foreach($floors as $floor)
<div id="modal-floor-{{ $floor->id }}" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-200 sticky top-0 bg-white z-10 flex items-center justify-between">
            <h2 class="text-xl font-bold text-gray-900">Apartments in {{ $floor->floor_name }}</h2>
            <button onclick="closeApartmentsModal('modal-floor-{{ $floor->id }}')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6">
            @if($floor->apartments->count() > 0)
                <!-- Statistics Section -->
                @php
                    $total = $floor->apartments->count();
                    $available = $floor->apartments->where('status', 'available')->count();
                    $occupied = $floor->apartments->where('status', 'occupied')->count();
                    $maintenance = $floor->apartments->where('status', 'maintenance')->count();
                @endphp
                <div class="grid grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-xs text-blue-600 uppercase font-semibold">Total Units</p>
                        <p class="text-3xl font-bold text-blue-900">{{ $total }}</p>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <p class="text-xs text-green-600 uppercase font-semibold">Available</p>
                        <p class="text-3xl font-bold text-green-900">{{ $available }}</p>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-xs text-blue-600 uppercase font-semibold">Occupied</p>
                        <p class="text-3xl font-bold text-blue-900">{{ $occupied }}</p>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <p class="text-xs text-yellow-600 uppercase font-semibold">Maintenance</p>
                        <p class="text-3xl font-bold text-yellow-900">{{ $maintenance }}</p>
                    </div>
                </div>

                <!-- Apartments Table -->
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
                            @foreach($floor->apartments as $apartment)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-900">{{ $apartment->apartment_number }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-gray-900">${{ number_format($apartment->monthly_rent, 2) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($apartment->status === 'available') bg-green-100 text-green-800
                                        @elseif($apartment->status === 'occupied') bg-blue-100 text-blue-800
                                        @elseif($apartment->status === 'maintenance') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($apartment->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-gray-600 text-sm">
                                        {{ $apartment->supervisor ? $apartment->supervisor->name : 'N/A' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
            <div class="text-center py-12">
                <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <p class="text-gray-600 font-medium">No apartments assigned to this floor</p>
                <p class="text-gray-500 text-sm mt-2">Add apartments from the Apartment Management page</p>
            </div>
            @endif
        </div>

        <!-- Modal Footer -->
        <div class="p-6 border-t border-gray-200 sticky bottom-0 bg-white">
            <button type="button" onclick="closeApartmentsModal('modal-floor-{{ $floor->id }}')" class="w-full bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition">
                Close
            </button>
        </div>
    </div>
</div>
@endforeach

<!-- Include Apartments View Modal (for viewing apartments for a floor) -->

<script>
// View Apartments Modal
function openApartmentsModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
}

function closeApartmentsModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Close apartments modals when clicking outside
document.addEventListener('click', function(event) {
    const apartmentModals = document.querySelectorAll('[id^="modal-floor-"]');
    apartmentModals.forEach(modal => {
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
});

// Close apartments modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const apartmentModals = document.querySelectorAll('[id^="modal-floor-"]');
        apartmentModals.forEach(modal => {
            modal.classList.add('hidden');
        });
    }
});
</script>
@endsection
