@extends('layouts.admin')

@section('title', 'Add Apartment')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Add New Apartment</h1>
            <p class="text-gray-600 mt-1">Create a new apartment unit in the system</p>
        </div>
        <a href="{{ route('admin.apartments.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition duration-200">
            Back to Apartments
        </a>
    </div>

    <!-- Flash Messages -->
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

    <!-- Form Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
        <form method="POST" action="{{ route('admin.apartments.store') }}" class="space-y-6">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Apartment Number -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Apartment Number <span class="text-red-500">*</span></label>
                    <input type="text" name="apartment_number" value="{{ old('apartment_number') }}" required 
                           placeholder="e.g., A101, 102, Unit 5B"
                           class="w-full px-4 py-2 border @error('apartment_number') border-red-500 @else border-gray-300 @enderror rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('apartment_number')
                    <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Floor -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Floor <span class="text-red-500">*</span></label>
                    <select name="floor_id" required class="w-full px-4 py-2 border @error('floor_id') border-red-500 @else border-gray-300 @enderror rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select a floor</option>
                        @foreach($floors as $floor)
                        <option value="{{ $floor->id }}" {{ old('floor_id') == $floor->id ? 'selected' : '' }}>
                            {{ $floor->floor_name }}
                        </option>
                        @endforeach
                    </select>
                    @error('floor_id')
                    <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Monthly Rent -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Monthly Rent <span class="text-red-500">*</span></label>
                    <input type="number" name="monthly_rent" step="0.01" min="0" value="{{ old('monthly_rent') }}" required 
                           placeholder="0.00"
                           class="w-full px-4 py-2 border @error('monthly_rent') border-red-500 @else border-gray-300 @enderror rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('monthly_rent')
                    <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
                    <select name="status" required class="w-full px-4 py-2 border @error('status') border-red-500 @else border-gray-300 @enderror rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="" disabled>Select status</option>
                        <option value="available" {{ old('status') == 'available' ? 'selected' : '' }}>Available</option>
                        <option value="occupied" {{ old('status') == 'occupied' ? 'selected' : '' }}>Occupied</option>
                        <option value="maintenance" {{ old('status') == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                    </select>
                    @error('status')
                    <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Supervisor -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Supervisor</label>
                    <select name="supervisor_id" class="w-full px-4 py-2 border @error('supervisor_id') border-red-500 @else border-gray-300 @enderror rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">No supervisor</option>
                        @foreach($supervisors as $supervisor)
                        <option value="{{ $supervisor->id }}" {{ old('supervisor_id') == $supervisor->id ? 'selected' : '' }}>
                            {{ $supervisor->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('supervisor_id')
                    <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="4" 
                          placeholder="Optional description of the apartment..."
                          class="w-full px-4 py-2 border @error('description') border-red-500 @else border-gray-300 @enderror rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('description') }}</textarea>
                @error('description')
                <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
                @enderror
            </div>

            <!-- Form Actions -->
            <div class="flex gap-3 pt-6 border-t border-gray-200">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200">
                    Create Apartment
                </button>
                <a href="{{ route('admin.apartments.index') }}" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200 text-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
