@extends('layouts.admin')

@section('content')
<div x-data="tenantFilter()" class="max-w-6xl mx-auto space-y-8">
        <!-- Header Section -->
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.active_tenants') }}</h1>

            <!-- Search (icon → expands to input) -->
            <div class="relative flex items-center">
                <div x-show="searchOpen" x-transition.opacity x-cloak class="relative">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                    <input type="text" x-model="searchQuery" x-ref="searchInput" placeholder="{{ __('messages.search_tenant_apartment') }}"
                        class="w-56 sm:w-64 h-10 pl-10 pr-9 text-sm bg-white border border-slate-200 rounded-full focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                    <button type="button" @click="searchQuery = ''; searchOpen = false" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <button type="button" x-show="!searchOpen" @click="searchOpen = true; $nextTick(() => $refs.searchInput.focus())"
                    class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition" aria-label="{{ __('messages.search') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                </button>
            </div>
        </div>

        <!-- Statistics Section -->
        <div class="grid grid-cols-2 gap-4 sm:gap-6">
            <div class="bg-white rounded-xl border border-slate-100 p-5 sm:p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-slate-50">
                        <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-slate-500 text-sm">{{ __('messages.active_tenants') }}</p>
                        <p id="activeTenants" class="text-2xl font-bold text-slate-800">{{ $activeTenantCount }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-5 sm:p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-slate-50">
                        <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-slate-500 text-sm">{{ __('messages.archived_tenants') }}</p>
                        <p id="archivedTenants" class="text-2xl font-bold text-slate-800">{{ $archivedTenantCount }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar — server-side links so the filter spans every page, not
             just the visible one (the list is paginated server-side). -->
        @php $activeRentStatus = request('rent_status'); @endphp
        <div class="bg-white rounded-xl border border-slate-100 p-3 md:p-4 flex flex-wrap items-center gap-2">
            <a href="{{ request()->fullUrlWithQuery(['rent_status' => null, 'page' => null]) }}"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ ! $activeRentStatus ? 'bg-slate-800 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' }}">{{ __('messages.all') }}</a>
            <a href="{{ request()->fullUrlWithQuery(['rent_status' => 'paid', 'page' => null]) }}"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ $activeRentStatus === 'paid' ? 'bg-emerald-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' }}">{{ __('messages.paid') }}</a>
            <a href="{{ request()->fullUrlWithQuery(['rent_status' => 'overdue', 'page' => null]) }}"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ $activeRentStatus === 'overdue' ? 'bg-red-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' }}">{{ __('messages.overdue') }}</a>
            <a href="{{ request()->fullUrlWithQuery(['rent_status' => 'unpaid', 'page' => null]) }}"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition {{ $activeRentStatus === 'unpaid' ? 'bg-gray-800 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' }}">{{ __('messages.unpaid') }}</a>
        </div>

        {{-- Tenants grouped by property, then into collapsible floor cards (styled like
             Billing & Payment's record_income). Each floor's "No." restarts at 1. --}}
        @php
            $byProperty = collect($tenants->items())->groupBy(fn ($t) => $t->apartment?->floor?->property_id ?? 0);
            $multipleProperties = $showingAll && $byProperty->count() > 1;
        @endphp
        @if(count($tenants) > 0)
        <div class="space-y-5">
            @foreach($byProperty as $propertyId => $propertyTenants)
                @if($multipleProperties)
                @php $propertyName = $propertyTenants->first()->apartment?->floor?->property?->name ?? __('messages.property'); @endphp
                <div x-show="floorHasMatch({{ Illuminate\Support\Js::from($propertyTenants->map(fn($t) => ['status' => $rentProgressMap[$t->id]['status'] ?? 'unknown', 'name' => strtolower($t->name ?? ''), 'apt' => strtolower($t->apartment?->apartment_number ?? '')])->values()) }})"
                     class="flex items-center gap-2.5 pt-3 pb-1 px-1">
                    <div class="w-8 h-8 rounded-lg bg-sky-50 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21"/></svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-700 truncate">{{ $propertyName }}</h3>
                </div>
                @endif
                @php $byFloor = $propertyTenants->groupBy(fn ($t) => $t->apartment?->floor?->id ?? 0); @endphp
                @foreach($byFloor as $floorId => $floorTenants)
                @php
                    $groupFloor = $floorTenants->first()->apartment?->floor;
                    $floorStatuses = $floorTenants->map(fn ($t) => $rentProgressMap[$t->id]['status'] ?? 'unknown');
                    $flPaid = $floorStatuses->filter(fn ($s) => $s === 'paid')->count();
                    $flOverdue = $floorStatuses->filter(fn ($s) => $s === 'overdue')->count();
                    $flOther = $floorTenants->count() - $flPaid - $flOverdue;
                    $rowNum = 1;
                    $mRowNum = 1;
                    $floorMatchJs = Illuminate\Support\Js::from($floorTenants->map(fn($t) => ['status' => $rentProgressMap[$t->id]['status'] ?? 'unknown', 'name' => strtolower($t->name ?? ''), 'apt' => strtolower($t->apartment?->apartment_number ?? '')])->values());
                @endphp
                <div x-show="floorHasMatch({{ $floorMatchJs }})"
                     class="bg-white rounded-xl border border-slate-100 overflow-hidden hover:border-slate-200 transition">
                    <!-- Floor summary (click toggles rooms) -->
                    <div @click="toggleFloor('{{ $floorId }}')"
                         class="flex items-center justify-between gap-3 cursor-pointer px-4 md:px-6 py-4 hover:bg-slate-50/50 transition select-none">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" /></svg>
                            </div>
                            <h3 class="text-base font-semibold text-slate-800 truncate">{{ $groupFloor?->floor_name ?? __('messages.no_col') }}</h3>
                        </div>
                        <div class="flex items-center gap-3 md:gap-4 flex-shrink-0">
                            <div class="flex items-center gap-1.5" title="{{ __('messages.total') }}">
                                <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                                <span class="text-xs font-semibold text-slate-700">{{ $floorTenants->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1.5" title="{{ __('messages.paid') }}">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                <span class="text-xs font-semibold text-emerald-600">{{ $flPaid }}</span>
                            </div>
                            <div class="flex items-center gap-1.5" title="{{ __('messages.overdue') }}">
                                <span class="w-2 h-2 rounded-full bg-red-400"></span>
                                <span class="text-xs font-semibold text-red-600">{{ $flOverdue }}</span>
                            </div>
                            <div class="flex items-center gap-1.5" title="{{ __('messages.unpaid') }}">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                <span class="text-xs font-semibold text-amber-600">{{ $flOther }}</span>
                            </div>
                            <svg class="w-4 h-4 text-slate-400 transition-transform flex-shrink-0" :class="isFloorOpen('{{ $floorId }}') ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                        </div>
                    </div>

                    <!-- Tenants table (desktop) -->
                    <div x-show="isFloorOpen('{{ $floorId }}')" x-cloak class="hidden md:block overflow-x-auto border-t border-slate-50">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-slate-50/80">
                                    <th class="w-12 px-4 lg:px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.no_col') }}</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.tenant_name') }}</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.floor_apartment') }}</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.progress') }}</th>
                                    <th class="px-4 lg:px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.status') }}</th>
                                    <th class="px-4 lg:px-6 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach($floorTenants as $tenant)
                                @php $rp = $rentProgressMap[$tenant->id] ?? null; $status = $rp['status'] ?? 'unknown'; @endphp
                                <tr x-show="isFloorOpen('{{ $floorId }}') && matchesFilter('{{ $status }}','{{ strtolower($tenant->name ?? '') }}','{{ strtolower($tenant->apartment?->apartment_number ?? '') }}','{{ $rp['cycle_percent'] ?? 0 }}')" class="hover:bg-gray-50 transition">
                                    <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $rowNum++ }}</td>
                                    <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            {{-- Initials sit underneath; the photo overlays them and, if its file is
                                                 missing (e.g. not migrated to this host), onerror reveals the initials. --}}
                                            <div class="relative h-10 w-10 rounded-full bg-blue-100 border border-gray-300 overflow-hidden flex items-center justify-center flex-shrink-0">
                                                <span class="text-blue-600 font-semibold text-sm">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                                                @if($tenant->photo_path && !str_ends_with($tenant->photo_path, '.pdf'))
                                                    <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}" class="absolute inset-0 h-full w-full object-cover" onerror="this.style.display='none'">
                                                @endif
                                            </div>
                                            <div class="ml-4">
                                                <p class="font-medium text-gray-900">{{ $tenant->name }}</p>
                                                <p class="text-sm text-gray-500">{{ $tenant->user_id ? __('messages.linked') : __('messages.not_linked') }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $tenant->apartment?->floor?->floor_name ?? 'N/A' }} / {{ $tenant->apartment?->apartment_number ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                        @if($rp)
                                        @php
                                            // Progress tracks the tenant's monthly rent cycle from their
                                            // stay/move-in date (renews on the monthly anniversary), not the
                                            // calendar month. Source: Rentals::stayProgress().
                                            $dp = $rp['cycle_percent'] ?? 0;
                                            $daysRemaining = $rp['days_left'] ?? 0;
                                        @endphp
                                        <div class="w-28">
                                            <div class="w-full bg-slate-200 rounded-full h-1.5">
                                                <div class="h-1.5 rounded-full {{ $dp > 75 ? 'bg-amber-500' : 'bg-sky-500' }}" style="width: {{ $dp }}%"></div>
                                            </div>
                                            <p class="text-xs {{ $daysRemaining <= 5 ? 'text-amber-500' : 'text-sky-500' }} font-medium mt-0.5">
                                                {{ __('messages.days_left', ['days' => $daysRemaining]) }}
                                            </p>
                                        </div>
                                        @else
                                        <span class="text-[10px] text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 lg:px-6 py-4 whitespace-nowrap">
                                        @if($status === 'paid')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-emerald-100 text-emerald-700">{{ __('messages.paid') }}</span>
                                        @elseif($status === 'partial')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-yellow-100 text-yellow-700">{{ __('messages.paying') }}</span>
                                        @elseif($status === 'overdue')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-red-100 text-red-700">{{ __('messages.overdue') }}</span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-gray-100 text-gray-700">{{ __('messages.unpaid') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.tenants.show', $tenant->id) }}" title="{{ __('messages.view_details') }}" class="inline-flex items-center justify-center h-8 w-8 rounded-md text-sky-600 bg-sky-50 hover:bg-sky-100 transition" aria-label="View">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Tenants list (mobile) -->
                    <div x-show="isFloorOpen('{{ $floorId }}')" x-cloak class="md:hidden border-t border-slate-50 divide-y divide-slate-100">
                        @foreach($floorTenants as $tenant)
                        @php
                            $rp = $rentProgressMap[$tenant->id] ?? null;
                            $status = $rp['status'] ?? 'unknown';
                            $dp = $rp['cycle_percent'] ?? 0;
                        @endphp
                        <div x-show="isFloorOpen('{{ $floorId }}') && matchesFilter('{{ $status }}','{{ strtolower($tenant->name ?? '') }}','{{ strtolower($tenant->apartment?->apartment_number ?? '') }}','{{ $dp }}')"
                             class="flex items-center gap-3 px-4 py-2.5 active:bg-slate-50 transition">
                            <span class="w-5 text-xs font-medium text-slate-400 text-center flex-shrink-0">{{ $mRowNum++ }}</span>
                            <!-- Avatar -->
                            <div class="relative h-9 w-9 rounded-full bg-blue-100 border border-gray-200 overflow-hidden flex items-center justify-center flex-shrink-0">
                                <span class="text-blue-600 font-semibold text-sm">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                                @if($tenant->photo_path && !str_ends_with($tenant->photo_path, '.pdf'))
                                    <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}" class="absolute inset-0 h-full w-full object-cover" onerror="this.style.display='none'">
                                @endif
                            </div>
                            <!-- Name + floor/apartment -->
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900 text-sm truncate">{{ $tenant->name }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $tenant->apartment?->floor?->floor_name ?? 'N/A' }} / {{ $tenant->apartment?->apartment_number ?? 'N/A' }}</p>
                            </div>
                            <!-- Status badge -->
                            @if($status === 'paid')
                                <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded bg-emerald-100 text-emerald-700 flex-shrink-0">{{ __('messages.paid') }}</span>
                            @elseif($status === 'partial')
                                <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded bg-yellow-100 text-yellow-700 flex-shrink-0">{{ __('messages.paying') }}</span>
                            @elseif($status === 'overdue')
                                <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded bg-red-100 text-red-700 flex-shrink-0">{{ __('messages.overdue') }}</span>
                            @else
                                <span class="px-1.5 py-0.5 text-[10px] font-semibold rounded bg-gray-100 text-gray-700 flex-shrink-0">{{ __('messages.unpaid') }}</span>
                            @endif
                            <!-- Actions -->
                            <div class="flex items-center gap-1 flex-shrink-0">
                                <a href="{{ route('admin.tenants.show', $tenant->id) }}" title="{{ __('messages.view_details') }}" class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-sky-700 bg-sky-50 active:bg-sky-100 transition" aria-label="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            @endforeach
        </div>
        @else
        <div class="bg-white rounded-xl border border-slate-100 p-8 text-center text-gray-500">{{ __('messages.no_tenants_found') }}</div>
        @endif

        <!-- Pagination -->
        @if($tenants->hasPages())
        <div class="bg-white rounded-xl border border-slate-100 px-6 py-4">
            {{ $tenants->withQueryString()->links() }}
        </div>
        @endif
</div>

<script>
function tenantFilter(){
    return {
        filter: 'all',
        searchOpen: false,
        searchQuery: '',
        // Collapsible floor cards (accordion). Default collapsed; search auto-expands.
        openFloors: {},
        toggleFloor(id) { this.openFloors[id] = !this.openFloors[id]; },
        isFloorOpen(id) {
            if (this.searchQuery) return true;
            return !!this.openFloors[id];
        },
        matchesFilter(status, name, apartment, dayPercent){
            const q = (this.searchQuery || '').trim().toLowerCase();
            if(q && !((name || '').toLowerCase().includes(q) || (apartment || '').toLowerCase().includes(q))) return false;
            if(this.filter === 'all') return true;
            if(this.filter === 'paid') return status === 'paid';
            if(this.filter === 'overdue') return status === 'overdue';
            if(this.filter === 'unpaid') return status === 'unpaid';
            return true;
        },
        // Hide a floor/property header when none of its tenants match the active filter/search.
        floorHasMatch(items){
            return items.some(it => this.matchesFilter(it.status, it.name, it.apt, 0));
        }
    }
}
</script>

@endsection
