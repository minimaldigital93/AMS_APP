<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\KhqrPayment;

/**
 * Tenant rent collected by QR or bank transfer, confirmed by the landlord.
 *
 * "Manual" is a provider in the same sense the others are — it is what
 * khqr_payments.provider records — but there is no remote party: the money
 * moves from the tenant's bank to the landlord's, and neither this app nor the
 * platform's Bakong token is on that path. Nobody but the landlord can see it
 * arrive, which is exactly why they are the one who confirms it
 * (KhqrPaymentService::confirmManual).
 *
 * verify() is therefore FALSE, always, and it is not a stub: an automatic
 * "paid" here would book rent nobody has checked for.
 */
final class ManualGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'manual';
    }

    public function verify(KhqrPayment $payment): bool
    {
        return false;
    }
}
