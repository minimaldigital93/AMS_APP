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

    <x-bakong-qr :image="$qrImage ?? null" :amount="$payment->amount" :currency="$payment->currency" tone="light" />

    <div class="mt-5">
        <template x-if="state === 'waiting'">
            <div class="space-y-1">
                <span class="text-sm text-gray-500">{{ __('messages.payment_waiting') }}</span>
                <p x-show="countdown" class="text-xs text-gray-400">{{ __('messages.payment_expires_in') }} <span class="font-medium tabular-nums" x-text="countdown"></span></p>
                {{-- The poll kept failing. Say so rather than spinning silently —
                     the payment may still land, so this is a warning beside the
                     spinner, not a terminal state. --}}
                <p x-show="stalled" x-cloak class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800">
                    {{ __('messages.payment_gateway_unreachable') }}
                </p>
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

    {{-- Hosted checkout only. The spinner cannot tell "not paid yet" from
         "khqr.cc showed you a JSON error and you came back" — the row just sits
         in qr_generated either way. The direct Bakong flow has no hosted page
         to fail, so the button has nothing left to diagnose. --}}
    @unless ($payment->usesBakong())
        <button type="button" x-on:click="$dispatch('khqr-diagnostics')"
                class="mt-6 block w-full text-sm text-gray-500 underline hover:text-gray-700">
            {{ __('messages.khqr_diag_checkout_help') }}
        </button>
    @endunless

    <a href="{{ route('admin.billing.index') }}" class="mt-3 inline-block text-sm text-gray-500 underline">{{ __('Back to billing') }}</a>
</div>

<x-khqr-diagnostics
    :endpoint="route('admin.billing.diagnostics')"
    :retry-url="route('admin.billing.index')"
    :settings-url="auth()->user()?->hasRole('superadmin') ? route('superadmin.settings.payment') : null" />

<script>
    function khqrBillingCheckout({ statusUrl, redirectUrl, expiresAt }) {
        const OPEN = ['pending', 'qr_generated', 'waiting_payment'];
        // Two consecutive bad polls before we warn: a single blip self-heals.
        const STALL_AFTER = 2;
        // Paced for a metered Bakong token — see subscribe/checkout.blade.php.
        const POLL_MS = 10000;
        return {
            state: 'waiting', // waiting | paid | failed
            stalled: false,
            misses: 0,
            // Button feedback. Without these the "check now" button gave no
            // sign it had done anything — pressed or not the page looked
            // identical, so a working check was indistinguishable from a dead
            // button.
            checking: false,
            lastCheckedAt: null,
            lastCheckedLabel: '',
            quotaResetsAt: null,
            timer: null,
            countdown: '',
            countdownTimer: null,
            start() { this.poll(); this.timer = setInterval(() => this.poll(), POLL_MS); this.startCountdown(); },
            stop() { if (this.timer) clearInterval(this.timer); this.timer = null; this.stopCountdown(); },
            startCountdown() {
                const deadline = expiresAt ? Date.parse(expiresAt) : NaN;
                if (isNaN(deadline)) return;
                const tick = () => {
                    this.refreshCheckedLabel();
                    const secs = Math.max(0, Math.round((deadline - Date.now()) / 1000));
                    this.countdown = Math.floor(secs / 60) + ':' + String(secs % 60).padStart(2, '0');
                    // Also the stop signal — the server deliberately leaves an
                    // elapsed QR open now so a late webhook can still finalize
                    // it, so the page cannot wait to be told it expired.
                    if (secs <= 0) {
                        this.stopCountdown();
                        if (this.state === 'waiting') { this.state = 'failed'; this.stop(); }
                    }
                };
                tick();
                this.countdownTimer = setInterval(tick, 1000);
            },
            stopCountdown() { if (this.countdownTimer) clearInterval(this.countdownTimer); this.countdownTimer = null; },
            // "Checked 12s ago" — the one thing that makes a check which found
            // nothing distinguishable from a check that never ran.
            refreshCheckedLabel() {
                if (!this.lastCheckedAt) { this.lastCheckedLabel = ''; return; }
                const secs = Math.round((Date.now() - this.lastCheckedAt) / 1000);
                this.lastCheckedLabel = secs < 5
                    ? @js(__('messages.bakong_checked_just_now'))
                    : @js(__('messages.bakong_checked_ago')).replace(':seconds', secs);
            },
            // A failed poll never stops the polling — the payment can still land.
            // It only raises a visible warning once it keeps failing.
            miss() { if (++this.misses >= STALL_AFTER) this.stalled = true; },
            async poll() {
                if (this.checking) return;   // a click landing on top of a tick
                this.checking = true;
                try {
                    const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return this.miss();
                    const data = await res.json();

                    this.quotaResetsAt = data.quota_exhausted || null;

                    if (data.gateway_error) return this.miss();

                    // ONLY a poll that actually reached the gateway may clear
                    // the miss counter. A poll the server-side cooldown
                    // absorbed has learned nothing, and treating it as proof of
                    // health is why the stall warning could never fire: the
                    // real sequence is error, cooldown, cooldown, cooldown,
                    // error — so a counter reset by cooldowns never reaches two.
                    if (!data.gateway_answered) return;

                    this.misses = 0;
                    this.stalled = false;
                    this.lastCheckedAt = Date.now();
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
                } catch (e) {
                    this.miss();
                } finally {
                    this.checking = false;
                }
            },
        };
    }
</script>
@endsection
