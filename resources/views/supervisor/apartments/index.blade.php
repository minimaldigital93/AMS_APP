@extends('layouts.supervisor')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">My Apartments</h1>
            <p class="text-sm text-gray-500 mt-1">Apartments assigned to you, grouped by floor</p>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
            <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span class="text-sm font-medium">{{ session('success') }}</span>
        </div>
        @endif

        @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 text-sm">{{ session('error') }}</div>
        @endif

        {{-- Filters --}}
        <div class="bg-white rounded-lg shadow-md mb-6 p-6">
            <form method="GET" action="{{ route('supervisor.apartments.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" placeholder="Search apartment number..." value="{{ request('search') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        <option value="">All Status</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 border border-emerald-300 rounded-lg text-emerald-600 hover:bg-emerald-50 transition font-medium">Filter</button>
                    <a href="{{ route('supervisor.apartments.index') }}" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium text-center">Reset</a>
                </div>
            </form>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow-md p-4 text-center">
                <p class="text-2xl font-bold text-gray-900">{{ $apartments->count() }}</p>
                <p class="text-xs text-gray-500 mt-1">Total Rooms</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4 text-center">
                <p class="text-2xl font-bold text-green-600">{{ $apartments->where('status', 'available')->count() }}</p>
                <p class="text-xs text-gray-500 mt-1">Available</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ $apartments->where('status', 'occupied')->count() }}</p>
                <p class="text-xs text-gray-500 mt-1">Occupied</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-4 text-center">
                <p class="text-2xl font-bold text-orange-600">{{ $apartments->where('status', 'maintenance')->count() }}</p>
                <p class="text-xs text-gray-500 mt-1">Maintenance</p>
            </div>
        </div>

        {{-- Apartments list view --}}
        @if($apartments->isEmpty())
            <div class="bg-white rounded-lg shadow-md p-12 text-center text-gray-400">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <p class="text-lg font-medium">No apartments found.</p>
                <p class="text-sm mt-1">Try adjusting filters or contact an administrator.</p>
            </div>
        @else
            @foreach($apartmentsByFloor as $floorId => $floorApartments)
                @php $floor = $floors->firstWhere('id', $floorId); @endphp
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-bold text-gray-900">{{ $floor?->floor_name ?? 'Floor ' . $floorId }}</h2>
                        <span class="text-sm text-gray-500">({{ $floorApartments->count() }} rooms)</span>
                    </div>

                    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Apartment</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly Rent</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($floorApartments as $apartment)
                                    @php $activeTenant = $apartment->tenants->where('status', 'active')->first(); @endphp
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $apartment->apartment_number }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $apartment->status === 'available' ? 'bg-green-100 text-green-800' :
                                                   ($apartment->status === 'occupied' ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800') }}">
                                                {{ ucfirst($apartment->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($apartment->monthly_rent, 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $activeTenant?->name ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="{{ route('supervisor.apartments.show', $apartment) }}" class="text-emerald-600 hover:text-emerald-800" title="View">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </a>
                                            @if($apartment->status === 'available')
                                            <a href="{{ route('supervisor.tenants.create') }}?apartment_id={{ $apartment->id }}" class="ml-4 text-blue-600 hover:text-blue-800" title="Assign">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            @php
                $noFloor = $apartments->filter(function($a) { return $a->floor === null; });
            @endphp

            @if($noFloor->isNotEmpty())
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-bold text-gray-900">Unassigned Floor</h2>
                        <span class="text-sm text-gray-500">({{ $noFloor->count() }} rooms)</span>
                    </div>

                    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-4">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Apartment</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly Rent</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($noFloor as $apartment)
                                    @php $activeTenant = $apartment->tenants->where('status', 'active')->first(); @endphp
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $apartment->apartment_number }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                {{ $apartment->status === 'available' ? 'bg-green-100 text-green-800' :
                                                   ($apartment->status === 'occupied' ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800') }}">
                                                {{ ucfirst($apartment->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($apartment->monthly_rent, 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $activeTenant?->name ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="{{ route('supervisor.apartments.show', $apartment) }}" class="text-emerald-600 hover:text-emerald-800">View</a>
                                            @if($apartment->status === 'available')
                                            <a href="{{ route('supervisor.tenants.create') }}?apartment_id={{ $apartment->id }}" class="ml-4 text-blue-600 hover:text-blue-800">Assign</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
