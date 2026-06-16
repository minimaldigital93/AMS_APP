<?php

namespace App\Contracts;

use App\Models\KhqrPayment;

/**
 * A payment provider's protocol — the provider-specific surface that everything
 * else in the payment flow depends on. The orchestration (creating transactions,
 * finalizing, activating subscriptions, expiring) is provider-agnostic and lives
 * in the services; only these three operations differ per provider.
 *
 * Add a provider by implementing this interface and registering it in
 * App\Services\Payment\PaymentManager. KHQRPay is the first driver (KhqrPayGateway).
 */
interface PaymentGateway
{
    /** The provider key stored on khqr_payments.provider (e.g. 'khqrpay'). */
    public function provider(): string;

    /** Ask the provider whether this charge has settled. */
    public function verify(KhqrPayment $payment): bool;

    /**
     * Authenticate an inbound webhook payload against a specific charge row
     * (signature + amount/currency/status), using that row's settlement credentials.
     */
    public function validateWebhook(KhqrPayment $payment, array $payload): bool;
}
