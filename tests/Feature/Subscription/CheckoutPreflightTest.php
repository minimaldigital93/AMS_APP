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

/**
 * The preflight probes TWO endpoints, and they answer differently in real life:
 *
 *  - check-transv2-khqrcc replies JSON, and its healthy answer to our throwaway
 *    probe id is a refusal ("Transaction Not Found");
 *  - /api/payment/request/{profile} is the hosted checkout the customer is
 *    handed to, and its healthy answer is an HTML payment FORM. JSON from it is
 *    the failure — it is exactly what the customer used to be shown.
 *
 * A single blanket fake would feed the checkout endpoint a JSON refusal and
 * make every test look like a broken gateway, so each test states both.
 */
function fakeGateway($checkTransaction, $handoff = null): void
{
    // Http::fake() MERGES stubs and the FIRST match wins, so calling this twice
    // in one test would leave the original answer in place — and the tests that
    // matter most here are the ones where the gateway starts misbehaving and
    // then recovers. Swap the factory so a second call really replaces the
    // first.
    Http::swap(new \Illuminate\Http\Client\Factory);

    Http::fake([
        'khqr.cc/api/payment/request/*' => $handoff ?? Http::response('<html><body>KHQR payment</body></html>', 200),
        'khqr.cc/*' => $checkTransaction,
    ]);
}

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
    fakeGateway(Http::response('Bad Gateway', 502));

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
    fakeGateway(Http::response([
        'responseCode' => 1,
        'responseMessage' => 'Bakong Token Required: No active official Bakong OpenAPI token configured.',
    ], 200));

    $response = $this->post(route('subscribe.store'), signupPayload());

    $response->assertSessionHas('error', __('messages.subscription_gateway_unavailable'));
    expect(User::where('phone', '0999000111')->exists())->toBeFalse();
});

it('lets checkout through when the gateway merely does not know the probe transaction', function () {
    // The HEALTHY answer, and what khqr.cc really sends: the probe id
    // deliberately does not exist there, so a working profile answers 404
    // "Transaction Not Found". Reading that status as a fault blocked every
    // checkout on a correctly configured profile — pin the real shape.
    fakeGateway(Http::response([
        'responseCode' => 1,
        'responseMessage' => 'Transaction Not Found',
    ], 404));

    $response = $this->post(route('subscribe.store'), signupPayload());

    $response->assertSessionMissing('error');
    expect($response->headers->get('Location'))->toContain('/api/payment/request/');
    expect(User::where('phone', '0999000111')->exists())->toBeTrue();
});

it('still blocks when a 404 names the profile rather than the transaction', function () {
    // The other 404: a wrong profile id. Same status as the healthy answer
    // above, so the message is what separates them.
    fakeGateway(Http::response([
        'responseCode' => 1,
        'responseMessage' => 'Merchant Profile Not Found',
    ], 404));

    $response = $this->post(route('subscribe.store'), signupPayload());

    $response->assertSessionHas('error', __('messages.subscription_gateway_unavailable'));
    expect(User::where('phone', '0999000111')->exists())->toBeFalse();
    expect(KhqrPayment::count())->toBe(0);
});

it('blocks a wrong secret, which the gateway rejects as an invalid hash', function () {
    // The state this account was actually in: profile recognised, secret stale.
    fakeGateway(Http::response([
        'responseCode' => 1,
        'responseMessage' => 'Invalid Security Hash',
    ], 403));

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
    fakeGateway(Http::response('Bad Gateway', 502));
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
    fakeGateway(Http::response('Bad Gateway', 502));

    $this->post(route('subscribe.store'), signupPayload())->assertSessionMissing('error');

    Http::assertNothingSent();
});

it('caches a healthy verdict so back-to-back checkouts cost one round trip', function () {
    fakeGateway(Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction not found'], 200));

    $this->post(route('subscribe.store'), signupPayload(['phone' => '0999000111']));
    $this->post(route('subscribe.store'), signupPayload(['phone' => '0999000222']));

    // One probe EACH for the two endpoints on the first checkout; the second is
    // served entirely from the healthy verdict.
    Http::assertSentCount(2);
});

/*
|--------------------------------------------------------------------------
| The handoff probe — the gap the first preflight left open
|--------------------------------------------------------------------------
|
| Checking a transaction and taking money are different permissions at
| khqr.cc. A profile whose Bakong token has never been activated answers the
| read-only check endpoint perfectly well and then hands the customer a JSON
| refusal on the checkout page. Every test above passes with only the first
| probe; these are the ones that failed, and the reason customers still ended
| up reading {"responseCode":1,…} on someone else's domain.
*/

it('blocks the handoff when only the checkout endpoint refuses', function () {
    fakeGateway(
        // The profile answers queries exactly like a healthy one…
        Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404),
        // …and still cannot take money.
        Http::response([
            'responseCode' => 1,
            'responseMessage' => 'Bakong Token Required: No active official Bakong OpenAPI token configured.',
        ], 200),
    );

    $response = $this->post(route('subscribe.store'), signupPayload());

    $response->assertSessionHas('error', __('messages.subscription_gateway_unavailable'));
    $response->assertSessionHas('khqr_fault', true);
    expect($response->headers->get('Location'))->not->toContain('khqr.cc');
    expect(User::where('phone', '0999000111')->exists())->toBeFalse();
    expect(KhqrPayment::count())->toBe(0);
});

it('lets the handoff through when the checkout endpoint renders a payment page', function () {
    fakeGateway(
        Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404),
        Http::response('<html><body>KHQR payment</body></html>', 200),
    );

    $response = $this->post(route('subscribe.store'), signupPayload());

    $response->assertSessionMissing('error');
    expect($response->headers->get('Location'))->toContain('/api/payment/request/');
});

it('probes the handoff with a throwaway transaction id, never the customer\'s', function () {
    // khqr.cc checkout sessions are single-use: probing the row's own URL would
    // burn the session the customer is about to open. THAT is the rule — the
    // request itself is fine.
    fakeGateway(Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404));

    $response = $this->post(route('subscribe.store'), signupPayload());

    $minted = KhqrPayment::firstOrFail();
    Http::assertSent(function ($request) use ($minted) {
        if (! str_contains($request->url(), '/api/payment/request/')) {
            return false;
        }

        return ! str_contains($request->url(), $minted->transaction_id);
    });
    expect($response->headers->get('Location'))->toContain($minted->transaction_id);
});

it('fails open when the checkout endpoint is merely unreachable', function () {
    fakeGateway(
        Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404),
        fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'),
    );

    $this->post(route('subscribe.store'), signupPayload())->assertSessionMissing('error');
});

it('skips the handoff probe when it is switched off', function () {
    config()->set('services.khqrpay.handoff_preflight', false);
    fakeGateway(
        Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404),
        Http::response(['responseCode' => 1, 'responseMessage' => 'Bakong Token Required'], 200),
    );

    $this->post(route('subscribe.store'), signupPayload())->assertSessionMissing('error');

    Http::assertSentCount(1);
});

it('does not cache a healthy verdict off a probe that never answered', function () {
    // An unknown outcome is let through, but it must not silence the next check
    // — otherwise one timeout buys a broken gateway a free minute of handoffs.
    fakeGateway(
        Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404),
        fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'),
    );

    $this->post(route('subscribe.store'), signupPayload(['phone' => '0999000111']));

    fakeGateway(
        Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404),
        Http::response(['responseCode' => 1, 'responseMessage' => 'Bakong Token Required'], 200),
    );

    $this->post(route('subscribe.store'), signupPayload(['phone' => '0999000222']))
        ->assertSessionHas('error', __('messages.subscription_gateway_unavailable'));
});

/*
|--------------------------------------------------------------------------
| The diagnostics popup
|--------------------------------------------------------------------------
|
| One sentence is all the customer-facing flash can say. Whoever has to FIX
| the profile needs to know WHICH part refused and in whose words.
*/

it('reports which check failed, in the gateway\'s own words', function () {
    fakeGateway(
        Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404),
        Http::response([
            'responseCode' => 1,
            'responseMessage' => 'Bakong Token Required: No active official Bakong OpenAPI token configured.',
        ], 200),
    );
    $this->actingAs(makeAdmin());

    $report = $this->getJson(route('admin.billing.diagnostics'))->assertOk()->json();

    expect($report['healthy'])->toBeFalse();

    $byKey = collect($report['checks'])->keyBy('key');
    expect($byKey['credentials']['state'])->toBe('ok');
    expect($byKey['profile']['state'])->toBe('ok');
    // The one that is actually broken, named and quoted.
    expect($byKey['handoff']['state'])->toBe('fail');
    expect($byKey['handoff']['detail'])->toContain('Bakong Token Required');
});

it('names the missing credential instead of probing with blank ones', function () {
    \App\Models\PlatformPaymentSetting::query()->delete();
    fakeGateway(Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404));
    $this->actingAs(makeAdmin());

    $report = $this->getJson(route('admin.billing.diagnostics'))->assertOk()->json();

    expect($report['healthy'])->toBeFalse();
    expect(collect($report['checks'])->firstWhere('key', 'credentials')['state'])->toBe('fail');
    // Nothing to sign with, so nothing was sent — the probe would only 404.
    Http::assertNothingSent();
});

it('shows the last refusal beside a report that has since gone green', function () {
    // The gateway is allowed to answer differently a minute later. An all-green
    // report in front of someone staring at the failure is worse than none.
    fakeGateway(
        Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404),
        Http::response(['responseCode' => 1, 'responseMessage' => 'Bakong Token Required'], 200),
    );
    $admin = makeAdmin();
    $this->actingAs($admin);
    $this->post(route('admin.billing.renew'), ['plan' => 'pro', 'billing_cycle' => 'monthly']);

    // …and now it behaves.
    fakeGateway(Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404));

    $report = $this->getJson(route('admin.billing.diagnostics'))->assertOk()->json();

    expect($report['healthy'])->toBeTrue();
    expect($report['last_fault']['probe'])->toBe('handoff');
    expect($report['last_fault']['message'])->toContain('Bakong Token Required');
});

it('keeps the gateway internals off the public signup form', function () {
    fakeGateway(Http::response('Bad Gateway', 502));

    $this->post(route('subscribe.store'), signupPayload());

    // The guest popup renders with no endpoint, so the form has no way to reach
    // the diagnostics route — and the route itself is behind auth.
    $page = $this->get(route('subscribe.create'));
    $page->assertOk();
    $page->assertDontSee('billing/diagnostics');
    $page->assertSee(__('messages.khqr_diag_guest_no_charge'));

    $this->get(route('admin.billing.diagnostics'))->assertRedirect(route('login'));
});
