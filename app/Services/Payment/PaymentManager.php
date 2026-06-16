<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\KhqrPayment;
use App\Services\Payment\Gateways\KhqrPayGateway;

/**
 * Registry that resolves a PaymentGateway driver by provider key. This is the
 * single place a new provider is wired in — add it to $drivers and implement the
 * contract; nothing else in the payment flow needs to know which provider a row
 * used.
 */
class PaymentManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    protected array $drivers = [
        'khqrpay' => KhqrPayGateway::class,
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
