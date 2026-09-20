<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\KhqrPayment;
use App\Services\Bakong\BakongTransactionService;

/**
 * The direct Bakong Open API driver.
 *
 * Resolved per row off khqr_payments.provider, which predates this migration:
 * rows minted at khqr.cc still say 'khqrpay' and answer through the tombstone
 * driver forever, while new subscription rows are written as 'bakong'.
 *
 * THERE IS NO validateWebhook() HERE, and that is the strongest form of the
 * security property this driver used to assert in code: the Bakong Open API
 * publishes no callback, so a POST claiming to settle a Bakong payment could
 * never be genuine — and since /khqr/callback was deleted with khqr.cc, there
 * is no longer an endpoint for one to arrive at.
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
}
