@extends('layouts.'.$panel)

@section('content')
<div class="max-w-6xl mx-auto space-y-8" x-data="billingManager()">
    <!-- Header -->
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.monthly_billing_payments') }}</h1>
        <div class="flex items-center gap-2 flex-shrink-0">
            <!-- Search (icon → expands to input) -->
            <div class="relative flex items-center justify-end">
                <div x-show="searchOpen" x-transition.opacity x-cloak class="relative">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                    <input type="text" x-model="searchQuery" x-ref="searchInput" placeholder="{{ __('messages.search_tenant_apartment') }}"
                        class="w-44 sm:w-64 h-10 pl-10 pr-9 text-sm bg-white border border-slate-200 rounded-full focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 transition">
                    <button type="button" @click="searchQuery = ''; searchOpen = false" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <button type="button" x-show="!searchOpen" @click="searchOpen = true; $nextTick(() => $refs.searchInput.focus())"
                    class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition" aria-label="{{ __('messages.search') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z"/></svg>
                </button>
            </div>
            <a href="{{ route($panel.'.revenue_expense.index') }}" class="inline-flex items-center justify-center h-10 w-10 bg-slate-800 hover:bg-slate-700 text-white rounded-lg transition flex-shrink-0" title="{{ __('messages.back') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        </div>
    </div>

    <!-- Month Navigation -->
    <div class="flex flex-wrap items-center justify-center gap-3">
        <div class="inline-flex items-center bg-white rounded-xl border border-slate-100 px-2 py-1.5 gap-1">
            <a href="{{ route($panel.'.revenue_expense.record_income', ['month' => $prevDate->month, 'year' => $prevDate->year]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ __('messages.previous_month') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="px-4 py-2 min-w-[180px] text-center">
                <span class="text-lg font-bold text-slate-800">{{ $selectedDate->format('F') }}</span>
                <span class="text-lg text-slate-400 ml-1">{{ $selectedDate->format('Y') }}</span>
                @if(!$isCurrentMonth)
                    @if($isFutureMonth)
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-50 text-sky-700">{{ __('messages.upcoming') }}</span>
                    @else
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">{{ __('messages.past') }}</span>
                    @endif
                @else
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">{{ __('messages.current') }}</span>
                @endif
            </div>
            <a href="{{ route($panel.'.revenue_expense.record_income', ['month' => $nextDate->month, 'year' => $nextDate->year]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ __('messages.next_month') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @if(!$isCurrentMonth)
            <a href="{{ route($panel.'.revenue_expense.record_income') }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition" title="{{ __('messages.go_to_current_month') }}">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></a>
            @endif
        </div>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-red-600 text-sm">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-4">
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.collected') }}</p>
                    <p class="text-xl font-bold text-emerald-600">{{ money($totalRentCollected) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ __('messages.tenants_paid', ['count' => $paidCount]) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.pending') }}</p>
                    <p class="text-xl font-bold text-amber-600">{{ money($totalPending) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ __('messages.n_pending', ['count' => $pendingCount]) }}</p>
        </div>
        @if($isFutureMonth)
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-sky-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.upcoming') }}</p>
                    <p class="text-xl font-bold text-sky-600">{{ $pendingCount }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ __('messages.scheduled_for') }} {{ $selectedDate->format('F') }}</p>
        </div>
        @else
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.overdue') }}</p>
                    <p class="text-xl font-bold text-red-600">{{ $overdueCount }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ __('messages.past_due_date') }}</p>
        </div>
        @endif
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-xl border border-slate-100 p-3 md:p-4 flex items-center gap-2 overflow-x-auto">
        <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition flex-shrink-0 whitespace-nowrap">{{ __('messages.all') }} {{ count($tenantBillsAll ?? $tenantBills) }}</button>
        @if(!$isFutureMonth)
        <button @click="filter = 'overdue'" :class="filter === 'overdue' ? 'bg-red-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition flex-shrink-0 whitespace-nowrap">{{ __('messages.overdue') }} {{ $overdueCount }}</button>
        @endif
        <button @click="filter = 'pending'" :class="filter === 'pending' ? 'bg-amber-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition flex-shrink-0 whitespace-nowrap">{{ $isFutureMonth ? __('messages.upcoming') : __('messages.pending') }} {{ $pendingCount }}</button>
        <button @click="filter = 'paid'" :class="filter === 'paid' ? 'bg-emerald-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
            class="px-3 py-1.5 rounded-lg text-sm font-medium transition flex-shrink-0 whitespace-nowrap">{{ __('messages.paid') }} {{ $paidCount }}</button>
    </div>

    <!-- Tenant Bills — grouped into floor accordion cards (styled like Floors & Rooms) -->
    @php
        // Group by property first so each building's floors stay together; the
        // property header is only shown when more than one property is present
        // (the consolidated "All properties" view).
        $billsByProperty = collect($tenantBills->items())->groupBy(fn ($b) => $b['apartment']->floor->property_id ?? 0);
        $multipleProperties = $billsByProperty->count() > 1;
    @endphp
    @if(count($tenantBillsAll ?? $tenantBills) > 0)
    <div class="space-y-5">
        @php $rowNum = $tenantBills->firstItem() ?? 1; @endphp
        @php $mRowNum = $tenantBills->firstItem() ?? 1; @endphp
        @foreach($billsByProperty as $propertyId => $propertyBills)
        @if($multipleProperties)
        @php $propertyName = $propertyBills->first()['apartment']->floor->property->name ?? __('messages.property'); @endphp
        <div x-show="floorHasMatch({{ Illuminate\Support\Js::from($propertyBills->map(fn($b) => ['status' => $b['status'], 'tenant' => strtolower($b['tenant']->name ?? ''), 'apt' => strtolower($b['apartment']->apartment_number ?? '')])->values()) }})"
             class="flex items-center gap-2.5 pt-3 pb-1 px-1">
            <div class="w-8 h-8 rounded-lg bg-sky-50 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21"/></svg>
            </div>
            <h3 class="text-base font-bold text-slate-700 truncate">{{ $propertyName }}</h3>
        </div>
        @endif
        @php $groupedBills = $propertyBills->groupBy(fn ($b) => $b['apartment']->floor->id ?? 0); @endphp
        @foreach($groupedBills as $floorId => $floorGroup)
        @php
            $groupFloor = $floorGroup->first()['apartment']->floor;
            $flPaid = $floorGroup->where('status', 'paid')->count();
            $flOverdue = $floorGroup->where('status', 'overdue')->count();
            $flPending = $floorGroup->count() - $flPaid - $flOverdue;
            $rowNum = 1;
            $mRowNum = 1;
        @endphp
        <div x-show="floorHasMatch({{ Illuminate\Support\Js::from($floorGroup->map(fn($b) => ['status' => $b['status'], 'tenant' => strtolower($b['tenant']->name ?? ''), 'apt' => strtolower($b['apartment']->apartment_number ?? '')])->values()) }})"
             class="bg-white rounded-xl border border-slate-100 overflow-hidden hover:border-slate-200 transition">
            <!-- Floor summary (click toggles rooms) -->
            <div @click="toggleFloor('{{ $floorId }}')"
                 class="flex items-center justify-between gap-3 cursor-pointer px-4 md:px-6 py-4 hover:bg-slate-50/50 transition select-none">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" /></svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-800 truncate">{{ $groupFloor?->floor_name ?? __('messages.no_col') }}</h3>
                </div>

                <div class="flex items-center gap-3 md:gap-4 flex-shrink-0">
                    <div class="flex items-center gap-1.5" title="{{ __('messages.total') }}">
                        <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                        <span class="text-xs font-semibold text-slate-700">{{ $floorGroup->count() }}</span>
                    </div>
                    <div class="flex items-center gap-1.5" title="{{ __('messages.paid') }}">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span class="text-xs font-semibold text-emerald-600">{{ $flPaid }}</span>
                    </div>
                    @if(!$isFutureMonth)
                    <div class="flex items-center gap-1.5" title="{{ __('messages.overdue') }}">
                        <span class="w-2 h-2 rounded-full bg-red-400"></span>
                        <span class="text-xs font-semibold text-red-600">{{ $flOverdue }}</span>
                    </div>
                    @endif
                    <div class="flex items-center gap-1.5" title="{{ $isFutureMonth ? __('messages.upcoming') : __('messages.pending') }}">
                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                        <span class="text-xs font-semibold text-amber-600">{{ $flPending }}</span>
                    </div>
                    <svg class="w-4 h-4 text-slate-400 transition-transform flex-shrink-0" :class="isFloorOpen('{{ $floorId }}') ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                </div>
            </div>

            <!-- Rooms table (desktop) -->
            <div x-show="isFloorOpen('{{ $floorId }}')" x-cloak class="hidden md:block overflow-x-auto border-t border-slate-50">
                <table class="w-full">
                    <thead>
                        <tr class="bg-slate-50/80">
                            <th class="w-12 px-4 lg:px-6 py-4 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.no_col') }}</th>
                            <th class="w-[28%] px-4 lg:px-6 py-4 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.tenant') }}</th>
                            <th class="w-[13%] px-4 lg:px-6 py-4 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.rent') }}</th>
                            <th class="w-[13%] px-4 lg:px-6 py-4 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.charges') }}</th>
                            <th class="w-[13%] px-4 lg:px-6 py-4 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.total') }}</th>
                            <th class="w-[12%] px-4 lg:px-6 py-4 text-center text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.status') }}</th>
                            <th class="px-4 lg:px-6 py-4 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($floorGroup as $bill)
                    @php
                        $chargesJson = $bill['utilities']->map(fn($u) => [
                            'id'     => $u->id,
                            'type'   => $u->utility_type,
                            'amount' => (float) $u->charge_amount,
                            'paid'   => (bool) $u->paid_status,
                        ])->values();
                    @endphp
                    <tr x-show="isFloorOpen('{{ $floorId }}') && matchesFilter('{{ $bill['status'] }}', '{{ strtolower($bill['tenant']->name ?? '') }}', '{{ strtolower($bill['apartment']->apartment_number ?? '') }}')"
                        class="hover:bg-gray-50 transition {{ $bill['status'] === 'overdue' ? 'bg-red-50/40' : ($bill['status'] === 'paid' ? 'bg-emerald-50/40' : ($isFutureMonth ? 'bg-sky-50/30' : '')) }}">
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $rowNum++ }}</td>
                        <td class="px-4 lg:px-6 py-4">
                            <div class="flex items-center">
                                <div class="h-9 w-9 rounded-lg bg-sky-50 items-center justify-center flex-shrink-0 hidden lg:flex">
                                    <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <div class="lg:ml-3 min-w-0">
                                    <p class="font-semibold text-slate-800 text-sm truncate">{{ $bill['tenant']->name ?? 'N/A' }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $bill['tenant']->phone ?? '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-right">
                            <p class="text-sm text-slate-700">{{ money($bill['monthly_rent']) }}</p>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-right">
                            <p class="text-sm text-slate-700">{{ money($bill['total_bill'] - $bill['monthly_rent']) }}</p>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-right">
                            <p class="text-sm font-bold {{ $bill['status'] === 'paid' ? 'text-emerald-600' : 'text-slate-800' }}">{{ money($bill['total_bill']) }}</p>
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-center">
                            @if($bill['status'] === 'paid')
                                <span class="inline-block px-2 py-1 text-xs font-semibold rounded-md bg-emerald-100 text-emerald-700">{{ __('messages.paid') }}</span>
                            @elseif($bill['status'] === 'overdue')
                                <span class="inline-block px-2 py-1 text-xs font-semibold rounded-md bg-red-100 text-red-700">{{ __('messages.overdue') }}</span>
                            @else
                                <span class="inline-block px-2 py-1 text-xs font-semibold rounded-md bg-amber-100 text-amber-700">{{ $isFutureMonth ? __('messages.upcoming') : __('messages.pending') }}</span>
                            @endif
                        </td>
                        <td class="px-4 lg:px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center justify-end gap-4">
                                @if($bill['status'] !== 'paid')
                                <button @click="openAddCharge({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? __('messages.tenant')) }}', '{{ $bill['apartment']->apartment_number }}')"
                                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-orange-600 bg-orange-50 hover:bg-orange-100 transition" title="{{ __('messages.add_charge') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                </button>
                                @endif
                                <button @click="openChargesReceipt({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? __('messages.tenant')) }}', '{{ $bill['apartment']->apartment_number }}', {{ $chargesJson->toJson() }}, {{ $bill['monthly_rent'] }}, {{ $bill['total_fixed'] }})"
                                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-sky-600 bg-sky-50 hover:bg-sky-100 transition" title="{{ __('messages.view_charges') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                                <button @click="openReceipt('{{ route($panel.'.revenue_expense.print_receipt', ['rental' => $bill['rental']->id, 'month' => $currentMonth, 'year' => $currentYear, 'embed' => 1]) }}')"
                                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition" title="{{ __('messages.view_receipt') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3h12v18l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5L6 21z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6M9 11.5h6M9 15h3.5"/></svg>
                                </button>
                                @if($bill['status'] !== 'paid')
                                <button @click="openCheckout({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? __('messages.tenant')) }}', '{{ $bill['apartment']->apartment_number }}', {{ $bill['monthly_rent'] }}, {{ $bill['total_utility_only'] }}, {{ $bill['total_other_charges'] }}, {{ $bill['total_fixed'] }}, {{ $bill['total_bill'] }}, {{ $bill['late_fee_suggested'] ?? 0 }}, {{ $bill['overdue_days'] ?? 0 }})"
                                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-emerald-600 bg-emerald-50 hover:bg-emerald-100 transition" title="{{ __('messages.checkout_pay') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Rooms list (mobile) -->
            <div x-show="isFloorOpen('{{ $floorId }}')" x-cloak class="md:hidden border-t border-slate-50 divide-y divide-slate-100">
                @foreach($floorGroup as $bill)
            @php
                $chargesJson = $bill['utilities']->map(fn($u) => [
                    'id'     => $u->id,
                    'type'   => $u->utility_type,
                    'amount' => (float) $u->charge_amount,
                    'paid'   => (bool) $u->paid_status,
                ])->values();
            @endphp
            <div x-show="isFloorOpen('{{ $floorId }}') && matchesFilter('{{ $bill['status'] }}', '{{ strtolower($bill['tenant']->name ?? '') }}', '{{ strtolower($bill['apartment']->apartment_number ?? '') }}')"
                 class="flex items-center gap-3 px-4 py-3 active:bg-slate-50 transition {{ $bill['status'] === 'overdue' ? 'bg-red-50/40' : ($bill['status'] === 'paid' ? 'bg-emerald-50/40' : ($isFutureMonth ? 'bg-sky-50/30' : '')) }}">
                <span class="w-5 text-xs font-medium text-slate-400 text-center flex-shrink-0">{{ $mRowNum++ }}</span>
                <div class="h-9 w-9 rounded-lg bg-sky-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <!-- Apartment + tenant -->
                <div class="min-w-0 flex-3">
                    <p class="font-semibold text-slate-800 text-sm truncate">{{ $bill['apartment']->apartment_number }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ $bill['tenant']->name ?? 'N/A' }}</p>
                </div>
                <!-- Amount + status -->
                <div class="flex flex-col items-center flex-1 min-w-0">
                    <p class="text-sm font-bold {{ $bill['status'] === 'paid' ? 'text-emerald-600' : 'text-slate-800' }} whitespace-nowrap">{{ money($bill['total_bill']) }}</p>
                    @if($bill['status'] === 'paid')
                        <span class="mt-0.5 px-1.5 py-0.5 text-[10px] font-semibold rounded bg-emerald-100 text-emerald-700">{{ __('messages.paid') }}</span>
                    @elseif($bill['status'] === 'overdue')
                        <span class="mt-0.5 px-1.5 py-0.5 text-[10px] font-semibold rounded bg-red-100 text-red-700">{{ __('messages.overdue') }}</span>
                    @else
                        <span class="mt-0.5 px-1.5 py-0.5 text-[10px] font-semibold rounded bg-amber-100 text-amber-700">{{ $isFutureMonth ? __('messages.upcoming') : __('messages.pending') }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                    @if($bill['status'] !== 'paid')
                    <button @click="openAddCharge({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? __('messages.tenant')) }}', '{{ $bill['apartment']->apartment_number }}')"
                        class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-orange-600 bg-orange-50 active:bg-orange-100 transition" title="{{ __('messages.add_charge') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    </button>
                    @else
                    <span class="h-8 w-8 flex-shrink-0" aria-hidden="true"></span>
                    @endif
                    <button @click="openChargesReceipt({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? __('messages.tenant')) }}', '{{ $bill['apartment']->apartment_number }}', {{ $chargesJson->toJson() }}, {{ $bill['monthly_rent'] }}, {{ $bill['total_fixed'] }})"
                        class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-sky-700 bg-sky-50 active:bg-sky-100 transition" title="{{ __('messages.view_charges') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                    <button @click="openReceipt('{{ route($panel.'.revenue_expense.print_receipt', ['rental' => $bill['rental']->id, 'month' => $currentMonth, 'year' => $currentYear, 'embed' => 1]) }}')"
                        class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-indigo-600 bg-indigo-50 active:bg-indigo-100 transition" title="{{ __('messages.view_receipt') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3h12v18l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5L6 21z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6M9 11.5h6M9 15h3.5"/></svg>
                    </button>
                    @if($bill['status'] !== 'paid')
                    <button @click="openCheckout({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? __('messages.tenant')) }}', '{{ $bill['apartment']->apartment_number }}', {{ $bill['monthly_rent'] }}, {{ $bill['total_utility_only'] }}, {{ $bill['total_other_charges'] }}, {{ $bill['total_fixed'] }}, {{ $bill['total_bill'] }}, {{ $bill['late_fee_suggested'] ?? 0 }}, {{ $bill['overdue_days'] ?? 0 }})"
                        class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-emerald-600 bg-emerald-50 active:bg-emerald-100 transition" title="{{ __('messages.checkout_pay') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </button>
                    @else
                    <span class="h-8 w-8 flex-shrink-0" aria-hidden="true"></span>
                    @endif
                </div>
            </div>
                @endforeach
            </div>
        </div>
        @endforeach
        @endforeach
    </div>
    @else
    <div class="bg-white rounded-xl border border-slate-100 text-center py-16">
        <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        <p class="text-slate-500 text-lg">{{ __('messages.no_active_rentals_found') }}</p>
        <p class="text-slate-400 text-sm mt-1">{{ __('messages.tenants_appear_auto') }}</p>
    </div>
    @endif

    @if((isset($tenantBills) && method_exists($tenantBills, 'links')))
    <div class="flex justify-center mt-2">{{ $tenantBills->appends(request()->query())->links() }}</div>
    @endif

    <!-- ============================================ -->
    <!-- CHARGES RECEIPT MODAL                        -->
    <!-- ============================================ -->
    <div x-show="showChargesReceipt" x-cloak class="fixed inset-0 z-[70] overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showChargesReceipt = false"></div>
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm relative z-10">
                <!-- Header -->
                <div class="px-5 py-4 flex items-center justify-between border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-amber-50 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm" x-text="viewApt + ' — ' + viewTenant"></p>
                            <p class="text-xs text-slate-400">{{ __('messages.charge_receipt') }}</p>
                        </div>
                    </div>
                    <button @click="showChargesReceipt = false" class="text-slate-400 hover:text-slate-600 w-7 h-7 flex items-center justify-center rounded-lg hover:bg-slate-100 transition text-lg">&times;</button>
                </div>

                <!-- Charge list -->
                <div class="px-5 py-4 space-y-1 max-h-72 overflow-y-auto">
                    <!-- Rent row (read-only) -->
                    <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-sky-50/60">
                        <span class="text-sm text-slate-600 font-medium">{{ __('messages.rent') }}</span>
                        <span class="text-sm font-semibold text-slate-800" x-text="'$' + parseFloat(viewRent).toFixed(2)"></span>
                    </div>

                    <!-- Dynamic charges -->
                    <template x-for="(c, i) in viewCharges" :key="i">
                        <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-slate-50 group">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full flex-shrink-0"
                                    :class="{
                                        'bg-yellow-400': c.type === 'electricity',
                                        'bg-blue-400': c.type === 'water',
                                        'bg-purple-400': c.type === 'internet',
                                        'bg-orange-400': c.type === 'parking',
                                        'bg-teal-400': c.type === 'trash',
                                        'bg-slate-400': c.type === 'other'
                                    }"></span>
                                <span class="text-sm text-slate-600 capitalize" x-text="typeLabels[c.type] || c.type"></span>
                                <span x-show="c.paid" class="text-[10px] font-medium text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">{{ __('messages.paid_lower') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold" :class="c.paid ? 'text-emerald-600' : 'text-amber-700'" x-text="'$' + parseFloat(c.amount).toFixed(2)"></span>
                                <button x-show="!c.paid" @click="removeViewCharge(c.id, i)"
                                    class="opacity-0 group-hover:opacity-100 w-5 h-5 flex items-center justify-center text-red-400 hover:text-red-600 rounded transition"
                                    title="{{ __('messages.remove_charge') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Apartment costs -->
                    <template x-if="viewFixed > 0">
                        <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-purple-50/60">
                            <span class="text-sm text-slate-600">{{ __('messages.apartment_costs') }}</span>
                            <span class="text-sm font-semibold text-slate-700" x-text="'$' + parseFloat(viewFixed).toFixed(2)"></span>
                        </div>
                    </template>

                    <!-- Empty state -->
                    <div x-show="viewCharges.length === 0 && viewFixed <= 0" class="text-center py-6 text-slate-400 text-sm">{{ __('messages.no_charges_yet') }}</div>
                </div>

                <!-- Total + actions -->
                <div class="px-5 pt-3 pb-5 border-t border-slate-100 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-700">{{ __('messages.total_bill') }}</span>
                        <span class="text-xl font-bold text-sky-600" x-text="'$' + viewBillTotal()"></span>
                    </div>

                    <!-- Delete all unpaid -->
                    <button x-show="viewCharges.some(c => !c.paid)"
                        @click="clearAllCharges()"
                        class="w-full flex items-center justify-center gap-2 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition" title="{{ __('messages.delete_all_unpaid') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>

                    <button @click="showChargesReceipt = false"
                        class="w-full py-2 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">{{ __('messages.close') }}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- RECEIPT MODAL (in-page preview via iframe)   -->
    <!-- ============================================ -->
    <div x-show="showReceipt" x-cloak class="fixed inset-0 z-[70] overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeReceipt()"></div>
            <div class="relative z-10 w-full max-w-sm bg-white rounded-xl shadow-2xl flex flex-col overflow-hidden" style="height:90vh;height:90dvh;max-height:90vh;">
                <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between flex-shrink-0">
                    <h3 class="text-sm font-semibold text-slate-700">{{ __('messages.payment_receipt') }}</h3>
                    <div class="flex items-center gap-2">
                        <button @click="printReceiptFrame()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            {{ __('messages.print_receipt') }}
                        </button>
                        <button @click="closeReceipt()" class="text-slate-400 hover:text-slate-600 w-7 h-7 flex items-center justify-center rounded-lg hover:bg-slate-100 transition text-lg">&times;</button>
                    </div>
                </div>
                <div class="bg-slate-200 flex-1 min-h-0">
                    <template x-if="showReceipt">
                        <iframe x-ref="receiptFrame" :src="receiptUrl" class="w-full h-full border-0" title="{{ __('messages.payment_receipt') }}"></iframe>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- ADD CHARGE MODAL (tap type → enter amount)   -->
    <!-- ============================================ -->
    @php
        $chargeTypeMeta = [
            'electricity' => [
                'label'  => __('messages.electric'),
                'icon'   => 'M13 10V3L4 14h7v7l9-11h-7z',
                'chip'   => 'bg-yellow-50 text-yellow-600',
                'dot'    => 'bg-yellow-400',
                'active' => 'border-yellow-400 bg-yellow-50/70 ring-1 ring-yellow-400',
                'text'   => 'text-yellow-700',
                'badge'  => 'bg-yellow-500',
            ],
            'water' => [
                'label'  => __('messages.water'),
                'icon'   => 'M12 3s-6.5 7.2-6.5 11.2C5.5 17.9 8.4 21 12 21s6.5-3.1 6.5-6.8C18.5 10.2 12 3 12 3z',
                'chip'   => 'bg-blue-50 text-blue-600',
                'dot'    => 'bg-blue-400',
                'active' => 'border-blue-400 bg-blue-50/70 ring-1 ring-blue-400',
                'text'   => 'text-blue-700',
                'badge'  => 'bg-blue-500',
            ],
            'internet' => [
                'label'  => __('messages.type_internet'),
                'icon'   => 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0',
                'chip'   => 'bg-purple-50 text-purple-600',
                'dot'    => 'bg-purple-400',
                'active' => 'border-purple-400 bg-purple-50/70 ring-1 ring-purple-400',
                'text'   => 'text-purple-700',
                'badge'  => 'bg-purple-500',
            ],
            'parking' => [
                'label'  => __('messages.type_parking'),
                'icon'   => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6 0a2 2 0 104 0m-4 0a2 2 0 11-4 0m10 0a2 2 0 104 0',
                'chip'   => 'bg-orange-50 text-orange-600',
                'dot'    => 'bg-orange-400',
                'active' => 'border-orange-400 bg-orange-50/70 ring-1 ring-orange-400',
                'text'   => 'text-orange-700',
                'badge'  => 'bg-orange-500',
            ],
            'trash' => [
                'label'  => __('messages.type_trash'),
                'icon'   => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
                'chip'   => 'bg-teal-50 text-teal-600',
                'dot'    => 'bg-teal-400',
                'active' => 'border-teal-400 bg-teal-50/70 ring-1 ring-teal-400',
                'text'   => 'text-teal-700',
                'badge'  => 'bg-teal-500',
            ],
            'other' => [
                'label'  => __('messages.type_other'),
                'icon'   => 'M12 6v6m0 0v6m0-6h6m-6 0H6',
                'chip'   => 'bg-slate-100 text-slate-500',
                'dot'    => 'bg-slate-400',
                'active' => 'border-slate-400 bg-slate-50 ring-1 ring-slate-400',
                'text'   => 'text-slate-700',
                'badge'  => 'bg-slate-500',
            ],
        ];
    @endphp
    <div x-show="showAddCharge" x-cloak x-ref="addChargeModal" class="fixed inset-0 z-[70] overflow-y-auto" aria-modal="true">
        <div x-ref="addChargeWrap" class="flex items-center justify-center min-h-screen px-4 py-4 sm:py-10" style="min-height:100dvh;">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showAddCharge = false"></div>
            <div x-ref="addChargeCard" class="bg-white rounded-2xl shadow-2xl w-full max-w-md relative z-10 flex flex-col" style="max-height:80vh;max-height:80dvh;">
                <!-- Header -->
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between flex-shrink-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 bg-orange-50 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-800 text-sm truncate">{{ __('messages.add_charge') }} — <span class="text-sky-600" x-text="chargeApt"></span></p>
                            <p class="text-xs text-slate-400 truncate"><span x-text="chargeTenant"></span> · {{ $selectedDate->format('F Y') }}</p>
                        </div>
                    </div>
                    <button @click="showAddCharge = false" class="text-slate-400 hover:text-slate-600 w-7 h-7 flex items-center justify-center rounded-lg hover:bg-slate-100 transition flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="saveDone" class="flex flex-col flex-1 min-h-0">
                    <div class="p-4 sm:p-5 space-y-4 overflow-y-auto flex-1">
                        <!-- Type selector chips -->
                        <div>
                            <p class="text-xs text-slate-400 mb-2">{{ __('messages.tap_charge_types_hint') }}</p>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach($chargeTypeMeta as $type => $meta)
                                <button type="button" @click="toggleCharge('{{ $type }}')"
                                    class="relative flex flex-col items-center gap-1.5 py-3 rounded-xl border transition select-none"
                                    :class="charges.{{ $type }}.active ? '{{ $meta['active'] }}' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'">
                                    <span class="w-8 h-8 rounded-lg flex items-center justify-center {{ $meta['chip'] }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $meta['icon'] }}"/></svg>
                                    </span>
                                    <span class="text-xs font-medium leading-tight" :class="charges.{{ $type }}.active ? '{{ $meta['text'] }}' : 'text-slate-500'">{{ $meta['label'] }}</span>
                                    <span x-show="charges.{{ $type }}.active" x-cloak
                                        class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full {{ $meta['badge'] }} text-white flex items-center justify-center shadow-sm">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div x-show="activeChargeCount() === 0" class="text-center py-8 border-2 border-dashed border-slate-100 rounded-xl">
                            <svg class="w-8 h-8 mx-auto text-slate-200 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                            <p class="text-sm text-slate-400">{{ __('messages.select_charge_hint') }}</p>
                        </div>

                        <!-- One input panel per selected type -->
                        <div class="space-y-3">
                            @foreach($chargeTypeMeta as $type => $meta)
                            <div x-show="charges.{{ $type }}.active" x-cloak
                                x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                class="rounded-xl border border-slate-100 bg-slate-50/60 p-3.5 space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full {{ $meta['dot'] }}"></span>
                                        <span class="text-sm font-semibold text-slate-700">{{ $meta['label'] }}</span>
                                    </div>
                                    <button type="button" @click="toggleCharge('{{ $type }}')"
                                        class="w-6 h-6 flex items-center justify-center text-slate-300 hover:text-red-500 rounded-md hover:bg-white transition" title="{{ __('messages.cancel') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>

                                @php $isMetered = in_array($type, ['electricity', 'water']); @endphp
                                @if($isMetered)
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[11px] text-slate-400 mb-1">{{ __('messages.meter_in') }}</label>
                                        <input type="number" x-model="charges.{{ $type }}.meter_in" @input="syncMeterAmount('{{ $type }}')" step="0.01" min="0" inputmode="decimal" placeholder="0"
                                            class="w-full px-3 py-2 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-sky-400 focus:border-sky-400 transition">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-slate-400 mb-1">{{ __('messages.meter_out') }}</label>
                                        <input type="number" x-model="charges.{{ $type }}.meter_out" @input="syncMeterAmount('{{ $type }}')" step="0.01" min="0" inputmode="decimal" placeholder="0"
                                            class="w-full px-3 py-2 text-sm bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-sky-400 focus:border-sky-400 transition">
                                    </div>
                                </div>
                                <div x-show="chargeUsage('{{ $type }}') !== null" class="flex items-center gap-1.5 text-xs text-slate-500">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                    {{ __('messages.usage') }}: <span class="font-semibold text-slate-700" x-text="chargeUsage('{{ $type }}')"></span>
                                </div>
                                <p x-show="chargeUsageInvalid('{{ $type }}')" class="text-xs text-red-500">{{ __('messages.meter_out_lt_in') }}</p>
                                <p x-show="charges.{{ $type }}.meter_in !== '' && charges.{{ $type }}.meter_out === ''" x-cloak class="text-[11px] text-slate-400">{{ __('messages.meter_in_only_hint') }}</p>
                                @endif

                                <div>
                                    <label class="block text-[11px] text-slate-400 mb-1">
                                        {{ __('messages.amount') }}
                                        @if($isMetered)
                                        <span x-show="meterAutoCalc" x-cloak class="ml-1 inline-flex items-center gap-0.5 text-[10px] font-medium text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">{{ __('messages.charge_auto_from_meter') }}</span>
                                        <span x-show="!meterAutoCalc" class="text-red-400">*</span>
                                        @else
                                        <span class="text-red-400">*</span>
                                        @endif
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">{{ currency_symbol() }}</span>
                                        <input type="number" x-model="charges.{{ $type }}.amount" step="0.01" min="0" inputmode="decimal" placeholder="{{ currency_is_khr() ? '0' : '0.00' }}"
                                            @if($isMetered) :readonly="meterAutoCalc" @endif
                                            class="w-full pl-8 pr-3 py-2.5 text-base font-semibold text-slate-800 text-right bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition @if($isMetered) read-only:bg-slate-100 read-only:text-slate-500 read-only:cursor-not-allowed @endif">
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Sticky footer: live total + actions -->
                    <div class="px-4 sm:px-5 pt-4 pb-[calc(1.25rem+env(safe-area-inset-bottom))] sm:pb-4 border-t border-slate-100 flex items-center justify-between gap-3 flex-shrink-0 bg-white rounded-b-2xl">
                        <div class="min-w-0">
                            <p class="text-[11px] text-slate-400">{{ __('messages.total') }}<span x-show="filledChargeCount() > 1" x-text="' · ' + filledChargeCount()"></span></p>
                            <p class="text-xl font-bold whitespace-nowrap" :class="filledChargeCount() > 0 ? 'text-emerald-600' : 'text-slate-300'">{{ currency_symbol() }}<span x-text="chargesTotal()"></span></p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button type="button" @click="showAddCharge = false"
                                class="px-4 py-2.5 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">{{ __('messages.cancel') }}</button>
                            <button type="submit" :disabled="isSubmitting || filledChargeCount() === 0"
                                class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-lg text-sm font-semibold flex items-center gap-1.5 transition">
                                <svg x-show="isSubmitting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                <span x-text="isSubmitting ? '{{ __('messages.saving') }}' : '{{ __('messages.save') }}'"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- CHECKOUT / PAY MODAL                         -->
    <!-- ============================================ -->
    <div x-show="showCheckout" x-cloak class="fixed inset-0 z-[70] overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="closeCheckout()"></div>
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative z-10 flex flex-col" style="max-height:90vh;max-height:90dvh;">
                <!-- Header -->
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-emerald-50 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800"><span x-text="checkoutApt"></span> — <span x-text="checkoutTenant"></span></p>
                            <p class="text-xs text-slate-400">{{ __('messages.monthly_payment') }}</p>
                        </div>
                    </div>
                    <button @click="closeCheckout()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition text-lg leading-none">&times;</button>
                </div>
                <form action="{{ route($panel.'.revenue_expense.checkout') }}" method="POST" class="p-5 space-y-4 overflow-y-auto flex-1" x-show="!khqrActive" @submit="onCheckoutSubmit($event)">
                    @csrf
                    <input type="hidden" name="rental_id" x-model="checkoutRentalId">
                    <input type="hidden" name="rent_amount" x-model="checkoutRent">
                    {{-- The month this bill page is showing — checkout settles THIS month's charges --}}
                    <input type="hidden" name="billing_month" value="{{ $currentMonth }}">
                    <input type="hidden" name="billing_year" value="{{ $currentYear }}">
                    <!-- Bill lines -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-50">
                            <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                                <input type="checkbox" name="pay_rent" value="1" x-model="payRent" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                {{ __('messages.rent') }}
                            </label>
                            <span class="text-sm font-semibold text-slate-800" x-text="'$' + parseFloat(checkoutRent).toFixed(2)"></span>
                        </div>
                        <div x-show="checkoutUtilities > 0 || checkoutOtherCharges > 0"
                             class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-50">
                            <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                                <input type="checkbox" name="pay_utilities" value="1" x-model="payUtilities" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                {{ __('messages.charges') }}
                            </label>
                            <span class="text-sm font-semibold text-slate-800" x-text="'$' + (parseFloat(checkoutUtilities) + parseFloat(checkoutOtherCharges)).toFixed(2)"></span>
                        </div>
                        <div x-show="checkoutUtilities > 0 && payUtilities" class="flex items-center justify-between py-1 px-3 pl-10">
                            <span class="text-xs text-slate-400">↳ Utilities (Elec/Water)</span>
                            <span class="text-xs text-slate-500" x-text="'$' + parseFloat(checkoutUtilities).toFixed(2)"></span>
                        </div>
                        <div x-show="checkoutOtherCharges > 0 && payUtilities" class="flex items-center justify-between py-1 px-3 pl-10">
                            <span class="text-xs text-slate-400">↳ Other (Internet/Parking…)</span>
                            <span class="text-xs text-slate-500" x-text="'$' + parseFloat(checkoutOtherCharges).toFixed(2)"></span>
                        </div>
                        <div x-show="checkoutFixed > 0" class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-50">
                            <span class="text-sm text-slate-500 pl-6">{{ __('messages.apartment_costs') }}</span>
                            <span class="text-sm font-medium text-slate-700" x-text="'$' + parseFloat(checkoutFixed).toFixed(2)"></span>
                        </div>
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-50">
                            <span class="text-sm text-slate-500">{{ __('messages.late_fee') }}</span>
                            <input type="number" name="late_fee" x-model="checkoutLateFee" step="0.01" min="0" value="0"
                                class="w-20 text-right text-sm border border-slate-200 rounded-lg px-2 py-1 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <p x-show="lateFeePercent > 0 && checkoutOverdueDays > 0" x-cloak class="text-[11px] text-slate-400 -mt-1 px-3"
                            x-text="'{{ __('messages.late_fee_auto_hint') }}'.replace(':percent', lateFeePercent).replace(':days', checkoutOverdueDays)"></p>
                        <div class="flex items-center justify-between pt-2 px-1 border-t border-slate-200">
                            <span class="font-bold text-slate-700">{{ __('messages.total') }}</span>
                            <span class="text-xl font-bold text-emerald-600" x-text="'$' + calculateCheckoutTotal()"></span>
                        </div>
                    </div>
                    <!-- Payment method chips -->
                    <div>
                        <p class="text-xs text-slate-400 mb-1.5">{{ __('messages.payment_method') }} <span class="text-red-400">*</span></p>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center justify-center gap-2 py-2.5 border rounded-xl cursor-pointer text-sm transition select-none"
                                :class="checkoutMethod === 'cash' ? 'bg-emerald-50 border-emerald-300 text-emerald-700 font-medium' : 'border-slate-200 text-slate-500 hover:border-slate-300'">
                                <input type="radio" name="payment_method" value="cash" x-model="checkoutMethod" class="sr-only" required>
                                💵 {{ __('messages.cash') }}
                            </label>
                            <label class="flex items-center justify-center gap-2 py-2.5 border rounded-xl cursor-pointer text-sm transition select-none"
                                :class="checkoutMethod === 'khqr' ? 'bg-rose-50 border-rose-300 text-rose-700 font-medium' : 'border-slate-200 text-slate-500 hover:border-slate-300'">
                                <input type="radio" name="payment_method" value="khqr" x-model="checkoutMethod" class="sr-only">
                                📱 KHQR
                            </label>
                        </div>
                    </div>
                    <!-- Date -->
                    <div class="min-w-0">
                        <label class="block text-xs text-slate-400 mb-1">{{ __('messages.date') }} <span class="text-red-400">*</span></label>
                        <input type="date" name="payment_date" required value="{{ date('Y-m-d') }}"
                            style="max-width:100%;box-sizing:border-box;"
                            class="w-full min-w-0 px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
                    </div>
                    <!-- Buttons -->
                    <div class="flex gap-2 pt-1">
                        <button type="button" @click="closeCheckout()"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium rounded-lg transition">{{ __('messages.cancel') }}</button>
                        <button type="submit"
                            class="flex-1 py-2.5 text-white text-sm font-semibold rounded-lg transition"
                            :class="checkoutMethod === 'khqr' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                            x-text="checkoutMethod === 'khqr' ? '{{ __('messages.generate_khqr') }}' : '{{ __('messages.confirm_payment') }}'"></button>
                    </div>
                </form>

                <!-- KHQR QR panel (shown after Generate) -->
                <div x-show="khqrActive" x-cloak class="p-5 overflow-y-auto flex-1 text-center space-y-4">
                    <div>
                        <p class="text-xs text-slate-400">{{ __('messages.scan_to_pay') }}</p>
                        <p class="text-3xl font-bold text-rose-600 mt-1">$<span x-text="khqrAmount"></span></p>
                    </div>

                    <!-- Generating -->
                    <div x-show="khqrLoading" class="py-12 flex flex-col items-center gap-3 text-slate-400">
                        <svg class="w-8 h-8 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                        <span class="text-sm">{{ __('messages.generating_qr') }}</span>
                    </div>

                    <!-- Error -->
                    <div x-show="khqrError" class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-red-600 text-sm" x-text="khqrError"></div>

                    <!-- QR + waiting / manual confirmation -->
                    <template x-if="!khqrLoading && !khqrError && (khqrUrl || khqrChannel === 'manual')">
                        <div class="space-y-4">
                            <div x-show="khqrUrl && !khqrExpired" class="inline-block p-3 bg-white border border-slate-200 rounded-2xl">
                                <img :src="khqrUrl" alt="KHQR" class="w-56 h-56 object-contain mx-auto">
                            </div>

                            <!-- Manual channel: bank details + landlord confirms receipt -->
                            <template x-if="khqrChannel === 'manual'">
                                <div class="space-y-3">
                                    <div x-show="khqrBank.bank_name || khqrBank.account_number"
                                        class="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 text-left text-sm text-slate-600 space-y-1">
                                        <div x-show="khqrBank.bank_name"><span class="text-slate-400">{{ __('messages.bank_name') }}:</span> <span class="font-medium" x-text="khqrBank.bank_name"></span></div>
                                        <div x-show="khqrBank.account_name"><span class="text-slate-400">{{ __('messages.bank_account_name') }}:</span> <span class="font-medium" x-text="khqrBank.account_name"></span></div>
                                        <div x-show="khqrBank.account_number"><span class="text-slate-400">{{ __('messages.bank_account_number') }}:</span> <span class="font-medium" x-text="khqrBank.account_number"></span></div>
                                    </div>
                                    <div x-show="!khqrPaid" class="space-y-2">
                                        <p class="text-xs text-slate-400">{{ __('messages.khqr_manual_confirm_hint') }}</p>
                                        <button type="button" @click="confirmKhqrManual()" :disabled="khqrConfirming"
                                            class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition disabled:opacity-50">
                                            {{ __('messages.khqr_mark_received') }}
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <div x-show="khqrChannel === 'api' && !khqrPaid && !khqrExpired" class="flex flex-col items-center gap-1">
                                <div class="flex items-center justify-center gap-2 text-amber-600 text-sm">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                    {{ __('messages.waiting_for_payment') }}
                                </div>
                                <p x-show="khqrCountdown" class="text-xs text-slate-400">{{ __('messages.payment_expires_in') }} <span class="font-medium tabular-nums" x-text="khqrCountdown"></span></p>
                            </div>
                            <!-- Expired / failed — friendly fallback, no infinite spinner -->
                            <div x-show="khqrExpired" class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-left space-y-2">
                                <p class="text-sm font-semibold text-amber-800">{{ __('messages.payment_session_ended') }}</p>
                                <p class="text-xs text-amber-700">{{ __('messages.payment_session_ended_hint') }}</p>
                                <button type="button" @click="regenerateKhqr()"
                                    class="w-full py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg transition">
                                    {{ __('messages.payment_try_again') }}
                                </button>
                            </div>
                            <div x-show="khqrPaid" class="flex flex-col items-center gap-2 text-emerald-600">
                                <svg class="w-12 h-12" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <span class="text-sm font-semibold">{{ __('messages.payment_received') }}</span>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="closeCheckout()"
                        class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium rounded-lg transition">{{ __('messages.cancel') }}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- RECENT PAYMENTS                              -->
    <!-- ============================================ -->
    {{-- Recent payments removed per request --}}
</div>

<script>
function billingManager() {
    return {
        typeLabels: { electricity: '{{ __('messages.electric') }}', water: '{{ __('messages.water') }}', internet: '{{ __('messages.type_internet') }}', parking: '{{ __('messages.type_parking') }}', trash: '{{ __('messages.type_trash') }}', other: '{{ __('messages.type_other') }}' },
        filter: '{{ in_array(request('filter'), ['paid', 'pending', 'overdue']) ? request('filter') : 'all' }}',
        searchOpen: false,
        searchQuery: '',

        // Collapsible floor groups (accordion). Default collapsed; search auto-expands.
        openFloors: {},
        toggleFloor(id) { this.openFloors[id] = !this.openFloors[id]; },
        isFloorOpen(id) {
            if (this.searchQuery) return true;
            return !!this.openFloors[id];
        },

        // Open the printable bill in a new tab (auto-triggered on confirmed payment)
        printBill(rentalId) {
            if (!rentalId) return;
            const url = '{{ route($panel.'.revenue_expense.print_bill', 'RENTAL_ID') }}'.replace('RENTAL_ID', rentalId);
            window.open(url, '_blank');
        },

        init() {
            // x-model bindings in the Add Charge modal need every type key to exist.
            this.charges = this.freshCharges();
            // Pin the Add Charge sheet to the *visible* viewport while it is open —
            // same approach as the assign-tenant modal — so the Cancel/Save footer
            // stays reachable when the phone keyboard is up.
            this.$watch('showAddCharge', (open) => {
                if (open) { this.lockScroll(); } else { this.unlockScroll(); }
                this.$nextTick(() => this.syncAddChargeViewport());
            });
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', () => this.syncAddChargeViewport());
                window.visualViewport.addEventListener('scroll', () => this.syncAddChargeViewport());
            }
            // After a confirmed cash/bank payment the page reloads — print the bill.
            @if(session('print_bill_rental'))
            this.printBill('{{ session('print_bill_rental') }}');
            @endif
        },

        // Receipt Modal (in-page iframe preview)
        showReceipt: false,
        receiptUrl: '',

        // Charges Receipt Modal
        showChargesReceipt: false,
        viewRentalId: null,
        viewTenant: '',
        viewApt: '',
        viewCharges: [],
        viewRent: 0,
        viewFixed: 0,

        // Add Charge Modal (tap type → enter amount)
        showAddCharge: false,
        chargeRentalId: null,
        chargeTenant: '',
        chargeApt: '',
        isSubmitting: false,
        khrCurrency: {{ currency_is_khr() ? 'true' : 'false' }},
        chargeTypes: ['electricity','water','internet','parking','trash','other'],
        charges: {},
        // Default monthly fees from Settings (stored in USD, shown in the display
        // currency). Only flat-fee types have a default; electricity/water are
        // metered and "other" has no configured fee. Blank when unset.
        chargeDefaults: @js([
            'internet' => filled(settings('utility_internet_fee')) ? money_input(settings('utility_internet_fee')) : '',
            'parking'  => filled(settings('utility_parking_fee')) ? money_input(settings('utility_parking_fee')) : '',
            'trash'    => filled(settings('utility_garbage_fee')) ? money_input(settings('utility_garbage_fee')) : '',
        ]),
        // Metered billing (electricity/water). When on, the amount is computed from
        // the readings (usage × unit rate) and locked; the rates are display-currency
        // and the final charge is recomputed server-side. meterContext carries the
        // per-rental roll-over prefill: {rentalId: {type: {start, out, has_open}}}.
        meteredTypes: ['electricity', 'water'],
        meterAutoCalc: {{ $meterAutoCalc ? 'true' : 'false' }},
        meterRates: { electricity: {{ (float) $electricityRate }}, water: {{ (float) $waterRate }} },
        meterContext: @js($meterContext),
        freshCharges() {
            return Object.fromEntries(this.chargeTypes.map(t => [t, { active: false, meter_in: '', meter_out: '', amount: '' }]));
        },
        isMetered(type) {
            return this.meteredTypes.includes(type);
        },
        // Auto-calc is only meaningful for a metered type once both readings exist.
        autoAmount(type) {
            const usage = this.chargeUsage(type);
            if (usage === null) return null;
            return (parseFloat(usage) * (this.meterRates[type] || 0));
        },
        autoAmountDisplay(type) {
            const amt = this.autoAmount(type);
            if (amt === null) return '';
            return amt.toFixed(this.khrCurrency ? 0 : 2);
        },
        // Keep the (locked) amount field in sync with the readings in auto mode.
        syncMeterAmount(type) {
            if (!this.meterAutoCalc || !this.isMetered(type)) return;
            const disp = this.autoAmountDisplay(type);
            this.charges[type].amount = disp;
        },
        // A row can be saved if it carries a real amount OR (for metered types) a
        // standalone opening reading — recording just the starting number.
        chargeSaveable(type) {
            const c = this.charges[type];
            if (parseFloat(c.amount) > 0) return true;
            return this.isMetered(type) && c.meter_in !== '' && c.meter_out === '';
        },

        // Checkout Modal
        showCheckout: false,
        checkoutRentalId: null,
        checkoutTenant: '',
        checkoutApt: '',
        checkoutRent: 0,
        checkoutUtilities: 0,
        checkoutOtherCharges: 0,
        checkoutFixed: 0,
        checkoutTotal: 0,
        checkoutLateFee: 0,
        checkoutOverdueDays: 0,
        lateFeePercent: {{ (float) settings('late_fee_percent', 0) }},
        checkoutMethod: 'cash',
        payRent: true,
        payUtilities: true,

        // KHQR (KHQRPay) flow
        khqrActive: false,
        khqrLoading: false,
        khqrUrl: '',
        khqrAmount: '0.00',
        khqrStatusUrl: '',
        khqrPaid: false,
        khqrError: '',
        khqrTimer: null,
        khqrChannel: 'api',
        khqrBank: {},
        khqrConfirmUrl: '',
        khqrConfirming: false,
        khqrExpired: false,
        khqrForm: null,
        khqrCountdown: '',
        khqrCountdownTimer: null,

        matchesFilter(status, tenantName, aptNumber) {
            if (this.filter !== 'all' && status !== this.filter) return false;
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                return tenantName.includes(query) || aptNumber.includes(query);
            }
            return true;
        },

        // Show a floor group header only when at least one of its bills passes the current filter/search
        floorHasMatch(bills) {
            return bills.some(b => this.matchesFilter(b.status, b.tenant, b.apt));
        },

        openReceipt(url) {
            this.receiptUrl = url;
            this.showReceipt = true;
        },
        closeReceipt() {
            this.showReceipt = false;
            this.receiptUrl = '';
        },
        printReceiptFrame() {
            const frame = this.$refs.receiptFrame;
            if (frame && frame.contentWindow) {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            }
        },

        openChargesReceipt(rentalId, tenant, apt, charges, rent, fixed) {
            this.viewRentalId = rentalId;
            this.viewTenant = tenant;
            this.viewApt = apt;
            this.viewCharges = charges;
            this.viewRent = rent;
            this.viewFixed = fixed;
            this.showChargesReceipt = true;
        },
        viewBillTotal() {
            const chargesSum = this.viewCharges.reduce((s, c) => s + (parseFloat(c.amount) || 0), 0);
            return (parseFloat(this.viewRent) + parseFloat(this.viewFixed) + chargesSum).toFixed(2);
        },
        async removeViewCharge(chargeId, index) {
            if (!(await window.confirmAction({ message: '{{ __('messages.remove_charge_confirm') }}' }))) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                const res = await fetch('{{ url('/'.$panel.'/revenue-expense/remove-charge') }}/' + chargeId, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                if (res.ok) {
                    this.viewCharges.splice(index, 1);
                } else {
                    window.location.reload();
                }
            } catch(e) { window.location.reload(); }
        },
        async clearAllCharges() {
            if (!(await window.confirmAction({ message: '{{ __('messages.delete_all_confirm') }}' }))) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                await fetch('{{ url('/'.$panel.'/revenue-expense/clear-charges') }}/' + this.viewRentalId, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                window.location.reload();
            } catch(e) { window.location.reload(); }
        },

        // iOS-safe body scroll lock. body{overflow:hidden} is unreliable on iOS
        // Safari — pinning the body with position:fixed actually freezes it; the
        // scroll position is restored on close.
        lockedScrollY: 0,
        lockScroll() {
            this.lockedScrollY = window.scrollY || window.pageYOffset || 0;
            document.body.style.position = 'fixed';
            document.body.style.top = `-${this.lockedScrollY}px`;
            document.body.style.left = '0';
            document.body.style.right = '0';
            document.body.style.width = '100%';
        },
        unlockScroll() {
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            document.body.style.width = '';
            window.scrollTo(0, this.lockedScrollY);
        },
        // Size the sheet to the visual viewport (which shrinks when the on-screen
        // keyboard opens) instead of the layout viewport, so the footer buttons
        // never end up hidden behind the keyboard.
        syncAddChargeViewport() {
            const vv = window.visualViewport;
            const modal = this.$refs.addChargeModal;
            const wrap = this.$refs.addChargeWrap;
            const card = this.$refs.addChargeCard;
            if (!vv || !modal || !wrap || !card) return;
            if (!this.showAddCharge) {
                modal.style.height = '';
                modal.style.top = '';
                modal.style.bottom = '';
                wrap.style.minHeight = '100dvh';
                card.style.maxHeight = '';
                return;
            }
            modal.style.height = vv.height + 'px';
            modal.style.top = vv.offsetTop + 'px';
            modal.style.bottom = 'auto';
            wrap.style.minHeight = vv.height + 'px';
            // Cap the card at 80% of the *visible* viewport so it always floats
            // centered with clear space above and below — the footer buttons must
            // never sit against the screen edge / browser UI.
            card.style.maxHeight = Math.min(vv.height - 32, Math.round(vv.height * 0.8)) + 'px';
        },

        // The roll-over context for the rental whose modal is open.
        meterCtx(type) {
            const byType = this.meterContext[this.chargeRentalId] || {};
            return byType[type] || { start: '', out: '', has_open: false };
        },

        openAddCharge(rentalId, tenant, apt) {
            this.chargeRentalId = rentalId;
            this.chargeTenant = tenant;
            this.chargeApt = apt;
            this.charges = this.freshCharges();
            // Seed metered readings from the roll-over context (this month's opening
            // reading, or last month's closing reading carried forward).
            this.meteredTypes.forEach(t => {
                const ctx = this.meterCtx(t);
                this.charges[t].meter_in = ctx.start ?? '';
                this.charges[t].meter_out = ctx.out ?? '';
                this.syncMeterAmount(t);
            });
            this.isSubmitting = false;
            this.showAddCharge = true;
        },

        toggleCharge(type) {
            const c = this.charges[type];
            c.active = !c.active;
            if (c.active) {
                if (this.isMetered(type)) {
                    // (Re)seed the continuous meter reading when re-opening the panel.
                    const ctx = this.meterCtx(type);
                    if (c.meter_in === '') c.meter_in = ctx.start ?? '';
                    if (c.meter_out === '') c.meter_out = ctx.out ?? '';
                    this.syncMeterAmount(type);
                } else if (!c.amount && this.chargeDefaults[type]) {
                    // Prefill the configured default fee (internet/parking/trash) when
                    // the amount is still empty; the user can override before saving.
                    c.amount = this.chargeDefaults[type];
                }
            } else {
                c.meter_in = ''; c.meter_out = ''; c.amount = '';
            }
        },

        activeChargeCount() {
            return this.chargeTypes.filter(t => this.charges[t].active).length;
        },

        filledChargeCount() {
            return this.chargeTypes.filter(t => this.charges[t].active && this.chargeSaveable(t)).length;
        },

        chargeUsage(type) {
            const c = this.charges[type];
            const inV = parseFloat(c.meter_in), outV = parseFloat(c.meter_out);
            if (isNaN(inV) || isNaN(outV) || outV < inV) return null;
            return (Math.round((outV - inV) * 100) / 100).toString();
        },

        chargeUsageInvalid(type) {
            const c = this.charges[type];
            const inV = parseFloat(c.meter_in), outV = parseFloat(c.meter_out);
            return !isNaN(inV) && !isNaN(outV) && outV < inV;
        },

        chargesTotal() {
            const sum = this.chargeTypes.reduce((s, t) => s + (this.charges[t].active ? (parseFloat(this.charges[t].amount) || 0) : 0), 0);
            return sum.toFixed(this.khrCurrency ? 0 : 2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        openCheckout(rentalId, tenant, apt, rent, utilities, otherCharges, fixed, total, lateFee = 0, overdueDays = 0) {
            this.checkoutRentalId = rentalId;
            this.checkoutTenant = tenant;
            this.checkoutApt = apt;
            this.checkoutRent = rent;
            this.checkoutUtilities = utilities;
            this.checkoutOtherCharges = otherCharges;
            this.checkoutFixed = fixed;
            this.checkoutTotal = total;
            this.checkoutLateFee = lateFee;
            this.checkoutOverdueDays = overdueDays;
            this.checkoutMethod = 'cash';
            this.payRent = true;
            this.payUtilities = true;
            this.resetKhqr();
            this.showCheckout = true;
        },

        // ---- KHQR (KHQRPay) ----
        onCheckoutSubmit(e) {
            // Cash / Bank keep the normal form POST; KHQR is handled via fetch.
            if (this.checkoutMethod === 'khqr') {
                e.preventDefault();
                this.generateKhqr(e.target);
            }
        },

        resetKhqr() {
            this.stopKhqrPoll();
            this.stopKhqrCountdown();
            this.khqrActive = false;
            this.khqrLoading = false;
            this.khqrUrl = '';
            this.khqrAmount = '0.00';
            this.khqrStatusUrl = '';
            this.khqrPaid = false;
            this.khqrError = '';
            this.khqrChannel = 'api';
            this.khqrBank = {};
            this.khqrConfirmUrl = '';
            this.khqrConfirming = false;
            this.khqrExpired = false;
            this.khqrCountdown = '';
        },

        closeCheckout() {
            this.resetKhqr();
            this.showCheckout = false;
        },

        async generateKhqr(form) {
            this.khqrForm = form;
            this.khqrError = '';
            this.khqrPaid = false;
            this.khqrExpired = false;
            this.khqrUrl = '';
            this.khqrLoading = true;
            this.khqrActive = true;
            this.stopKhqrCountdown();
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                const res = await fetch('{{ route($panel.'.revenue_expense.khqr_generate') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: new FormData(form)
                });
                const j = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(j.message || ('HTTP ' + res.status));
                this.khqrUrl = j.qr_url || '';
                this.khqrAmount = j.amount || this.khqrAmount;
                this.khqrStatusUrl = j.status_url || '';
                this.khqrChannel = j.channel || 'api';
                this.khqrBank = j.bank || {};
                this.khqrConfirmUrl = j.confirm_url || '';
                this.khqrLoading = false;
                if (!this.khqrUrl && this.khqrChannel !== 'manual') {
                    this.khqrError = '{{ __('messages.khqr_no_qr') }}';
                    return;
                }
                if (this.khqrChannel === 'api') {
                    this.startKhqrPoll();
                    this.startKhqrCountdown(j.expires_at);
                }
            } catch (err) {
                this.khqrLoading = false;
                this.khqrError = err.message || 'Failed to generate KHQR.';
            }
        },

        regenerateKhqr() {
            if (this.khqrForm) this.generateKhqr(this.khqrForm);
        },

        // Manual channel: the landlord checks their banking app, then confirms.
        async confirmKhqrManual() {
            if (!this.khqrConfirmUrl || this.khqrConfirming) return;
            this.khqrConfirming = true;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                const res = await fetch(this.khqrConfirmUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const j = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(j.message || ('HTTP ' + res.status));
                if (j.paid) {
                    this.khqrPaid = true;
                    this.printBill(this.checkoutRentalId);
                    setTimeout(() => window.location.reload(), 1300);
                }
            } catch (err) {
                this.khqrError = err.message || 'Failed to confirm payment.';
            } finally {
                this.khqrConfirming = false;
            }
        },

        startKhqrPoll() {
            this.stopKhqrPoll();
            this.khqrTimer = setInterval(() => this.checkKhqr(), 3500);
        },

        stopKhqrPoll() {
            if (this.khqrTimer) { clearInterval(this.khqrTimer); this.khqrTimer = null; }
        },

        async checkKhqr() {
            if (!this.khqrStatusUrl) return;
            // Open states the gateway may still advance to "paid".
            const OPEN = ['pending', 'qr_generated', 'waiting_payment'];
            try {
                const res = await fetch(this.khqrStatusUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                if (!res.ok) return; // transient server hiccup — keep polling
                const j = await res.json().catch(() => ({}));
                if (j.paid) {
                    this.khqrPaid = true;
                    this.stopKhqrPoll();
                    this.stopKhqrCountdown();
                    this.printBill(this.checkoutRentalId);
                    setTimeout(() => window.location.reload(), 1300);
                    return;
                }
                // Anything neither paid nor still open is terminal (expired /
                // failed / cancelled) — stop and offer a fresh QR instead of
                // spinning "waiting for payment" forever.
                if (j.status && !OPEN.includes(j.status)) {
                    this.stopKhqrPoll();
                    this.stopKhqrCountdown();
                    this.khqrExpired = true;
                    this.khqrCountdown = '';
                }
            } catch (e) { /* keep polling */ }
        },

        // Live countdown to the QR's expiry, so the payer knows their window.
        startKhqrCountdown(expiresAt) {
            this.stopKhqrCountdown();
            const deadline = expiresAt ? Date.parse(expiresAt) : NaN;
            if (isNaN(deadline)) { this.khqrCountdown = ''; return; }
            const tick = () => {
                const secs = Math.max(0, Math.round((deadline - Date.now()) / 1000));
                const m = Math.floor(secs / 60);
                const s = secs % 60;
                this.khqrCountdown = m + ':' + String(s).padStart(2, '0');
                if (secs <= 0) this.stopKhqrCountdown(); // poll flips to expired
            };
            tick();
            this.khqrCountdownTimer = setInterval(tick, 1000);
        },

        stopKhqrCountdown() {
            if (this.khqrCountdownTimer) { clearInterval(this.khqrCountdownTimer); this.khqrCountdownTimer = null; }
        },

        saveDone() {
            // Block a metered close whose usage is negative (out < in).
            const invalid = this.chargeTypes.find(t => this.charges[t].active && this.chargeUsageInvalid(t));
            if (invalid) { alert('{{ __('messages.meter_out_lt_in') }}'); return; }

            const filled = this.chargeTypes
                .filter(t => this.charges[t].active && this.chargeSaveable(t))
                .map(t => ({ type: t, meter_in: this.charges[t].meter_in, meter_out: this.charges[t].meter_out, amount: this.charges[t].amount }));
            if (filled.length === 0) {
                alert('{{ __('messages.enter_charge_alert') }}');
                return;
            }
            this.submitAllCharges(filled);
        },

        async submitAllCharges(rows) {
            this.isSubmitting = true;
            const addChargeUrl = '{{ route($panel.'.revenue_expense.add_charge') }}';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const failures = [];

            for (const charge of rows) {
                const form = new FormData();
                form.append('_token', csrf);
                form.append('rental_id', this.chargeRentalId);
                form.append('billing_month', '{{ $currentMonth }}');
                form.append('billing_year', '{{ $currentYear }}');
                form.append('charge_type', charge.type);
                form.append('meter_reading_in', charge.meter_in || '');
                form.append('meter_reading_out', charge.meter_out || '');
                // Opening readings carry no amount; send blank so the server records
                // them as a $0 opening row instead of NaN.
                const amt = parseFloat(charge.amount);
                form.append('charge_amount', amt > 0 ? amt.toFixed(2) : '');
                form.append('note', '');

                try {
                    const res = await fetch(addChargeUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: form
                    });
                    if (!res.ok) {
                        let msg = 'HTTP ' + res.status;
                        try { const j = await res.json(); msg = j.message || JSON.stringify(j.errors) || msg; } catch(e) {}
                        failures.push({ type: charge.type, msg });
                    }
                } catch (err) {
                    failures.push({ type: charge.type, msg: err.message });
                }
            }

            if (failures.length > 0) {
                this.isSubmitting = false;
                alert('{{ __('messages.failed_to_save') }} ' + failures.map(f => f.type + ' (' + f.msg + ')').join(', '));
            } else {
                window.location.reload();
            }
        },

        calculateCheckoutTotal() {
            let total = 0;
            if (this.payRent) total += parseFloat(this.checkoutRent) || 0;
            if (this.payUtilities) {
                total += parseFloat(this.checkoutUtilities) || 0;
                total += parseFloat(this.checkoutOtherCharges) || 0;
            }
            total += parseFloat(this.checkoutFixed) || 0;
            total += parseFloat(this.checkoutLateFee) || 0;
            return total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    };
}
</script>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
