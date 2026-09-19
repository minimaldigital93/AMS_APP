<?php

use App\Models\BakongApiCall;
use App\Models\BakongToken;
use App\Models\KhqrPayment;
use App\Services\Bakong\BakongProviderClient;
use App\Services\Bakong\BakongQuotaLedger;
use App\Services\Bakong\BakongTransactionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * WHEN NBC SAYS THE DAY IS OVER, STOP ASKING.
 *
 * This is the bug that cost a real allowance. Bakong answers HTTP 200 with
 * responseCode 1, errorCode 17 and "Daily request limit of 100 exceeded.
 * Please try again tomorrow." — and errorCode 17 is NOT in the v1.0.2
 * document, whose published list stops at 11. Coding strictly to that list
 * meant the refusal fell through to "generic refusal", which neither backed the
 * credential off nor recorded anything, so the app re-asked once per cooldown
 * for the rest of the day. Every one of those retries is charged exactly like a
 * sale.
 *
 * The second half matters just as much: our own ceiling counts what WE spend.
 * This token is shared, so the allowance can be gone while our ledger reads 6
 * of 80. Only the gateway can tell us that, and it only tells us once.
 */
beforeEach(function () {
    Cache::flush();

    config()->set('bakong.enabled', true);
    config()->set('bakong.demo', false);
    config()->set('bakong.base_url', 'https://bakong.test');
    config()->set('bakong.account_id', 'ams_test@devb');
    config()->set('bakong.integrator.email', 'integrator@ams.test');
    config()->set('bakong.daily_request_limit', 80);
    config()->set('bakong.verify_cooldown', 0);
    config()->set('bakong.max_verify_attempts', 0);

    BakongToken::create([
        'email' => 'integrator@ams.test',
        'token' => 'eyJ0eXAiOiJKV1QifQ.e30.sig',
        'expires_at' => now()->addDays(60),
        'verified_at' => now(),
    ]);
});

/** The exact envelope NBC returned. */
function bakongDailyLimitResponse(): void
{
    Http::fake(['bakong.test/*' => Http::response([
        'data' => null,
        'errorCode' => 17,
        'responseCode' => 1,
        'responseMessage' => 'Daily request limit of 100 exceeded. Please try again tomorrow.',
    ], 200)]);
}

function bakongQuotaRow(): KhqrPayment
{
    static $n = 0;
    $payload = '00020101021229170013ams_test@devb5204599953038405405 5.995802KH5903AMS6304ABCD';

    return KhqrPayment::create([
        'transaction_id' => 'SUB-Q-'.(++$n),
        'provider' => 'bakong',
        'subscription_id' => 1,
        'amount' => 5.99,
        'currency' => 'USD',
        'status' => 'qr_generated',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription'],
        'qr_payload' => $payload,
        'qr_md5' => md5($payload.$n),
        'expires_at' => now()->addMinutes(6),
    ]);
}

it('asks exactly once after NBC says the allowance is exhausted', function () {
    bakongDailyLimitResponse();
    $svc = app(BakongTransactionService::class);

    // First poll discovers it and spends one request.
    expect($svc->verifyOutcome(bakongQuotaRow()))->toBe(BakongTransactionService::VERIFY_REFUSED);
    Http::assertSentCount(1);

    // Ten more polls on ten DIFFERENT transactions — the cooldown cannot help
    // here, which is the whole point: only the latch can.
    for ($i = 0; $i < 10; $i++) {
        expect($svc->verifyOutcome(bakongQuotaRow()))->toBe(BakongTransactionService::VERIFY_REFUSED);
    }

    // Still one. Before this fix it was eleven.
    Http::assertSentCount(1);

    expect(BakongApiCall::spentOn('platform'))->toBe(1)
        ->and(BakongApiCall::blockedByReasonOn('platform'))
        ->toHaveKey(BakongProviderClient::BLOCK_UPSTREAM_EXHAUSTED);
});

it('exempts nothing — not a token renewal, not the operator diagnostic', function () {
    bakongDailyLimitResponse();
    app(BakongTransactionService::class)->verifyOutcome(bakongQuotaRow());
    Http::assertSentCount(1);

    $client = new BakongProviderClient;

    // The failure backoff deliberately lets these two through, because they are
    // what FIXES a bad credential. A spent allowance is not a bad credential:
    // both of these would be charged and both would be refused.
    foreach ([
        [BakongProviderClient::REASON_TOKEN_RENEW, BakongProviderClient::EP_RENEW_TOKEN],
        [BakongProviderClient::REASON_MANUAL_DIAGNOSTIC, BakongProviderClient::EP_CHECK_ACCOUNT],
    ] as [$reason, $endpoint]) {
        expect($client->call($reason, $endpoint, ['x' => 1])->blockedReason)
            ->toBe(BakongProviderClient::BLOCK_UPSTREAM_EXHAUSTED, $reason);
    }

    Http::assertSentCount(1);
});

it('recognises the refusal by its words too, not only by errorCode 17', function () {
    // errorCode 17 is undocumented, so it may not be the only code NBC uses for
    // this. Matching the message as well is what stops the next undocumented
    // code costing another day's allowance.
    Http::fake(['bakong.test/*' => Http::response([
        'data' => null, 'errorCode' => 99, 'responseCode' => 1,
        'responseMessage' => 'Rate limit exceeded for this token.',
    ], 200)]);

    app(BakongTransactionService::class)->verifyOutcome(bakongQuotaRow());
    app(BakongTransactionService::class)->verifyOutcome(bakongQuotaRow());

    Http::assertSentCount(1);
});

it('never mistakes a message about one amount for a spent allowance', function () {
    // The needles are phrases, never the bare word "limit". A gateway saying
    // "amount below minimum limit" is describing this request, not the account,
    // and latching on it would shut down checkout on a healthy token.
    expect(BakongProviderClient::isQuotaRefusal('Amount is below the minimum limit for this account'))->toBeFalse()
        ->and(BakongProviderClient::isQuotaRefusal('Transaction could not be found.'))->toBeFalse()
        ->and(BakongProviderClient::isQuotaRefusal('Daily request limit of 100 exceeded.'))->toBeTrue()
        ->and(BakongProviderClient::isQuotaRefusal('Too many requests'))->toBeTrue();
});

it('latches until midnight, not for a few minutes', function () {
    bakongDailyLimitResponse();
    app(BakongTransactionService::class)->verifyOutcome(bakongQuotaRow());

    $state = (new BakongQuotaLedger)->upstreamExhausted('platform');

    // "Please try again tomorrow" is what NBC actually says. A 15-minute
    // failure backoff would resume spending 96 more times before midnight.
    expect($state)->not->toBeNull()
        ->and($state['until']->isSameDay(now()))->toBeTrue()
        ->and($state['until']->gt(now()->addHours(1)) || now()->hour >= 23)->toBeTrue();
});

it('tells the payer the truth instead of "try again"', function () {
    bakongDailyLimitResponse();
    $row = bakongQuotaRow();

    $svc = app(BakongTransactionService::class);
    $svc->verifyOutcome($row);

    // The page must be able to say "cannot confirm until tomorrow, do not pay
    // again" rather than the generic "gateway unreachable", which invites the
    // customer to retry something that cannot work today.
    expect($svc->upstreamExhausted('platform'))->not->toBeNull();
});

it('reports the exhausted allowance in diagnose, separately from our own ceiling', function () {
    bakongDailyLimitResponse();
    app(BakongTransactionService::class)->verifyOutcome(bakongQuotaRow());

    // Our own spend is 1 of 80 — healthy. NBC's is gone. A report that showed
    // only the first would send an operator hunting in the wrong place.
    $this->artisan('bakong:diagnose')
        ->expectsOutputToContain('EXHAUSTED')
        ->assertExitCode(1);
});
