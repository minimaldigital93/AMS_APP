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
 * Migrating a live payment provider is only safe if going back is one
 * environment variable — and if flipping it cannot strand the customers who are
 * already mid-checkout. Those are two different rules, and conflating them is
 * the bug:
 *
 *  1. WHICH PROVIDER MINTS A NEW PAYMENT is decided by CONFIGURATION
 *     (BAKONG_API_ENABLED). Rollback is flipping it off.
 *
 *  2. WHICH PROVIDER ANSWERS FOR AN EXISTING PAYMENT is decided by the ROW
 *     (khqr_payments.provider). A QR minted at khqr.cc must keep being asked
 *     about at khqr.cc for the rest of its life. If the switch changed this
 *     too, flipping it would start polling every in-flight khqr.cc QR against
 *     Bakong — where that transaction does not exist, so Bakong would answer
 *     "transaction could not be found", which reads as UNPAID, which expires
 *     the QR. Every customer paying at the moment of the switch would lose
 *     their checkout, and any who had already paid would have it written out of
 *     the books.
 */
beforeEach(function () {
    Cache::flush();
    seedRoles();

    config()->set('bakong.base_url', 'https://bakong.test');
    config()->set('bakong.account_id', 'ams_test@devb');
    config()->set('bakong.integrator.email', 'integrator@ams.test');
    config()->set('bakong.demo', false);
    // The suite runs KHQRPay in demo mode, which short-circuits every request
    // locally. These tests are about WHERE a request goes, so the legacy path
    // has to be able to actually make one.
    config()->set('services.khqrpay.demo', false);

    BakongToken::create([
        'email' => 'integrator@ams.test',
        'token' => 'eyJ0eXAiOiJKV1QifQ.e30.sig',
        'expires_at' => now()->addDays(60),
        'verified_at' => now(),
    ]);

    // Platform KHQRPay credentials, so the legacy path is genuinely usable and
    // a failure to take it shows up as a failure rather than as a fallback.
    PlatformPaymentSetting::create([
        'khqrpay_profile_id' => 'profile-1',
        'khqrpay_secret' => 'secret-1',
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

    expect($checkout->usesBakong())->toBeTrue()
        ->and($row->provider)->toBe('bakong')
        ->and($row->qr_md5)->not->toBeNull()
        // Null handoff means "render our own checkout page": there is no
        // redirect()->away(), which is what removes the one-way door.
        ->and($checkout->handoffUrl($row, 'https://ams.test/return'))->toBeNull()
        ->and($checkout->qrImage($row))->toStartWith('data:image/svg+xml;base64,');

    // And it cost nothing: no QR endpoint to call, no hosted page to preflight.
    Http::assertNothingSent();
});

it('makes no preflight probes at all under Bakong', function () {
    config()->set('bakong.enabled', true);
    Http::fake();

    // platformCheckoutFault() spends TWO metered requests probing a gateway the
    // customer is about to be redirected to. Under Bakong they are never
    // redirected anywhere, so the question does not arise.
    expect(app(SubscriptionCheckout::class)->preflightFault())->toBeNull();

    Http::assertNothingSent();
});

it('falls straight back to KHQRPay when the switch is off', function () {
    config()->set('bakong.enabled', false);
    Http::fake(['khqr.cc/*' => Http::response(['responseCode' => 0, 'data' => []], 200)]);

    $checkout = app(SubscriptionCheckout::class);
    $row = $checkout->create(switchSubscription(), 10.00);

    expect($checkout->usesBakong())->toBeFalse()
        ->and($row->fresh()->provider)->toBe('khqrpay')  // the column defaults at the DB level
        // A hosted checkout: the browser IS taken away, to a signed khqr.cc URL.
        ->and($checkout->handoffUrl($row, 'https://ams.test/return'))->toContain('khqr.cc')
        ->and($checkout->qrImage($row))->toBeNull();
});

it('is switched by one environment variable and nothing else', function () {
    Http::fake();

    // No controller edit, no migration, no deploy step — which matters because
    // the moment rollback is needed is the moment nobody wants to be editing
    // controllers.
    config()->set('bakong.enabled', true);
    expect(app(SubscriptionCheckout::class)->usesBakong())->toBeTrue();

    config()->set('bakong.enabled', false);
    expect(app(SubscriptionCheckout::class)->usesBakong())->toBeFalse();

    // An empty base URL is a second off switch: a half-configured .env must not
    // put customers on a provider that cannot be reached.
    config()->set('bakong.enabled', true);
    config()->set('bakong.base_url', '');
    expect(app(SubscriptionCheckout::class)->usesBakong())->toBeFalse();
});

// ═════════════════ rule 2: the row decides existing payments ═════════════════

it('keeps polling an in-flight KHQRPay payment at khqr.cc after the switch is flipped', function () {
    config()->set('bakong.enabled', false);
    Http::fake(['khqr.cc/*' => Http::response(['responseCode' => 0, 'data' => []], 200)]);

    $legacy = app(SubscriptionCheckout::class)->create(switchSubscription(), 10.00);
    expect($legacy->fresh()->provider)->toBe('khqrpay');

    // The operator switches providers while this customer is still paying.
    config()->set('bakong.enabled', true);
    Cache::flush();

    Http::fake([
        'khqr.cc/*' => Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction not found'], 200),
        // If the poll were routed by CONFIG instead of by the row, it would
        // arrive here — and Bakong, which has never heard of this transaction,
        // would answer "could not be found". That reads as UNPAID, and UNPAID
        // is the verdict that expires the QR.
        'bakong.test/*' => Http::response(['responseCode' => 1, 'errorCode' => 1], 200),
    ]);

    app(SubscriptionCheckout::class)->poll($legacy->fresh());

    Http::assertSent(fn ($r) => str_contains($r->url(), 'khqr.cc'));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), 'bakong.test'));
});

it('keeps answering for a Bakong payment after the switch is turned back off', function () {
    config()->set('bakong.enabled', true);
    Http::fake();

    $row = app(SubscriptionCheckout::class)->create(switchSubscription(), 10.00);
    expect($row->provider)->toBe('bakong');

    // Rollback, mid-checkout. The row still knows who minted it.
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

    foreach (['khqrpay' => false, 'bakong' => true] as $provider => $expected) {
        $row = new KhqrPayment(['provider' => $provider]);
        expect($row->usesBakong())->toBe($expected, $provider);
    }

    // A row with no provider is a pre-migration row, and those were all KHQRPay.
    expect((new KhqrPayment)->usesBakong())->toBeFalse();
});
