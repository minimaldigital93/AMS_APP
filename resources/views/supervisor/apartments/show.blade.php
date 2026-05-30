@extends('layouts.supervisor')

@section('title', 'View Apartment')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ $apartment->apartment_number }}</h1>
                <p class="text-slate-400 text-sm mt-0.5">
                    Floor {{ $apartment->floor?->floor_name ?? 'N/A' }}
                    @if(isset($activePeriod) && $activePeriod)
                        · {{ $activePeriod->name }}
                    @endif
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span @class([
                'inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg',
                'text-emerald-600 bg-emerald-50' => $apartment->status === 'available',
                'text-sky-600 bg-sky-50' => $apartment->status === 'occupied',
                'text-amber-600 bg-amber-50' => $apartment->status === 'maintenance',
                'text-slate-500 bg-slate-50' => !in_array($apartment->status, ['available', 'occupied', 'maintenance']),
            ])>
            <span @class([
                'w-1.5 h-1.5 rounded-full',
                'bg-emerald-400' => $apartment->status === 'available',
                'bg-sky-400' => $apartment->status === 'occupied',
                'bg-amber-400' => $apartment->status === 'maintenance',
                'bg-slate-300' => !in_array($apartment->status, ['available', 'occupied', 'maintenance']),
            ])></span>
            {{ ucfirst($apartment->status) }}
            </span>
            <a href="{{ route('supervisor.apartments.index') }}" class="inline-flex items-center gap-2 text-slate-500 hover:text-slate-700 text-sm font-medium py-2.5 px-5 rounded-lg border border-slate-200 hover:border-slate-300 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
        </div>
    </div>

    <!-- Current Tenant — universal tenant view (same as Active Tenants) -->
    @if($activeRental && $activeRental->tenant)
    <div>
        <h2 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">Current Tenant</h2>
        @include('partials.tenant-show', [
            'tenant' => $activeRental->tenant,
            'role' => 'supervisor',
            'showHeader' => false,
        ])
    </div>
    @endif

    <!-- Actions -->
    <div class="flex items-center gap-3">
        @if($apartment->status === 'available')
        <a href="{{ route('supervisor.tenants.create') }}?apartment_id={{ $apartment->id }}" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Assign Tenant
        </a>
        @endif
        @if($activeRental && $activeRental->tenant)
        <a href="{{ route('supervisor.tenants.show', $activeRental->tenant) }}" class="inline-flex items-center gap-2 text-slate-500 hover:text-slate-700 text-sm font-medium py-2.5 px-5 rounded-lg border border-slate-200 hover:border-slate-300 transition">
            View Tenant Profile
        </a>
        @endif
    </div>
</div>
@endsection
