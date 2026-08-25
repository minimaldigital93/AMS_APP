<?php

use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\PlatformPaymentSetting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Handing the browser to khqr.cc is a ONE-WAY DOOR. A profile that cannot
 * transact answers the hosted-checkout request with a raw JSON body — the
 * customer ends up reading {"responseCode":1,…} on someone else's domain and
 * this app never gets to say what happened or offer a retry.
 *
 * So both entry points ask the gateway first, and keep the customer on their
 * own page with a warning when the answer is bad.
 */
beforeEach(function () {
    seedRoles();
    config()->set('services.khqrpay.base_url', 'https://khqr.cc');
    config()->set('services.khqrpay.demo', false);
    Cache::flush(); // the healthy verdict is cached across checkouts

    // Platform credentials live in the DB, not .env (see KhqrCredentials).
    PlatformPaymentSetting::create([
        'khqrpay_profile_id' => 'PROFILE-1',
        'khqrpay_secret' => 'platform-secret',
        'currency' => 'USD',
    ]);

    $this->plan = Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24,
        'billing_period_days' => 30, 'is_active' => true,
    ]);
});

/** The signup form's fields, so each test only states what it varies. */
function signupPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Owner',
        'phone' => '0999000111',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ], $overrides);
}

it('keeps the customer on the signup form when the gateway cannot take payments', function () {
    // The unprovisioned-profile signature on this integration: khqr.cc 502s.
    Http::fake(['khqr.cc/*' => Http::response('Bad Gateway', 502)]);

    $response = $this->post(route('subscribe.store'), signupPayload());

    // A warning on our own form — NOT a redirect onto khqr.cc's JSON error page.
    $response->assertRedirect();
    $response->assertSessionHas('error', __('messages.subscription_gateway_unavailable'));
    expect($response->headers->get('Location'))->not->toContain('khqr.cc');

    // Refused before anything was minted, so there is no half-finished signup
    // and no orphan QR — same contract as the missing-credentials guard.
    expect(User::where('phone', '0999000111')->exists())->toBeFalse();
    expect(Subscription::count())->toBe(0);
    expect(KhqrPayment::count())->toBe(0);
});

it('reads a credential refusal in the gateway body, not just the HTTP status', function () {
    // A lapsed Bakong OpenAPI token answers 200 with a non-zero responseCode.
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 1,
        'responseMessage' => 'Bakong Token Required: No active official Bakong OpenAPI token configured.',
    ], 200)]);

    $response = $this->post(route('subscribe.store'), signupPayload());

    $response->assertSessionHas('error', __('messages.subscription_gateway_unavailable'));
    expect(User::where('phone', '0999000111')->exists())->toBeFalse();
});

it('lets checkout through when the gateway merely does not know the probe transaction', function () {
    // The HEALTHY answer, and what khqr.cc really sends: the probe id
    // deliberately does not exist there, so a working profile answers 404
    // "Transaction Not Found". Reading that status as a fault blocked every
    // checkout on a correctly configured profile — pin the real shape.
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 1,
        'responseMessage' => 'Transaction Not Found',
    ], 404)]);

    $response = $this->post(route('subscribe.store'), signupPayload());

    $response->assertSessionMissing('error');
    expect($response->headers->get('Location'))->toContain('/api/payment/request/');
    expect(User::where('phone', '0999000111')->exists())->toBeTrue();
});

it('still blocks when a 404 names the profile rather than the transaction', function () {
    // The other 404: a wrong profile id. Same status as the healthy answer
    // above, so the message is what separates them.
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 1,
        'responseMessage' => 'Merchant Profile Not Found',
    ], 404)]);

    $response = $this->post(route('subscribe.store'), signupPayload());

    $response->assertSessionHas('error', __('messages.subscription_gateway_unavailable'));
    expect(User::where('phone', '0999000111')->exists())->toBeFalse();
    expect(KhqrPayment::count())->toBe(0);
});

it('blocks a wrong secret, which the gateway rejects as an invalid hash', function () {
    // The state this account was actually in: profile recognised, secret stale.
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 1,
        'responseMessage' => 'Invalid Security Hash',
    ], 403)]);

    $response = $this->post(route('subscribe.store'), signupPayload());

    $response->assertSessionHas('error', __('messages.subscription_gateway_unavailable'));
    expect(KhqrPayment::count())->toBe(0);
});

it('fails open when the gateway is simply unreachable', function () {
    // A timeout is ambiguous — the hosted page may still load for the customer.
    // Blocking a working checkout on a flaky probe would cost real money.
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));

    $response = $this->post(route('subscribe.store'), signupPayload());

    $response->assertSessionMissing('error');
    expect($response->headers->get('Location'))->toContain('/api/payment/request/');
});

it('blocks a renewal the same way and mints nothing', function () {
    Http::fake(['khqr.cc/*' => Http::response('Bad Gateway', 502)]);
    $admin = makeAdmin();
    $before = KhqrPayment::count();
    $this->actingAs($admin);

    $response = $this->post(route('admin.billing.renew'), ['plan' => 'pro', 'billing_cycle' => 'monthly']);

    $response->assertRedirect();
    $response->assertSessionHas('error', __('messages.subscription_gateway_unavailable'));
    expect($response->headers->get('Location'))->not->toContain('khqr.cc');
    expect(KhqrPayment::count())->toBe($before);
});

it('does not probe the gateway in demo mode', function () {
    config()->set('services.khqrpay.demo', true);
    Http::fake(['khqr.cc/*' => Http::response('Bad Gateway', 502)]);

    $this->post(route('subscribe.store'), signupPayload())->assertSessionMissing('error');

    Http::assertNothingSent();
});

it('caches a healthy verdict so back-to-back checkouts cost one round trip', function () {
    Http::fake(['khqr.cc/*' => Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction not found'], 200)]);

    $this->post(route('subscribe.store'), signupPayload(['phone' => '0999000111']));
    $this->post(route('subscribe.store'), signupPayload(['phone' => '0999000222']));

    Http::assertSentCount(1);
});
