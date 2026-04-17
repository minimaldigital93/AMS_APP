@extends('layouts.admin')

@section('title', 'Add New Floor')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">Add New Floor</h1>
            <p class="text-slate-400 text-sm mt-1">Create a floor and assign apartment units</p>
        </div>
        <a href="{{ route('admin.floors.index') }}" class="text-slate-400 hover:text-slate-600 text-sm font-medium py-2 px-4 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            Back to Floors
        </a>
    </div>

    <!-- Form -->
    <div class="bg-white rounded-xl border border-slate-100">
        <form method="POST" action="{{ route('admin.floors.store') }}">
            @csrf

            <!-- Floor Information -->
            <div class="p-6 space-y-5">
                <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide">Floor Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Floor Name <span class="text-red-400">*</span></label>
                        <input type="text" name="floor_name" required
                               value="{{ old('floor_name', session('floor_data')['floor_name'] ?? '') }}"
                               placeholder="e.g., 1st Floor, Ground Floor"
                               class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('floor_name') border-red-300 ring-1 ring-red-200 @enderror">
                        @error('floor_name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Description</label>
                        <input type="text" name="description"
                               value="{{ old('description', session('floor_data')['description'] ?? '') }}"
                               placeholder="Optional description..."
                               class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('description') border-red-300 ring-1 ring-red-200 @enderror">
                        @error('description')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Divider -->
            <div class="border-t border-slate-100"></div>

            <!-- Apartments Section -->
            <div class="p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide">Apartment Units</h3>
                    @if($tempApartments && count($tempApartments) > 0)
                        <span class="text-xs font-medium text-slate-400">{{ count($tempApartments) }} unit(s) added</span>
                    @endif
                </div>

                <!-- Add Unit Row -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Unit Number</label>
                        <input type="text" name="apartment_number"
                               placeholder="e.g., 101"
                               value="{{ old('apartment_number') }}"
                               class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('apartment_number') border-red-300 ring-1 ring-red-200 @enderror">
                        @error('apartment_number')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Monthly Rent</label>
                        <input type="number" name="monthly_rent" step="0.01" min="0"
                               placeholder="0.00"
                               value="{{ old('monthly_rent') }}"
                               class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Status</label>
                        <select name="apartment_status" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                            <option value="">-- Optional --</option>
                            <option value="available" {{ old('apartment_status') === 'available' ? 'selected' : '' }}>Available</option>
                            <option value="occupied" {{ old('apartment_status') === 'occupied' ? 'selected' : '' }}>Occupied</option>
                            <option value="maintenance" {{ old('apartment_status') === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" name="action" value="add_apartment"
                                class="w-full inline-flex items-center justify-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium py-2 px-4 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Unit
                        </button>
                    </div>
                </div>

                <!-- Added Units List -->
                @if($tempApartments && count($tempApartments) > 0)
                <div class="rounded-xl border border-slate-100 overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50/80">
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">Unit #</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">Monthly Rent</th>
                                <th class="px-4 py-2.5 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($tempApartments as $index => $apt)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-4 py-2.5">
                                    <span class="text-sm font-medium text-slate-700">{{ $apt['apartment_number'] }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="text-sm text-slate-600">${{ number_format($apt['monthly_rent'] ?? 0, 2) }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium
                                        @if($apt['status'] === 'available') text-emerald-600
                                        @elseif($apt['status'] === 'occupied') text-sky-600
                                        @elseif($apt['status'] === 'maintenance') text-amber-600
                                        @else text-slate-500
                                        @endif">
                                        <span class="w-1.5 h-1.5 rounded-full
                                            @if($apt['status'] === 'available') bg-emerald-400
                                            @elseif($apt['status'] === 'occupied') bg-sky-400
                                            @elseif($apt['status'] === 'maintenance') bg-amber-400
                                            @else bg-slate-300
                                            @endif"></span>
                                        {{ ucfirst($apt['status']) }}
                                    </span>
                                </td>
                            </tr>
                            <input type="hidden" name="apartments[{{ $index }}][apartment_number]" value="{{ $apt['apartment_number'] }}">
                            <input type="hidden" name="apartments[{{ $index }}][monthly_rent]" value="{{ $apt['monthly_rent'] ?? 0 }}">
                            <input type="hidden" name="apartments[{{ $index }}][status]" value="{{ $apt['status'] }}">
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-10">
                    <p class="text-slate-400 text-sm">No units added yet</p>
                </div>
                @endif
            </div>

            <!-- Footer Actions -->
            <div class="px-6 py-4 border-t border-slate-100 flex gap-3">
                <button type="submit" name="action" value="create_floor" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition">
                    Create Floor
                </button>
                <a href="{{ route('admin.floors.index') }}" class="flex-1 text-center text-slate-500 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 text-sm font-medium py-2.5 px-5 rounded-lg transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
