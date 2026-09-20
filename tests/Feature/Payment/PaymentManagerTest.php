<?php

use App\Contracts\PaymentGateway;
use App\Models\KhqrPayment;
use App\Services\Payment\Gateways\BakongGateway;
use App\Services\Payment\Gateways\ManualGateway;
use App\Services\Payment\Gateways\RetiredKhqrPayGateway;
use App\Services\Payment\PaymentManager;

it('resolves the bakong driver as a PaymentGateway', function () {
    $gateway = app(PaymentManager::class)->driver('bakong');

    expect($gateway)->toBeInstanceOf(PaymentGateway::class)
        ->and($gateway)->toBeInstanceOf(BakongGateway::class)
        ->and($gateway->provider())->toBe('bakong');
});

it('resolves the manual driver for tenant rent', function () {
    $gateway = app(PaymentManager::class)->driver('manual');

    expect($gateway)->toBeInstanceOf(ManualGateway::class)
        ->and($gateway->provider())->toBe('manual');
});

it('throws for an unregistered provider', function () {
    app(PaymentManager::class)->driver('stripe');
})->throws(InvalidArgumentException::class);

it('can register an additional provider at runtime', function () {
    $manager = app(PaymentManager::class);
    $manager->extend('mock', ManualGateway::class);

    expect($manager->driver('mock'))->toBeInstanceOf(PaymentGateway::class);
});

// ───────────────────────────── the tombstone ─────────────────────────────
//
// khqr.cc was retired in 2026-09, but khqr_payments.provider is history as much
// as configuration: rows minted there are settled money that platform finance
// and the payments console still read. If the key stopped resolving, a payment
// from last quarter would 500 a reporting page.

it('still resolves rows minted at the retired khqr.cc gateway', function () {
    $legacy = KhqrPayment::create([
        'transaction_id' => 'TX-LEGACY-1',
        'provider' => 'khqrpay',
        'amount' => 10, 'currency' => 'USD', 'status' => 'qr_generated',
        'channel' => 'api', 'checkout_payload' => [],
    ]);

    $gateway = app(PaymentManager::class)->for($legacy);

    expect($gateway)->toBeInstanceOf(RetiredKhqrPayGateway::class)
        ->and($gateway->provider())->toBe('khqrpay');
});

it('never confirms a payment through a retired or manual driver', function () {
    $row = KhqrPayment::create([
        'transaction_id' => 'TX-LEGACY-2',
        'provider' => 'khqrpay',
        'amount' => 10, 'currency' => 'USD', 'status' => 'qr_generated',
        'channel' => 'api', 'checkout_payload' => [],
    ]);

    // FALSE means "no confirmation", never "unpaid" — and nothing acts on it.
    // There is no client left to ask khqr.cc with, and rent is confirmed by the
    // landlord. A true here would book money nobody has checked for.
    expect(app(PaymentManager::class)->driver('khqrpay')->verify($row))->toBeFalse()
        ->and(app(PaymentManager::class)->driver('manual')->verify($row))->toBeFalse();
});

it('exposes no webhook surface on any driver', function () {
    // The contract dropped validateWebhook() with khqr.cc: the Bakong Open API
    // publishes no callback, and /khqr/callback — public and CSRF-exempt — was
    // deleted with the provider. A driver growing this method back would be the
    // first step to that endpoint returning.
    foreach (['bakong', 'manual', 'khqrpay'] as $provider) {
        expect(method_exists(app(PaymentManager::class)->driver($provider), 'validateWebhook'))->toBeFalse();
    }
});
