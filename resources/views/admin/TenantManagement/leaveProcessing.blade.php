@extends('layouts.admin')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
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
                <form id="leaveProcessForm" onsubmit="submitLeaveProcess(event)" class="space-y-6">
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
                                <label class="block text-sm font-medium text-gray-700 mb-2">Move Out Date *</label>
                                <input type="date" name="move_out_date" id="moveOutDate" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <p class="mt-1 text-xs text-gray-500">The date the tenant is moving out</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Leaving</label>
                                <select name="leave_reason" id="leaveReason" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select a reason</option>
                                    <option value="relocation">Relocation</option>
                                    <option value="end_of_lease">End of Lease</option>
                                    <option value="early_termination">Early Termination</option>
                                    <option value="personal">Personal Reasons</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Forwarding Address</label>
                                <textarea name="forwarding_address" id="forwardingAddress" rows="3" placeholder="Enter the tenant's forwarding address" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                                <textarea name="leaving_notes" id="leavingNotes" rows="3" placeholder="Any additional information about the tenant's departure" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Settlement Details -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-6">Settlement Details</h2>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Deposit Amount (Read-only)</label>
                                <input type="number" id="depositAmount" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600" step="0.01">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Deductions/Charges Amount</label>
                                <input type="number" name="deductions" id="deductionsAmount" min="0" step="0.01" placeholder="0.00" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="0">
                                <p class="mt-1 text-xs text-gray-500">e.g., damages, cleaning, unpaid rent</p>
                            </div>

                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-700 font-medium">Refundable Amount</span>
                                    <span id="refundableAmount" class="text-2xl font-bold text-blue-600">$0.00</span>
                                </div>
                                <p class="mt-2 text-xs text-gray-600">Calculated as: Deposit - Deductions</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Refund Status</label>
                                <select name="refund_status" id="refundStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="pending">Pending</option>
                                    <option value="processed">Processed</option>
                                    <option value="withheld">Withheld</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Refund Date</label>
                                <input type="date" name="refund_date" id="refundDate" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Process Tenant Leave
                        </button>
                        <a href="/admin/tenants" class="flex-1 px-6 py-3 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Summary Section -->
            <div>
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6">Summary</h3>

                    <div class="space-y-4">
                        <div class="border-b border-gray-200 pb-4">
                            <p class="text-sm text-gray-600">Tenant Status</p>
                            <p id="summaryStatus" class="text-lg font-semibold text-gray-900 mt-1">-</p>
                        </div>

                        <div class="border-b border-gray-200 pb-4">
                            <p class="text-sm text-gray-600">Current Apartment</p>
                            <p id="summaryApartment" class="text-lg font-semibold text-gray-900 mt-1">-</p>
                        </div>

                        <div class="border-b border-gray-200 pb-4">
                            <p class="text-sm text-gray-600">Move In Date</p>
                            <p id="summaryMoveIn" class="text-lg font-semibold text-gray-900 mt-1">-</p>
                        </div>

                        <div class="border-b border-gray-200 pb-4">
                            <p class="text-sm text-gray-600">Tenancy Duration</p>
                            <p id="summaryDuration" class="text-lg font-semibold text-gray-900 mt-1">-</p>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm text-gray-600">Deposit</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1" id="summaryDeposit">$0.00</p>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <p class="text-sm text-gray-600">After Processing</p>
                            <p class="text-2xl font-bold text-yellow-600 mt-1" id="summaryRefund">$0.00</p>
                        </div>

                        <div class="text-xs text-gray-500 bg-blue-50 rounded-lg p-3">
                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2z" clip-rule="evenodd"></path>
                            </svg>
                            Once processed, the tenant will be moved to archived tenants and cannot be edited as active.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    let tenantData = null;

    // Get tenant ID from URL
    const tenantId = window.location.pathname.split('/').pop();

    document.addEventListener('DOMContentLoaded', function() {
        loadTenantData();
        setupCalculations();
    });

    // Load tenant data
    async function loadTenantData() {
        try {
            const response = await fetch(`/api/admin/tenants/${tenantId}`);
            const result = await response.json();
            tenantData = result.data;

            // Populate tenant information
            document.getElementById('tenantId').value = tenantData.id;
            document.getElementById('tenantName').value = tenantData.name;
            document.getElementById('tenantEmail').value = tenantData.email;
            document.getElementById('tenantPhone').value = tenantData.phone;
            document.getElementById('apartmentNumber').value = tenantData.apartment?.apartment_number || 'N/A';
            document.getElementById('moveInDate').value = tenantData.move_in_date;
            document.getElementById('currentStatus').value = tenantData.status.charAt(0).toUpperCase() + tenantData.status.slice(1);
            document.getElementById('depositAmount').value = parseFloat(tenantData.deposit || 0).toFixed(2);

            // Update summary
            document.getElementById('summaryStatus').textContent = tenantData.status.charAt(0).toUpperCase() + tenantData.status.slice(1);
            document.getElementById('summaryApartment').textContent = tenantData.apartment?.apartment_number || 'N/A';
            document.getElementById('summaryMoveIn').textContent = tenantData.move_in_date;
            document.getElementById('summaryDeposit').textContent = '$' + parseFloat(tenantData.deposit || 0).toFixed(2);

            // Calculate tenancy duration
            calculateDuration();
        } catch (error) {
            console.error('Error loading tenant data:', error);
            alert('Error loading tenant information');
        }
    }

    // Setup event listeners for calculations
    function setupCalculations() {
        document.getElementById('deductionsAmount').addEventListener('input', calculateRefund);
    }

    // Calculate tenancy duration
    function calculateDuration() {
        if (!tenantData) return;

        const moveIn = new Date(tenantData.move_in_date);
        const today = new Date();

        const months = (today.getFullYear() - moveIn.getFullYear()) * 12 + (today.getMonth() - moveIn.getMonth());
        const days = today.getDate() - moveIn.getDate();

        let duration = '';
        if (months > 0) duration += months + ' month' + (months > 1 ? 's' : '') + ' ';
        if (days > 0) duration += days + ' day' + (days > 1 ? 's' : '');

        document.getElementById('summaryDuration').textContent = duration || '0 days';
    }

    // Calculate refundable amount
    function calculateRefund() {
        const deposit = parseFloat(document.getElementById('depositAmount').value) || 0;
        const deductions = parseFloat(document.getElementById('deductionsAmount').value) || 0;
        const refund = Math.max(0, deposit - deductions);

        document.getElementById('refundableAmount').textContent = '$' + refund.toFixed(2);
        document.getElementById('summaryRefund').textContent = '$' + refund.toFixed(2);
    }

    // Set move out date default to today
    document.getElementById('moveOutDate').addEventListener('focus', function() {
        if (!this.value) {
            const today = new Date().toISOString().split('T')[0];
            this.value = today;
        }
    });

    // Submit form
    async function submitLeaveProcess(e) {
        e.preventDefault();

        if (!document.getElementById('moveOutDate').value) {
            alert('Please specify a move out date');
            return;
        }

        // Confirm action
        if (!confirm('Are you sure you want to process this tenant\'s leave? They will be moved to archived tenants.')) {
            return;
        }

        const formData = new FormData(document.getElementById('leaveProcessForm'));
        const data = {
            move_out_date: formData.get('move_out_date'),
            status: 'inactive',
            archived_at: new Date().toISOString(),
            notes: formData.get('leaving_notes') || '',
        };

        try {
            const response = await fetch(`/api/admin/tenants/${tenantId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(data)
            });

            if (response.ok) {
                alert('Tenant leave processed successfully! They have been moved to archived tenants.');
                window.location.href = '/admin/tenants/archived';
            } else {
                const error = await response.json();
                alert('Error: ' + (error.message || 'Failed to process leave'));
            }
        } catch (error) {
            console.error('Error processing leave:', error);
            alert('Error processing tenant leave');
        }
    }

    // Initialize refund calculation on load
    calculateRefund();
</script>
@endpush
