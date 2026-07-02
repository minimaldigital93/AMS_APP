@extends('layouts.admin')

@section('content')
<div class="max-w-6xl mx-auto space-y-8" x-data="{ showForm: false, activeTab: 'apartment', showDetail: false, detail: null, openDetail(d) { this.detail = d; this.showDetail = true }, showBizDetail: false, bizDetail: null, openBizDetail(d) { this.bizDetail = d; this.showBizDetail = true } }">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.record_expense') }}</h1>
        </div>
        <div class="flex items-center gap-3">
            @if(!isset($periodMonths) || count($periodMonths) === 0)
            <button @click="showForm = !showForm" aria-label="Toggle Add Expense"
                class="inline-flex items-center justify-center w-10 h-10 rounded-lg transition"
                :class="showForm ? 'bg-slate-100 text-slate-600' : 'bg-red-600 text-white hover:bg-red-700'">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
            @endif

            <a href="{{ route('admin.revenue_expense.index') }}" class="inline-flex items-center px-4 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-700 transition" title="{{ __('messages.back') }}">
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
    <div class="mb-6 flex items-center">
        <div class="flex-1"></div>
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
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">{{ __('messages.current') }}</span>
                @elseif($selectedMonth->isFuture())
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-50 text-sky-700">{{ __('messages.upcoming') }}</span>
                @else
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-50 text-slate-500">{{ __('messages.past') }}</span>
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
               class="ml-1 inline-flex items-center px-3 py-2 text-sm font-medium text-sky-600 bg-sky-50 rounded-lg hover:bg-sky-100 transition" title="{{ __('messages.today') }}">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></a>
            @endif
            @endif
        </div>
        <div class="flex-1 flex justify-center">
            <button @click="showForm = !showForm" aria-label="Toggle Add Expense"
                class="inline-flex items-center justify-center w-10 h-10 rounded-lg transition"
                :class="showForm ? 'bg-slate-100 text-slate-600' : 'bg-red-600 text-white hover:bg-red-700'">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
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
    <div x-show="showForm" x-cloak x-transition class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:items-center">
        <div class="fixed inset-0 bg-black/50" @click="showForm = false"></div>
        <div class="relative bg-white rounded-xl border border-slate-100 w-full max-w-2xl my-auto overflow-hidden" @click.away="showForm = false">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h2 class="text-lg font-semibold text-slate-800">{{ __('messages.add_new_expense') }}</h2>
                <button @click="showForm = false" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-500 hover:bg-slate-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6">
                <!-- Business Expense Form -->
                <form action="{{ route('admin.revenue_expense.store_business_expense') }}" method="POST" enctype="multipart/form-data">
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
                            <x-attachments-input
                                name="attachments"
                                :label="__('messages.attachment_label')"
                                :hint="__('messages.attachment_hint')"
                                :max-files="3"
                                :max-bytes="10485760" />
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
    <div class="grid grid-cols-2 gap-3 md:gap-4">
        <div x-data="{ expanded: false }" @click="expanded = !expanded"
            class="bg-white rounded-xl border border-emerald-100 p-4 md:p-5 cursor-pointer transition hover:shadow-sm">
            <div class="flex flex-col items-start gap-2 md:flex-row md:items-center md:gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.tenants_expense_collected') }}</p>
                    <p class="text-lg md:text-xl font-bold text-emerald-600">{{ money($tenantsExpenseCollected) }}</p>
                </div>
                <div class="ml-auto text-right text-xs text-slate-500"></div>
            </div>
            <div x-show="expanded" x-cloak class="mt-4 space-y-3">
                <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                    <div class="flex items-center justify-between text-sm text-slate-600">
                        <span>{{ __('messages.apartment_costs_total') }}</span>
                        <span class="font-semibold text-indigo-600">{{ money($sumFixed) }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <div class="bg-amber-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-[10px] text-amber-500 font-medium uppercase">{{ __('messages.electric') }}</p>
                        <p class="text-sm font-bold text-amber-700">{{ money($sumElec) }}</p>
                    </div>
                    <div class="bg-sky-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-[10px] text-sky-500 font-medium uppercase">{{ __('messages.water') }}</p>
                        <p class="text-sm font-bold text-sky-700">{{ money($sumWater) }}</p>
                    </div>
                    <div class="bg-purple-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-[10px] text-purple-500 font-medium uppercase">{{ __('messages.type_internet') }}</p>
                        <p class="text-sm font-bold text-purple-700">{{ money($sumNet) }}</p>
                    </div>
                    <div class="bg-orange-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-[10px] text-orange-500 font-medium uppercase">{{ __('messages.type_parking') }}</p>
                        <p class="text-sm font-bold text-orange-700">{{ money($sumParking) }}</p>
                    </div>
                    <div class="bg-green-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-[10px] text-green-500 font-medium uppercase">{{ __('messages.type_trash') }}</p>
                        <p class="text-sm font-bold text-green-700">{{ money($sumTrash) }}</p>
                    </div>
                    <div class="bg-slate-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-[10px] text-slate-500 font-medium uppercase">{{ __('messages.type_other') }}</p>
                        <p class="text-sm font-bold text-slate-700">{{ money($sumOther) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div x-data="{ expanded: false }" @click="expanded = !expanded"
            class="bg-white rounded-xl border border-slate-100 p-4 md:p-5 cursor-pointer transition hover:shadow-sm">
            <div class="flex flex-col items-start gap-2 md:flex-row md:items-center md:gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-slate-400 font-medium">{{ __('messages.business_expenses') }}</p>
                    <p class="text-lg md:text-xl font-bold text-orange-600">{{ money($businessTotal) }}</p>
                </div>
                <div class="ml-auto text-right text-xs text-slate-500"></div>
            </div>
            <div x-show="expanded" x-cloak class="mt-4 space-y-2 text-sm text-slate-600">
                <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                    <div class="flex items-center justify-between">
                        <span>{{ __('messages.business_total') }}</span>
                        <span class="font-semibold text-orange-600">{{ money($businessTotal) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-data="{ expanded: false }" @click="expanded = !expanded"
        class="bg-white rounded-xl border {{ $expenseNet >= 0 ? 'border-emerald-100' : 'border-orange-100' }} p-5 cursor-pointer transition hover:shadow-sm">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-lg {{ $expenseNet >= 0 ? 'bg-emerald-50' : 'bg-orange-50' }} flex items-center justify-center">
                <svg class="w-5 h-5 {{ $expenseNet >= 0 ? 'text-emerald-600' : 'text-orange-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-xs text-slate-400 font-medium">{{ $expenseNet >= 0 ? __('messages.expense_revenue').' — '.__('messages.added_to_revenue') : __('messages.remaining_business_expense') }}</p>
                <p class="text-xl font-bold {{ $expenseNet >= 0 ? 'text-emerald-600' : 'text-orange-600' }}">{{ $expenseNet < 0 ? '-' : '+' }}{{ money(abs($expenseNet)) }}</p>
            </div>
            <div class="ml-auto text-right text-xs text-slate-500"></div>
        </div>
        <div x-show="expanded" x-cloak class="mt-4">
            <div class="rounded-xl bg-slate-50 p-4 border border-slate-100 space-y-2">
                <div class="flex items-center justify-between text-sm text-slate-600">
                    <span>{{ __('messages.business_expenses') }}</span>
                    <span class="font-semibold text-orange-600">-{{ money($businessTotal) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm text-slate-600">
                    <span>{{ __('messages.tenants_expense_collected') }}</span>
                    <span class="font-semibold text-emerald-600">+{{ money($tenantsExpenseCollected) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm pt-2 border-t border-slate-200 {{ $expenseNet >= 0 ? 'text-emerald-700' : 'text-orange-700' }}">
                    <span class="font-medium">{{ $expenseNet >= 0 ? __('messages.expense_revenue') : __('messages.remaining_business_expense') }}</span>
                    <span class="font-bold">{{ $expenseNet < 0 ? '-' : '+' }}{{ money(abs($expenseNet)) }}</span>
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
            {{ __('messages.expense_collected') }}
        </button>
        <button @click="activeTab = 'business'"
            :class="activeTab === 'business' ? 'bg-orange-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'"
            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            {{ __('messages.business_expenses') }}
        </button>
    </div>

    <!-- Apartment Expenses — grouped by floor -->
    <div x-show="activeTab === 'apartment'" x-cloak class="space-y-5">
        <div class="flex items-center gap-2 px-1">
            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            <h2 class="text-lg font-semibold text-slate-800">Room Expenses — {{ \Carbon\Carbon::create($filterYear, $filterMonth, 1)->format('F Y') }}</h2>
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
                            <span class="text-sm font-bold text-red-600">{{ money($floorTotal) }}</span>
                            <svg class="w-4 h-4 text-slate-400 group-open:rotate-90 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </summary>

                    <!-- Desktop table (hidden on mobile) -->
                    <div class="hidden md:block overflow-x-auto border-t border-slate-50">
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
                                    <td class="px-4 py-3 text-right text-sm {{ $aptExp['electricity'] > 0 ? 'font-semibold text-amber-600' : 'text-slate-400' }}">{{ money($aptExp['electricity']) }}</td>
                                    <td class="px-4 py-3 text-right text-sm {{ $aptExp['water'] > 0 ? 'font-semibold text-sky-600' : 'text-slate-400' }}">{{ money($aptExp['water']) }}</td>
                                    <td class="px-4 py-3 text-right text-sm {{ $aptExp['internet'] > 0 ? 'font-semibold text-purple-600' : 'text-slate-400' }}">{{ money($aptExp['internet']) }}</td>
                                    <td class="px-4 py-3 text-right text-sm {{ $aptExp['parking'] > 0 ? 'font-semibold text-orange-600' : 'text-slate-400' }}">{{ money($aptExp['parking']) }}</td>
                                    <td class="px-4 py-3 text-right text-sm {{ $aptExp['trash'] > 0 ? 'font-semibold text-green-600' : 'text-slate-400' }}">{{ money($aptExp['trash']) }}</td>
                                    <td class="px-4 py-3 text-right text-sm {{ $aptExp['other'] > 0 ? 'font-semibold text-slate-600' : 'text-slate-400' }}">{{ money($aptExp['other']) }}</td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        @if($aptExp['fixed_total'] > 0)
                                        <button @click="showFixed = !showFixed" class="font-semibold text-indigo-600 hover:text-indigo-800">
                                            {{ money($aptExp['fixed_total']) }}
                                            <svg class="w-3 h-3 inline transition-transform" :class="showFixed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        @else
                                        <span class="text-slate-400">$0.00</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-red-600">{{ money($aptExp['grand_total']) }}</td>
                                </tr>
                                @if($aptExp['fixed_items']->count() > 0)
                                <tr x-show="showFixed" x-cloak class="bg-indigo-50">
                                    <td colspan="11" class="px-6 py-2">
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($aptExp['fixed_items'] as $fi)
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                                {{ $fi->expense_name }}: {{ money($fi->amount) }}
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
                                    <td class="px-4 py-3 text-right font-bold text-amber-600">{{ money($floorElec) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-sky-600">{{ money($floorWater) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-purple-600">{{ money($floorNet) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-orange-600">{{ money($floorPark) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-green-600">{{ money($floorTrash) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-slate-600">{{ money($floorOther) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-indigo-600">{{ money($floorFixed) }}</td>
                                    <td class="px-4 py-3 text-right font-bold text-red-600">{{ money($floorTotal) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Mobile compact list (shown on mobile only) -->
                    <div class="md:hidden border-t border-slate-50 divide-y divide-slate-50">
                        @foreach($expensesInFloor as $aptExp)
                        @php
                            $detailJson = [
                                'apt'         => $aptExp['apartment']->apartment_number,
                                'occupied'    => (bool) $aptExp['has_active_rental'],
                                'electricity' => (float) $aptExp['electricity'],
                                'water'       => (float) $aptExp['water'],
                                'internet'    => (float) $aptExp['internet'],
                                'parking'     => (float) $aptExp['parking'],
                                'trash'       => (float) $aptExp['trash'],
                                'other'       => (float) $aptExp['other'],
                                'fixed_total' => (float) $aptExp['fixed_total'],
                                'fixed_items' => $aptExp['fixed_items']->map(fn($fi) => ['name' => $fi->expense_name, 'amount' => (float) $fi->amount])->values(),
                                'total'       => (float) $aptExp['grand_total'],
                            ];
                        @endphp
                        <div class="flex items-center gap-3 px-4 py-3 active:bg-slate-50 transition">
                            <span class="w-5 text-xs font-medium text-slate-400 text-center flex-shrink-0">{{ $loop->iteration }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-slate-800 text-sm truncate">{{ $aptExp['apartment']->apartment_number }}</p>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $aptExp['has_active_rental'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50 text-slate-500' }}">
                                    {{ $aptExp['has_active_rental'] ? __('messages.occupied') : __('messages.vacant') }}
                                </span>
                            </div>
                            <p class="text-sm font-bold text-red-600 whitespace-nowrap">{{ money($aptExp['grand_total']) }}</p>
                            <button @click="openDetail(@js($detailJson))"
                                class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-sky-700 bg-sky-50 active:bg-sky-100 transition flex-shrink-0" title="{{ __('messages.view') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                        </div>
                        @endforeach
                    </div>
                </details>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-slate-100 p-8 text-center text-slate-400">
                <p>{{ __('messages.no_apt_expenses') }}</p>
            </div>
        @endforelse
    </div>

    <!-- ============================================ -->
    <!-- APARTMENT EXPENSE DETAIL MODAL (mobile)      -->
    <!-- ============================================ -->
    <div x-show="showDetail" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showDetail = false"></div>
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm relative z-10">
                <template x-if="detail">
                    <div>
                        <!-- Header -->
                        <div class="px-5 py-4 flex items-center justify-between border-b border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-amber-50 rounded-lg flex items-center justify-center">
                                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-800 text-sm" x-text="detail.apt"></p>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium"
                                        :class="detail.occupied ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50 text-slate-500'"
                                        x-text="detail.occupied ? '{{ __('messages.occupied') }}' : '{{ __('messages.vacant') }}'"></span>
                                </div>
                            </div>
                            <button @click="showDetail = false" class="text-slate-400 hover:text-slate-600 w-7 h-7 flex items-center justify-center rounded-lg hover:bg-slate-100 transition text-lg">&times;</button>
                        </div>

                        <!-- Breakdown -->
                        <div class="px-5 py-4 space-y-1 max-h-72 overflow-y-auto">
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-slate-50">
                                <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-yellow-400 flex-shrink-0"></span><span class="text-sm text-slate-600">{{ __('messages.electric') }}</span></div>
                                <span class="text-sm font-semibold" :class="detail.electricity > 0 ? 'text-amber-600' : 'text-slate-400'" x-text="'$' + detail.electricity.toFixed(2)"></span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-slate-50">
                                <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-400 flex-shrink-0"></span><span class="text-sm text-slate-600">{{ __('messages.water') }}</span></div>
                                <span class="text-sm font-semibold" :class="detail.water > 0 ? 'text-sky-600' : 'text-slate-400'" x-text="'$' + detail.water.toFixed(2)"></span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-slate-50">
                                <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-purple-400 flex-shrink-0"></span><span class="text-sm text-slate-600">{{ __('messages.type_internet') }}</span></div>
                                <span class="text-sm font-semibold" :class="detail.internet > 0 ? 'text-purple-600' : 'text-slate-400'" x-text="'$' + detail.internet.toFixed(2)"></span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-slate-50">
                                <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-orange-400 flex-shrink-0"></span><span class="text-sm text-slate-600">{{ __('messages.type_parking') }}</span></div>
                                <span class="text-sm font-semibold" :class="detail.parking > 0 ? 'text-orange-600' : 'text-slate-400'" x-text="'$' + detail.parking.toFixed(2)"></span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-slate-50">
                                <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-teal-400 flex-shrink-0"></span><span class="text-sm text-slate-600">{{ __('messages.type_trash') }}</span></div>
                                <span class="text-sm font-semibold" :class="detail.trash > 0 ? 'text-green-600' : 'text-slate-400'" x-text="'$' + detail.trash.toFixed(2)"></span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-slate-50">
                                <div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-slate-400 flex-shrink-0"></span><span class="text-sm text-slate-600">{{ __('messages.type_other') }}</span></div>
                                <span class="text-sm font-semibold" :class="detail.other > 0 ? 'text-slate-600' : 'text-slate-400'" x-text="'$' + detail.other.toFixed(2)"></span>
                            </div>
                            <!-- Apartment costs -->
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-indigo-50/60">
                                <span class="text-sm text-slate-600">{{ __('messages.costs') }}</span>
                                <span class="text-sm font-semibold" :class="detail.fixed_total > 0 ? 'text-indigo-600' : 'text-slate-400'" x-text="'$' + detail.fixed_total.toFixed(2)"></span>
                            </div>
                            <template x-if="detail.fixed_items.length > 0">
                                <div class="flex flex-wrap gap-2 px-3 py-1.5">
                                    <template x-for="(fi, i) in detail.fixed_items" :key="i">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700"
                                            x-text="fi.name + ': $' + fi.amount.toFixed(2)"></span>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <!-- Total + close -->
                        <div class="px-5 pt-3 pb-5 border-t border-slate-100 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-slate-700">{{ __('messages.total') }}</span>
                                <span class="text-xl font-bold text-red-600" x-text="'$' + detail.total.toFixed(2)"></span>
                            </div>
                            <button @click="showDetail = false"
                                class="w-full py-2 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">{{ __('messages.close') }}</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- BUSINESS EXPENSE DETAIL MODAL (mobile)       -->
    <!-- ============================================ -->
    <div x-show="showBizDetail" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showBizDetail = false"></div>
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm relative z-10">
                <template x-if="bizDetail">
                    <div>
                        <!-- Header -->
                        <div class="px-5 py-4 flex items-center justify-between border-b border-slate-100">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 bg-purple-50 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-800 text-sm truncate" x-text="bizDetail.name"></p>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium"
                                        :class="bizDetail.type === 'business' ? 'bg-amber-100 text-amber-700' : 'bg-purple-100 text-purple-700'"
                                        x-text="bizDetail.type === 'business' ? '{{ __('messages.business_word') }}' : '{{ __('messages.type_other') }}'"></span>
                                    <span x-show="bizDetail.recurring" class="inline-flex items-center ml-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-sky-50 text-sky-600">{{ __('messages.recurring') }}</span>
                                </div>
                            </div>
                            <button @click="showBizDetail = false" class="text-slate-400 hover:text-slate-600 w-7 h-7 flex items-center justify-center rounded-lg hover:bg-slate-100 transition text-lg flex-shrink-0">&times;</button>
                        </div>

                        <!-- Details -->
                        <div class="px-5 py-4 space-y-1">
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-slate-50">
                                <span class="text-sm text-slate-600">{{ __('messages.date') }}</span>
                                <span class="text-sm font-semibold text-slate-800" x-text="bizDetail.date"></span>
                            </div>
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-slate-50">
                                <span class="text-sm text-slate-600">{{ __('messages.category') }}</span>
                                <span class="text-sm font-semibold text-slate-800" x-text="bizDetail.category"></span>
                            </div>
                            <template x-if="bizDetail.note">
                                <div class="py-1.5 px-3 rounded-lg bg-slate-50">
                                    <p class="text-xs text-slate-400" x-text="bizDetail.note"></p>
                                </div>
                            </template>
                        </div>

                        <!-- Amount + actions -->
                        <div class="px-5 pt-3 pb-5 border-t border-slate-100 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold text-slate-700">{{ __('messages.amount') }}</span>
                                <span class="text-xl font-bold text-red-600" x-text="'$' + bizDetail.amount.toFixed(2)"></span>
                            </div>
                            <template x-for="a in bizDetail.attachments" :key="a.id">
                                <div class="w-full flex items-center gap-2">
                                    <a :href="a.url" target="_blank" rel="noopener" download :title="a.name"
                                        class="flex-1 min-w-0 flex items-center justify-center gap-2 py-2 text-sm font-medium text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-lg transition">
                                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v8"/></svg>
                                        <span class="truncate" x-text="a.name"></span>
                                    </a>
                                    <form :action="a.delete_url" method="POST" data-confirm="{{ __('messages.remove_attachment_confirm') }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="w-9 h-9 flex items-center justify-center rounded-lg text-red-500 hover:bg-red-50" title="{{ __('messages.delete') }}">&times;</button>
                                    </form>
                                </div>
                            </template>
                            <form :action="bizDetail.delete_url" method="POST" data-confirm="{{ __('messages.remove_expense_confirm') }}">
                                @csrf @method('DELETE')
                                <button type="submit"
                                    class="w-full flex items-center justify-center gap-2 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    {{ __('messages.delete') }}
                                </button>
                            </form>
                            <button @click="showBizDetail = false"
                                class="w-full py-2 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">{{ __('messages.close') }}</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
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
        <!-- Desktop table (hidden on mobile) -->
        <div class="hidden md:block overflow-x-auto">
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
                        <td class="px-4 py-3 text-right font-semibold text-purple-600">{{ money($oe->amount) }}</td>
                        <td class="px-4 py-3 text-center">
                            <form action="{{ route('admin.revenue_expense.delete_other_expense', $oe) }}" method="POST" data-confirm="{{ __('messages.remove_expense_confirm') }}">
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
                        <td class="px-4 py-3 text-right font-semibold text-amber-600">{{ money($be->amount) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                @foreach($be->attachments as $a)
                                <a href="{{ $a->url() }}" target="_blank" rel="noopener" download
                                   title="{{ $a->original_name }}" aria-label="{{ __('messages.download_pdf') }}"
                                   class="inline-flex items-center justify-center w-7 h-7 rounded text-red-500 hover:bg-red-50 hover:text-red-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v8"/></svg>
                                </a>
                                @endforeach
                                <form action="{{ route('admin.revenue_expense.delete_business_expense', $be) }}" method="POST" data-confirm="{{ __('messages.remove_expense_confirm') }}">
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
                        <td class="px-4 py-3 text-right font-bold text-red-600">{{ money($totalOtherExpenses + $businessTotal) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Mobile compact list (shown on mobile only) -->
        <div class="md:hidden divide-y divide-slate-50">
            @foreach($otherExpenses as $oe)
            @php
                $bizJson = [
                    'name'       => $oe->description,
                    'type'       => 'other',
                    'date'       => \Carbon\Carbon::parse($oe->transaction_date)->format('M d, Y'),
                    'category'   => ucfirst(str_replace('_', ' ', $oe->category)),
                    'note'       => $oe->note,
                    'amount'     => (float) $oe->amount,
                    'recurring'  => false,
                    'attachments' => [],
                    'delete_url' => route('admin.revenue_expense.delete_other_expense', $oe),
                ];
            @endphp
            <div class="flex items-center gap-3 px-4 py-3 active:bg-slate-50 transition">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-slate-800 text-sm truncate">{{ $oe->description }}</p>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-purple-100 text-purple-700">{{ __('messages.type_other') }}</span>
                </div>
                <p class="text-sm font-bold text-purple-600 whitespace-nowrap">{{ money($oe->amount) }}</p>
                <button @click="openBizDetail(@js($bizJson))"
                    class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-sky-700 bg-sky-50 active:bg-sky-100 transition flex-shrink-0" title="{{ __('messages.view') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>
            @endforeach
            @foreach($businessExpenses as $be)
            @php
                $bizJson = [
                    'name'       => $be->expense_name,
                    'type'       => 'business',
                    'date'       => $be->expense_date->format('M d, Y'),
                    'category'   => ucfirst(str_replace('_', ' ', $be->category)),
                    'note'       => $be->note,
                    'amount'     => (float) $be->amount,
                    'recurring'  => (bool) $be->is_recurring,
                    'attachments' => $be->attachments->map(fn ($a) => [
                        'id' => $a->id,
                        'url' => $a->url(),
                        'name' => $a->original_name,
                        'delete_url' => route('admin.revenue_expense.delete_business_expense_attachment', [$be, $a]),
                    ])->all(),
                    'delete_url' => route('admin.revenue_expense.delete_business_expense', $be),
                ];
            @endphp
            <div class="flex items-center gap-3 px-4 py-3 active:bg-slate-50 transition">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-slate-800 text-sm truncate">{{ $be->expense_name }}</p>
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-100 text-amber-700">{{ __('messages.business_word') }}</span>
                </div>
                <p class="text-sm font-bold text-amber-600 whitespace-nowrap">{{ money($be->amount) }}</p>
                <button @click="openBizDetail(@js($bizJson))"
                    class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-sky-700 bg-sky-50 active:bg-sky-100 transition flex-shrink-0" title="{{ __('messages.view') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-8 text-slate-400">
            <p>{{ __('messages.no_other_business') }}</p>
        </div>
        @endif
    </div>
</div>

@endsection