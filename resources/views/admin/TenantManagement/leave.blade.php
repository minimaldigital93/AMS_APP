@extends('layouts.admin')

@section('title', 'Process Tenant Leave')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <!-- Header -->
    <div>
        <a href="{{ route('admin.tenants.index') }}" class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm mb-3">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Back to Tenants
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Process Tenant Leave</h1>
    </div>

    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
    @endif

    <!-- Tenant Info Card -->
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <div class="flex items-center gap-4 mb-4">
            @if($tenant->photo_path)
                <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}" class="h-14 w-14 rounded-lg object-cover border">
            @else
                <div class="h-14 w-14 rounded-lg bg-blue-100 flex items-center justify-center border">
                    <span class="text-xl font-bold text-blue-600">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                </div>
            @endif
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ $tenant->name }}</h2>
                <p class="text-sm text-gray-500">Apt {{ $tenant->apartment?->apartment_number ?? 'N/A' }} &middot; {{ $tenant->phone ?? '' }}</p>
            </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs text-gray-500">Move In</p>
                <p class="font-semibold text-gray-800">{{ $tenant->move_in_date?->format('M d, Y') ?? 'N/A' }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs text-gray-500">Monthly Rent</p>
                <p class="font-semibold text-gray-800">${{ number_format($rental->rent_amount ?? 0, 2) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-xs text-gray-500">Deposit</p>
                <p class="font-semibold text-gray-800">${{ number_format($tenant->deposit ?? 0, 2) }}</p>
            </div>
            <div class="bg-blue-50 rounded-lg p-3">
                <p class="text-xs text-gray-500">Stay Days</p>
                <p class="font-semibold text-blue-700" id="stayDaysDisplay">0</p>
            </div>
        </div>
    </div>

    <!-- Leave Form -->
    <form action="{{ route('admin.tenants.processLeave', $tenant->id) }}" method="POST">
        @csrf

        <div class="bg-white rounded-xl shadow-sm border p-5 space-y-4">
            <h3 class="text-base font-semibold text-gray-900">Leave Details</h3>

            <!-- Leave Date -->
            <div>
                <label for="moveOutDate" class="block text-sm font-medium text-gray-700 mb-1">Move Out Date *</label>
                <input type="date" name="leave_date" id="moveOutDate" value="{{ old('leave_date', today()->format('Y-m-d')) }}" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
            </div>

            <!-- Utility Charges -->
            <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Utility Charges (optional)</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Electricity ($)</label>
                        <input type="number" name="electricity_charge" step="0.01" value="{{ old('electricity_charge', '0.00') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Water ($)</label>
                        <input type="number" name="water_charge" step="0.01" value="{{ old('water_charge', '0.00') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Internet ($)</label>
                        <input type="number" name="internet_charge" step="0.01" value="{{ old('internet_charge', '0.00') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Parking ($)</label>
                        <input type="number" name="parking_charge" step="0.01" value="{{ old('parking_charge', '0.00') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2" placeholder="Optional notes about the departure"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">{{ old('notes') }}</textarea>
            </div>
        </div>

        <!-- Settlement Summary -->
        <div class="bg-white rounded-xl shadow-sm border p-5 mt-4">
            <h3 class="text-base font-semibold text-gray-900 mb-3">Settlement Summary</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-600">Pro-rata Rent</span><span class="font-semibold" id="pro_rata_rent">$0.00</span></div>
                <div class="flex justify-between"><span class="text-gray-600">Electricity</span><span class="font-semibold" id="summary_electricity">$0.00</span></div>
                <div class="flex justify-between"><span class="text-gray-600">Water</span><span class="font-semibold" id="summary_water">$0.00</span></div>
                <div class="flex justify-between"><span class="text-gray-600">Internet</span><span class="font-semibold" id="summary_internet">$0.00</span></div>
                <div class="flex justify-between"><span class="text-gray-600">Parking</span><span class="font-semibold" id="summary_parking">$0.00</span></div>
                <div class="border-t pt-2 mt-2 flex justify-between"><span class="font-semibold text-gray-800">Total Due</span><span class="font-bold text-lg text-gray-900" id="total_due">$0.00</span></div>
                <div class="flex justify-between"><span class="text-gray-600">Deposit Applied</span><span class="font-semibold text-green-600" id="deposit_applied">$0.00</span></div>
                <div class="flex justify-between"><span class="text-gray-600">Balance Due</span><span class="font-semibold text-red-600" id="balance_due">$0.00</span></div>
                <div class="flex justify-between"><span class="text-gray-600">Refund</span><span class="font-semibold text-green-600" id="refund_amount">$0.00</span></div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 mt-4">
            <button type="submit" class="flex-1 px-5 py-2.5 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition text-sm">
                Process Leave & Archive
            </button>
            <a href="{{ route('admin.tenants.index') }}" class="flex-1 px-5 py-2.5 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition text-center text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
    const monthlyRent = {{ $rental->rent_amount ?? 0 }};
    const deposit = {{ $tenant->deposit ?? 0 }};
    const moveInDate = new Date('{{ $tenant->move_in_date?->format("Y-m-d") ?? now()->format("Y-m-d") }}');

    function updateCalculations() {
        const leaveDate = new Date(document.getElementById('moveOutDate').value);
        const electricity = parseFloat(document.querySelector('input[name="electricity_charge"]').value) || 0;
        const water = parseFloat(document.querySelector('input[name="water_charge"]').value) || 0;
        const internet = parseFloat(document.querySelector('input[name="internet_charge"]').value) || 0;
        const parking = parseFloat(document.querySelector('input[name="parking_charge"]').value) || 0;

        const stayDays = Math.ceil((leaveDate - moveInDate) / (1000 * 60 * 60 * 24)) + 1;
        const proRata = stayDays * (monthlyRent / 30);
        const totalDue = proRata + electricity + water + internet + parking;
        const depApplied = Math.min(deposit, totalDue);
        const balance = Math.max(0, totalDue - depApplied);
        const refund = deposit - depApplied;

        document.getElementById('stayDaysDisplay').textContent = stayDays + ' days';
        document.getElementById('pro_rata_rent').textContent = '$' + proRata.toFixed(2);
        document.getElementById('summary_electricity').textContent = '$' + electricity.toFixed(2);
        document.getElementById('summary_water').textContent = '$' + water.toFixed(2);
        document.getElementById('summary_internet').textContent = '$' + internet.toFixed(2);
        document.getElementById('summary_parking').textContent = '$' + parking.toFixed(2);
        document.getElementById('total_due').textContent = '$' + totalDue.toFixed(2);
        document.getElementById('deposit_applied').textContent = '$' + depApplied.toFixed(2);
        document.getElementById('balance_due').textContent = '$' + balance.toFixed(2);
        document.getElementById('refund_amount').textContent = '$' + refund.toFixed(2);
    }

    document.getElementById('moveOutDate').addEventListener('change', updateCalculations);
    document.querySelectorAll('input[type="number"]').forEach(el => el.addEventListener('input', updateCalculations));
    updateCalculations();
</script>
@endsection
