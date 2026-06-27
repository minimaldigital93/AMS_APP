@extends('layouts.supervisor')

@section('content')
<div class="max-w-6xl mx-auto space-y-8" x-data="billingManager()">
    <!-- Header -->
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.monthly_billing_payments') }}</h1>
        <a href="{{ route('supervisor.revenue_expense.index') }}" class="inline-flex items-center justify-center h-10 w-10 bg-slate-800 hover:bg-slate-700 text-white rounded-lg transition flex-shrink-0" title="{{ __('messages.back') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
    </div>

    <!-- Month Navigation + Search (one row) -->
    <div class="relative flex flex-wrap items-center justify-center gap-3">
        <div class="inline-flex items-center bg-white rounded-xl border border-slate-100 px-2 py-1.5 gap-1">
            <a href="{{ route('supervisor.revenue_expense.record_income', ['month' => $prevDate->month, 'year' => $prevDate->year]) }}"
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
            <a href="{{ route('supervisor.revenue_expense.record_income', ['month' => $nextDate->month, 'year' => $nextDate->year]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ __('messages.next_month') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @if(!$isCurrentMonth)
            <a href="{{ route('supervisor.revenue_expense.record_income') }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition" title="{{ __('messages.go_to_current_month') }}">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></a>
            @endif
        </div>

        <!-- Search (icon → expands to input): inline on mobile, pinned right on sm+ -->
        <div class="flex items-center justify-end sm:absolute sm:right-0 sm:top-1/2 sm:-translate-y-1/2">
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
    <div class="grid grid-cols-2 gap-3 md:gap-4">
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-sky-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.total_expected') }}</p>
                    <p class="text-xl font-bold text-sky-600">{{ money($totalRentExpected) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ __('messages.active_tenants_n', ['count' => count($tenantBillsAll ?? $tenantBills)]) }}</p>
        </div>
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

    <!-- Tenant Billing Table -->
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <div class="px-4 md:px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-800 flex items-center min-w-0">
                <svg class="w-5 h-5 mr-2 text-sky-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span class="truncate">{{ __('messages.tenant_bills') }} — {{ $selectedDate->format('F Y') }}</span>
            </h2>
            <!-- Floor dropdown (server-side filter) -->
            <select onchange="window.location.href = this.value"
                class="flex-shrink-0 h-9 pl-3 pr-8 text-sm bg-slate-50 border border-slate-200 rounded-lg text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-slate-300 cursor-pointer">
                <option value="{{ request()->fullUrlWithQuery(['floor' => null, 'page' => null]) }}" @selected(!request()->filled('floor'))>{{ __('messages.all_floors') }}</option>
                @foreach($floors as $floor)
                    <option value="{{ request()->fullUrlWithQuery(['floor' => $floor->id, 'page' => null]) }}" @selected(request('floor') == $floor->id)>{{ $floor->floor_name }}</option>
                @endforeach
            </select>
        </div>

        @if(count($tenantBillsAll ?? $tenantBills) > 0)
        <!-- Desktop table (hidden on mobile) -->
        <div class="hidden md:block p-6 overflow-x-auto">
            <table class="min-w-full table-fixed divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-12 px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.no_col') }}</th>
                        <th class="w-1/3 px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.tenant') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.total') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">{{ __('messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($tenantBills as $index => $bill)
                    @php
                        $chargesJson = $bill['utilities']->map(fn($u) => [
                            'id'     => $u->id,
                            'type'   => $u->utility_type,
                            'amount' => (float) $u->charge_amount,
                            'paid'   => (bool) $u->paid_status,
                        ])->values();
                    @endphp
                    <tr x-show="matchesFilter('{{ $bill['status'] }}', '{{ strtolower($bill['tenant']->name ?? '') }}', '{{ strtolower($bill['apartment']->apartment_number ?? '') }}')"
                        class="hover:bg-gray-50 transition {{ $bill['status'] === 'overdue' ? 'bg-red-50/40' : ($bill['status'] === 'paid' ? 'bg-emerald-50/40' : ($isFutureMonth ? 'bg-sky-50/30' : '')) }}">
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600">{{ $tenantBills->firstItem() ? $tenantBills->firstItem() + $loop->index : $loop->iteration }}</td>
                        <td class="px-4 py-4">
                            <div class="flex items-center">
                                <div class="h-10 w-10 rounded-lg bg-sky-50 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <div class="ml-4 min-w-0">
                                    <p class="font-semibold text-slate-800 truncate">{{ $bill['tenant']->name ?? 'N/A' }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $bill['tenant']->phone ?? '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <p class="text-sm font-bold {{ $bill['status'] === 'paid' ? 'text-emerald-600' : 'text-slate-800' }}">{{ money($bill['total_bill']) }}</p>
                            <p class="text-xs text-slate-400">{{ __('messages.rent') }} {{ money($bill['monthly_rent']) }}</p>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            @if($bill['status'] === 'paid')
                                <span class="inline-block px-2 py-1 text-xs font-semibold rounded-md bg-emerald-100 text-emerald-700">{{ __('messages.paid') }}</span>
                            @elseif($bill['status'] === 'overdue')
                                <span class="inline-block px-2 py-1 text-xs font-semibold rounded-md bg-red-100 text-red-700">{{ __('messages.overdue') }}</span>
                            @else
                                <span class="inline-block px-2 py-1 text-xs font-semibold rounded-md bg-amber-100 text-amber-700">{{ $isFutureMonth ? __('messages.upcoming') : __('messages.pending') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center justify-end gap-2">
                                @if($bill['status'] !== 'paid')
                                <button @click="openAddCharge({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? __('messages.tenant')) }}', '{{ $bill['apartment']->apartment_number }}')"
                                    class="inline-flex items-center justify-center h-8 w-8 rounded-md text-orange-600 bg-orange-50 hover:bg-orange-100 transition" title="{{ __('messages.add_charge') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                </button>
                                @endif
                                <button @click="openChargesReceipt({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? __('messages.tenant')) }}', '{{ $bill['apartment']->apartment_number }}', {{ $chargesJson->toJson() }}, {{ $bill['monthly_rent'] }}, {{ $bill['total_fixed'] }})"
                                    class="inline-flex items-center justify-center h-8 w-8 rounded-md text-sky-600 bg-sky-50 hover:bg-sky-100 transition" title="{{ __('messages.view_charges') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                                <button @click="openReceipt('{{ route('supervisor.revenue_expense.print_receipt', ['rental' => $bill['rental']->id, 'month' => $currentMonth, 'year' => $currentYear, 'embed' => 1]) }}')"
                                    class="inline-flex items-center justify-center h-8 w-8 rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 transition" title="{{ __('messages.view_receipt') }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3h12v18l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5L6 21z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6M9 11.5h6M9 15h3.5"/></svg>
                                </button>
                                @if($bill['status'] !== 'paid')
                                <button @click="openCheckout({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? __('messages.tenant')) }}', '{{ $bill['apartment']->apartment_number }}', {{ $bill['monthly_rent'] }}, {{ $bill['total_utility_only'] }}, {{ $bill['total_other_charges'] }}, {{ $bill['total_fixed'] }}, {{ $bill['total_bill'] }})"
                                    class="inline-flex items-center justify-center h-8 w-8 rounded-md text-emerald-600 bg-emerald-50 hover:bg-emerald-100 transition" title="{{ __('messages.checkout_pay') }}">
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

        <!-- Mobile compact list (shown on mobile only) -->
        <div class="md:hidden divide-y divide-slate-100">
            @foreach($tenantBills as $index => $bill)
            @php
                $chargesJson = $bill['utilities']->map(fn($u) => [
                    'id'     => $u->id,
                    'type'   => $u->utility_type,
                    'amount' => (float) $u->charge_amount,
                    'paid'   => (bool) $u->paid_status,
                ])->values();
            @endphp
            <div x-show="matchesFilter('{{ $bill['status'] }}', '{{ strtolower($bill['tenant']->name ?? '') }}', '{{ strtolower($bill['apartment']->apartment_number ?? '') }}')"
                 class="flex items-center gap-3 px-4 py-3 active:bg-slate-50 transition {{ $bill['status'] === 'overdue' ? 'bg-red-50/40' : ($bill['status'] === 'paid' ? 'bg-emerald-50/40' : ($isFutureMonth ? 'bg-sky-50/30' : '')) }}">
                <span class="w-5 text-xs font-medium text-slate-400 text-center flex-shrink-0">{{ $tenantBills->firstItem() ? $tenantBills->firstItem() + $loop->index : $loop->iteration }}</span>
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
                    <button @click="openReceipt('{{ route('supervisor.revenue_expense.print_receipt', ['rental' => $bill['rental']->id, 'month' => $currentMonth, 'year' => $currentYear, 'embed' => 1]) }}')"
                        class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-indigo-600 bg-indigo-50 active:bg-indigo-100 transition" title="{{ __('messages.view_receipt') }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3h12v18l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5L6 21z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6M9 11.5h6M9 15h3.5"/></svg>
                    </button>
                    @if($bill['status'] !== 'paid')
                    <button @click="openCheckout({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? __('messages.tenant')) }}', '{{ $bill['apartment']->apartment_number }}', {{ $bill['monthly_rent'] }}, {{ $bill['total_utility_only'] }}, {{ $bill['total_other_charges'] }}, {{ $bill['total_fixed'] }}, {{ $bill['total_bill'] }})"
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
        @else
        <div class="text-center py-16">
            <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <p class="text-slate-500 text-lg">{{ __('messages.no_active_rentals_found') }}</p>
            <p class="text-slate-400 text-sm mt-1">{{ __('messages.tenants_appear_auto') }}</p>
        </div>
        @endif
        @if((isset($tenantBills) && method_exists($tenantBills, 'links')))
        <div class="px-6 py-4 border-t border-slate-100">{{ $tenantBills->appends(request()->query())->links() }}</div>
        @endif
    </div>

    <!-- ============================================ -->
    <!-- RECEIPT MODAL (in-page preview via iframe)   -->
    <!-- ============================================ -->
    <div x-show="showReceipt" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeReceipt()"></div>
            <div class="relative z-10 w-full max-w-sm bg-white rounded-xl shadow-2xl flex flex-col overflow-hidden" style="max-height:90vh;">
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
                <div class="bg-slate-200 flex-1" style="height:68vh;">
                    <template x-if="showReceipt">
                        <iframe x-ref="receiptFrame" :src="receiptUrl" class="w-full h-full border-0" title="{{ __('messages.payment_receipt') }}"></iframe>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- CHARGES RECEIPT MODAL                        -->
    <!-- ============================================ -->
    <div x-show="showChargesReceipt" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
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
    <!-- ADD CHARGE MODAL (single form, all types)    -->
    <!-- ============================================ -->
    <div x-show="showAddCharge" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showAddCharge = false"></div>
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md relative z-10">
                <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-800">
                        Add Charges — <span x-text="chargeApt" class="text-sky-600"></span>
                    </h3>
                    <button @click="showAddCharge = false" class="text-slate-400 hover:text-slate-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form @submit.prevent="saveDone" class="p-4 space-y-3">
                    <p class="text-xs text-slate-400">{{ __('messages.enter_amounts_hint') }}</p>

                    <template x-for="(row, i) in chargeRows" :key="row.type">
                        <div class="grid grid-cols-12 gap-2 items-center">
                            <div class="col-span-3 text-sm text-slate-600 capitalize" x-text="typeLabels[row.type] || row.type"></div>
                            <template x-if="row.type === 'electricity' || row.type === 'water'">
                                <div class="col-span-6 grid grid-cols-2 gap-2">
                                    <input type="number" x-model="row.meter_in" step="0.01" min="0" placeholder="{{ __('messages.meter_in_ph') }}"
                                        class="w-full px-2 py-1.5 border border-slate-200 rounded-md text-sm">
                                    <input type="number" x-model="row.meter_out" step="0.01" min="0" placeholder="{{ __('messages.meter_out_ph') }}"
                                        class="w-full px-2 py-1.5 border border-slate-200 rounded-md text-sm">
                                </div>
                            </template>
                            <template x-if="row.type !== 'electricity' && row.type !== 'water'">
                                <div class="col-span-6"></div>
                            </template>
                            <input type="number" x-model="row.amount" step="0.01" min="0" placeholder="0.00"
                                class="col-span-3 w-full px-2 py-1.5 border border-slate-200 rounded-md text-sm font-semibold text-right">
                        </div>
                    </template>

                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <span class="text-xs text-slate-500">{{ __('messages.total') }}: <span class="font-semibold text-slate-800" x-text="'$' + chargesTotal()"></span></span>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="showAddCharge = false" class="px-3 py-1.5 text-sm text-slate-500 hover:text-slate-700">{{ __('messages.cancel') }}</button>
                            <button type="submit" :disabled="isSubmitting"
                                class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white rounded-md text-sm font-medium flex items-center gap-1.5">
                                <svg x-show="isSubmitting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                <span x-text="isSubmitting ? 'Saving…' : 'Save'"></span>
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
    <div x-show="showCheckout" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="closeCheckout()"></div>
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative z-10 flex flex-col" style="max-height:90dvh;max-height:90vh;">
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
                <form action="{{ route('supervisor.revenue_expense.checkout') }}" method="POST" class="p-5 space-y-4 overflow-y-auto flex-1" x-show="!khqrActive" @submit="onCheckoutSubmit($event)">
                    @csrf
                    <input type="hidden" name="rental_id" x-model="checkoutRentalId">
                    <input type="hidden" name="rent_amount" x-model="checkoutRent">
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
        filter: 'all',
        searchOpen: false,
        searchQuery: '',

        // Open the printable bill in a new tab (auto-triggered on confirmed payment)
        printBill(rentalId) {
            if (!rentalId) return;
            const url = '{{ route('supervisor.revenue_expense.print_bill', 'RENTAL_ID') }}'.replace('RENTAL_ID', rentalId);
            window.open(url, '_blank');
        },

        init() {
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

        // Add Charge Modal (single form)
        showAddCharge: false,
        chargeRentalId: null,
        chargeTenant: '',
        chargeApt: '',
        isSubmitting: false,
        chargeTypes: ['electricity','water','internet','parking','trash','other'],
        chargeRows: [],

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
                const res = await fetch('{{ url('/supervisor/revenue-expense/remove-charge') }}/' + chargeId, {
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
                await fetch('{{ url('/supervisor/revenue-expense/clear-charges') }}/' + this.viewRentalId, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                window.location.reload();
            } catch(e) { window.location.reload(); }
        },

        openAddCharge(rentalId, tenant, apt) {
            this.chargeRentalId = rentalId;
            this.chargeTenant = tenant;
            this.chargeApt = apt;
            this.chargeRows = this.chargeTypes.map(t => ({ type: t, meter_in: '', meter_out: '', amount: '' }));
            this.isSubmitting = false;
            this.showAddCharge = true;
        },

        chargesTotal() {
            const sum = this.chargeRows.reduce((s, r) => s + (parseFloat(r.amount) || 0), 0);
            return sum.toFixed(2);
        },

        openCheckout(rentalId, tenant, apt, rent, utilities, otherCharges, fixed, total) {
            this.checkoutRentalId = rentalId;
            this.checkoutTenant = tenant;
            this.checkoutApt = apt;
            this.checkoutRent = rent;
            this.checkoutUtilities = utilities;
            this.checkoutOtherCharges = otherCharges;
            this.checkoutFixed = fixed;
            this.checkoutTotal = total;
            this.checkoutLateFee = 0;
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
                const res = await fetch('{{ route('supervisor.revenue_expense.khqr_generate') }}', {
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
            const filled = this.chargeRows.filter(r => parseFloat(r.amount) > 0);
            if (filled.length === 0) {
                alert('{{ __('messages.enter_charge_alert') }}');
                return;
            }
            this.submitAllCharges(filled);
        },

        async submitAllCharges(rows) {
            this.isSubmitting = true;
            const addChargeUrl = '{{ route('supervisor.revenue_expense.add_charge') }}';
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
                form.append('charge_amount', parseFloat(charge.amount).toFixed(2));
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