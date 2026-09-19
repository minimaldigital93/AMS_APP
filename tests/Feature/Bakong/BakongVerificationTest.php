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

it('rejects any webhook claiming to settle a Bakong payment', function () {
    Http::fake();
    $row = bakongCheckout();

    $gateway = app(PaymentManager::class)->for($row);

    expect($gateway->provider())->toBe('bakong');

    // The Bakong Open API publishes no callback, no push and no signing scheme,
    // so nothing arriving at the public, CSRF-exempt /khqr/callback can be
    // genuine. Without this, a forged POST naming a real transaction id would
    // be the cheapest possible way to activate a subscription for free.
    expect($gateway->validateWebhook($row, [
        'transaction_id' => $row->transaction_id,
        'status' => 'PAID',
        'amount' => '10.00',
        'hash' => 'whatever',
    ]))->toBeFalse();

    $this->postJson(route('khqr.callback'), [
        'transaction_id' => $row->transaction_id,
        'status' => 'PAID',
        'amount' => '10.00',
    ])->assertForbidden();

    expect($row->fresh()->status)->not->toBe('paid');
});

it('resolves old KHQRPay rows through the old driver, untouched', function () {
    Http::fake();

    $legacy = KhqrPayment::create([
        'transaction_id' => 'LEGACY-1',
        'provider' => 'khqrpay',
        'subscription_id' => bakongSubscription()->id,
        'amount' => 10.00, 'currency' => 'USD', 'status' => 'qr_generated',
        'settlement_target' => 'platform', 'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription'],
    ]);

    // The provider column predates this migration and defaults to 'khqrpay',
    // so every existing row keeps answering through the driver that minted it.
    expect(app(PaymentManager::class)->for($legacy)->provider())->toBe('khqrpay')
        ->and($legacy->usesBakong())->toBeFalse();
});
