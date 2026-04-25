@extends('layouts.admin')

@section('content')
<div class="max-w-6xl mx-auto space-y-8" x-data="{ activeForm: 'utility', showForm: false, activeTab: 'apartment' }">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">Record Expense</h1>
            <p class="text-slate-500 mt-2">
                Manage expenses for <span class="font-semibold text-sky-600">{{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }}</span>
                — Fiscal Period: <span class="font-semibold text-sky-600">{{ $activePeriod->name }}</span>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="showForm = !showForm" aria-label="Toggle Add Expense"
                class="inline-flex items-center justify-center w-10 h-10 rounded-lg transition"
                :class="showForm ? 'bg-slate-100 text-slate-600' : 'bg-red-600 text-white hover:bg-red-700'">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>

            <a href="{{ route('admin.revenue_expense.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back
            </a>
        </div>
    </div>

    <!-- Month Navigation -->
    @if(isset($periodMonths) && count($periodMonths) > 0)
    @php
        $currentIdx = null;
        foreach ($periodMonths as $idx => $pm) {
            if ($filterMonth == $pm['month'] && $filterYear == $pm['year']) { $currentIdx = $idx; break; }
        }
        $selectedMonth = \Carbon\Carbon::create($filterYear, $filterMonth, 1);
        $isCurrentMonth = $selectedMonth->month === now()->month && $selectedMonth->year === now()->year;
        $prevMonth = ($currentIdx !== null && $currentIdx > 0) ? $periodMonths[$currentIdx - 1] : null;
        $nextMonth = ($currentIdx !== null && $currentIdx < count($periodMonths) - 1) ? $periodMonths[$currentIdx + 1] : null;
    @endphp
    <div class="mb-6 flex items-center justify-center">
        <div class="inline-flex items-center bg-white rounded-xl border border-slate-100 px-2 py-1.5 gap-1">
            @if($prevMonth)
            <a href="{{ route('admin.revenue_expense.record_expense', ['month' => $prevMonth['month'], 'year' => $prevMonth['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </span>
            @endif
            <div class="px-4 py-2 min-w-[180px] text-center">
                <span class="text-lg font-bold text-slate-800">{{ $selectedMonth->format('F') }}</span>
                <span class="text-lg text-slate-400 ml-1">{{ $selectedMonth->format('Y') }}</span>
                @if($isCurrentMonth)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">Current</span>
                @elseif($selectedMonth->isFuture())
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-50 text-sky-700">Upcoming</span>
                @else
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-50 text-slate-500">Past</span>
                @endif
            </div>
            @if($nextMonth)
            <a href="{{ route('admin.revenue_expense.record_expense', ['month' => $nextMonth['month'], 'year' => $nextMonth['year']]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @else
            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-300 cursor-not-allowed">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
            @endif
            @if(!$isCurrentMonth)
            @php
                $nowMonth = now()->month; $nowYear = now()->year;
                $currentInPeriod = collect($periodMonths)->first(fn($pm) => $pm['month'] == $nowMonth && $pm['year'] == $nowYear);
            @endphp
            @if($currentInPeriod)
            <a href="{{ route('admin.revenue_expense.record_expense', ['month' => $nowMonth, 'year' => $nowYear]) }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Today
            </a>
            @endif
            @endif
        </div>
    </div>
    @endif

    <!-- Flash Messages -->
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-3 rounded-xl flex items-center">
        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-100 text-red-700 px-4 py-3 rounded-xl flex items-center">
        <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-100 text-red-700 px-4 py-3 rounded-xl">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    {{-- Add Expense modal — opens via header Add button --}}
    <div x-show="showForm" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-black/50" @click="showForm = false"></div>
        <div class="relative bg-white rounded-xl border border-slate-100 w-full max-w-4xl mx-4 overflow-hidden" @click.away="showForm = false">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h2 class="text-lg font-semibold text-slate-800">Add New Expense</h2>
                <button @click="showForm = false" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 hover:bg-slate-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6">
                <!-- Form Type Selector -->
                <div class="flex gap-2 mb-6">
                    <button @click="activeForm = 'utility'" :class="activeForm === 'utility' ? 'bg-amber-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition">Utility Expense</button>
                    <button @click="activeForm = 'other'" :class="activeForm === 'other' ? 'bg-purple-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition">Other Expense</button>
                    <button @click="activeForm = 'business'" :class="activeForm === 'business' ? 'bg-orange-600 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100'"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition">Business Expense</button>
                </div>

                <!-- Utility Expense Form -->
                <div x-show="activeForm === 'utility'">
                    <form action="{{ route('admin.revenue_expense.store_expense') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="rental_id" class="block text-sm font-medium text-slate-700 mb-1">Apartment <span class="text-red-500">*</span></label>
                                <select name="rental_id" id="rental_id" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                                    <option value="">-- Select apartment --</option>
                                    @foreach($apartments as $apartment)
                                        @foreach($apartment->rentals as $rental)
                                        <option value="{{ $rental->id }}" {{ old('rental_id') == $rental->id ? 'selected' : '' }}>
                                            {{ $apartment->apartment_number }} — {{ $rental->tenant->name ?? 'N/A' }}
                                        </option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="utility_type" class="block text-sm font-medium text-slate-700 mb-1">Utility Type <span class="text-red-500">*</span></label>
                                <select name="utility_type" id="utility_type" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                                    <option value="">-- Select type --</option>
                                    @foreach($utilityTypes as $key => $label)
                                                                        <option value="{{ $key }}" {{ old('utility_type') == $key ? 'selected' : '' }}>{{ $key === 'trash' ? 'Trash Services' : $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="charge_amount" class="block text-sm font-medium text-slate-700 mb-1">Amount ($) <span class="text-red-500">*</span></label>
                                <input type="number" name="charge_amount" id="charge_amount" step="0.01" min="0.01" required
                                    value="{{ old('charge_amount') }}" placeholder="0.00"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div>
                                <label for="transaction_date" class="block text-sm font-medium text-slate-700 mb-1">Date <span class="text-red-500">*</span></label>
                                <input type="date" name="transaction_date" id="transaction_date" required
                                    value="{{ old('transaction_date', date('Y-m-d')) }}"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 bg-white appearance-none h-10">
                            </div>
                        </div>
                        <div id="meter-readings" class="mt-4 p-3 bg-amber-50 rounded-lg border border-amber-200" style="display: none;">
                            <p class="text-sm font-semibold text-amber-800 mb-2">Meter Readings</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="meter_reading_in" class="block text-xs text-slate-500 mb-1">Meter In (Previous)</label>
                                    <input type="number" name="meter_reading_in" id="meter_reading_in" step="0.01" min="0"
                                        value="{{ old('meter_reading_in') }}" placeholder="0.00"
                                        class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label for="meter_reading_out" class="block text-xs text-slate-500 mb-1">Meter Out (Current)</label>
                                    <input type="number" name="meter_reading_out" id="meter_reading_out" step="0.01" min="0"
                                        value="{{ old('meter_reading_out') }}" placeholder="0.00"
                                        class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-amber-500">
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <label for="note" class="block text-sm font-medium text-slate-700 mb-1">Note</label>
                            <input type="text" name="note" id="note" value="{{ old('note') }}" placeholder="Optional note..."
                                class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button type="button" @click="showForm = false" class="mr-2 px-4 py-2 rounded-lg border border-slate-200 text-slate-600">Cancel</button>
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition font-medium text-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                Record Utility Expense
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Other Expense Form -->
                <div x-show="activeForm === 'other'" x-cloak>
                    <form action="{{ route('admin.revenue_expense.store_other_expense') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="other_category" class="block text-sm font-medium text-slate-700 mb-1">Category <span class="text-red-500">*</span></label>
                                <select name="category" id="other_category" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                                    <option value="">-- Select category --</option>
                                    @foreach($otherExpenseCategories as $key => $label)
                                    <option value="{{ $key }}" {{ old('category') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="other_amount" class="block text-sm font-medium text-slate-700 mb-1">Amount ($) <span class="text-red-500">*</span></label>
                                <input type="number" name="amount" id="other_amount" step="0.01" min="0.01" required
                                    value="{{ old('amount') }}" placeholder="0.00"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            </div>
                            <div>
                                <label for="other_description" class="block text-sm font-medium text-slate-700 mb-1">Description <span class="text-red-500">*</span></label>
                                <input type="text" name="description" id="other_description" required
                                    value="{{ old('description') }}" placeholder="e.g. Roof repair, pest control..."
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            </div>
                            <div>
                                <label for="other_date" class="block text-sm font-medium text-slate-700 mb-1">Date <span class="text-red-500">*</span></label>
                                <input type="date" name="transaction_date" id="other_date" required
                                    value="{{ old('transaction_date', date('Y-m-d')) }}"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 bg-white appearance-none h-10">
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button type="button" @click="showForm = false" class="mr-2 px-4 py-2 rounded-lg border border-slate-200 text-slate-600">Cancel</button>
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium text-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                Add Other Expense
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Business Expense Form -->
                <div x-show="activeForm === 'business'" x-cloak>
                    <form action="{{ route('admin.revenue_expense.store_business_expense') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="biz_expense_name" class="block text-sm font-medium text-slate-700 mb-1">Expense Name <span class="text-red-500">*</span></label>
                                <input type="text" name="expense_name" id="biz_expense_name" required
                                    value="{{ old('expense_name') }}" placeholder="e.g. Building Insurance..."
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label for="biz_cost_type" class="block text-sm font-medium text-slate-700 mb-1">Cost Type <span class="text-red-500">*</span></label>
                                <select name="cost_type" id="biz_cost_type" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                    <option value="">-- Select type --</option>
                                    <option value="fixed" {{ old('cost_type') == 'fixed' ? 'selected' : '' }}>Fixed</option>
                                    <option value="variable" {{ old('cost_type') == 'variable' ? 'selected' : '' }}>Variable</option>
                                </select>
                            </div>
                            <div>
                                <label for="biz_category" class="block text-sm font-medium text-slate-700 mb-1">Category <span class="text-red-500">*</span></label>
                                <select name="category" id="biz_category" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                    <option value="">-- Select category --</option>
                                    @foreach($businessCategories as $key => $label)
                                    <option value="{{ $key }}" {{ old('category') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="biz_amount" class="block text-sm font-medium text-slate-700 mb-1">Amount ($) <span class="text-red-500">*</span></label>
                                <input type="number" name="amount" id="biz_amount" step="0.01" min="0.01" required
                                    value="{{ old('amount') }}" placeholder="0.00"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            </div>
                            <div>
                                <label for="biz_date" class="block text-sm font-medium text-slate-700 mb-1">Date <span class="text-red-500">*</span></label>
                                <input type="date" name="expense_date" id="biz_date" required
                                    value="{{ old('expense_date', date('Y-m-d')) }}"
                                    class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-white appearance-none h-10">
                            </div>
                            <div class="flex items-end pb-1">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="is_recurring" value="1" {{ old('is_recurring') ? 'checked' : '' }}
                                        class="w-4 h-4 rounded border-slate-200 text-orange-600 focus:ring-orange-500">
                                    <span class="text-sm text-slate-600">Recurring monthly</span>
                                </label>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <button type="button" @click="showForm = false" class="mr-2 px-4 py-2 rounded-lg border border-slate-200 text-slate-600">Cancel</button>
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition font-medium text-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                Add Business Expense
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Apartment Utilities</p>
                    <p class="text-xl font-bold text-amber-600">${{ number_format(collect($apartmentExpensesAll)->sum('total'), 2) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">+ Fixed: ${{ number_format(collect($apartmentExpensesAll)->sum('fixed_total'), 2) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Other Expenses</p>
                    <p class="text-xl font-bold text-purple-600">${{ number_format($totalOtherExpenses, 2) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">{{ $otherExpenses->count() }} item(s)</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Business Expenses</p>
                    <p class="text-xl font-bold text-orange-600">${{ number_format($businessTotal, 2) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">Fixed: ${{ number_format($businessFixedTotal, 2) }} | Variable: ${{ number_format($businessVariableTotal, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">Grand Total</p>
                    <p class="text-xl font-bold text-red-600">${{ number_format($grandTotalExpenses, 2) }}</p>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 mt-2">All expenses combined</p>
        </div>
    </div>

    <!-- Tab Bar -->
    <div class="bg-white rounded-xl border border-slate-100 p-1.5 flex gap-1">
        <button @click="activeTab = 'apartment'"
            :class="activeTab === 'apartment' ? 'bg-amber-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            Apartment Expenses
            <span class="px-2 py-0.5 rounded-full text-xs" :class="activeTab === 'apartment' ? 'bg-amber-500/30 text-white' : 'bg-slate-100 text-slate-500'">${{ number_format(collect($apartmentExpensesAll)->sum('total') + collect($apartmentExpensesAll)->sum('fixed_total'), 2) }}</span>
        </button>
        <button @click="activeTab = 'business'"
            :class="activeTab === 'business' ? 'bg-orange-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Other & Business Expenses
            <span class="px-2 py-0.5 rounded-full text-xs" :class="activeTab === 'business' ? 'bg-orange-500/30 text-white' : 'bg-slate-100 text-slate-500'">${{ number_format($totalOtherExpenses + $businessTotal, 2) }}</span>
        </button>
    </div>

    <!-- Apartment Expenses Table -->
    <div x-show="activeTab === 'apartment'" x-cloak class="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-lg font-semibold text-slate-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Apartment Expenses — {{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }}
            </h2>
        </div>
        @if(count($apartmentExpensesAll) > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50/80">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">Apartment</th>
                        <th class="px-4 py-3 text-center text-[11px] font-medium text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">Electric</th>
                        <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">Water</th>
                        <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">Internet</th>
                        <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">Parking</th>
                        <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">Fixed</th>
                        <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($apartmentExpenses as $aptExp)
                    <tr class="hover:bg-slate-50/50" x-data="{ showFixed: false }">
                        <td class="px-4 py-3">
                            <span class="font-semibold text-slate-800">{{ $aptExp['apartment']->apartment_number }}</span>
                            <span class="text-xs text-slate-400 block">Floor {{ $aptExp['apartment']->floor->floor_number ?? 'N/A' }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $aptExp['has_active_rental'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50 text-slate-500' }}">
                                {{ $aptExp['has_active_rental'] ? 'Occupied' : 'Vacant' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm {{ $aptExp['electricity'] > 0 ? 'font-semibold text-amber-600' : 'text-slate-400' }}">${{ number_format($aptExp['electricity'], 2) }}</td>
                        <td class="px-4 py-3 text-right text-sm {{ $aptExp['water'] > 0 ? 'font-semibold text-sky-600' : 'text-slate-400' }}">${{ number_format($aptExp['water'], 2) }}</td>
                        <td class="px-4 py-3 text-right text-sm {{ $aptExp['internet'] > 0 ? 'font-semibold text-purple-600' : 'text-slate-400' }}">${{ number_format($aptExp['internet'], 2) }}</td>
                        <td class="px-4 py-3 text-right text-sm {{ $aptExp['parking'] > 0 ? 'font-semibold text-orange-600' : 'text-slate-400' }}">${{ number_format($aptExp['parking'], 2) }}</td>
                        <td class="px-4 py-3 text-right text-sm">
                            @if($aptExp['fixed_total'] > 0)
                            <button @click="showFixed = !showFixed" class="font-semibold text-indigo-600 hover:text-indigo-800">
                                ${{ number_format($aptExp['fixed_total'], 2) }}
                                <svg class="w-3 h-3 inline transition-transform" :class="showFixed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            @else
                            <span class="text-slate-400">$0.00</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">${{ number_format($aptExp['grand_total'], 2) }}</td>
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
                <tfoot class="bg-slate-50/80">
                    <tr>
                        <td class="px-4 py-3 font-bold text-slate-800" colspan="2">Total</td>
                        <td class="px-4 py-3 text-right font-bold text-amber-600">${{ number_format(collect($apartmentExpensesAll)->sum('electricity'), 2) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-sky-600">${{ number_format(collect($apartmentExpensesAll)->sum('water'), 2) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-purple-600">${{ number_format(collect($apartmentExpensesAll)->sum('internet'), 2) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-orange-600">${{ number_format(collect($apartmentExpensesAll)->sum('parking'), 2) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-indigo-600">${{ number_format(collect($apartmentExpensesAll)->sum('fixed_total'), 2) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">${{ number_format($totalExpenses, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-100">{{ $apartmentExpenses->appends(request()->query())->links() }}</div>
        @else
        <div class="text-center py-8 text-slate-400">
            <p>No apartment expenses found.</p>
        </div>
        @endif
    </div>

    <!-- Other & Business Expenses Table -->
    <div x-show="activeTab === 'business'" x-cloak class="bg-white rounded-xl border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h2 class="text-lg font-semibold text-slate-800 flex items-center">
                <svg class="w-5 h-5 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Other & Business Expenses
            </h2>
        </div>
        @if($otherExpenses->count() > 0 || $businessExpenses->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50/80">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">Type</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">Category</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">Description</th>
                        <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-center text-[11px] font-medium text-slate-400 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($otherExpenses as $oe)
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-4 py-3 text-sm text-slate-600">{{ \Carbon\Carbon::parse($oe->transaction_date)->format('M d, Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Other</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst(str_replace('_', ' ', $oe->category)) }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            {{ $oe->description }}
                            @if($oe->note)<span class="block text-xs text-slate-400">{{ $oe->note }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-purple-600">${{ number_format($oe->amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <form action="{{ route('admin.revenue_expense.delete_other_expense', $oe) }}" method="POST" onsubmit="return confirm('Remove this expense?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                    @foreach($businessExpenses as $be)
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-4 py-3 text-sm text-slate-600">{{ $be->expense_date->format('M d, Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $be->cost_type === 'fixed' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                Biz {{ ucfirst($be->cost_type) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst(str_replace('_', ' ', $be->category)) }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            {{ $be->expense_name }}
                            @if($be->note)<span class="block text-xs text-slate-400">{{ $be->note }}</span>@endif
                            @if($be->is_recurring)<span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-xs bg-sky-50 text-sky-600">Recurring</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold {{ $be->cost_type === 'fixed' ? 'text-green-600' : 'text-amber-600' }}">${{ number_format($be->amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <form action="{{ route('admin.revenue_expense.delete_business_expense', $be) }}" method="POST" onsubmit="return confirm('Remove this expense?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50/80">
                    <tr>
                        <td class="px-4 py-3 font-bold text-slate-800" colspan="4">Total</td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">${{ number_format($totalOtherExpenses + $businessTotal, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @else
        <div class="text-center py-8 text-slate-400">
            <p>No other or business expenses recorded yet.</p>
        </div>
        @endif
    </div>
</div>

<script>
    document.getElementById('utility_type').addEventListener('change', function() {
        document.getElementById('meter-readings').style.display = this.value === 'electricity' ? 'block' : 'none';
    });
    if (document.getElementById('utility_type').value === 'electricity') {
        document.getElementById('meter-readings').style.display = 'block';
    }
</script>
@endsection