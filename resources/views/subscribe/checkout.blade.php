<x-guest-layout>
    <h2 class="login-title">{{ __('messages.payment_confirming_title') }}</h2>

    <div x-data="khqrCheckout({
            statusUrl: '{{ route('subscribe.checkout.status', $payment->public_token) }}',
            loginUrl: '{{ route('login') }}',
            expiresAt: '{{ $payment->expires_at?->toIso8601String() }}',
         })"
         x-init="start()"
         class="text-center text-white">

        <p class="text-sm text-white/80">
            {{ $payment->subscription->plan->name }} {{ __('plan') }} —
            <span class="font-semibold">${{ number_format($payment->amount, 2) }}/{{ __('mo') }}</span>
        </p>

        <div class="mt-6">
            <!-- Waiting -->
            <template x-if="state === 'waiting'">
                <div class="flex flex-col items-center gap-1">
                    <div class="flex items-center justify-center gap-2 text-sm text-white/80">
                        <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/>
                        </svg>
                        {{ __('messages.payment_waiting') }}
                    </div>
                    <p x-show="countdown" class="text-xs text-white/50">{{ __('messages.payment_expires_in') }} <span class="font-medium tabular-nums" x-text="countdown"></span></p>
                </div>
            </template>

            <!-- Paid -->
            <template x-if="state === 'paid'">
                <p class="text-green-300 font-semibold">{{ __('messages.payment_received_redirecting') }}</p>
            </template>

            <!-- Expired / failed — friendly fallback, no infinite spinner -->
            <template x-if="state === 'failed'">
                <div class="rounded-xl border border-amber-400/40 bg-amber-500/15 px-4 py-4 text-left">
                    <p class="font-semibold text-amber-100">{{ __('messages.payment_session_ended') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-amber-100/80">{{ __('messages.payment_session_ended_hint') }}</p>
                    <a href="{{ route('subscribe.create', ['plan' => $payment->subscription->plan->slug]) }}"
                       class="mt-3 inline-block rounded-lg bg-amber-400/90 px-4 py-2 text-sm font-semibold text-amber-950 hover:bg-amber-300 transition">
                        {{ __('messages.payment_try_again') }}
                    </a>
                </div>
            </template>
        </div>

        <p class="mt-6 text-xs text-white/60" x-show="state === 'waiting'">
            {{ __('Once your payment is confirmed you’ll be redirected to sign in. You can keep this page open.') }}
        </p>
    </div>

    <script>
        function khqrCheckout({ statusUrl, loginUrl, expiresAt }) {
            // Open states the gateway may still advance to "paid".
            const OPEN = ['pending', 'qr_generated', 'waiting_payment'];
            return {
                state: 'waiting', // waiting | paid | failed
                timer: null,
                countdown: '',
                countdownTimer: null,
                start() {
                    this.poll();
                    this.timer = setInterval(() => this.poll(), 3000);
                    this.startCountdown();
                },
                stop() {
                    if (this.timer) clearInterval(this.timer);
                    this.timer = null;
                    this.stopCountdown();
                },
                // Informational countdown only — the poll decides the final state
                // once the server lazily expires the row.
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
                stopCountdown() {
                    if (this.countdownTimer) clearInterval(this.countdownTimer);
                    this.countdownTimer = null;
                },
                async poll() {
                    try {
                        const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) return; // transient server hiccup — keep polling
                        const data = await res.json();

                        if (data.paid) {
                            this.state = 'paid';
                            this.stop();
                            setTimeout(() => window.location = data.redirect || loginUrl, 1500);
                            return;
                        }

                        // Anything that is neither paid nor still open is terminal
                        // (expired / failed / cancelled) — stop and offer a retry
                        // instead of spinning forever.
                        if (data.status && !OPEN.includes(data.status)) {
                            this.state = 'failed';
                            this.stop();
                        }
                    } catch (e) { /* network blip — keep polling */ }
                },
            };
        }
    </script>
</x-guest-layout>
