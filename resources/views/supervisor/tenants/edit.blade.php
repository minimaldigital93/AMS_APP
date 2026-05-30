@extends('layouts.supervisor')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">Edit Tenant</h1>
                <p class="text-slate-400 text-sm mt-1">{{ $tenant->name }}</p>
            </div>
            <a href="{{ route('supervisor.tenants.show', $tenant->id) }}" class="text-slate-400 hover:text-slate-600 text-sm font-medium py-2 px-4 rounded-lg border border-slate-200 hover:border-slate-300 transition">
                Back to Details
            </a>
        </div>

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
            <ul class="list-disc pl-4 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('supervisor.tenants.update', $tenant->id) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Photo Upload --}}
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Tenant Photo</h2>
                <div class="flex items-start gap-6">
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
                    @if($tenant->document_path)
                    <div class="flex-shrink-0">
                        <a href="{{ asset('storage/' . $tenant->document_path) }}" target="_blank" class="inline-flex items-center px-3 py-2 bg-gray-50 text-gray-700 rounded-lg border border-gray-200 hover:bg-gray-100">
                            <svg class="w-4 h-4 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z" /></svg>
                            View Document
                        </a>
                    </div>
                    @endif

                    <div class="flex-1">
                        <label for="photo" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                <p class="text-sm text-gray-700"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                                <p class="text-xs text-gray-500">PNG, JPG, GIF up to 5MB</p>
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

            {{-- Apartment Selection --}}
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Apartment Assignment</h2>
                <div>
                    <label for="apartment_id" class="block text-sm font-medium text-gray-700 mb-2">Apartment <span class="text-red-500">*</span></label>
                    <select name="apartment_id" id="apartment_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        @foreach($apartments as $apt)
                            <option value="{{ $apt->id }}" {{ old('apartment_id', $tenant->apartment_id) == $apt->id ? 'selected' : '' }}>
                                {{ $apt->apartment_number }} — ${{ number_format($apt->monthly_rent, 2) }}/mo
                                @if($apt->id == $tenant->apartment_id) (Current) @endif
                            </option>
                        @endforeach
                    </select>
                    @error('apartment_id')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Personal Information --}}
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Personal Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" id="name" name="name" required value="{{ old('name', $tenant->name) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-gray-400">(optional)</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email', $tenant->email) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        @error('email')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                        <input type="tel" id="phone" name="phone" required value="{{ old('phone', $tenant->phone) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        @error('phone')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $tenant->date_of_birth?->format('Y-m-d')) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent bg-white appearance-none h-10">
                        @error('date_of_birth')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea id="address" name="address" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">{{ old('address', $tenant->address) }}</textarea>
                    @error('address')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Lease Details --}}
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Lease Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="move_in_date" class="block text-sm font-medium text-gray-700 mb-1">Move In Date <span class="text-red-500">*</span></label>
                        <input type="date" id="move_in_date" name="move_in_date" required value="{{ old('move_in_date', $tenant->move_in_date?->format('Y-m-d')) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent bg-white appearance-none h-10">
                        @error('move_in_date')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="move_out_date" class="block text-sm font-medium text-gray-700 mb-1">Move Out Date</label>
                        <input type="date" id="move_out_date" name="move_out_date" value="{{ old('move_out_date', $tenant->move_out_date) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent bg-white appearance-none h-10">
                        @error('move_out_date')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                        <select id="status" name="status" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                            <option value="pending" {{ old('status', $tenant->status) === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="active" {{ old('status', $tenant->status) === 'active' ? 'selected' : '' }}>Active</option>
                        </select>
                        @error('status')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="deposit" class="block text-sm font-medium text-gray-700 mb-1">Deposit Amount</label>
                        <input type="number" id="deposit" name="deposit" step="0.01" min="0" value="{{ old('deposit', $tenant->deposit) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        @error('deposit')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">{{ old('notes', $tenant->notes) }}</textarea>
                    @error('notes')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Buttons --}}
            <div class="flex gap-3">
                <button type="submit" class="flex-1 px-6 py-2.5 bg-slate-800 text-white font-medium rounded-lg hover:bg-slate-700 transition text-center">
                    Update Tenant
                </button>
                <a href="{{ route('supervisor.tenants.show', $tenant->id) }}" class="flex-1 px-6 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition text-center">
                    Cancel
                </a>
            </div>
        </form>
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
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>`;
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
