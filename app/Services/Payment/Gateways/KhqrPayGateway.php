<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\PaymentGateway;
use App\Models\KhqrPayment;
use App\Services\RevenueExpense\KhqrPaymentService;

/**
 * KHQRPay (khqr.cc) driver — the first PaymentGateway implementation. The KHQR
 * protocol (SHA1 request signing, the qr-api-khqrcc / check-trans endpoints,
 * SHA256 webhook verification) currently lives in KhqrPaymentService; this is the
 * thin adapter that exposes it behind the provider-agnostic contract so callers
 * resolve it via PaymentManager rather than depending on KHQR directly.
 */
final class KhqrPayGateway implements PaymentGateway
{
    public function __construct(private KhqrPaymentService $khqr) {}

    public function provider(): string
    {
        return 'khqrpay';
    }

    public function verify(KhqrPayment $payment): bool
    {
        return $this->khqr->verify($payment);
    }

    public function validateWebhook(KhqrPayment $payment, array $payload): bool
    {
        return $this->khqr->isValidCallbackFor($payment, $payload);
    }
}
