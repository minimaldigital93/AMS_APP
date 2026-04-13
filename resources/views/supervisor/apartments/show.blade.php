@extends('layouts.supervisor')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="flex items-center gap-4 mb-8">
            <a href="{{ route('supervisor.apartments.index') }}" class="text-emerald-600 hover:text-emerald-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $apartment->apartment_number }}</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Floor {{ $apartment->floor?->floor_name ?? 'N/A' }}
                    @if(isset($activePeriod) && $activePeriod)
                        · Fiscal Period: {{ $activePeriod->name }}
                    @endif
                </p>
            </div>
            <span class="ml-auto inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                {{ $apartment->status === 'available' ? 'bg-green-100 text-green-800' :
                   ($apartment->status === 'occupied' ? 'bg-blue-100 text-blue-800' :
                   ($apartment->status === 'maintenance' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800')) }}">
                {{ ucfirst($apartment->status) }}
            </span>
        </div>

        {{-- Apartment Details --}}
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Apartment Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Apartment Number</label>
                    <p class="text-sm font-semibold text-gray-900">{{ $apartment->apartment_number }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Floor</label>
                    <p class="text-sm text-gray-700">{{ $apartment->floor?->floor_name ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Monthly Rent</label>
                    <p class="text-sm font-semibold text-emerald-600">${{ number_format($apartment->monthly_rent, 2) }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 uppercase">Status</label>
                    <p class="text-sm text-gray-700">{{ ucfirst($apartment->status) }}</p>
                </div>
                @if($apartment->description)
                <div class="md:col-span-2">
                    <label class="text-xs font-medium text-gray-500 uppercase">Description</label>
                    <p class="text-sm text-gray-700">{{ $apartment->description }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Current Tenant --}}
        @if($activeRental)
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Current Tenant</h2>
            <div class="flex items-center gap-4">
                <div class="h-14 w-14 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold text-xl">
                    {{ strtoupper(substr($activeRental->tenant->name ?? '?', 0, 1)) }}
                </div>
                <div class="flex-1">
                    <p class="text-lg font-semibold text-gray-900">{{ $activeRental->tenant->name ?? 'N/A' }}</p>
                    <p class="text-sm text-gray-500">{{ $activeRental->tenant->email ?? '' }}</p>
                    <p class="text-xs text-gray-400">Since {{ \Carbon\Carbon::parse($activeRental->start_date)->format('M d, Y') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Rent</p>
                    <p class="text-lg font-bold text-gray-900">${{ number_format($activeRental->rent_amount, 2) }}</p>
                </div>
            </div>
        </div>
        @endif

        {{-- Rent Payment Progress --}}
        @if(count($rentProgress) > 0)
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-900">Rent Payment Timeline</h2>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Overall Progress</p>
                    <p class="text-lg font-bold {{ $overallPercent >= 80 ? 'text-green-600' : ($overallPercent >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ $overallPercent }}%
                    </p>
                    <p class="text-xs text-gray-400">${{ number_format($totalPaid, 2) }} / ${{ number_format($totalExpected, 2) }}</p>
                </div>
            </div>

            {{-- Overall Progress Bar --}}
            <div class="w-full bg-gray-200 rounded-full h-2 mb-6">
                <div class="h-2 rounded-full {{ $overallPercent >= 80 ? 'bg-green-500' : ($overallPercent >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $overallPercent }}%"></div>
            </div>

            {{-- Monthly Timeline --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach($rentProgress as $month)
                <div class="border rounded-lg p-3 {{ $month['is_current'] ? 'border-emerald-300 bg-emerald-50' : 'border-gray-200' }}">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-gray-700">{{ $month['label'] }}</span>
                        @if($month['is_current'])
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        @endif
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5 mb-1">
                        <div class="h-1.5 rounded-full
                            {{ $month['status'] === 'paid' ? 'bg-green-500' :
                               ($month['status'] === 'partial' ? 'bg-yellow-500' :
                               ($month['status'] === 'overdue' ? 'bg-red-500' :
                               ($month['status'] === 'due' ? 'bg-blue-500' : 'bg-gray-300'))) }}"
                            style="width: {{ $month['percent'] }}%"></div>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="{{ $month['status'] === 'paid' ? 'text-green-600' :
                                        ($month['status'] === 'partial' ? 'text-yellow-600' :
                                        ($month['status'] === 'overdue' ? 'text-red-600' :
                                        ($month['status'] === 'due' ? 'text-blue-600' : 'text-gray-400'))) }} font-medium">
                            {{ ucfirst($month['status']) }}
                        </span>
                        <span class="text-gray-500">{{ $month['percent'] }}%</span>
                    </div>
                    @if($month['paid_date'])
                    <p class="text-xs text-gray-400 mt-1">Paid {{ $month['paid_date'] }}</p>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Actions --}}
        <div class="flex items-center gap-3">
            @if($apartment->status === 'available')
            <a href="{{ route('supervisor.tenants.create') }}?apartment_id={{ $apartment->id }}" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Assign Tenant
            </a>
            @endif
            @if($activeRental && $activeRental->tenant)
            <a href="{{ route('supervisor.tenants.show', $activeRental->tenant) }}" class="inline-flex items-center gap-2 border border-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-50 transition text-sm font-medium">
                View Tenant Profile
            </a>
            <a href="{{ route('supervisor.tenants.leave', $activeRental->tenant) }}" class="inline-flex items-center gap-2 border border-red-300 text-red-600 px-4 py-2 rounded-lg hover:bg-red-50 transition text-sm font-medium">
                Process Leave
            </a>
            @endif
        </div>
    </div>
</div>
@endsection
