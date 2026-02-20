@extends('layouts.admin')

@section('title', 'Edit Floor')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Floor</h1>
            <p class="text-gray-600 mt-1">Update floor information and manage apartment units</p>
        </div>
        <a href="{{ route('admin.floors.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition duration-200">
            Back to Floors
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

    @if ($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
        <h3 class="font-semibold mb-2">Please fix the following errors:</h3>
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Update Floor Form -->
    <form method="POST" action="{{ route('admin.floors.update', $floor) }}" class="space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="action" value="update_floor">
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
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
                        <input type="text" name="floor_name" required value="{{ old('floor_name', $floor->floor_name) }}"
                               placeholder="e.g., 1st Floor, Ground Floor, Floor A"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('floor_name') border-red-500 @enderror">
                        @error('floor_name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <input type="text" name="description" value="{{ old('description', $floor->description) }}"
                               placeholder="Optional description of the floor..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">
                        @error('description')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Existing Apartments Section -->
            @if($floor->apartments->count() > 0)
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                    </svg>
                    Existing Apartment Units ({{ $floor->apartments->count() }})
                </h3>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @foreach($floor->apartments as $apartment)
                    <div class="bg-white border border-purple-200 rounded-lg p-4 flex items-center justify-between group hover:shadow-md transition">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                <span class="text-sm font-bold text-purple-600">{{ substr($apartment->apartment_number, 0, 1) }}</span>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">{{ $apartment->apartment_number }}</p>
                                <div class="flex gap-4 text-xs text-gray-600">
                                    <span>💰 ${{ number_format($apartment->monthly_rent, 2) }}</span>
                                    <span>• 
                                        @if($apartment->status === 'available')
                                            <span class="text-green-600">🟢 Available</span>
                                        @elseif($apartment->status === 'occupied')
                                            <span class="text-red-600">🔴 Occupied</span>
                                        @else
                                            <span class="text-yellow-600">🟡 Maintenance</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                            <a href="{{ route('admin.apartments.edit', $apartment) }}" 
                               class="bg-blue-100 hover:bg-blue-200 text-blue-600 p-2 rounded transition" title="Edit">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                </svg>
                            </a>
                            <form action="{{ route('admin.apartments.destroy', $apartment) }}" method="POST" style="display:inline" 
                                  onsubmit="return confirm('Are you sure you want to delete this apartment?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="bg-red-100 hover:bg-red-200 text-red-600 p-2 rounded transition" title="Delete">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        
        </div>
    </form>

    <!-- Add New Apartments Section (Outside main form) -->
    <div class="bg-gradient-to-br from-emerald-50 via-white to-emerald-50 border border-emerald-200 rounded-xl p-6">
        <div class="mb-6">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-3 mb-1">
                <div class="p-2 bg-gradient-to-br from-emerald-500 to-green-600 rounded-lg">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                    </svg>
                </div>
                Add New Apartment Unit
            </h3>
            <p class="text-sm text-gray-600 ml-11">Add one apartment unit at a time</p>
        </div>
        
        <!-- Separate Form for Adding Apartment -->
        <form method="POST" action="{{ route('admin.floors.update', $floor) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="action" value="add_apartment">
            
            <div class="bg-white rounded-xl border-2 border-emerald-100 p-5 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="relative">
                        <div class="absolute left-0 top-0 w-1 h-8 bg-gradient-to-b from-emerald-500 to-transparent rounded-full"></div>
                        <label class="block text-xs font-semibold text-emerald-700 mb-2 uppercase tracking-wider">🏠 Unit Number</label>
                        <input type="text" name="apartment_number" 
                               placeholder="e.g., 101"
                               value="{{ old('apartment_number') }}"
                               class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-50 transition text-sm font-medium @error('apartment_number') border-red-500 @enderror">
                        @error('apartment_number')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="relative">
                        <div class="absolute left-0 top-0 w-1 h-8 bg-gradient-to-b from-blue-500 to-transparent rounded-full"></div>
                        <label class="block text-xs font-semibold text-blue-700 mb-2 uppercase tracking-wider">💰 Monthly Rent</label>
                        <input type="number" name="monthly_rent" step="0.01" min="0" 
                               placeholder="0.00"
                               value="{{ old('monthly_rent') }}"
                               class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-50 transition text-sm font-medium">
                    </div>
                    <div class="relative">
                        <div class="absolute left-0 top-0 w-1 h-8 bg-gradient-to-b from-purple-500 to-transparent rounded-full"></div>
                        <label class="block text-xs font-semibold text-purple-700 mb-2 uppercase tracking-wider">📊 Status</label>
                        <select name="apartment_status" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-50 transition text-sm font-medium bg-white" required>
                            <option value="available" {{ old('apartment_status', 'available') === 'available' ? 'selected' : '' }}>🟢 Available</option>
                            <option value="occupied" {{ old('apartment_status', 'available') === 'occupied' ? 'selected' : '' }}>🔴 Occupied</option>
                            <option value="maintenance" {{ old('apartment_status', 'available') === 'maintenance' ? 'selected' : '' }}>🟡 Maintenance</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" name="action" value="add_apartment" class="w-full bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-bold py-2.5 px-4 rounded-lg transition duration-300 flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Add Unit</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

