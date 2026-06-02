@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-900">{{ __('messages.user_management_title') }}</h1>
        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition" title="Add User">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg></a>
    </div>

    <!-- Realtime Search and Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex gap-4 items-center">
            <input id="userSearch" type="text" placeholder="{{ __('messages.search_name_phone') }}"
                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

            <select id="roleFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">{{ __('messages.all_roles') }}</option>
                @foreach($roles as $role)
                    <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                @endforeach
            </select>

            <button id="clearFilters" type="button" class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                Clear
            </button>

            <button id="sortRoleBtn" type="button" class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-100">{{ __('messages.sort_asc') }}</button>
        </div>
       
    </div>

    <!-- Users Table (styled like apartment/floor layout) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">No</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('messages.name') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('messages.phone') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('messages.role') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('messages.apartment') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('messages.status') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('messages.assigned_roles') }}</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">{{ __('messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 text-gray-600">{{ ($users->currentPage()-1) * $users->perPage() + $loop->iteration }}</td>
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $user->name }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $user->phone }}</td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-700">{{ ucfirst($user->roles->first()?->name ?? 'N/A') }}</span>
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $tenantRecord = $user->roles->first()?->name === 'tenant'
                                    ? $user->tenants->whereIn('status', ['active', 'pending'])->first()
                                    : null;
                            @endphp
                            @if($tenantRecord?->apartment)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">
                                    {{ $tenantRecord->apartment->apartment_number }}
                                </span>
                            @else
                                <span class="text-gray-400 text-xs">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $user->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">{{ ucfirst($user->status ?? 'unknown') }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <form action="{{ route('admin.users.updateRole', $user) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <select name="role" onchange="this.form.submit()" class="px-2 py-1 text-xs font-medium rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">{{ __('messages.assign_role') }}</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" {{ $user->roles->contains($role->id) ? 'selected' : '' }}>{{ ucfirst($role->name) }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td class="px-6 py-4 flex items-center gap-3">
                            <a href="{{ route('admin.users.edit', $user) }}"
                               class="text-sky-600 hover:text-sky-700 p-2 rounded-lg bg-sky-50/20 hover:bg-sky-50/40 transition" title="{{ __('messages.edit_user') }}">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                </svg>
                            </a>
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Delete this user?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-600 p-2 rounded-lg bg-red-50/20 hover:bg-red-50/40 transition" title="{{ __('messages.delete') }}">
                                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">{{ __('messages.no_users_found') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="flex justify-center">
        {{ $users->links() }}
    </div>
</div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('userSearch');
        const roleFilter = document.getElementById('roleFilter');
        const clearBtn = document.getElementById('clearFilters');
        const sortBtn = document.getElementById('sortRoleBtn');
        const tbody = document.querySelector('table tbody');
        const cards = document.querySelectorAll('.user-card');
        let sortAsc = true;

        function normalize(text){ return (text||'').toString().trim().toLowerCase(); }

        function filterList() {
            const q = normalize(searchInput.value);
            const role = normalize(roleFilter.value);

            if (tbody) {
                Array.from(tbody.querySelectorAll('tr')).forEach(row => {
                    // skip empty/no-data row
                    if (row.querySelectorAll('td').length === 1) return;
                    const name = normalize(row.children[1].innerText);
                    const phone = normalize(row.children[2].innerText);
                    const roleText = normalize(row.children[3].innerText); // col 3 = Role

                    const matchesQuery = q === '' || name.includes(q) || phone.includes(q);
                    const matchesRole = role === '' || roleText === role;

                    row.style.display = (matchesQuery && matchesRole) ? '' : 'none';
                });
            } else {
                Array.from(cards).forEach(card => {
                    const name = card.dataset.name || '';
                    const phone = card.dataset.phone || '';
                    const roleText = card.dataset.role || '';
                    const matchesQuery = q === '' || name.includes(q) || phone.includes(q);
                    const matchesRole = role === '' || roleText === role;
                    card.style.display = (matchesQuery && matchesRole) ? '' : 'none';
                });
            }
        }

        function sortByRole() {
            if (tbody) {
                const rows = Array.from(tbody.querySelectorAll('tr'))
                    .filter(r => r.querySelectorAll('td').length > 1 && r.style.display !== 'none');

                rows.sort((a,b) => {
                    const ra = normalize(a.children[3].innerText) || 'zzzz';
                    const rb = normalize(b.children[3].innerText) || 'zzzz';
                    if (ra === rb) {
                        const na = normalize(a.children[1].innerText);
                        const nb = normalize(b.children[1].innerText);
                        return na.localeCompare(nb) * (sortAsc ? 1 : -1);
                    }
                    return (ra.localeCompare(rb)) * (sortAsc ? 1 : -1);
                });

                // Re-append in sorted order
                rows.forEach(r => tbody.appendChild(r));
                sortAsc = !sortAsc;
                if (sortBtn) sortBtn.textContent = sortAsc ? 'Sort ▲' : 'Sort ▼';
            } else {
                const cardList = Array.from(cards).filter(c => c.style.display !== 'none');
                cardList.sort((a,b) => {
                    const ra = normalize(a.dataset.role) || 'zzzz';
                    const rb = normalize(b.dataset.role) || 'zzzz';
                    if (ra === rb) return normalize(a.dataset.name).localeCompare(normalize(b.dataset.name)) * (sortAsc ? 1 : -1);
                    return ra.localeCompare(rb) * (sortAsc ? 1 : -1);
                });
                const container = document.querySelector('.grid.grid-cols-1') || document.body;
                cardList.forEach(c => container.appendChild(c));
                sortAsc = !sortAsc;
                if (sortBtn) sortBtn.textContent = sortAsc ? 'Sort ▲' : 'Sort ▼';
            }
        }

        searchInput.addEventListener('input', filterList);
        roleFilter.addEventListener('change', filterList);
        clearBtn.addEventListener('click', function(){ searchInput.value=''; roleFilter.value=''; filterList(); });
        if (sortBtn) sortBtn.addEventListener('click', sortByRole);

        if (sortBtn) sortBtn.textContent = 'Sort ▲';
    });
    </script>
    @endpush

@endsection
