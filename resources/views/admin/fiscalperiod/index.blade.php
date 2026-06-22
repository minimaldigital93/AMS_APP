@extends('layouts.admin')

@section('content')
<div class="container mx-auto py-8 max-w-4xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.fiscal_periods') }}</h1>
        @if($hasOpenPeriod)
            <span title="{{ __('messages.flash_fp_close_current_first') }}"
                class="bg-gray-200 text-gray-400 px-4 py-2 rounded-lg text-sm font-semibold cursor-not-allowed select-none">
                + New Period
            </span>
        @else
            <a href="{{ route('admin.fiscalperiod.create') }}" class="bg-slate-800 text-white px-4 py-2 rounded-lg hover:bg-slate-700 text-sm font-semibold">
                + New Period
            </a>
        @endif
    </div>

    @if($hasOpenPeriod)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg px-4 py-3 mb-6">
            {{ __('messages.flash_fp_close_current_first') }}
        </div>
    @endif

    @if($fiscalPeriods->count())
        <div class="space-y-4">
            @foreach($fiscalPeriods as $period)
                <a href="{{ route('admin.fiscalperiod.show', $period->id) }}" class="block bg-white rounded-lg shadow hover:shadow-md transition p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-3 mb-1">
                                <h2 class="text-lg font-semibold">{{ $period->name }}</h2>
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $period->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($period->status) }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500">
                                {{ $period->opening_date->format('M d, Y') }} — {{ $period->closing_date->format('M d, Y') }}
                                <span class="text-gray-400 mx-1">·</span>
                                {{ $period->opening_date->diffInDays($period->closing_date) }} days
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-500">{{ __('messages.balance') }}</p>
                            <p class="text-lg font-bold">${{ number_format($period->opening_balance, 2) }}</p>
                            @if($period->status === 'closed' && $period->closing_balance != 0)
                                <p class="text-xs {{ $period->closing_balance >= $period->opening_balance ? 'text-green-600' : 'text-red-600' }}">
                                    → ${{ number_format($period->closing_balance, 2) }}
                                </p>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $fiscalPeriods->links() }}</div>
    @else
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <p class="text-gray-500 mb-4">{{ __('messages.no_fiscal_periods_yet') }}</p>
            <a href="{{ route('admin.fiscalperiod.create') }}" class="inline-block bg-slate-800 text-white px-5 py-2 rounded-lg hover:bg-slate-700 font-semibold">
                Create First Period
            </a>
        </div>
    @endif
</div>
@endsection
