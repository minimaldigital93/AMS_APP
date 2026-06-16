<?php

use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;

beforeEach(function () {
    config(['services.khqrpay.demo' => true]);
    seedRoles();
    Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24,
        'max_floors' => 4, 'max_apartments' => 200, 'billing_period_days' => 30, 'is_active' => true,
    ]);

    $this->post(route('subscribe.store'), [
        'name' => 'Token Owner',
        'phone' => '0997111222',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    $sub = Subscription::first();
    $this->payment = KhqrPayment::where('subscription_id', $sub->id)->first();
});

it('mints an unguessable 40-char public token distinct from the transaction id', function () {
    expect($this->payment->public_token)->toHaveLength(40);
    expect($this->payment->public_token)->not->toBe($this->payment->transaction_id);
});

it('serves the checkout + status pages by public token', function () {
    $this->get(route('subscribe.checkout', $this->payment->public_token))->assertOk();
    $this->getJson(route('subscribe.checkout.status', $this->payment->public_token))
        ->assertOk()
        ->assertJsonStructure(['status', 'paid', 'redirect']);
});

it('no longer addresses the public routes by guessable transaction id', function () {
    // The whole point of the token: knowing/guessing SUB-… must not work.
    $this->get(route('subscribe.checkout', $this->payment->transaction_id))->assertNotFound();
    $this->getJson(route('subscribe.checkout.status', $this->payment->transaction_id))->assertNotFound();
});
