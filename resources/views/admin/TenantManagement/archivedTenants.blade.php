@extends('layouts.admin')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Archived Tenants</h1>
                <p class="mt-2 text-sm text-gray-600">View and manage tenants who have left the property</p>
            </div>
            <a href="/admin/tenants" class="mt-4 sm:mt-0 inline-flex items-center px-6 py-2 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                View Active Tenants
            </a>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-lg shadow-md mb-6 p-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search by Name or Email</label>
                    <input type="text" id="searchInput" placeholder="Search archived tenants..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Apartment</label>
                    <select id="apartmentFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Apartments</option>
                        <!-- Options will be populated dynamically -->
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Archive Date</label>
                    <select id="archiveDateFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Dates</option>
                        <option value="month">Last 30 Days</option>
                        <option value="quarter">Last 3 Months</option>
                        <option value="year">Last Year</option>
                        <option value="older">Older</option>
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Total Archived Tenants</p>
                        <p id="totalArchivedTenants" class="text-2xl font-bold text-gray-900">0</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Recently Archived</p>
                        <p id="recentlyArchived" class="text-2xl font-bold text-gray-900">0</p>
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
                        <p class="text-gray-600 text-sm">Total Deposits Retained</p>
                        <p id="totalDeposits" class="text-2xl font-bold text-gray-900">$0</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Archived Tenants Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Tenant Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Apartment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Move In Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Move Out Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Archive Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Tenancy Duration</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="archivedTenantsTableBody" class="bg-white divide-y divide-gray-200">
                        @forelse($tenants as $tenant)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $tenant->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $tenant->apartment?->apartment_number ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $tenant->email }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $tenant->move_in_date->format('M d, Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    @if($tenant->leaves->last())
                                        {{ $tenant->leaves->last()->leave_date->format('M d, Y') }}
                                    @else
                                        {{ $tenant->move_out_date?->format('M d, Y') ?? 'N/A' }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $tenant->archived_at?->format('M d, Y') ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    @if($tenant->leaves->last() && $tenant->move_in_date)
                                        {{ $tenant->leaves->last()->stay_days }} days
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="viewTenantSettlement('{{ $tenant->id }}', '{{ addslashes($tenant->name) }}')" class="text-blue-600 hover:text-blue-900 transition" title="View Settlement">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-600">
                                    No archived tenants found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            @if($tenants->count() > 0)
            <div class="bg-white px-6 py-4 border-t border-gray-200">
                {{ $tenants->links() }}
            </div>
            @endif
        </div>
        <!-- Empty state shown only if no tenants -->
        @if($tenants->isEmpty())
        <div class="bg-white rounded-lg shadow-md p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No archived tenants found</h3>
                <p class="mt-1 text-sm text-gray-600">Tenants will appear here after their leave has been processed.</p>
            </div>
        @endif
    </div>
</div>

<!-- View Tenant Details Modal -->
<div id="viewTenantModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg max-w-3xl w-full max-h-screen overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-900">Archived Tenant Details</h2>
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
    let allArchivedTenants = [
        @foreach($tenants as $tenant)
            {
                id: {{ $tenant->id }},
                name: '{{ addslashes($tenant->name) }}',
                email: '{{ $tenant->email }}',
                apartment: '{{ $tenant->apartment?->apartment_number ?? "N/A" }}',
                move_in_date: '{{ $tenant->move_in_date }}',
                move_out_date: '{{ $tenant->leaves->last()?->leave_date ?? $tenant->move_out_date ?? "" }}',
                archived_at: '{{ $tenant->archived_at }}',
                deposit: {{ $tenant->deposit ?? 0 }},
                stay_days: {{ $tenant->leaves->last()?->stay_days ?? 0 }}
            },
        @endforeach
    ];

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadApartments();
        setupFilterListeners();
        updateStatistics();
    });

    // Setup filter listeners
    function setupFilterListeners() {
        document.getElementById('searchInput').addEventListener('input', filterTenants);
        document.getElementById('apartmentFilter').addEventListener('change', filterTenants);
        document.getElementById('archiveDateFilter').addEventListener('change', filterTenants);
    }

    // Load apartments for dropdown
    async function loadApartments() {
        try {
            const apartments = [...new Set(allArchivedTenants.map(t => t.apartment))];
            const select = document.getElementById('apartmentFilter');
            apartments.forEach(apt => {
                if (apt !== 'N/A') {
                    const option = document.createElement('option');
                    option.value = apt;
                    option.textContent = apt;
                    select.appendChild(option);
                }
            });
        } catch (error) {
            console.error('Error loading apartments:', error);
        }
    }

    // Update statistics
    function updateStatistics() {
        const total = allArchivedTenants.length;
        const now = new Date();
        const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate());
        const lastMonthCount = allArchivedTenants.filter(t => {
            const archivedDate = new Date(t.archived_at);
            return archivedDate >= lastMonth && archivedDate <= now;
        }).length;
        const totalDeposits = allArchivedTenants.reduce((sum, t) => sum + (parseFloat(t.deposit) || 0), 0);

        document.getElementById('totalArchivedTenants').textContent = total;
        document.getElementById('recentlyArchived').textContent = lastMonthCount;
        document.getElementById('totalDeposits').textContent = '$' + totalDeposits.toFixed(2);
    }

    // Display archived tenants in table
    function displayTenants(tenants) {
        const tbody = document.getElementById('archivedTenantsTableBody');
        const noTenantsMsg = document.getElementById('noTenantsMessage');

        if (tenants.length === 0) {
            tbody.innerHTML = '';
            noTenantsMsg.classList.remove('hidden');
            return;
        }

        noTenantsMsg.classList.add('hidden');
        tbody.innerHTML = tenants.map(tenant => {
            const moveInDate = new Date(tenant.move_in_date);
            const moveOutDate = tenant.move_out_date ? new Date(tenant.move_out_date) : new Date();
            const duration = calculateDuration(moveInDate, moveOutDate);

            return `
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center">
                                <span class="text-gray-600 font-semibold text-sm">${tenant.name.charAt(0).toUpperCase()}</span>
                            </div>
                            <div class="ml-4">
                                <p class="font-medium text-gray-900">${tenant.name}</p>
                                <span class="inline-block px-2 py-1 text-xs rounded-full bg-red-100 text-red-800 mt-1">Archived</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${tenant.apartment?.apartment_number || 'N/A'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${tenant.email}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${tenant.move_in_date}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${tenant.move_out_date || 'N/A'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${formatDate(tenant.archived_at)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${duration}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                        <button onclick="viewTenantDetails(${tenant.id})" class="text-blue-600 hover:text-blue-900">View Details</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Filter tenants based on search and filters
    function filterTenants() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const apartmentId = document.getElementById('apartmentFilter').value;
        const archiveDateFilter = document.getElementById('archiveDateFilter').value;

        const filtered = allArchivedTenants.filter(tenant => {
            const matchSearch = tenant.name.toLowerCase().includes(searchTerm) ||
                              tenant.email.toLowerCase().includes(searchTerm);
            const matchApartment = !apartmentId || tenant.apartment_id == apartmentId;
            const matchDate = matchesDateFilter(tenant.archived_at, archiveDateFilter);

            return matchSearch && matchApartment && matchDate;
        });

        displayTenants(filtered);
    }

    // Check if date matches selected filter
    function matchesDateFilter(archiveDate, filter) {
        if (!filter) return true;

        const archived = new Date(archiveDate);
        const today = new Date();
        const daysDiff = Math.floor((today - archived) / (1000 * 60 * 60 * 24));

        switch (filter) {
            case 'month':
                return daysDiff <= 30;
            case 'quarter':
                return daysDiff <= 90;
            case 'year':
                return daysDiff <= 365;
            case 'older':
                return daysDiff > 365;
            default:
                return true;
        }
    }

    // Reset filters
    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('apartmentFilter').value = '';
        document.getElementById('archiveDateFilter').value = '';
        displayTenants(allArchivedTenants);
    }

    // Update statistics
    function updateStatistics() {
        const total = allArchivedTenants.length;
        const lastMonth = allArchivedTenants.filter(t => {
            const archived = new Date(t.archived_at);
            const today = new Date();
            return (today - archived) / (1000 * 60 * 60 * 24) <= 30;
        }).length;

        const deposits = allArchivedTenants.reduce((sum, t) => sum + (parseFloat(t.deposit) || 0), 0);

        document.getElementById('totalArchivedTenants').textContent = total;
        document.getElementById('recentlyArchived').textContent = lastMonth;
        document.getElementById('totalDeposits').textContent = '$' + deposits.toFixed(2);
    }

    // Calculate tenancy duration between two dates
    function calculateDuration(moveInDate, moveOutDate) {
        const months = Math.floor((moveOutDate - moveInDate) / (1000 * 60 * 60 * 24) / 30.44);
        const days = Math.floor(((moveOutDate - moveInDate) / (1000 * 60 * 60 * 24)) % 30.44);

        let duration = '';
        if (months > 0) duration += months + ' month' + (months > 1 ? 's' : '') + ' ';
        if (days > 0) duration += days + ' day' + (days > 1 ? 's' : '');

        return duration || '0 days';
    }

    // Format date
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    // View tenant details
    async function viewTenantDetails(tenantId) {
        try {
            // API call removed - use Blade controller instead
            console.log('Fetching tenant:', tenantId);
            const tenant = await response.json();
            const t = tenant.data;

            const moveInDate = new Date(t.move_in_date);
            const moveOutDate = t.move_out_date ? new Date(t.move_out_date) : new Date();
            const duration = calculateDuration(moveInDate, moveOutDate);

            const content = `
                <div class="space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-600 mb-4">Personal Information</h3>
                            <div class="space-y-3">
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
                            <h3 class="text-sm font-medium text-gray-600 mb-4">Tenancy Information</h3>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-xs text-gray-500">Apartment</p>
                                    <p class="text-sm font-medium text-gray-900">${t.apartment?.apartment_number || 'N/A'}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Move In Date</p>
                                    <p class="text-sm font-medium text-gray-900">${t.move_in_date}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Move Out Date</p>
                                    <p class="text-sm font-medium text-gray-900">${t.move_out_date || 'N/A'}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Duration</p>
                                    <p class="text-sm font-medium text-gray-900">${duration}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-600 mb-4">Settlement Information</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Deposit Amount</p>
                                <p class="text-lg font-semibold text-gray-900">$${parseFloat(t.deposit || 0).toFixed(2)}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Status</p>
                                <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Archived</span>
                            </div>
                        </div>
                    </div>

                    ${t.notes ? `<div class="border-t border-gray-200 pt-4">
                        <p class="text-sm font-medium text-gray-600">Additional Notes</p>
                        <p class="mt-2 text-sm text-gray-700">${t.notes}</p>
                    </div>` : ''}

                    <div class="bg-blue-50 rounded-lg border border-blue-200 p-4">
                        <p class="text-xs text-gray-600">
                            <svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2z" clip-rule="evenodd"></path>
                            </svg>
                            This tenant has been archived and moved out of the property.
                        </p>
                    </div>

                    <button onclick="closeViewTenantModal()" class="w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
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
</script>
@endpush
