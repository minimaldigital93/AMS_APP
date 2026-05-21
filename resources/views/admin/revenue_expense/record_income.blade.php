@extends('layouts.admin')

@section('content')
<div class="max-w-6xl mx-auto space-y-8" x-data="billingManager()">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">Monthly Billing & Payments</h1>
            <p class="text-slate-400 text-sm mt-1">
                Manage tenant payments for <span class="font-semibold text-sky-600">{{ $selectedDate->format('F Y') }}</span>
                — Fiscal Period: <span class="font-semibold text-sky-600">{{ $activePeriod->name }}</span>
            </p>
        </div>
        <a href="{{ route('admin.revenue_expense.index') }}" class="inline-flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back
        </a>
    </div>

    <!-- Month Navigation -->
    <div class="flex items-center justify-center">
        <div class="inline-flex items-center bg-white rounded-xl border border-slate-100 px-2 py-1.5 gap-1">
            <a href="{{ route('admin.revenue_expense.record_income', ['month' => $prevDate->month, 'year' => $prevDate->year]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="Previous Month">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="px-4 py-2 min-w-[180px] text-center">
                <span class="text-lg font-bold text-slate-800">{{ $selectedDate->format('F') }}</span>
                <span class="text-lg text-slate-400 ml-1">{{ $selectedDate->format('Y') }}</span>
                @if(!$isCurrentMonth)
                    @if($isFutureMonth)
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-50 text-sky-700">Upcoming</span>
                    @else
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Past</span>
                    @endif
                @else
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Current</span>
                @endif
            </div>
            <a href="{{ route('admin.revenue_expense.record_income', ['month' => $nextDate->month, 'year' => $nextDate->year]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="Next Month">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @if(!$isCurrentMonth)
            <a href="{{ route('admin.revenue_expense.record_income') }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition" title="Go to current month">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Today
            </a>
            @endif
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-100 rounded-lg px-4 py-3 text-emerald-700 text-sm flex items-center gap-2.5">
        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-red-600 text-sm flex items-center gap-2.5">
        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
        {{ session('error') }}
    </div>
    @endif
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
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-sky-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Total Expected</p>
                    <p class="text-xl font-bold text-sky-600">${{ number_format($totalRentExpected, 2) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ count($tenantBillsAll ?? $tenantBills) }} active tenant(s)</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Collected</p>
                    <p class="text-xl font-bold text-emerald-600">${{ number_format($totalRentCollected, 2) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ $paidCount }} tenant(s) paid</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Pending</p>
                    <p class="text-xl font-bold text-amber-600">${{ number_format($totalPending, 2) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ $pendingCount }} pending</p>
        </div>
        @if($isFutureMonth)
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-sky-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Upcoming</p>
                    <p class="text-xl font-bold text-sky-600">{{ $pendingCount }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">Scheduled for {{ $selectedDate->format('F') }}</p>
        </div>
        @else
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Overdue</p>
                    <p class="text-xl font-bold text-red-600">{{ $overdueCount }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">Past due date</p>
        </div>
        @endif
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-xl border border-slate-100 p-4 flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-slate-500">Filter:</span>
            <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition">All ({{ count($tenantBillsAll ?? $tenantBills) }})</button>
            @if(!$isFutureMonth)
            <button @click="filter = 'overdue'" :class="filter === 'overdue' ? 'bg-red-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition">Overdue ({{ $overdueCount }})</button>
            @endif
            <button @click="filter = 'pending'" :class="filter === 'pending' ? 'bg-amber-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition">{{ $isFutureMonth ? 'Upcoming' : 'Pending' }} ({{ $pendingCount }})</button>
            <button @click="filter = 'paid'" :class="filter === 'paid' ? 'bg-emerald-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition">Paid ({{ $paidCount }})</button>
        </div>
        <div class="flex-1"></div>
        <div class="relative">
            <input type="text" x-model="searchQuery" placeholder="Search tenant or apartment..."
                class="pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500 w-64">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
    </div>

    <!-- Tenant Billing Table -->
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Tenant Bills — {{ $selectedDate->format('F Y') }}
            </h2>
        </div>

        @if(count($tenantBillsAll ?? $tenantBills) > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50/80">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">Apartment</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">Tenant</th>
                        <th class="hidden lg:table-cell px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">Rent</th>
                        <th class="hidden lg:table-cell px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">Charges</th>
                        <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">Total</th>
                        <th class="px-3 py-3 text-center text-[11px] font-medium text-slate-400 uppercase tracking-wider">Due</th>
                        <th class="px-3 py-3 text-center text-[11px] font-medium text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-3 py-3 text-center text-[11px] font-medium text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($tenantBills as $index => $bill)
                    <tr x-show="matchesFilter('{{ $bill['status'] }}', '{{ strtolower($bill['tenant']->name ?? '') }}', '{{ strtolower($bill['apartment']->apartment_number ?? '') }}')"
                        class="hover:bg-slate-50/50 transition {{ $bill['status'] === 'overdue' ? 'bg-red-50/50' : ($bill['status'] === 'paid' ? 'bg-emerald-50/50' : ($isFutureMonth ? 'bg-sky-50/30' : '')) }}">
                        <td class="px-4 py-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-sky-50 rounded-lg flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-800">{{ $bill['apartment']->apartment_number }}</p>
                                    <p class="text-xs text-slate-400">Floor {{ $bill['apartment']->floor->floor_number ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <p class="font-medium text-slate-700">{{ $bill['tenant']->name ?? 'N/A' }}</p>
                            <p class="text-xs text-slate-400">{{ $bill['tenant']->phone ?? '' }}</p>
                        </td>
                        <td class="hidden lg:table-cell px-4 py-4 text-right">
                                <span class="font-semibold text-slate-800">${{ number_format($bill['monthly_rent'], 2) }}</span>
                        </td>
                        <td class="hidden lg:table-cell px-4 py-4 text-right">
                            @php
                                $extraCharges = $bill['total_utilities'] + $bill['total_fixed'];
                                $chargesCount = $bill['utilities']->count() + $bill['fixed_expenses']->count();
                            @endphp
                            @if($extraCharges > 0)
                                <span class="font-medium text-amber-600">${{ number_format($extraCharges, 2) }}</span>
                                <p class="text-xs text-slate-400">{{ $chargesCount }} charge(s)</p>
                            @else
                                <span class="text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-right">
                            <span class="text-lg font-bold {{ $bill['status'] === 'paid' ? 'text-emerald-600' : 'text-slate-800' }}">${{ number_format($bill['total_bill'], 2) }}</span>
                        </td>
                        <td class="px-3 py-4 text-center">
                            <span class="text-sm text-slate-600 whitespace-nowrap">{{ $bill['due_date']->format('M d') }}</span>
                            @if($bill['status'] === 'overdue')
                                <p class="text-xs text-red-500 font-medium">{{ (int) ($isPastMonth ? $selectedDate->copy()->endOfMonth() : now())->diffInDays($bill['due_date']) }} days late</p>
                            @elseif($bill['status'] === 'pending' && ($isFutureMonth || $isCurrentMonth))
                                @php
                                    // Time-elapsed progress (matches Active Tenants): days elapsed in the selected month / total days in month
                                    $monthStart = $selectedDate->copy()->startOfMonth()->startOfDay();
                                    $monthEnd = $monthStart->copy()->endOfMonth();
                                    $totalDaysInMonth = $monthStart->daysInMonth;

                                    if ($isFutureMonth) {
                                        $progressPct = 0;
                                        $daysRemaining = $totalDaysInMonth;
                                    } else {
                                        $rentalStart = \Carbon\Carbon::parse($bill['rental']->start_date)->startOfDay();
                                        $stayStart = $rentalStart->greaterThan($monthStart) ? $rentalStart : $monthStart;
                                        $stayEnd = now()->greaterThan($monthEnd) ? $monthEnd : now();
                                        $daysStayed = $stayEnd->greaterThanOrEqualTo($stayStart)
                                            ? min((int) $stayStart->diffInDays($stayEnd) + 1, $totalDaysInMonth)
                                            : 0;
                                        $progressPct = $totalDaysInMonth > 0 ? round(($daysStayed / $totalDaysInMonth) * 100) : 0;
                                        $daysRemaining = max(0, $totalDaysInMonth - $daysStayed);
                                    }
                                @endphp
                                <div class="mt-1.5 w-full">
                                    <div class="w-full bg-slate-200 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full {{ $progressPct > 75 ? 'bg-amber-500' : 'bg-sky-500' }}" style="width: {{ $progressPct }}%"></div>
                                    </div>
                                    <p class="text-xs {{ $daysRemaining <= 5 && $isCurrentMonth ? 'text-amber-500' : 'text-sky-500' }} font-medium mt-0.5">
                                        @if($isFutureMonth)
                                            Upcoming
                                        @else
                                            {{ $daysRemaining }} day{{ $daysRemaining !== 1 ? 's' : '' }} left
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </td>
                        <td class="px-3 py-4 text-center">
                            @if($bill['status'] === 'paid')
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    Paid
                                </span>
                            @elseif($bill['status'] === 'overdue')
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    Overdue
                                </span>
                            @elseif($isFutureMonth)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-sky-50 text-sky-700">
                                    Upcoming
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                    Pending
                                </span>
                            @endif
                        </td>
                        <td class="px-2 py-4 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <!-- Add Charge Button -->
                                @if($bill['status'] !== 'paid')
                                <button @click="openAddCharge({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? 'Tenant') }}', '{{ $bill['apartment']->apartment_number }}')"
                                    class="p-2 text-orange-600 hover:bg-orange-50 rounded-lg transition" title="Add Charge">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                </button>
                                @endif

                                <!-- View Charges Receipt -->
                                @php
                                $chargesJson = $bill['utilities']->map(fn($u) => [
                                    'id'     => $u->id,
                                    'type'   => $u->utility_type,
                                    'amount' => (float) $u->charge_amount,
                                    'paid'   => (bool) $u->paid_status,
                                ])->values();
                                @endphp
                                <button @click="openChargesReceipt({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? 'Tenant') }}', '{{ $bill['apartment']->apartment_number }}', {{ $chargesJson->toJson() }}, {{ $bill['monthly_rent'] }}, {{ $bill['total_fixed'] }})"
                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Charges">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>

                                <!-- Print Bill -->
                                <a href="{{ route('admin.revenue_expense.print_bill', $bill['rental']->id) }}" target="_blank"
                                    class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg transition" title="Print Bill">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                </a>

                                <!-- Checkout / Pay -->
                                @if($bill['status'] !== 'paid')
                                <button @click="openCheckout({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? 'Tenant') }}', '{{ $bill['apartment']->apartment_number }}', {{ $bill['monthly_rent'] }}, {{ $bill['total_utility_only'] }}, {{ $bill['total_other_charges'] }}, {{ $bill['total_fixed'] }}, {{ $bill['total_bill'] }})"
                                    class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition" title="Checkout & Pay">
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
        @else
        <div class="text-center py-16">
            <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <p class="text-slate-500 text-lg">No active rentals found</p>
            <p class="text-slate-400 text-sm mt-1">Tenants with active rental agreements will appear here automatically.</p>
        </div>
        @endif
        @if((isset($tenantBills) && method_exists($tenantBills, 'links')))
        <div class="px-6 py-4 border-t border-slate-100">{{ $tenantBills->appends(request()->query())->links() }}</div>
        @endif
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
                            <p class="text-xs text-slate-400">Charge Receipt</p>
                        </div>
                    </div>
                    <button @click="showChargesReceipt = false" class="text-slate-400 hover:text-slate-600 w-7 h-7 flex items-center justify-center rounded-lg hover:bg-slate-100 transition text-lg">&times;</button>
                </div>

                <!-- Charge list -->
                <div class="px-5 py-4 space-y-1 max-h-72 overflow-y-auto">
                    <!-- Rent row (read-only) -->
                    <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-sky-50/60">
                        <span class="text-sm text-slate-600 font-medium">Rent</span>
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
                                <span class="text-sm text-slate-600 capitalize" x-text="c.type"></span>
                                <span x-show="c.paid" class="text-[10px] font-medium text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">paid</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold" :class="c.paid ? 'text-emerald-600' : 'text-amber-700'" x-text="'$' + parseFloat(c.amount).toFixed(2)"></span>
                                <button x-show="!c.paid" @click="removeViewCharge(c.id, i)"
                                    class="opacity-0 group-hover:opacity-100 w-5 h-5 flex items-center justify-center text-red-400 hover:text-red-600 rounded transition"
                                    title="Remove charge">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Fixed charges -->
                    <template x-if="viewFixed > 0">
                        <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-purple-50/60">
                            <span class="text-sm text-slate-600">Fixed Charges</span>
                            <span class="text-sm font-semibold text-slate-700" x-text="'$' + parseFloat(viewFixed).toFixed(2)"></span>
                        </div>
                    </template>

                    <!-- Empty state -->
                    <div x-show="viewCharges.length === 0 && viewFixed <= 0" class="text-center py-6 text-slate-400 text-sm">
                        No charges added yet
                    </div>
                </div>

                <!-- Total + actions -->
                <div class="px-5 pt-3 pb-5 border-t border-slate-100 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-700">Total Bill</span>
                        <span class="text-xl font-bold text-sky-600" x-text="'$' + viewBillTotal()"></span>
                    </div>

                    <!-- Delete all unpaid -->
                    <button x-show="viewCharges.some(c => !c.paid)"
                        @click="clearAllCharges()"
                        class="w-full flex items-center justify-center gap-2 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete All Unpaid Charges
                    </button>

                    <button @click="showChargesReceipt = false"
                        class="w-full py-2 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
                        Close
                    </button>
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
                    <p class="text-xs text-slate-400">Enter amounts for the rows you want to bill. Empty rows are skipped.</p>

                    <template x-for="(row, i) in chargeRows" :key="row.type">
                        <div class="grid grid-cols-12 gap-2 items-center">
                            <div class="col-span-3 text-sm text-slate-600 capitalize" x-text="row.type"></div>
                            <template x-if="row.type === 'electricity' || row.type === 'water'">
                                <div class="col-span-6 grid grid-cols-2 gap-2">
                                    <input type="number" x-model="row.meter_in" step="0.01" min="0" placeholder="In"
                                        class="w-full px-2 py-1.5 border border-slate-200 rounded-md text-sm">
                                    <input type="number" x-model="row.meter_out" step="0.01" min="0" placeholder="Out"
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
                        <span class="text-xs text-slate-500">Total: <span class="font-semibold text-slate-800" x-text="'$' + chargesTotal()"></span></span>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="showAddCharge = false" class="px-3 py-1.5 text-sm text-slate-500 hover:text-slate-700">Cancel</button>
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
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showCheckout = false"></div>
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md relative z-10 flex flex-col" style="max-height:90dvh;max-height:90vh;">
                <!-- Header -->
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 bg-emerald-50 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800"><span x-text="checkoutApt"></span> — <span x-text="checkoutTenant"></span></p>
                            <p class="text-xs text-slate-400">Monthly Payment</p>
                        </div>
                    </div>
                    <button @click="showCheckout = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition text-lg leading-none">&times;</button>
                </div>
                <form action="{{ route('admin.revenue_expense.checkout') }}" method="POST" class="p-5 space-y-4 overflow-y-auto flex-1">
                    @csrf
                    <input type="hidden" name="rental_id" x-model="checkoutRentalId">
                    <input type="hidden" name="rent_amount" x-model="checkoutRent">
                    <!-- Bill lines -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-50">
                            <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                                <input type="checkbox" name="pay_rent" value="1" x-model="payRent" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                Rent
                            </label>
                            <span class="text-sm font-semibold text-slate-800" x-text="'$' + parseFloat(checkoutRent).toFixed(2)"></span>
                        </div>
                        <div x-show="checkoutUtilities > 0 || checkoutOtherCharges > 0"
                             class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-50">
                            <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                                <input type="checkbox" name="pay_utilities" value="1" x-model="payUtilities" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                Charges
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
                            <span class="text-sm text-slate-500 pl-6">Fixed Charges</span>
                            <span class="text-sm font-medium text-slate-700" x-text="'$' + parseFloat(checkoutFixed).toFixed(2)"></span>
                        </div>
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg bg-slate-50">
                            <span class="text-sm text-slate-500">Late Fee</span>
                            <input type="number" name="late_fee" x-model="checkoutLateFee" step="0.01" min="0" value="0"
                                class="w-20 text-right text-sm border border-slate-200 rounded-lg px-2 py-1 focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div class="flex items-center justify-between pt-2 px-1 border-t border-slate-200">
                            <span class="font-bold text-slate-700">Total</span>
                            <span class="text-xl font-bold text-emerald-600" x-text="'$' + calculateCheckoutTotal()"></span>
                        </div>
                    </div>
                    <!-- Payment method chips -->
                    <div>
                        <p class="text-xs text-slate-400 mb-1.5">Payment Method <span class="text-red-400">*</span></p>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center justify-center gap-2 py-2.5 border rounded-xl cursor-pointer text-sm transition select-none"
                                :class="checkoutMethod === 'cash' ? 'bg-emerald-50 border-emerald-300 text-emerald-700 font-medium' : 'border-slate-200 text-slate-500 hover:border-slate-300'">
                                <input type="radio" name="payment_method" value="cash" x-model="checkoutMethod" class="sr-only" required>
                                💵 Cash
                            </label>
                            <label class="flex items-center justify-center gap-2 py-2.5 border rounded-xl cursor-pointer text-sm transition select-none"
                                :class="checkoutMethod === 'bank' ? 'bg-sky-50 border-sky-300 text-sky-700 font-medium' : 'border-slate-200 text-slate-500 hover:border-slate-300'">
                                <input type="radio" name="payment_method" value="bank" x-model="checkoutMethod" class="sr-only">
                                🏦 Bank Transfer
                            </label>
                        </div>
                    </div>
                    <!-- Date + Reference -->
                    <div class="grid grid-cols-1 gap-3">
                        <div class="min-w-0">
                            <label class="block text-xs text-slate-400 mb-1">Date <span class="text-red-400">*</span></label>
                            <input type="date" name="payment_date" required value="{{ date('Y-m-d') }}"
                                style="max-width:100%;box-sizing:border-box;"
                                class="w-full min-w-0 px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 bg-white">
                        </div>
                        <div class="min-w-0">
                            <label class="block text-xs text-slate-400 mb-1">Reference</label>
                            <input type="text" name="transaction_reference" placeholder="TXN-…"
                                style="max-width:99%;box-sizing:border-box;"
                                class="w-full min-w-0 px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                    </div>
                    <input type="text" name="note" placeholder="Note (optional)"
                        class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">
                    <!-- Buttons -->
                    <div class="flex gap-2 pt-1">
                        <button type="submit"
                            class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition">
                            Confirm Payment
                        </button>
                        <button type="button" @click="showCheckout = false"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-medium rounded-lg transition">
                            Cancel
                        </button>
                    </div>
                </form>
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
        filter: 'all',
        searchQuery: '',

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

        matchesFilter(status, tenantName, aptNumber) {
            if (this.filter !== 'all' && status !== this.filter) return false;
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                return tenantName.includes(query) || aptNumber.includes(query);
            }
            return true;
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
            if (!confirm('Remove this charge?')) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                const res = await fetch('/admin/revenue-expense/remove-charge/' + chargeId, {
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
            if (!confirm('Delete ALL unpaid charges for this tenant? This cannot be undone.')) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                await fetch('/admin/revenue-expense/clear-charges/' + this.viewRentalId, {
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
            this.showCheckout = true;
        },

        saveDone() {
            const filled = this.chargeRows.filter(r => parseFloat(r.amount) > 0);
            if (filled.length === 0) {
                alert('Please enter at least one charge amount before saving.');
                return;
            }
            this.submitAllCharges(filled);
        },

        async submitAllCharges(rows) {
            this.isSubmitting = true;
            const addChargeUrl = '{{ route('admin.revenue_expense.add_charge') }}';
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
                alert('Failed to save: ' + failures.map(f => f.type + ' (' + f.msg + ')').join(', '));
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