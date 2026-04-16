@extends('layouts.admin')

@section('title', 'View Apartment')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Apartment {{ $apartment->apartment_number }}</h1>
            <p class="text-gray-600 mt-1">View complete apartment details</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.apartments.edit', $apartment->id) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
            </a>
            <a href="{{ route('admin.apartments.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-4 rounded-lg transition duration-200">
                Back to Apartments
            </a>
        </div>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Apartment Information Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
                <h2 class="text-2xl font-semibold text-gray-900">Apartment Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Apartment Number -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Apartment Number</label>
                        <p class="text-lg font-semibold text-gray-900">{{ $apartment->apartment_number }}</p>
                    </div>

                    <!-- Floor -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Floor</label>
                        <p class="text-lg font-semibold text-gray-900">{{ $apartment->floor->floor_name ?? 'N/A' }}</p>
                    </div>

                    <!-- Monthly Rent -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Rent</label>
                        <p class="text-lg font-semibold text-gray-900">${{ number_format($apartment->monthly_rent, 2) }}</p>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <div>
                            @if($apartment->status === 'available')
                                <span class="inline-flex items-center bg-green-100 text-green-800 text-sm px-3 py-1 rounded-full font-medium">
                                    About Available
                                </span>
                            @elseif($apartment->status === 'occupied')
                                <span class="inline-flex items-center bg-blue-100 text-blue-800 text-sm px-3 py-1 rounded-full font-medium">
                                    Occupied
                                </span>
                            @else
                                <span class="inline-flex items-center bg-yellow-100 text-yellow-800 text-sm px-3 py-1 rounded-full font-medium">
                                    Maintenance
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Supervisor -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supervisor</label>
                        <p class="text-lg font-semibold text-gray-900">{{ $apartment->supervisor->name ?? 'Unassigned' }}</p>
                    </div>

                    <!-- Created Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Created Date</label>
                        <p class="text-lg font-semibold text-gray-900">{{ $apartment->created_at?->format('M d, Y') ?? 'N/A' }}</p>
                    </div>
                </div>

                <!-- Description -->
                @if($apartment->description)
                <div class="pt-4 border-t border-gray-200">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <p class="text-gray-700 whitespace-pre-wrap">{{ $apartment->description }}</p>
                </div>
                @endif
            </div>

            <!-- Rent Payment Progress -->
            @if($activeRental && count($rentProgress) > 0)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-900">Rent Payment Progress</h2>
                        <p class="text-sm text-gray-500 mt-1">
                            {{ $activeRental->tenant->name ?? 'Tenant' }} &middot;
                            ${{ number_format($activeRental->rent_amount, 2) }}/mo &middot;
                            Since {{ \Carbon\Carbon::parse($activeRental->start_date)->format('M d, Y') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold {{ $overallPercent >= 100 ? 'text-green-600' : ($overallPercent > 0 ? 'text-blue-600' : 'text-gray-400') }}">{{ $overallPercent }}%</p>
                        <p class="text-xs text-gray-500">${{ number_format($totalPaid, 2) }} / ${{ number_format($totalExpected, 2) }}</p>
                    </div>
                </div>

                {{-- Overall Bar --}}
                <div class="w-full bg-gray-100 rounded-full h-3">
                    <div class="h-3 rounded-full transition-all duration-500 {{ $overallPercent >= 100 ? 'bg-green-500' : ($overallPercent > 50 ? 'bg-blue-500' : ($overallPercent > 0 ? 'bg-yellow-500' : 'bg-gray-200')) }}"
                         style="width: {{ $overallPercent }}%"></div>
                </div>

                {{-- Monthly Breakdown --}}
                <div class="space-y-2">
                    @foreach($rentProgress as $rp)
                    <div class="flex items-center gap-3 p-2.5 rounded-lg {{ $rp['is_current'] ? 'bg-blue-50 border border-blue-200' : 'hover:bg-gray-50' }}">
                        {{-- Month Label --}}
                        <div class="w-16 shrink-0">
                            <p class="text-sm font-semibold {{ $rp['is_current'] ? 'text-blue-700' : 'text-gray-700' }}">{{ $rp['month'] }}</p>
                            <p class="text-xs text-gray-400">{{ $rp['year'] }}</p>
                        </div>

                        {{-- Progress Bar --}}
                        <div class="flex-1">
                            <div class="w-full bg-gray-100 rounded-full h-4 relative overflow-hidden">
                                @php
                                    $barColor = match($rp['status']) {
                                        'paid' => 'bg-green-500',
                                        'partial' => 'bg-yellow-500',
                                        'overdue' => 'bg-red-400',
                                        'due' => 'bg-blue-200',
                                        default => 'bg-gray-200',
                                    };
                                @endphp
                                <div class="{{ $barColor }} h-full rounded-full transition-all duration-500 flex items-center justify-end pr-1"
                                     style="width: {{ max($rp['percent'], ($rp['status'] === 'due' ? 100 : ($rp['status'] === 'upcoming' ? 100 : 0))) }}%">
                                    @if($rp['percent'] > 15)
                                    <span class="text-[10px] font-bold text-white">{{ $rp['percent'] }}%</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Amount --}}
                        <div class="w-24 text-right shrink-0">
                            <p class="text-sm font-semibold {{ $rp['status'] === 'paid' ? 'text-green-600' : ($rp['status'] === 'overdue' ? 'text-red-600' : 'text-gray-700') }}">
                                ${{ number_format($rp['paid'], 2) }}
                            </p>
                            @if($rp['late_fee'] > 0)
                            <p class="text-[10px] text-orange-500">+${{ number_format($rp['late_fee'], 2) }} fee</p>
                            @endif
                        </div>

                        {{-- Status Badge --}}
                        <div class="w-20 text-center shrink-0">
                            @php
                                $badgeClasses = match($rp['status']) {
                                    'paid' => 'bg-green-100 text-green-700',
                                    'partial' => 'bg-yellow-100 text-yellow-700',
                                    'overdue' => 'bg-red-100 text-red-700',
                                    'due' => 'bg-blue-100 text-blue-700',
                                    default => 'bg-gray-100 text-gray-500',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badgeClasses }}">
                                {{ ucfirst($rp['status']) }}
                            </span>
                            @if($rp['paid_date'])
                            <p class="text-[10px] text-gray-400 mt-0.5">{{ $rp['paid_date'] }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Legend --}}
                <div class="flex items-center gap-4 pt-2 border-t text-xs text-gray-500">
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span> Paid</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span> Partial</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-red-400"></span> Overdue</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-blue-200"></span> Due Now</span>
                    <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-gray-200"></span> Upcoming</span>
                </div>
            </div>
            @elseif(!$activeRental)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-center">
                <div class="text-gray-400 mb-2">
                    <svg class="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h2 class="text-lg font-semibold text-gray-900">Rent Payment Progress</h2>
                <p class="text-sm text-gray-500 mt-1">No active rental — assign a tenant to track rent.</p>
            </div>
            @endif

            <!-- Tenant Information Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-2xl font-semibold text-gray-900 mb-6">Current Tenant</h2>
                
                @php
                    $tenant = $apartment->tenants()->latest()->first();
                @endphp

                @if($tenant)
                    <div class="space-y-4">
                        <!-- Tenant Photo -->
                        @if($tenant->photo_path && !str_ends_with($tenant->photo_path, '.pdf'))
                            <div class="mb-6">
                                <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}" class="h-40 w-40 rounded-lg object-cover shadow-md border border-gray-300">
                            </div>
                        @elseif($tenant->photo_path && str_ends_with($tenant->photo_path, '.pdf'))
                            <div class="mb-6">
                                <a href="{{ asset('storage/' . $tenant->photo_path) }}" target="_blank" class="inline-flex items-center px-3 py-2 bg-red-50 text-red-600 rounded-lg border border-red-200">
                                    <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                                    View Photo (PDF)
                                </a>
                            </div>
                        @endif

                        {{-- Action buttons for tenant attachments --}}
                        <div class="mb-4 flex items-center gap-3">
                            @if($tenant && $tenant->photo_path)
                                <a href="{{ asset('storage/' . $tenant->photo_path) }}" target="_blank" class="inline-flex items-center px-3 py-2 bg-gray-50 text-gray-700 rounded-lg border border-gray-200 hover:bg-gray-100">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    View Photo
                                </a>
                            @endif

                            @if($tenant && $tenant->document_path)
                                <a href="{{ asset('storage/' . $tenant->document_path) }}" target="_blank" class="inline-flex items-center px-3 py-2 bg-gray-50 text-gray-700 rounded-lg border border-gray-200 hover:bg-gray-100">
                                    <svg class="w-4 h-4 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z" /></svg>
                                    View Document
                                </a>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Tenant Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <p class="text-lg font-semibold text-gray-900">{{ $tenant->name }}</p>
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <p class="text-gray-700">
                                    <a href="mailto:{{ $tenant->email }}" class="text-blue-600 hover:underline">{{ $tenant->email }}</a>
                                </p>
                            </div>

                            <!-- Phone -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <p class="text-gray-700">
                                    <a href="tel:{{ $tenant->phone }}" class="text-blue-600 hover:underline">{{ $tenant->phone }}</a>
                                </p>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tenant Status</label>
                                <div>
                                    @if($tenant->status === 'active')
                                        <span class="inline-flex items-center gap-1 px-3 py-1 text-sm font-medium bg-blue-100 text-blue-800 rounded">
                                            <span class="w-2 h-2 rounded-full bg-blue-600"></span> Active
                                        </span>
                                    @elseif($tenant->status === 'pending')
                                        <span class="inline-flex items-center gap-1 px-3 py-1 text-sm font-medium bg-orange-100 text-orange-800 rounded">
                                            <span class="w-2 h-2 rounded-full bg-orange-600"></span> Pending
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-3 py-1 text-sm font-medium bg-gray-200 text-gray-800 rounded">
                                            <span class="w-2 h-2 rounded-full bg-gray-600"></span> Inactive
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Move In Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Move In Date</label>
                                <p class="text-gray-700">{{ $tenant->move_in_date?->format('M d, Y') ?? 'N/A' }}</p>
                            </div>

                            <!-- Deposit -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Deposit Amount</label>
                                <p class="text-gray-700">${{ number_format($tenant->deposit ?? 0, 2) }}</p>
                            </div>

                            <!-- Date of Birth -->
                            @if($tenant->date_of_birth)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                                <p class="text-gray-700">{{ $tenant->date_of_birth->format('M d, Y') }}</p>
                            </div>
                            @endif
                        </div>

                        <!-- Address -->
                        @if($tenant->address)
                        <div class="pt-4 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <p class="text-gray-700 whitespace-pre-wrap">{{ $tenant->address }}</p>
                        </div>
                        @endif

                        <!-- Notes -->
                        @if($tenant->notes)
                        <div class="pt-4 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <p class="text-gray-700 whitespace-pre-wrap">{{ $tenant->notes }}</p>
                        </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 8.646 4 4 0 010-8.646M9 9H3m18 0h-6m-2 5.5C10.5 15.5 8.97 16 7.5 16c-1.93 0-3.5 1.343-3.5 3" />
                        </svg>
                        <p class="text-gray-500 font-medium">No tenant assigned yet</p>
                        <p class="text-gray-400 text-sm mt-1">Assign a tenant to this apartment</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Quick Stats Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">Quick Info</h3>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600 text-sm">Apartment ID</span>
                        <span class="font-semibold text-gray-900">{{ $apartment->id }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600 text-sm">Total Tenants</span>
                        <span class="font-semibold text-gray-900">{{ $apartment->tenants->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600 text-sm">Last Updated</span>
                        <span class="font-semibold text-gray-900">{{ $apartment->updated_at?->diffForHumans() ?? 'N/A' }}</span>
                    </div>
                    @php
                        $assignedBy = $tenant?->manager?->name ?? $apartment->supervisor?->name ?? null;
                    @endphp
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600 text-sm">Assigned By</span>
                        <span class="font-semibold text-gray-900">{{ $assignedBy ?? 'Unassigned' }}</span>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Card -->
            @if($apartment->allTenants->count() > 0)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Tenants History</h3>
                
                <!-- Active Tenants -->
                @if($apartment->tenants->count() > 0)
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Active Tenants</h4>
                    <div class="space-y-3">
                        @foreach($apartment->tenants->take(5) as $historicalTenant)
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 flex items-center gap-3">
                            @if($historicalTenant->photo_path && !str_ends_with($historicalTenant->photo_path, '.pdf'))
                                <img src="{{ asset('storage/' . $historicalTenant->photo_path) }}" alt="{{ $historicalTenant->name }}" class="h-10 w-10 rounded-full object-cover border border-gray-300">
                            @elseif($historicalTenant->photo_path && str_ends_with($historicalTenant->photo_path, '.pdf'))
                                <a href="{{ asset('storage/' . $historicalTenant->photo_path) }}" target="_blank" class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center text-xs font-semibold text-red-600 border border-red-300" title="View PDF">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z" clip-rule="evenodd"/></svg>
                                </a>
                            @else
                                <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-xs font-semibold text-blue-600">
                                    {{ strtoupper(substr($historicalTenant->name, 0, 1)) }}
                                </div>
                            @endif
                            <div>
                                <p class="font-medium text-gray-900">{{ $historicalTenant->name }}</p>
                                <p class="text-xs text-gray-600 mt-1">From {{ $historicalTenant->move_in_date?->format('M d, Y') ?? 'Date unknown' }}</p>
                                @if($historicalTenant->document_path)
                                    <div class="mt-2">
                                        <a href="{{ asset('storage/' . $historicalTenant->document_path) }}" target="_blank" class="inline-flex items-center px-2 py-1 bg-gray-50 text-gray-700 rounded-lg border border-gray-200 hover:bg-gray-100 text-xs">
                                            <svg class="w-4 h-4 mr-1 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                                            View Document
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                
                <!-- Archived Tenants -->
                @if($apartment->archivedTenants->count() > 0)
                <div>
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Archived Tenants</h4>
                    <div class="space-y-3">
                        @foreach($apartment->archivedTenants->take(5) as $archivedTenant)
                        <div class="p-3 bg-amber-50 rounded-lg border border-amber-200 flex items-center gap-3">
                            @if($archivedTenant->photo_path && !str_ends_with($archivedTenant->photo_path, '.pdf'))
                                <img src="{{ asset('storage/' . $archivedTenant->photo_path) }}" alt="{{ $archivedTenant->name }}" class="h-10 w-10 rounded-full object-cover border border-gray-300">
                            @elseif($archivedTenant->photo_path && str_ends_with($archivedTenant->photo_path, '.pdf'))
                                <a href="{{ asset('storage/' . $archivedTenant->photo_path) }}" target="_blank" class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center text-xs font-semibold text-red-600 border border-red-300" title="View PDF">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z" clip-rule="evenodd"/></svg>
                                </a>
                            @else
                                <div class="h-10 w-10 rounded-full bg-amber-100 flex items-center justify-center text-xs font-semibold text-amber-600">
                                    {{ strtoupper(substr($archivedTenant->name, 0, 1)) }}
                                </div>
                            @endif
                            <div>
                                <p class="font-medium text-gray-900">{{ $archivedTenant->name }}</p>
                                <p class="text-xs text-gray-600 mt-1">From {{ $archivedTenant->move_in_date?->format('M d, Y') ?? 'Date unknown' }} to {{ $archivedTenant->move_out_date?->format('M d, Y') ?? 'Unknown' }}</p>
                                <p class="text-xs text-amber-600 mt-1">Archived: {{ $archivedTenant->archived_at?->format('M d, Y') ?? 'N/A' }}</p>
                                @if($archivedTenant->document_path)
                                    <div class="mt-2">
                                        <a href="{{ asset('storage/' . $archivedTenant->document_path) }}" target="_blank" class="inline-flex items-center px-2 py-1 bg-amber-50 text-amber-700 rounded-lg border border-amber-200 hover:bg-amber-100 text-xs">
                                            <svg class="w-4 h-4 mr-1 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                                            View Document
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
