<?php

namespace App\Services\Payment;

use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Bakong\BakongProviderClient;
use App\Services\Bakong\BakongTransactionService;
use App\Services\RevenueExpense\KhqrPaymentService;

/**
 * Which provider takes a subscription payment, and how the checkout behaves
 * once it has.
 *
 * Both entry points — the public signup funnel (SubscriptionController) and the
 * in-app renewal (Admin\BillingController) — go through here so the decision is
 * made in ONE place. During a provider migration that matters more than usual:
 * two controllers each making their own choice is two things to switch back,
 * and the moment you need to switch back is the moment you least want to be
 * editing controllers.
 *
 * TWO RULES, and they are not the same rule:
 *
 *  1. WHICH PROVIDER MINTS A NEW PAYMENT is decided by configuration —
 *     BAKONG_API_ENABLED. That makes rollback one environment variable: turn it
 *     off and the next checkout is a KHQRPay checkout again.
 *
 *  2. WHICH PROVIDER ANSWERS FOR AN EXISTING PAYMENT is decided by the ROW, via
 *     khqr_payments.provider. A payment minted at khqr.cc must keep being polled
 *     at khqr.cc for the rest of its life, whatever the config says now — a
 *     customer who is mid-checkout when the switch is flipped must not have
 *     their QR silently start being asked about at the wrong gateway, where it
 *     does not exist and would read as unpaid.
 *
 * The difference between the two flows is bigger than which HTTP calls get
 * made. KHQRPay is a HOSTED CHECKOUT: the browser is handed to khqr.cc with
 * redirect()->away(), which is a one-way door — hence platformCheckoutFault()
 * and the two preflight probes that exist purely to avoid walking through it
 * when the gateway cannot take money. Bakong has no hosted page: the QR is
 * built locally and shown on our own page, so there is no door, nothing to
 * preflight, and nothing to spend on preflighting it.
 */
class SubscriptionCheckout
{
    public function __construct(
        private BakongTransactionService $bakong,
        private KhqrPaymentService $khqr,
    ) {}

    /** Will a NEW payment be minted through the direct Bakong integration? */
    public function usesBakong(): bool
    {
        return BakongProviderClient::featureEnabled();
    }

    /**
     * Can the gateway take a payment right now? Null when it can.
     *
     * KHQRPay only. The whole preflight exists because the customer is about to
     * be handed to someone else's domain, where this app can no longer say
     * anything — a profile that cannot transact answers the hosted checkout
     * with a raw JSON body and the customer is left reading it. Bakong's
     * checkout never leaves this app, so there is nothing to ask and, more to
     * the point, no reason to spend two metered requests asking it.
     */
    public function preflightFault(): ?string
    {
        return $this->usesBakong() ? null : $this->khqr->platformCheckoutFault();
    }

    /**
     * Mint the payment.
     *
     * @param  Plan|null  $plan  what the customer is BUYING — carried on the payment,
     *                           never written to the live subscription before the money lands
     */
    public function create(Subscription $subscription, float $amount, ?Plan $plan = null, ?string $cycle = null): KhqrPayment
    {
        return $this->usesBakong()
            ? $this->bakong->createSubscriptionQr($subscription, $amount, $plan, $cycle)
            : $this->khqr->createSubscriptionQr($subscription, $amount, $plan, $cycle);
    }

    /**
     * Where to send the browser after minting.
     *
     * Null means "render our own checkout page" — the Bakong flow, where the
     * payer never leaves. A URL means the KHQRPay hosted page.
     */
    public function handoffUrl(KhqrPayment $row, string $returnUrl): ?string
    {
        return $row->usesBakong() ? null : $this->khqr->subscriptionCheckoutUrl($row, $returnUrl);
    }

    /**
     * Advance a payment by asking whoever minted it.
     *
     * Routed on the ROW, not the config — see rule 2 above.
     *
     * @return array{payment: KhqrPayment, gateway_error: bool, gateway_answered: bool, quota_exhausted: ?string}
     */
    public function poll(KhqrPayment $row): array
    {
        if ($row->usesBakong()) {
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

        return [
            'payment' => $this->khqr->pollAndAdvance($row),
            'gateway_error' => $this->khqr->lastPollRefused(),
            // KHQRPay rows keep their previous behaviour exactly: every poll
            // counts as an answer, which is what the old client-side counter
            // assumed.
            'gateway_answered' => true,
            'quota_exhausted' => null,
        ];
    }

    /** The scannable QR, for a row that carries its own payload. Null for hosted checkouts. */
    public function qrImage(KhqrPayment $row, int $size = 280): ?string
    {
        return $row->usesBakong() ? $this->bakong->qrImage($row, $size) : null;
    }
}
