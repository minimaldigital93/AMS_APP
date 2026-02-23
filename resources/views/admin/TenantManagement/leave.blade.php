@extends('layouts.admin')

@section('title', 'Process Tenant Leave')

@section('content')
<div class="space-y-6">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <a href="/admin/tenants" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Tenants
            </a>
            <h1 class="text-3xl font-bold text-gray-900">Tenant Leave Processing</h1>
            <p class="mt-2 text-sm text-gray-600">Complete the leave process to archive the tenant</p>
        </div>

        <!-- Progress Steps -->
        <div class="bg-white rounded-lg shadow-md mb-8 p-6">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center h-10 w-10 rounded-full bg-blue-600 text-white">
                            <span class="text-lg font-semibold">1</span>
                        </div>
                        <p class="ml-4 text-sm font-medium text-gray-900">Tenant Information</p>
                    </div>
                </div>
                <div class="flex-auto border-t-2 border-gray-300 mx-4"></div>
                <div class="flex-1">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center h-10 w-10 rounded-full bg-gray-300 text-gray-600">
                            <span class="text-lg font-semibold">2</span>
                        </div>
                        <p class="ml-4 text-sm font-medium text-gray-600">Leave Details</p>
                    </div>
                </div>
                <div class="flex-auto border-t-2 border-gray-300 mx-4"></div>
                <div class="flex-1">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center h-10 w-10 rounded-full bg-gray-300 text-gray-600">
                            <span class="text-lg font-semibold">3</span>
                        </div>
                        <p class="ml-4 text-sm font-medium text-gray-600">Confirmation</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Form Section -->
            <div class="lg:col-span-2">
                <form action="{{ route('admin.tenants.processLeave', $tenant->id) }}" method="POST" class="space-y-6">
                    @csrf
                    <!-- Step 1: Tenant Information -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6">Tenant Information</h2>

                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tenant Name</label>
                                <input type="text" id="tenantName" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                                <input type="hidden" id="tenantId">
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input type="email" id="tenantEmail" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                                <input type="tel" id="tenantPhone" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Apartment</label>
                                <input type="text" id="apartmentNumber" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Move In Date</label>
                                <input type="date" id="moveInDate" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Status</label>
                                <div class="relative">
                                    <select id="currentStatus" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600 appearance-none cursor-not-allowed">
                                        <option>Active</option>
                                        <option>Pending</option>
                                        <option>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Leave Details -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6">Leave Details</h2>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Actual Leave Date *</label>
                                <input type="date" name="leave_date" id="moveOutDate" value="{{ old('leave_date', today()->format('Y-m-d')) }}" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <p class="mt-1 text-xs text-gray-500">The date the tenant is actually moving out</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Electricity Meter Reading (Units)</label>
                                <input type="number" name="electricity_reading" step="0.01" placeholder="e.g., 1250.50" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="{{ old('electricity_reading') }}">
                                <p class="mt-1 text-xs text-gray-500">Enter the final meter reading</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Water Meter Reading (Units)</label>
                                <input type="number" name="water_reading" step="0.01" placeholder="e.g., 450.30" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="{{ old('water_reading') }}">
                                <p class="mt-1 text-xs text-gray-500">Enter the final meter reading</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Internet Charge ($)</label>
                                <input type="number" name="internet_charge" step="0.01" placeholder="0.00" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="{{ old('internet_charge', '0.00') }}">
                                <p class="mt-1 text-xs text-gray-500">Pro-rata internet cost</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Parking Charge ($)</label>
                                <input type="number" name="parking_charge" step="0.01" placeholder="0.00" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="{{ old('parking_charge', '0.00') }}">
                                <p class="mt-1 text-xs text-gray-500">Pro-rata parking cost</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                                <textarea name="notes" rows="3" placeholder="Any additional information about the tenant's departure" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Settlement Summary -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6">Settlement Summary</h2>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Pro-rata Rent:</span>
                                <span class="text-lg font-semibold text-gray-900" id="pro_rata_rent">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Electricity:</span>
                                <span class="text-lg font-semibold text-gray-900" id="summary_electricity">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Water:</span>
                                <span class="text-lg font-semibold text-gray-900" id="summary_water">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Internet:</span>
                                <span class="text-lg font-semibold text-gray-900" id="summary_internet">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Parking:</span>
                                <span class="text-lg font-semibold text-gray-900" id="summary_parking">$0.00</span>
                            </div>

                            <div class="border-t border-gray-300 my-3 pt-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-700 font-semibold">Total Amount Due:</span>
                                    <span class="text-xl font-bold text-gray-900" id="total_due">$0.00</span>
                                </div>
                            </div>

                            <div class="border-t border-gray-300 my-3 pt-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-700">Deposit Applied:</span>
                                    <span class="text-lg font-semibold text-green-600" id="deposit_applied">$0.00</span>
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-gray-700">Balance Due:</span>
                                    <span class="text-lg font-semibold text-red-600" id="balance_due">$0.00</span>
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-gray-700">Refund Amount:</span>
                                    <span class="text-lg font-semibold text-green-600" id="refund_amount">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Process Tenant Leave & Archive
                        </button>
                        <a href="{{ route('admin.tenants.index') }}" class="flex-1 px-6 py-3 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- JavaScript for calculations -->
<script>
    // Populate tenant information
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('tenantName').value = '{{ $tenant->name ?? "N/A" }}';
        document.getElementById('tenantEmail').value = '{{ $tenant->email ?? "N/A" }}';
        document.getElementById('tenantPhone').value = '{{ $tenant->phone ?? "N/A" }}';
        document.getElementById('apartmentNumber').value = '{{ $tenant->apartment?->apartment_number ?? "N/A" }}';
        document.getElementById('moveInDate').value = '{{ $tenant->move_in_date ?? "" }}';
        document.getElementById('currentStatus').value = '{{ ucfirst($tenant->status) ?? "N/A" }}';
    });

    const monthlyRent = {{ $rental->rent_amount ?? 0 }};
    const deposit = {{ $tenant->deposit ?? 0 }};
    const moveInDate = new Date('{{ $tenant->move_in_date ?? date("Y-m-d") }}');

    function updateCalculations() {
        // Get values
        const leaveDateInput = document.getElementById('moveOutDate').value;
        const leaveDate = new Date(leaveDateInput);
        const electricityReading = parseFloat(document.querySelector('input[name="electricity_reading"]').value) || 0;
        const waterReading = parseFloat(document.querySelector('input[name="water_reading"]').value) || 0;
        const internetCharge = parseFloat(document.querySelector('input[name="internet_charge"]').value) || 0;
        const parkingCharge = parseFloat(document.querySelector('input[name="parking_charge"]').value) || 0;

        // Calculate stay days
        const stayDays = Math.ceil((leaveDate - moveInDate) / (1000 * 60 * 60 * 24)) + 1;

        // Calculate pro-rata rent (assuming 30 days per month)
        const dailyRate = monthlyRent / 30;
        const proRataRent = stayDays * dailyRate;

        // Calculate electricity charge (example rates)
        const electricityCharge = electricityReading * 2.5;

        // Calculate water charge (example rates)
        const waterCharge = waterReading * 1.8;

        // Calculate totals
        const totalDue = proRataRent + electricityCharge + waterCharge + internetCharge + parkingCharge;
        const depositApplied = Math.min(deposit, totalDue);
        const balanceDue = Math.max(0, totalDue - depositApplied);
        const refundAmount = deposit - depositApplied;

        // Update display
        document.getElementById('pro_rata_rent').textContent = '$' + proRataRent.toFixed(2);
        document.getElementById('summary_electricity').textContent = '$' + electricityCharge.toFixed(2);
        document.getElementById('summary_water').textContent = '$' + waterCharge.toFixed(2);
        document.getElementById('summary_internet').textContent = '$' + internetCharge.toFixed(2);
        document.getElementById('summary_parking').textContent = '$' + parkingCharge.toFixed(2);
        document.getElementById('total_due').textContent = '$' + totalDue.toFixed(2);
        document.getElementById('deposit_applied').textContent = '$' + depositApplied.toFixed(2);
        document.getElementById('balance_due').textContent = '$' + balanceDue.toFixed(2);
        document.getElementById('refund_amount').textContent = '$' + refundAmount.toFixed(2);
    }

    // Update calculations on input change
    document.getElementById('moveOutDate').addEventListener('change', updateCalculations);
    document.querySelector('input[name="electricity_reading"]').addEventListener('input', updateCalculations);
    document.querySelector('input[name="water_reading"]').addEventListener('input', updateCalculations);
    document.querySelector('input[name="internet_charge"]').addEventListener('input', updateCalculations);
    document.querySelector('input[name="parking_charge"]').addEventListener('input', updateCalculations);

    // Initial calculation
    updateCalculations();
</script>
@endsection
