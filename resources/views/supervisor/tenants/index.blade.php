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
            <a href="{{ route('supervisor.tenants.create') }}" class="mt-3 sm:mt-0 inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                Register New Tenant
            </a>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
            <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <span class="text-sm font-medium">{{ session('success') }}</span>
        </div>
        @endif

        {{-- Filters --}}
        <div class="bg-white rounded-lg shadow-md mb-6 p-6">
            <form method="GET" action="{{ route('supervisor.tenants.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" placeholder="Search by name or email..." value="{{ request('search') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Apartment</label>
                    <select name="apartment" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        <option value="">All Apartments</option>
                        @foreach($apartments as $apt)
                            <option value="{{ $apt->id }}" {{ request('apartment') == $apt->id ? 'selected' : '' }}>{{ $apt->apartment_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 border border-emerald-300 rounded-lg text-emerald-600 hover:bg-emerald-50 transition font-medium">Filter</button>
                    <a href="{{ route('supervisor.tenants.index') }}" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium text-center">Reset</a>
                </div>
            </form>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow-md p-5">
                <p class="text-xs text-gray-500 uppercase font-medium">Total Tenants</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ $tenants->total() }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-5">
                <p class="text-xs text-gray-500 uppercase font-medium">Active</p>
                <p class="text-2xl font-bold text-green-600 mt-1">{{ $tenants->getCollection()->where('status', 'active')->count() }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-5">
                <p class="text-xs text-gray-500 uppercase font-medium">Floors</p>
                <p class="text-2xl font-bold text-purple-600 mt-1">{{ isset($floors) ? $floors->count() : 0 }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-5">
                <p class="text-xs text-gray-500 uppercase font-medium">Apartments</p>
                <p class="text-2xl font-bold text-indigo-600 mt-1">{{ $apartments->count() }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-5">
                <p class="text-xs text-gray-500 uppercase font-medium">Rent Income</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">${{ number_format($incomeStats['total_rent_collected'] ?? 0, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-5">
                <p class="text-xs text-gray-500 uppercase font-medium">Total Income</p>
                <p class="text-2xl font-bold text-amber-600 mt-1">${{ number_format($incomeStats['total_income'] ?? 0, 2) }}</p>
            </div>
        </div>

        {{-- Tenant Table grouped by Floor --}}
        <div class="space-y-6">
            @php
                $grouped = $tenants->getCollection()->groupBy(function($t) {
                    return $t->apartment?->floor?->id ?? 'no_floor';
                });
            @endphp

            @forelse($grouped as $floorId => $group)
                @php $floor = $floors->firstWhere('id', $floorId); @endphp
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">{{ $floor?->floor_name ?? 'Unassigned Floor' }}</h3>
                            <p class="text-sm text-gray-500">{{ $group->count() }} tenant(s)</p>
                        </div>
                        <div class="text-sm text-gray-500">&nbsp;</div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Apartment</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rent Progress</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Move In</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($group as $tenant)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                                @if($tenant->photo_path && !str_ends_with($tenant->photo_path, '.pdf'))
                                                    <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}" class="h-10 w-10 rounded-full object-cover">
                                                @else
                                                    <div class="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold text-sm">
                                                        {{ strtoupper(substr($tenant->name, 0, 1)) }}
                                                    </div>
                                                @endif
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ $tenant->name }}</div>
                                                <div class="text-sm text-gray-500">{{ $tenant->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $tenant->apartment?->apartment_number ?? 'N/A' }} <span class="text-xs text-gray-400">${{ number_format($tenant->apartment?->monthly_rent ?? 0, 0) }}/mo</span></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $tenant->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">{{ ucfirst($tenant->status) }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if(isset($rentProgressMap[$tenant->id]))
                                            @php $rp = $rentProgressMap[$tenant->id]; @endphp
                                            <div class="w-32">
                                                <div class="flex justify-between text-xs mb-1">
                                                    <span class="{{ $rp['status'] === 'paid' ? 'text-green-600' : ($rp['status'] === 'partial' ? 'text-yellow-600' : 'text-red-600') }} font-medium">{{ $rp['percent'] }}%</span>
                                                    <span class="text-gray-500">${{ number_format($rp['paid'], 0) }}/${{ number_format($rp['rent'], 0) }}</span>
                                                </div>
                                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                                    <div class="h-1.5 rounded-full {{ $rp['status'] === 'paid' ? 'bg-green-500' : ($rp['status'] === 'partial' ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $rp['percent'] }}%"></div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">No rental</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $tenant->move_in_date ? $tenant->move_in_date->format('M d, Y') : 'N/A' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-3">
                                            <a href="{{ route('supervisor.tenants.show', $tenant) }}" class="text-emerald-600 hover:text-emerald-900" title="View">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </a>
                                            <a href="{{ route('supervisor.tenants.edit', $tenant) }}" class="text-blue-600 hover:text-blue-900" title="Edit">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-lg shadow-md p-6 text-center text-gray-400">
                    <p class="text-sm">No tenants found.</p>
                </div>
            @endforelse
        </div>

        @if($tenants->hasPages())
        <div class="mt-6">
            {{ $tenants->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
