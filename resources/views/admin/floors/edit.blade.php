@extends('layouts.admin')

@section('title', 'Edit Floor')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.edit_floor') }}</h1>
        </div>
        <a href="{{ route('admin.floors.index') }}" title="{{ __('messages.back_to_floors') }}" class="inline-flex items-center justify-center text-slate-400 hover:text-slate-600 p-2 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
    </div>

    <!-- Errors -->
    @if ($errors->any())
    <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-red-600 text-sm">
        <p class="font-medium mb-1">{{ __('messages.please_fix_errors') }}</p>
        <ul class="list-disc list-inside space-y-0.5 text-red-500">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Form -->
    <form method="POST" action="{{ route('admin.floors.update', $floor) }}" id="updateFloorForm" class="space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="action" value="update_floor">

        <div class="bg-white rounded-xl border border-slate-100">
            <div class="p-6 space-y-5">
                <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide">{{ __('messages.floor_details') }}</h3>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.properties') }} <span class="text-red-400">*</span></label>
                    <select name="property_id" required
                            class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 focus:outline-none focus:ring-2 focus:ring-slate-300 transition @error('property_id') border-red-300 ring-1 ring-red-200 @enderror">
                        @foreach ($properties as $property)
                            <option value="{{ $property->id }}" @selected(old('property_id', $floor->property_id) == $property->id)>{{ $property->name }}</option>
                        @endforeach
                    </select>
                    @error('property_id')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.floor_name') }} <span class="text-red-400">*</span></label>
                    <input type="text" name="floor_name" required value="{{ old('floor_name', $floor->floor_name) }}" placeholder="{{ __('messages.eg_floor_1') }}"
                           class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 transition @error('floor_name') border-red-300 ring-1 ring-red-200 @enderror">
                    @error('floor_name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
    </form>

    <!-- Existing Apartments -->
    @if($floor->apartments->count() > 0)
    <div class="bg-white rounded-xl border border-slate-100">
        <div class="p-6">
            <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">Existing Room Units ({{ $floor->apartments->count() }})</h3>
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
                                <span>{{ money($apartment->monthly_rent) }}</span>
                                <span>·</span>
                                <span class="font-medium @if($apartment->status === 'available') text-emerald-600 @elseif($apartment->status === 'occupied') text-sky-600 @endif">{{ status_label($apartment->status) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition">
                        <a href="{{ route('admin.apartments.edit', $apartment) }}" class="text-sky-600 hover:text-sky-700 p-2 rounded-lg bg-sky-50/20" title="{{ __('messages.edit') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125"/></svg>
                        </a>
                        <form action="{{ route('admin.apartments.destroy', $apartment) }}" method="POST" style="display:inline" data-confirm="Are you sure you want to delete this apartment?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-600 p-2 rounded-lg bg-red-50/20" title="{{ __('messages.delete') }}">
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
        <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">{{ __('messages.add_new_apt_unit') }}</h3>
        <form method="POST" action="{{ route('admin.floors.update', $floor) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="action" value="add_apartment">

            <div class="grid grid-cols-1 md:grid-cols-[1fr_1fr_auto] gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.unit_number') }}</label>
                    <input type="text" name="apartment_number" value="{{ old('apartment_number') }}" placeholder="{{ __('messages.eg_unit') }}" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 focus:outline-none focus:ring-2 focus:ring-slate-300">
                    @error('apartment_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.monthly_rent') }}</label>
                    <input type="number" name="monthly_rent" step="0.01" min="0" value="{{ old('monthly_rent') }}" placeholder="0.00" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 focus:outline-none focus:ring-2 focus:ring-slate-300">
                </div>
                <div class="flex items-end">
                    <button type="submit" name="action" value="add_apartment" title="{{ __('messages.add_unit') }}" class="inline-flex items-center justify-center bg-slate-800 hover:bg-slate-700 text-white p-2.5 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Danger Zone -->
    <div class="bg-white rounded-xl border border-red-100 p-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-medium text-red-600">{{ __('messages.delete_floor') }}</h3>
                <p class="text-slate-400 text-xs mt-0.5">{{ __('messages.cannot_be_undone') }}</p>
            </div>
            <form method="POST" action="{{ route('admin.floors.destroy', $floor) }}" class="inline" data-confirm="{{ __('messages.confirm_delete_floor', ['name' => $floor->floor_name]) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-red-500 hover:text-white hover:bg-red-500 text-sm font-medium py-2 px-4 rounded-lg border border-red-200 hover:border-red-500 transition">
                    {{ __('messages.delete_floor') }}
                </button>
            </form>
        </div>
    </div>

    <!-- Footer Actions -->
    <div class="flex gap-3">
        <a href="{{ route('admin.floors.index') }}" class="flex-1 text-center text-slate-500 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 text-sm font-medium py-2.5 px-5 rounded-lg transition">{{ __('messages.cancel') }}</a>
        <button type="submit" form="updateFloorForm" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition">{{ __('messages.update_floor') }}</button>
    </div>
</div>
@endsection

