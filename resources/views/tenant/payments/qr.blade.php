@extends('layouts.tenant')

{{--
    The QR, never shown without saying what it is for.

    Confirmation here is the LANDLORD's: rent settles into their own bank, which
    neither this app nor NBC's token can see, so nothing auto-marks this paid.
    The page says so plainly rather than spinning on a promise it cannot keep —
    the status poll is local and contacts nobody.
--}}

@section('content')
<div class="max-w-md mx-auto space-y-5"
     x-data="{
        status: @js($payment->status),
        paid: @js($payment->status === 'paid'),
        auto: @js($auto),
        gatewayError: false,
        poll() {
            fetch(@js(route('tenant.payments.status', $payment->transaction_id)), { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(j => { this.status = j.status; this.paid = j.paid; this.auto = j.auto; this.gatewayError = j.gateway_error; })
                .catch(() => {});
        }
     }"
     x-init="setInterval(() => poll(), 10000)">

    <a href="{{ route('tenant.payments.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700">
        &larr; {{ __('messages.back') }}
    </a>

    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden text-center">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50">
            <h1 class="text-lg font-bold text-slate-900">
                {{ $side === 'rent' ? __('messages.monthly_rent') : __('messages.other_charges') }}
            </h1>
            <p class="text-sm text-slate-500">{{ $monthLabel }}</p>
            @if($rental?->apartment)
                <p class="text-xs text-slate-400 mt-0.5">
                    {{ __('messages.room') }} {{ $rental->apartment->apartment_number }}
                </p>
            @endif
        </div>

        <div class="px-5 py-5">
            <p class="text-3xl font-bold text-slate-900">{{ money($payment->amount) }}</p>

            <template x-if="! paid">
                <div class="mt-5">
                    @if($qrImage)
                        <img src="{{ $qrImage }}" alt="KHQR" class="mx-auto w-56 h-56">
                    @else
                        <p class="text-sm text-slate-400 py-10">{{ __('messages.qr_unavailable') }}</p>
                    @endif
                    <p class="mt-3 text-sm text-slate-500">{{ __('messages.khqr_scan_hint') }}</p>
                </div>
            </template>

            <template x-if="paid">
                <div class="mt-5 py-8">
                    <div class="mx-auto w-14 h-14 rounded-full bg-emerald-100 flex items-center justify-center">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <p class="mt-3 font-semibold text-emerald-700">{{ __('messages.payment_confirmed') }}</p>
                </div>
            </template>
        </div>

        {{-- The honest state. A tenant-scanned QR is money the landlord has to
             see land in their own bank before anything here says "paid". --}}
        <div class="px-5 py-4 border-t border-slate-100 bg-slate-50">
            {{-- Two honest states, never one pretending to be the other. With
                 the landlord's own token this page really is watching for the
                 money; without it, only the landlord can confirm and saying
                 otherwise would be a promise the app cannot keep. --}}
            <template x-if="! paid">
                <div>
                    <p class="text-sm font-medium text-amber-700"
                       x-text="auto ? @js(__('messages.waiting_for_payment')) : @js(__('messages.awaiting_confirmation'))"></p>
                    <p class="text-xs text-slate-500 mt-1"
                       x-text="auto ? @js(__('messages.waiting_for_payment_hint')) : @js(__('messages.awaiting_confirmation_hint'))"></p>
                    <p x-show="gatewayError" x-cloak class="text-xs text-amber-600 mt-1">
                        {{ __('messages.payment_check_unavailable') }}
                    </p>
                </div>
            </template>
            <template x-if="paid">
                <p class="text-sm text-emerald-700">{{ __('messages.payment_confirmed_hint') }}</p>
            </template>
        </div>
    </div>
</div>
@endsection
