{{--
    Vehicle Management — every registered vehicle laid out by floor and room.
    Shared by admin and supervisor (see Shared\VehicleController).

    Required vars:
      $panel          : 'admin' | 'supervisor'
      $groups         : [['floor' => Floors, 'rooms' => [['room','tenant','vehicles','fee']], 'vehicle_count', 'fee']]
      $unverified     : vehicles that no longer resolve to a live tenant in a room
      $totalVehicles, $totalBilled, $billableCount, $typeCounts,
      $parkingRevenue, $parkingOutstanding,
      $search, $type

    The page only reads. Every form posts to the tenant vehicle routes — the one
    write path, shared with the tenant-detail card — carrying the room it was
    drawn under so the controller can confirm the tenant is still sitting there.

    Layout follows the Active Tenants page: the search is an icon that expands,
    and each floor is a collapsible card. Collapsed is the default so a building
    fits on one screen; anything that narrows the list (search, type) or a form
    that failed validation opens the floors, or the answer would be hidden
    behind a chevron.
--}}
@extends('layouts.'.$panel)

@section('content')
@php
    // Full literal class strings (Tailwind only compiles complete tokens).
    $isSup = $panel === 'supervisor';
    $btnCls = $isSup ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-blue-600 hover:bg-blue-700';
    $ringCls = $isSup ? 'focus:ring-emerald-500 focus:border-emerald-500' : 'focus:ring-blue-500 focus:border-blue-500';
    $chipOnCls = $isSup ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-blue-600 text-white border-blue-600';
    $chipOffCls = 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50';
    $filters = array_filter([
        'search' => $search ?: null,
        'type' => $type,
    ]);
    $chipFor = fn (array $overrides) => route($panel.'.vehicles.index', array_filter(array_merge($filters, $overrides), fn ($v) => $v !== null));

    // Donut segments for the type card. One ring, one arc per type, drawn from
    // 12 o'clock — so a glance says what the fleet is made of, which three bare
    // numbers side by side never did.
    $typeTotal = array_sum($typeCounts);
    $donutColors = ['car' => '#3b82f6', 'tuktuk' => '#f59e0b', 'motorbike' => '#10b981'];
    $donutCirc = 2 * M_PI * 30; // r=30 inside the 80×80 viewBox
    $donutOffset = 0.0;

    // Which floors start open. A filtered or single-floor view has already been
    // narrowed to what the user asked for, so hiding it again helps no one.
    $openByDefault = $filters !== [] || count($groups) === 1 || old('_form') !== null;
    $initialFloors = collect($groups)->mapWithKeys(fn ($g) => [(string) $g['floor']->id => $openByDefault])->all();

    // The five vehicle columns, shared by the header strip and every row, so the
    // plate and the description each get real width instead of wrapping.
    $rowGrid = 'sm:grid sm:grid-cols-[7.5rem_10rem_minmax(0,1fr)_8rem_4.5rem] sm:items-center sm:gap-4';
@endphp

{{-- One form is open at a time across the whole page; `_form` survives a
     validation redirect so the form that failed re-opens with its errors. --}}
<div class="max-w-6xl mx-auto space-y-6"
     x-data="{
        open: @js(old('_form')),
        searchOpen: @js($search !== ''),
        floors: @js($initialFloors),
        toggleFloor(id) { this.floors[id] = ! this.floors[id]; },
        isFloorOpen(id) { return !! this.floors[id]; },
     }">

    {{-- Header — search matches the Active Tenants page: an icon that expands. --}}
    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.vehicle_management') }}</h1>
        </div>

        <form method="GET" action="{{ route($panel.'.vehicles.index') }}" class="relative flex items-center shrink-0">
            @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif

            <div x-show="searchOpen" x-transition.opacity x-cloak class="relative">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                <input type="text" name="search" value="{{ $search }}" x-ref="searchInput" placeholder="{{ __('messages.search_vehicles') }}"
                       class="w-56 sm:w-72 h-10 pl-10 pr-9 text-sm bg-white border border-slate-200 rounded-full focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                @if($search)
                    <a href="{{ $chipFor(['search' => null]) }}" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @else
                    <button type="button" x-on:click="searchOpen = false" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>
            <button type="button" x-show="!searchOpen" x-on:click="searchOpen = true; $nextTick(() => $refs.searchInput.focus())"
                    class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition" aria-label="{{ __('messages.search') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
            </button>
        </form>
    </div>

    {{-- Summary. These describe the whole property, not the filtered view.
         Registered / monthly fees / by type come off the vehicle rows (what the
         next bill run will charge); parking revenue comes off the `parking`
         charge rows (what was actually billed and collected). --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 p-5 summary-card" data-card="neutral">
            <p class="text-slate-500 text-sm">{{ __('messages.total_vehicles') }}</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $totalVehicles }}</p>
            @if($unverified->isNotEmpty())
                <a href="#vehicles-unverified" class="text-xs text-amber-600 hover:text-amber-700 mt-1 inline-block">
                    {{ __('messages.vehicles_need_attention', ['count' => $unverified->count()]) }}
                </a>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-5 summary-card" data-card="deposits">
            <p class="text-slate-500 text-sm">{{ __('messages.parking_billed_monthly') }}</p>
            <p class="text-2xl font-bold text-slate-800 mt-1 tabular-nums">{{ money($totalBilled) }}</p>
            <p class="text-xs text-slate-400 mt-1">
                {{ __('messages.vehicles_paying_free', ['paying' => $billableCount, 'free' => $totalVehicles - $billableCount]) }}
            </p>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-5 summary-card" data-card="occupancy">
            <p class="text-slate-500 text-sm">{{ __('messages.vehicles_by_type') }}</p>
            <div class="mt-2 flex items-center gap-4">
                {{-- Inline SVG, not Chart.js: three fixed slices need no library,
                     and this one also prints. --}}
                <svg viewBox="0 0 80 80" class="w-20 h-20 shrink-0" role="img"
                     aria-label="{{ __('messages.vehicles_by_type') }}">
                    <circle cx="40" cy="40" r="30" fill="none" stroke="#f1f5f9" stroke-width="12"/>
                    @if($typeTotal > 0)
                        @foreach(\App\Models\TenantVehicle::TYPES as $vt)
                            @continue($typeCounts[$vt] === 0)
                            @php
                                $arc = $donutCirc * $typeCounts[$vt] / $typeTotal;
                                $dashOffset = -$donutOffset;
                                $donutOffset += $arc;
                            @endphp
                            <circle cx="40" cy="40" r="30" fill="none" stroke="{{ $donutColors[$vt] }}" stroke-width="12"
                                    stroke-dasharray="{{ round($arc, 2) }} {{ round($donutCirc - $arc, 2) }}"
                                    stroke-dashoffset="{{ round($dashOffset, 2) }}"
                                    transform="rotate(-90 40 40)"/>
                        @endforeach
                    @endif
                    <text x="40" y="40" text-anchor="middle" dominant-baseline="central"
                          class="fill-slate-800 font-bold" style="font-size:18px">{{ $typeTotal }}</text>
                </svg>

                <ul class="min-w-0 space-y-1">
                    @foreach(\App\Models\TenantVehicle::TYPES as $vt)
                        <li class="flex items-center gap-2 text-[11px]">
                            <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $donutColors[$vt] }}"></span>
                            <span class="text-slate-500 truncate">{{ __('messages.vehicle_type_'.$vt) }}</span>
                            <span class="ml-auto font-semibold text-slate-800 tabular-nums">{{ $typeCounts[$vt] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-5 summary-card" data-card="revenue">
            <p class="text-slate-500 text-sm">{{ __('messages.parking_revenue_this_month') }}</p>
            <p class="text-2xl font-bold text-slate-800 mt-1 tabular-nums">{{ money($parkingRevenue) }}</p>
            @if($parkingOutstanding > 0)
                <p class="text-xs text-amber-600 mt-1 tabular-nums">
                    {{ __('messages.amount_outstanding', ['amount' => money($parkingOutstanding)]) }}
                </p>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-slate-100 px-4 sm:px-5 py-4 flex flex-wrap items-center gap-2">
        <a href="{{ $chipFor(['type' => null]) }}" class="px-3 py-1.5 rounded-full border text-sm font-medium transition {{ $type === null ? $chipOnCls : $chipOffCls }}">{{ __('messages.all') }}</a>
        @foreach(\App\Models\TenantVehicle::TYPES as $vt)
            <a href="{{ $chipFor(['type' => $vt]) }}" class="px-3 py-1.5 rounded-full border text-sm font-medium transition {{ $type === $vt ? $chipOnCls : $chipOffCls }}">{{ __('messages.vehicle_type_'.$vt) }}</a>
        @endforeach

        @if($filters)
        <a href="{{ route($panel.'.vehicles.index') }}" class="ml-auto text-sm text-slate-500 hover:text-slate-700 underline">{{ __('messages.clear_filters') }}</a>
        @endif
    </div>

    {{-- Floors → rooms → vehicles --}}
    @forelse($groups as $group)
        @php $floor = $group['floor']; @endphp
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden hover:border-slate-200 transition">
            {{-- Floor summary (click toggles its rooms) --}}
            <div x-on:click="toggleFloor('{{ $floor->id }}')"
                 class="flex items-center justify-between gap-3 cursor-pointer px-4 sm:px-6 py-4 hover:bg-slate-50/50 transition select-none">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold text-slate-800 truncate">{{ $floor->floor_name }}</h2>
                        @if($floor->property)
                        <p class="text-xs text-slate-400 truncate">{{ $floor->property->name }}</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-3 sm:gap-4 flex-shrink-0">
                    <span class="text-xs sm:text-sm text-slate-500">{{ trans_choice('messages.vehicle_count', $group['vehicle_count'], ['count' => $group['vehicle_count']]) }}</span>
                    @if($group['fee'] > 0)
                    <span class="text-xs sm:text-sm font-semibold text-slate-800 tabular-nums">{{ money($group['fee']) }}/{{ __('messages.mo') }}</span>
                    @endif
                    <svg class="w-4 h-4 text-slate-400 transition-transform flex-shrink-0" :class="isFloorOpen('{{ $floor->id }}') ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>
            </div>

            <ul x-show="isFloorOpen('{{ $floor->id }}')" x-cloak class="border-t border-slate-50 divide-y divide-slate-100">
                @foreach($group['rooms'] as $entry)
                    @php
                        $room = $entry['room'];
                        $tenant = $entry['tenant'];
                        $addForm = 'add-'.$room->id;
                    @endphp
                    <li class="px-4 sm:px-6 py-4">
                        {{-- Room + its sitting tenant: the pair a vehicle is verified against --}}
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="inline-flex items-center justify-center min-w-[3rem] px-2.5 h-8 rounded-lg bg-slate-100 text-slate-700 text-sm font-semibold">{{ $room->apartment_number }}</span>
                                @if($tenant)
                                    <a href="{{ route($panel.'.tenants.show', $tenant->id) }}" class="text-sm font-medium text-slate-800 hover:underline truncate">{{ $tenant->name }}</a>
                                @else
                                    <span class="text-sm text-slate-400">{{ __('messages.no_tenant') }}</span>
                                @endif
                            </div>

                            @if($tenant)
                            {{-- Icon only: one of these sits on every room row, so
                                 the label was repeated down the whole page. --}}
                            <button type="button" x-on:click="open = (open === @js($addForm) ? null : @js($addForm))"
                                    class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition shrink-0"
                                    title="{{ __('messages.add_vehicle') }}" aria-label="{{ __('messages.add_vehicle') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            </button>
                            @endif
                        </div>

                        {{-- Add form — bound to this room and its tenant --}}
                        @if($tenant)
                        <form x-cloak x-show="open === @js($addForm)" method="POST"
                              action="{{ route($panel.'.tenants.vehicles.store', $tenant->id) }}" class="mt-3">
                            @csrf
                            <input type="hidden" name="redirect_to" value="vehicles">
                            <input type="hidden" name="_form" value="{{ $addForm }}">
                            <input type="hidden" name="apartment_id" value="{{ $room->id }}">
                            @include('shared.vehicles._fields', ['ringCls' => $ringCls, 'btnCls' => $btnCls, 'vehicle' => null, 'formId' => $addForm])
                        </form>
                        @endif

                        {{-- Registered vehicles, one column each so the plate and the
                             description are readable at a glance. --}}
                        @if($entry['vehicles']->isEmpty())
                            <p class="mt-3 text-sm text-slate-400">{{ __('messages.no_vehicles') }}</p>
                        @else
                        <div class="mt-3 border border-slate-100 rounded-lg overflow-hidden">
                            <div class="hidden {{ $rowGrid }} bg-slate-50/80 px-3 py-2 text-[11px] font-medium text-slate-400 uppercase tracking-wider">
                                <span>{{ __('messages.type') }}</span>
                                <span>{{ __('messages.plate_no') }}</span>
                                <span>{{ __('messages.vehicle_info') }}</span>
                                <span class="text-right">{{ __('messages.fee_per_month') }}</span>
                                <span class="text-right">{{ __('messages.actions') }}</span>
                            </div>
                            <ul class="divide-y divide-slate-100">
                                @foreach($entry['vehicles'] as $vehicle)
                                    @php $editForm = 'edit-'.$vehicle->id; @endphp
                                    <li class="px-3 py-2.5">
                                        <div x-show="open !== @js($editForm)" class="flex flex-wrap items-center gap-x-3 gap-y-1 {{ $rowGrid }}">
                                            <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600 w-max">{{ __('messages.vehicle_type_'.$vehicle->vehicle_type) }}</span>
                                            <span class="text-sm font-semibold text-slate-800 tracking-wide truncate">{{ $vehicle->plate_number }}</span>
                                            <span class="text-sm text-slate-500 truncate">{{ $vehicle->vehicle_model ?: '—' }}</span>
                                            <span class="text-sm tabular-nums sm:text-right {{ $vehicle->isBillable() ? 'font-semibold text-slate-800' : 'text-slate-400' }}">
                                                {{ $vehicle->isBillable() ? money($vehicle->monthly_fee).'/'.__('messages.mo') : __('messages.no_charge') }}
                                            </span>
                                            <div class="flex items-center gap-1 shrink-0 ml-auto sm:ml-0 sm:justify-end">
                                                <button type="button" x-on:click="open = @js($editForm)"
                                                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-50 transition" title="{{ __('messages.edit') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                </button>
                                                <form action="{{ route($panel.'.tenants.vehicles.destroy', [$vehicle->tenant_id, $vehicle->id]) }}" method="POST"
                                                      data-confirm="{{ __('messages.remove_vehicle_confirm', ['plate' => $vehicle->plate_number]) }}">
                                                    @csrf @method('DELETE')
                                                    <input type="hidden" name="redirect_to" value="vehicles">
                                                    <button type="submit" class="p-1.5 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 transition" title="{{ __('messages.delete') }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>

                                        {{-- Edit form — same write path, PUT --}}
                                        <form x-cloak x-show="open === @js($editForm)" method="POST"
                                              action="{{ route($panel.'.tenants.vehicles.update', [$vehicle->tenant_id, $vehicle->id]) }}">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="redirect_to" value="vehicles">
                                            <input type="hidden" name="_form" value="{{ $editForm }}">
                                            <input type="hidden" name="apartment_id" value="{{ $room->id }}">
                                            @include('shared.vehicles._fields', ['ringCls' => $ringCls, 'btnCls' => $btnCls, 'vehicle' => $vehicle, 'formId' => $editForm])
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-slate-100 p-10 text-center">
            <svg class="w-10 h-10 mx-auto text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 17h8m-9-4h10l-1.2-3.6A2 2 0 0014.9 8H9.1a2 2 0 00-1.9 1.4L6 13zm-1 0h12a1 1 0 011 1v3H5v-3a1 1 0 011-1z"/></svg>
            <p class="mt-3 text-sm text-slate-500">{{ $filters ? __('messages.no_vehicles_match') : __('messages.no_vehicles_yet') }}</p>
        </div>
    @endforelse

    {{-- Verification: vehicles that no longer resolve to a live tenant in a
         room. A tenant who moves out is soft-deleted, so their vehicles stay
         behind with nowhere to park and no other screen that would show them. --}}
    @if($unverified->isNotEmpty())
    <div id="vehicles-unverified" class="bg-white rounded-xl border border-amber-200 overflow-hidden scroll-mt-6">
        <div class="px-4 sm:px-6 py-4 border-b border-amber-100 bg-amber-50/60 flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <div>
                <h2 class="text-sm font-semibold text-amber-800">{{ __('messages.vehicles_unverified') }}</h2>
                <p class="text-xs text-amber-700 mt-0.5">{{ __('messages.vehicles_unverified_hint') }}</p>
            </div>
        </div>
        <ul class="divide-y divide-slate-100">
            @foreach($unverified as $vehicle)
            <li class="px-4 sm:px-6 py-3 flex flex-wrap items-center gap-x-3 gap-y-1">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600 shrink-0">{{ __('messages.vehicle_type_'.$vehicle->vehicle_type) }}</span>
                <span class="text-sm font-semibold text-slate-800 tracking-wide">{{ $vehicle->plate_number }}</span>
                <span class="text-sm text-slate-500 truncate">{{ $vehicle->vehicle_model ?: '—' }}</span>
                <span class="text-xs text-amber-600">
                    {{ $vehicle->tenant === null ? __('messages.vehicle_tenant_departed') : __('messages.vehicle_no_room', ['name' => $vehicle->tenant->name]) }}
                </span>
                <span class="ml-auto text-sm tabular-nums {{ $vehicle->isBillable() ? 'font-semibold text-slate-800' : 'text-slate-400' }}">
                    {{ $vehicle->isBillable() ? money($vehicle->monthly_fee).'/'.__('messages.mo') : __('messages.no_charge') }}
                </span>
                <form action="{{ route($panel.'.tenants.vehicles.destroy', [$vehicle->tenant_id, $vehicle->id]) }}" method="POST"
                      data-confirm="{{ __('messages.remove_vehicle_confirm', ['plate' => $vehicle->plate_number]) }}" class="shrink-0">
                    @csrf @method('DELETE')
                    <input type="hidden" name="redirect_to" value="vehicles">
                    <button type="submit" class="p-1.5 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 transition" title="{{ __('messages.delete') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                    </button>
                </form>
            </li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
@endsection
