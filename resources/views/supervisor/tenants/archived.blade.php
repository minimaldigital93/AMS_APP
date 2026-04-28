@extends('layouts.supervisor')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Departed Tenants</h1>
                <p class="text-sm text-gray-500 mt-1">Archived tenants from your assigned apartments</p>
            </div>
            <a href="{{ route('supervisor.tenants.index') }}" class="mt-3 sm:mt-0 inline-flex items-center gap-2 text-emerald-600 hover:text-emerald-800 text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Active Tenants
            </a>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-xl border border-slate-100 mb-6 p-6">
            <form method="GET" action="{{ route('supervisor.tenants.archived') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" placeholder="Search by name or email..." value="{{ request('search') }}" class="w-full h-10 px-3 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-300 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Apartment</label>
                    <select name="apartment" class="w-full h-10 px-3 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-300 focus:border-transparent">
                        <option value="">All Apartments</option>
                        @foreach($apartments as $apt)
                            <option value="{{ $apt->id }}" {{ request('apartment') == $apt->id ? 'selected' : '' }}>{{ $apt->apartment_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 border border-emerald-300 rounded-lg text-emerald-600 hover:bg-emerald-50 transition font-medium">Filter</button>
                    <a href="{{ route('supervisor.tenants.archived') }}" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium text-center">Reset</a>
                </div>
            </form>
        </div>

        {{-- Archived Tenant Table --}}
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Apartment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Move In</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Departed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($tenants as $tenant)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold text-sm">
                                        {{ strtoupper(substr($tenant->name, 0, 1)) }}
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $tenant->name }}</div>
                                        <div class="text-sm text-gray-500">{{ $tenant->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $tenant->apartment?->apartment_number ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $tenant->move_in_date ? $tenant->move_in_date->format('M d, Y') : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $tenant->deleted_at ? $tenant->deleted_at->format('M d, Y') : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($tenant->move_in_date && $tenant->deleted_at)
                                    {{ $tenant->move_in_date->diffForHumans($tenant->deleted_at, true) }}
                                @else
                                    N/A
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex items-center space-x-3 mt-3">
                                <a href="{{ route('supervisor.tenants.show', $tenant) }}" title="View Details" class="inline-flex items-center justify-center h-8 w-8 rounded-md text-sky-600 bg-sky-50 hover:bg-sky-100 transition" aria-label="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                <p class="text-sm">No departed tenants found.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($tenants->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $tenants->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
