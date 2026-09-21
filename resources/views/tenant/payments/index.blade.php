@extends('layouts.tenant')

{{--
    What the tenant owes, stated the way the landlord's collection page states
    it (TenantObligationService is the shared derivation, pinned by
    TenantObligationParityTest).

    Rent and charges are kept visually separate and each charge is named,
    because "you owe $297" tells a tenant nothing they can act on or dispute.
    The two sides settle on separate visits in this app, so they are offered as
    two payments rather than one combined total.
--}}

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl sm:text-2xl font-bold text-gray-900">{{ __('messages.my_payments') }}</h1>
    </div>

    @if(! $rental)
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm p-6 text-center">
            <p class="text-slate-600">{{ __('messages.no_active_tenancy') }}</p>
            <p class="text-sm text-slate-400 mt-1">{{ __('messages.contact_property_manager') }}</p>
        </div>
    @else

        {{-- The one number the tenant came for. --}}
        <div class="rounded-xl p-5 sm:p-6 {{ $totalOutstanding > 0 ? 'bg-amber-50 border border-amber-200' : 'bg-emerald-50 border border-emerald-200' }}">
            <p class="text-sm font-medium {{ $totalOutstanding > 0 ? 'text-amber-800' : 'text-emerald-800' }}">
                {{ __('messages.total_outstanding') }}
            </p>
            <p class="text-3xl sm:text-4xl font-bold mt-1 {{ $totalOutstanding > 0 ? 'text-amber-900' : 'text-emerald-900' }}">
                {{ money($totalOutstanding) }}
            </p>
            @if($totalOutstanding <= 0)
                <p class="text-sm text-emerald-700 mt-1">{{ __('messages.nothing_outstanding') }}</p>
            @endif
            @if($rental->apartment)
                <p class="text-xs mt-3 {{ $totalOutstanding > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                    {{ $rental->apartment->floor->property->name ?? '' }}
                    @if($rental->apartment->apartment_number)
                        · {{ __('messages.room') }} {{ $rental->apartment->apartment_number }}
                    @endif
                </p>
            @endif
        </div>

        @foreach($obligations as $obligation)
            <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">

                <div class="flex items-center justify-between gap-3 px-4 sm:px-6 py-3 border-b border-slate-100 bg-slate-50">
                    <h2 class="font-semibold text-slate-800">{{ $obligation['label'] }}</h2>
                    <x-bill-status :bill="$obligation" />
                </div>

                {{-- RENT --}}
                <div class="px-4 sm:px-6 py-4 border-b border-slate-100">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-800">{{ __('messages.monthly_rent') }}</p>
                            @if($obligation['billing_period'] && $obligation['billing_period']->isProrated)
                                <p class="text-xs text-slate-500 mt-0.5">{{ $obligation['billing_period']->label() }}</p>
                            @endif
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ __('messages.due_date') }}: {{ $obligation['due_date']->format('M j, Y') }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-lg font-bold text-slate-900">{{ money($obligation['rent_amount']) }}</p>
                            @if($obligation['rent_paid'])
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                                    {{ __('messages.paid') }}
                                </span>
                            @elseif($obligation['is_upcoming'])
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700">
                                    {{ __('messages.upcoming') }}
                                </span>
                            @elseif($obligation['rent_status'] === 'overdue')
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                    {{ __('messages.overdue') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($obligation['rent_outstanding'] > 0 && ! $obligation['is_upcoming'])
                        <a href="{{ route('tenant.payments.show', ['side' => 'rent', 'year' => $obligation['year'], 'month' => $obligation['month']]) }}"
                           class="mt-3 w-full inline-flex items-center justify-center px-4 py-3 rounded-lg bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 active:bg-indigo-800">
                            {{ __('messages.pay_rent') }} · {{ money($obligation['rent_outstanding']) }}
                        </a>
                    @endif
                </div>

                {{-- OTHER CHARGES — each one named, so the tenant can see what
                     they are paying for rather than a lump sum. --}}
                <div class="px-4 sm:px-6 py-4">
                    <p class="font-medium text-slate-800">{{ __('messages.other_charges') }}</p>

                    @if($obligation['charges']->isEmpty())
                        <p class="text-sm text-slate-400 mt-1">{{ __('messages.no_charges_yet') }}</p>
                    @else
                        <ul class="mt-2 space-y-2">
                            @foreach($obligation['charges'] as $charge)
                                <li class="flex items-center justify-between gap-3 text-sm">
                                    <span class="text-slate-600">{{ __('messages.'.$charge->utility_type) }}</span>
                                    <span class="flex items-center gap-2 shrink-0">
                                        <span class="font-semibold text-slate-800">{{ money($charge->charge_amount) }}</span>
                                        @if($charge->paid_status)
                                            <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700">{{ __('messages.paid') }}</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">{{ __('messages.pending') }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if($obligation['unpaid_charge_total'] > 0 && ! $obligation['is_upcoming'])
                        <a href="{{ route('tenant.payments.show', ['side' => 'charges', 'year' => $obligation['year'], 'month' => $obligation['month']]) }}"
                           class="mt-3 w-full inline-flex items-center justify-center px-4 py-3 rounded-lg bg-indigo-600 text-white font-semibold text-sm hover:bg-indigo-700 active:bg-indigo-800">
                            {{ __('messages.pay_charges') }} · {{ money($obligation['unpaid_charge_total']) }}
                        </a>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- HISTORY --}}
        <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 sm:px-6 py-3 border-b border-slate-100 bg-slate-50">
                <h2 class="font-semibold text-slate-800">{{ __('messages.payment_history') }}</h2>
            </div>
            @if($history->isEmpty())
                <p class="px-4 sm:px-6 py-6 text-center text-sm text-slate-400">{{ __('messages.no_payments_yet') }}</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach($history as $payment)
                        <li class="flex items-center justify-between gap-3 px-4 sm:px-6 py-3">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800 text-sm">
                                    {{ __('messages.'.$payment->payment_type) }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ $payment->paid_at?->format('M j, Y') }}
                                    @if($payment->payment_method)
                                        · {{ __('messages.'.$payment->payment_method) }}
                                    @endif
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-semibold text-slate-900">{{ money($payment->amount + $payment->late_fee) }}</p>
                                <span class="text-xs text-emerald-600">{{ __('messages.paid') }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
@endsection
