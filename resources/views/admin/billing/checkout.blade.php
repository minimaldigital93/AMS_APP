@extends('layouts.admin')

@section('title', __('Pay subscription'))

@section('content')
<div class="mx-auto max-w-md text-center"
     x-data="khqrBillingCheckout('{{ $statusUrl }}', '{{ $redirectUrl }}')" x-init="start()">

    <h1 class="text-xl font-bold text-gray-900">{{ __('Scan to pay') }}</h1>
    <p class="mt-1 text-sm text-gray-500">
        {{ $payment->subscription->plan->name }} {{ __('plan') }} —
        <span class="font-semibold">${{ number_format($payment->amount, 2) }}/{{ __('mo') }}</span>
    </p>

    <div class="mx-auto mt-5 w-[260px] rounded-2xl border border-gray-200 bg-white p-3 shadow-sm">
        @if ($payment->qr_url)
            <img src="{{ $payment->qr_url }}" alt="KHQR" class="h-[240px] w-[240px] object-contain">
        @else
            <div class="flex h-[240px] items-center justify-center text-gray-400">{{ __('QR unavailable') }}</div>
        @endif
    </div>

    <div class="mt-5">
        <template x-if="!paid">
            <span class="text-sm text-gray-500">{{ __('Waiting for payment…') }}</span>
        </template>
        <template x-if="paid">
            <span class="font-semibold text-green-600">{{ __('Payment received! Redirecting…') }}</span>
        </template>
    </div>

    <a href="{{ route('admin.billing.index') }}" class="mt-6 inline-block text-sm text-gray-500 underline">{{ __('Back to billing') }}</a>
</div>

<script>
    function khqrBillingCheckout(statusUrl, redirectUrl) {
        return {
            paid: false, timer: null,
            start() { this.poll(); this.timer = setInterval(() => this.poll(), 3000); },
            async poll() {
                try {
                    const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (data.paid) {
                        this.paid = true;
                        clearInterval(this.timer);
                        setTimeout(() => window.location = data.redirect || redirectUrl, 1500);
                    }
                } catch (e) {}
            },
        };
    }
</script>
@endsection
