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

        <!-- Filters / Search Section -->
        <div class="bg-white rounded-xl border border-slate-100 mb-6 p-6">
            <form method="GET" action="{{ route('admin.tenants.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-500 mb-2">Search by Name or Email</label>
                    <input type="text" name="search" placeholder="Search tenants..." value="{{ request('search') }}" class="w-full h-10 px-3 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-300 focus:border-transparent">
                </div>
                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-slate-500 mb-2">Sort by Floor</label>
                    <select name="floor" class="w-full h-10 px-3 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-300 focus:border-transparent">
                        <option value="">All Floors</option>
                        @foreach($floors ?? [] as $floor)
                            <option value="{{ $floor->id }}" {{ request('floor') == $floor->id ? 'selected' : '' }}>{{ $floor->floor_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end justify-center md:col-span-1">
                    <a href="{{ route('admin.tenants.index') }}" class="inline-flex items-center h-10 px-3 whitespace-nowrap border border-slate-200 rounded-md text-slate-700 hover:bg-slate-50 transition font-medium text-center text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M4 10a8 8 0 0116 0M20 14a8 8 0 01-16 0" />
                        </svg>
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Tenants Table -->
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Tenant Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Floor / Apartment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($tenants as $tenant)
                            <tr class="hover:bg-gray-50 transition" title="photo_path: {{ $tenant->photo_path ?? 'empty' }}">
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
                                    @php $rp = $rentProgressMap[$tenant->id] ?? null; @endphp
                                    @if($rp)
                                    <div class="w-28">
                                        <div class="flex items-center justify-between mb-0.5">
                                            <span class="text-[10px] text-gray-500">{{ intval($rp['days_stayed']) }}/{{ intval($rp['total_days']) }}d</span>
                                            <span class="text-[10px] font-semibold {{ $rp['day_percent'] >= 100 ? 'text-green-600' : 'text-gray-600' }}">{{ intval($rp['day_percent']) }}%</span>
                                        </div>
                                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                                            <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $rp['day_percent'] }}%"></div>
                                        </div>
                                    </div>
                                    @else
                                    <span class="text-[10px] text-gray-300">—</span>
                                    @endif
                                </td>
                                {{-- status column removed per request --}}
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
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
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
document.addEventListener('DOMContentLoaded', function(){
    const searchInput = document.querySelector('input[name="search"]');
    const floorSelect = document.querySelector('select[name="floor"]');
    if(!searchInput) return;

    const form = searchInput.closest('form');
    let timer = null;

    searchInput.addEventListener('input', function(){
        clearTimeout(timer);
        timer = setTimeout(function(){
            form.submit();
        }, 400);
    });

    if(floorSelect){
        floorSelect.addEventListener('change', function(){
            form.submit();
        });
    }
});
</script>

@endsection
