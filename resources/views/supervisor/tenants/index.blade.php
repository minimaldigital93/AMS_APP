@extends('layouts.supervisor')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Active Tenants</h1>
                <p class="text-sm text-gray-500 mt-1">
                    @if(isset($activePeriod) && $activePeriod)
                        Fiscal Period: {{ $activePeriod->name }} ({{ \Carbon\Carbon::parse($activePeriod->opening_date)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($activePeriod->closing_date)->format('M d, Y') }})
                    @else
                        Tenants in your assigned apartments
                    @endif
                </p>
            </div>
         
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
            <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span class="text-sm font-medium">{{ session('success') }}</span>
        </div>
        @endif

        {{-- Statistics --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-slate-50">
                        <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-slate-500 text-sm">Active Tenants</p>
                        <p class="text-2xl font-bold text-slate-800">{{ $activeTenantCount }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-slate-50">
                        <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-slate-500 text-sm">Archived Tenants</p>
                        <p class="text-2xl font-bold text-slate-800">{{ $archivedTenantCount }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-slate-50">
                        <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-slate-500 text-sm">Total Deposits</p>
                        <p class="text-2xl font-bold text-slate-800">${{ number_format($totalDeposits, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tenants Table with Alpine.js filter --}}
        <div x-data="tenantFilter()" class="bg-white rounded-xl border border-slate-100 overflow-hidden">
            {{-- Filter Bar --}}
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Deposit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($tenants as $tenant)
                            @php
                                $rp = $rentProgressMap[$tenant->id] ?? null;
                                $status = $rp['status'] ?? 'unknown';
                                $dayPercent = $rp['day_percent'] ?? 0;
                            @endphp
                            <tr x-show="matchesFilter('{{ $status }}','{{ strtolower($tenant->name ?? '') }}','{{ strtolower($tenant->apartment?->apartment_number ?? '') }}','{{ $dayPercent }}')"
                                class="hover:bg-gray-50 transition">
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
                                    @if($rp)
                                        @php
                                            $dp = $rp['day_percent'];
                                            $barColor   = $dp >= 80 ? 'bg-red-400'    : ($dp >= 50 ? 'bg-yellow-400' : 'bg-blue-400');
                                            $trackColor = $dp >= 80 ? 'bg-red-100'    : ($dp >= 50 ? 'bg-yellow-100' : 'bg-blue-100');
                                            $textColor  = $dp >= 80 ? 'text-red-500'  : ($dp >= 50 ? 'text-yellow-600' : 'text-blue-500');
                                            $periodDays   = $rp['period_days'] ?? 30;
                                            $daysElapsed  = (int) round(($dp / 100) * $periodDays);
                                            $daysRemaining = max(0, $periodDays - $daysElapsed);
                                        @endphp
                                        <div class="w-28">
                                            <div class="flex items-center justify-between mb-0.5">
                                                <span class="text-[10px] font-semibold {{ $textColor }}">{{ $dp }}%</span>
                                                <span class="text-[10px] text-gray-400">{{ $daysRemaining }} day{{ $daysRemaining !== 1 ? 's' : '' }} left</span>
                                            </div>
                                            <div class="w-full rounded-full h-1.5 {{ $trackColor }}">
                                                <div class="h-1.5 rounded-full {{ $barColor }}" style="width: {{ $dp }}%"></div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-[10px] text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($rp)
                                        @if($status === 'paid')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-emerald-100 text-emerald-700">Paid</span>
                                        @elseif($status === 'partial')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-yellow-100 text-yellow-700">Paying</span>
                                        @else
                                            @if($dayPercent >= 80)
                                                <span class="px-2 py-1 text-xs font-semibold rounded-md bg-red-100 text-red-700">Overdue</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-semibold rounded-md bg-gray-100 text-gray-700">Unpaid</span>
                                            @endif
                                        @endif
                                    @else
                                        <span class="text-[10px] text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${{ number_format($tenant->deposit ?? 0, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex items-center space-x-3 mt-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('supervisor.tenants.show', $tenant) }}" title="View Details"
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-md text-sky-600 bg-sky-50 hover:bg-sky-100 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                        <a href="{{ route('supervisor.tenants.edit', $tenant) }}" title="Edit Tenant"
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-md text-emerald-600 bg-emerald-50 hover:bg-emerald-100 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <a href="{{ route('supervisor.tenants.leave', $tenant) }}" title="Process Leave"
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-md text-amber-600 bg-amber-50 hover:bg-amber-100 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-400 text-sm">No tenants found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($tenants->hasPages())
        <div class="mt-6">
            {{ $tenants->withQueryString()->links() }}
        </div>
        @endif

    </div>
</div>

<script>
function tenantFilter() {
    return {
        filter: 'all',
        searchQuery: '',
        matchesFilter(status, name, apartment, dayPercent) {
            const q = (this.searchQuery || '').trim().toLowerCase();
            if (q && !((name || '').toLowerCase().includes(q) || (apartment || '').toLowerCase().includes(q))) return false;
            if (this.filter === 'all') return true;
            if (this.filter === 'paid') return status === 'paid';
            if (this.filter === 'overdue') return status === 'overdue' || Number(dayPercent) >= 80;
            if (this.filter === 'unpaid') return status !== 'paid' && status !== 'partial' && Number(dayPercent) < 80;
            return true;
        }
    }
}
</script>
@endsection
