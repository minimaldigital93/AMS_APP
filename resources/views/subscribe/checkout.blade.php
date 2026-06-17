<x-guest-layout>
    <h2 class="login-title">{{ __('Confirming your payment') }}</h2>

    <div x-data="khqrCheckout('{{ route('subscribe.checkout.status', $payment->public_token) }}')"
         x-init="start()"
         class="text-center text-white">

        <p class="text-sm text-white/80">
            {{ $payment->subscription->plan->name }} {{ __('plan') }} —
            <span class="font-semibold">${{ number_format($payment->amount, 2) }}/{{ __('mo') }}</span>
        </p>

        <div class="mt-6">
            <template x-if="!paid">
                <div class="flex items-center justify-center gap-2 text-sm text-white/80">
                    <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/>
                    </svg>
                    {{ __('Waiting for confirmation…') }}
                </div>
            </template>
            <template x-if="paid">
                <p class="text-green-300 font-semibold">{{ __('Payment received! Redirecting…') }}</p>
            </template>
        </div>

        <p class="mt-6 text-xs text-white/60">{{ __('Once your payment is confirmed you’ll be redirected to sign in. You can keep this page open.') }}</p>
    </div>

    <script>
        function khqrCheckout(statusUrl) {
            return {
                paid: false,
                timer: null,
                start() {
                    this.poll();
                    this.timer = setInterval(() => this.poll(), 3000);
                },
                async poll() {
                    try {
                        const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        if (data.paid) {
                            this.paid = true;
                            clearInterval(this.timer);
                            setTimeout(() => window.location = data.redirect || '{{ route('login') }}', 1500);
                        }
                    } catch (e) { /* keep polling */ }
                },
            };
        }
    </script>
</x-guest-layout>
