@extends('layouts.admin')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Active Tenants Management</h1>
            </div>
        </div>

        <!-- Statistics Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-slate-50">
                        <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-slate-500 text-sm">Active Tenants</p>
                        <p id="activeTenants" class="text-2xl font-bold text-slate-800">{{ $activeTenantCount }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-slate-50">
                        <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-slate-500 text-sm">Archived Tenants</p>
                        <p id="archivedTenants" class="text-2xl font-bold text-slate-800">{{ $archivedTenantCount }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-slate-50">
                        <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-slate-500 text-sm">Total Deposits</p>
                        <p id="totalDeposits" class="text-2xl font-bold text-slate-800">${{ number_format($totalDeposits, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

  

        <!-- Tenants Table -->
        <div x-data="tenantFilter()" class="bg-white rounded-xl border border-slate-100 overflow-hidden">
            <!-- Client-side Filter Bar -->
            <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-slate-500">Filter:</span>
                    <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition">All</button>
                    <button @click="filter = 'paid'" :class="filter === 'paid' ? 'bg-emerald-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition">Paid</button>
                    <button @click="filter = 'overdue'" :class="filter === 'overdue' ? 'bg-red-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition">Overdue</button>
                    <button @click="filter = 'unpaid'" :class="filter === 'unpaid' ? 'bg-gray-800 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                        class="px-3 py-1.5 rounded-lg text-sm font-medium transition">Unpaid</button>
                </div>
                <div class="flex-1"></div>
                <div class="relative">
                    <input type="text" x-model="searchQuery" placeholder="Search tenant or apartment..."
                        class="pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-slate-300 focus:border-slate-300 w-64">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Tenant Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Floor / Apartment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Stay</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Deposit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($tenants as $tenant)
                            @php $rp = $rentProgressMap[$tenant->id] ?? null; $status = $rp['status'] ?? 'unknown'; @endphp
                            <tr x-show="matchesFilter('{{ $status }}','{{ strtolower($tenant->name ?? '') }}','{{ strtolower($tenant->apartment?->apartment_number ?? '') }}','{{ $rp['day_percent'] ?? 0 }}')" class="hover:bg-gray-50 transition" title="photo_path: {{ $tenant->photo_path ?? 'empty' }}">
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $tenants->firstItem() ? $tenants->firstItem() + $loop->index : $loop->iteration }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        @if($tenant->photo_path && !str_ends_with($tenant->photo_path, '.pdf'))
                                            <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}" class="h-10 w-10 rounded-full object-cover border border-gray-300" onerror="this.style.display='none'">
                                        @else
                                            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                <span class="text-blue-600 font-semibold text-sm">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                                            </div>
                                        @endif
                                        <div class="ml-4">
                                            <p class="font-medium text-gray-900">{{ $tenant->name }}</p>
                                            <p class="text-sm text-gray-500">{{ $tenant->user_id ? 'Linked' : 'Not Linked' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $tenant->floor?->floor_name ?? ($tenant->apartment?->floor?->floor_name ?? 'N/A') }} / {{ $tenant->apartment?->apartment_number ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($rp && $rp['stay_label'])
                                        <div class="w-32">
                                            @if($rp['stay_percent'] !== null)
                                                <div class="w-full bg-slate-200 rounded-full h-1.5">
                                                    <div class="h-1.5 rounded-full {{ $rp['stay_percent'] >= 90 ? 'bg-rose-500' : 'bg-indigo-500' }}" style="width: {{ $rp['stay_percent'] }}%"></div>
                                                </div>
                                                <p class="text-xs text-slate-500 font-medium mt-0.5">
                                                    {{ $rp['stay_label'] }} · {{ $rp['lease_months_elapsed'] }}/{{ $rp['lease_months_total'] }} mo
                                                </p>
                                            @else
                                                <div class="flex items-center gap-1.5">
                                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    <p class="text-xs text-slate-600 font-medium">{{ $rp['stay_label'] }}</p>
                                                </div>
                                                <p class="text-[10px] text-slate-400 mt-0.5">Open-ended · since {{ $rp['lease_start_label'] }}</p>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-[10px] text-gray-300">—</span>
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
                                            {{ $daysRemaining }} day{{ $daysRemaining !== 1 ? 's' : '' }} left
                                        </p>
                                    </div>
                                    @else
                                    <span class="text-[10px] text-gray-300">—</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($tenant->deposit ?? 0, 2) }}</td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if(isset($rp))
                                        @php
                                            $status = $rp['status'] ?? null;
                                            $dayPercent = $rp['day_percent'] ?? 0;
                                        @endphp
                                        @if($status === 'paid')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-emerald-100 text-emerald-700">Paid</span>
                                        @elseif($status === 'partial')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-yellow-100 text-yellow-700">Paying</span>
                                        @elseif($status === 'overdue')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-red-100 text-red-700">Overdue</span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-gray-100 text-gray-700">Unpaid</span>
                                        @endif
                                    @else
                                        <span class="text-[10px] text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex items-center space-x-3 mt-3">
                                    <a href="{{ route('admin.tenants.show', $tenant->id) }}" title="View Details" class="inline-flex items-center justify-center h-8 w-8 rounded-md text-sky-600 bg-sky-50 hover:bg-sky-100 transition" aria-label="View">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>
                                    <a href="{{ route('admin.tenants.edit', $tenant->id) }}" title="Edit Tenant" class="inline-flex items-center justify-center h-8 w-8 rounded-md text-emerald-600 bg-emerald-50 hover:bg-emerald-100 transition" aria-label="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-7.5-1.5L15 3m0 0l3 3m-3-3v10"></path>
                                        </svg>
                                    </a>
                                    <a href="{{ route('admin.tenants.leave', $tenant->id) }}" title="Process Leave" class="inline-flex items-center justify-center h-8 w-8 rounded-md text-amber-600 bg-amber-50 hover:bg-amber-100 transition" aria-label="Leave">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                        </svg>
                                    </a>
                                    {{-- document icon removed as requested --}}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                    No tenants found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function tenantFilter(){
    return {
        filter: 'all',
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
document.addEventListener('DOMContentLoaded', function(){
    const searchInput = document.querySelector('input[name="search"]');
    const floorSelect = document.querySelector('select[name="floor"]');
    const form = (floorSelect || searchInput) ? (floorSelect || searchInput).closest('form') : null;
    let timer = null;

    if(searchInput && form){
        searchInput.addEventListener('input', function(){
            clearTimeout(timer);
            timer = setTimeout(function(){
                form.submit();
            }, 400);
        });
    }

    if(floorSelect && form){
        floorSelect.addEventListener('change', function(){
            form.submit();
        });
    }
});
</script>

@endsection
