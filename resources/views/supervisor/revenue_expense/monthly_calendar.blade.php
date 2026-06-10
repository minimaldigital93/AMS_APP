@extends('layouts.supervisor')

@section('content')
@php
    $isCurrentMonth = $startOfMonth->isSameMonth(now());
    $isFutureMonth = $startOfMonth->gt(now()->startOfMonth());
@endphp
<div class="max-w-7xl mx-auto px-4 py-6 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.monthly_calendar') }}</h1>
        <a href="{{ route('supervisor.revenue_expense.index') }}"
           class="inline-flex items-center justify-center h-10 w-10 bg-slate-800 hover:bg-slate-700 text-white rounded-lg transition flex-shrink-0"
           title="{{ __('messages.back') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
    </div>

    {{-- Month Navigation --}}
    <div class="flex justify-center">
        <div class="inline-flex items-center bg-white rounded-xl border border-slate-100 px-2 py-1.5 gap-1">
            <a href="{{ route('supervisor.revenue_expense.monthly_calendar', ['month' => $prevMonth->month, 'year' => $prevMonth->year]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ __('messages.previous_month') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div class="px-3 sm:px-4 py-2 min-w-[150px] sm:min-w-[180px] text-center">
                <span class="text-lg font-bold text-slate-800">{{ $startOfMonth->format('F') }}</span>
                <span class="text-lg text-slate-400 ml-1">{{ $startOfMonth->format('Y') }}</span>
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
            <a href="{{ route('supervisor.revenue_expense.monthly_calendar', ['month' => $nextMonth->month, 'year' => $nextMonth->year]) }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-sky-600 transition" title="{{ __('messages.next_month') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            @if(!$isCurrentMonth)
            <a href="{{ route('supervisor.revenue_expense.monthly_calendar') }}"
               class="ml-1 inline-flex items-center justify-center w-10 h-10 rounded-lg text-sky-600 bg-sky-50 hover:bg-sky-100 transition" title="{{ __('messages.go_to_current_month') }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </a>
            @endif
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
        <div class="bg-white rounded-xl border border-slate-100 p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-emerald-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-slate-400 font-medium truncate">{{ __('messages.total_income') }}</p>
                    <p class="text-lg sm:text-xl font-bold text-emerald-600">${{ number_format($monthTotalIncome, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-slate-400 font-medium truncate">{{ __('messages.total_expenses') }}</p>
                    <p class="text-lg sm:text-xl font-bold text-red-600">${{ number_format($monthTotalExpense, 2) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg {{ $monthNet >= 0 ? 'bg-sky-50' : 'bg-amber-50' }} flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 {{ $monthNet >= 0 ? 'text-sky-600' : 'text-amber-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-slate-400 font-medium truncate">{{ __('messages.net_profit_loss') }}</p>
                    <p class="text-lg sm:text-xl font-bold {{ $monthNet >= 0 ? 'text-sky-600' : 'text-amber-600' }}">
                        {{ $monthNet >= 0 ? '+' : '' }}${{ number_format($monthNet, 2) }}
                    </p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-100 p-4 sm:p-5">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-violet-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-slate-400 font-medium truncate">{{ __('messages.best_day') }}</p>
                    @if($bestDay)
                        <p class="text-lg sm:text-xl font-bold text-slate-800 leading-tight">{{ $startOfMonth->copy()->day($bestDay)->format('M d') }}</p>
                        <p class="text-[11px] text-emerald-600 font-medium">+${{ number_format($calendarDays[$bestDay]['net'], 2) }}</p>
                    @else
                        <p class="text-sm text-slate-300">{{ __('messages.no_data') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 bg-emerald-500 rounded-full inline-block"></span> {{ __('messages.income') }}</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 bg-red-500 rounded-full inline-block"></span> {{ __('messages.expense') }}</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 border-2 border-blue-500 rounded-md inline-block"></span> {{ __('messages.today') }}</span>
        <span class="sm:hidden flex items-center gap-1.5 text-slate-400">· {{ __('messages.net_profit_loss') }}</span>
    </div>

    {{-- Calendar Grid --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-2 sm:p-4">
        {{-- Day Headers --}}
        <div class="grid grid-cols-7 gap-1 sm:gap-1.5 mb-1 sm:mb-2">
            @foreach(['day_sun','day_mon','day_tue','day_wed','day_thu','day_fri','day_sat'] as $dayKey)
                <div class="text-center text-[10px] sm:text-xs font-semibold text-slate-400 py-1 uppercase tracking-wider">{{ __('messages.' . $dayKey) }}</div>
            @endforeach
        </div>

        {{-- Calendar Days --}}
        <div class="grid grid-cols-7 gap-1 sm:gap-1.5">
            {{-- Empty cells for offset --}}
            @for($i = 0; $i < $firstDayOfWeek; $i++)
                <div></div>
            @endfor

            {{-- Actual days --}}
            @for($d = 1; $d <= $daysInMonth; $d++)
                @php
                    $dayData = $calendarDays[$d];
                    $hasData = $dayData['tx_count'] > 0;
                    $hasBoth = $dayData['income'] > 0 && $dayData['expense'] > 0;
                    $isToday = $dayData['is_today'];
                    $isFuture = $dayData['is_future'];
                    $net = $dayData['net'];
                    $netSign = $net > 0 ? '+' : ($net < 0 ? '-' : '');
                    $netColor = $net > 0 ? 'text-emerald-600' : ($net < 0 ? 'text-red-600' : 'text-slate-400');
                    $netBg = $net > 0 ? 'bg-emerald-50' : ($net < 0 ? 'bg-red-50' : 'bg-slate-100');

                    $cell = 'flex flex-col min-h-[58px] sm:min-h-[116px] p-1 sm:p-2 rounded-lg sm:rounded-xl border transition';
                    if ($isToday) $cell .= ' border-blue-300 bg-blue-50/50 ring-1 ring-blue-200';
                    elseif ($isFuture) $cell .= ' border-slate-100 bg-slate-50/50';
                    elseif ($hasData) $cell .= ' border-slate-100 bg-white hover:border-slate-200 hover:shadow-sm';
                    else $cell .= ' border-slate-100 bg-white';
                @endphp
                <div class="{{ $cell }}">
                    {{-- Day number --}}
                    <div class="flex items-center justify-between">
                        <span class="{{ $isToday
                            ? 'bg-blue-500 text-white w-5 h-5 sm:w-6 sm:h-6 rounded-full flex items-center justify-center text-[10px] sm:text-xs font-bold'
                            : ($isFuture ? 'text-slate-300 text-[11px] sm:text-sm font-semibold pl-0.5' : 'text-slate-600 text-[11px] sm:text-sm font-semibold pl-0.5') }}">
                            {{ $d }}
                        </span>
                        @if($hasData)
                            <span class="hidden sm:inline text-[10px] text-slate-400">{{ $dayData['tx_count'] }} tx</span>
                        @endif
                    </div>

                    @if($hasData)
                        {{-- Desktop: income / expense split + combined net --}}
                        <div class="hidden sm:flex flex-col items-end gap-0.5 mt-auto">
                            @if($dayData['income'] > 0)
                                <span class="text-[11px] font-semibold text-emerald-600">+${{ number_format($dayData['income'], 0) }}</span>
                            @endif
                            @if($dayData['expense'] > 0)
                                <span class="text-[11px] font-semibold text-red-600">-${{ number_format($dayData['expense'], 0) }}</span>
                            @endif
                            @if($hasBoth)
                                <span class="mt-0.5 pt-0.5 border-t border-slate-100 w-full text-right text-xs font-bold {{ $netColor }}">{{ $netSign }}${{ number_format(abs($net), 0) }}</span>
                            @endif
                        </div>

                        {{-- Mobile: single color-coded net pill --}}
                        <div class="sm:hidden mt-auto flex justify-center">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[11px] font-bold leading-none {{ $netColor }} {{ $netBg }}">{{ $netSign }}${{ number_format(abs($net), 0) }}</span>
                        </div>
                    @endif
                </div>
            @endfor
        </div>
    </div>

    {{-- Daily Breakdown List (scrollable) --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/70">
            <h3 class="text-sm font-semibold text-slate-700">{{ __('messages.daily_breakdown') }}</h3>
        </div>
        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
            @php $hasAnyTx = collect($calendarDays)->where('tx_count', '>', 0)->isNotEmpty(); @endphp
            @if(!$hasAnyTx)
                <p class="px-4 py-8 text-center text-sm text-slate-400">{{ __('messages.no_data') }}</p>
            @endif
            @for($d = $daysInMonth; $d >= 1; $d--)
                @php $dayData = $calendarDays[$d]; @endphp
                @if($dayData['tx_count'] > 0)
                    @php $hasBoth = $dayData['income'] > 0 && $dayData['expense'] > 0; @endphp
                    <div class="flex items-center justify-between gap-2 px-4 py-2.5 hover:bg-slate-50 {{ $dayData['is_today'] ? 'bg-blue-50/40' : '' }}">
                        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                            <span class="text-sm font-medium text-slate-700 whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($dayData['date'])->format('M d, D') }}
                            </span>
                            <span class="hidden sm:inline text-xs text-slate-400">{{ $dayData['tx_count'] }} {{ __('messages.transactions') }}</span>
                        </div>
                        <div class="flex items-center gap-3 sm:gap-4 text-sm flex-shrink-0">
                            @if($dayData['income'] > 0)
                                <span class="text-emerald-600 font-medium">+${{ number_format($dayData['income'], 2) }}</span>
                            @endif
                            @if($dayData['expense'] > 0)
                                <span class="text-red-600 font-medium">-${{ number_format($dayData['expense'], 2) }}</span>
                            @endif
                            {{-- Net total only when there's both income and expense to combine --}}
                            @if($hasBoth)
                                <span class="font-semibold {{ $dayData['net'] >= 0 ? 'text-emerald-700' : 'text-red-700' }} w-24 text-right">
                                    {{ $dayData['net'] >= 0 ? '+' : '' }}${{ number_format($dayData['net'], 2) }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            @endfor
        </div>
    </div>
</div>
@endsection
