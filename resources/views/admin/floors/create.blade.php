@extends('layouts.admin')

@section('title', 'Add New Floor')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Add New Floor</h1>
            <p class="text-gray-600 mt-1">Create a floor and optionally assign apartment units</p>
        </div>
        <a href="{{ route('admin.floors.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition duration-200">
            Back to Floors
        </a>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('admin.floors.store') }}" class="space-y-6">
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
                               value="{{ old('floor_name', session('floor_data')['floor_name'] ?? '') }}"
                               placeholder="e.g., 1st Floor, Ground Floor, Floor A"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('floor_name') border-red-500 @enderror">
                        @error('floor_name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <input type="text" name="description" 
                               value="{{ old('description', session('floor_data')['description'] ?? '') }}"
                               placeholder="Optional description of the floor..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">
                        @error('description')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Apartments Section -->
            <div class="bg-gradient-to-br from-emerald-50 via-white to-emerald-50 border border-emerald-200 rounded-xl p-6">
                <div class="mb-6">
                    <h3 class="text-xl font-bold text-gray-900 flex items-center gap-3 mb-1">
                        <div class="p-2 bg-gradient-to-br from-emerald-500 to-green-600 rounded-lg">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                            </svg>
                        </div>
                        Assign Apartment Units
                    </h3>
                    <p class="text-sm text-gray-600 ml-11">Add units one by one to build your floor</p>
                </div>
                
                <!-- Input Section -->
                <div class="bg-white rounded-xl border-2 border-emerald-100 p-5 mb-6 shadow-sm">
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
                            <select name="apartment_status" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-purple-500 focus:ring-4 focus:ring-purple-50 transition text-sm font-medium bg-white">
                                <option value="">-- Optional --</option>
                                <option value="available" {{ old('apartment_status') === 'available' ? 'selected' : '' }}>🟢 Available</option>
                                <option value="occupied" {{ old('apartment_status') === 'occupied' ? 'selected' : '' }}>🔴 Occupied</option>
                                <option value="maintenance" {{ old('apartment_status') === 'maintenance' ? 'selected' : '' }}>🟡 Maintenance</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" name="action" value="add_apartment"
                                    class="flex-1 bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-bold py-2.5 px-4 rounded-lg transition duration-300 flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span>Add Unit</span>
                            </button>
                            <button type="submit" name="action" value="create_floor"
                                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg transition duration-300 flex items-center justify-center gap-2 shadow-md hover:shadow-lg">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>Create</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Units Display List -->
                <div id="apartmentsContainer" class="space-y-2">
                    @if($tempApartments && count($tempApartments) > 0)
                        <div class="mb-4">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Added Units ({{ count($tempApartments) }})</h4>
                            @foreach($tempApartments as $index => $apt)
                            <div class="group bg-white border border-gray-200 rounded-lg p-4 hover:border-emerald-300 hover:shadow-md transition-all duration-300 flex items-center justify-between">
                                <div class="flex items-center gap-4 flex-1">
                                    <div class="w-14 h-14 bg-gradient-to-br from-emerald-100 to-green-100 rounded-lg flex items-center justify-center ring-2 ring-emerald-200 flex-shrink-0">
                                        <span class="text-lg font-black text-emerald-600">{{ substr($apt['apartment_number'], 0, 1) }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-gray-500 uppercase">Unit Number</p>
                                        <p class="text-lg font-bold text-gray-900">Unit {{ $apt['apartment_number'] }}</p>
                                    </div>
                                </div>
                                <div class="flex-1 hidden md:block px-4">
                                    <p class="text-xs font-semibold text-gray-500 uppercase">Monthly Rent</p>
                                    <p class="text-sm font-bold text-blue-600">${{ number_format($apt['monthly_rent'] ?? 0, 2) }}</p>
                                </div>
                                <div class="flex-1 hidden md:block px-4">
                                    <p class="text-xs font-semibold text-gray-500 uppercase">Status</p>
                                    <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold 
                                        @if($apt['status'] === 'available') bg-gradient-to-r from-green-100 to-green-50 text-green-700
                                        @elseif($apt['status'] === 'occupied') bg-gradient-to-r from-red-100 to-red-50 text-red-700
                                        @else bg-gradient-to-r from-yellow-100 to-yellow-50 text-yellow-700 @endif">
                                        {{ ucfirst($apt['status']) }}
                                    </span>
                                </div>
                                <div class="hidden lg:flex items-center gap-2 px-4">
                                    <div class="w-2 h-2 bg-emerald-500 rounded-full"></div>
                                    <p class="text-xs font-semibold text-emerald-600">Added</p>
                                </div>
                                <input type="hidden" name="apartments[{{ $index }}][apartment_number]" value="{{ $apt['apartment_number'] }}">
                                <input type="hidden" name="apartments[{{ $index }}][monthly_rent]" value="{{ $apt['monthly_rent'] ?? 0 }}">
                                <input type="hidden" name="apartments[{{ $index }}][status]" value="{{ $apt['status'] }}">
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 text-center py-8">No units added yet. Enter unit details and click "Add Unit" to begin.</p>
                    @endif
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-3 pt-4 border-t border-gray-200">
                <button type="submit" name="action" value="create_floor" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition">
                    Create Floor
                </button>
                <a href="{{ route('admin.floors.index') }}" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-3 px-4 rounded-lg transition text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
