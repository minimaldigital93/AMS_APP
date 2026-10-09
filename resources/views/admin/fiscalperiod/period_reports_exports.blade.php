@extends('layouts.admin')

@section('content')
@php
    // Property scope follows the global top-bar selector (PropertyContext);
    // $selectedProperty is null for the "All properties" consolidated view.
    $selectedProperty = $selectedProperty ?? null;
    $propertyScopeLabel = $selectedProperty?->name ?? __('messages.all_properties_consolidated');

    // Icon paths for the <x-summary-card> tiles (same glyphs as the dashboard).
    $icons = [
        'revenue'  => 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z',
        'expense'  => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        'profit'   => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        'wallet'   => 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3',
        'building' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        'scale'    => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
        'check'    => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'alert'    => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        'in'       => 'M7 11l5-5m0 0l5 5m-5-5v12',
        'out'      => 'M17 13l-5 5m0 0l-5-5m5 5V6',
        'draw'     => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'cash'     => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
    ];

    // Month navigation ($reportMonth null = the whole period). The income
    // statement, cash flow, overview and monthly breakdown follow it; the
    // balance sheet and trial balance are as-of figures for the whole period.
    $rangeFrom = $reportMonth?->start_date ?? $fiscalperiod->opening_date;
    $rangeTo = $reportMonth?->end_date ?? $fiscalperiod->closing_date;
    $monthParams = fn ($m) => $m
        ? ['fiscalperiod' => $fiscalperiod->id, 'month' => $m->start_date->month, 'year' => $m->start_date->year]
        : ['fiscalperiod' => $fiscalperiod->id, 'month' => 'all'];
    $navUrl = fn ($m) => route('admin.fiscalperiod.reports', $monthParams($m));
    $isCurrentMonth = $reportMonth && $reportMonth->start_date->isSameMonth(now());
@endphp
<div class="container mx-auto py-8" x-data="reportsPdf({{ $errors->statementPdf->any() ? 'true' : 'false' }})" x-on:keydown.escape.window="pdfOpen = false; closeViewer()">
    {{-- Print letterhead. Landscape: the monthly income-statement matrix and
         the cash-flow table have too many columns for portrait A4. Only the
         active tab prints (inactive tabs carry Alpine's inline display:none). --}}
    <x-print.report-header
        :landscape="true"
        :title="__('messages.reports_exports')"
        :subtitle="$rangeFrom?->format('M j, Y').' — '.($rangeTo?->format('M j, Y') ?? __('messages.current'))"
        :meta="[
            __('messages.fiscal_period') => $fiscalperiod->name,
            __('messages.month') => $reportMonth?->name ?? __('messages.all_months'),
            __('messages.property') => $propertyScopeLabel,
        ]" />

    <!-- Header -->
    <div class="flex items-start justify-between mb-6 gap-4 no-print">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.reports_exports') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('messages.viewing_property', ['name' => $propertyScopeLabel]) }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 justify-end no-print">
            {{-- Property scope follows the global top-bar selector (PropertyContext);
                 it drives revenue, the income statement, cash flow & the monthly
                 breakdown. Exports inherit that same active-property context. --}}
            {{-- One export menu: the statement PDF (date popup → in-app viewer),
                 printing the tab on screen, and the CSV. --}}
            <div class="relative" x-data="{ menu: false }" x-on:click.outside="menu = false" x-on:keydown.escape="menu = false">
                <button type="button" x-on:click="menu = !menu" :aria-expanded="menu" aria-haspopup="menu"
                        class="bg-slate-800 text-white p-2 rounded-lg hover:bg-slate-700 flex items-center justify-center"
                        title="{{ __('messages.export') }}" aria-label="{{ __('messages.export') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </button>
                <div x-show="menu" x-cloak x-transition.origin.top.right role="menu"
                     class="absolute right-0 mt-2 w-60 bg-white rounded-xl shadow-lg border border-gray-100 py-1.5 z-40">
                    <button type="button" role="menuitem" x-on:click="menu = false; pdfOpen = true"
                            class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-gray-50">
                        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span class="text-sm font-medium text-slate-800">{{ __('messages.fs_menu_pdf') }}</span>
                    </button>
                    <button type="button" role="menuitem" x-on:click="menu = false; $nextTick(() => window.print())"
                            class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-gray-50">
                        <svg class="w-5 h-5 text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span class="text-sm font-medium text-slate-800">{{ __('messages.fs_menu_print') }}</span>
                    </button>
                    <a href="{{ route('admin.fiscalperiod.exportCSV', $reportMonth ? $monthParams($reportMonth) : $fiscalperiod->id) }}" role="menuitem" x-on:click="menu = false"
                       class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18M10 4v16M5 4h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                        <span class="text-sm font-medium text-slate-800">{{ __('messages.export_csv') }}</span>
                    </a>
                </div>
            </div>
            <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200 flex items-center" title="{{ __('messages.back') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg></a>
        </div>
    </div>

    {{-- Month navigation, the same control as the break-even page. "All months"
         is the whole period and sits before the first month; the open tab
         rides along in the #hash so stepping a month keeps it. --}}
    <div class="flex flex-wrap items-center justify-center gap-3 mb-6 no-print">
        <div class="inline-flex max-w-full items-center bg-white rounded-xl border border-slate-100 px-1.5 sm:px-2 py-1.5 gap-0.5 sm:gap-1">
            @if($reportMonth)
                <a :href="@js($navUrl($prevReportMonth)) + '#' + activeTab"
                   href="{{ $navUrl($prevReportMonth) }}"
                   class="inline-flex shrink-0 items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ $prevReportMonth ? __('messages.previous_month') : __('messages.all_months') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
            @else
                <span class="inline-flex shrink-0 items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-lg text-slate-200 cursor-not-allowed">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </span>
            @endif

            <div class="px-2 sm:px-4 py-2 min-w-0 sm:min-w-[180px] text-center">
                @if($reportMonth)
                    <span class="text-base sm:text-lg font-bold text-slate-800 whitespace-nowrap">{{ $reportMonth->start_date->translatedFormat('F') }}</span>
                    <span class="text-base sm:text-lg text-slate-400 ml-1">{{ $reportMonth->start_date->format('Y') }}</span>
                    @if($isCurrentMonth)
                        <span class="ml-2 hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">{{ __('messages.current') }}</span>
                    @endif
                @else
                    <span class="text-base sm:text-lg font-bold text-slate-800 whitespace-nowrap">{{ __('messages.all_months') }}</span>
                @endif
            </div>

            @if($nextReportMonth)
                <a :href="@js($navUrl($nextReportMonth)) + '#' + activeTab"
                   href="{{ $navUrl($nextReportMonth) }}"
                   class="inline-flex shrink-0 items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ __('messages.next_month') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span class="inline-flex shrink-0 items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-lg text-slate-200 cursor-not-allowed">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
            @endif
        </div>
        @if($reportMonth)
            <a :href="@js($navUrl(null)) + '#' + activeTab" href="{{ $navUrl(null) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-sky-600 transition"
               title="{{ __('messages.all_months') }}" aria-label="{{ __('messages.all_months') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </a>
        @endif
    </div>

    {{-- Statement PDF popup: pick the dates the statement covers (inside this
         fiscal period); FiscalPeriodController::statementPdf generates it and
         the viewer below shows it on screen. Validation errors come back in
         the statementPdf bag and reopen the popup. --}}
    @php
        $pdfMin = $fiscalperiod->opening_date->toDateString();
        $pdfMax = $fiscalperiod->closing_date?->toDateString();
        $pdfMonths = $monthlyPeriods->map(fn ($m) => [
            'label' => $m->name,
            'from' => max($pdfMin, \Carbon\Carbon::parse($m->start_date)->toDateString()),
            'to' => $pdfMax ? min($pdfMax, \Carbon\Carbon::parse($m->end_date)->toDateString()) : \Carbon\Carbon::parse($m->end_date)->toDateString(),
        ])->values();
        // Opened from a month, the popup starts on that month.
        $pdfMonthIndex = $reportMonth ? $monthlyPeriods->search(fn ($m) => $m->is($reportMonth)) : null;
        $pdfDefaultFrom = $pdfMonthIndex !== null ? $pdfMonths[$pdfMonthIndex]['from'] : $pdfMin;
        $pdfDefaultTo = $pdfMonthIndex !== null ? $pdfMonths[$pdfMonthIndex]['to'] : ($pdfMax ?? now()->toDateString());
    @endphp
    <div x-show="pdfOpen" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4 no-print" role="dialog" aria-modal="true" aria-labelledby="statementPdfTitle">
        <div class="absolute inset-0 bg-slate-900/40" @click="pdfOpen = false"></div>
        <form id="statementPdfForm" method="GET" action="{{ route('admin.fiscalperiod.statement-pdf', $fiscalperiod->id) }}"
              x-data="{ from: @js(old('from', $pdfDefaultFrom)), to: @js(old('to', $pdfDefaultTo)), months: @js($pdfMonths), month: @js(old('from') || $pdfMonthIndex === null ? '' : (string) $pdfMonthIndex) }"
              x-on:submit.prevent="viewPdf($el)"
              class="relative w-full max-w-md bg-white rounded-xl shadow-xl p-6 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="statementPdfTitle" class="text-lg font-semibold text-slate-800">{{ __('messages.fs_pdf_title') }}</h2>
                    <p class="text-xs text-gray-500 mt-1">{{ __('messages.fs_pdf_hint', ['name' => $propertyScopeLabel]) }}</p>
                </div>
                <button type="button" @click="pdfOpen = false" class="text-gray-400 hover:text-gray-600" title="{{ __('messages.cancel') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="from = @js($pdfMin); to = @js($pdfMax ?? now()->toDateString()); month = ''"
                        class="text-xs px-3 py-1.5 rounded-full border border-slate-200 text-slate-700 hover:bg-slate-50">{{ __('messages.fs_pdf_whole_period') }}</button>
                <select x-model="month" @change="if (month !== '') { from = months[month].from; to = months[month].to }"
                        class="text-xs border-slate-200 rounded-full py-1.5 pl-3 pr-8 text-slate-700">
                    <option value="">{{ __('messages.fs_pdf_pick_month') }}</option>
                    <template x-for="(m, i) in months" :key="i"><option :value="i" x-text="m.label"></option></template>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm">
                    <span class="text-gray-600">{{ __('messages.start_date') }}</span>
                    <input type="date" name="from" x-model="from" @input="month = ''" min="{{ $pdfMin }}" @if($pdfMax) max="{{ $pdfMax }}" @endif required
                           class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                </label>
                <label class="block text-sm">
                    <span class="text-gray-600">{{ __('messages.end_date') }}</span>
                    <input type="date" name="to" x-model="to" @input="month = ''" :min="from" @if($pdfMax) max="{{ $pdfMax }}" @endif required
                           class="mt-1 w-full border-gray-300 rounded-lg text-sm">
                </label>
            </div>

            @if($errors->statementPdf->any())
                <div class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{{ $errors->statementPdf->first() }}</div>
            @endif

            <div class="flex justify-end gap-2 pt-1">
                <button type="button" @click="pdfOpen = false" class="text-sm px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">{{ __('messages.cancel') }}</button>
                <button type="submit"
                        class="text-sm px-4 py-2 rounded-lg bg-slate-800 text-white hover:bg-slate-700 flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <span>{{ __('messages.fs_pdf_generate') }}</span>
                </button>
            </div>
        </form>
    </div>

    {{-- In-app PDF viewer. The statement is fetched, then drawn page by page
         with pdf.js (window.ensurePdfViewer) — the same on a desktop, a phone
         browser and the installed app, where opening the file itself would
         download it or leave the app with no way back. --}}
    <div x-show="viewer" x-cloak class="fixed inset-0 z-[80] flex flex-col bg-slate-100 no-print" role="dialog" aria-modal="true" aria-labelledby="pdfViewerTitle"
         x-effect="document.body.style.overflow = viewer ? 'hidden' : ''">
        <div class="flex items-center gap-2 px-3 sm:px-5 py-2.5 bg-white border-b border-gray-200 shadow-sm" style="padding-top: max(0.625rem, env(safe-area-inset-top));">
            <button type="button" x-on:click="closeViewer()" class="p-2 -ml-1 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700" title="{{ __('messages.close') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <div class="min-w-0 flex-1">
                <div id="pdfViewerTitle" class="text-sm font-semibold text-slate-800 truncate">{{ __('messages.fs_title') }}</div>
                <div class="text-xs text-gray-500 truncate" x-text="range"></div>
            </div>
            <button type="button" x-show="ready && canShare" x-on:click="sharePdf()"
                    class="text-sm bg-slate-800 text-white px-3 py-2 rounded-lg hover:bg-slate-700 flex items-center gap-1.5" title="{{ __('messages.fs_viewer_share') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12v7a2 2 0 002 2h12a2 2 0 002-2v-7M16 6l-4-4m0 0L8 6m4-4v13"/></svg>
                <span class="hidden sm:inline">{{ __('messages.fs_viewer_share') }}</span>
            </button>
            <a x-show="ready && (!standalone || !canShare)" :href="url" :download="fileName"
               class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200 flex items-center gap-1.5" title="{{ __('messages.download') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span class="hidden sm:inline">{{ __('messages.download') }}</span>
            </a>
            <button type="button" x-show="ready && !touch" x-on:click="printPdf()"
                    class="text-sm bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200 flex items-center gap-1.5" title="{{ __('messages.print') }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span class="hidden sm:inline">{{ __('messages.print') }}</span>
            </button>
        </div>
        <div class="flex-1 overflow-auto px-3 py-4 sm:p-6" style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
            <div x-show="loading" class="flex flex-col items-center justify-center gap-3 py-24 text-sm text-gray-500">
                <svg class="w-8 h-8 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                {{ __('messages.preparing_pdf') }}
            </div>
            <div x-show="error" class="max-w-md mx-auto mt-16 text-center text-sm text-gray-600 bg-white rounded-xl border border-gray-200 p-6">
                <p>{{ __('messages.fs_viewer_error') }}</p>
                <a x-show="ready" :href="url" :download="fileName" class="inline-block mt-3 text-slate-800 font-medium underline">{{ __('messages.download') }}</a>
            </div>
            <div x-ref="pdfPages" class="mx-auto max-w-4xl"></div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white rounded-t-lg shadow-sm border-b flex overflow-x-auto no-print">
        <button @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'border-b-2 border-slate-800 text-slate-800 bg-slate-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            Overview
        </button>
        <button @click="activeTab = 'income_statement'" :class="activeTab === 'income_statement' ? 'border-b-2 border-slate-800 text-slate-800 bg-slate-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            {{ __('messages.income_statement_title') }}
        </button>
        <button @click="activeTab = 'balance_sheet'" :class="activeTab === 'balance_sheet' ? 'border-b-2 border-slate-800 text-slate-800 bg-slate-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            {{ __('messages.balance_sheet') }}
        </button>
        <button @click="activeTab = 'cash_flow'" :class="activeTab === 'cash_flow' ? 'border-b-2 border-slate-800 text-slate-800 bg-slate-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            Cash Flow
        </button>
        <button @click="activeTab = 'trial_balance'" :class="activeTab === 'trial_balance' ? 'border-b-2 border-slate-800 text-slate-800 bg-slate-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            {{ __('messages.trial_balance') }}
        </button>
        <button @click="activeTab = 'monthly'" :class="activeTab === 'monthly' ? 'border-b-2 border-slate-800 text-slate-800 bg-slate-50' : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
                class="px-5 py-3 text-sm font-semibold whitespace-nowrap transition">
            {{ __('messages.monthly_breakdown') }}
        </button>
    </div>

    <!-- ==================== OVERVIEW TAB ==================== -->
    <div x-show="activeTab === 'overview'" class="bg-white rounded-b-lg shadow p-6">
        <!-- Summary Cards (screen only — the printed page is the statement below) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8 no-print">
            <x-summary-card :label="__('messages.total_revenue')" tone="emerald" :icon="$icons['revenue']"
                :value="money($periodFinancials['total_income'])"
                :hint="$periodFinancials['payment_count'].' payments'" />
            <x-summary-card :label="__('messages.total_expenses')" tone="red" :icon="$icons['expense']"
                :value="money($periodFinancials['total_expenses'])" />
            <x-summary-card :label="$periodFinancials['is_profitable'] ? 'Net Profit' : 'Net Loss'"
                :tone="$periodFinancials['is_profitable'] ? 'sky' : 'orange'" :icon="$icons['profit']"
                :value="($periodFinancials['is_profitable'] ? '+' : '').money($periodFinancials['net_income'])"
                :value-class="$periodFinancials['is_profitable'] ? 'text-green-700' : 'text-red-700'" />
            <x-summary-card :label="__('messages.period_balance')" tone="purple" :icon="$icons['wallet']"
                :value="money($cashFlow['closing_balance'])"
                :hint="'Open: '.money($cashFlow['opening_balance'])" />
        </div>

        <!-- Revenue vs Expenses Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 no-print">
            <div class="border border-gray-100 rounded-lg p-5">
                <h3 class="font-semibold text-sm text-gray-700 mb-3">{{ __('messages.revenue_sources') }}</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.rent_income') }}</span><span class="font-medium text-green-600">{{ money($periodFinancials['rent_income']) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.late_fees') }}</span><span class="font-medium text-green-600">{{ money($periodFinancials['late_fees']) }}</span></div>
                    @if($periodFinancials['other_income'] > 0)
                        <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.other_income') }}</span><span class="font-medium text-green-600">{{ money($periodFinancials['other_income']) }}</span></div>
                    @endif
                    <div class="flex justify-between border-t pt-2 font-semibold"><span>{{ __('messages.total') }}</span><span class="text-green-700">{{ money($periodFinancials['total_income']) }}</span></div>
                </div>
            </div>
            <div class="border border-gray-100 rounded-lg p-5">
                <h3 class="font-semibold text-sm text-gray-700 mb-3">{{ __('messages.expense_categories') }}</h3>
                <div class="space-y-2 text-sm">
                    @forelse($periodFinancials['utility_expenses'] as $type => $amount)
                        <div class="flex justify-between"><span class="text-gray-600 capitalize">{{ str_replace('_', ' ', $type) }}</span><span class="font-medium text-red-600">{{ money($amount) }}</span></div>
                    @empty
                        <p class="text-gray-400 text-xs">{{ __('messages.no_utility_expenses') }}</p>
                    @endforelse
                    @if($periodFinancials['fixed_expenses'] > 0)
                        <div class="flex justify-between"><span class="text-gray-600">{{ __('messages.fixed_other') }}</span><span class="font-medium text-red-600">{{ money($periodFinancials['fixed_expenses']) }}</span></div>
                    @endif
                    <div class="flex justify-between border-t pt-2 font-semibold"><span>{{ __('messages.total') }}</span><span class="text-red-700">{{ money($periodFinancials['total_expenses']) }}</span></div>
                </div>
            </div>
        </div>

        {{-- Printed Overview = the summary financial statement (the screen's cards
             above are no-print). The same partial is the downloadable PDF. --}}
        <div class="print-only">
            @include('admin.fiscalperiod.partials.summary-statement', [
                'pf' => $periodFinancials,
                'cash' => $cashFlow,
                'from' => $rangeFrom,
                'to' => $rangeTo,
                'scopeLabel' => $propertyScopeLabel,
            ])
        </div>
    </div>

    <!-- ==================== INCOME STATEMENT TAB ==================== -->
    <div x-show="activeTab === 'income_statement'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <h2 class="text-xl font-bold mb-1">{{ __('messages.income_statement_title') }}</h2>
        <p class="text-sm text-gray-500 mb-6">{{ $rangeFrom->format('M d, Y') }} – {{ $rangeTo?->format('M d, Y') }}</p>

        @if(count($incomeStatement['months']) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-300">
                            <th class="px-3 py-2 text-left font-semibold">{{ __('messages.account') }}</th>
                            @foreach($incomeStatement['months'] as $m)
                                <th class="px-3 py-2 text-right font-semibold">{{ $m['short'] }}</th>
                            @endforeach
                            <th class="px-3 py-2 text-right font-bold bg-gray-200">{{ __('messages.total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Revenue Section -->
                        <tr class="bg-green-50 border-b">
                            <td class="px-3 py-2 font-bold text-green-800" colspan="{{ count($incomeStatement['months']) + 2 }}">{{ __('messages.revenue_caps') }}</td>
                        </tr>
                        <tr class="border-b">
                            <td class="px-3 py-2 pl-6">{{ __('messages.rent_income') }}</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right">{{ $m['data']['rent_income'] > 0 ? money($m['data']['rent_income']) : '-' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold bg-gray-50">{{ money($incomeStatement['totals']['rent_income']) }}</td>
                        </tr>
                        <tr class="border-b">
                            <td class="px-3 py-2 pl-6">{{ __('messages.late_fees') }}</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right">{{ $m['data']['late_fees'] > 0 ? money($m['data']['late_fees']) : '-' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold bg-gray-50">{{ money($incomeStatement['totals']['late_fees']) }}</td>
                        </tr>
                        <tr class="border-b">
                            <td class="px-3 py-2 pl-6">{{ __('messages.other_income') }}</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right">{{ $m['data']['other_income'] > 0 ? money($m['data']['other_income']) : '-' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold bg-gray-50">{{ money($incomeStatement['totals']['other_income']) }}</td>
                        </tr>
                        <tr class="border-b-2 border-green-400 bg-green-50">
                            <td class="px-3 py-2 font-bold text-green-800">{{ __('messages.total_revenue') }}</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right font-bold text-green-700">{{ money($m['data']['total_income']) }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold text-green-800 bg-green-100">{{ money($incomeStatement['totals']['total_income']) }}</td>
                        </tr>

                        <!-- Expense Section -->
                        <tr class="bg-red-50 border-b">
                            <td class="px-3 py-2 font-bold text-red-800" colspan="{{ count($incomeStatement['months']) + 2 }}">{{ __('messages.expenses_caps') }}</td>
                        </tr>
                        <tr class="border-b">
                            <td class="px-3 py-2 pl-6">{{ __('messages.total_expenses') }}</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-2 text-right text-red-600">{{ $m['data']['total_expenses'] > 0 ? money($m['data']['total_expenses']) : '-' }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-bold text-red-700 bg-gray-50">{{ money($incomeStatement['totals']['total_expenses']) }}</td>
                        </tr>

                        <!-- Net Income -->
                        <tr class="border-t-2 border-gray-400 bg-gray-100">
                            <td class="px-3 py-3 font-bold text-lg">{{ __('messages.net_income_caps') }}</td>
                            @foreach($incomeStatement['months'] as $m)
                                <td class="px-3 py-3 text-right font-bold {{ $m['data']['net_income'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $m['data']['net_income'] >= 0 ? '+' : '' }}{{ money($m['data']['net_income']) }}
                                </td>
                            @endforeach
                            <td class="px-3 py-3 text-right font-bold text-lg {{ $incomeStatement['totals']['net_income'] >= 0 ? 'text-green-800' : 'text-red-800' }} bg-gray-200">
                                {{ $incomeStatement['totals']['net_income'] >= 0 ? '+' : '' }}{{ money($incomeStatement['totals']['net_income']) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8 text-gray-500">{{ __('messages.no_monthly_periods_fp') }}</div>
        @endif
    </div>

    <!-- ==================== BALANCE SHEET TAB ==================== -->
    <div x-show="activeTab === 'balance_sheet'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <h2 class="text-xl font-bold mb-1">{{ __('messages.balance_sheet') }}</h2>
        <p class="text-sm text-gray-500 {{ $reportMonth ? 'mb-1' : 'mb-6' }}">As of {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>
        @if($reportMonth)
            <p class="text-xs text-gray-400 mb-6 no-print">{{ __('messages.report_period_wide_note') }}</p>
        @endif

        @if($selectedProperty)
            <div class="mb-6 px-4 py-2.5 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 flex items-start gap-2">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ __('messages.account_wide_note') }}</span>
            </div>
        @endif

        <!-- Summary -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <x-summary-card :label="__('messages.total_assets')" tone="sky" :icon="$icons['building']"
                :value="money($summary['total_assets'])" />
            <x-summary-card :label="__('messages.total_liabilities')" tone="red" :icon="$icons['expense']"
                :value="money($summary['total_liabilities'])" value-class="text-red-600" />
            <x-summary-card :label="__('messages.total_equity')" tone="purple" :icon="$icons['scale']"
                :value="money($summary['total_equity'])" />
            <x-summary-card :label="__('messages.status')"
                :tone="$summary['balance_check'] ? 'emerald' : 'amber'"
                :icon="$summary['balance_check'] ? $icons['check'] : $icons['alert']"
                :value="$summary['balance_check'] ? 'Balanced' : 'Unbalanced'"
                :value-class="$summary['balance_check'] ? 'text-green-600' : 'text-amber-600'" />
        </div>

        <!-- Balance Sheet Items -->
        @if($balanceSheetItems->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-300">
                            <th class="px-4 py-2 text-left font-semibold">{{ __('messages.type') }}</th>
                            <th class="px-4 py-2 text-left font-semibold">{{ __('messages.sub_type') }}</th>
                            <th class="px-4 py-2 text-left font-semibold">{{ __('messages.name') }}</th>
                            <th class="px-4 py-2 text-right font-semibold">{{ __('messages.amount') }}</th>
                            <th class="px-4 py-2 text-left font-semibold">{{ __('messages.date') }}</th>
                            <th class="px-4 py-2 text-left font-semibold">{{ __('messages.ref') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($balanceSheetItems->groupBy('item_type') as $type => $items)
                            <tr class="bg-gray-50 border-b">
                                <td class="px-4 py-2 font-bold uppercase" colspan="6">{{ $type }}</td>
                            </tr>
                            @foreach($items as $item)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $item->item_type === 'asset' ? 'bg-sky-100 text-sky-800' : ($item->item_type === 'liability' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800') }}">
                                            {{ ucfirst($item->item_type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">{{ ucfirst(str_replace('_', ' ', $item->sub_type)) }}</td>
                                    <td class="px-4 py-2 font-medium">{{ $item->name }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">{{ money($item->amount) }}</td>
                                    <td class="px-4 py-2">{{ $item->as_of_date->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $item->reference_number ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Equation Verification -->
            <div class="mt-6 p-4 {{ $summary['balance_check'] ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200' }} border rounded-lg">
                <p class="text-sm {{ $summary['balance_check'] ? 'text-green-700' : 'text-amber-700' }}">
                    <strong>Assets ({{ money($summary['total_assets']) }})</strong> =
                    <strong>Liabilities ({{ money($summary['total_liabilities']) }})</strong> +
                    <strong>Equity ({{ money($summary['total_equity']) }})</strong>
                    &mdash; {{ $summary['balance_check'] ? 'Balanced' : 'Not balanced' }}
                </p>
            </div>
        @else
            <div class="text-center py-8 text-gray-500">
                No balance sheet items recorded. 
                <a href="{{ route('admin.fiscalperiod.balance-sheet', $fiscalperiod->id) }}" class="text-sky-600 font-semibold">{{ __('messages.add_items') }}</a>
            </div>
        @endif
    </div>

    <!-- ==================== CASH FLOW TAB ==================== -->
    <div x-show="activeTab === 'cash_flow'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <h2 class="text-xl font-bold mb-1">{{ __('messages.cash_flow_statement') }}</h2>
        <p class="text-sm text-gray-500 mb-6">{{ $rangeFrom->format('M d, Y') }} – {{ $rangeTo?->format('M d, Y') }}</p>

        @if($selectedProperty)
            <div class="mb-6 px-4 py-2.5 bg-sky-50 border border-sky-200 rounded-lg text-sm text-sky-800 flex items-start gap-2">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ __('messages.cash_flow_property_note') }}</span>
            </div>
        @endif

        <!-- Cash Flow Summary -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <x-summary-card :label="__('messages.opening_cash')" tone="slate" :icon="$icons['cash']"
                :value="money($cashFlow['opening_balance'])" />
            <x-summary-card :label="__('messages.total_cash_in')" tone="emerald" :icon="$icons['in']"
                :value="'+'.money($cashFlow['total_cash_in'])" value-class="text-green-600" />
            <x-summary-card :label="__('messages.total_cash_out')" tone="red" :icon="$icons['out']"
                :value="'-'.money($cashFlow['total_cash_out'])" value-class="text-red-600" />
            <x-summary-card :label="__('messages.owner_draws')" tone="purple" :icon="$icons['draw']"
                :value="'-'.money($cashFlow['total_withdrawals'])" value-class="text-purple-600" />
            <x-summary-card :label="__('messages.closing_cash')" tone="sky" :icon="$icons['wallet']"
                :value="money($cashFlow['closing_balance'])" class="col-span-2 lg:col-span-1" />
        </div>

        @if(count($cashFlow['months']) > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-100 border-b-2 border-gray-300">
                            <th class="px-3 py-2 text-left font-semibold">{{ __('messages.month') }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ __('messages.opening_balance') }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ __('messages.cash_in') }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ __('messages.cash_out') }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ __('messages.net_cash_flow') }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ __('messages.owner_draw') }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ __('messages.closing_balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cashFlow['months'] as $m)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-3 py-2 font-medium">{{ $m['name'] }}</td>
                                <td class="px-3 py-2 text-right">{{ money($m['opening_balance']) }}</td>
                                <td class="px-3 py-2 text-right text-green-600 font-medium">+{{ money($m['cash_in']) }}</td>
                                <td class="px-3 py-2 text-right text-red-600 font-medium">-{{ money($m['cash_out']) }}</td>
                                <td class="px-3 py-2 text-right font-bold {{ $m['net_cash_flow'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $m['net_cash_flow'] >= 0 ? '+' : '' }}{{ money($m['net_cash_flow']) }}
                                </td>
                                <td class="px-3 py-2 text-right text-purple-700 font-medium">{{ $m['owner_withdrawal'] > 0 ? '-'.money_number($m['owner_withdrawal']) : '—' }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ money($m['closing_balance']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 border-t-2 border-gray-400">
                            <td class="px-3 py-3 font-bold">{{ __('messages.total_caps') }}</td>
                            <td class="px-3 py-3 text-right font-bold">{{ money($cashFlow['opening_balance']) }}</td>
                            <td class="px-3 py-3 text-right font-bold text-green-700">+{{ money($cashFlow['total_cash_in']) }}</td>
                            <td class="px-3 py-3 text-right font-bold text-red-700">-{{ money($cashFlow['total_cash_out']) }}</td>
                            <td class="px-3 py-3 text-right font-bold {{ $cashFlow['net_change'] >= 0 ? 'text-green-800' : 'text-red-800' }}">
                                {{ $cashFlow['net_change'] >= 0 ? '+' : '' }}{{ money($cashFlow['net_change']) }}
                            </td>
                            <td class="px-3 py-3 text-right font-bold text-purple-700">{{ $cashFlow['total_withdrawals'] > 0 ? '-'.money_number($cashFlow['total_withdrawals']) : '—' }}</td>
                            <td class="px-3 py-3 text-right font-bold">{{ money($cashFlow['closing_balance']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Cash Flow Visual Bar -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('messages.cash_position_trend') }}</h3>
                <div class="flex items-end gap-1" style="height: 120px;">
                    @php
                        $maxBal = max(array_column($cashFlow['months'], 'closing_balance'));
                        $maxBal = $maxBal > 0 ? $maxBal : 1;
                    @endphp
                    @foreach($cashFlow['months'] as $m)
                        @php
                            $height = $maxBal > 0 ? max(($m['closing_balance'] / $maxBal) * 100, 5) : 5;
                        @endphp
                        <div class="flex-1 flex flex-col items-center group">
                            <div class="w-full rounded-t {{ $m['net_cash_flow'] >= 0 ? 'bg-green-400' : 'bg-red-400' }} transition-all relative"
                                 style="height: {{ $height }}%"
                                 title="{{ money($m['closing_balance']) }}">
                            </div>
                            <span class="text-[10px] text-gray-500 mt-1">{{ $m['short'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="text-center py-8 text-gray-500">{{ __('messages.no_monthly_periods') }}</div>
        @endif
    </div>

    <!-- ==================== TRIAL BALANCE TAB ==================== -->
    <div x-show="activeTab === 'trial_balance'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <h2 class="text-xl font-bold mb-1">{{ __('messages.trial_balance') }}</h2>
        <p class="text-sm text-gray-500 mb-1">As of {{ $fiscalperiod->closing_date->format('M d, Y') }}</p>
        @if($reportMonth)
            <p class="text-xs text-gray-400 mb-1 no-print">{{ __('messages.report_period_wide_note') }}</p>
        @endif
        <p class="text-xs text-gray-400 mb-6">Post-closing: net income (retained earnings of {{ money($trialBalance['retained_earnings']) }}) is folded into equity, not listed as a separate account.</p>

        @if($selectedProperty)
            <div class="mb-6 px-4 py-2.5 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 flex items-start gap-2">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ __('messages.account_wide_note') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Debits -->
            <div>
                <h3 class="text-sm font-bold text-gray-700 uppercase mb-3 flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-sky-500"></span> Debit Accounts
                </h3>
                <div class="bg-gray-50 rounded-lg overflow-hidden">
                    @forelse($trialBalance['debits'] as $debit)
                        <div class="flex justify-between items-center px-4 py-3 border-b">
                            <span class="text-sm">{{ $debit['account'] }}</span>
                            <span class="text-sm font-semibold">{{ money($debit['amount']) }}</span>
                        </div>
                    @empty
                        <div class="px-4 py-3 text-gray-400 text-sm">{{ __('messages.no_debit_entries') }}</div>
                    @endforelse
                    <div class="flex justify-between items-center px-4 py-3 bg-sky-100 border-t-2 border-sky-300">
                        <span class="font-bold">{{ __('messages.total_debits') }}</span>
                        <span class="font-bold text-sky-700">{{ money($trialBalance['total_debits']) }}</span>
                    </div>
                </div>
            </div>

            <!-- Credits -->
            <div>
                <h3 class="text-sm font-bold text-gray-700 uppercase mb-3 flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-green-500"></span> Credit Accounts
                </h3>
                <div class="bg-gray-50 rounded-lg overflow-hidden">
                    @forelse($trialBalance['credits'] as $credit)
                        <div class="flex justify-between items-center px-4 py-3 border-b">
                            <span class="text-sm">{{ $credit['account'] }}</span>
                            <span class="text-sm font-semibold">{{ money($credit['amount']) }}</span>
                        </div>
                    @empty
                        <div class="px-4 py-3 text-gray-400 text-sm">{{ __('messages.no_credit_entries') }}</div>
                    @endforelse
                    <div class="flex justify-between items-center px-4 py-3 bg-green-100 border-t-2 border-green-300">
                        <span class="font-bold">{{ __('messages.total_credits') }}</span>
                        <span class="font-bold text-green-700">{{ money($trialBalance['total_credits']) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Check -->
        <div class="p-4 rounded-lg border {{ $trialBalance['is_balanced'] ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200' }}">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold {{ $trialBalance['is_balanced'] ? 'text-green-900' : 'text-amber-900' }}">
                        {{ $trialBalance['is_balanced'] ? 'Trial Balance is balanced' : 'Trial Balance discrepancy' }}
                    </p>
                    @if(!$trialBalance['is_balanced'])
                        <p class="text-sm text-amber-700 mt-1">
                            Difference: {{ money(abs($trialBalance['difference'])) }}
                        </p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">{{ __('messages.debits_label') }} <strong>{{ money($trialBalance['total_debits']) }}</strong></p>
                    <p class="text-sm text-gray-600">{{ __('messages.credits_label') }} <strong>{{ money($trialBalance['total_credits']) }}</strong></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== MONTHLY BREAKDOWN TAB ==================== -->
    <div x-show="activeTab === 'monthly'" x-cloak class="bg-white rounded-b-lg shadow p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold">{{ __('messages.monthly_breakdown') }}</h2>
                <p class="text-sm text-gray-500">{{ __('messages.month_by_month') }}</p>
            </div>
            <a href="{{ route('admin.fiscalperiod.show', $fiscalperiod->id) }}" class="text-sky-600 text-sm font-semibold hover:underline no-print">
                Manage Monthly Periods →
            </a>
        </div>

        @if(count($monthlyData) > 0)
            <div class="space-y-4">
                @foreach($monthlyData as $md)
                    @php $mp = $md['period']; $mf = $md['financials']; @endphp
                    <div class="border rounded-lg overflow-hidden {{ $mp->isClosed() ? 'border-gray-200' : 'border-sky-200' }}">
                        <div class="flex items-center justify-between px-5 py-3 {{ $mp->isClosed() ? 'bg-gray-50' : 'bg-sky-50' }}">
                            <div class="flex items-center gap-3">
                                <h3 class="font-semibold">{{ $mp->name }}</h3>
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $mp->status === 'open' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ status_label($mp->status) }}
                                </span>
                            </div>
                            <span class="text-sm text-gray-600">{{ $mp->start_date->format('M d') }} – {{ $mp->end_date->format('M d') }}</span>
                        </div>
                        <div class="px-5 py-4">
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500 text-xs">{{ __('messages.opening_bal') }}</p>
                                    <p class="font-semibold">{{ money($mp->opening_balance) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">{{ __('messages.income') }}</p>
                                    <p class="font-semibold text-green-600">+{{ money($mf['total_income']) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">{{ __('messages.expenses_word') }}</p>
                                    <p class="font-semibold text-red-600">-{{ money($mf['total_expenses']) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">{{ __('messages.net_income') }}</p>
                                    <p class="font-bold {{ $mf['net_income'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $mf['net_income'] >= 0 ? '+' : '' }}{{ money($mf['net_income']) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-500 text-xs">{{ __('messages.closing_bal') }}</p>
                                    <p class="font-semibold">
                                        @if($mp->isClosed())
                                            {{ money($mp->closing_balance) }}
                                        @else
                                            <span class="text-gray-400">{{ money($mp->opening_balance + $mf['net_income'] - (float) $mp->owner_withdrawal) }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8 text-gray-500">{{ __('messages.no_monthly_periods') }}</div>
        @endif
    </div>

    <x-print.report-footer />
</div>
@endsection

@push('scripts')
<script>
    // Reports & Exports page state: the active tab, the statement date popup
    // and the in-app PDF viewer. The PDF is never handed to the browser as a
    // download — it is fetched and drawn in the viewer, which then offers
    // Download / Print on a computer and Share (save, print, send) on a phone.
    function reportsPdf(popupOpen) {
        const touch = window.matchMedia('(pointer: coarse)').matches;
        const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        const fmt = v => v ? new Date(v + 'T00:00:00').toLocaleDateString(document.documentElement.lang || undefined, { day: '2-digit', month: 'short', year: 'numeric' }) : '';
        let file = null; // kept off the reactive state: navigator.share needs the raw File

        return {
            // The open tab survives month navigation via the #hash.
            activeTab: ['overview', 'income_statement', 'balance_sheet', 'cash_flow', 'trial_balance', 'monthly']
                .includes(location.hash.slice(1)) ? location.hash.slice(1) : 'overview',
            pdfOpen: popupOpen,
            viewer: false, loading: false, error: false, ready: false,
            url: null, fileName: null, range: '', canShare: false,
            touch, standalone, run: 0,

            async viewPdf(form) {
                const params = new URLSearchParams(new FormData(form));
                const href = form.action + '?' + params.toString();
                const run = ++this.run;
                this.clear();
                this.range = fmt(params.get('from')) + ' – ' + fmt(params.get('to'));
                this.releaseForm(form);
                this.pdfOpen = false;
                this.viewer = true;
                this.loading = true;
                try {
                    const res = await fetch(href, { credentials: 'same-origin', headers: { Accept: 'application/pdf' } });
                    if (!res.ok || !(res.headers.get('Content-Type') || '').includes('application/pdf')) {
                        // A validation error redirects back to this page; a plain
                        // navigation shows it, with the popup reopened.
                        window.location.href = href;
                        return;
                    }
                    const m = (res.headers.get('Content-Disposition') || '').match(/filename="?([^";]+)"?/);
                    const bytes = await res.arrayBuffer();
                    if (run !== this.run) return;
                    file = new File([bytes], m ? m[1] : 'financial-statement.pdf', { type: 'application/pdf' });
                    this.url = URL.createObjectURL(file);
                    this.fileName = file.name;
                    this.canShare = touch && !!navigator.canShare && navigator.canShare({ files: [file] });
                    this.ready = true;
                    const render = await window.ensurePdfViewer();
                    if (run !== this.run) return;
                    await render(this.$refs.pdfPages, bytes);
                } catch (e) {
                    if (run === this.run) this.error = true;
                } finally {
                    if (run === this.run) this.loading = false;
                }
            },

            // The layout's global submit handler disables the button and adds a
            // spinner, expecting the page to navigate away. This form stays on
            // the page, so undo that or the next "View PDF" is a dead button.
            releaseForm(form) {
                delete form.dataset.submitting;
                form.querySelectorAll('button[type="submit"]').forEach(btn => {
                    btn.disabled = false;
                    btn.removeAttribute('aria-disabled');
                    btn.querySelectorAll('.spinner').forEach(s => s.remove());
                });
            },

            closeViewer() {
                if (!this.viewer) return;
                this.run++;
                this.viewer = false;
                this.clear();
            },

            clear() {
                if (this.url) URL.revokeObjectURL(this.url);
                file = null;
                this.url = null; this.fileName = null;
                this.ready = false; this.error = false; this.loading = false; this.canShare = false;
                if (this.$refs.pdfPages) this.$refs.pdfPages.replaceChildren();
            },

            sharePdf() {
                if (file) navigator.share({ files: [file], title: file.name }).catch(() => {});
            },

            // Print the PDF itself (not this page) through a hidden frame.
            printPdf() {
                const url = this.url;
                const frame = document.createElement('iframe');
                frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
                frame.onload = () => {
                    try { frame.contentWindow.focus(); frame.contentWindow.print(); } catch (e) { window.open(url, '_blank'); }
                    setTimeout(() => frame.remove(), 60000);
                };
                frame.src = url;
                document.body.appendChild(frame);
            },
        };
    }
</script>
@endpush
