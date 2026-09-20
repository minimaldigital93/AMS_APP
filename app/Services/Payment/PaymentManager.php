<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\KhqrPayment;
use App\Services\Payment\Gateways\BakongGateway;
use App\Services\Payment\Gateways\ManualGateway;
use App\Services\Payment\Gateways\RetiredKhqrPayGateway;

/**
 * Registry that resolves a PaymentGateway driver by provider key. This is the
 * single place a new provider is wired in — add it to $drivers and implement the
 * contract; nothing else in the payment flow needs to know which provider a row
 * used.
 *
 * Three keys, and only one of them can still mint anything:
 *
 *   bakong  — subscriptions, via the direct NBC Open API.
 *   manual  — tenant rent: a locally built KHQR the landlord confirms by hand.
 *   khqrpay — RETIRED. Kept so rows minted at khqr.cc before 2026-09 still
 *             resolve; see RetiredKhqrPayGateway. Never written to a new row.
 *
 * The retired key is why this registry is not simply deleted along with the
 * provider: khqr_payments.provider is history as much as configuration, and a
 * reporting page that reads a settled payment from last quarter must not throw
 * because the gateway that took it no longer exists.
 */
class PaymentManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    protected array $drivers = [
        'bakong' => BakongGateway::class,
        'manual' => ManualGateway::class,
        'khqrpay' => RetiredKhqrPayGateway::class,
    ];

    public function driver(string $provider): PaymentGateway
    {
        $class = $this->drivers[$provider]
            ?? throw new \InvalidArgumentException("No payment gateway registered for provider [{$provider}].");

        return app($class);
    }

    /** Resolve the driver that minted a given charge. */
    public function for(KhqrPayment $payment): PaymentGateway
    {
        return $this->driver($payment->provider ?: 'khqrpay');
    }

    /** Register/override a driver at runtime (e.g. from a package). */
    public function extend(string $provider, string $gatewayClass): void
    {
        $this->drivers[$provider] = $gatewayClass;
    }
}
