@props([
    // Inline SVG data URI built from the payment row's own stored payload.
    // Null for a hosted-checkout (KHQRPay) row, where the payer paid elsewhere
    // and there is nothing for this component to show.
    'image' => null,
    'amount' => null,
    'currency' => 'USD',
    // 'dark' on the guest signup layout (white text on the brand background),
    // 'light' inside the admin panel.
    'tone' => 'light',
])

@if ($image)
    @php
        $dark = $tone === 'dark';
    @endphp

    {{--
        THE WHOLE CHECKOUT NOW HAPPENS HERE.

        Under KHQRPay the customer was redirected to khqr.cc to pay, and this
        page only ever showed a spinner waiting for the webhook. The Bakong Open
        API has no hosted checkout, so the QR is built locally and shown on our
        own page — which removes the one-way door that the two preflight probes
        existed to guard, and means a failure is something this app can still
        explain instead of a JSON body on someone else's domain.

        The image is a data: URI, so the payer's browser makes NO request for the
        thing it is about to pay. That matters beyond privacy: the old manual
        channel handed the payload to api.qrserver.com as a URL parameter, so the
        QR simply failed to appear whenever that service was unreachable.
    --}}
    <div class="mt-6 flex flex-col items-center">
        <div class="rounded-2xl bg-white p-4 shadow-sm {{ $dark ? 'ring-1 ring-white/20' : 'border border-slate-200' }}">
            <img src="{{ $image }}"
                 alt="{{ __('messages.bakong_scan_title') }}"
                 width="240" height="240"
                 class="h-60 w-60 max-w-full" />
        </div>

        @if ($amount !== null)
            <p class="mt-3 text-lg font-semibold tabular-nums {{ $dark ? 'text-white' : 'text-slate-800' }}">
                {{ $currency === 'KHR' ? '៛' : '$' }}{{ $currency === 'KHR' ? number_format((float) $amount) : number_format((float) $amount, 2) }}
            </p>
        @endif

        <p class="mt-1 max-w-xs text-center text-xs leading-relaxed {{ $dark ? 'text-white/70' : 'text-slate-500' }}">
            {{ __('messages.bakong_scan_hint') }}
        </p>

        {{--
            "I've paid — check now".

            With no webhook, polling is the ONLY way this page ever learns the
            money arrived, and it is paced for a metered token rather than for
            the fastest confirmation. This button spends a check at the one
            moment it is most likely to succeed: when the payer says they have
            actually paid. If the server-side cooldown absorbs it, nothing is
            spent and the spinner simply carries on — which is the honest
            outcome, not a failure.
        --}}
        <button type="button"
                x-on:click="poll()"
                x-show="state === 'waiting'"
                class="mt-4 rounded-lg px-4 py-2 text-sm font-semibold transition
                       {{ $dark
                            ? 'bg-white/15 text-white hover:bg-white/25'
                            : 'bg-slate-800 text-white hover:bg-slate-700' }}">
            {{ __('messages.bakong_check_now') }}
        </button>
    </div>
@endif
