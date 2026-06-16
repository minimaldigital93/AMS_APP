<?php

use App\Contracts\PaymentGateway;
use App\Models\KhqrPayment;
use App\Services\Payment\Gateways\KhqrPayGateway;
use App\Services\Payment\PaymentManager;

it('resolves the khqrpay driver as a PaymentGateway', function () {
    $gateway = app(PaymentManager::class)->driver('khqrpay');

    expect($gateway)->toBeInstanceOf(PaymentGateway::class);
    expect($gateway)->toBeInstanceOf(KhqrPayGateway::class);
    expect($gateway->provider())->toBe('khqrpay');
});

it('throws for an unregistered provider', function () {
    app(PaymentManager::class)->driver('stripe');
})->throws(InvalidArgumentException::class);

it('resolves a driver for a charge by its provider column', function () {
    $row = KhqrPayment::create([
        'transaction_id' => 'TX-PROV-1',
        'amount' => 10, 'currency' => 'USD', 'status' => 'qr_generated',
        'channel' => 'api', 'checkout_payload' => [],
    ]);

    // Defaults to khqrpay (DB default) and resolves the matching driver.
    expect(app(PaymentManager::class)->for($row)->provider())->toBe('khqrpay');
});

it('can register an additional provider at runtime', function () {
    $manager = app(PaymentManager::class);
    $manager->extend('mock', KhqrPayGateway::class);

    expect($manager->driver('mock'))->toBeInstanceOf(PaymentGateway::class);
});
