<?php

use App\Models\KhqrPayment;
use App\Models\PaymentWebhook;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    config()->set('services.khqrpay.base_url', 'https://khqr.cc');
    config()->set('services.khqrpay.profile_id', 'profile123');
    config()->set('services.khqrpay.secret', 'test-secret');
    config()->set('services.khqrpay.currency', 'USD');
    config()->set('services.khqrpay.demo', false);
    seedRoles();

    $this->plan = Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24,
        'max_floors' => 4, 'max_apartments' => 200, 'billing_period_days' => 30, 'is_active' => true,
    ]);

    $this->owner = User::factory()->create(['phone' => '0999000abc', 'status' => 'inactive']);
    $this->owner->forceFill(['account_id' => $this->owner->id])->save();

    $this->sub = Subscription::create([
        'account_id' => $this->owner->id,
        'plan_id' => $this->plan->id,
        'status' => 'pending',
    ]);

    $this->payment = KhqrPayment::create([
        'transaction_id' => 'SUB-HOOK-1',
        'subscription_id' => $this->sub->id,
        'amount' => 24,
        'currency' => 'USD',
        'status' => 'qr_generated',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription'],
    ]);
});

/** A correctly-signed SUCCESS payload for the seeded payment. */
function validCallback(array $overrides = []): array
{
    $payload = array_merge([
        'transaction_id' => 'SUB-HOOK-1',
        'amount' => '24.00',
        'currency' => 'USD',
        'status' => 'SUCCESS',
        'req_time' => (string) time(),
    ], $overrides);

    // Let a test force a forged hash by passing one in $overrides.
    if (! array_key_exists('hash', $overrides)) {
        $secret = config('services.khqrpay.secret');
        $payload['hash'] = hash('sha256',
            $secret.$payload['req_time'].$payload['transaction_id'].$payload['amount'].strtoupper($payload['status'])
        );
    }

    return $payload;
}

it('finalizes the subscription and logs a processed webhook on a valid callback', function () {
    $res = $this->postJson(route('khqr.callback'), validCallback());

    $res->assertOk()->assertJson(['ok' => true]);

    expect($this->sub->fresh()->status)->toBe('active');
    expect($this->payment->fresh()->status)->toBe('paid');
    expect($this->owner->fresh()->hasRole('admin'))->toBeTrue();

    $hook = PaymentWebhook::sole();
    expect($hook->status)->toBe(PaymentWebhook::STATUS_PROCESSED);
    expect($hook->signature_valid)->toBeTrue();
    expect($hook->khqr_payment_id)->toBe($this->payment->id);
});

it('acks a duplicate/replayed delivery without finalizing twice', function () {
    $payload = validCallback();

    $this->postJson(route('khqr.callback'), $payload)->assertOk();
    $firstExpiry = $this->sub->fresh()->expires_at;

    // Exact same payload (same hash) replayed.
    $this->postJson(route('khqr.callback'), $payload)
        ->assertOk()
        ->assertJson(['duplicate' => true]);

    expect($this->sub->fresh()->expires_at->toDateTimeString())->toBe($firstExpiry->toDateTimeString());
    expect(PaymentWebhook::count())->toBe(1); // only the first delivery was stored
});

it('rejects a forged signature with 403 and marks the webhook invalid', function () {
    $payload = validCallback(['hash' => 'deadbeef']);

    $this->postJson(route('khqr.callback'), $payload)->assertStatus(403);

    expect($this->sub->fresh()->status)->toBe('pending'); // not activated
    expect(PaymentWebhook::sole()->status)->toBe(PaymentWebhook::STATUS_INVALID);
});

it('rejects an unknown transaction with the same 403 (no enumeration)', function () {
    $res = $this->postJson(route('khqr.callback'), validCallback([
        'transaction_id' => 'SUB-DOES-NOT-EXIST',
    ]));

    $res->assertStatus(403)->assertJson(['message' => 'Invalid signature']);
});

it('rejects a stale (replayed) delivery outside the freshness window', function () {
    config()->set('services.khqrpay.webhook_tolerance', 300);

    $payload = validCallback(['req_time' => (string) (time() - 4000)]);

    $this->postJson(route('khqr.callback'), $payload)->assertStatus(403);

    expect($this->sub->fresh()->status)->toBe('pending');
    expect(PaymentWebhook::sole()->error)->toBe('stale req_time');
});

it('rejects a callback whose amount does not match the minted row', function () {
    // Correctly signed, but for $1 instead of the $24 the QR was minted for.
    $this->postJson(route('khqr.callback'), validCallback(['amount' => '1.00']))
        ->assertStatus(403);

    expect($this->sub->fresh()->status)->toBe('pending');
});
