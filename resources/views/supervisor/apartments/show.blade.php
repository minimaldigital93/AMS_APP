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

    <!-- Apartment Details -->
    <div class="bg-white rounded-xl border border-slate-100">
        <div class="p-6">
            <h2 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">Apartment Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <p class="text-[11px] text-slate-400 uppercase tracking-wider font-medium">Apartment Number</p>
                    <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ $apartment->apartment_number }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 uppercase tracking-wider font-medium">Floor</p>
                    <p class="text-sm text-slate-600 mt-0.5">{{ $apartment->floor?->floor_name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 uppercase tracking-wider font-medium">Monthly Rent</p>
                    <p class="text-sm font-semibold text-slate-700 mt-0.5">${{ number_format($apartment->monthly_rent, 2) }}</p>
                </div>
                <div>
                    <p class="text-[11px] text-slate-400 uppercase tracking-wider font-medium">Status</p>
                    <p class="text-sm text-slate-600 mt-0.5">{{ ucfirst($apartment->status) }}</p>
                </div>
                @if($apartment->description)
                <div class="md:col-span-2">
                    <p class="text-[11px] text-slate-400 uppercase tracking-wider font-medium">Description</p>
                    <p class="text-sm text-slate-600 mt-0.5">{{ $apartment->description }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Current Tenant -->
    @if($activeRental)
    <div class="bg-white rounded-xl border border-slate-100">
        <div class="p-6">
            <h2 class="text-sm font-medium text-slate-500 uppercase tracking-wide mb-4">Current Tenant</h2>
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 font-semibold text-lg">
                    {{ strtoupper(substr($activeRental->tenant->name ?? '?', 0, 1)) }}
                </div>
                <div class="flex-1">
                    <p class="text-base font-semibold text-slate-800">{{ $activeRental->tenant->name ?? 'N/A' }}</p>
                    <p class="text-sm text-slate-400">{{ $activeRental->tenant->email ?? '' }}</p>
                    <p class="text-xs text-slate-300 mt-0.5">Since {{ \Carbon\Carbon::parse($activeRental->start_date)->format('M d, Y') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-[11px] text-slate-400 uppercase tracking-wider font-medium">Rent</p>
                    <p class="text-lg font-semibold text-slate-800">${{ number_format($activeRental->rent_amount, 2) }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Rent Payment Progress -->
    @if(!empty($rentProgress) && count($rentProgress) > 0)
    <div class="bg-white rounded-xl border border-slate-100">
        <div class="p-6">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-sm font-medium text-slate-500 uppercase tracking-wide">Rent Payment Timeline</h2>
                <div class="text-right">
                    <span class="text-lg font-semibold {{ $overallPercent >= 80 ? 'text-emerald-600' : ($overallPercent >= 50 ? 'text-amber-600' : 'text-red-500') }}">{{ $overallPercent }}%</span>
                    <p class="text-[11px] text-slate-400">${{ number_format($totalPaid ?? 0, 2) }} / ${{ number_format($totalExpected ?? 0, 2) }}</p>
                </div>
            </div>

            <!-- Overall Progress Bar -->
            <div class="w-full bg-slate-100 rounded-full h-1.5 mb-6">
                <div class="h-1.5 rounded-full {{ $overallPercent >= 80 ? 'bg-emerald-400' : ($overallPercent >= 50 ? 'bg-amber-400' : 'bg-red-400') }}" style="width: {{ $overallPercent }}%"></div>
            </div>

            <!-- Monthly Timeline -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach($rentProgress as $month)
                @php
                    $displayPercent = $month['percent'] ?? 0;
                    if(!empty($month['is_current']) && $month['is_current'] && !empty($activeRental)) {
                        if(isset($month['year']) && isset($month['month']) && is_numeric($month['month'])) {
                            $monthStart = \Carbon\Carbon::createFromDate($month['year'], $month['month'], 1)->startOfMonth();
                        } else {
                            try {
                                $monthStart = \Carbon\Carbon::createFromFormat('M Y', ($month['month'] ?? '') . ' ' . ($month['year'] ?? now()->year))->startOfMonth();
                            } catch (\Exception $e) {
                                $monthStart = \Carbon\Carbon::createFromDate($month['year'] ?? now()->year, now()->month, 1)->startOfMonth();
                            }
                        }
                        $monthEnd = (clone $monthStart)->endOfMonth();
                        $rentalStart = \Carbon\Carbon::parse($activeRental->start_date)->startOfDay();
                        $rentalEnd = $activeRental->end_date ? \Carbon\Carbon::parse($activeRental->end_date)->endOfDay() : now();
                        $occStart = $rentalStart->greaterThan($monthStart) ? $rentalStart : $monthStart;
                        $occEnd = $rentalEnd->lessThan($monthEnd) ? $rentalEnd : $monthEnd;
                        $daysInMonth = $monthStart->daysInMonth;
                        $daysOccupied = $occEnd->gte($occStart) ? $occEnd->diffInDays($occStart) + 1 : 0;
                        $displayPercent = $daysInMonth > 0 ? min(100, round(($daysOccupied / $daysInMonth) * 100, 1)) : $displayPercent;
                    }
                @endphp
                <div class="rounded-xl border p-3 {{ $month['is_current'] ? 'border-slate-300 bg-slate-50' : 'border-slate-100' }}">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-semibold text-slate-600">{{ $month['label'] }}</span>
                        @if($month['is_current'])
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-500 animate-pulse"></span>
                        @endif
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-1 mb-1.5">
                        <div class="h-1 rounded-full
                            {{ $month['status'] === 'paid' ? 'bg-emerald-400' :
                                ($month['status'] === 'partial' ? 'bg-amber-400' :
                                ($month['status'] === 'overdue' ? 'bg-red-400' :
                                ($month['status'] === 'due' ? 'bg-sky-400' : 'bg-slate-200'))) }}"
                            style="width: {{ $displayPercent }}%"></div>
                    </div>
                    <div class="flex justify-between text-[10px]">
                        <span class="{{ $month['status'] === 'paid' ? 'text-emerald-600' :
                                        ($month['status'] === 'partial' ? 'text-amber-600' :
                                        ($month['status'] === 'overdue' ? 'text-red-500' :
                                        ($month['status'] === 'due' ? 'text-sky-600' : 'text-slate-400'))) }} font-medium">
                            {{ ucfirst($month['status']) }}
                        </span>
                        <span class="text-slate-400">{{ $displayPercent }}%</span>
                    </div>
                    @if(!empty($month['paid_date']))
                    <p class="text-[10px] text-slate-300 mt-1">Paid {{ $month['paid_date'] }}</p>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
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
