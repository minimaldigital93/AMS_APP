@extends('layouts.admin')

@section('title', 'Add Apartment')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.add_new_apartment') }}</h1>
        </div>
        <a href="{{ route('admin.apartments.index') }}" class="text-slate-400 hover:text-slate-600 text-sm font-medium py-2 px-4 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            Back to Apartments
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
    <div class="bg-white rounded-xl border border-slate-100">
        <form method="POST" action="{{ route('admin.apartments.store') }}">
            @csrf

            <div class="p-6 space-y-5">
                <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide">{{ __('messages.apartment_details') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Apartment Number -->
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.apartment_number') }} <span class="text-red-400">*</span></label>
                        <input type="text" name="apartment_number" value="{{ old('apartment_number') }}" required
                               placeholder="{{ __('messages.eg_apt_number') }}"
                               class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('apartment_number') border-red-300 ring-1 ring-red-200 @enderror">
                        @error('apartment_number')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Floor -->
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.floor') }} <span class="text-red-400">*</span></label>
                        <select name="floor_id" required class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('floor_id') border-red-300 ring-1 ring-red-200 @enderror">
                            <option value="">{{ __('messages.select_a_floor') }}</option>
                            @foreach($floors as $floor)
                            <option value="{{ $floor->id }}" {{ old('floor_id') == $floor->id ? 'selected' : '' }}>
                                {{ $floor->floor_name }}
                            </option>
                            @endforeach
                        </select>
                        @error('floor_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Monthly Rent -->
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.monthly_rent') }} <span class="text-red-400">*</span></label>
                        <input type="number" name="monthly_rent" step="0.01" min="0" value="{{ old('monthly_rent') }}" required
                               placeholder="0.00"
                               class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('monthly_rent') border-red-300 ring-1 ring-red-200 @enderror">
                        @error('monthly_rent')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Supervisor -->
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.supervisor') }}</label>
                        <select name="supervisor_id" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('supervisor_id') border-red-300 ring-1 ring-red-200 @enderror">
                            <option value="">{{ __('messages.no_supervisor') }}</option>
                            @foreach($supervisors as $supervisor)
                            <option value="{{ $supervisor->id }}" {{ old('supervisor_id') == $supervisor->id ? 'selected' : '' }}>
                                {{ $supervisor->name }}
                            </option>
                            @endforeach
                        </select>
                        @error('supervisor_id')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.description') }}</label>
                    <textarea name="description" rows="3"
                              placeholder="{{ __('messages.optional_description') }}"
                              class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('description') border-red-300 ring-1 ring-red-200 @enderror">{{ old('description') }}</textarea>
                    @error('description')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="px-6 py-4 border-t border-slate-100 flex gap-3">
                <button type="submit" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition">
                    Create Apartment
                </button>
                <a href="{{ route('admin.apartments.index') }}" class="flex-1 text-center text-slate-500 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 text-sm font-medium py-2.5 px-5 rounded-lg transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
