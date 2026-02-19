@extends('layouts.admin')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Active Tenants Management</h1>
                <p class="mt-2 text-sm text-gray-600">Manage and monitor all active tenants in your property</p>
            </div>
            <button onclick="openAddTenantModal()" class="mt-4 sm:mt-0 inline-flex items-center px-6 py-2 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add New Tenant
            </button>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-lg shadow-md mb-6 p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search by Name or Email</label>
                    <input type="text" id="searchInput" placeholder="Search tenants..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Apartment</label>
                    <select id="apartmentFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Apartments</option>
                        <!-- Options will be populated dynamically -->
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Status</label>
                    <select id="statusFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button onclick="resetFilters()" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium">
                        Reset Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics Section -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Total Active Tenants</p>
                        <p id="totalTenants" class="text-2xl font-bold text-gray-900">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Verified Tenants</p>
                        <p id="verifiedTenants" class="text-2xl font-bold text-gray-900">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Pending Tenants</p>
                        <p id="pendingTenants" class="text-2xl font-bold text-gray-900">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Total Deposits</p>
                        <p id="totalDeposits" class="text-2xl font-bold text-gray-900">$0</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tenants Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Tenant Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Apartment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Move In Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Deposit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tenantsTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Tenants will be populated here via JavaScript -->
                    </tbody>
                </table>
            </div>
            <div id="noTenantsMessage" class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No tenants found</h3>
                <p class="mt-1 text-sm text-gray-600">Get started by adding a new tenant.</p>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Tenant Modal -->
<div id="tenantModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg max-w-2xl w-full max-h-96 overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
            <h2 id="modalTitle" class="text-lg font-semibold text-gray-900">Add New Tenant</h2>
            <button onclick="closeTenantModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="tenantForm" onsubmit="saveTenant(event)" class="p-6 space-y-4">
            <input type="hidden" id="tenantId">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Apartment *</label>
                    <select id="apartmentId" name="apartment_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select Apartment</option>
                        <!-- Options will be populated dynamically -->
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tenant Name *</label>
                    <input type="text" id="tenantName" name="name" required placeholder="Full Name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" id="tenantEmail" name="email" required placeholder="email@example.com" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                    <input type="tel" id="tenantPhone" name="phone" required placeholder="+1 (555) 000-0000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Move In Date *</label>
                    <input type="date" id="moveInDate" name="move_in_date" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Move Out Date</label>
                    <input type="date" id="moveOutDate" name="move_out_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" id="dateOfBirth" name="date_of_birth" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                    <select id="tenantStatus" name="status" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deposit Amount</label>
                    <input type="number" id="deposit" name="deposit" step="0.01" min="0" placeholder="0.00" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                <textarea id="address" name="address" rows="2" placeholder="Enter tenant address" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea id="notes" name="notes" rows="2" placeholder="Any additional notes" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 px-6 py-2 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition">
                    Save Tenant
                </button>
                <button type="button" onclick="closeTenantModal()" class="flex-1 px-6 py-2 border border-gray-300 text-base font-medium rounded-md text-gray-700 hover:bg-gray-50 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Tenant Details Modal -->
<div id="viewTenantModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg max-w-3xl w-full max-h-96 overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-900">Tenant Details</h2>
            <button onclick="closeViewTenantModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div id="tenantDetailsContent" class="p-6">
            <!-- Details will be populated here -->
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    let allTenants = [];

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadApartments();
        loadTenants();
        setupFilterListeners();
        
        // Reload tenants when user returns to the page (tab focus)
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                console.log('Page regained focus, reloading tenants');
                loadTenants();
            }
        });
    });

    // Setup filter listeners
    function setupFilterListeners() {
        document.getElementById('searchInput').addEventListener('input', filterTenants);
        document.getElementById('apartmentFilter').addEventListener('change', filterTenants);
        document.getElementById('statusFilter').addEventListener('change', filterTenants);
    }

    // Load apartments for dropdown
    async function loadApartments() {
        try {
            const response = await fetch('/api/admin/apartments?per_page=1000');
            const data = await response.json();
            const apartments = data.data;

            const apartmentSelects = document.querySelectorAll('[name="apartment_id"], #apartmentFilter');
            apartmentSelects.forEach(select => {
                apartments.forEach(apt => {
                    if (select.id === 'apartmentId' || select === document.getElementById('apartmentId')) {
                        const option = document.createElement('option');
                        option.value = apt.id;
                        option.textContent = apt.apartment_number;
                        select.appendChild(option);
                    } else if (select === document.getElementById('apartmentFilter')) {
                        const option = document.createElement('option');
                        option.value = apt.id;
                        option.textContent = apt.apartment_number;
                        select.appendChild(option);
                    }
                });
            });
        } catch (error) {
            console.error('Error loading apartments:', error);
        }
    }

    // Load tenants from API
    async function loadTenants() {
        try {
            // API now returns only active and pending tenants by default
            const response = await fetch('/api/admin/tenants?per_page=1000');
            const data = await response.json();
            let tenants = data.data || data;
            
            // Ensure tenants is an array
            if (!Array.isArray(tenants)) {
                console.warn('API returned unexpected format:', tenants);
                tenants = Object.values(tenants || {});
            }
            
            console.log('✓ Active tenants from API (count: ' + tenants.length + '):', tenants);
            
            allTenants = tenants;
            
            displayTenants(allTenants);
            updateStatistics();
        } catch (error) {
            console.error('✗ Error loading tenants:', error);
            alert('Error loading tenants. Check console for details: ' + error.message);
        }
    }
    
    // Refresh tenants every 5 seconds to show newly assigned tenants (only if no modal is open)
    setInterval(() => {
        const tenantModal = document.getElementById('tenantModal');
        const viewModal = document.getElementById('viewTenantModal');
        if (tenantModal.classList.contains('hidden') && viewModal.classList.contains('hidden')) {
            loadTenants();
        }
    }, 5000);

    // Display tenants in table
    function displayTenants(tenants) {
        const tbody = document.getElementById('tenantsTableBody');
        const noTenantsMsg = document.getElementById('noTenantsMessage');

        if (tenants.length === 0) {
            tbody.innerHTML = '';
            noTenantsMsg.classList.remove('hidden');
            return;
        }

        noTenantsMsg.classList.add('hidden');
        tbody.innerHTML = tenants.map(tenant => `
            <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                            <span class="text-blue-600 font-semibold text-sm">${tenant.name.charAt(0).toUpperCase()}</span>
                        </div>
                        <div class="ml-4">
                            <p class="font-medium text-gray-900">${tenant.name}</p>
                            <p class="text-sm text-gray-500">${tenant.user_id ? 'Linked' : 'Not Linked'}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${tenant.apartment?.apartment_number || 'N/A'}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${tenant.email}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${tenant.phone}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${tenant.move_in_date}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                        ${tenant.status === 'active' ? 'bg-green-100 text-green-800' :
                          tenant.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                          'bg-red-100 text-red-800'}">
                        ${tenant.status.charAt(0).toUpperCase() + tenant.status.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">$${parseFloat(tenant.deposit || 0).toFixed(2)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                    <button onclick="viewTenantDetails(${tenant.id})" class="text-blue-600 hover:text-blue-900">View</button>
                    <button onclick="editTenant(${tenant.id})" class="text-green-600 hover:text-green-900">Edit</button>
                    <button onclick="processTenantLeave(${tenant.id})" class="text-orange-600 hover:text-orange-900">Process Leave</button>
                </td>
            </tr>
        `).join('');
    }

    // Filter tenants based on search and filters
    function filterTenants() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const apartmentId = document.getElementById('apartmentFilter').value;
        const status = document.getElementById('statusFilter').value;

        const filtered = allTenants.filter(tenant => {
            const matchSearch = tenant.name.toLowerCase().includes(searchTerm) ||
                              tenant.email.toLowerCase().includes(searchTerm);
            const matchApartment = !apartmentId || tenant.apartment_id == apartmentId;
            const matchStatus = !status || tenant.status === status;

            return matchSearch && matchApartment && matchStatus;
        });

        displayTenants(filtered);
    }

    // Reset filters
    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('apartmentFilter').value = '';
        document.getElementById('statusFilter').value = '';
        displayTenants(allTenants);
    }

    // Update statistics
    function updateStatistics() {
        const total = allTenants.length;
        const verified = allTenants.filter(t => t.status === 'active').length;
        const pending = allTenants.filter(t => t.status === 'pending').length;
        const deposits = allTenants.reduce((sum, t) => sum + (parseFloat(t.deposit) || 0), 0);

        document.getElementById('totalTenants').textContent = total;
        document.getElementById('verifiedTenants').textContent = verified;
        document.getElementById('pendingTenants').textContent = pending;
        document.getElementById('totalDeposits').textContent = '$' + deposits.toFixed(2);
    }

    // Open add tenant modal
    function openAddTenantModal() {
        document.getElementById('tenantId').value = '';
        document.getElementById('tenantForm').reset();
        document.getElementById('modalTitle').textContent = 'Add New Tenant';
        document.getElementById('tenantModal').classList.remove('hidden');
    }

    // Close tenant modal
    function closeTenantModal() {
        document.getElementById('tenantModal').classList.add('hidden');
    }

    // Save tenant
    async function saveTenant(e) {
        e.preventDefault();
        const tenantId = document.getElementById('tenantId').value;
        const formData = new FormData(document.getElementById('tenantForm'));
        const data = Object.fromEntries(formData);

        try {
            const url = tenantId ? `/api/admin/tenants/${tenantId}` : '/api/admin/tenants';
            const method = tenantId ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(data)
            });

            if (response.ok) {
                alert(tenantId ? 'Tenant updated successfully!' : 'Tenant created successfully!');
                closeTenantModal();
                loadTenants();
            } else {
                const error = await response.json();
                alert('Error: ' + (error.message || 'Failed to save tenant'));
            }
        } catch (error) {
            console.error('Error saving tenant:', error);
            alert('Error saving tenant');
        }
    }

    // Edit tenant
    async function editTenant(tenantId) {
        try {
            const response = await fetch(`/api/admin/tenants/${tenantId}`);
            const tenant = await response.json();

            document.getElementById('tenantId').value = tenant.data.id;
            document.getElementById('apartmentId').value = tenant.data.apartment_id;
            document.getElementById('tenantName').value = tenant.data.name;
            document.getElementById('tenantEmail').value = tenant.data.email;
            document.getElementById('tenantPhone').value = tenant.data.phone;
            document.getElementById('moveInDate').value = tenant.data.move_in_date;
            document.getElementById('moveOutDate').value = tenant.data.move_out_date || '';
            document.getElementById('dateOfBirth').value = tenant.data.date_of_birth || '';
            document.getElementById('tenantStatus').value = tenant.data.status;
            document.getElementById('deposit').value = tenant.data.deposit || '';
            document.getElementById('address').value = tenant.data.address || '';
            document.getElementById('notes').value = tenant.data.notes || '';

            document.getElementById('modalTitle').textContent = 'Edit Tenant';
            document.getElementById('tenantModal').classList.remove('hidden');
        } catch (error) {
            console.error('Error loading tenant:', error);
            alert('Error loading tenant details');
        }
    }

    // View tenant details
    async function viewTenantDetails(tenantId) {
        try {
            const response = await fetch(`/api/admin/tenants/${tenantId}`);
            const tenant = await response.json();
            const t = tenant.data;

            const content = `
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-600">Personal Information</h3>
                        <div class="mt-4 space-y-3">
                            <div>
                                <p class="text-xs text-gray-500">Full Name</p>
                                <p class="text-sm font-medium text-gray-900">${t.name}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Email</p>
                                <p class="text-sm font-medium text-gray-900">${t.email}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Phone</p>
                                <p class="text-sm font-medium text-gray-900">${t.phone}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Date of Birth</p>
                                <p class="text-sm font-medium text-gray-900">${t.date_of_birth || 'Not provided'}</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-600">Tenancy Information</h3>
                        <div class="mt-4 space-y-3">
                            <div>
                                <p class="text-xs text-gray-500">Apartment</p>
                                <p class="text-sm font-medium text-gray-900">${t.apartment?.apartment_number || 'N/A'}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Move In Date</p>
                                <p class="text-sm font-medium text-gray-900">${t.move_in_date}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Status</p>
                                <p class="text-sm font-medium text-gray-900">${t.status}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Deposit</p>
                                <p class="text-sm font-medium text-gray-900">$${parseFloat(t.deposit || 0).toFixed(2)}</p>
                            </div>
                        </div>
                    </div>
                </div>
                ${t.notes ? `<div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-sm font-medium text-gray-600">Notes</p>
                    <p class="mt-2 text-sm text-gray-700">${t.notes}</p>
                </div>` : ''}
                <div class="mt-6 flex gap-3">
                    <button onclick="editTenant(${t.id})" class="flex-1 px-4 py-2 border border-blue-300 text-blue-600 rounded-lg hover:bg-blue-50 transition font-medium">
                        Edit Tenant
                    </button>
                    <button onclick="closeViewTenantModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                        Close
                    </button>
                </div>
            `;

            document.getElementById('tenantDetailsContent').innerHTML = content;
            document.getElementById('viewTenantModal').classList.remove('hidden');
        } catch (error) {
            console.error('Error loading tenant details:', error);
            alert('Error loading tenant details');
        }
    }

    // Close view tenant modal
    function closeViewTenantModal() {
        document.getElementById('viewTenantModal').classList.add('hidden');
    }

    // Process tenant leave
    async function processTenantLeave(tenantId) {
        if (confirm('Are you sure you want to initiate leave processing for this tenant? They will be moved to archived tenants.')) {
            window.location.href = `/admin/tenants/leave/${tenantId}`;
        }
    }
</script>
@endpush
