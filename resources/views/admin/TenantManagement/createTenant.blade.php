@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <a href="{{ route('admin.tenants.index') }}" class="text-blue-600 hover:text-blue-900 flex items-center mb-4">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Tenants
            </a>
            <h1 class="text-3xl font-bold text-gray-900">Create New Tenant</h1>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <form action="{{ route('admin.tenants.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <!-- Photo Upload -->
                <div>
                    <label for="photo" class="block text-sm font-medium text-slate-500 mb-2">Tenant Photo</label>
                    <div class="flex items-center justify-center w-full">
                        <label for="photo" class="flex flex-col items-center justify-center w-full h-32 border-2 border-slate-200 border-dashed rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition text-slate-600">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                <p class="text-sm text-gray-700"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF up to 5MB</p>
                            </div>
                            <input id="photo" type="file" name="photo" class="hidden" accept="image/*" onchange="previewPhoto(event)">
                        </label>
                    </div>
                    <div id="photoPreview" class="mt-4"></div>
                    @error('photo')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Apartment -->
                    <div>
                        <label for="apartment_id" class="block text-sm font-medium text-slate-500 mb-2">Apartment *</label>
                        <select id="apartment_id" name="apartment_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $errors->has('apartment_id') ? 'border-red-500' : '' }}">
                            <option value="">Select an Apartment</option>
                            @foreach($apartments as $apartment)
                                <option value="{{ $apartment->id }}" {{ old('apartment_id') == $apartment->id ? 'selected' : '' }}>
                                    {{ $apartment->apartment_number }}
                                </option>
                            @endforeach
                        </select>
                        @error('apartment_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Tenant Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-500 mb-2">Tenant Name *</label>
                        <input type="text" id="name" name="name" required placeholder="Full Name" value="{{ old('name') }}" class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-200 focus:border-transparent text-slate-600 {{ $errors->has('name') ? 'border-red-500' : '' }}">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-500 mb-2">Email *</label>
                        <input type="email" id="email" name="email" required placeholder="email@example.com" value="{{ old('email') }}" class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-200 focus:border-transparent text-slate-600 {{ $errors->has('email') ? 'border-red-500' : '' }}">
                        @error('email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                        <input type="tel" id="phone" name="phone" required placeholder="+1 (555) 000-0000" value="{{ old('phone') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $errors->has('phone') ? 'border-red-500' : '' }}">
                        @error('phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Move In Date -->
                    <div>
                        <label for="move_in_date" class="block text-sm font-medium text-gray-700 mb-2">Move In Date *</label>
                        <input type="date" id="move_in_date" name="move_in_date" required value="{{ old('move_in_date') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $errors->has('move_in_date') ? 'border-red-500' : '' }}">
                        @error('move_in_date')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Move Out Date -->
                    <div>
                        <label for="move_out_date" class="block text-sm font-medium text-gray-700 mb-2">Move Out Date</label>
                        <input type="date" id="move_out_date" name="move_out_date" value="{{ old('move_out_date') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $errors->has('move_out_date') ? 'border-red-500' : '' }}">
                        @error('move_out_date')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Date of Birth -->
                    <div>
                        <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $errors->has('date_of_birth') ? 'border-red-500' : '' }}">
                        @error('date_of_birth')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-500 mb-2">Status *</label>
                            <select id="status" name="status" required class="w-full px-4 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-200 focus:border-transparent text-slate-600 {{ $errors->has('status') ? 'border-red-500' : '' }}">
                            <option value="">Select Status</option>
                            <option value="pending" {{ old('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Deposit -->
                    <div>
                        <label for="deposit" class="block text-sm font-medium text-gray-700 mb-2">Deposit Amount</label>
                        <input type="number" id="deposit" name="deposit" step="0.01" min="0" placeholder="0.00" value="{{ old('deposit') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $errors->has('deposit') ? 'border-red-500' : '' }}">
                        @error('deposit')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Address -->
                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <textarea id="address" name="address" rows="2" placeholder="Enter tenant address" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $errors->has('address') ? 'border-red-500' : '' }}">{{ old('address') }}</textarea>
                    @error('address')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Any additional notes" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent {{ $errors->has('notes') ? 'border-red-500' : '' }}">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 pt-6">
                    <button type="submit" class="flex-1 px-6 py-2 border border-transparent text-base font-medium rounded-md text-white bg-slate-800 hover:bg-slate-700 transition">
                        Create Tenant
                    </button>
                    <a href="{{ route('admin.tenants.index') }}" class="flex-1 px-6 py-2 border border-slate-200 text-base font-medium rounded-md text-slate-700 hover:bg-slate-50 transition text-center">
                        Cancel
                    </a>
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
