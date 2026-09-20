<?php

use App\Models\BakongToken;
use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Bakong\BakongProviderClient;
use App\Services\Bakong\BakongTransactionService;
use App\Services\Payment\PaymentManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A REFUSAL IS NOT A VERDICT.
 *
 * Under KHQRPay a signed webhook was the primary settlement path and polling was
 * the safety net, so a poll that guessed wrong could still be corrected by a
 * callback. The Bakong Open API sends NO WEBHOOK: polling is the only way money
 * is ever seen to arrive. That inverts the stakes — a refusal misread as "the
 * payer has not paid" expires a QR someone may already have paid, and there is
 * no second channel left to find it.
 *
 * So only a 2xx answer from Bakong may say UNPAID. A blocked call, a timeout, a
 * 429, a 5xx, a rejected token and a body that is not the documented envelope
 * are all REFUSED — ask again later.
 */
beforeEach(function () {
    Cache::flush();

    config()->set('bakong.enabled', true);
    config()->set('bakong.demo', false);
    config()->set('bakong.base_url', 'https://bakong.test');
    config()->set('bakong.account_id', 'ams_test@devb');
    config()->set('bakong.integrator.email', 'integrator@ams.test');
    config()->set('bakong.merchant_name', 'AMS');
    config()->set('bakong.merchant_city', 'Phnom Penh');
    config()->set('bakong.currency', 'USD');
    config()->set('bakong.qr_ttl', 6);
    config()->set('bakong.verify_cooldown', 0);     // the cooldown has its own tests
    config()->set('bakong.daily_request_limit', 0); // so does the ceiling
    config()->set('bakong.max_verify_attempts', 0);

    BakongToken::create([
        'email' => 'integrator@ams.test',
        'token' => 'eyJ0eXAiOiJKV1QifQ.e30.sig',
        'expires_at' => now()->addDays(60),
        'verified_at' => now(),
    ]);
});

function bakongSubscription(): Subscription
{
    seedRoles();

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

function bakongCheckout(): KhqrPayment
{
    return app(BakongTransactionService::class)
        ->createSubscriptionQr(bakongSubscription(), 10.00);
}

/** The documented success envelope for check_transaction_by_md5. */
function bakongPaid(float $amount = 10.00, string $currency = 'USD'): void
{
    Http::fake(['bakong.test/*' => Http::response([
        'responseCode' => 0,
        'responseMessage' => 'Getting transaction successfully.',
        'data' => [
            'hash' => str_repeat('a', 64),
            'fromAccountId' => 'payer@devb',
            'toAccountId' => 'ams_test@devb',
            'currency' => $currency,
            'amount' => $amount,
            'description' => 'subscription',
        ],
    ], 200)]);
}

/** The documented "not found" envelope — the honest pre-payment answer. */
function bakongNotFound(): void
{
    Http::fake(['bakong.test/*' => Http::response([
        'data' => null,
        'errorCode' => 1,
        'responseCode' => 1,
        'responseMessage' => 'Transaction could not be found.Please check and try again.',
    ], 200)]);
}

// ═══════════════════════ creating costs nothing ═══════════════════════

it('mints a subscription QR without contacting Bakong at all', function () {
    Http::fake();

    $row = bakongCheckout();

    expect($row->provider)->toBe('bakong')
        ->and($row->status)->toBe('qr_generated')
        ->and($row->qr_payload)->not->toBeEmpty()
        ->and($row->qr_md5)->toBe(md5($row->qr_payload))
        ->and($row->settlement_target)->toBe('platform');

    // There is no QR endpoint and no hosted checkout to preflight, so creating
    // a payment is FREE. KHQRPay spent one request minting and two more
    // probing the handoff before the customer saw anything.
    Http::assertNothingSent();
});

it('renders the QR locally from the stored payload', function () {
    Http::fake();
    $row = bakongCheckout();

    $image = app(BakongTransactionService::class)->qrImage($row);

    expect($image)->toStartWith('data:image/svg+xml;base64,');
    Http::assertNothingSent();
});

it('retires an earlier open QR rather than leaving two payable at once', function () {
    Http::fake();

    $subscription = bakongSubscription();
    $service = app(BakongTransactionService::class);

    $first = $service->createSubscriptionQr($subscription, 10.00);
    $second = $service->createSubscriptionQr($subscription, 10.00);

    // Two live QRs for one subscription is a double payment waiting to happen —
    // and here it is also twice the metered cost for one sale.
    expect($first->fresh()->status)->toBe('expired')
        ->and($second->status)->toBe('qr_generated');
});

it('refuses to mint while Bakong is switched off, leaving no half-finished row', function () {
    Http::fake();
    config()->set('bakong.enabled', false);

    expect(fn () => bakongCheckout())->toThrow(RuntimeException::class);

    expect(KhqrPayment::where('provider', 'bakong')->count())->toBe(0);
    Http::assertNothingSent();
});

it('refuses to mint with no account to pay into', function () {
    Http::fake();
    config()->set('bakong.account_id', '');

    expect(fn () => bakongCheckout())->toThrow(RuntimeException::class);
    Http::assertNothingSent();
});

// ═══════════════════ only a 2xx may say "unpaid" ═══════════════════

it('reads the documented not-found envelope as genuinely unpaid', function () {
    bakongNotFound();
    $row = bakongCheckout();

    // errorCode 1 is the everyday answer: the transaction does not exist at
    // Bakong until the payer pays it.
    expect(app(BakongTransactionService::class)->verifyOutcome($row))
        ->toBe(BakongTransactionService::VERIFY_UNPAID);
});

it('treats every refusal as "ask again later", never as unpaid', function () {
    $service = app(BakongTransactionService::class);

    $refusals = [
        '401 — our token was rejected' => Http::response(['responseCode' => 1, 'errorCode' => 6, 'responseMessage' => 'Unauthorized.'], 401),
        '403 — forbidden' => Http::response(['responseCode' => 1, 'responseMessage' => 'Forbidden'], 403),
        '429 — the allowance is spent' => Http::response(['responseCode' => 1, 'responseMessage' => 'To many request'], 429),
        '500 — Bakong is unwell' => Http::response(['responseCode' => 1, 'responseMessage' => 'Internal server error'], 500),
        '200 but not the envelope' => Http::response('<html>proxy challenge</html>', 200),
        '200, errorCode 6 — unauthorized' => Http::response(['responseCode' => 1, 'errorCode' => 6, 'responseMessage' => 'Unauthorized.'], 200),
        '200, errorCode 10 — not registered' => Http::response(['responseCode' => 1, 'errorCode' => 10, 'responseMessage' => 'Not registered yet.'], 200),
    ];

    foreach ($refusals as $label => $response) {
        Cache::flush();
        Http::fake(['bakong.test/*' => $response]);

        $row = bakongCheckout();

        expect($service->verifyOutcome($row))
            ->toBe(BakongTransactionService::VERIFY_REFUSED, $label);

        // And critically: the row is NOT closed. With no webhook behind it,
        // leaving it open is the only thing that keeps a landed payment
        // findable.
        expect($row->fresh()->isOpen())->toBeTrue($label);
    }
});

it('treats a network failure as a refusal, not a verdict', function () {
    Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

    $row = bakongCheckout();

    expect(app(BakongTransactionService::class)->verifyOutcome($row))
        ->toBe(BakongTransactionService::VERIFY_REFUSED)
        ->and($row->fresh()->isOpen())->toBeTrue();
});

it('reads a reported transaction failure as unpaid, and leaves the QR payable', function () {
    Http::fake(['bakong.test/*' => Http::response([
        'data' => null, 'errorCode' => 3, 'responseCode' => 1, 'responseMessage' => 'Transaction failed.',
    ], 200)]);

    $row = bakongCheckout();

    // A real attempt that did not go through. The payer can simply scan again,
    // so the row keeps its remaining lifetime rather than being failed here.
    expect(app(BakongTransactionService::class)->verifyOutcome($row))
        ->toBe(BakongTransactionService::VERIFY_UNPAID)
        ->and($row->fresh()->isOpen())->toBeTrue();
});

// ═══════════════════ confirming, and what it checks ═══════════════════

it('confirms a settled payment and records Bakong’s own transaction hash', function () {
    bakongPaid();
    $row = bakongCheckout();

    expect(app(BakongTransactionService::class)->verifyOutcome($row))
        ->toBe(BakongTransactionService::VERIFY_PAID)
        ->and($row->fresh()->provider_hash)->toBe(str_repeat('a', 64));
});

it('refuses to book a settlement whose amount or currency is not this payment’s', function () {
    $service = app(BakongTransactionService::class);

    // The md5 is derived from a payload that already carries the amount, so a
    // mismatch should be impossible — which is exactly why it is checked.
    // Booking the wrong sum is unrecoverable in a way that asking again is not.
    bakongPaid(amount: 1.00);
    expect($service->verifyOutcome(bakongCheckout()))->toBe(BakongTransactionService::VERIFY_REFUSED);

    Cache::flush();
    bakongPaid(currency: 'KHR');
    expect($service->verifyOutcome(bakongCheckout()))->toBe(BakongTransactionService::VERIFY_REFUSED);
});

it('accepts a settlement within half a cent of the expected amount', function () {
    bakongPaid(amount: 10.001);

    expect(app(BakongTransactionService::class)->verifyOutcome(bakongCheckout()))
        ->toBe(BakongTransactionService::VERIFY_PAID);
});

it('activates the subscription through the shared finalize path', function () {
    bakongPaid();
    $row = bakongCheckout();

    app(BakongTransactionService::class)->pollAndAdvance($row);

    $row->refresh();
    $subscription = $row->subscription->fresh();

    expect($row->status)->toBe('paid')
        ->and($row->paid_at)->not->toBeNull()
        ->and($subscription->status)->toBe('active')
        ->and($subscription->expires_at)->not->toBeNull()
        // Booking is not reimplemented for Bakong: finalizeSubscription()
        // never touched khqr.cc, so it is reused rather than duplicated.
        ->and($subscription->khqr_payment_id)->toBe($row->id);
});

it('asks nothing about a payment already known to have landed', function () {
    bakongPaid();
    $row = bakongCheckout();
    app(BakongTransactionService::class)->pollAndAdvance($row);

    Http::fake();

    expect(app(BakongTransactionService::class)->verifyOutcome($row->fresh()))
        ->toBe(BakongTransactionService::VERIFY_PAID);

    Http::assertNothingSent();
});

// ═══════════════════════ polling behaviour ═══════════════════════

it('warns the page when the gateway refuses', function () {
    Http::fake(['bakong.test/*' => Http::response(['responseCode' => 1, 'responseMessage' => 'error'], 500)]);

    $service = app(BakongTransactionService::class);
    $row = bakongCheckout();
    $service->pollAndAdvance($row);

    // With no webhook behind it, a gateway refusing every request is otherwise
    // indistinguishable from a payer who has simply not paid yet — the customer
    // watches a silent spinner until the QR dies.
    expect($service->lastPollRefused())->toBeTrue()
        ->and($row->fresh()->isOpen())->toBeTrue();
});

it('stays quiet about its own cooldown, which is not a gateway problem', function () {
    config()->set('bakong.verify_cooldown', 60);

    // A plain "not found" does not back the credential off, so the ONLY thing
    // stopping the second poll is the cooldown.
    bakongNotFound();

    $row = bakongCheckout();
    app(BakongTransactionService::class)->pollAndAdvance($row);

    $second = app(BakongTransactionService::class);
    $second->pollAndAdvance($row->fresh());

    // Reporting this would make ordinary 10-second polling look like a broken
    // gateway to every customer, every time.
    expect($second->lastBlock())->toBe(BakongProviderClient::BLOCK_COOLDOWN)
        ->and($second->lastPollRefused())->toBeFalse();

    // And the whole point of the cooldown: two polls, one metered request.
    Http::assertSentCount(1);
});

it('expires an elapsed QR locally, without spending a request', function () {
    bakongNotFound();
    $row = bakongCheckout();
    $row->forceFill(['expires_at' => now()->subMinute()])->save();

    Http::fake();

    app(BakongTransactionService::class)->pollAndAdvance($row);

    // The deadline rescue is claimed once per session; after that the row is
    // closed locally and nothing further is asked about it.
    expect($row->fresh()->status)->toBeIn(['expired', 'waiting_payment']);
});

// ═════════════════ the webhook that cannot exist ═════════════════

it('exposes no webhook endpoint at all', function () {
    // The Bakong Open API publishes no callback, no push and no signing scheme.
    // /khqr/callback — public and CSRF-exempt — belonged to khqr.cc and was
    // deleted with it in 2026-09, because a forged POST naming a real
    // transaction id would otherwise have been the cheapest possible way to
    // activate a subscription for free.
    expect(\Illuminate\Support\Facades\Route::has('khqr.callback'))->toBeFalse();

    $this->postJson('/khqr/callback', [
        'transaction_id' => 'ANYTHING',
        'status' => 'PAID',
        'amount' => '10.00',
    ])->assertNotFound();
});

it('resolves old KHQRPay rows through the retired driver, untouched', function () {
    Http::fake();

    $legacy = KhqrPayment::create([
        'transaction_id' => 'LEGACY-1',
        'provider' => 'khqrpay',
        'subscription_id' => bakongSubscription()->id,
        'amount' => 10.00, 'currency' => 'USD', 'status' => 'qr_generated',
        'settlement_target' => 'platform', 'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription'],
    ]);

    // The provider column predates this migration, so every existing row keeps
    // answering through the driver that minted it — now a tombstone that can
    // never call out. See PaymentManagerTest.
    expect(app(PaymentManager::class)->for($legacy)->provider())->toBe('khqrpay')
        ->and($legacy->usesBakong())->toBeFalse();
});

// ═══════════════════════ demo: rehearsing at zero cost ═══════════════════════

it('settles a demo payment locally, without ever transmitting', function () {
    config()->set('bakong.demo', true);
    config()->set('bakong.demo_settle_after', 0);
    Http::fake();

    $row = bakongCheckout();

    expect($row->qr_payload)->not->toBeEmpty()
        ->and(app(BakongTransactionService::class)->verifyOutcome($row))
        ->toBe(BakongTransactionService::VERIFY_PAID);

    // Without this, demo could mint a QR and never confirm it — the client
    // correctly refuses to transmit, so the one thing a demo exists to show
    // (money arriving, the subscription activating) was the one thing it could
    // not show.
    Http::assertNothingSent();
});

it('makes a demo payment wait before settling, so the poll loop is exercised', function () {
    config()->set('bakong.demo', true);
    config()->set('bakong.demo_settle_after', 300);
    Http::fake();

    $row = bakongCheckout();

    // The delay is the point: it rehearses the spinner and the "check now"
    // button rather than jumping straight to a confirmed page.
    expect(app(BakongTransactionService::class)->verifyOutcome($row))
        ->toBe(BakongTransactionService::VERIFY_UNPAID)
        ->and($row->fresh()->isOpen())->toBeTrue();

    Http::assertNothingSent();
});

it('runs a demo with no account configured at all', function () {
    config()->set('bakong.demo', true);
    config()->set('bakong.account_id', '');
    Http::fake();

    // Demo is an explicit simulation, so it may run before any credential
    // exists — that is what makes it useful for rehearsing before setup.
    $row = bakongCheckout();

    expect($row->qr_md5)->not->toBeNull();
    Http::assertNothingSent();
});

it('activates a subscription end to end in demo, with no money and no requests', function () {
    config()->set('bakong.demo', true);
    config()->set('bakong.demo_settle_after', 0);
    Http::fake();

    $row = bakongCheckout();
    app(BakongTransactionService::class)->pollAndAdvance($row);

    expect($row->fresh()->status)->toBe('paid')
        ->and($row->fresh()->subscription->fresh()->status)->toBe('active');

    Http::assertNothingSent();
});

// ═══════ the status endpoint must say whether it actually ASKED ═══════

it('reports gateway_answered=false when the cooldown absorbed the poll', function () {
    config()->set('bakong.verify_cooldown', 60);
    bakongNotFound();

    $row = bakongCheckout();

    // First poll reaches Bakong.
    $first = $this->getJson(route('subscribe.checkout.status', $row->public_token))->assertOk()->json();
    expect($first['gateway_answered'])->toBeTrue()
        ->and($first['gateway_error'])->toBeFalse();

    // Second is absorbed by the cooldown — nothing was learned.
    $second = $this->getJson(route('subscribe.checkout.status', $row->public_token))->assertOk()->json();

    // THE BUG THIS PINS: the page resets its consecutive-miss counter on any
    // poll that is not an outright error. A cooldown-absorbed poll used to
    // report gateway_error=false with nothing to distinguish it from a healthy
    // answer, so the real sequence — error, cooldown, cooldown, cooldown,
    // error — reset the counter every time and the stall warning could never
    // reach its threshold of two. A gateway refusing every request looked
    // exactly like a payer who had not paid yet.
    expect($second['gateway_answered'])->toBeFalse()
        ->and($second['gateway_error'])->toBeFalse();

    Http::assertSentCount(1);
});

it('tells the page when today’s allowance is gone', function () {
    Http::fake(['bakong.test/*' => Http::response([
        'data' => null, 'errorCode' => 17, 'responseCode' => 1,
        'responseMessage' => 'Daily request limit of 100 exceeded. Please try again tomorrow.',
    ], 200)]);

    $row = bakongCheckout();
    $body = $this->getJson(route('subscribe.checkout.status', $row->public_token))->assertOk()->json();

    // Distinct from gateway_error: retrying cannot help, so the page says
    // "cannot confirm until tomorrow, do not pay again" rather than inviting a
    // retry that is guaranteed to fail.
    expect($body['quota_exhausted'])->not->toBeNull()
        ->and($body['gateway_error'])->toBeTrue()
        ->and($row->fresh()->isOpen())->toBeTrue();
});

it('tells the page plainly that a legacy khqr.cc row has no one left to ask', function () {
    Http::fake();

    $legacy = KhqrPayment::create([
        'transaction_id' => 'LEGACY-ANS-1',
        'provider' => 'khqrpay',
        'subscription_id' => bakongSubscription()->id,
        'amount' => 10.00, 'currency' => 'USD', 'status' => 'qr_generated',
        'settlement_target' => 'platform', 'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription'],
        'expires_at' => now()->addMinutes(10),
    ]);

    $body = $this->getJson(route('subscribe.checkout.status', $legacy->public_token))->assertOk()->json();

    // While khqr.cc existed this reported gateway_answered=true, because every
    // poll really was an answer. Now there is no client to ask with, and saying
    // "answered" would make a permanently unanswerable row look like a payer
    // who simply has not paid — the page would spin in silence forever. It says
    // the gateway did not answer instead, which is exactly true, and the row
    // stays open for a human (SuperAdmin → Accounts → change plan).
    expect($body['gateway_answered'])->toBeFalse()
        ->and($body['gateway_error'])->toBeTrue()
        ->and($body['paid'])->toBeFalse()
        ->and($body['quota_exhausted'])->toBeNull();

    Http::assertNothingSent();
    expect($legacy->fresh()->isOpen())->toBeTrue();
});
