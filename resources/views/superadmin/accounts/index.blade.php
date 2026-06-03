@extends('layouts.superadmin')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ __('Customer accounts') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Every admin account on the platform.') }}</p>
        </div>
        <a href="{{ route('superadmin.accounts.create') }}" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition" title="{{ __('Add account') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
        </a>
    </div>

    <!-- Realtime Search and Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <div class="flex flex-wrap gap-2.5 items-center">
            <!-- Search -->
            <div class="relative flex-1 min-w-[200px]">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                </svg>
                <input id="accountSearch" type="text" placeholder="{{ __('Search name or phone') }}"
                    class="w-full h-10 pl-10 pr-4 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
            </div>

            <!-- Status filter -->
            <select id="statusFilter" class="h-10 w-44 px-3 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="inactive">{{ __('Inactive') }}</option>
                <option value="suspended">{{ __('Suspended') }}</option>
            </select>

            <!-- Sort by name -->
            <button id="sortNameBtn" type="button" class="inline-flex items-center justify-center gap-1.5 h-10 px-3.5 text-sm font-medium text-gray-600 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 hover:text-gray-800 transition" title="{{ __('Sort') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h12M3 12h8M3 17h4M17 7v10m0 0l-3-3m3 3l3-3" />
                </svg>
                <span id="sortNameLabel">{{ __('Sort') }}</span>
            </button>

            <!-- Clear -->
            <button id="clearFilters" type="button" class="inline-flex items-center justify-center h-10 w-10 text-gray-400 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition" title="{{ __('Clear') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">No</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('Name') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('Phone') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('Plan') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('Usage') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('Expires') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($accounts as $account)
                    @php($sub = $account->subscription)
                    @php($statusKey = $account->status === 'suspended' ? 'suspended' : ($sub && $sub->isActive() ? 'active' : 'inactive'))
                    <tr class="hover:bg-gray-50 transition" data-status="{{ $statusKey }}">
                        <td class="px-6 py-4 text-gray-600">{{ ($accounts->currentPage()-1) * $accounts->perPage() + $loop->iteration }}</td>
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $account->name }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $account->phone }}</td>
                        <td class="px-6 py-4">
                            <form method="POST" action="{{ route('superadmin.accounts.plan', $account) }}">
                                @csrf
                                <select name="plan" onchange="this.form.submit()" class="w-40 px-2 py-1 text-xs font-medium rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    @foreach ($plans as $p)
                                        <option value="{{ $p->slug }}" @selected($sub?->plan_id === $p->id)>{{ $p->name }} (${{ rtrim(rtrim(number_format($p->price_usd,2),'0'),'.') }})</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 text-sm font-semibold rounded-full
                                {{ $statusKey === 'suspended' ? 'bg-red-100 text-red-700' : ($statusKey === 'active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700') }}">
                                {{ $statusKey === 'suspended' ? __('Suspended') : ($statusKey === 'active' ? __('Active') : __('Inactive')) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                            {{ __('Floors') }}: {{ $usage[$account->id]['floors'] }} · {{ __('Apts') }}: {{ $usage[$account->id]['apartments'] }}
                        </td>
                        <td class="px-6 py-4 text-gray-500 whitespace-nowrap">
                            {{ $sub?->expires_at ? $sub->expires_at->format('M j, Y') : '—' }}
                        </td>
                        <td class="px-6 py-4">
                            @if ($account->status !== 'suspended' && $sub && ! $sub->isActive())
                                <form method="POST" action="{{ route('superadmin.accounts.activate', $account) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-green-600 hover:bg-green-50 transition" title="{{ __('Activate') }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('superadmin.accounts.suspend', $account) }}" class="inline">
                                @csrf
                                @if ($account->status === 'suspended')
                                    <button type="submit" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-green-600 hover:bg-green-50 transition" title="{{ __('Reactivate') }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                @else
                                    <button type="submit" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-red-600 hover:bg-red-50 transition" title="{{ __('Suspend') }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                @endif
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">{{ __('No accounts yet.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="flex justify-center">
        {{ $accounts->links() }}
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('accountSearch');
    const statusFilter = document.getElementById('statusFilter');
    const clearBtn = document.getElementById('clearFilters');
    const sortBtn = document.getElementById('sortNameBtn');
    const sortLabel = document.getElementById('sortNameLabel');
    const tbody = document.querySelector('table tbody');
    let sortAsc = true;

    function normalize(text){ return (text||'').toString().trim().toLowerCase(); }

    function filterList() {
        const q = normalize(searchInput.value);
        const status = normalize(statusFilter.value);
        Array.from(tbody.querySelectorAll('tr')).forEach(row => {
            if (row.querySelectorAll('td').length === 1) return; // empty row
            const name = normalize(row.children[1].innerText);
            const phone = normalize(row.children[2].innerText);
            const rowStatus = normalize(row.dataset.status);
            const matchesQuery = q === '' || name.includes(q) || phone.includes(q);
            const matchesStatus = status === '' || rowStatus === status;
            row.style.display = (matchesQuery && matchesStatus) ? '' : 'none';
        });
    }

    function sortByName() {
        const rows = Array.from(tbody.querySelectorAll('tr'))
            .filter(r => r.querySelectorAll('td').length > 1);
        rows.sort((a,b) => normalize(a.children[1].innerText)
            .localeCompare(normalize(b.children[1].innerText)) * (sortAsc ? 1 : -1));
        rows.forEach(r => tbody.appendChild(r));
        sortAsc = !sortAsc;
        if (sortLabel) sortLabel.textContent = sortAsc ? 'Sort ▲' : 'Sort ▼';
    }

    searchInput.addEventListener('input', filterList);
    statusFilter.addEventListener('change', filterList);
    clearBtn.addEventListener('click', function(){ searchInput.value=''; statusFilter.value=''; filterList(); });
    if (sortBtn) sortBtn.addEventListener('click', sortByName);
    if (sortLabel) sortLabel.textContent = 'Sort ▲';
});
</script>
@endsection
