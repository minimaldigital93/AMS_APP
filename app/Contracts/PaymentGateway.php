<?php

namespace App\Contracts;

use App\Models\KhqrPayment;

/**
 * A payment provider's protocol — the provider-specific surface that everything
 * else in the payment flow depends on. The orchestration (creating
 * transactions, finalizing, activating subscriptions, expiring) is
 * provider-agnostic and lives in the services; only these operations differ.
 *
 * Add a provider by implementing this interface and registering it in
 * App\Services\Payment\PaymentManager.
 *
 * THERE IS NO validateWebhook() ANY MORE, and its absence is deliberate. It
 * existed for khqr.cc, which was retired in 2026-09 along with the public
 * /khqr/callback endpoint it authenticated. The Bakong Open API publishes no
 * callback of any kind — all eight documented endpoints are outbound
 * request/response — so this installation accepts no payment webhooks at all.
 * A provider that does push confirmations should bring its own endpoint and its
 * own signature check; re-adding a shared one here would recreate a public,
 * CSRF-exempt route that nothing currently needs.
 */
interface PaymentGateway
{
    /** The provider key stored on khqr_payments.provider (e.g. 'bakong'). */
    public function provider(): string;

    /** Ask the provider whether this charge has settled. */
    public function verify(KhqrPayment $payment): bool;
}
