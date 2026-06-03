@extends('layouts.admin')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ __('messages.archived_tenants_management') }}</h1>
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
                        <p class="text-slate-500 text-sm">{{ __('messages.total_archived') }}</p>
                        <p class="text-2xl font-bold text-slate-800">{{ $archivedTenantCount }}</p>
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
                        <p class="text-slate-500 text-sm">{{ __('messages.recently_archived') }}</p>
                        <p class="text-2xl font-bold text-slate-800">{{ $recentlyArchivedCount }}</p>
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
                        <p class="text-slate-500 text-sm">{{ __('messages.total_deposits') }}</p>
                        <p class="text-2xl font-bold text-slate-800">${{ number_format($totalDeposits, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters / Search Section -->
        <div class="bg-white rounded-xl border border-slate-100 mb-6 p-6">
            <form method="GET" action="{{ route('admin.tenants.archived') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-500 mb-2">{{ __('messages.search_by_name_phone') }}</label>
                    <div class="relative">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                        <input type="text" name="search" placeholder="{{ __('messages.search_archived_tenants') }}" value="{{ request('search') }}" class="w-full h-10 pl-10 pr-4 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                    </div>
                </div>
                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-slate-500 mb-2">{{ __('messages.sort_by_floor') }}</label>
                    <select name="floor" class="w-full h-10 px-3 text-sm border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-300 focus:border-transparent">
                        <option value="">{{ __('messages.all_floors') }}</option>
                        @foreach($floors ?? [] as $floor)
                            <option value="{{ $floor->id }}" {{ request('floor') == $floor->id ? 'selected' : '' }}>{{ $floor->floor_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end justify-center md:col-span-1">
                    <a href="{{ route('admin.tenants.archived') }}" class="inline-flex items-center h-10 px-3 whitespace-nowrap border border-slate-200 rounded-md text-slate-700 hover:bg-slate-50 transition font-medium text-center text-sm" title="{{ __('messages.reset') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M4 10a8 8 0 0116 0M20 14a8 8 0 01-16 0" />
                        </svg></a>
                </div>
            </form>
        </div>

        <!-- Tenants Table -->
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
            <div class="p-6 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.no_col') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.tenant_name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.floor_apartment') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.tenancy_duration') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($tenants as $tenant)
                            @php $apt = $tenant->apartment ?? $tenant->leaves->last()?->apartment; @endphp
                            <tr class="hover:bg-gray-50 transition">
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
                                            <p class="text-sm text-gray-500">{{ $tenant->deleted_at?->format('M d, Y') ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $apt?->floor?->floor_name ?? 'N/A' }} / {{ $apt?->apartment_number ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    @if($tenant->leaves->last() && $tenant->move_in_date)
                                        {{ __('messages.days_suffix', ['days' => $tenant->leaves->last()->stay_days]) }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex items-center space-x-3 mt-3">
                                    <button onclick="viewTenantSettlement('{{ $tenant->id }}', '{{ addslashes($tenant->name) }}')" title="{{ __('messages.view_settlement') }}" class="inline-flex items-center justify-center h-8 w-8 rounded-md text-sky-600 bg-sky-50 hover:bg-sky-100 transition" aria-label="View Settlement">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </button>
                                    @if($tenant->document_path)
                                        <button type="button" onclick="viewTenantDocument('{{ $tenant->id }}', '{{ addslashes($tenant->name) }}')" title="{{ __('messages.view_document') }}" class="inline-flex items-center justify-center h-8 w-8 rounded-md text-red-600 bg-red-50 hover:bg-red-100 transition" aria-label="Document">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z" />
                                            </svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">{{ __('messages.no_archived_tenants_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            @if($tenants->hasPages())
            <div class="bg-white px-6 py-4 border-t border-gray-200">
                {{ $tenants->links() }}
            </div>
            @endif
        </div>
    </div>
</div>

<!-- View Tenant Details Modal -->
<div id="viewTenantModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg max-w-3xl w-full max-h-screen overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('messages.archived_tenant_details') }}</h2>
            <button onclick="closeViewTenantModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div id="tenantDetailsContent" class="p-6">
            <!-- Details will be populated here -->
        </div>
    </div>
</div>

<!-- Document Viewer Modal -->
<div id="documentViewerModal" class="hidden fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden">
        <div class="bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3 min-w-0">
                <span class="inline-flex items-center justify-center h-9 w-9 rounded-md text-red-600 bg-red-50 flex-shrink-0">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
                </span>
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-900 truncate">{{ __('messages.document') }}</h2>
                    <p id="documentTenantName" class="text-xs text-gray-500 truncate"></p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a id="documentDownloadBtn" href="#" download class="inline-flex items-center px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition text-sm font-medium" title="{{ __('messages.download') }}">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    {{ __('messages.download') }}
                </a>
                <a id="documentOpenBtn" href="#" target="_blank" class="inline-flex items-center px-3 py-2 bg-gray-50 text-gray-700 rounded-lg border border-gray-200 hover:bg-gray-100 transition text-sm font-medium" title="{{ __('messages.view_document') }}">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
                <button onclick="closeDocumentModal()" class="text-gray-400 hover:text-gray-600 ml-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>
        <div id="documentViewerBody" class="flex-1 overflow-auto bg-gray-100 p-4 flex items-center justify-center">
            <!-- Document preview injected here -->
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

let allArchivedTenants = [
    @foreach($tenants as $tenant)
        @php $apt = $tenant->apartment ?? $tenant->leaves->last()?->apartment; @endphp
        {
            id: {{ $tenant->id }},
            name: '{{ addslashes($tenant->name) }}',
            email: '{{ $tenant->email }}',
            phone: '{{ $tenant->phone ?? "" }}',
            date_of_birth: '{{ $tenant->date_of_birth }}',
            notes: '{{ addslashes($tenant->notes ?? "") }}',
            floor: '{{ $apt?->floor?->floor_name ?? "N/A" }}',
            apartment: '{{ $apt?->apartment_number ?? "N/A" }}',
            apartment_id: {{ $apt?->id ?? "null" }},
            move_in_date: '{{ $tenant->move_in_date }}',
            move_out_date: '{{ $tenant->leaves->last()?->leave_date ?? $tenant->move_out_date ?? "" }}',
            archived_at: '{{ $tenant->archived_at }}',
            deposit: {{ $tenant->deposit ?? 0 }},
            stay_days: {{ $tenant->leaves->last()?->stay_days ?? 0 }},
            photo_path: '{{ $tenant->photo_path ?? "" }}',
            document_path: '{{ $tenant->document_path ?? "" }}'
        },
    @endforeach
];

function calculateDuration(moveInDate, moveOutDate) {
    const months = Math.floor((moveOutDate - moveInDate) / (1000 * 60 * 60 * 24) / 30.44);
    const days = Math.floor(((moveOutDate - moveInDate) / (1000 * 60 * 60 * 24)) % 30.44);
    let duration = '';
    if (months > 0) duration += months + ' {{ __('messages.month_short') }} ';
    if (days > 0) duration += days + ' {{ __('messages.day_short') }}';
    return duration || ('0 ' + '{{ __('messages.day_short') }}');
}

function viewTenantSettlement(tenantId, tenantName) {
    try {
        const tenant = allArchivedTenants.find(t => t.id == tenantId);
        if (!tenant) { alert('{{ __('messages.tenant_not_found') }}'); return; }

        const t = tenant;
        const moveInDate = new Date(t.move_in_date);
        const moveOutDate = t.move_out_date ? new Date(t.move_out_date) : new Date();
        const duration = calculateDuration(moveInDate, moveOutDate);

        const content = `
            <div class="space-y-6">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-medium text-gray-600 mb-4">{{ __('messages.personal_information') }}</h3>
                        <div class="space-y-3">
                            <div class="flex items-center gap-4">
                                ${t.photo_path ? `<img src="${t.photo_path.startsWith('/') ? t.photo_path : ('/storage/' + t.photo_path)}" alt="${t.name}" class="h-20 w-20 rounded-lg object-cover border border-gray-300">` : `<div class="h-20 w-20 rounded-lg bg-red-50 flex items-center justify-center text-xl font-semibold text-red-600">${t.name.charAt(0).toUpperCase()}</div>`}
                                <div>
                                    <p class="text-xs text-gray-500">{{ __('messages.full_name') }}</p>
                                    <p class="text-sm font-medium text-gray-900">${t.name}</p>
                                    <p class="text-xs text-gray-500 mt-2">{{ __('messages.phone') }}</p>
                                    <p class="text-sm font-medium text-gray-900">${t.phone}</p>
                                    <p class="text-xs text-gray-500 mt-2">{{ __('messages.email') }}</p>
                                    <p class="text-sm font-medium text-gray-900">${t.email || '—'}</p>
                                </div>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">{{ __('messages.date_of_birth') }}</p>
                                <p class="text-sm font-medium text-gray-900">${t.date_of_birth || '{{ __('messages.not_provided') }}'}</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-600 mb-4">{{ __('messages.tenancy_information') }}</h3>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs text-gray-500">{{ __('messages.floor_apartment') }}</p>
                                <p class="text-sm font-medium text-gray-900">${t.floor} / ${t.apartment || 'N/A'}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">{{ __('messages.move_in_date') }}</p>
                                <p class="text-sm font-medium text-gray-900">${t.move_in_date}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">{{ __('messages.move_out_date') }}</p>
                                <p class="text-sm font-medium text-gray-900">${t.move_out_date || 'N/A'}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">{{ __('messages.duration') }}</p>
                                <p class="text-sm font-medium text-gray-900">${duration}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-gray-600 mb-4">{{ __('messages.settlement_information') }}</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500">{{ __('messages.deposit_amount') }}</p>
                            <p class="text-lg font-semibold text-gray-900">$${parseFloat(t.deposit || 0).toFixed(2)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">{{ __('messages.status') }}</p>
                            <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">{{ __('messages.archived') }}</span>
                        </div>
                    </div>
                </div>

                ${t.notes ? `<div class="border-t border-gray-200 pt-4">
                    <p class="text-sm font-medium text-gray-600">{{ __('messages.additional_notes') }}</p>
                    <p class="mt-2 text-sm text-gray-700">${t.notes}</p>
                </div>` : ''}
                ${t.document_path ? `<div class="mt-4"><button type="button" onclick="viewTenantDocument('${t.id}', '${t.name.replace(/'/g, "\\'")}')" class="inline-flex items-center px-3 py-2 bg-gray-50 text-gray-700 rounded-lg border border-gray-200 hover:bg-gray-100" title="{{ __('messages.view_document') }}"><svg class="w-4 h-4 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>{{ __('messages.view_document') }}</button></div>` : '' }

                <button onclick="closeViewTenantModal()" class="w-full px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition font-medium">{{ __('messages.close') }}</button>
            </div>
        `;

        document.getElementById('tenantDetailsContent').innerHTML = content;
        document.getElementById('viewTenantModal').classList.remove('hidden');
    } catch (error) {
        console.error('Error loading tenant details:', error);
        alert('{{ __('messages.error_loading_tenant') }}');
    }
}

function closeViewTenantModal() {
    document.getElementById('viewTenantModal').classList.add('hidden');
}

function viewTenantDocument(tenantId, tenantName) {
    const tenant = allArchivedTenants.find(t => t.id == tenantId);
    if (!tenant || !tenant.document_path) { alert('{{ __('messages.no_document_attached') }}'); return; }

    const url = tenant.document_path.startsWith('/') ? tenant.document_path : ('/storage/' + tenant.document_path);
    const ext = (tenant.document_path.split('.').pop() || '').toLowerCase();
    const body = document.getElementById('documentViewerBody');

    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(ext)) {
        body.innerHTML = `<img src="${url}" alt="${tenantName}" class="max-w-full max-h-[75vh] object-contain rounded shadow-sm bg-white">`;
    } else if (ext === 'pdf') {
        body.innerHTML = `<iframe src="${url}" class="w-full h-[75vh] bg-white rounded shadow-sm" frameborder="0"></iframe>`;
    } else {
        body.innerHTML = `<div class="text-center py-12">
            <svg class="w-16 h-16 mx-auto text-red-300 mb-4" fill="currentColor" viewBox="0 0 20 20"><path d="M4 2h7l5 5v11a2 2 0 01-2 2H4a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>
            <p class="text-sm text-gray-600">${ext.toUpperCase()} {{ __('messages.document') }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ __('messages.download') }}</p>
        </div>`;
    }

    document.getElementById('documentTenantName').textContent = tenantName || '';
    document.getElementById('documentDownloadBtn').setAttribute('href', url);
    document.getElementById('documentOpenBtn').setAttribute('href', url);
    document.getElementById('documentViewerModal').classList.remove('hidden');
}

function closeDocumentModal() {
    document.getElementById('documentViewerModal').classList.add('hidden');
    document.getElementById('documentViewerBody').innerHTML = '';
}

document.getElementById('documentViewerModal').addEventListener('click', function(e){
    if (e.target === this) closeDocumentModal();
});
</script>

@endsection
