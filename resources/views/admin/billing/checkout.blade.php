@extends('layouts.admin')

@section('title', __('Pay subscription'))

@section('content')
<div class="mx-auto max-w-md text-center"
     x-data="khqrBillingCheckout('{{ $statusUrl }}', '{{ $redirectUrl }}')" x-init="start()">

    <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('Confirming your payment') }}</h1>

    <p class="mt-3 text-sm text-gray-500">{{ __('Once your payment is confirmed this page will update automatically.') }}</p>

    <div class="mt-5">
        <template x-if="!paid">
            <span class="text-sm text-gray-500">{{ __('Waiting for confirmation…') }}</span>
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
