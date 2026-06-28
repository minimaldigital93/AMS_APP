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

        <!-- Tenants Table -->
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
            <!-- Filter Bar -->
            <div class="px-4 sm:px-6 py-4 border-b border-slate-100 flex flex-wrap items-center gap-2">
                <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition">{{ __('messages.all') }}</button>
                <button @click="filter = 'paid'" :class="filter === 'paid' ? 'bg-emerald-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition">{{ __('messages.paid') }}</button>
                <button @click="filter = 'overdue'" :class="filter === 'overdue' ? 'bg-red-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition">{{ __('messages.overdue') }}</button>
                <button @click="filter = 'unpaid'" :class="filter === 'unpaid' ? 'bg-gray-800 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition">{{ __('messages.unpaid') }}</button>

                <!-- Property + Floor dropdowns (server-side filters) -->
                <div class="ms-auto flex items-center gap-2">
                    {{-- Property filter: only when the top-bar is on "All properties". --}}
                    @if($showingAll && $properties->count() > 1)
                    <select onchange="window.location.href = this.value"
                        class="h-9 pl-3 pr-8 text-sm bg-slate-50 border border-slate-200 rounded-lg text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-slate-300 cursor-pointer"
                        aria-label="{{ __('messages.filter_by_property') }}" title="{{ __('messages.filter_by_property') }}">
                        <option value="{{ request()->fullUrlWithQuery(['property' => null, 'floor' => null, 'page' => null]) }}" @selected(!$selectedPropertyId)>{{ __('messages.all_properties') }}</option>
                        @foreach($properties as $prop)
                            <option value="{{ request()->fullUrlWithQuery(['property' => $prop->id, 'floor' => null, 'page' => null]) }}" @selected($selectedPropertyId === $prop->id)>{{ $prop->name }}</option>
                        @endforeach
                    </select>
                    @endif
                    <select onchange="window.location.href = this.value"
                        class="h-9 pl-3 pr-8 text-sm bg-slate-50 border border-slate-200 rounded-lg text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-slate-300 cursor-pointer">
                        <option value="{{ request()->fullUrlWithQuery(['floor' => null, 'page' => null]) }}" @selected(!request()->filled('floor'))>{{ __('messages.all_floors') }}</option>
                        @foreach($floors as $floor)
                            <option value="{{ request()->fullUrlWithQuery(['floor' => $floor->id, 'page' => null]) }}" @selected(request('floor') == $floor->id)>{{ $floor->floor_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <!-- Desktop table (hidden on mobile) -->
            <div class="hidden md:block p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.no_col') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.tenant_name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.floor_apartment') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.progress') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.status') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($tenants as $tenant)
                            @php $rp = $rentProgressMap[$tenant->id] ?? null; $status = $rp['status'] ?? 'unknown'; @endphp
                            <tr x-show="matchesFilter('{{ $status }}','{{ strtolower($tenant->name ?? '') }}','{{ strtolower($tenant->apartment?->apartment_number ?? '') }}','{{ $rp['day_percent'] ?? 0 }}')" class="hover:bg-gray-50 transition">
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $tenants->firstItem() ? $tenants->firstItem() + $loop->index : $loop->iteration }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $tenant->floor?->floor_name ?? ($tenant->apartment?->floor?->floor_name ?? 'N/A') }} / {{ $tenant->apartment?->apartment_number ?? 'N/A' }}
                                    @if($showingAll && $tenant->apartment?->floor?->property)
                                    <span class="block text-xs text-slate-400">{{ $tenant->apartment->floor->property->name }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php $rp = $rentProgressMap[$tenant->id] ?? null; @endphp
                                    @if($rp)
                                    @php
                                        $dp = $rp['day_percent'];
                                        $totalDays = $rp['total_days'] ?? 30;
                                        $daysStayed = $rp['days_stayed'] ?? 0;
                                        $daysRemaining = max(0, $totalDays - $daysStayed);
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

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if(isset($rp))
                                        @php
                                            $status = $rp['status'] ?? null;
                                            $dayPercent = $rp['day_percent'] ?? 0;
                                        @endphp
                                        @if($status === 'paid')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-emerald-100 text-emerald-700">{{ __('messages.paid') }}</span>
                                        @elseif($status === 'partial')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-yellow-100 text-yellow-700">{{ __('messages.paying') }}</span>
                                        @elseif($status === 'overdue')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-red-100 text-red-700">{{ __('messages.overdue') }}</span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-gray-100 text-gray-700">{{ __('messages.unpaid') }}</span>
                                        @endif
                                    @else
                                        <span class="text-[10px] text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
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
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">{{ __('messages.no_tenants_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Mobile compact list (one tenant per row, shown on mobile only) -->
            <div class="md:hidden divide-y divide-slate-100">
                @forelse ($tenants as $tenant)
                    @php
                        $rp = $rentProgressMap[$tenant->id] ?? null;
                        $status = $rp['status'] ?? 'unknown';
                        $dp = $rp['day_percent'] ?? 0;
                        $totalDays = $rp['total_days'] ?? 30;
                        $daysStayed = $rp['days_stayed'] ?? 0;
                        $daysRemaining = max(0, $totalDays - $daysStayed);
                    @endphp
                    <div x-show="matchesFilter('{{ $status }}','{{ strtolower($tenant->name ?? '') }}','{{ strtolower($tenant->apartment?->apartment_number ?? '') }}','{{ $dp }}')"
                         class="flex items-center gap-3 px-4 py-2.5 active:bg-slate-50 transition">
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
                            <p class="text-xs text-gray-500 truncate">{{ $tenant->floor?->floor_name ?? ($tenant->apartment?->floor?->floor_name ?? 'N/A') }} / {{ $tenant->apartment?->apartment_number ?? 'N/A' }}</p>
                            @if($showingAll && $tenant->apartment?->floor?->property)
                            <p class="text-xs text-slate-400 truncate">{{ $tenant->apartment->floor->property->name }}</p>
                            @endif
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
                @empty
                    <div class="p-8 text-center text-gray-500">{{ __('messages.no_tenants_found') }}</div>
                @endforelse
            </div>

            <!-- Pagination -->
            @if($tenants->hasPages())
            <div class="bg-white px-6 py-4 border-t border-gray-200">
                {{ $tenants->withQueryString()->links() }}
            </div>
            @endif
        </div>
</div>

<script>
function tenantFilter(){
    return {
        filter: 'all',
        searchOpen: false,
        searchQuery: '',
        matchesFilter(status, name, apartment, dayPercent){
            const q = (this.searchQuery || '').trim().toLowerCase();
            if(q && !((name || '').toLowerCase().includes(q) || (apartment || '').toLowerCase().includes(q))) return false;
            if(this.filter === 'all') return true;
            if(this.filter === 'paid') return status === 'paid';
            if(this.filter === 'overdue') return status === 'overdue';
            if(this.filter === 'unpaid') return status === 'unpaid';
            return true;
        }
    }
}
</script>

@endsection
