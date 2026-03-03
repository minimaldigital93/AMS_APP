@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8" x-data="billingManager()">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Monthly Billing & Payments</h1>
            <p class="text-gray-600 mt-2">
                Manage tenant payments for <span class="font-semibold text-blue-600">{{ $selectedDate->format('F Y') }}</span>
                — Fiscal Period: <span class="font-semibold text-blue-600">{{ $activePeriod->name }}</span>
            </p>
        </div>
        <a href="{{ route('admin.revenue_expense.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back
        </a>
    </div>

    <!-- Month Navigation -->
    <div class="mb-6 flex items-center justify-center">
        <div class="inline-flex items-center bg-white rounded-xl shadow-md border border-gray-200 px-2 py-1.5 gap-1">
            <a href="{{ route('admin.revenue_expense.record_income', ['month' => $prevDate->month, 'year' => $prevDate->year]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-blue-600 transition" title="Previous Month">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="px-4 py-2 min-w-[180px] text-center">
                <span class="text-lg font-bold text-gray-900">{{ $selectedDate->format('F') }}</span>
                <span class="text-lg text-gray-500 ml-1">{{ $selectedDate->format('Y') }}</span>
                @if(!$isCurrentMonth)
                    @if($isFutureMonth)
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Upcoming</span>
                    @else
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Past</span>
                    @endif
                @else
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Current</span>
                @endif
            </div>
            <a href="{{ route('admin.revenue_expense.record_income', ['month' => $nextDate->month, 'year' => $nextDate->year]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-blue-600 transition" title="Next Month">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @if(!$isCurrentMonth)
            <a href="{{ route('admin.revenue_expense.record_income') }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition" title="Go to current month">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Today
            </a>
            @endif
        </div>
    </div>

    <!-- Flash Messages -->
    @if(session('success'))
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center">
        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg flex items-center">
        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Total Expected</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1">${{ number_format($totalRentExpected, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">{{ count($tenantBills) }} active tenant(s)</p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Collected</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">${{ number_format($totalRentCollected, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">{{ $paidCount }} tenant(s) paid</p>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Pending</p>
                    <p class="text-2xl font-bold text-orange-600 mt-1">${{ number_format($totalPending, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">{{ $pendingCount }} pending</p>
        </div>
        @if($isFutureMonth)
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-400">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Upcoming</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1">{{ $pendingCount }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Scheduled for {{ $selectedDate->format('F') }}</p>
        </div>
        @else
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Overdue</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">{{ $overdueCount }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Past due date</p>
        </div>
        @endif
    </div>

    <!-- Filter Bar -->
    <div class="bg-white rounded-xl shadow-md p-4 mb-6 flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-gray-600">Filter:</span>
            <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition">All ({{ count($tenantBills) }})</button>
            @if(!$isFutureMonth)
            <button @click="filter = 'overdue'" :class="filter === 'overdue' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition">Overdue ({{ $overdueCount }})</button>
            @endif
            <button @click="filter = 'pending'" :class="filter === 'pending' ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition">{{ $isFutureMonth ? 'Upcoming' : 'Pending' }} ({{ $pendingCount }})</button>
            <button @click="filter = 'paid'" :class="filter === 'paid' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition">Paid ({{ $paidCount }})</button>
        </div>
        <div class="flex-1"></div>
        <div class="relative">
            <input type="text" x-model="searchQuery" placeholder="Search tenant or apartment..."
                class="pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64">
            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>
    </div>

    <!-- Tenant Billing Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Tenant Bills — {{ $selectedDate->format('F Y') }}
            </h2>
        </div>

        @if(count($tenantBills) > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Apartment</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tenant</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Rent</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Charges</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Bill</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Due Date</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($tenantBills as $index => $bill)
                    <tr x-show="matchesFilter('{{ $bill['status'] }}', '{{ strtolower($bill['tenant']->name ?? '') }}', '{{ strtolower($bill['apartment']->apartment_number ?? '') }}')"
                        class="hover:bg-gray-50 transition {{ $bill['status'] === 'overdue' ? 'bg-red-50' : ($bill['status'] === 'paid' ? 'bg-green-50' : ($isFutureMonth ? 'bg-blue-50/30' : '')) }}">
                        <td class="px-4 py-4">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $bill['apartment']->apartment_number }}</p>
                                    <p class="text-xs text-gray-500">Floor {{ $bill['apartment']->floor->floor_number ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <p class="font-medium text-gray-800">{{ $bill['tenant']->name ?? 'N/A' }}</p>
                            <p class="text-xs text-gray-500">{{ $bill['tenant']->phone ?? '' }}</p>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <span class="font-semibold text-gray-900">${{ number_format($bill['monthly_rent'], 2) }}</span>
                        </td>
                        <td class="px-4 py-4 text-right">
                            @php $extraCharges = $bill['total_utilities'] + $bill['total_fixed']; @endphp
                            @if($extraCharges > 0)
                                <span class="font-medium text-orange-600">${{ number_format($extraCharges, 2) }}</span>
                                <p class="text-xs text-gray-400">
                                    {{ $bill['utilities']->count() }} utility, {{ $bill['fixed_expenses']->count() }} fixed
                                </p>
                            @else
                                <span class="text-gray-400">$0.00</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-right">
                            <span class="text-lg font-bold {{ $bill['status'] === 'paid' ? 'text-green-600' : 'text-gray-900' }}">${{ number_format($bill['total_bill'], 2) }}</span>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <span class="text-sm text-gray-700">{{ $bill['due_date']->format('M d') }}</span>
                            @if($bill['status'] === 'overdue')
                                <p class="text-xs text-red-500 font-medium">{{ (int) ($isPastMonth ? $selectedDate->copy()->endOfMonth() : now())->diffInDays($bill['due_date']) }} days late</p>
                            @elseif($bill['status'] === 'pending' && ($isFutureMonth || $isCurrentMonth))
                                @php
                                    $daysUntilDue = (int) now()->diffInDays($bill['due_date'], false);
                                    $totalDaysInMonth = $selectedDate->copy()->daysInMonth;
                                    $daysPassed = $isCurrentMonth ? now()->day : 0;
                                    $progressPct = $isCurrentMonth ? min(100, round(($daysPassed / $totalDaysInMonth) * 100)) : 0;
                                @endphp
                                <div class="mt-1.5 w-full">
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full {{ $progressPct > 75 ? 'bg-orange-500' : 'bg-blue-500' }}" style="width: {{ $progressPct }}%"></div>
                                    </div>
                                    <p class="text-xs {{ $daysUntilDue <= 5 && $isCurrentMonth ? 'text-orange-500' : 'text-blue-500' }} font-medium mt-0.5">
                                        @if($isFutureMonth)
                                            Upcoming
                                        @else
                                            {{ $daysUntilDue }} days left
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-center">
                            @if($bill['status'] === 'paid')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    Paid
                                </span>
                            @elseif($bill['status'] === 'overdue')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    Overdue
                                </span>
                            @elseif($isFutureMonth)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Upcoming
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                                    Pending
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <!-- Add Charge Button -->
                                @if($bill['status'] !== 'paid')
                                <button @click="openAddCharge({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? 'Tenant') }}', '{{ $bill['apartment']->apartment_number }}')"
                                    class="p-2 text-orange-600 hover:bg-orange-50 rounded-lg transition" title="Add Charge">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                </button>
                                @endif

                                <!-- View/Expand Bill Detail -->
                                <button @click="toggleBillDetail({{ $index }})"
                                    class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Bill Detail">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>

                                <!-- Print Bill -->
                                <a href="{{ route('admin.revenue_expense.print_bill', $bill['rental']->id) }}" target="_blank"
                                    class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg transition" title="Print Bill">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                </a>

                                <!-- Checkout / Pay -->
                                @if($bill['status'] !== 'paid')
                                <button @click="openCheckout({{ $bill['rental']->id }}, '{{ addslashes($bill['tenant']->name ?? 'Tenant') }}', '{{ $bill['apartment']->apartment_number }}', {{ $bill['monthly_rent'] }}, {{ $bill['total_utilities'] }}, {{ $bill['total_fixed'] }}, {{ $bill['total_bill'] }})"
                                    class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition" title="Checkout & Pay">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>

                    <!-- Expandable Bill Detail Row -->
                    <tr x-show="expandedBill === {{ $index }}" x-transition
                        class="bg-gray-50">
                        <td colspan="8" class="px-6 py-4">
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                <!-- Rent Details -->
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                        Rent
                                    </h4>
                                    <div class="space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Monthly Rent</span>
                                            <span class="font-medium">${{ number_format($bill['monthly_rent'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Due Date</span>
                                            <span class="font-medium">{{ $bill['due_date']->format('M d, Y') }}</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Status</span>
                                            <span class="font-medium {{ $bill['paid_this_month'] ? 'text-green-600' : 'text-orange-600' }}">
                                                {{ $bill['paid_this_month'] ? 'Paid' : 'Unpaid' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Utility Charges -->
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        Utility Charges
                                    </h4>
                                    @if($bill['utilities']->count() > 0)
                                    <div class="space-y-2">
                                        @foreach($bill['utilities'] as $utility)
                                        <div class="flex justify-between items-center text-sm">
                                            <div class="flex items-center">
                                                <span class="text-gray-600 capitalize">{{ str_replace('_', ' ', $utility->utility_type) }}</span>
                                                @if(!$utility->paid_status)
                                                <form action="{{ route('admin.revenue_expense.remove_charge', $utility->id) }}" method="POST" class="inline ml-1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-400 hover:text-red-600" title="Remove" onclick="return confirm('Remove this charge?')">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </form>
                                                @endif
                                            </div>
                                            <span class="font-medium {{ $utility->paid_status ? 'text-green-600' : 'text-orange-600' }}">${{ number_format($utility->charge_amount, 2) }}</span>
                                        </div>
                                        @endforeach
                                        <div class="pt-2 border-t border-gray-100 flex justify-between text-sm font-semibold">
                                            <span>Total Utilities</span>
                                            <span>${{ number_format($bill['total_utilities'], 2) }}</span>
                                        </div>
                                    </div>
                                    @else
                                    <p class="text-sm text-gray-400">No utility charges assigned</p>
                                    @endif
                                </div>

                                <!-- Fixed Expenses & Total -->
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        Fixed Expenses & Summary
                                    </h4>
                                    @if($bill['fixed_expenses']->count() > 0)
                                    <div class="space-y-2 mb-3">
                                        @foreach($bill['fixed_expenses'] as $expense)
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">{{ $expense->expense_name }}</span>
                                            <span class="font-medium">${{ number_format($expense->amount, 2) }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif
                                    <div class="pt-3 border-t-2 border-gray-200 space-y-1">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Rent</span>
                                            <span>${{ number_format($bill['monthly_rent'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Utilities</span>
                                            <span>${{ number_format($bill['total_utilities'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Fixed Charges</span>
                                            <span>${{ number_format($bill['total_fixed'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between text-base font-bold pt-2 border-t border-gray-200">
                                            <span class="text-gray-900">TOTAL BILL</span>
                                            <span class="text-blue-600">${{ number_format($bill['total_bill'], 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-16">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <p class="text-gray-500 text-lg">No active rentals found</p>
            <p class="text-gray-400 text-sm mt-1">Tenants with active rental agreements will appear here automatically.</p>
        </div>
        @endif
    </div>

    <!-- ============================================ -->
    <!-- ADD CHARGE MODAL                             -->
    <!-- ============================================ -->
    <div x-show="showAddCharge" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="showAddCharge = false"></div>
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg relative z-10 transform transition-all">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">
                        Add Charge — <span x-text="chargeApt" class="text-blue-600"></span>
                    </h3>
                    <button @click="showAddCharge = false" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form action="{{ route('admin.revenue_expense.add_charge') }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    <input type="hidden" name="rental_id" x-model="chargeRentalId">

                    <p class="text-sm text-gray-600">Adding charge for <strong x-text="chargeTenant"></strong></p>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Charge Type <span class="text-red-500">*</span></label>
                        <select name="charge_type" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="electricity">Electricity</option>
                            <option value="water">Water</option>
                            <option value="internet">Internet</option>
                            <option value="parking">Parking</option>
                            <option value="cleaning">Cleaning</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meter In</label>
                            <input type="number" name="meter_reading_in" step="0.01" min="0" placeholder="0" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meter Out</label>
                            <input type="number" name="meter_reading_out" step="0.01" min="0" placeholder="0" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount ($) <span class="text-red-500">*</span></label>
                        <input type="number" name="charge_amount" step="0.01" min="0.01" required placeholder="0.00"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg font-semibold">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                        <input type="text" name="note" placeholder="Optional note..." class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="flex-1 px-4 py-2.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition font-medium">
                            Add Charge
                        </button>
                        <button type="button" @click="showAddCharge = false" class="px-4 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- CHECKOUT / PAY MODAL                         -->
    <!-- ============================================ -->
    <div x-show="showCheckout" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="showCheckout = false"></div>
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg relative z-10 transform transition-all">
                <div class="px-6 py-4 border-b border-gray-200 bg-green-50 rounded-t-xl">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Checkout — <span x-text="checkoutApt" class="text-blue-600 ml-1"></span>
                    </h3>
                    <p class="text-sm text-gray-600 mt-1">Tenant: <strong x-text="checkoutTenant"></strong></p>
                </div>
                <form action="{{ route('admin.revenue_expense.checkout') }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    <input type="hidden" name="rental_id" x-model="checkoutRentalId">
                    <input type="hidden" name="rent_amount" x-model="checkoutRent">

                    <!-- Bill Summary -->
                    <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                        <h4 class="font-semibold text-gray-800 mb-2">Bill Summary</h4>
                        <div class="flex justify-between text-sm">
                            <label class="flex items-center text-gray-700">
                                <input type="checkbox" name="pay_rent" value="1" checked x-model="payRent"
                                    class="w-4 h-4 text-green-600 rounded focus:ring-green-500 mr-2">
                                Monthly Rent
                            </label>
                            <span class="font-medium" x-text="'$' + parseFloat(checkoutRent).toFixed(2)"></span>
                        </div>
                        <div class="flex justify-between text-sm" x-show="checkoutUtilities > 0">
                            <label class="flex items-center text-gray-700">
                                <input type="checkbox" name="pay_utilities" value="1" checked x-model="payUtilities"
                                    class="w-4 h-4 text-green-600 rounded focus:ring-green-500 mr-2">
                                Utility Charges
                            </label>
                            <span class="font-medium" x-text="'$' + parseFloat(checkoutUtilities).toFixed(2)"></span>
                        </div>
                        <div class="flex justify-between text-sm" x-show="checkoutFixed > 0">
                            <span class="text-gray-600 ml-6">Fixed Charges (included)</span>
                            <span class="font-medium" x-text="'$' + parseFloat(checkoutFixed).toFixed(2)"></span>
                        </div>
                        <div class="pt-2 border-t border-gray-200">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-600">Late Fee ($)</span>
                                <input type="number" name="late_fee" x-model="checkoutLateFee" step="0.01" min="0" value="0"
                                    class="w-24 px-2 py-1 text-right border border-gray-300 rounded focus:ring-2 focus:ring-green-500 text-sm">
                            </div>
                        </div>
                        <div class="pt-2 border-t-2 border-gray-300 flex justify-between font-bold text-lg">
                            <span>TOTAL TO PAY</span>
                            <span class="text-green-600" x-text="'$' + calculateCheckoutTotal()"></span>
                        </div>
                    </div>

                    <!-- Payment Details -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method <span class="text-red-500">*</span></label>
                            <select name="payment_method" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date <span class="text-red-500">*</span></label>
                            <input type="date" name="payment_date" required value="{{ date('Y-m-d') }}"
                                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                        <input type="text" name="transaction_reference" placeholder="e.g. TXN-001234"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                        <input type="text" name="note" placeholder="Optional note..."
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold text-lg flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Confirm Payment
                        </button>
                        <button type="button" @click="showCheckout = false" class="px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">
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
    <div class="mt-8">
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Recent Payments
            </h3>

            @if($recentIncome->isEmpty())
                <p class="text-gray-400 text-sm text-center py-4">No payments recorded yet this period.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($recentIncome as $record)
                    <div class="flex items-center justify-between py-3 px-2 hover:bg-gray-50 rounded-lg transition">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ $record->description }}</p>
                                <p class="text-xs text-gray-500">{{ ucfirst(str_replace('_', ' ', $record->category)) }} &middot; {{ \Carbon\Carbon::parse($record->transaction_date)->format('M d, Y') }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-bold text-green-600 whitespace-nowrap">${{ number_format($record->amount, 2) }}</span>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function billingManager() {
    return {
        filter: 'all',
        searchQuery: '',
        expandedBill: null,

        // Add Charge Modal
        showAddCharge: false,
        chargeRentalId: null,
        chargeTenant: '',
        chargeApt: '',

        // Checkout Modal
        showCheckout: false,
        checkoutRentalId: null,
        checkoutTenant: '',
        checkoutApt: '',
        checkoutRent: 0,
        checkoutUtilities: 0,
        checkoutFixed: 0,
        checkoutTotal: 0,
        checkoutLateFee: 0,
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

        toggleBillDetail(index) {
            this.expandedBill = this.expandedBill === index ? null : index;
        },

        openAddCharge(rentalId, tenant, apt) {
            this.chargeRentalId = rentalId;
            this.chargeTenant = tenant;
            this.chargeApt = apt;
            this.showAddCharge = true;
        },

        openCheckout(rentalId, tenant, apt, rent, utilities, fixed, total) {
            this.checkoutRentalId = rentalId;
            this.checkoutTenant = tenant;
            this.checkoutApt = apt;
            this.checkoutRent = rent;
            this.checkoutUtilities = utilities;
            this.checkoutFixed = fixed;
            this.checkoutTotal = total;
            this.checkoutLateFee = 0;
            this.payRent = true;
            this.payUtilities = true;
            this.showCheckout = true;
        },

        calculateCheckoutTotal() {
            let total = 0;
            if (this.payRent) total += parseFloat(this.checkoutRent) || 0;
            if (this.payUtilities) total += parseFloat(this.checkoutUtilities) || 0;
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