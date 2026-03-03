@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Record Expense</h1>
            <p class="text-gray-600 mt-2">
                Viewing <span class="font-semibold text-blue-600">{{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }}</span>
                — Fiscal Period: <span class="font-semibold text-blue-600">{{ $activePeriod->name }}</span>
            </p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.revenue_expense.fixed_expenses') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Apartment Fixed Expenses
            </a>
            <a href="{{ route('admin.revenue_expense.generate_bills') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Generate Monthly Bills
            </a>
            <a href="{{ route('admin.revenue_expense.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- MONTH NAVIGATION                                             -->
    <!-- ============================================================ -->
    @if(isset($periodMonths) && count($periodMonths) > 0)
    @php
        $currentIdx = null;
        foreach ($periodMonths as $idx => $pm) {
            if ($filterMonth == $pm['month'] && $filterYear == $pm['year']) {
                $currentIdx = $idx;
                break;
            }
        }
        $selectedMonth = \Carbon\Carbon::create($filterYear, $filterMonth, 1);
        $isCurrentMonth = $selectedMonth->month === now()->month && $selectedMonth->year === now()->year;

        $prevMonth = ($currentIdx !== null && $currentIdx > 0) ? $periodMonths[$currentIdx - 1] : null;
        $nextMonth = ($currentIdx !== null && $currentIdx < count($periodMonths) - 1) ? $periodMonths[$currentIdx + 1] : null;
    @endphp
    <div class="mb-6 flex items-center justify-center">
        <div class="inline-flex items-center bg-white rounded-xl shadow-md border border-gray-200 px-2 py-1.5 gap-1">
            {{-- Previous Month --}}
            @if($prevMonth)
            <a href="{{ route('admin.revenue_expense.record_expense', ['month' => $prevMonth['month'], 'year' => $prevMonth['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-blue-600 transition" title="Previous Month">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </span>
            @endif

            {{-- Current Month Display --}}
            <div class="px-4 py-2 min-w-[220px] text-center">
                <span class="text-lg font-bold text-gray-900">{{ $selectedMonth->format('F') }}</span>
                <span class="text-lg text-gray-500 ml-1">{{ $selectedMonth->format('Y') }}</span>
                @if($isCurrentMonth)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Current</span>
                @elseif($selectedMonth->isFuture())
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Upcoming</span>
                @else
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Past</span>
                @endif
            </div>

            {{-- Next Month --}}
            @if($nextMonth)
            <a href="{{ route('admin.revenue_expense.record_expense', ['month' => $nextMonth['month'], 'year' => $nextMonth['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-blue-600 transition" title="Next Month">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-gray-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
            @endif

            {{-- Quick Jump: Today --}}
            @if(!$isCurrentMonth)
            @php
                $nowMonth = now()->month;
                $nowYear = now()->year;
                $currentInPeriod = collect($periodMonths)->first(fn($pm) => $pm['month'] == $nowMonth && $pm['year'] == $nowYear);
            @endphp
            @if($currentInPeriod)
            <a href="{{ route('admin.revenue_expense.record_expense', ['month' => $nowMonth, 'year' => $nowYear]) }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition" title="Go to current month">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Today
            </a>
            @endif
            @endif

            {{-- Month Dropdown --}}
            <div class="ml-1 relative" x-data="{ open: false }">
                <button @click="open = !open" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 bg-gray-50 rounded-lg hover:bg-gray-100 transition" title="Jump to month">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    Jump
                </button>
                <div x-show="open" @click.away="open = false" x-cloak
                     class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-64 overflow-y-auto">
                    @foreach($periodMonths as $pm)
                    <a href="{{ route('admin.revenue_expense.record_expense', ['month' => $pm['month'], 'year' => $pm['year']]) }}"
                       class="block px-4 py-2 text-sm hover:bg-blue-50 transition {{ ($filterMonth == $pm['month'] && $filterYear == $pm['year']) ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-700' }}">
                        {{ $pm['label'] }}
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Success / Error Messages -->
    @if(session('success'))
    <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
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

    <!-- ============================================================ -->
    <!-- EXPENSE SUMMARY CARDS                                        -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <!-- Apartment Utility Expenses -->
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-yellow-500">
            <p class="text-gray-500 text-xs font-medium uppercase">Apartment Utilities</p>
            <p class="text-2xl font-bold text-yellow-600 mt-1">${{ number_format(collect($apartmentExpenses)->sum('total'), 2) }}</p>
        </div>
        <!-- Apartment Fixed Expenses -->
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-indigo-500">
            <p class="text-gray-500 text-xs font-medium uppercase">Apartment Fixed Costs</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1">${{ number_format(collect($apartmentExpenses)->sum('fixed_total'), 2) }}</p>
        </div>
        <!-- Other Allocated Expenses -->
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-purple-500">
            <p class="text-gray-500 text-xs font-medium uppercase">Other Expenses</p>
            <p class="text-2xl font-bold text-purple-600 mt-1">${{ number_format($totalOtherExpenses, 2) }}</p>
        </div>
        <!-- Business Expenses -->
        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-red-500">
            <p class="text-gray-500 text-xs font-medium uppercase">Business Expenses</p>
            <p class="text-2xl font-bold text-red-600 mt-1">${{ number_format($businessTotal, 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">Fixed: ${{ number_format($businessFixedTotal, 2) }} | Variable: ${{ number_format($businessVariableTotal, 2) }}</p>
        </div>
    </div>

    <!-- Grand Total Bar -->
    <div class="bg-gradient-to-r from-red-600 to-red-800 rounded-lg shadow-lg p-5 mb-8 text-white flex items-center justify-between">
        <div>
            <p class="text-red-200 text-sm font-medium">Grand Total All Expenses ({{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }})</p>
            <p class="text-3xl font-bold mt-1">${{ number_format($grandTotalExpenses, 2) }}</p>
        </div>
        <svg class="w-12 h-12 text-red-300 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
    </div>

    <!-- ============================================================ -->
    <!-- TAB NAVIGATION                                               -->
    <!-- ============================================================ -->
    <div x-data="{ activeTab: 'apartments' }" class="mb-8">
        <div class="border-b border-gray-200 mb-6">
            <nav class="flex gap-1 -mb-px">
                <button @click="activeTab = 'apartments'" :class="activeTab === 'apartments' ? 'border-red-500 text-red-600 bg-red-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-5 py-3 border-b-2 font-medium text-sm rounded-t-lg transition">
                    Apartment Expenses
                </button>
                <button @click="activeTab = 'other'" :class="activeTab === 'other' ? 'border-purple-500 text-purple-600 bg-purple-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-5 py-3 border-b-2 font-medium text-sm rounded-t-lg transition">
                    Other Expenses
                </button>
                <button @click="activeTab = 'business'" :class="activeTab === 'business' ? 'border-orange-500 text-orange-600 bg-orange-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="px-5 py-3 border-b-2 font-medium text-sm rounded-t-lg transition">
                    Business Fixed & Variable
                </button>
            </nav>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 1: APARTMENT EXPENSES                                    -->
        <!-- ============================================================ -->
        <div x-show="activeTab === 'apartments'" x-cloak>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left: Apartment Expense Table + Form -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Per-Apartment Expense Breakdown -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 pb-3 border-b-2 border-red-500">
                            Expenses per Apartment ({{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }})
                        </h2>

                        @if(count($apartmentExpenses) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Apartment</th>
                                        <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Electric</th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Water</th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Internet</th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Parking</th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Fixed</th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($apartmentExpenses as $aptExp)
                                    <tr class="hover:bg-gray-50 group" x-data="{ showFixed: false }">
                                        <td class="px-3 py-3">
                                            <span class="font-semibold text-gray-900">{{ $aptExp['apartment']->apartment_number }}</span>
                                            <span class="text-xs text-gray-500 block">Floor {{ $aptExp['apartment']->floor->floor_number ?? 'N/A' }}</span>
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $aptExp['has_active_rental'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                                {{ $aptExp['has_active_rental'] ? 'Occupied' : 'Vacant' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm {{ $aptExp['electricity'] > 0 ? 'font-semibold text-yellow-600' : 'text-gray-400' }}">
                                            ${{ number_format($aptExp['electricity'], 2) }}
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm {{ $aptExp['water'] > 0 ? 'font-semibold text-blue-600' : 'text-gray-400' }}">
                                            ${{ number_format($aptExp['water'], 2) }}
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm {{ $aptExp['internet'] > 0 ? 'font-semibold text-purple-600' : 'text-gray-400' }}">
                                            ${{ number_format($aptExp['internet'], 2) }}
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm {{ $aptExp['parking'] > 0 ? 'font-semibold text-orange-600' : 'text-gray-400' }}">
                                            ${{ number_format($aptExp['parking'], 2) }}
                                        </td>
                                        <td class="px-3 py-3 text-right text-sm">
                                            @if($aptExp['fixed_total'] > 0)
                                            <button @click="showFixed = !showFixed" class="font-semibold text-indigo-600 hover:text-indigo-800 cursor-pointer">
                                                ${{ number_format($aptExp['fixed_total'], 2) }}
                                                <svg class="w-3 h-3 inline-block transition-transform" :class="showFixed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </button>
                                            @else
                                            <span class="text-gray-400">$0.00</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right font-bold text-red-600">
                                            ${{ number_format($aptExp['grand_total'], 2) }}
                                        </td>
                                    </tr>
                                    @if($aptExp['fixed_items']->count() > 0)
                                    <tr x-show="showFixed" x-cloak class="bg-indigo-50">
                                        <td colspan="8" class="px-6 py-2">
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($aptExp['fixed_items'] as $fi)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                                    {{ $fi->expense_name }}: ${{ number_format($fi->amount, 2) }}
                                                </span>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-100">
                                    <tr>
                                        <td class="px-3 py-3 font-bold text-gray-900" colspan="2">Grand Total</td>
                                        <td class="px-3 py-3 text-right font-bold text-yellow-600">${{ number_format(collect($apartmentExpenses)->sum('electricity'), 2) }}</td>
                                        <td class="px-3 py-3 text-right font-bold text-blue-600">${{ number_format(collect($apartmentExpenses)->sum('water'), 2) }}</td>
                                        <td class="px-3 py-3 text-right font-bold text-purple-600">${{ number_format(collect($apartmentExpenses)->sum('internet'), 2) }}</td>
                                        <td class="px-3 py-3 text-right font-bold text-orange-600">${{ number_format(collect($apartmentExpenses)->sum('parking'), 2) }}</td>
                                        <td class="px-3 py-3 text-right font-bold text-indigo-600">${{ number_format(collect($apartmentExpenses)->sum('fixed_total'), 2) }}</td>
                                        <td class="px-3 py-3 text-right font-bold text-red-600">${{ number_format($totalExpenses, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        @else
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <p>No apartments found.</p>
                        </div>
                        @endif
                    </div>

                    <!-- Record Utility Expense Form -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 pb-4 border-b-2 border-red-500">
                            Record Utility Expense for Apartment
                        </h2>

                        <form action="{{ route('admin.revenue_expense.store_expense') }}" method="POST">
                            @csrf

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Apartment -->
                                <div>
                                    <label for="rental_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Select Apartment <span class="text-red-500">*</span>
                                    </label>
                                    <select name="rental_id" id="rental_id" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                                        <option value="">-- Select an apartment --</option>
                                        @foreach($apartments as $apartment)
                                            @foreach($apartment->rentals as $rental)
                                            <option value="{{ $rental->id }}" {{ old('rental_id') == $rental->id ? 'selected' : '' }}>
                                                {{ $apartment->apartment_number }} (Floor {{ $apartment->floor->floor_number ?? 'N/A' }})
                                                — {{ $rental->tenant->name ?? 'N/A' }}
                                            </option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Utility Type -->
                                <div>
                                    <label for="utility_type" class="block text-sm font-medium text-gray-700 mb-2">
                                        Utility Type <span class="text-red-500">*</span>
                                    </label>
                                    <select name="utility_type" id="utility_type" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                                        <option value="">-- Select type --</option>
                                        @foreach($utilityTypes as $key => $label)
                                        <option value="{{ $key }}" {{ old('utility_type') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Charge Amount -->
                                <div>
                                    <label for="charge_amount" class="block text-sm font-medium text-gray-700 mb-2">
                                        Charge Amount ($) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" name="charge_amount" id="charge_amount" step="0.01" min="0.01" required
                                        value="{{ old('charge_amount') }}" placeholder="0.00"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                                </div>

                                <!-- Transaction Date -->
                                <div>
                                    <label for="transaction_date" class="block text-sm font-medium text-gray-700 mb-2">
                                        Expense Date <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" name="transaction_date" id="transaction_date" required
                                        value="{{ old('transaction_date', date('Y-m-d')) }}"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                                </div>
                            </div>

                            <!-- Meter Readings (for electricity) -->
                            <div id="meter-readings" class="mt-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200" style="display: none;">
                                <h3 class="text-sm font-semibold text-yellow-800 mb-3">Meter Readings (Electricity)</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="meter_reading_in" class="block text-sm font-medium text-gray-700 mb-1">Meter In (Previous)</label>
                                        <input type="number" name="meter_reading_in" id="meter_reading_in" step="0.01" min="0"
                                            value="{{ old('meter_reading_in') }}" placeholder="0.00"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                    </div>
                                    <div>
                                        <label for="meter_reading_out" class="block text-sm font-medium text-gray-700 mb-1">Meter Out (Current)</label>
                                        <input type="number" name="meter_reading_out" id="meter_reading_out" step="0.01" min="0"
                                            value="{{ old('meter_reading_out') }}" placeholder="0.00"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                                    </div>
                                </div>
                            </div>

                            <!-- Note -->
                            <div class="mt-6">
                                <label for="note" class="block text-sm font-medium text-gray-700 mb-2">Note</label>
                                <textarea name="note" id="note" rows="2" placeholder="Optional note about this expense..."
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">{{ old('note') }}</textarea>
                            </div>

                            <!-- Submit -->
                            <div class="mt-6 flex items-center gap-4">
                                <button type="submit" class="inline-flex items-center px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    Record Utility Expense
                                </button>
                                <button type="reset" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">Reset</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Sidebar: Recent Expenses + Legend -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b-2 border-red-500">Recent Expense Records</h3>

                        @if($recentExpenses->isEmpty())
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <p class="text-gray-500 text-sm">No expenses recorded yet</p>
                            </div>
                        @else
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                @foreach($recentExpenses as $record)
                                <div class="p-3 bg-red-50 rounded-lg border border-red-100">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="text-sm font-medium text-gray-800">{{ ucfirst(str_replace('_', ' ', $record->category)) }}</p>
                                            <p class="text-xs text-gray-500 mt-1">{{ $record->description }}</p>
                                            <p class="text-xs text-gray-400 mt-1">{{ \Carbon\Carbon::parse($record->transaction_date)->format('M d, Y') }}</p>
                                        </div>
                                        <p class="text-lg font-bold text-red-600">${{ number_format($record->amount, 2) }}</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Expense Type Legend -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b-2 border-blue-500">Expense Types</h3>
                        <div class="space-y-3">
                            <div class="flex items-center gap-3 p-2 bg-yellow-50 rounded">
                                <span class="text-xl">⚡</span>
                                <div>
                                    <p class="text-sm font-medium">Electricity</p>
                                    <p class="text-xs text-gray-500">Meter-based consumption charges</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 p-2 bg-blue-50 rounded">
                                <span class="text-xl">💧</span>
                                <div>
                                    <p class="text-sm font-medium">Water</p>
                                    <p class="text-xs text-gray-500">Monthly water supply charges</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 p-2 bg-purple-50 rounded">
                                <span class="text-xl">📡</span>
                                <div>
                                    <p class="text-sm font-medium">Internet</p>
                                    <p class="text-xs text-gray-500">Internet service provider fees</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 p-2 bg-orange-50 rounded">
                                <span class="text-xl">🚗</span>
                                <div>
                                    <p class="text-sm font-medium">Parking</p>
                                    <p class="text-xs text-gray-500">Parking facility charges</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 2: OTHER EXPENSES (Allocate miscellaneous expenses)      -->
        <!-- ============================================================ -->
        <div x-show="activeTab === 'other'" x-cloak>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-6">
                    <!-- Existing Other Expenses List -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 pb-3 border-b-2 border-purple-500">
                            Allocated Other Expenses ({{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }})
                        </h2>

                        @if($otherExpenses->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Category</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Description</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Amount</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($otherExpenses as $oe)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ \Carbon\Carbon::parse($oe->transaction_date)->format('M d, Y') }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                                {{ ucfirst(str_replace('_', ' ', $oe->category)) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-800">
                                            {{ $oe->description }}
                                            @if($oe->note)
                                            <span class="block text-xs text-gray-400 mt-1">{{ $oe->note }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-purple-600">${{ number_format($oe->amount, 2) }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <form action="{{ route('admin.revenue_expense.delete_other_expense', $oe) }}" method="POST" onsubmit="return confirm('Remove this expense?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-500 hover:text-red-700 text-sm">
                                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-100">
                                    <tr>
                                        <td class="px-4 py-3 font-bold text-gray-900" colspan="3">Total Other Expenses</td>
                                        <td class="px-4 py-3 text-right font-bold text-purple-600">${{ number_format($totalOtherExpenses, 2) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        @else
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <p>No other expenses allocated yet.</p>
                        </div>
                        @endif
                    </div>

                    <!-- Allocate Other Expense Form -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 pb-4 border-b-2 border-purple-500">
                            Allocate Other Expense
                        </h2>

                        <form action="{{ route('admin.revenue_expense.store_other_expense') }}" method="POST">
                            @csrf

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Category -->
                                <div>
                                    <label for="other_category" class="block text-sm font-medium text-gray-700 mb-2">
                                        Category <span class="text-red-500">*</span>
                                    </label>
                                    <select name="category" id="other_category" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                                        <option value="">-- Select category --</option>
                                        @foreach($otherExpenseCategories as $key => $label)
                                        <option value="{{ $key }}" {{ old('category') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Amount -->
                                <div>
                                    <label for="other_amount" class="block text-sm font-medium text-gray-700 mb-2">
                                        Amount ($) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" name="amount" id="other_amount" step="0.01" min="0.01" required
                                        value="{{ old('amount') }}" placeholder="0.00"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                                </div>

                                <!-- Description -->
                                <div>
                                    <label for="other_description" class="block text-sm font-medium text-gray-700 mb-2">
                                        Description <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="description" id="other_description" required
                                        value="{{ old('description') }}" placeholder="e.g. Roof repair, pest control..."
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                                </div>

                                <!-- Date -->
                                <div>
                                    <label for="other_date" class="block text-sm font-medium text-gray-700 mb-2">
                                        Expense Date <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" name="transaction_date" id="other_date" required
                                        value="{{ old('transaction_date', date('Y-m-d')) }}"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">
                                </div>
                            </div>

                            <!-- Note -->
                            <div class="mt-6">
                                <label for="other_note" class="block text-sm font-medium text-gray-700 mb-2">Note</label>
                                <textarea name="note" id="other_note" rows="2" placeholder="Optional note..."
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition">{{ old('note') }}</textarea>
                            </div>

                            <!-- Submit -->
                            <div class="mt-6">
                                <button type="submit" class="inline-flex items-center px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    Allocate Expense
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Sidebar: Category Breakdown -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b-2 border-purple-500">Expense by Category</h3>
                        @if($otherExpenses->count() > 0)
                            @php
                                $categoryTotals = $otherExpenses->groupBy('category')->map(function ($items) {
                                    return $items->sum('amount');
                                })->sortDesc();
                            @endphp
                            <div class="space-y-3">
                                @foreach($categoryTotals as $cat => $catTotal)
                                <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                                    <span class="text-sm font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $cat)) }}</span>
                                    <span class="font-bold text-purple-600">${{ number_format($catTotal, 2) }}</span>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 text-sm text-center py-4">No expenses yet</p>
                        @endif
                    </div>

                    <!-- Available Categories -->
                    <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b-2 border-gray-300">Available Categories</h3>
                        <div class="space-y-2">
                            @foreach($otherExpenseCategories as $key => $label)
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <span class="w-2 h-2 rounded-full bg-purple-400"></span>
                                {{ $label }}
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 3: BUSINESS FIXED & VARIABLE EXPENSES                    -->
        <!-- ============================================================ -->
        <div x-show="activeTab === 'business'" x-cloak>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-6">
                    <!-- Business Expense Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-green-500">
                            <p class="text-gray-500 text-xs font-medium uppercase">Fixed Expenses</p>
                            <p class="text-2xl font-bold text-green-600 mt-1">${{ number_format($businessFixedTotal, 2) }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $businessExpenses->where('cost_type', 'fixed')->count() }} items</p>
                        </div>
                        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-amber-500">
                            <p class="text-gray-500 text-xs font-medium uppercase">Variable Expenses</p>
                            <p class="text-2xl font-bold text-amber-600 mt-1">${{ number_format($businessVariableTotal, 2) }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $businessExpenses->where('cost_type', 'variable')->count() }} items</p>
                        </div>
                        <div class="bg-white rounded-lg shadow-md p-5 border-l-4 border-red-500">
                            <p class="text-gray-500 text-xs font-medium uppercase">Total Business Cost</p>
                            <p class="text-2xl font-bold text-red-600 mt-1">${{ number_format($businessTotal, 2) }}</p>
                        </div>
                    </div>

                    <!-- Business Expenses List -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 pb-3 border-b-2 border-orange-500">
                            Business Expenses ({{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }})
                        </h2>

                        @if($businessExpenses->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Expense</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Category</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Recurring</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Amount</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($businessExpenses as $be)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $be->expense_date->format('M d, Y') }}</td>
                                        <td class="px-4 py-3">
                                            <span class="font-medium text-gray-900">{{ $be->expense_name }}</span>
                                            @if($be->note)
                                            <span class="block text-xs text-gray-400 mt-1">{{ $be->note }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $be->cost_type === 'fixed' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                                {{ ucfirst($be->cost_type) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">{{ ucfirst(str_replace('_', ' ', $be->category)) }}</td>
                                        <td class="px-4 py-3 text-center">
                                            @if($be->is_recurring)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                Yes
                                            </span>
                                            @else
                                            <span class="text-gray-400 text-xs">No</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold {{ $be->cost_type === 'fixed' ? 'text-green-600' : 'text-amber-600' }}">
                                            ${{ number_format($be->amount, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <form action="{{ route('admin.revenue_expense.delete_business_expense', $be) }}" method="POST" onsubmit="return confirm('Remove this business expense?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-500 hover:text-red-700 text-sm">
                                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-100">
                                    <tr>
                                        <td class="px-4 py-3 font-bold text-gray-900" colspan="5">Total</td>
                                        <td class="px-4 py-3 text-right font-bold text-red-600">${{ number_format($businessTotal, 2) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        @else
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <p>No business expenses recorded yet.</p>
                        </div>
                        @endif
                    </div>

                    <!-- Add Business Expense Form -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 pb-4 border-b-2 border-orange-500">
                            Add Business Fixed / Variable Expense
                        </h2>

                        <form action="{{ route('admin.revenue_expense.store_business_expense') }}" method="POST">
                            @csrf

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Expense Name -->
                                <div>
                                    <label for="biz_expense_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Expense Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="expense_name" id="biz_expense_name" required
                                        value="{{ old('expense_name') }}" placeholder="e.g. Building Insurance, Elevator Maintenance..."
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition">
                                </div>

                                <!-- Cost Type -->
                                <div>
                                    <label for="biz_cost_type" class="block text-sm font-medium text-gray-700 mb-2">
                                        Cost Type <span class="text-red-500">*</span>
                                    </label>
                                    <select name="cost_type" id="biz_cost_type" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition">
                                        <option value="">-- Select type --</option>
                                        <option value="fixed" {{ old('cost_type') == 'fixed' ? 'selected' : '' }}>Fixed (Recurring / Predictable)</option>
                                        <option value="variable" {{ old('cost_type') == 'variable' ? 'selected' : '' }}>Variable (One-time / Fluctuating)</option>
                                    </select>
                                </div>

                                <!-- Category -->
                                <div>
                                    <label for="biz_category" class="block text-sm font-medium text-gray-700 mb-2">
                                        Category <span class="text-red-500">*</span>
                                    </label>
                                    <select name="category" id="biz_category" required
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition">
                                        <option value="">-- Select category --</option>
                                        @foreach($businessCategories as $key => $label)
                                        <option value="{{ $key }}" {{ old('category') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Amount -->
                                <div>
                                    <label for="biz_amount" class="block text-sm font-medium text-gray-700 mb-2">
                                        Amount ($) <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" name="amount" id="biz_amount" step="0.01" min="0.01" required
                                        value="{{ old('amount') }}" placeholder="0.00"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition">
                                </div>

                                <!-- Expense Date -->
                                <div>
                                    <label for="biz_date" class="block text-sm font-medium text-gray-700 mb-2">
                                        Expense Date <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" name="expense_date" id="biz_date" required
                                        value="{{ old('expense_date', date('Y-m-d')) }}"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition">
                                </div>

                                <!-- Is Recurring -->
                                <div class="flex items-end pb-2">
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" name="is_recurring" value="1" {{ old('is_recurring') ? 'checked' : '' }}
                                            class="w-5 h-5 rounded border-gray-300 text-orange-600 focus:ring-orange-500">
                                        <span class="text-sm font-medium text-gray-700">This is a recurring monthly expense</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Note -->
                            <div class="mt-6">
                                <label for="biz_note" class="block text-sm font-medium text-gray-700 mb-2">Note</label>
                                <textarea name="note" id="biz_note" rows="2" placeholder="Optional details..."
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition">{{ old('note') }}</textarea>
                            </div>

                            <!-- Submit -->
                            <div class="mt-6">
                                <button type="submit" class="inline-flex items-center px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition font-medium">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    Add Business Expense
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Sidebar: Cost Type Breakdown -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Fixed vs Variable Breakdown -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b-2 border-orange-500">Cost Breakdown</h3>

                        @if($businessTotal > 0)
                        <div class="space-y-4">
                            <!-- Fixed -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-green-700">Fixed Costs</span>
                                    <span class="font-bold text-green-600">${{ number_format($businessFixedTotal, 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-green-500 h-3 rounded-full transition-all" style="width: {{ $businessTotal > 0 ? round(($businessFixedTotal / $businessTotal) * 100) : 0 }}%"></div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">{{ $businessTotal > 0 ? round(($businessFixedTotal / $businessTotal) * 100, 1) : 0 }}% of total</p>
                            </div>

                            <!-- Variable -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-amber-700">Variable Costs</span>
                                    <span class="font-bold text-amber-600">${{ number_format($businessVariableTotal, 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-amber-500 h-3 rounded-full transition-all" style="width: {{ $businessTotal > 0 ? round(($businessVariableTotal / $businessTotal) * 100) : 0 }}%"></div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">{{ $businessTotal > 0 ? round(($businessVariableTotal / $businessTotal) * 100, 1) : 0 }}% of total</p>
                            </div>
                        </div>
                        @else
                        <p class="text-gray-500 text-sm text-center py-4">No business expenses yet</p>
                        @endif
                    </div>

                    <!-- By Category -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-4 pb-3 border-b-2 border-gray-300">By Category</h3>
                        @if($businessExpenses->count() > 0)
                            @php
                                $bizCatTotals = $businessExpenses->groupBy('category')->map(function ($items) {
                                    return $items->sum('amount');
                                })->sortDesc();
                            @endphp
                            <div class="space-y-3">
                                @foreach($bizCatTotals as $cat => $catTotal)
                                <div class="flex justify-between items-center p-3 bg-orange-50 rounded-lg">
                                    <span class="text-sm font-medium text-gray-700">{{ ucfirst(str_replace('_', ' ', $cat)) }}</span>
                                    <span class="font-bold text-orange-600">${{ number_format($catTotal, 2) }}</span>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 text-sm text-center py-4">No expenses yet</p>
                        @endif
                    </div>

                    <!-- Info Card -->
                    <div class="bg-gradient-to-br from-orange-50 to-amber-50 rounded-lg shadow-md p-6 border border-orange-200">
                        <h3 class="text-sm font-bold text-orange-800 mb-3">Fixed vs Variable Expenses</h3>
                        <div class="space-y-3 text-xs text-gray-700">
                            <div>
                                <p class="font-semibold text-green-700 mb-1">Fixed Expenses:</p>
                                <p>Costs that stay the same each month regardless of occupancy — insurance, property tax, mortgage, management fees.</p>
                            </div>
                            <div>
                                <p class="font-semibold text-amber-700 mb-1">Variable Expenses:</p>
                                <p>Costs that fluctuate based on usage or events — repairs, maintenance, pest control, seasonal landscaping.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Show/hide meter readings based on utility type
    document.getElementById('utility_type').addEventListener('change', function() {
        const meterSection = document.getElementById('meter-readings');
        meterSection.style.display = this.value === 'electricity' ? 'block' : 'none';
    });

    // Show on load if electricity was previously selected
    if (document.getElementById('utility_type').value === 'electricity') {
        document.getElementById('meter-readings').style.display = 'block';
    }
</script>
@endsection