<?php

use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\RevenueExpense\KhqrPaymentService;

beforeEach(function () {
    config(['services.khqrpay.demo' => true]); // local QR, no live HTTP
    seedRoles();

    $this->plan = Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24,
        'max_floors' => 4, 'max_apartments' => 200, 'billing_period_days' => 30, 'is_active' => true,
    ]);
    $this->pro2 = Plan::create([
        'slug' => 'max', 'name' => 'Max', 'price_usd' => 49,
        'max_floors' => 9, 'max_apartments' => 900, 'billing_period_days' => 30, 'is_active' => true,
    ]);

    $this->owner = User::factory()->create(['status' => 'inactive']);
    $this->owner->forceFill(['account_id' => $this->owner->id])->save();
    $this->sub = Subscription::create([
        'account_id' => $this->owner->id, 'plan_id' => $this->plan->id, 'status' => 'pending',
    ]);
    $this->svc = app(KhqrPaymentService::class);
});

it('reuses the existing payable QR when renew is clicked twice for the same plan', function () {
    $first = $this->svc->createSubscriptionQr($this->sub, 24.0);
    $second = $this->svc->createSubscriptionQr($this->sub, 24.0);

    expect($second->id)->toBe($first->id);            // same transaction reused
    expect(KhqrPayment::where('subscription_id', $this->sub->id)->count())->toBe(1);
});

it('retires the stale QR and mints a fresh one when the plan/price changes', function () {
    $first = $this->svc->createSubscriptionQr($this->sub, 24.0);
    $second = $this->svc->createSubscriptionQr($this->sub, 49.0);

    expect($second->id)->not->toBe($first->id);
    expect($first->fresh()->status)->toBe('expired'); // old QR retired
    expect($second->status)->toBe('qr_generated');
    expect($second->amount)->toBe(49.0);
});

it('sets an expiry on a freshly minted subscription QR', function () {
    $row = $this->svc->createSubscriptionQr($this->sub, 24.0);

    expect($row->expires_at)->not->toBeNull();
    expect($row->expires_at->isFuture())->toBeTrue();
});

it('lazily expires a dead QR when the status page is polled', function () {
    $payment = KhqrPayment::create([
        'transaction_id' => 'SUB-EXP-1',
        'subscription_id' => $this->sub->id,
        'amount' => 24,
        'currency' => 'USD',
        'status' => 'qr_generated',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription'],
        'expires_at' => now()->subMinute(), // already dead
    ]);

    $res = $this->getJson(route('subscribe.checkout.status', $payment->public_token));

    $res->assertOk()->assertJson(['status' => 'expired', 'paid' => false]);
    expect($payment->fresh()->status)->toBe('expired');
});
