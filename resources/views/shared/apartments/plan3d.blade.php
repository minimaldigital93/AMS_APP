@extends('layouts.'.$panel)

@section('title', __('messages.floor_layout'))

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.floor_layout') }}</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route($panel.'.revenue_expense.record_income') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-sm" title="{{ __('messages.record_income') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></a>
            <a href="{{ route($panel.'.revenue_expense.record_expense') }}"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white bg-rose-600 hover:bg-rose-700 transition shadow-sm" title="{{ __('messages.record_expense') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg></a>
            <a href="{{ route($panel.'.dashboard') }}"
               class="inline-flex items-center justify-center h-10 w-10 bg-slate-800 hover:bg-slate-700 text-white rounded-lg transition flex-shrink-0" title="{{ __('messages.back') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        </div>
    </div>

    {{-- Summary chips --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-slate-100 p-4">
            <p class="text-xs text-slate-400 font-medium">{{ __('messages.floors') }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ $summary['floors'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4">
            <p class="text-xs text-slate-400 font-medium">{{ __('messages.total_units') }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ $summary['total'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4">
            <p class="text-xs text-slate-400 font-medium">{{ __('messages.available') }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ $summary['available'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4">
            <p class="text-xs text-slate-400 font-medium">{{ __('messages.occupied') }}</p>
            <p class="text-2xl font-bold text-slate-800">{{ $summary['occupied'] }}</p>
        </div>
    </div>

    @if($summary['floors'] === 0)
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-center">
            <p class="text-yellow-800 font-medium">{{ __('messages.no_floors_yet') }}</p>
            @if($panel === 'admin')
                <a href="{{ route('admin.floors.create') }}" class="inline-block mt-3 bg-yellow-600 text-white px-5 py-2 rounded-lg hover:bg-yellow-700 transition text-sm font-medium">{{ __('messages.create_a_floor') }}</a>
            @endif
        </div>
    @else
        {{-- Overall occupancy progress --}}
        @php
            $occupancyRate = $summary['total'] > 0 ? round(($summary['occupied'] / $summary['total']) * 100) : 0;
            $availablePct = $summary['total'] > 0 ? ($summary['available'] / $summary['total']) * 100 : 0;
            $occupiedPct = $summary['total'] > 0 ? ($summary['occupied'] / $summary['total']) * 100 : 0;
        @endphp
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm font-semibold text-slate-700">{{ __('messages.building_occupancy') }}</p>
                <p class="text-sm font-bold {{ $occupancyRate >= 90 ? 'text-rose-600' : 'text-slate-800' }}">{{ $occupancyRate }}%</p>
            </div>
            <div class="w-full flex bg-slate-100 rounded-full h-2.5 overflow-hidden">
                <div class="h-2.5 transition-all bg-emerald-500" style="width: {{ $availablePct }}%"></div>
                <div class="h-2.5 transition-all bg-blue-500" style="width: {{ $occupiedPct }}%"></div>
            </div>
            <p class="text-xs text-slate-400 mt-2">{{ $summary['occupied'] }} occupied · {{ $summary['available'] }} available of {{ $summary['total'] }} units</p>
        </div>

        {{-- Legend (dot style) --}}
        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-500">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> {{ __('messages.available') }}</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> {{ __('messages.occupied') }}</span>
        </div>

        {{-- Floors (collapsible dropdowns, list view) --}}
        <div class="space-y-4">
            @foreach($floorsData as $floor)
                @php
                    $floorTotal = count($floor['apartments']);
                    $floorOccupied = collect($floor['apartments'])->where('status', 'occupied')->count();
                    $floorRate = $floorTotal > 0 ? round(($floorOccupied / $floorTotal) * 100) : 0;
                @endphp
                <div x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
                    {{-- Dropdown header --}}
                    <button type="button" @click="open = !open"
                            class="w-full px-5 py-3 border-b border-slate-100 bg-slate-50/60 text-left hover:bg-slate-100/60 transition">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <svg class="w-4 h-4 text-slate-400 transition-transform shrink-0" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <h2 class="text-sm font-semibold text-slate-700 truncate">{{ $floor['name'] ?: 'Floor' }}</h2>
                            </div>
                            <span class="text-xs text-slate-400 shrink-0">{{ $floorOccupied }}/{{ $floorTotal }} {{ __('messages.occupied') }} · {{ $floorRate }}%</span>
                        </div>
                        @if($floorTotal > 0)
                            <div class="w-full bg-slate-200 rounded-full h-1.5 mt-2">
                                <div class="h-1.5 rounded-full bg-blue-500" style="width: {{ $floorRate }}%"></div>
                            </div>
                        @endif
                    </button>

                    {{-- Dropdown body --}}
                    <div x-show="open" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0">
                        @if(count($floor['apartments']) === 0)
                            <p class="px-5 py-6 text-sm text-slate-400 text-center">{{ __('messages.no_apts_this_floor') }}</p>
                        @else
                            <div class="grid grid-cols-4 gap-3 p-4">
                                @foreach($floor['apartments'] as $apt)
                                    @php
                                        $status = $apt['status'] ?? 'available';
                                        $dots = [
                                            'available'   => 'bg-emerald-500',
                                            'occupied'    => 'bg-blue-500',
                                        ];
                                        $dot = $dots[$status] ?? 'bg-slate-400';
                                        $borderColors = [
                                            'available'   => 'border-emerald-200',
                                            'occupied'    => 'border-blue-200',
                                        ];
                                        $border = $borderColors[$status] ?? 'border-slate-200';
                                        $isAvailable = $status === 'available';
                                    @endphp
                                    <div class="relative rounded-xl border {{ $border }} bg-white p-3 flex flex-col gap-3 hover:shadow-sm transition">
                                        {{-- Status dot --}}
                                        <span class="absolute top-2 right-2 w-2 h-2 rounded-full {{ $dot }}" title="{{ $status }}"></span>

                                        {{-- Unit number --}}
                                        <span class="text-sm font-bold text-slate-700 leading-tight pr-6">{{ $apt['number'] }}</span>

                                        {{-- Occupied → whole card links to the tenant's detail --}}
                                        @if(!$isAvailable && !empty($apt['tenant_id']))
                                            <a href="{{ route($panel.'.tenants.show', $apt['tenant_id']) }}"
                                               class="absolute inset-0 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-300"
                                               title="{{ __('messages.view') }}: {{ $apt['tenant'] }}">
                                                <span class="sr-only">{{ $apt['tenant'] }}</span>
                                            </a>
                                        @endif

                                        {{-- Stay duration (half-donut: monthly cycle progress, total stay in centre) --}}
                                        @if(!empty($apt['stay_label']))
                                            <div class="mt-1 flex flex-col items-center">
                                                <x-stay-gauge
                                                    :percent="$apt['cycle_percent']"
                                                    :label="$apt['stay_label']"
                                                    :size="64"
                                                    :tip="__('messages.stay_duration').': '.$apt['stay_label'].' · '.__('messages.renews_on', ['date' => $apt['next_renewal_label']])" />
                                            </div>
                                        @endif

                                        {{-- Assign button (centered) --}}
                                        @if($isAvailable)
                                            <div class="flex-1 flex items-center justify-center">
                                                <button type="button"
                                                        data-apartment-id="{{ $apt['id'] }}"
                                                        data-apartment-number="{{ $apt['number'] }}"
                                                        title="{{ __('messages.assign_tenant_unit', ['number' => $apt['number']]) }}"
                                                        class="assign-tenant-btn inline-flex items-center justify-center w-11 h-11 rounded-full text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@include('shared.apartments._assign-tenant-modal')
@endsection
