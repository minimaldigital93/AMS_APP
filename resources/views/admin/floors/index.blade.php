@extends('layouts.admin')

@section('title','Floor Management')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.floor_management') }}</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.floors.create') }}" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition" title="Add Floor">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg></a>
        </div>
    </div>

    <!-- Floors -->
    <div class="space-y-5">
        @forelse($floors as $floor)
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden hover:border-slate-200 transition">
            <!-- Floor Header -->
            <div class="p-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-800">{{ $floor->floor_name }}</h3>
                            @if($showingAll && $floor->property)
                            <span class="inline-flex items-center gap-1 mt-0.5 text-xs text-slate-400">
                                <span class="material-icons text-[13px] leading-none">apartment</span>
                                {{ $floor->property->name }}
                            </span>
                            @endif
                        </div>
                    </div>
                    @php
                        $total = $floor->apartments->count();
                        $available = $floor->apartments->where('status', 'available')->count();
                        $occupied = $floor->apartments->where('status', 'occupied')->count();
                    @endphp
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-1.5" title="{{ __('messages.total') }}">
                            <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                            <span class="text-xs font-semibold text-slate-700">{{ $total }}</span>
                        </div>
                        <div class="flex items-center gap-1.5" title="{{ __('messages.available') }}">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <span class="text-xs font-semibold text-emerald-600">{{ $available }}</span>
                        </div>
                        <div class="flex items-center gap-1.5" title="{{ __('messages.occupied') }}">
                            <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                            <span class="text-xs font-semibold text-sky-600">{{ $occupied }}</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1">
                        <button type="button" onclick="openApartmentsModal('modal-floor-{{ $floor->id }}')"
                           class="text-slate-500 hover:text-slate-700 p-2 rounded-lg bg-slate-50/40 hover:bg-slate-100/60 transition" title="{{ __('messages.apartments') }}">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                        <a href="{{ route('admin.floors.edit', $floor) }}"
                           class="text-sky-600 hover:text-sky-700 p-2 rounded-lg bg-sky-50/20 hover:bg-sky-50/40 transition" title="{{ __('messages.edit_floor') }}">
                            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('admin.floors.destroy', $floor) }}" class="inline" data-confirm="Are you sure you want to delete {{ $floor->floor_name }}? This action cannot be undone. All apartments will also be deleted.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-600 p-2 rounded-lg bg-red-50/20 hover:bg-red-50/40 transition" title="{{ __('messages.delete_floor') }}">
                                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
        @empty
        <div class="bg-white rounded-xl border border-slate-100 p-16 text-center">
            <div class="w-14 h-14 rounded-xl bg-slate-50 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
                </svg>
            </div>
            <p class="font-medium text-slate-600">{{ __('messages.no_floors_found') }}</p>
            <p class="text-slate-400 text-sm mt-1">{{ __('messages.click_add_floor') }}</p>
        </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($floors->hasPages())
    <div class="flex justify-center mt-6">
        {{ $floors->links() }}
    </div>
    @endif
</div>


<!-- Floor Apartments Modals -->
@foreach($floors as $floor)
<div id="modal-floor-{{ $floor->id }}" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[85vh] overflow-y-auto shadow-xl">
        <!-- Modal Header -->
        <div class="p-6 border-b border-slate-100 sticky top-0 bg-white z-10 flex items-center justify-between rounded-t-2xl">
            <div>
                <h2 class="text-lg font-semibold text-slate-800">{{ $floor->floor_name }}</h2>
                <p class="text-slate-400 text-sm mt-0.5">{{ __('messages.apt_units_overview') }}</p>
            </div>
            <button onclick="closeApartmentsModal('modal-floor-{{ $floor->id }}')" class="text-slate-300 hover:text-slate-500 p-1 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6">
            @if($floor->apartments->count() > 0)
                <!-- Statistics -->
                @php
                    $total = $floor->apartments->count();
                    $available = $floor->apartments->where('status', 'available')->count();
                    $occupied = $floor->apartments->where('status', 'occupied')->count();
                @endphp
                <div class="grid grid-cols-3 gap-3 mb-6">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="text-[11px] text-slate-400 uppercase tracking-wider font-medium">{{ __('messages.total') }}</p>
                        <p class="text-2xl font-semibold text-slate-700 mt-1">{{ $total }}</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50/70 p-4">
                        <p class="text-[11px] text-emerald-500 uppercase tracking-wider font-medium">{{ __('messages.available') }}</p>
                        <p class="text-2xl font-semibold text-emerald-700 mt-1">{{ $available }}</p>
                    </div>
                    <div class="rounded-xl bg-sky-50/70 p-4">
                        <p class="text-[11px] text-sky-500 uppercase tracking-wider font-medium">{{ __('messages.occupied') }}</p>
                        <p class="text-2xl font-semibold text-sky-700 mt-1">{{ $occupied }}</p>
                    </div>
                </div>

                <!-- Apartments Table (desktop) -->
                <div class="hidden md:block overflow-x-auto rounded-xl border border-slate-100">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50/80">
                                <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.unit_hash') }}</th>
                                <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.monthly_rent') }}</th>
                                <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.status') }}</th>
                                <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.supervisor') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($floor->apartments as $apartment)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-4 py-3">
                                    <span class="text-sm font-medium text-slate-700">{{ $apartment->apartment_number }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm text-slate-600">{{ money($apartment->monthly_rent) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium
                                        @if($apartment->status === 'available') text-emerald-600
                                        @elseif($apartment->status === 'occupied') text-sky-600
                                        @else text-slate-500
                                        @endif">
                                        <span class="w-1.5 h-1.5 rounded-full
                                            @if($apartment->status === 'available') bg-emerald-400
                                            @elseif($apartment->status === 'occupied') bg-sky-400
                                            @else bg-slate-300
                                            @endif"></span>
                                        {{ status_label($apartment->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm text-slate-400">
                                        {{ $apartment->supervisor ? $apartment->supervisor->name : '—' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Apartments cards (mobile) -->
                <div class="md:hidden space-y-2.5">
                    @foreach($floor->apartments as $apartment)
                    <div class="rounded-xl border border-slate-100 p-3.5">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-slate-700">{{ $apartment->apartment_number }}</span>
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium
                                @if($apartment->status === 'available') text-emerald-600
                                @elseif($apartment->status === 'occupied') text-sky-600
                                @else text-slate-500
                                @endif">
                                <span class="w-1.5 h-1.5 rounded-full
                                    @if($apartment->status === 'available') bg-emerald-400
                                    @elseif($apartment->status === 'occupied') bg-sky-400
                                    @else bg-slate-300
                                    @endif"></span>
                                {{ status_label($apartment->status) }}
                            </span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-sm">
                            <span class="text-slate-400 text-xs">{{ __('messages.monthly_rent') }}</span>
                            <span class="text-slate-600 font-medium">{{ money($apartment->monthly_rent) }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-sm">
                            <span class="text-slate-400 text-xs">{{ __('messages.supervisor') }}</span>
                            <span class="text-slate-500">{{ $apartment->supervisor ? $apartment->supervisor->name : '—' }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            @else
            <div class="text-center py-14">
                <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205l3 1m1.5.5l-1.5-.5M6.75 7.364V3h-3v18m3-13.636l10.5-3.819" />
                    </svg>
                </div>
                <p class="text-slate-500 text-sm font-medium">{{ __('messages.no_apts_this_floor') }}</p>
                <p class="text-slate-400 text-xs mt-1">{{ __('messages.add_apts_from_mgmt') }}</p>
            </div>
            @endif
        </div>

        <!-- Modal Footer -->
        <div class="p-5 border-t border-slate-100 sticky bottom-0 bg-white rounded-b-2xl">
            <button type="button" onclick="closeApartmentsModal('modal-floor-{{ $floor->id }}')" class="w-full text-slate-500 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 text-sm font-medium py-2.5 px-4 rounded-lg transition">
                Close
            </button>
        </div>
    </div>
</div>
@endforeach

<script>
function openApartmentsModal(modalId) {
    document.getElementById(modalId).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeApartmentsModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.body.style.overflow = '';
}

document.addEventListener('click', function(event) {
    document.querySelectorAll('[id^="modal-floor-"]').forEach(modal => {
        if (event.target === modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    });
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.querySelectorAll('[id^="modal-floor-"]').forEach(modal => {
            modal.classList.add('hidden');
        });
        document.body.style.overflow = '';
    }
});
</script>
@endsection
