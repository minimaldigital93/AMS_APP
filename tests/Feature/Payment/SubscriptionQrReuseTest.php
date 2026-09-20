<?php

use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Bakong\BakongTransactionService;

beforeEach(function () {
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
    $this->svc = app(BakongTransactionService::class);
});

it('retires the previous QR and mints a fresh session each time checkout is re-initiated', function () {
    // Two live QRs for one subscription is a double-payment waiting to happen —
    // and under Bakong it is also two md5s to poll, so twice the metered cost
    // for one sale.
    $first = $this->svc->createSubscriptionQr($this->sub, 24.0);
    $second = $this->svc->createSubscriptionQr($this->sub, 24.0);

    expect($second->id)->not->toBe($first->id);       // fresh transaction minted
    expect($first->fresh()->status)->toBe('expired'); // stale QR retired
    expect(KhqrPayment::where('subscription_id', $this->sub->id)
        ->whereIn('status', \App\Enums\PaymentStatus::openValues())
        ->count())->toBe(1);
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

it('mints the QR without contacting Bakong at all', function () {
    Illuminate\Support\Facades\Http::fake();

    $row = $this->svc->createSubscriptionQr($this->sub, 24.0);

    // The single biggest saving of the direct integration over a hosted
    // checkout: the payload is EMV built here, so a customer reaching the
    // checkout page costs nothing. Only verification is metered — which is why
    // the whole quota budget can be spent on confirming payments rather than on
    // creating them.
    Illuminate\Support\Facades\Http::assertNothingSent();
    expect($row->qr_payload)->not->toBeEmpty()
        ->and($row->qr_md5)->toBe(md5($row->qr_payload));
});

it('leaves a dead QR open rather than expiring it on a refusal', function () {
    $payment = KhqrPayment::create([
        'transaction_id' => 'SUB-EXP-1',
        'provider' => 'bakong',
        'subscription_id' => $this->sub->id,
        'amount' => 24,
        'currency' => 'USD',
        'status' => 'qr_generated',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription'],
        'expires_at' => now()->subHours(3), // long dead
    ]);

    $res = $this->getJson(route('subscribe.checkout.status', $payment->public_token));

    // A REFUSAL IS NOT A VERDICT. Nothing answered — the row has no md5, so
    // there was no question to ask and the client refused locally — and
    // expiring on that would write a payment out of the books if the money had
    // in fact landed at the deadline. finalize() refuses a closed row, so the
    // expiry would also shut `bakong:reconcile` out of ever rescuing it.
    $res->assertOk()->assertJson(['paid' => false, 'gateway_error' => true]);
    expect($payment->fresh()->isOpen())->toBeTrue();

    // The checkout page stops on its own countdown rather than waiting to be
    // told the row is expired, and `khqr:expire-abandoned` is the human closer
    // for whatever is still open a day later.
    $this->artisan('khqr:expire-abandoned', ['--hours' => 1, '--force' => true])->assertSuccessful();
    expect($payment->fresh()->status)->toBe('expired');
});
