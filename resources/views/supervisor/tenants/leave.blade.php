@extends('layouts.supervisor')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="flex items-center gap-4 mb-8">
            <a href="{{ route('supervisor.tenants.index') }}" class="text-emerald-600 hover:text-emerald-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Process Tenant Leave</h1>
                <p class="text-sm text-gray-500 mt-1">Settlement for {{ $tenant->name }}</p>
            </div>
        </div>

        @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 text-sm">{{ session('error') }}</div>
        @endif

        @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
            <ul class="list-disc pl-4 text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Tenant Summary --}}
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Tenant Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Tenant</label>
                    <p class="text-sm font-semibold text-gray-900">{{ $tenant->name }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Apartment</label>
                    <p class="text-sm text-gray-700">{{ $tenant->apartment?->apartment_number ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Monthly Rent</label>
                    <p class="text-sm text-gray-700">${{ number_format($rental->rent_amount ?? 0, 2) }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Move In Date</label>
                    <p class="text-sm text-gray-700">{{ $tenant->move_in_date ? $tenant->move_in_date->format('M d, Y') : 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Deposit</label>
                    <p class="text-sm text-gray-700">${{ number_format($tenant->deposit ?? 0, 2) }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Days Stayed</label>
                    <p class="text-sm text-gray-700">{{ $tenant->move_in_date ? $tenant->move_in_date->diffInDays(now()) : 'N/A' }} days</p>
                </div>
            </div>
        </div>

        {{-- Leave Form --}}
        <form method="POST" action="{{ route('supervisor.tenants.processLeave', $tenant) }}" x-data="leaveForm()" class="space-y-6">
            @csrf

            {{-- Leave Date --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Leave Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="leave_date" class="block text-sm font-medium text-gray-700 mb-2">Leave Date <span class="text-red-500">*</span></label>
                        <input type="date" name="leave_date" id="leave_date" value="{{ old('leave_date', date('Y-m-d')) }}" required
                            x-model="leaveDate" @change="calculateProRata()"
                            min="{{ $tenant->move_in_date ? $tenant->move_in_date->format('Y-m-d') : '' }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent bg-white appearance-none h-10">
                    </div>
                    <div class="flex items-end">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2 w-full">
                            <span class="text-xs text-blue-600 font-medium">Estimated Pro-Rata Rent</span>
                            <p class="text-lg font-bold text-blue-800">$<span x-text="proRata.toFixed(2)">0.00</span></p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Utility Charges --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Utility Charges</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="electricity_charge" class="block text-sm font-medium text-gray-700 mb-2">Electricity</label>
                        <input type="number" name="electricity_charge" id="electricity_charge" value="{{ old('electricity_charge', 0) }}" min="0" step="0.01"
                            x-model.number="electricity" @input="calculateTotal()"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="water_charge" class="block text-sm font-medium text-gray-700 mb-2">Water</label>
                        <input type="number" name="water_charge" id="water_charge" value="{{ old('water_charge', 0) }}" min="0" step="0.01"
                            x-model.number="water" @input="calculateTotal()"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="internet_charge" class="block text-sm font-medium text-gray-700 mb-2">Internet</label>
                        <input type="number" name="internet_charge" id="internet_charge" value="{{ old('internet_charge', 0) }}" min="0" step="0.01"
                            x-model.number="internet" @input="calculateTotal()"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="parking_charge" class="block text-sm font-medium text-gray-700 mb-2">Parking</label>
                        <input type="number" name="parking_charge" id="parking_charge" value="{{ old('parking_charge', 0) }}" min="0" step="0.01"
                            x-model.number="parking" @input="calculateTotal()"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                </div>
            </div>

            {{-- Settlement Summary --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Settlement Summary</h2>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Pro-Rata Rent</span>
                        <span class="font-medium text-gray-900">$<span x-text="proRata.toFixed(2)">0.00</span></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Utility Charges</span>
                        <span class="font-medium text-gray-900">$<span x-text="utilityTotal.toFixed(2)">0.00</span></span>
                    </div>
                    <div class="border-t pt-2 flex justify-between text-sm">
                        <span class="text-gray-600 font-medium">Total Due</span>
                        <span class="font-bold text-gray-900">$<span x-text="totalDue.toFixed(2)">0.00</span></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Deposit Applied</span>
                        <span class="font-medium text-green-600">-${{ number_format($tenant->deposit ?? 0, 2) }}</span>
                    </div>
                    <div class="border-t pt-2 flex justify-between">
                        <span class="text-gray-900 font-bold">Balance</span>
                        <span class="font-bold text-lg" :class="balance >= 0 ? 'text-red-600' : 'text-green-600'" x-text="(balance >= 0 ? '$' : '-$') + Math.abs(balance).toFixed(2)">$0.00</span>
                    </div>
                    <p class="text-xs text-gray-500" x-show="balance < 0">* Negative balance = refund to tenant</p>
                </div>
            </div>

            {{-- Notes --}}
            <div class="bg-white rounded-lg shadow-md p-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                <textarea name="notes" id="notes" rows="3" placeholder="Any additional notes about this departure..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">{{ old('notes') }}</textarea>
            </div>

            {{-- Submit --}}
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('supervisor.tenants.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-sm font-medium">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm font-medium"
                    onclick="return confirm('Are you sure you want to process this tenant leave? This action cannot be undone.')">
                    Process Leave
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function leaveForm() {
    return {
        leaveDate: '{{ old('leave_date', date('Y-m-d')) }}',
        monthlyRent: {{ $rental->rent_amount ?? 0 }},
        deposit: {{ $tenant->deposit ?? 0 }},
        moveInDate: '{{ $tenant->move_in_date ? $tenant->move_in_date->format('Y-m-d') : date('Y-m-d') }}',
        electricity: {{ old('electricity_charge', 0) }},
        water: {{ old('water_charge', 0) }},
        internet: {{ old('internet_charge', 0) }},
        parking: {{ old('parking_charge', 0) }},
        proRata: 0,
        utilityTotal: 0,
        totalDue: 0,
        balance: 0,

        init() {
            this.calculateProRata();
            this.calculateTotal();
        },

        calculateProRata() {
            if (!this.leaveDate || !this.moveInDate) return;
            const leave = new Date(this.leaveDate);
            const daysInMonth = new Date(leave.getFullYear(), leave.getMonth() + 1, 0).getDate();
            const dayOfMonth = leave.getDate();
            this.proRata = (this.monthlyRent / daysInMonth) * dayOfMonth;
            this.calculateTotal();
        },

        calculateTotal() {
            this.utilityTotal = (this.electricity || 0) + (this.water || 0) + (this.internet || 0) + (this.parking || 0);
            this.totalDue = this.proRata + this.utilityTotal;
            this.balance = this.totalDue - this.deposit;
        }
    }
}
</script>
@endsection
