@extends('layouts.supervisor')

@section('content')
<div class="max-w-6xl mx-auto space-y-8" x-data="{ showForm: false, activeTab: 'apartment' }">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.record_expense') }}</h1>
        </div>
        <div class="flex items-center gap-3">
            <button @click="showForm = !showForm" aria-label="Toggle Add Expense"
                class="inline-flex items-center justify-center w-10 h-10 rounded-lg transition"
                :class="showForm ? 'bg-slate-100 text-slate-600' : 'bg-red-600 text-white hover:bg-red-700'">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>

            <a href="{{ route('supervisor.revenue_expense.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700 transition" title="{{ __('messages.back') }}">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
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
            <a href="{{ route('supervisor.revenue_expense.record_expense', ['month' => $prevMonth['month'], 'year' => $prevMonth['year']]) }}"
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
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">{{ __('messages.current') }}</span>
                @elseif($selectedMonth->isFuture())
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-50 text-sky-700">{{ __('messages.upcoming') }}</span>
                @else
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-50 text-slate-500">{{ __('messages.past') }}</span>
                @endif
            </div>
            @if($nextMonth)
            <a href="{{ route('supervisor.revenue_expense.record_expense', ['month' => $nextMonth['month'], 'year' => $nextMonth['year']]) }}"
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
            <a href="{{ route('supervisor.revenue_expense.record_expense', ['month' => $nowMonth, 'year' => $nowYear]) }}"
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition" title="{{ __('messages.today') }}">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></a>
            @endif
            @endif
        </div>
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
                <h2 class="text-lg font-semibold text-slate-800">{{ __('messages.add_new_expense') }}</h2>
                <button @click="showForm = false" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 hover:bg-slate-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6">
                <!-- Business Expense Form -->
                <form action="{{ route('supervisor.revenue_expense.store_business_expense') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="biz_expense_name" class="block text-sm font-medium text-slate-700 mb-1">{{ __('messages.expense_name') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="expense_name" id="biz_expense_name" required
                                value="{{ old('expense_name') }}" placeholder="{{ __('messages.eg_building_insurance') }}"
                                class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label for="biz_category" class="block text-sm font-medium text-slate-700 mb-1">{{ __('messages.category') }} <span class="text-red-500">*</span></label>
                            <select name="category" id="biz_category" required class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                                <option value="">{{ __('messages.select_category') }}</option>
                                @foreach($businessCategories as $key => $label)
                                <option value="{{ $key }}" {{ old('category') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="biz_amount" class="block text-sm font-medium text-slate-700 mb-1">{{ __('messages.amount_dollar') }} <span class="text-red-500">*</span></label>
                            <input type="number" name="amount" id="biz_amount" step="0.01" min="0.01" required
                                value="{{ old('amount') }}" placeholder="0.00"
                                class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                        </div>
                        <div>
                            <label for="biz_date" class="block text-sm font-medium text-slate-700 mb-1">{{ __('messages.date') }} <span class="text-red-500">*</span></label>
                            <input type="date" name="expense_date" id="biz_date" required
                                value="{{ old('expense_date', date('Y-m-d')) }}"
                                class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-white appearance-none h-10">
                        </div>
                        <div class="md:col-span-2">
                            <label for="biz_attachment" class="block text-sm font-medium text-slate-700 mb-1">{{ __('messages.attachment_pdf') }}</label>
                            <input type="file" name="attachment" id="biz_attachment" accept="application/pdf"
                                class="w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-orange-50 file:text-orange-700 hover:file:bg-orange-100">
                            <p class="text-xs text-slate-400 mt-1">{{ __('messages.attachment_hint') }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" @click="showForm = false" class="mr-2 px-4 py-2 rounded-lg border border-slate-200 text-slate-600">{{ __('messages.cancel') }}</button>
                        <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition font-medium text-sm" title="{{ __('messages.add_business_expense') }}">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    @php
        $allExp = collect($apartmentExpensesAll);
        $sumElec    = $allExp->sum('electricity');
        $sumWater   = $allExp->sum('water');
        $sumNet     = $allExp->sum('internet');
        $sumParking = $allExp->sum('parking');
        $sumTrash   = $allExp->sum('trash');
        $sumOther   = $allExp->sum('other');
        $sumFixed   = $allExp->sum('fixed_total');
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div x-data="{ expanded: false }" @click="expanded = !expanded"
            class="bg-white rounded-xl border border-emerald-100 p-5 cursor-pointer transition hover:shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.tenants_expense_collected') }}</p>
                    <p class="text-xl font-bold text-emerald-600">${{ number_format($allExp->sum('total') + $sumFixed, 2) }}</p>
                </div>
                <div class="ml-auto text-right text-xs text-slate-500"></div>
            </div>
            <div x-show="expanded" x-cloak class="mt-4 space-y-3">
                <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                    <div class="flex items-center justify-between text-sm text-slate-600">
                        <span>{{ __('messages.apartment_costs_total') }}</span>
                        <span class="font-semibold text-indigo-600">${{ number_format($sumFixed, 2) }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div class="bg-amber-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-[10px] text-amber-500 font-medium uppercase">{{ __('messages.electric') }}</p>
                        <p class="text-sm font-bold text-amber-700">${{ number_format($sumElec, 2) }}</p>
                    </div>
                    <div class="bg-sky-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-[10px] text-sky-500 font-medium uppercase">{{ __('messages.water') }}</p>
                        <p class="text-sm font-bold text-sky-700">${{ number_format($sumWater, 2) }}</p>
                    </div>
                    <div class="bg-purple-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-[10px] text-purple-500 font-medium uppercase">{{ __('messages.type_internet') }}</p>
                        <p class="text-sm font-bold text-purple-700">${{ number_format($sumNet, 2) }}</p>
                    </div>
                    <div class="bg-orange-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-[10px] text-orange-500 font-medium uppercase">{{ __('messages.type_parking') }}</p>
                        <p class="text-sm font-bold text-orange-700">${{ number_format($sumParking, 2) }}</p>
                    </div>
                    <div class="bg-green-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-[10px] text-green-500 font-medium uppercase">{{ __('messages.type_trash') }}</p>
                        <p class="text-sm font-bold text-green-700">${{ number_format($sumTrash, 2) }}</p>
                    </div>
                    <div class="bg-slate-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-[10px] text-slate-500 font-medium uppercase">{{ __('messages.type_other') }}</p>
                        <p class="text-sm font-bold text-slate-700">${{ number_format($sumOther, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ expanded: false }" @click="expanded = !expanded"
            class="bg-white rounded-xl border border-slate-100 p-5 cursor-pointer transition hover:shadow-sm">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.business_expenses') }}</p>
                    <p class="text-xl font-bold text-orange-600">${{ number_format($businessTotal, 2) }}</p>
                </div>
                <div class="ml-auto text-right text-xs text-slate-500"></div>
            </div>
            <div x-show="expanded" x-cloak class="mt-4 space-y-2 text-sm text-slate-600">
                <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                    <div class="flex items-center justify-between">
                        <span>{{ __('messages.business_total') }}</span>
                        <span class="font-semibold text-orange-600">${{ number_format($businessTotal, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div x-data="{ expanded: false }" @click="expanded = !expanded"
            class="bg-white rounded-xl border border-slate-100 p-5 cursor-pointer transition hover:shadow-sm md:col-span-2">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.grand_total') }}</p>
                    <p class="text-xl font-bold text-red-600">${{ number_format($grandTotalExpenses, 2) }}</p>
                </div>
                <div class="ml-auto text-right text-xs text-slate-500"></div>
            </div>
            <div x-show="expanded" x-cloak class="mt-4 space-y-2 text-sm text-slate-600">
                <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                    <div class="flex items-center justify-between">
                        <span>{{ __('messages.other_expenses') }}</span>
                        <span class="font-semibold">${{ number_format($totalOtherExpenses, 2) }}</span>
                    </div>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                    <div class="flex items-center justify-between">
                        <span>{{ __('messages.business_expenses') }}</span>
                        <span class="font-semibold">${{ number_format($businessTotal, 2) }}</span>
                    </div>
                </div>
                <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-100">
                    <div class="flex items-center justify-between text-emerald-700">
                        <span>{{ __('messages.tenants_collected_not_counted') }}</span>
                        <span class="font-semibold">${{ number_format($tenantsExpenseCollected, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Bar -->
    <div class="bg-white rounded-xl border border-slate-100 p-1.5 flex gap-1">
        <button @click="activeTab = 'apartment'"
            :class="activeTab === 'apartment' ? 'bg-amber-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            {{ __('messages.apartment_expenses') }}
            <span class="px-2 py-0.5 rounded-full text-xs" :class="activeTab === 'apartment' ? 'bg-amber-500/30 text-white' : 'bg-slate-100 text-slate-500'">${{ number_format(collect($apartmentExpensesAll)->sum('total') + collect($apartmentExpensesAll)->sum('fixed_total'), 2) }}</span>
        </button>
        <button @click="activeTab = 'business'"
            :class="activeTab === 'business' ? 'bg-orange-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            {{ __('messages.other_business_expenses') }}
            <span class="px-2 py-0.5 rounded-full text-xs" :class="activeTab === 'business' ? 'bg-orange-500/30 text-white' : 'bg-slate-100 text-slate-500'">${{ number_format($totalOtherExpenses + $businessTotal, 2) }}</span>
        </button>
    </div>

    <!-- Apartment Expenses — grouped by floor -->
    <div x-show="activeTab === 'apartment'" x-cloak class="space-y-5">
        <div class="flex items-center gap-2 px-1">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <h2 class="text-lg font-semibold text-slate-800">Apartment Expenses — {{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }}</h2>
        </div>

        @php
            $expensesByFloor = collect($apartmentExpensesAll)
                ->groupBy(fn($e) => optional($e['apartment']->floor)->id ?? 0)
                ->sortBy(fn($items) => optional($items->first()['apartment']->floor)->floor_number ?? 0);
        @endphp

        @forelse($expensesByFloor as $floorId => $expensesInFloor)
            @php
                $floor = $expensesInFloor->first()['apartment']->floor;
                $floorElec   = $expensesInFloor->sum('electricity');
                $floorWater  = $expensesInFloor->sum('water');
                $floorNet    = $expensesInFloor->sum('internet');
                $floorPark   = $expensesInFloor->sum('parking');
                $floorTrash  = $expensesInFloor->sum('trash');
                $floorOther  = $expensesInFloor->sum('other');
                $floorFixed  = $expensesInFloor->sum('fixed_total');
                $floorTotal  = $expensesInFloor->sum('grand_total');
            @endphp

            <div class="bg-white rounded-xl border border-slate-100 overflow-hidden hover:border-slate-200 transition">
                <details class="group">
                    <summary class="flex items-center justify-between cursor-pointer px-6 py-4 hover:bg-slate-50/50 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-slate-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
                                </svg>
                            </div>
                            <h2 class="text-base font-semibold text-slate-800">{{ $floor->floor_name ?? 'Floor '.($floor->floor_number ?? $floorId) }}</h2>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-slate-400 font-medium">{{ count($expensesInFloor) }} {{ __('messages.apartments') }}</span>
                            <span class="text-sm font-bold text-red-600">${{ number_format($floorTotal, 2) }}</span>
                            <svg class="w-4 h-4 text-slate-400 group-open:rotate-90 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </summary>

                    <div class="overflow-x-auto border-t border-slate-50">
                        <table class="min-w-full">
                            <thead class="bg-slate-50/80">
                                <tr>
                                    <th class="px-3 py-3 text-center text-[11px] font-medium text-slate-400 uppercase tracking-wider w-10">#</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.apartment') }}</th>
                                    <th class="px-4 py-3 text-center text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.status') }}</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.electric') }}</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.water') }}</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.type_internet') }}</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.type_parking') }}</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.type_trash') }}</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.type_other') }}</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.costs') }}</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.total') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach($expensesInFloor as $aptExp)
                                <tr class="hover:bg-slate-50/50" x-data="{ showFixed: false }">
                                    <td class="px-3 py-3 text-center text-xs text-slate-400 font-medium">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold text-slate-800">{{ $aptExp['apartment']->apartment_number }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $aptExp['has_active_rental'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50 text-slate-500' }}">
                                            {{ $aptExp['has_active_rental'] ? __('messages.occupied') : __('messages.vacant') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm {{ $aptExp['electricity'] > 0 ? 'font-semibold text-amber-600' : 'text-slate-400' }}">${{ number_format($aptExp['electricity'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-sm {{ $aptExp['water'] > 0 ? 'font-semibold text-sky-600' : 'text-slate-400' }}">${{ number_format($aptExp['water'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-sm {{ $aptExp['internet'] > 0 ? 'font-semibold text-purple-600' : 'text-slate-400' }}">${{ number_format($aptExp['internet'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-sm {{ $aptExp['parking'] > 0 ? 'font-semibold text-orange-600' : 'text-slate-400' }}">${{ number_format($aptExp['parking'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-sm {{ $aptExp['trash'] > 0 ? 'font-semibold text-green-600' : 'text-slate-400' }}">${{ number_format($aptExp['trash'], 2) }}</td>
                                    <td class="px-4 py-3 text-right text-sm {{ $aptExp['other'] > 0 ? 'font-semibold text-slate-600' : 'text-slate-400' }}">${{ number_format($aptExp['other'], 2) }}</td>
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
                                    <td colspan="11" class="px-6 py-2">
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
                                    <td class="px-3 py-3"></td>
                                    <td class="px-4 py-3 font-bold text-slate-800" colspan="2">{{ __('messages.floor_total') }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-amber-600">${{ number_format($floorElec, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-sky-600">${{ number_format($floorWater, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-purple-600">${{ number_format($floorNet, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-orange-600">${{ number_format($floorPark, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-green-600">${{ number_format($floorTrash, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-slate-600">${{ number_format($floorOther, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-indigo-600">${{ number_format($floorFixed, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-red-600">${{ number_format($floorTotal, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </details>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-slate-100 p-8 text-center text-slate-400">
                <p>{{ __('messages.no_apt_expenses') }}</p>
            </div>
        @endforelse

        @if(count($apartmentExpensesAll) > 0)
        <div class="bg-white rounded-xl border border-slate-100 px-6 py-4 flex items-center justify-between">
            <span class="text-sm font-semibold text-slate-700">{{ __('messages.grand_total_all_floors') }}</span>
            <span class="text-base font-bold text-red-600">${{ number_format($totalExpenses, 2) }}</span>
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
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.date') }}</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.type') }}</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.category') }}</th>
                        <th class="px-4 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.description') }}</th>
                        <th class="px-4 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.amount') }}</th>
                        <th class="px-4 py-3 text-center text-[11px] font-medium text-slate-400 uppercase tracking-wider">{{ __('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($otherExpenses as $oe)
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-4 py-3 text-sm text-slate-600">{{ \Carbon\Carbon::parse($oe->transaction_date)->format('M d, Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700">{{ __('messages.type_other') }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst(str_replace('_', ' ', $oe->category)) }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            {{ $oe->description }}
                            @if($oe->note)<span class="block text-xs text-slate-400">{{ $oe->note }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-purple-600">${{ number_format($oe->amount, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            <form action="{{ route('supervisor.revenue_expense.delete_other_expense', $oe) }}" method="POST" onsubmit="return confirm('{{ __('messages.remove_expense_confirm') }}')">
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
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                                {{ __('messages.business_word') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst(str_replace('_', ' ', $be->category)) }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            {{ $be->expense_name }}
                            @if($be->note)<span class="block text-xs text-slate-400">{{ $be->note }}</span>@endif
                            @if($be->is_recurring)<span class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-xs bg-sky-50 text-sky-600">{{ __('messages.recurring') }}</span>@endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-amber-600">${{ number_format($be->amount, 2) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                @if($be->attachment)
                                <a href="{{ asset('storage/'.$be->attachment) }}" target="_blank" rel="noopener" download
                                   title="{{ __('messages.download_pdf') }}" aria-label="{{ __('messages.download_pdf') }}"
                                   class="inline-flex items-center justify-center w-7 h-7 rounded text-red-500 hover:bg-red-50 hover:text-red-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v8"/></svg>
                                </a>
                                @endif
                                <form action="{{ route('supervisor.revenue_expense.delete_business_expense', $be) }}" method="POST" onsubmit="return confirm('{{ __('messages.remove_expense_confirm') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex items-center justify-center w-7 h-7 rounded text-red-500 hover:bg-red-50 hover:text-red-700" title="{{ __('messages.delete') }}" aria-label="{{ __('messages.delete') }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50/80">
                    <tr>
                        <td class="px-4 py-3 font-bold text-slate-800" colspan="4">{{ __('messages.total') }}</td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">${{ number_format($totalOtherExpenses + $businessTotal, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @else
        <div class="text-center py-8 text-slate-400">
            <p>{{ __('messages.no_other_business') }}</p>
        </div>
        @endif
    </div>
</div>

@endsection