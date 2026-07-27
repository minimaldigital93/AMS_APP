@extends('layouts.admin')

@section('title', __('messages.user_management_title'))

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between gap-3" x-data="{ searchOpen: false }">
        <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.user_management_title') }}</h1>
        <div class="flex items-center gap-2">
            <!-- Search (expands from icon) -->
            <input id="userSearch" type="text" placeholder="{{ __('messages.search_name_phone') }}"
                x-show="searchOpen" style="display: none"
                @keydown.escape="searchOpen = false"
                x-effect="if (!searchOpen && $el.value) { $el.value = ''; $el.dispatchEvent(new Event('input')) }"
                class="h-10 w-44 sm:w-56 px-3 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
            <button type="button"
                @click="searchOpen = !searchOpen; if (searchOpen) $nextTick(() => $el.previousElementSibling.focus())"
                class="inline-flex items-center justify-center h-10 w-10 text-gray-600 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 hover:text-gray-800 transition" title="{{ __('messages.search_name_phone') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                </svg>
            </button>

            <!-- Role filter -->
            <select id="roleFilter" class="h-10 w-32 sm:w-40 px-3 text-sm text-gray-700 bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                <option value="">{{ __('messages.all_roles') }}</option>
                @foreach($roles as $role)
                    <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                @endforeach
            </select>

            <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition" title="Add User">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg></a>
        </div>
    </div>

    <!-- Users Table (styled like apartment/floor layout) -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">No</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">{{ __('messages.name') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">{{ __('messages.phone') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">{{ __('messages.role') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">{{ __('messages.apartment') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">{{ __('messages.status') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">{{ __('messages.assigned_roles') }}</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900 whitespace-nowrap">{{ __('messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php $rowNum = 0; @endphp
                    @if($users->isEmpty() && $suspended->isEmpty())
                        <tr>
                            <td colspan="8" class="px-4 py-3 text-center text-gray-500">{{ __('messages.no_users_found') }}</td>
                        </tr>
                    @else
                        @foreach($users as $user)
                            @php $rowNum++; @endphp
                            @include('admin.users._row', ['user' => $user, 'roles' => $roles, 'number' => $rowNum])
                        @endforeach

                        @if($suspended->isNotEmpty())
                            <tr class="bg-amber-50/60">
                                <td colspan="8" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-amber-700">
                                    {{ __('messages.suspended') }} ({{ $suspended->count() }})
                                </td>
                            </tr>
                            @foreach($suspended as $user)
                                @php $rowNum++; @endphp
                                @include('admin.users._row', ['user' => $user, 'roles' => $roles, 'number' => $rowNum])
                            @endforeach
                        @endif
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Mobile / tablet card list -->
        <div id="userCards" class="lg:hidden divide-y divide-gray-100">
            @php $cardNum = 0; @endphp
            @if($users->isEmpty() && $suspended->isEmpty())
                <div class="p-8 text-center text-gray-500">{{ __('messages.no_users_found') }}</div>
            @else
                @foreach($users as $user)
                    @php $cardNum++; @endphp
                    @include('admin.users._card', ['user' => $user, 'roles' => $roles, 'number' => $cardNum])
                @endforeach

                @if($suspended->isNotEmpty())
                    <div class="px-3 py-2 bg-amber-50/60 text-xs font-semibold uppercase tracking-wide text-amber-700">
                        {{ __('messages.suspended') }} ({{ $suspended->count() }})
                    </div>
                    @foreach($suspended as $user)
                        @php $cardNum++; @endphp
                        @include('admin.users._card', ['user' => $user, 'roles' => $roles, 'number' => $cardNum])
                    @endforeach
                @endif
            @endif
        </div>
    </div>
</div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('userSearch');
        const roleFilter = document.getElementById('roleFilter');
        const tbody = document.querySelector('table tbody');
        const cards = document.querySelectorAll('.user-card');

        function normalize(text){ return (text||'').toString().trim().toLowerCase(); }

        function filterList() {
            const q = normalize(searchInput.value);
            const role = normalize(roleFilter.value);

            // Desktop table rows
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
            }

            // Mobile cards
            Array.from(cards).forEach(card => {
                const name = card.dataset.name || '';
                const phone = card.dataset.phone || '';
                const roleText = card.dataset.role || '';
                const matchesQuery = q === '' || name.includes(q) || phone.includes(q);
                const matchesRole = role === '' || roleText === role;
                card.style.display = (matchesQuery && matchesRole) ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', filterList);
        roleFilter.addEventListener('change', filterList);
    });
    </script>
    @endpush

@endsection
