@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-900">User Management</h1>
        <a href="{{ route('admin.users.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            + Add User
        </a>
    </div>

    <!-- Realtime Search and Filters -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex gap-4 items-center">
            <input id="userSearch" type="text" placeholder="search by name or email..."
                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

            <select id="roleFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                @endforeach
            </select>

            <button id="clearFilters" type="button" class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                Clear
            </button>
        </div>
       
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">No</th>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Name</th>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Email</th>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Role
                        <button id="sortRoleBtn" title="Sort by role" class="ml-2 text-xs text-gray-500 hover:text-gray-700">Sort</button>
                    </th>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Assigned Roles</th>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($users as $user)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 text-gray-600">{{ ($users->currentPage()-1) * $users->perPage() + $loop->iteration }}</td>
                    <td class="px-6 py-4 font-medium text-gray-900">{{ $user->name }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $user->email }}</td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-700">
                            {{ ucfirst($user->roles->first()?->name ?? 'N/A') }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $user->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ ucfirst($user->status ?? 'unknown') }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <form action="{{ route('admin.users.updateRole', $user) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <select name="role" onchange="this.form.submit()" class="px-2 py-1 text-xs font-medium rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Assign Role</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" {{ $user->roles->contains($role->id) ? 'selected' : '' }}>
                                        {{ ucfirst($role->name) }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                    <td class="px-6 py-4 flex items-center gap-3">
                        <a href="{{ route('admin.users.edit', $user) }}" class="text-blue-600 hover:text-blue-900">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </a>
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('Delete this user?')">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">No users found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
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
        let sortAsc = true;

        function normalize(text){ return (text||'').toString().trim().toLowerCase(); }

        function filterRows() {
            const q = normalize(searchInput.value);
            const role = normalize(roleFilter.value);

            Array.from(tbody.querySelectorAll('tr')).forEach(row => {
                // skip empty/no-data row
                if (row.querySelectorAll('td').length === 1) return;
                const name = normalize(row.children[1].innerText);
                const email = normalize(row.children[2].innerText);
                const roleText = normalize(row.children[3].innerText);

                const matchesQuery = q === '' || name.includes(q) || email.includes(q);
                const matchesRole = role === '' || roleText === role;

                row.style.display = (matchesQuery && matchesRole) ? '' : 'none';
            });
        }

        function sortByRole() {
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
            sortBtn.textContent = sortAsc ? 'Sort ▲' : 'Sort ▼';
        }

        searchInput.addEventListener('input', filterRows);
        roleFilter.addEventListener('change', filterRows);
        clearBtn.addEventListener('click', function(){ searchInput.value=''; roleFilter.value=''; filterRows(); });
        sortBtn.addEventListener('click', sortByRole);

        // initialize button text
        sortBtn.textContent = 'Sort ▲';
    });
    </script>
    @endpush

@endsection
