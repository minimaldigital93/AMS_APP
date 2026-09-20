<?php

use App\Models\BakongToken;
use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\PlatformPaymentSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\SubscriptionCheckout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * THE SWITCH, AND THE THING IT MUST NOT BREAK.
 *
 * khqr.cc was retired in 2026-09 and Bakong is now the only provider that can
 * mint a subscription payment. That collapses one of the two rules this file
 * was written for, but not the other — and the surviving one is the one that
 * protects money:
 *
 *  1. WHETHER A NEW PAYMENT CAN BE MINTED is decided by CONFIGURATION
 *     (BAKONG_API_ENABLED). With it off, checkout REFUSES. There is no fallback
 *     any more, and that is deliberate: minting a session nobody can confirm is
 *     worse than refusing, because it shows the customer a QR nobody is
 *     watching and is then swept by every net that looks for open rows.
 *
 *  2. WHICH PROVIDER ANSWERS FOR AN EXISTING PAYMENT is decided by the ROW
 *     (khqr_payments.provider), and this still matters with one provider left.
 *     A QR minted at khqr.cc must never be asked about at Bakong, where that
 *     transaction does not exist — Bakong would answer "transaction could not
 *     be found", which reads as UNPAID, which expires the QR. A payment that
 *     had in fact landed would be written out of the books on the strength of a
 *     question asked at the wrong gateway.
 */
beforeEach(function () {
    Cache::flush();
    seedRoles();

    config()->set('bakong.base_url', 'https://bakong.test');
    config()->set('bakong.account_id', 'ams_test@devb');
    config()->set('bakong.integrator.email', 'integrator@ams.test');
    config()->set('bakong.demo', false);

    BakongToken::create([
        'email' => 'integrator@ams.test',
        'token' => 'eyJ0eXAiOiJKV1QifQ.e30.sig',
        'expires_at' => now()->addDays(60),
        'verified_at' => now(),
    ]);

    PlatformPaymentSetting::create([
        'bakong_account_id' => 'platform@aclb',
        'merchant_name' => 'AMS',
        'currency' => 'USD',
    ]);
});

function switchSubscription(): Subscription
{
    $owner = User::factory()->create(['status' => 'inactive']);
    $owner->forceFill(['account_id' => $owner->id])->save();

    $plan = Plan::create([
        'name' => 'Basic', 'slug' => 'basic-'.$owner->id, 'price_usd' => 10,
        'billing_period_days' => 30, 'max_rooms' => 10, 'max_staff' => 2, 'is_active' => true,
    ]);

    return Subscription::create([
        'account_id' => $owner->id,
        'plan_id' => $plan->id,
        'status' => 'pending',
        'billing_cycle' => 'monthly',
    ]);
}

// ═════════════════════ rule 1: config decides new payments ═════════════════════

it('mints through Bakong when the switch is on, and keeps the payer on our own page', function () {
    config()->set('bakong.enabled', true);
    Http::fake();

    $checkout = app(SubscriptionCheckout::class);
    $row = $checkout->create(switchSubscription(), 10.00);

    expect($checkout->available())->toBeTrue()
        ->and($row->provider)->toBe('bakong')
        ->and($row->qr_md5)->not->toBeNull()
        // Null handoff means "render our own checkout page": there is no
        // redirect()->away(), which is what removes the one-way door.
        ->and($checkout->handoffUrl($row, 'https://ams.test/return'))->toBeNull()
        ->and($checkout->qrImage($row))->toStartWith('data:image/svg+xml;base64,');

    // And it cost nothing: no QR endpoint to call, no hosted page to preflight.
    Http::assertNothingSent();
});

it('makes no preflight probes at all', function () {
    config()->set('bakong.enabled', true);
    Http::fake();

    // The retired platformCheckoutFault() spent TWO metered requests probing a
    // gateway the customer was about to be redirected to. They are never
    // redirected anywhere now, so the question does not arise.
    expect(app(SubscriptionCheckout::class)->preflightFault())->toBeNull();

    Http::assertNothingSent();
});

it('refuses to mint rather than falling back when the switch is off', function () {
    config()->set('bakong.enabled', false);
    Http::fake();

    $checkout = app(SubscriptionCheckout::class);

    expect($checkout->available())->toBeFalse();
    expect(fn () => $checkout->create(switchSubscription(), 10.00))
        ->toThrow(RuntimeException::class);

    // Refused BEFORE any row exists, so there is no orphan session to sweep.
    expect(KhqrPayment::count())->toBe(0);
    Http::assertNothingSent();
});

it('is switched by one environment variable and nothing else', function () {
    Http::fake();

    // No controller edit, no migration, no deploy step — which matters because
    // the moment a switch-off is needed is the moment nobody wants to be
    // editing controllers.
    config()->set('bakong.enabled', true);
    expect(app(SubscriptionCheckout::class)->available())->toBeTrue();

    config()->set('bakong.enabled', false);
    expect(app(SubscriptionCheckout::class)->available())->toBeFalse();

    // An empty base URL is a second off switch: a half-configured .env must not
    // put customers on a provider that cannot be reached.
    config()->set('bakong.enabled', true);
    config()->set('bakong.base_url', '');
    expect(app(SubscriptionCheckout::class)->available())->toBeFalse();
});

// ═════════════════ rule 2: the row decides existing payments ═════════════════

it('never polls a legacy khqr.cc payment against Bakong', function () {
    config()->set('bakong.enabled', true);
    Http::fake();

    $legacy = KhqrPayment::create([
        'transaction_id' => 'LEGACY-SWITCH-1',
        'provider' => 'khqrpay',
        'subscription_id' => switchSubscription()->id,
        'amount' => 10.00, 'currency' => 'USD', 'status' => 'qr_generated',
        'settlement_target' => 'platform', 'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription'],
        'expires_at' => now()->addMinutes(5),
    ]);

    $result = app(SubscriptionCheckout::class)->poll($legacy);

    // Bakong has never heard of this transaction and would answer "could not be
    // found" — which reads as UNPAID, and UNPAID is the verdict that expires a
    // QR. So the row is not asked about at all: it reports no answer, stays
    // open, and waits for a human.
    Http::assertNothingSent();
    expect($result['gateway_error'])->toBeTrue()
        ->and($result['gateway_answered'])->toBeFalse()
        ->and($result['payment']->isOpen())->toBeTrue();
});

it('keeps answering for a Bakong payment after the switch is turned off', function () {
    config()->set('bakong.enabled', true);
    Http::fake();

    $row = app(SubscriptionCheckout::class)->create(switchSubscription(), 10.00);
    expect($row->provider)->toBe('bakong');

    // Switched off mid-checkout. The row still knows who minted it.
    config()->set('bakong.enabled', false);

    expect($row->fresh()->usesBakong())->toBeTrue()
        ->and(app(SubscriptionCheckout::class)->qrImage($row->fresh()))
        ->toStartWith('data:image/svg+xml;base64,');

    // The master switch still refuses the outbound call, so the row simply
    // stays open for whenever it is turned back on — it is never expired on the
    // strength of a request that was not made.
    $result = app(SubscriptionCheckout::class)->poll($row->fresh());

    expect($result['payment']->isOpen())->toBeTrue();
    Http::assertNothingSent();
});

it('never routes a payment to a provider that did not mint it', function () {
    Http::fake();

    foreach (['khqrpay' => false, 'manual' => false, 'bakong' => true] as $provider => $expected) {
        $row = new KhqrPayment(['provider' => $provider]);
        expect($row->usesBakong())->toBe($expected, $provider);
    }

    // A row with no provider is a pre-migration row, and those were all KHQRPay.
    expect((new KhqrPayment)->usesBakong())->toBeFalse();
});
