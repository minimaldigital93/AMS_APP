<?php

namespace App\Services\Payment;

use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Bakong\BakongProviderClient;
use App\Services\Bakong\BakongTransactionService;

/**
 * Which provider takes a subscription payment, and how the checkout behaves
 * once it has.
 *
 * Both entry points — the public signup funnel (SubscriptionController) and the
 * in-app renewal (Admin\BillingController) — go through here so the decision is
 * made in ONE place.
 *
 * SINCE 2026-09 THERE IS ONLY ONE PROVIDER. khqr.cc was retired and its client
 * deleted, so every new subscription payment is minted through the direct NBC
 * Bakong Open API. What survives from the two-provider period is the shape of
 * the seam, and it is worth keeping:
 *
 *  - poll() is still routed on the ROW (khqr_payments.provider), not on
 *    configuration. A payment minted at khqr.cc keeps answering through its own
 *    driver for the rest of its life — which is what stops a historical row
 *    from being asked about at a gateway where it does not exist, and would
 *    read as unpaid.
 *  - handoffUrl() still exists and still returns null. Bakong has no hosted
 *    page: the QR is built locally and shown on this app's own page, so there
 *    is no redirect()->away() one-way door — and therefore nothing to
 *    preflight, which is why preflightFault() is null rather than two metered
 *    probes. If a hosted provider is ever added back, this is where it plugs
 *    in.
 */
class SubscriptionCheckout
{
    public function __construct(private BakongTransactionService $bakong) {}

    /**
     * Can a NEW subscription payment be minted at all?
     *
     * One provider means one answer: this is the Bakong master switch. With it
     * off, create() refuses rather than falling back — there is nothing left to
     * fall back TO, and minting a session nobody can confirm is worse than
     * refusing, because it shows the customer a QR nobody is watching.
     */
    public function available(): bool
    {
        return BakongProviderClient::featureEnabled();
    }

    /**
     * Can the gateway take a payment right now? Null when it can.
     *
     * Always null. The preflight existed because the customer was about to be
     * handed to khqr.cc's domain, where this app could no longer say anything —
     * a profile that could not transact answered with a raw JSON body and the
     * customer was left reading it. Bakong's checkout never leaves this app, so
     * there is nothing to ask and, more to the point, no reason to spend two
     * metered requests asking it.
     */
    public function preflightFault(): ?string
    {
        return null;
    }

    /**
     * Mint the payment.
     *
     * @param  Plan|null  $plan  what the customer is BUYING — carried on the payment,
     *                           never written to the live subscription before the money lands
     */
    public function create(Subscription $subscription, float $amount, ?Plan $plan = null, ?string $cycle = null): KhqrPayment
    {
        return $this->bakong->createSubscriptionQr($subscription, $amount, $plan, $cycle);
    }

    /**
     * Where to send the browser after minting. Always null — render our own
     * checkout page, because the payer never leaves it.
     */
    public function handoffUrl(KhqrPayment $row, string $returnUrl): ?string
    {
        return null;
    }

    /**
     * Advance a payment by asking whoever minted it.
     *
     * Routed on the ROW. A legacy khqr.cc row has no gateway left to ask, so it
     * reports its stored status unchanged and says the gateway did not answer —
     * never that the payer has not paid. Settling one of those is a human job
     * (SuperAdmin → Accounts → change plan, the sanctioned out-of-band path).
     *
     * @return array{payment: KhqrPayment, gateway_error: bool, gateway_answered: bool, quota_exhausted: ?string}
     */
    public function poll(KhqrPayment $row): array
    {
        if (! $row->usesBakong()) {
            return [
                'payment' => $row,
                'gateway_error' => true,
                'gateway_answered' => false,
                'quota_exhausted' => null,
            ];
        }

        $payment = $this->bakong->pollAndAdvance($row);
        $exhausted = $this->bakong->upstreamExhausted($row->settlement_target ?: 'platform');

        return [
            'payment' => $payment,
            // A refusal reaches the page as gateway_error so the spinner can
            // say something. With no webhook behind Bakong, a gateway that
            // refuses every request is otherwise indistinguishable from a
            // payer who has not paid yet.
            'gateway_error' => $this->bakong->lastPollRefused(),
            // Whether we actually ASKED. The page must not treat a
            // cooldown-absorbed poll as evidence the gateway is healthy.
            'gateway_answered' => $this->bakong->lastPollAnswered(),
            // When the allowance resets, so the page can say something
            // truthful instead of "try again" about a thing that cannot
            // work until tomorrow.
            'quota_exhausted' => $exhausted === null ? null : $exhausted['until']->toIso8601String(),
        ];
    }

    /** The scannable QR, for a row that carries its own payload. Null otherwise. */
    public function qrImage(KhqrPayment $row, int $size = 280): ?string
    {
        return $row->usesBakong() ? $this->bakong->qrImage($row, $size) : null;
    }
}
