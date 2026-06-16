<x-guest-layout>
    <h2 class="login-title">{{ __('Scan to pay') }}</h2>

    <div x-data="khqrCheckout('{{ route('subscribe.checkout.status', $payment->public_token) }}')"
         x-init="start()"
         class="text-center text-white">

        <p class="text-sm text-white/80">
            {{ $payment->subscription->plan->name }} {{ __('plan') }} —
            <span class="font-semibold">${{ number_format($payment->amount, 2) }}/{{ __('mo') }}</span>
        </p>

        <div class="mx-auto mt-4 w-[260px] rounded-2xl bg-white p-3 shadow-lg">
            @if($payment->qr_url)
                <img src="{{ $payment->qr_url }}" alt="KHQR" class="h-[240px] w-[240px] object-contain">
            @else
                <div class="flex h-[240px] items-center justify-center text-gray-400">{{ __('QR unavailable') }}</div>
            @endif
        </div>

        <div class="mt-5">
            <template x-if="!paid">
                <div class="flex items-center justify-center gap-2 text-sm text-white/80">
                    <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/>
                    </svg>
                    {{ __('Waiting for payment…') }}
                </div>
            </template>
            <template x-if="paid">
                <p class="text-green-300 font-semibold">{{ __('Payment received! Redirecting…') }}</p>
            </template>
        </div>

        <p class="mt-6 text-xs text-white/60">{{ __('Open your banking app and scan the KHQR code above.') }}</p>
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
