@extends('layouts.admin')

@section('title', 'Edit Floor')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">Edit Floor</h1>
            <p class="text-slate-400 text-sm mt-1">Update floor information and manage apartment units</p>
        </div>
        <a href="{{ route('admin.floors.index') }}" class="text-slate-400 hover:text-slate-600 text-sm font-medium py-2 px-4 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            Back to Floors
        </a>
    </div>

    <!-- Flash / Errors -->
    @if ($message = Session::get('success'))
    <div class="bg-emerald-50 border border-emerald-100 rounded-lg px-4 py-3 text-emerald-700 text-sm flex items-center gap-2.5">
        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        {{ $message }}
    </div>
    @endif

    @if ($errors->any())
    <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-red-600 text-sm">
        <p class="font-medium mb-1">Please fix the following errors:</p>
        <ul class="list-disc list-inside space-y-0.5 text-red-500">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Form -->
    <form method="POST" action="{{ route('admin.floors.update', $floor) }}" class="space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="action" value="update_floor">

        <div class="bg-white rounded-xl border border-slate-100">
            <div class="p-6 space-y-5">
                <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide">Floor Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Floor Name <span class="text-red-400">*</span></label>
                        <input type="text" name="floor_name" required value="{{ old('floor_name', $floor->floor_name) }}" placeholder="e.g., 1st Floor, Ground Floor"
                               class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 transition @error('floor_name') border-red-300 ring-1 ring-red-200 @enderror">
                        @error('floor_name')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Description</label>
                        <input type="text" name="description" value="{{ old('description', $floor->description) }}" placeholder="Optional description..."
                               class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 transition @error('description') border-red-300 ring-1 ring-red-200 @enderror">
                        @error('description')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="px-6 py-4 border-t border-slate-100 flex gap-3">
                <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition">Update Floor</button>
                <a href="{{ route('admin.floors.index') }}" class="flex-1 text-center text-slate-500 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 text-sm font-medium py-2.5 px-5 rounded-lg transition">Cancel</a>
            </div>
        </div>
    </form>

    <!-- Existing Apartments -->
    @if($floor->apartments->count() > 0)
    <div class="bg-white rounded-xl border border-slate-100">
        <div class="p-6">
            <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">Existing Apartment Units ({{ $floor->apartments->count() }})</h3>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($floor->apartments as $apartment)
                <div class="bg-slate-50 rounded-xl border border-slate-100 p-4 flex items-center justify-between group hover:border-slate-200 transition">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center">
                            <span class="text-sm font-medium text-slate-700">{{ strtoupper(substr($apartment->apartment_number, 0, 1)) }}</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ $apartment->apartment_number }}</p>
                            <div class="flex gap-3 text-[12px] text-slate-400">
                                <span>${{ number_format($apartment->monthly_rent, 2) }}</span>
                                <span>·</span>
                                <span class="font-medium @if($apartment->status === 'available') text-emerald-600 @elseif($apartment->status === 'occupied') text-sky-600 @elseif($apartment->status === 'maintenance') text-amber-600 @endif">{{ ucfirst($apartment->status) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition">
                        <a href="{{ route('admin.apartments.edit', $apartment) }}" class="text-sky-600 hover:text-sky-700 p-2 rounded-lg bg-sky-50/20" title="Edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/></svg>
                        </a>
                        <form action="{{ route('admin.apartments.destroy', $apartment) }}" method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this apartment?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-600 p-2 rounded-lg bg-red-50/20" title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Add New Apartment Unit -->
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">Add New Apartment Unit</h3>
        <form method="POST" action="{{ route('admin.floors.update', $floor) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="action" value="add_apartment">

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Unit Number</label>
                    <input type="text" name="apartment_number" value="{{ old('apartment_number') }}" placeholder="e.g., 101" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 focus:outline-none focus:ring-2 focus:ring-slate-300">
                    @error('apartment_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Monthly Rent</label>
                    <input type="number" name="monthly_rent" step="0.01" min="0" value="{{ old('monthly_rent') }}" placeholder="0.00" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 focus:outline-none focus:ring-2 focus:ring-slate-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">Status</label>
                    <select name="apartment_status" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-slate-300">
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" name="action" value="add_apartment" class="w-full bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-4 rounded-lg transition">Add Unit</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

