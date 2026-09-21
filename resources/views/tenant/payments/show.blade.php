@extends('layouts.tenant')

{{--
    The confirmation step: what is being paid, spelled out, BEFORE any QR
    exists. Nothing on this page contacts a provider or mints a session —
    loading it costs nothing, which is what lets the tenant read it twice.
--}}

@section('content')
<div class="max-w-lg mx-auto space-y-5">

    <a href="{{ route('tenant.payments.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
        &larr; {{ __('messages.back') }}
    </a>

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
            <h1 class="text-lg font-bold text-slate-900">
                {{ $side === 'rent' ? __('messages.pay_rent') : __('messages.pay_charges') }}
            </h1>
            <p class="text-sm text-slate-500">{{ $obligation['label'] }}</p>
        </div>

        <dl class="px-5 py-4 space-y-3 text-sm">
            @if($rental->apartment)
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">{{ __('messages.property') }}</dt>
                    <dd class="font-medium text-slate-800 text-right">{{ $rental->apartment->floor->property->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">{{ __('messages.room') }}</dt>
                    <dd class="font-medium text-slate-800 text-right">{{ $rental->apartment->apartment_number }}</dd>
                </div>
            @endif

            @if($side === 'rent')
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">{{ __('messages.billing_period') }}</dt>
                    <dd class="font-medium text-slate-800 text-right">
                        {{ $obligation['billing_period']?->label() ?? $obligation['label'] }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">{{ __('messages.due_date') }}</dt>
                    <dd class="font-medium text-slate-800 text-right">{{ $obligation['due_date']->format('M j, Y') }}</dd>
                </div>
            @else
                {{-- Every charge by name: the tenant must be able to see what
                     the total is made of before they agree to pay it. --}}
                @foreach($obligation['charges']->where('paid_status', false) as $charge)
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">{{ __('messages.'.$charge->utility_type) }}</dt>
                        <dd class="font-medium text-slate-800 text-right">{{ money($charge->charge_amount) }}</dd>
                    </div>
                @endforeach
            @endif
        </dl>

        <div class="px-5 py-4 border-t border-slate-100 bg-slate-50 flex items-baseline justify-between">
            <span class="text-sm font-medium text-slate-600">{{ __('messages.amount') }}</span>
            <span class="text-2xl font-bold text-slate-900">{{ money($amount) }}</span>
        </div>
    </div>

    @if($payable)
        <form method="POST" action="{{ route('tenant.payments.pay', ['side' => $side, 'year' => $obligation['year'], 'month' => $obligation['month']]) }}">
            @csrf
            <button type="submit"
                class="w-full inline-flex items-center justify-center px-4 py-4 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700 active:bg-indigo-800">
                {{ __('messages.continue_to_khqr') }}
            </button>
        </form>
        <p class="text-xs text-center text-slate-400">{{ __('messages.khqr_scan_hint') }}</p>
    @else
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-center">
            <p class="text-sm text-emerald-800">{{ __('messages.tenant_pay_nothing_due') }}</p>
        </div>
    @endif
</div>
@endsection
