@extends('layouts.supervisor')

@section('content')
<div class="max-w-xl mx-auto px-4 py-6 space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-slate-800">Register New Tenant</h1>
            <p class="text-sm text-slate-400 mt-0.5">Add a tenant to one of your assigned apartments</p>
        </div>
        <a href="{{ route('supervisor.tenants.index') }}" class="inline-flex items-center gap-1.5 text-slate-500 hover:text-slate-700 text-sm font-medium py-2 px-4 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back
        </a>
    </div>

    @if(session('error'))
    <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-red-600 text-sm">{{ session('error') }}</div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-red-600 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <!-- Form Card -->
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <form action="{{ route('supervisor.tenants.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <!-- Photo Upload -->
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1.5">Photo (optional)</label>
                <label for="photo" class="flex items-center gap-3 w-full px-4 py-3 border border-dashed border-slate-200 rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition">
                    <svg class="w-5 h-5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span class="text-sm text-slate-500">Click to upload photo</span>
                    <input id="photo" type="file" name="photo" class="hidden" accept="image/*" onchange="previewPhoto(event)">
                </label>
                <div id="photoPreview" class="mt-2"></div>
                @error('photo')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-1 gap-4">

                <!-- Apartment -->
                <div>
                    <label for="apartment_id" class="block text-xs font-medium text-slate-500 mb-1.5">Apartment <span class="text-red-400">*</span></label>
                    @php
                        $selectedApt = null;
                        if(request()->has('apartment_id')) {
                            $selectedApt = $apartments->firstWhere('id', request('apartment_id'));
                        }
                    @endphp
                    @if($selectedApt)
                        <div class="flex items-center justify-between gap-4 p-3 border border-slate-200 rounded-lg bg-slate-50 text-sm">
                            <span class="font-medium text-slate-800">{{ $selectedApt->apartment_number }}</span>
                            <span class="text-slate-500">${{ number_format($selectedApt->monthly_rent, 2) }}/mo</span>
                        </div>
                        <input type="hidden" name="apartment_id" value="{{ $selectedApt->id }}">
                    @elseif($apartments->count() > 0)
                        <select id="apartment_id" name="apartment_id" required
                            class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-slate-400 focus:border-slate-400 bg-white {{ $errors->has('apartment_id') ? 'border-red-400' : '' }}">
                            <option value="">Select apartment</option>
                            @foreach($apartments as $apartment)
                                <option value="{{ $apartment->id }}" {{ old('apartment_id') == $apartment->id ? 'selected' : '' }}>
                                    {{ $apartment->apartment_number }} — ${{ number_format($apartment->monthly_rent, 2) }}/mo
                                </option>
                            @endforeach
                        </select>
                        @error('apartment_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    @else
                        <div class="bg-yellow-50 border border-yellow-200 px-4 py-3 rounded-lg text-yellow-800 text-sm">
                            No available apartments. All your assigned apartments are currently occupied or under maintenance.
                        </div>
                    @endif
                </div>

                <!-- Tenant Name -->
                <div>
                    <label for="name" class="block text-xs font-medium text-slate-500 mb-1.5">Full Name <span class="text-red-400">*</span></label>
                    <input type="text" id="name" name="name" required placeholder="Full name" value="{{ old('name') }}"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-slate-400 focus:border-slate-400 {{ $errors->has('name') ? 'border-red-400' : '' }}">
                    @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-xs font-medium text-slate-500 mb-1.5">Email <span class="text-red-400">*</span></label>
                    <input type="email" id="email" name="email" required placeholder="email@example.com" value="{{ old('email') }}"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-slate-400 focus:border-slate-400 {{ $errors->has('email') ? 'border-red-400' : '' }}">
                    @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <!-- Phone -->
                <div>
                    <label for="phone" class="block text-xs font-medium text-slate-500 mb-1.5">Phone <span class="text-red-400">*</span></label>
                    <input type="tel" id="phone" name="phone" required placeholder="Phone number" value="{{ old('phone') }}"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-slate-400 focus:border-slate-400 {{ $errors->has('phone') ? 'border-red-400' : '' }}">
                    @error('phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <!-- Move In Date -->
                <div>
                    <label for="move_in_date" class="block text-xs font-medium text-slate-500 mb-1.5">Move In Date <span class="text-red-400">*</span></label>
                    <input type="date" id="move_in_date" name="move_in_date" required value="{{ old('move_in_date', date('Y-m-d')) }}"
                        style="max-width:100%;box-sizing:border-box;"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-slate-400 focus:border-slate-400 bg-white {{ $errors->has('move_in_date') ? 'border-red-400' : '' }}">
                    @error('move_in_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <!-- Move Out Date -->
                <div>
                    <label for="move_out_date" class="block text-xs font-medium text-slate-500 mb-1.5">Move Out Date <span class="text-slate-300">(optional)</span></label>
                    <input type="date" id="move_out_date" name="move_out_date" value="{{ old('move_out_date') }}"
                        style="max-width:100%;box-sizing:border-box;"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-slate-400 focus:border-slate-400 bg-white {{ $errors->has('move_out_date') ? 'border-red-400' : '' }}">
                    @error('move_out_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <!-- Date of Birth -->
                <div>
                    <label for="date_of_birth" class="block text-xs font-medium text-slate-500 mb-1.5">Date of Birth <span class="text-slate-300">(optional)</span></label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}"
                        style="max-width:100%;box-sizing:border-box;"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-slate-400 focus:border-slate-400 bg-white {{ $errors->has('date_of_birth') ? 'border-red-400' : '' }}">
                    @error('date_of_birth')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-xs font-medium text-slate-500 mb-1.5">Status <span class="text-red-400">*</span></label>
                    <select id="status" name="status" required
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-slate-400 focus:border-slate-400 bg-white {{ $errors->has('status') ? 'border-red-400' : '' }}">
                        <option value="">Select status</option>
                        <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="pending" {{ old('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    </select>
                    @error('status')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <!-- Deposit -->
                <div>
                    <label for="deposit" class="block text-xs font-medium text-slate-500 mb-1.5">Deposit Amount <span class="text-slate-300">(optional)</span></label>
                    <input type="number" id="deposit" name="deposit" step="0.01" min="0" placeholder="0.00" value="{{ old('deposit', 0) }}"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-slate-400 focus:border-slate-400 {{ $errors->has('deposit') ? 'border-red-400' : '' }}">
                    @error('deposit')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <!-- Address -->
                <div>
                    <label for="address" class="block text-xs font-medium text-slate-500 mb-1.5">Address <span class="text-slate-300">(optional)</span></label>
                    <textarea id="address" name="address" rows="2" placeholder="Tenant address"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-slate-400 focus:border-slate-400 {{ $errors->has('address') ? 'border-red-400' : '' }}">{{ old('address') }}</textarea>
                    @error('address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-xs font-medium text-slate-500 mb-1.5">Notes <span class="text-slate-300">(optional)</span></label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Any additional notes"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-slate-400 focus:border-slate-400 {{ $errors->has('notes') ? 'border-red-400' : '' }}">{{ old('notes') }}</textarea>
                    @error('notes')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

            </div>

            <!-- Buttons -->
            <div class="flex gap-2 pt-2">
                <button type="submit" {{ $apartments->count() === 0 && !isset($selectedApt) ? 'disabled' : '' }}
                    class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-semibold rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Register Tenant
                </button>
                <a href="{{ route('supervisor.tenants.index') }}" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium rounded-lg transition text-center">
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
            preview.innerHTML = `<div class="relative inline-block">
                <img src="${e.target.result}" alt="Preview" class="h-20 w-20 object-cover rounded-lg border border-slate-200">
                <button type="button" onclick="clearPhoto()" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-red-600 transition">&times;</button>
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
