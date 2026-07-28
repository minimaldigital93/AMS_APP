@extends('layouts.admin')

@section('title', __('messages.edit_apartment'))

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.edit_apartment') }}</h1>
        </div>
        <a href="{{ route('admin.floors.index') }}" title="{{ __('messages.back') }}" aria-label="{{ __('messages.back') }}" class="inline-flex items-center justify-center w-9 h-9 text-slate-400 hover:text-slate-600 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
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
    <div class="bg-white rounded-xl border border-slate-100">
        <form method="POST" action="{{ route('admin.apartments.update', $apartment->id) }}" id="updateRoomForm">
            @csrf
            @method('PUT')

            <div class="p-6 space-y-5">
                <h3 class="text-sm font-medium text-slate-500 uppercase tracking-wide">{{ __('messages.apartment_details') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Apartment Number -->
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.apartment_number') }} <span class="text-red-400">*</span></label>
                        <input type="text" name="apartment_number" value="{{ old('apartment_number', $apartment->apartment_number) }}" required
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
                            <option value="{{ $floor->id }}" {{ old('floor_id', $apartment->floor_id) == $floor->id ? 'selected' : '' }}>
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
                        <input type="number" name="monthly_rent" step="0.01" min="0" value="{{ old('monthly_rent', money_input($apartment->monthly_rent)) }}" required
                               placeholder="0.00"
                               class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('monthly_rent') border-red-300 ring-1 ring-red-200 @enderror">
                        @error('monthly_rent')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.status') }} <span class="text-red-400">*</span></label>
                        <select name="status" required class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('status') border-red-300 ring-1 ring-red-200 @enderror">
                            <option value="" disabled>{{ __('messages.select_status') }}</option>
                            <option value="available" {{ old('status', $apartment->status) == 'available' ? 'selected' : '' }}>{{ __('messages.available') }}</option>
                            <option value="occupied" {{ old('status', $apartment->status) == 'occupied' ? 'selected' : '' }}>{{ __('messages.occupied') }}</option>
                        </select>
                        @error('status')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Supervisor -->
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5 uppercase tracking-wide">{{ __('messages.supervisor') }}</label>
                        <select name="supervisor_id" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 text-slate-600 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('supervisor_id') border-red-300 ring-1 ring-red-200 @enderror">
                            <option value="">{{ __('messages.no_supervisor') }}</option>
                            @foreach($supervisors as $supervisor)
                            <option value="{{ $supervisor->id }}" {{ old('supervisor_id', $apartment->supervisor_id) == $supervisor->id ? 'selected' : '' }}>
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
                              class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-lg bg-slate-50/50 placeholder-slate-300 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition @error('description') border-red-300 ring-1 ring-red-200 @enderror">{{ old('description', $apartment->description) }}</textarea>
                    @error('description')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </form>

        {{-- Maintenance mode. Deliberately its own form, posting to its own
             route: a switch reads as an instant control, so it saves on click
             and flashes a confirmation instead of waiting for "Update Room".
             The hidden value is the OPPOSITE of the stored state, so the
             switch works with no JavaScript at all — its rendered position is
             always the server's truth. --}}
        @php $isOccupied = $apartment->isCurrentlyOccupied(); @endphp
        <div class="px-6 py-5 border-t border-slate-100">
            <form method="POST" action="{{ route('admin.apartments.maintenance', $apartment->id) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="under_maintenance" value="{{ $apartment->under_maintenance ? 0 : 1 }}">

                <div class="flex items-center justify-between gap-4">
                    <h3 class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <span class="material-icons text-[16px] leading-none text-slate-400">handyman</span>
                        {{ __('messages.maintenance_mode') }}
                    </h3>

                    <button type="submit"
                            role="switch"
                            aria-checked="{{ $apartment->under_maintenance ? 'true' : 'false' }}"
                            aria-label="{{ __('messages.maintenance_mode') }}"
                            @disabled($isOccupied)
                            class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2 disabled:opacity-40 disabled:cursor-not-allowed {{ $apartment->under_maintenance ? 'bg-slate-700' : 'bg-slate-300' }}">
                        <span aria-hidden="true"
                              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 {{ $apartment->under_maintenance ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="px-6 py-5 border-t border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-red-600">{{ __('messages.delete_apartment') }}</h3>
                    <p class="text-slate-400 text-xs mt-0.5">{{ __('messages.cannot_be_undone') }}</p>
                </div>
                <form method="POST" action="{{ route('admin.apartments.destroy', $apartment->id) }}" class="inline" data-confirm="{{ __('messages.delete_apt_confirm') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-500 hover:text-white hover:bg-red-500 text-sm font-medium py-2 px-4 rounded-lg border border-red-200 hover:border-red-500 transition">
                        Delete
                    </button>
                </form>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="px-6 py-4 border-t border-slate-100 flex gap-3">
            <button type="submit" form="updateRoomForm" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition">
                Update Room
            </button>
            <a href="{{ route('admin.floors.index') }}" class="flex-1 text-center text-slate-500 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 text-sm font-medium py-2.5 px-5 rounded-lg transition">
                Cancel
            </a>
        </div>
    </div>
</div>
@endsection
