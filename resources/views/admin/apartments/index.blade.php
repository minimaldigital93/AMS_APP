@extends('layouts.admin')

@section('title', 'Apartment Management')

@section('content')
<div class="space-y-6">
    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Apartment Management</h1>
        </div>
        <a href="{{ route('admin.apartments.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 flex items-center gap-2">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
            </svg>
            Add Apartment
        </a>
    </div>

    <!-- Flash Messages -->
    @if ($message = Session::get('success'))
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700 flex items-center gap-3">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <span>{{ $message }}</span>
    </div>
    @endif

    @if ($message = Session::get('error'))
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700 flex items-center gap-3">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <span>{{ $message }}</span>
    </div>
    @endif

    <!-- Apartments Grouped by Floor -->
    @forelse($apartmentsByFloor as $floorId => $apartmentsInFloor)
        @php
            $floor = $apartmentsInFloor->first()->floor;
        @endphp
        
        <!-- Floor Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <!-- Floor Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.5 1.5H3a1.5 1.5 0 00-1.5 1.5v12a1.5 1.5 0 001.5 1.5h14a1.5 1.5 0 001.5-1.5V6.5a1.5 1.5 0 00-1.5-1.5h-7v-3a1 1 0 10-2 0v3z" />
                    </svg>
                    {{ $floor->floor_name }} ({{ $apartmentsInFloor->count() }} apartments)
                </h2>
            </div>

            <!-- Apartments Grid -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50">
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Apartment</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Monthly Rent</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Tenant</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Stay Duration</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Supervisor</th>
                            <th class="px-6 py-3 text-right text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($apartmentsInFloor as $apartment)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                <div class="flex items-center gap-2">
                                    @php
                                        $tenant = $apartment->tenants()->whereNull('deleted_at')->latest()->first();
                                        $tenantStatus = $tenant ? $tenant->status : null;
                                    @endphp
                                    <span class="w-2 h-2 rounded-full {{ 
                                        $tenantStatus === 'active' ? 'bg-blue-500' : 
                                        ($tenantStatus === 'pending' ? 'bg-orange-500' : 'bg-green-500') 
                                    }}"></span>
                                    {{ $apartment->apartment_number }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                ${{ number_format($apartment->monthly_rent, 2) }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($apartment->status === 'available')
                                    <span class="inline-flex items-center bg-green-100 text-green-800 text-xs px-2.5 py-0.5 rounded-full font-medium">
                                        Available
                                    </span>
                                @elseif($apartment->status === 'occupied')
                                    <span class="inline-flex items-center bg-blue-100 text-blue-800 text-xs px-2.5 py-0.5 rounded-full font-medium">
                                        Occupied
                                    </span>
                                @else
                                    <span class="inline-flex items-center bg-yellow-100 text-yellow-800 text-xs px-2.5 py-0.5 rounded-full font-medium">
                                        Maintenance
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                @php
                                    $tenant = $apartment->tenants()->whereNull('deleted_at')->latest()->first();
                                @endphp
                                @if($tenant)
                                    <div class="flex items-center gap-3">
                                        @if($tenant->photo_path && !str_ends_with($tenant->photo_path, '.pdf'))
                                            <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}" class="h-8 w-8 rounded-full object-cover border border-gray-300">
                                        @elseif($tenant->photo_path && str_ends_with($tenant->photo_path, '.pdf'))
                                            <a href="{{ asset('storage/' . $tenant->photo_path) }}" target="_blank" class="h-8 w-8 rounded-full bg-red-100 flex items-center justify-center text-xs font-semibold text-red-600 border border-red-300" title="View PDF">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                                            </a>
                                        @else
                                            <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-xs font-semibold text-blue-600">
                                                {{ strtoupper(substr($tenant->name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $tenant->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $tenant->email }}</div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-gray-400">No tenant assigned</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @php
                                    $tenant = $apartment->tenants()->whereNull('deleted_at')->latest()->first();
                                    $hasLease = false;
                                    $hasMonthlyPeriod = false;

                                    if($tenant && $tenant->move_in_date) {
                                        $moveInDate = \Carbon\Carbon::parse($tenant->move_in_date);
                                        $today = now();
                                        $stayDays = $moveInDate->diffInDays($today);
                                        $stayMonths = $moveInDate->diffInMonths($today);

                                        // Lease progress (only if move_out_date exists)
                                        if ($tenant->move_out_date) {
                                            $moveOutDate = \Carbon\Carbon::parse($tenant->move_out_date);
                                            $totalDays = $moveInDate->diffInDays($moveOutDate);
                                            $daysPassed = $moveInDate->diffInDays($today);
                                            $percentagePassed = ($totalDays > 0) ? min(100, max(0, ($daysPassed / $totalDays) * 100)) : 0;
                                            $daysRemaining = max(0, $today->diffInDays($moveOutDate, false));
                                            $hasLease = true;
                                        }

                                        // Monthly rent period progress (always available with move_in_date)
                                        $billingDay = $moveInDate->day;

                                        // Calculate current billing period start
                                        if ($today->day >= $billingDay) {
                                            $periodStart = $today->copy()->day($billingDay)->startOfDay();
                                        } else {
                                            $prevMonth = $today->copy()->subMonth();
                                            $periodStart = $prevMonth->day(min($billingDay, $prevMonth->daysInMonth))->startOfDay();
                                        }

                                        // Calculate billing period end
                                        $periodEnd = $periodStart->copy()->addMonth()->subDay()->endOfDay();

                                        // Clamp within lease if move_out_date exists
                                        if ($periodStart->lt($moveInDate)) $periodStart = $moveInDate->copy();
                                        if ($tenant->move_out_date && $periodEnd->gt($moveOutDate)) $periodEnd = $moveOutDate->copy()->endOfDay();

                                        $periodTotalDays = max(1, $periodStart->diffInDays($periodEnd));
                                        $periodDaysPassed = max(0, min($periodTotalDays, $periodStart->diffInDays($today)));
                                        $periodPercent = min(100, max(0, round(($periodDaysPassed / $periodTotalDays) * 100, 1)));
                                        $periodDaysLeft = max(0, (int)$today->diffInDays($periodEnd, false));

                                        // Color based on progress
                                        if ($periodPercent >= 80) {
                                            $monthBarColor = 'from-red-400 to-red-600';
                                            $monthTextColor = 'text-red-600';
                                        } elseif ($periodPercent >= 50) {
                                            $monthBarColor = 'from-yellow-400 to-orange-500';
                                            $monthTextColor = 'text-orange-600';
                                        } else {
                                            $monthBarColor = 'from-blue-400 to-blue-600';
                                            $monthTextColor = 'text-blue-600';
                                        }

                                        // Calculate billing/payment progress for the current billing period
                                        $rental = $apartment->rentals()->where('tenant_id', $tenant->id)->latest()->first();
                                        $expectedAmount = $rental->rent_amount ?? $apartment->monthly_rent ?? 0;
                                        $paidAmount = 0.0;
                                        $paymentPercent = 0;
                                        $paymentBarColor = 'from-gray-300 to-gray-400';

                                        if ($expectedAmount > 0 && $rental) {
                                            try {
                                                $paidAmount = (float) $rental->payments()
                                                    ->whereNotNull('paid_at')
                                                    ->whereBetween('due_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                                                    ->sum('amount');
                                                $paymentPercent = min(100, max(0, round(($paidAmount / $expectedAmount) * 100, 1)));

                                                if ($paymentPercent >= 80) {
                                                    $paymentBarColor = 'from-green-400 to-green-600';
                                                } elseif ($paymentPercent >= 50) {
                                                    $paymentBarColor = 'from-yellow-400 to-orange-500';
                                                } else {
                                                    $paymentBarColor = 'from-red-400 to-red-600';
                                                }
                                            } catch (\Exception $e) {
                                                $paidAmount = 0.0;
                                                $paymentPercent = 0;
                                                $paymentBarColor = 'from-gray-300 to-gray-400';
                                            }
                                        }

                                        $hasMonthlyPeriod = true;
                                    }
                                @endphp
                                
                                @if($tenant && $tenant->move_in_date)
                                    <div class="min-w-[120px]">
                                        @if($hasMonthlyPeriod)
                                        <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden" title="{{ $periodStart->format('M d') }}–{{ $periodEnd->format('M d') }} ({{ $periodPercent }}%)">
                                            <div class="bg-gradient-to-r {{ $monthBarColor }} h-full rounded-full" style="width: {{ $periodPercent }}%"></div>
                                        </div>
                                        <div class="{{ $monthTextColor }} text-[11px] mt-1 font-medium">{{ $periodDaysLeft }}d left</div>

                                        {{-- Billing payment progress for current period --}}
                                        @if($expectedAmount > 0)
                                        <div class="mt-2">
                                            <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                                <div class="font-medium">Paid</div>
                                                <div class="font-semibold text-gray-700">${{ number_format($paidAmount, 2) }} / ${{ number_format($expectedAmount, 2) }} ({{ $paymentPercent }}%)</div>
                                            </div>
                                            <div class="w-full bg-gray-100 rounded-full h-1 overflow-hidden">
                                                <div class="bg-gradient-to-r {{ $paymentBarColor }} h-full rounded-full" style="width: {{ $paymentPercent }}%"></div>
                                            </div>
                                        </div>
                                        @endif
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-400 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                @php
                                    // Prefer the supervisor who managed/assigned the tenant; fall back to apartment supervisor
                                    $tenant = $apartment->tenants()->whereNull('deleted_at')->latest()->first();
                                    $tenantManager = $tenant?->manager ?? null;
                                    $displaySupervisor = $tenantManager ?? $apartment->supervisor;
                                @endphp
                                {{ $displaySupervisor?->name ?? 'Unassigned' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.apartments.show', $apartment->id) }}" 
                                       title="View apartment"
                                       class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-600 hover:bg-gray-100 transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    
                                    {{-- Leave Tenant Button --}}
                                    @if($tenant && $tenant->status === 'active')
                                    {{-- View Tenant Document Button --}}
                                    @if($tenant && $tenant->document_path)
                                        <a href="{{ asset('storage/' . $tenant->document_path) }}" target="_blank" title="View Tenant Document" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-600 hover:bg-gray-100 transition">
                                            <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z" />
                                            </svg>
                                        </a>
                                    @endif
                                    <button type="button" 
                                            onclick="processLeaveClick(event, {{ $tenant->id }}, '{{ $tenant->name }}')"
                                            title="Process tenant leave"
                                            class="leave-tenant-btn inline-flex items-center justify-center w-9 h-9 rounded-lg text-orange-600 hover:bg-orange-50 transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                    </button>
                                    @endif
                                    
                                    {{-- Assign Tenant Button --}}
                                    @if(!$tenant || $tenant->status !== 'active')
                                    <button type="button" 
                                            data-apartment-id="{{ $apartment->id }}"
                                            data-apartment-number="{{ $apartment->apartment_number }}"
                                            class="assign-tenant-btn inline-flex items-center justify-center w-9 h-9 rounded-lg text-indigo-600 hover:bg-indigo-50 transition"
                                            title="Assign tenant">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </button>
                                    @endif
                                    
                                    <a href="{{ route('admin.apartments.edit', $apartment->id) }}" 
                                       title="Edit apartment"
                                       class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-blue-600 hover:bg-blue-50 transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                    <form action="{{ route('admin.apartments.destroy', $apartment->id) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this apartment?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Delete apartment"
                                                class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-red-600 hover:bg-red-50 transition">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
        <svg class="w-12 h-12 mx-auto mb-2 opacity-50 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
        </svg>
        <p class="font-medium text-gray-500">No apartments found</p>
    </div>
    @endforelse
</div>

<!-- Assign Tenant Modal -->
<div id="assignTenantModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full my-8">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 id="modalTitle" class="text-lg font-semibold text-gray-900">Assign Tenant to <span id="apartmentNumberDisplay"></span></h3>
            <button type="button" class="close-modal text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Tab Navigation -->
        <div id="tabNavigation" class="px-6 pt-4 border-b border-gray-200 hidden">
            <div class="flex gap-4">
                <button type="button" id="existingTenantTab" class="tab-button active px-4 py-2 font-medium text-blue-600 border-b-2 border-blue-600 hover:text-blue-700">
                    Existing Tenant
                </button>
                <button type="button" id="newTenantTab" class="tab-button px-4 py-2 font-medium text-gray-600 border-b-2 border-transparent hover:text-gray-900">
                    Create New Tenant
                </button>
            </div>
        </div>

        <form id="assignTenantForm" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            @csrf
            <input type="hidden" id="apartmentId" name="apartment_id">
            <input type="hidden" id="tenantOption" name="tenant_option" value="existing">

            <!-- Existing Tenant Tab -->
            <div id="existingTenantContent" class="tab-content space-y-4 hidden">
                <div>
                    <label for="tenant_id" class="block text-sm font-medium text-gray-700 mb-1">Select Tenant</label>
                    <select id="tenant_id" name="tenant_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">-- Choose an unassigned tenant --</option>
                        @foreach($availableTenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ $tenant->email }})</option>
                        @endforeach
                    </select>
                    @if($availableTenants->isEmpty())
                        <p class="text-sm text-orange-600 mt-2">No unassigned tenants available. Create a new tenant instead.</p>
                    @endif
                </div>
            </div>

            <!-- New Tenant Tab -->
            <div id="newTenantContent" class="tab-content space-y-6">
                <!-- Personal Information Section -->
                <div class="border-b pb-6">
                    <h4 class="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Personal Information
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                            <input type="text" id="name" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" id="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                            <input type="tel" id="phone" name="phone" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div class="col-span-2">
                            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <input type="text" id="address" name="address" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div class="col-span-2">
                            <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div class= "col-span-2">
                            <label for="attached_photo" class="block text-sm font-medium text-gray-700 mb-1">Attached Photo</label>
                            <input type="file" id="attached_photo" name="attached_photo" accept="image/*,.pdf" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1">Accepted formats: JPG, PNG, GIF, PDF</p>
                        </div>
                        <div class="col-span-2">
                            <label for="id_pdf" class="block text-sm font-medium text-gray-700 mb-1">Attached ID PDF</label>
                            <input type="file" id="id_pdf" name="id_pdf" accept=".pdf,image/*" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1">Accepted formats: PDF, JPG, PNG, GIF</p>
                        </div>
                    </div>
                </div>

                <!-- Rent Information Section -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Rent Information
                    </h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="move_in_date" class="block text-sm font-medium text-gray-700 mb-1">Move In Date *</label>
                            <input type="date" id="move_in_date" name="move_in_date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="deposit" class="block text-sm font-medium text-gray-700 mb-1">Deposit *</label>
                            <input type="number" id="deposit" name="deposit" min="0" step="0.01" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-4 border-t">
                <button type="button" class="close-modal flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">
                    Cancel
                </button>
                <button type="submit" id="submitBtn" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                    Assign Tenant
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('assignTenantModal');
    const form = document.getElementById('assignTenantForm');
    const apartmentIdInput = document.getElementById('apartmentId');
    const apartmentNumberDisplay = document.getElementById('apartmentNumberDisplay');
    const tenantOptionInput = document.getElementById('tenantOption');
    const tabNavigation = document.getElementById('tabNavigation');
    
    // Tab switching
    const existingTenantTab = document.getElementById('existingTenantTab');
    const newTenantTab = document.getElementById('newTenantTab');
    const existingTenantContent = document.getElementById('existingTenantContent');
    const newTenantContent = document.getElementById('newTenantContent');
    const tabButtons = document.querySelectorAll('.tab-button');
    
    function switchToExistingTab() {
        tenantOptionInput.value = 'existing';
        existingTenantContent.classList.remove('hidden');
        newTenantContent.classList.add('hidden');
        
        tabButtons.forEach(btn => btn.classList.remove('active', 'text-blue-600', 'border-blue-600'));
        tabButtons.forEach(btn => btn.classList.add('text-gray-600', 'border-transparent'));
        existingTenantTab.classList.add('active', 'text-blue-600', 'border-blue-600');
        existingTenantTab.classList.remove('text-gray-600', 'border-transparent');
    }
    
    function switchToNewTab() {
        tenantOptionInput.value = 'new';
        existingTenantContent.classList.add('hidden');
        newTenantContent.classList.remove('hidden');
        
        tabButtons.forEach(btn => btn.classList.remove('active', 'text-blue-600', 'border-blue-600'));
        tabButtons.forEach(btn => btn.classList.add('text-gray-600', 'border-transparent'));
        newTenantTab.classList.add('active', 'text-blue-600', 'border-blue-600');
        newTenantTab.classList.remove('text-gray-600', 'border-transparent');
    }
    
    existingTenantTab.addEventListener('click', switchToExistingTab);
    newTenantTab.addEventListener('click', switchToNewTab);
    
    // Open modal for apartment tenant assignment
    document.querySelectorAll('.assign-tenant-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const apartmentId = this.dataset.apartmentId;
            const apartmentNumber = this.dataset.apartmentNumber;
            apartmentIdInput.value = apartmentId;
            apartmentNumberDisplay.textContent = apartmentNumber;
            form.action = `/admin/apartments/${apartmentId}/assign-tenant`;
            
            // Update modal title and submit button for apartment assignment
            document.getElementById('modalTitle').innerHTML = 'Assign Tenant to <span id="apartmentNumberDisplay">' + apartmentNumber + '</span>';
            document.getElementById('submitBtn').textContent = 'Assign Tenant';
            
            // Hide tabs and always show new tenant form
            tabNavigation.classList.add('hidden');
            existingTenantContent.classList.add('hidden');
            newTenantContent.classList.remove('hidden');
            
            // Reset form
            tenantOptionInput.value = 'new';
            form.reset();
            
            modal.classList.remove('hidden');
        });
    });
    
    // Close modal
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            modal.classList.add('hidden');
        });
    });

    // Close on backdrop click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.add('hidden');
        }
    });

    // Process tenant leave with confirmation
    window.processLeaveClick = function(event, tenantId, tenantName) {
        event.preventDefault();
        
        console.log('✓ Leave button clicked for tenant:', tenantName, 'ID:', tenantId);
        
        // Show confirmation dialog
        if (confirm(`Are you sure you want to process leave for tenant "${tenantName}"?\n\nThis will:\n- Archive the tenant\n- Calculate final settlement\n- Mark apartment as available`)) {
            console.log('✓ User confirmed leave action for tenant:', tenantId);
            
            const leaveUrl = `/admin/tenants/${tenantId}/leave`;
            console.log('✓ Navigating to:', leaveUrl);
            
            try {
                window.location.href = leaveUrl;
            } catch (error) {
                console.error('✗ Error navigating to leave page:', error);
                alert('Error: Could not navigate to leave processing page. Please try again.');
            }
        } else {
            console.log('✗ Leave action cancelled by user for tenant:', tenantId);
        }
    };
});
</script>

@endsection
