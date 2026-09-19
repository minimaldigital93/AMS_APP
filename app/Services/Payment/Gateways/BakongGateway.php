<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\KhqrPayment;
use App\Services\Bakong\BakongTransactionService;
use Illuminate\Support\Facades\Log;

/**
 * The direct Bakong Open API driver.
 *
 * Registered in PaymentManager alongside KhqrPayGateway, resolved per row off
 * khqr_payments.provider. That column predates this migration and already
 * defaults to 'khqrpay', so every existing row keeps answering through the old
 * driver forever while new rows are written as 'bakong'. Nothing in the payment
 * flow has to know which one a given payment used.
 */
final class BakongGateway implements PaymentGateway
{
    public function __construct(private BakongTransactionService $bakong) {}

    public function provider(): string
    {
        return 'bakong';
    }

    public function verify(KhqrPayment $payment): bool
    {
        return $this->bakong->verify($payment);
    }

    /**
     * THE BAKONG OPEN API SENDS NO WEBHOOKS. Always false, and that is a
     * security property rather than a gap in this driver.
     *
     * All eight documented endpoints are outbound request/response; NBC
     * publishes no callback, no push and no signing scheme for one. So there is
     * no payload this method could legitimately authenticate — and anything
     * arriving at /khqr/callback claiming to settle a Bakong row is, by
     * definition, not from Bakong.
     *
     * Returning false here means such a delivery is recorded by
     * WebhookIngestService, rejected with the same 403 as a bad signature, and
     * never finalizes a payment. That matters because the callback endpoint is
     * public and CSRF-exempt: without this, a forged POST naming a real
     * transaction id would be the cheapest possible way to activate a
     * subscription for free.
     *
     * The endpoint itself is deliberately NOT deleted — it still belongs to
     * KHQRPay, whose rows continue to settle through it.
     */
    public function validateWebhook(KhqrPayment $payment, array $payload): bool
    {
        Log::warning('Webhook received for a Bakong payment — rejected', [
            'transaction' => $payment->transaction_id,
            // No payload contents: it is unauthenticated attacker-controlled
            // input, and logging it verbatim is how a log becomes an injection
            // surface.
            'note' => 'The Bakong Open API sends no webhooks; this delivery cannot be genuine.',
        ]);

        return false;
    }
}
