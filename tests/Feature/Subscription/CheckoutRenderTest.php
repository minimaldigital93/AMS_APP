<?php

use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;

beforeEach(function () {
    seedRoles();
    Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24,
        'max_floors' => 4, 'max_apartments' => 200, 'billing_period_days' => 30, 'is_active' => true,
    ]);
});

it('renders the public signup form (GET subscribe)', function () {
    $this->get(route('subscribe.create'))->assertOk();
});

it('renders the subscription checkout/pay page without 500', function () {
    config(['services.khqrpay.demo' => true]);

    $this->post(route('subscribe.store'), [
        'name' => 'Pay Renderer',
        'phone' => '0997000111',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    $sub = Subscription::first();
    $payment = KhqrPayment::where('subscription_id', $sub->id)->first();

    $this->get(route('subscribe.checkout', $payment->public_token))->assertOk();
});
