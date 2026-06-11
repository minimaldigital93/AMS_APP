@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.edit_tenant') }}</h1>
            </div>
            <a href="{{ route('admin.tenants.show', $tenant->id) }}"
                title="{{ __('messages.back_to_details') }}"
                aria-label="{{ __('messages.back_to_details') }}"
                class="h-10 w-10 inline-flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:text-slate-600 hover:border-slate-300 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <form action="{{ route('admin.tenants.update', $tenant->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Photo Upload -->
                <div>
                            <label for="photo" class="block text-sm font-medium text-slate-500 mb-2">{{ __('messages.tenant_photo') }}</label>
                    <div class="flex items-start gap-6">
                        <!-- Current Photo -->
                        @if($tenant->photo_path && !str_ends_with($tenant->photo_path, '.pdf'))
                            <div class="flex-shrink-0">
                                <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}" class="h-32 w-32 object-cover rounded-lg shadow-md">
                            </div>
                        @elseif($tenant->photo_path && str_ends_with($tenant->photo_path, '.pdf'))
                            <div class="flex-shrink-0">
                                <a href="{{ asset('storage/' . $tenant->photo_path) }}" target="_blank" class="h-32 w-32 rounded-lg bg-red-50 flex items-center justify-center text-red-600 border border-red-200">
                                    <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                                </a>
                            </div>
                        @endif
                        <!-- Upload Area -->
                        <div class="flex-1">
                            <label for="photo" class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-200 border-dashed rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition text-slate-600">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    <p class="text-sm text-gray-700"><span class="font-semibold">{{ __('messages.click_to_upload') }}</span> {{ __('messages.or_drag_drop') }}</p>
                                    <p class="text-xs text-gray-500">{{ __('messages.png_jpg_gif') }}</p>
                                </div>
                                <input id="photo" type="file" name="photo" class="hidden" accept="image/*" onchange="previewPhoto(event)">
                            </label>
                        </div>
                    </div>
                    <div id="photoPreview" class="mt-4"></div>
                    @error('photo')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Apartment -->
                    <div>
                        <label for="apartment_id" class="block text-sm font-medium text-slate-500 mb-2">{{ __('messages.apartment') }} *</label>
                            <select id="apartment_id" name="apartment_id" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-200 focus:border-transparent text-slate-600 {{ $errors->has('apartment_id') ? 'border-red-500' : '' }}">
                            <option value="">{{ __('messages.select_apartment') }}</option>
                            @foreach($apartments as $apartment)
                                <option value="{{ $apartment->id }}" {{ old('apartment_id', $tenant->apartment_id) == $apartment->id ? 'selected' : '' }}>
                                    {{ $apartment->apartment_number }}@if($apartment->id == $tenant->apartment_id) ({{ __('messages.current') }})@endif
                                </option>
                            @endforeach
                        </select>
                        @error('apartment_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Tenant Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-500 mb-2">{{ __('messages.tenant_name') }} *</label>
                        <input type="text" id="name" name="name" required placeholder="{{ __('messages.full_name') }}" value="{{ old('name', $tenant->name) }}" class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-200 focus:border-transparent text-slate-600 {{ $errors->has('name') ? 'border-red-500' : '' }}">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.phone') }} *</label>
                        <input type="tel" id="phone" name="phone" required placeholder="+1 (555) 000-0000" value="{{ old('phone', $tenant->phone) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $errors->has('phone') ? 'border-red-500' : '' }}">
                        @error('phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Move In Date -->
                    <div>
                        <label for="move_in_date" class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.move_in_date') }} *</label>
                        <input type="date" id="move_in_date" name="move_in_date" required value="{{ old('move_in_date', $tenant->move_in_date?->format('Y-m-d')) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white appearance-none h-10 {{ $errors->has('move_in_date') ? 'border-red-500' : '' }}">
                        @error('move_in_date')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Date of Birth -->
                    <div>
                        <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.date_of_birth') }}</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $tenant->date_of_birth?->format('Y-m-d')) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white appearance-none h-10 {{ $errors->has('date_of_birth') ? 'border-red-500' : '' }}">
                        @error('date_of_birth')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-500 mb-2">{{ __('messages.status') }} *</label>
                            <select id="status" name="status" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-200 focus:border-transparent text-slate-600 {{ $errors->has('status') ? 'border-red-500' : '' }}">
                            <option value="">{{ __('messages.select_status') }}</option>
                            <option value="pending" {{ old('status', $tenant->status) === 'pending' ? 'selected' : '' }}>{{ __('messages.pending') }}</option>
                            <option value="active" {{ old('status', $tenant->status) === 'active' ? 'selected' : '' }}>{{ __('messages.active') }}</option>
                            <option value="inactive" {{ old('status', $tenant->status) === 'inactive' ? 'selected' : '' }}>{{ __('messages.inactive') }}</option>
                        </select>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Deposit -->
                    <div>
                        <label for="deposit" class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.deposit_amount') }}</label>
                        <input type="number" id="deposit" name="deposit" step="0.01" min="0" placeholder="0.00" value="{{ old('deposit', $tenant->deposit) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $errors->has('deposit') ? 'border-red-500' : '' }}">
                        @error('deposit')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Address -->
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.address') }}</label>
                    <textarea id="address" name="address" rows="2" placeholder="{{ __('messages.enter_tenant_address') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $errors->has('address') ? 'border-red-500' : '' }}">{{ old('address', $tenant->address) }}</textarea>
                    @error('address')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">{{ __('messages.notes') }}</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="{{ __('messages.any_additional_notes') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $errors->has('notes') ? 'border-red-500' : '' }}">{{ old('notes', $tenant->notes) }}</textarea>
                    @error('notes')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Attached Document -->
                @if($tenant->document_path)
                    <div>
                        <label class="block text-sm font-medium text-slate-500 mb-2">{{ __('messages.attached_document') }}</label>
                        <a href="{{ asset('storage/' . $tenant->document_path) }}" target="_blank"
                            title="{{ __('messages.view_document') }}"
                            aria-label="{{ __('messages.view_document') }}"
                            class="h-12 w-12 rounded-lg bg-red-50 hover:bg-red-100 border border-red-100 hover:border-red-200 inline-flex items-center justify-center transition">
                            <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                        </a>
                    </div>
                @endif

                <!-- Buttons -->
                <div class="flex gap-3 pt-6">
                    <a href="{{ route('admin.tenants.show', $tenant->id) }}" class="flex-1 px-6 py-2 border border-slate-200 text-base font-medium rounded-md text-slate-700 hover:bg-slate-50 transition text-center">{{ __('messages.cancel') }}</a>
                    <button type="submit" class="flex-1 px-6 py-2 border border-transparent text-base font-medium rounded-md text-white bg-slate-800 hover:bg-slate-700 transition">{{ __('messages.update_tenant') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function previewPhoto(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('photoPreview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div class="relative inline-block">
                    <img src="${e.target.result}" alt="Preview" class="max-w-xs h-auto rounded-lg shadow-md">
                    <button type="button" onclick="clearPhoto()" class="absolute top-0 right-0 bg-red-500 text-white rounded-full p-2 hover:bg-red-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    }
}

function clearPhoto() {
    document.getElementById('photo').value = '';
    document.getElementById('photoPreview').innerHTML = '';
}
</script>
@endsection
