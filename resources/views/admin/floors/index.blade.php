@extends('layouts.admin')

@section('title', __('messages.floors_and_rooms'))

@section('content')
<div class="max-w-6xl mx-auto space-y-8">
    <!-- Header -->
    {{-- Property scoping is driven by the top-bar selector; on "All properties"
         each floor below is tagged with its building. Rooms are listed inline
         under each floor via the collapsible dropdown. --}}
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.floors_and_rooms') }}</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.floors.create') }}" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition" title="{{ __('messages.add_floor') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
            </a>
        </div>
    </div>

    <!-- Floors (each expands to its rooms) -->
    <div class="space-y-5">
        @forelse($floors as $floor)
        @php
            $total = $floor->apartments->count();
            $available = $floor->apartments->where('status', 'available')->count();
            $occupied = $floor->apartments->where('status', 'occupied')->count();
        @endphp
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden hover:border-slate-200 transition">
            <details class="group">
                <summary class="flex items-center justify-between gap-3 cursor-pointer px-6 py-4 hover:bg-slate-50/50 transition">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold text-slate-800 truncate">{{ $floor->floor_name }}</h2>
                            @if($showingAll && $floor->property)
                            <span class="inline-flex items-center gap-1 text-xs text-slate-400">
                                <span class="material-icons text-[13px] leading-none">apartment</span>
                                {{ $floor->property->name }}
                            </span>
                            @endif
                        </div>
                    </div>

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

                    <!-- Floor actions grouped next to the dropdown arrow -->
                    <div class="flex items-center gap-1">
                            <!-- Edit only (delete moved into the floor edit page to prevent accidental deletion) -->
                            <div class="flex items-center gap-1" onclick="event.stopPropagation()">
                                <a href="{{ route('admin.floors.edit', $floor) }}"
                                   class="text-sky-600 hover:text-sky-700 p-2 rounded-lg bg-sky-50/20 hover:bg-sky-50/40 transition" title="{{ __('messages.edit_floor') }}">
                                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                    </svg>
                                </a>
                            </div>

                            <!-- Dropdown arrow (kept outside stopPropagation so it still toggles) -->
                            <svg class="w-4 h-4 text-slate-400 group-open:rotate-90 transition-transform flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                    </div>
                </summary>

                @if($floor->apartments->count() > 0)
                <!-- Rooms Table (desktop) -->
                <div class="hidden md:block overflow-x-auto border-t border-slate-50">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50/80">
                            <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">No</th>
                            <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.apartment') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.tenant') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.monthly_rent') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.status') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.stay_duration') }}</th>
                            <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.supervisor') }}</th>
                            <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($floor->apartments as $apartment)
                        @php
                            $tenant = $apartment->tenants->whereNull('deleted_at')->sortByDesc('id')->first();
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-4 py-3 text-sm text-slate-500">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{
                                        $apartment->status === 'available' ? 'bg-emerald-400' :
                                        ($apartment->status === 'occupied' ? 'bg-sky-400' : 'bg-amber-400')
                                    }}" title="{{ __('messages.' . $apartment->status) }}"></span>
                                    <span class="text-sm font-medium text-slate-700">{{ $apartment->apartment_number }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if($tenant)
                                    <div class="flex items-center gap-2.5">
                                        @if($tenant->photo_path && !str_ends_with($tenant->photo_path, '.pdf'))
                                            <img src="{{ asset('storage/' . $tenant->photo_path) }}" alt="{{ $tenant->name }}" class="h-7 w-7 rounded-full object-cover border border-slate-200">
                                        @else
                                            <div class="h-7 w-7 rounded-full bg-slate-100 flex items-center justify-center text-xs font-medium text-slate-500">
                                                {{ strtoupper(substr($tenant->name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="text-sm font-medium text-slate-700">{{ $tenant->name }}</div>
                                            <div class="text-[11px] text-slate-400">{{ $tenant->phone }}</div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-slate-300 text-sm">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $rentTenant = $apartment->tenants()->whereNull('deleted_at')->latest()->first();
                                    $rentRental = $rentTenant ? $apartment->rentals()->where('tenant_id', $rentTenant->id)->latest()->first() : null;
                                    $rentExpected = (float) ($rentRental->rent_amount ?? $apartment->monthly_rent ?? 0);
                                @endphp
                                <span class="text-sm font-medium text-slate-600">{{ money($rentExpected) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $statusTextClass = match($apartment->status) {
                                        'available' => 'text-emerald-600',
                                        'occupied' => 'text-sky-600',
                                        default => 'text-slate-500',
                                    };
                                    $statusBgClass = match($apartment->status) {
                                        'available' => 'bg-emerald-400',
                                        'occupied' => 'bg-sky-400',
                                        default => 'bg-slate-300',
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $statusTextClass }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusBgClass }}"></span>
                                    {{ status_label($apartment->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $hasMonthlyPeriod = false;

                                    if($tenant && $tenant->move_in_date) {
                                        $moveInDate = \Carbon\Carbon::parse($tenant->move_in_date);
                                        $today = now();

                                        if ($tenant->move_out_date) {
                                            $moveOutDate = \Carbon\Carbon::parse($tenant->move_out_date);
                                        }

                                        $billingDay = $moveInDate->day;
                                        if ($today->day >= $billingDay) {
                                            $periodStart = $today->copy()->day($billingDay)->startOfDay();
                                        } else {
                                            $prevMonth = $today->copy()->subMonth();
                                            $periodStart = $prevMonth->day(min($billingDay, $prevMonth->daysInMonth))->startOfDay();
                                        }
                                        $periodEnd = $periodStart->copy()->addMonth()->subDay()->endOfDay();
                                        if ($periodStart->lt($moveInDate)) $periodStart = $moveInDate->copy();
                                        if ($tenant->move_out_date && $periodEnd->gt($moveOutDate)) $periodEnd = $moveOutDate->copy()->endOfDay();

                                        $periodTotalDays = max(1, $periodStart->diffInDays($periodEnd));
                                        $periodDaysPassed = max(0, min($periodTotalDays, $periodStart->diffInDays($today)));
                                        $periodPercent = min(100, max(0, round(($periodDaysPassed / $periodTotalDays) * 100, 1)));
                                        $periodDaysLeft = max(0, (int)$today->diffInDays($periodEnd, false));

                                        if ($periodPercent >= 80) {
                                            $monthBarColor = 'bg-red-400';
                                            $monthTextColor = 'text-red-500';
                                        } elseif ($periodPercent >= 50) {
                                            $monthBarColor = 'bg-amber-400';
                                            $monthTextColor = 'text-amber-500';
                                        } else {
                                            $monthBarColor = 'bg-sky-400';
                                            $monthTextColor = 'text-sky-500';
                                        }

                                        $rental = $apartment->rentals()->where('tenant_id', $tenant->id)->latest()->first();
                                        $expectedAmount = $rental->rent_amount ?? $apartment->monthly_rent ?? 0;
                                        $paymentPercent = 0;

                                        if ($expectedAmount > 0 && $rental) {
                                            try {
                                                $paidAmount = (float) $rental->payments()
                                                    ->whereNotNull('paid_at')
                                                    ->whereBetween('due_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                                                    ->sum('amount');
                                                $paymentPercent = min(100, max(0, round(($paidAmount / $expectedAmount) * 100, 1)));
                                            } catch (\Exception $e) {
                                                $paymentPercent = 0;
                                            }
                                        }

                                        $hasMonthlyPeriod = true;
                                    }
                                @endphp

                                @if($tenant && $tenant->move_in_date)
                                    @if($hasMonthlyPeriod)
                                    <div class="w-32" title="{{ $periodStart->format('M d') }}–{{ $periodEnd->format('M d') }} ({{ $periodPercent }}%, {{ $periodDaysLeft }}d left)">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-[11px] font-medium {{ $monthTextColor }}">
                                                @if($paymentPercent >= 100)
                                                    <span class="inline-flex items-center gap-1 text-emerald-500">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                        {{ __('messages.paid') }}
                                                    </span>
                                                @else
                                                    {{ $periodDaysLeft }}d left
                                                @endif
                                            </span>
                                            <span class="text-[11px] font-medium {{ $monthTextColor }}">{{ $periodPercent }}%</span>
                                        </div>
                                        <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                            <div class="h-full rounded-full {{ $monthBarColor }}" style="width: {{ $periodPercent }}%"></div>
                                        </div>
                                    </div>
                                    @endif
                                @else
                                    <span class="text-slate-300 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $displaySupervisor = ($tenant?->manager ?? null) ?? $apartment->supervisor;
                                @endphp
                                @if($displaySupervisor)
                                    <span class="text-sm text-slate-600">{{ $displaySupervisor->name }}</span>
                                @else
                                    <span class="text-slate-300 text-sm">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @if(!$tenant || $tenant->status !== 'active')
                                    <button type="button"
                                            data-apartment-id="{{ $apartment->id }}"
                                            data-apartment-number="{{ $apartment->apartment_number }}"
                                            class="assign-tenant-btn text-emerald-600 hover:text-emerald-700 p-1.5 rounded-lg bg-emerald-50/20 hover:bg-emerald-50 transition"
                                            title="{{ __('messages.assign_tenant') }}">
                                        <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </button>
                                    @endif

                                    <a href="{{ route('admin.apartments.edit', $apartment->id) }}"
                                       title="{{ __('messages.edit_apartment') }}"
                                       class="text-sky-600 hover:text-sky-700 p-1.5 rounded-lg bg-sky-50/20 hover:bg-slate-50 transition">
                                        <svg class="w-[16px] h-[16px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                <!-- Rooms cards (mobile) -->
                <div class="md:hidden border-t border-slate-50 divide-y divide-slate-50">
                    @foreach($floor->apartments as $apartment)
                        @php
                            $mTenant = $apartment->tenants()->whereNull('deleted_at')->latest()->first();
                            $mRental = $mTenant ? $apartment->rentals()->where('tenant_id', $mTenant->id)->latest()->first() : null;
                            $mRent = (float) ($mRental->rent_amount ?? $apartment->monthly_rent ?? 0);
                            $mSupervisor = ($mTenant?->manager ?? null) ?? $apartment->supervisor;
                            $mStatusText = match($apartment->status) {
                                'available' => 'text-emerald-600',
                                'occupied' => 'text-sky-600',
                                default => 'text-slate-500',
                            };
                            $mStatusBg = match($apartment->status) {
                                'available' => 'bg-emerald-400',
                                'occupied' => 'bg-sky-400',
                                default => 'bg-slate-300',
                            };
                        @endphp
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $mStatusBg }} flex-shrink-0"></span>
                                    <span class="text-base font-semibold text-slate-800">{{ $apartment->apartment_number }}</span>
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium {{ $mStatusText }}">{{ status_label($apartment->status) }}</span>
                                </div>
                                <span class="text-sm font-semibold text-slate-700 flex-shrink-0">{{ money($mRent) }}</span>
                            </div>

                            <div class="mt-3">
                                @if($mTenant)
                                    <div class="flex items-center gap-2.5">
                                        @if($mTenant->photo_path && !str_ends_with($mTenant->photo_path, '.pdf'))
                                            <img src="{{ asset('storage/' . $mTenant->photo_path) }}" alt="{{ $mTenant->name }}" class="h-9 w-9 rounded-full object-cover border border-slate-200">
                                        @else
                                            <div class="h-9 w-9 rounded-full bg-slate-100 flex items-center justify-center text-xs font-medium text-slate-500">{{ strtoupper(substr($mTenant->name, 0, 1)) }}</div>
                                        @endif
                                        <div class="min-w-0">
                                            <div class="text-sm font-medium text-slate-700 truncate">{{ $mTenant->name }}</div>
                                            <div class="text-[11px] text-slate-400">{{ $mTenant->phone }}</div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-slate-300 text-sm">{{ __('messages.vacant') ?? '—' }}</span>
                                @endif
                            </div>

                            <div class="mt-3 flex items-center justify-between gap-3">
                                <div class="text-xs text-slate-400 truncate">
                                    @if($mSupervisor)
                                        <span class="text-slate-300">{{ __('messages.supervisor') }}:</span> {{ $mSupervisor->name }}
                                    @endif
                                </div>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    @if(!$mTenant || $mTenant->status !== 'active')
                                    <button type="button"
                                            data-apartment-id="{{ $apartment->id }}"
                                            data-apartment-number="{{ $apartment->apartment_number }}"
                                            class="assign-tenant-btn inline-flex items-center justify-center h-9 w-9 rounded-lg text-emerald-600 bg-emerald-50 active:bg-emerald-100 transition" title="{{ __('messages.assign_tenant') }}">
                                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                                    </button>
                                    @endif
                                    <a href="{{ route('admin.apartments.edit', $apartment->id) }}" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-sky-600 bg-sky-50 active:bg-sky-100 transition" title="{{ __('messages.edit_apartment') }}">
                                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @else
                <!-- Empty floor -->
                <div class="border-t border-slate-50 text-center py-12 px-6">
                    <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205l3 1m1.5.5l-1.5-.5M6.75 7.364V3h-3v18m3-13.636l10.5-3.819" />
                        </svg>
                    </div>
                    <p class="text-slate-500 text-sm font-medium">{{ __('messages.no_apts_this_floor') }}</p>
                    <a href="{{ route('admin.floors.edit', $floor) }}" class="inline-flex items-center gap-1.5 mt-3 text-sky-600 hover:text-sky-700 text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        {{ __('messages.add_apartment') }}
                    </a>
                </div>
                @endif
            </details>
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

@include('admin.apartments._assign-tenant-modal')

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Accordion behaviour: opening one floor collapses the others.
    const floorDetails = document.querySelectorAll('details.group');
    if (!floorDetails || floorDetails.length === 0) return;
    floorDetails.forEach(detail => {
        detail.addEventListener('toggle', function() {
            if (detail.open) {
                floorDetails.forEach(d => { if (d !== detail) d.open = false; });
            }
        });
    });
});
</script>
@endsection
