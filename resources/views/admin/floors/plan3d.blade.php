@extends('layouts.admin')

@section('title', 'Floor Layout')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.floor_layout') }}</h1>
            <p class="text-slate-400 text-sm mt-1">A quick visual map of every floor and the status of each apartment.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 self-start">
            <a href="{{ route('admin.revenue_expense.record_income') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-sm" title="{{ __('messages.record_income') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></a>
            <a href="{{ route('admin.revenue_expense.record_expense') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 transition shadow-sm" title="{{ __('messages.record_expense') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg></a>
            <a href="{{ route('admin.dashboard') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition" title="Back to Dashboard">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></a>
        </div>
    </div>

    {{-- Summary chips --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div class="bg-white rounded-xl border border-slate-100 p-4">
            <p class="text-xs text-slate-400 font-medium">{{ __('messages.floors') }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ $summary['floors'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4">
            <p class="text-xs text-slate-400 font-medium">{{ __('messages.total_units') }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ $summary['total'] }}</p>
        </div>
        <div class="bg-emerald-50 rounded-xl border border-emerald-100 p-4">
            <p class="text-xs text-emerald-600 font-medium">{{ __('messages.available') }}</p>
            <p class="text-2xl font-bold text-emerald-700">{{ $summary['available'] }}</p>
        </div>
        <div class="bg-rose-50 rounded-xl border border-rose-100 p-4">
            <p class="text-xs text-rose-600 font-medium">{{ __('messages.occupied') }}</p>
            <p class="text-2xl font-bold text-rose-700">{{ $summary['occupied'] }}</p>
        </div>
        <div class="bg-slate-50 rounded-xl border border-slate-100 p-4">
            <p class="text-xs text-slate-500 font-medium">{{ __('messages.maintenance') }}</p>
            <p class="text-2xl font-bold text-slate-700">{{ $summary['maintenance'] }}</p>
        </div>
    </div>

    @if($summary['floors'] === 0)
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-center">
            <p class="text-yellow-800 font-medium">{{ __('messages.no_floors_yet_title') }}</p>
            <a href="{{ route('admin.floors.create') }}" class="inline-block mt-3 bg-yellow-600 text-white px-5 py-2 rounded-lg hover:bg-yellow-700 transition text-sm font-medium">{{ __('messages.create_a_floor') }}</a>
        </div>
    @else
        {{-- Overall occupancy progress --}}
        @php
            $occupancyRate = $summary['total'] > 0 ? round(($summary['occupied'] / $summary['total']) * 100) : 0;
        @endphp
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-slate-700">{{ __('messages.building_occupancy') }}</p>
                <p class="text-sm font-bold {{ $occupancyRate >= 90 ? 'text-rose-600' : 'text-slate-800' }}">{{ $occupancyRate }}%</p>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2.5">
                <div class="h-2.5 rounded-full transition-all {{ $occupancyRate >= 90 ? 'bg-rose-500' : 'bg-indigo-500' }}" style="width: {{ $occupancyRate }}%"></div>
            </div>
            <p class="text-xs text-slate-400 mt-2">{{ $summary['occupied'] }} occupied · {{ $summary['available'] }} available of {{ $summary['total'] }} units</p>
        </div>

        {{-- Legend --}}
        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500">
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-emerald-500"></span> {{ __('messages.available') }}</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-rose-500"></span> {{ __('messages.occupied') }}</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-slate-400"></span> {{ __('messages.maintenance') }}</span>
        </div>

        {{-- Floors --}}
        <div class="space-y-4">
            @foreach($floorsData as $floor)
                @php
                    $floorTotal = count($floor['apartments']);
                    $floorOccupied = collect($floor['apartments'])->where('status', 'occupied')->count();
                    $floorRate = $floorTotal > 0 ? round(($floorOccupied / $floorTotal) * 100) : 0;
                @endphp
                <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
                    <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/60">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-slate-700">{{ $floor['name'] ?: 'Floor' }}</h2>
                            <span class="text-xs text-slate-400">{{ $floorOccupied }}/{{ $floorTotal }} occupied · {{ $floorRate }}%</span>
                        </div>
                        @if($floorTotal > 0)
                            <div class="w-full bg-slate-200 rounded-full h-1.5 mt-2">
                                <div class="h-1.5 rounded-full {{ $floorRate >= 90 ? 'bg-rose-500' : 'bg-indigo-500' }}" style="width: {{ $floorRate }}%"></div>
                            </div>
                        @endif
                    </div>

                    @if(count($floor['apartments']) === 0)
                        <p class="px-5 py-6 text-sm text-slate-400 text-center">{{ __('messages.no_apts_this_floor') }}</p>
                    @else
                        <div class="p-4 grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-3">
                            @foreach($floor['apartments'] as $apt)
                                @php
                                    $status = $apt['status'] ?? 'maintenance';
                                    $styles = [
                                        'available'   => 'bg-emerald-500 hover:bg-emerald-600 text-white',
                                        'occupied'    => 'bg-rose-500 hover:bg-rose-600 text-white',
                                        'maintenance' => 'bg-slate-400 hover:bg-slate-500 text-white',
                                    ];
                                    $cls = $styles[$status] ?? $styles['maintenance'];
                                    $isAvailable = $status === 'available';
                                @endphp
                                @if($isAvailable)
                                <button type="button"
                                   data-apartment-id="{{ $apt['id'] }}"
                                   data-apartment-number="{{ $apt['number'] }}"
                                   title="{{ __('messages.assign_tenant_unit', ['number' => $apt['number']]) }}"
                                   class="assign-tenant-btn group relative aspect-square rounded-xl {{ $cls }} flex flex-col items-center justify-center transition shadow-sm cursor-pointer select-none ring-1 ring-inset ring-white/20 hover:ring-2 hover:ring-white/60">
                                    <span class="text-base font-bold leading-none">{{ $apt['number'] }}</span>
                                    <span class="mt-1 text-[10px] uppercase tracking-wide opacity-80 group-hover:opacity-0 transition">{{ $status }}</span>
                                    <span class="absolute inset-x-0 bottom-1 text-center text-[10px] font-semibold opacity-0 group-hover:opacity-100 transition">+ Assign</span>
                                </button>
                                @else
                                <div class="group relative aspect-square rounded-xl {{ $cls }} flex flex-col items-center justify-center transition shadow-sm cursor-default select-none">
                                    <span class="text-base font-bold leading-none">{{ $apt['number'] }}</span>
                                    <span class="mt-1 text-[10px] uppercase tracking-wide opacity-80">{{ $status }}</span>
                                    @if(!empty($apt['rent']))
                                        <span class="absolute bottom-1 inset-x-0 text-center text-[10px] opacity-0 group-hover:opacity-90 transition">
                                            ${{ number_format($apt['rent']) }}
                                        </span>
                                    @endif
                                </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

@include('admin.apartments._assign-tenant-modal')
@endsection
