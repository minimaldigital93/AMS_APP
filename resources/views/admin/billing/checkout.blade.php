@extends('layouts.admin')

@section('title', __('Pay subscription'))

@section('content')
<div class="mx-auto max-w-md text-center"
     x-data="khqrBillingCheckout({
        statusUrl: '{{ $statusUrl }}',
        redirectUrl: '{{ $redirectUrl }}',
        expiresAt: '{{ $payment->expires_at?->toIso8601String() }}',
     })" x-init="start()">

    <h1 class="text-2xl font-semibold text-slate-800 tracking-tight">{{ __('messages.payment_confirming_title') }}</h1>

    <p class="mt-3 text-sm text-gray-500" x-show="state === 'waiting'">
        {{ __('Once your payment is confirmed this page will update automatically.') }}
    </p>

    <div class="mt-5">
        <template x-if="state === 'waiting'">
            <div class="space-y-1">
                <span class="text-sm text-gray-500">{{ __('messages.payment_waiting') }}</span>
                <p x-show="countdown" class="text-xs text-gray-400">{{ __('messages.payment_expires_in') }} <span class="font-medium tabular-nums" x-text="countdown"></span></p>
            </div>
        </template>
        <template x-if="state === 'paid'">
            <span class="font-semibold text-green-600">{{ __('messages.payment_received_redirecting') }}</span>
        </template>
        <template x-if="state === 'failed'">
            <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-4 text-left">
                <p class="font-semibold text-amber-800">{{ __('messages.payment_session_ended') }}</p>
                <p class="mt-1 text-xs leading-relaxed text-amber-700">{{ __('messages.payment_session_ended_hint') }}</p>
                <a href="{{ route('admin.billing.index') }}"
                   class="mt-3 inline-block rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600 transition">
                    {{ __('messages.payment_try_again') }}
                </a>
            </div>
        </template>
    </div>

    <a href="{{ route('admin.billing.index') }}" class="mt-6 inline-block text-sm text-gray-500 underline">{{ __('Back to billing') }}</a>
</div>

<script>
    function khqrBillingCheckout({ statusUrl, redirectUrl, expiresAt }) {
        const OPEN = ['pending', 'qr_generated', 'waiting_payment'];
        return {
            state: 'waiting', // waiting | paid | failed
            timer: null,
            countdown: '',
            countdownTimer: null,
            start() { this.poll(); this.timer = setInterval(() => this.poll(), 3000); this.startCountdown(); },
            stop() { if (this.timer) clearInterval(this.timer); this.timer = null; this.stopCountdown(); },
            startCountdown() {
                const deadline = expiresAt ? Date.parse(expiresAt) : NaN;
                if (isNaN(deadline)) return;
                const tick = () => {
                    const secs = Math.max(0, Math.round((deadline - Date.now()) / 1000));
                    this.countdown = Math.floor(secs / 60) + ':' + String(secs % 60).padStart(2, '0');
                    if (secs <= 0) this.stopCountdown();
                };
                tick();
                this.countdownTimer = setInterval(tick, 1000);
            },
            stopCountdown() { if (this.countdownTimer) clearInterval(this.countdownTimer); this.countdownTimer = null; },
            async poll() {
                try {
                    const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data.paid) {
                        this.state = 'paid';
                        this.stop();
                        setTimeout(() => window.location = data.redirect || redirectUrl, 1500);
                        return;
                    }
                    if (data.status && !OPEN.includes(data.status)) {
                        this.state = 'failed';
                        this.stop();
                    }
                } catch (e) {}
            },
        };
    }
</script>
@endsection
