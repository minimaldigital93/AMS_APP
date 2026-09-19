<?php

use App\Models\BakongApiCall;
use App\Models\BakongToken;
use App\Models\KhqrPayment;
use App\Services\Bakong\BakongProviderClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * THE ZERO-REQUEST GUARANTEE, for the direct Bakong integration.
 *
 * Bakong meters the upstream token per CALENDAR DAY — roughly 100 requests on
 * this account — and charges a REFUSED request exactly like a successful one.
 * And unlike KHQRPay there is no webhook, so the thing that spends the
 * allowance is also the only thing that can confirm a payment: an integration
 * that wastes requests does not merely cost money, it loses the ability to see
 * money arrive.
 *
 * Two rules, and this file is the proof of both:
 *
 *  1. With BAKONG_API_ENABLED=false, NOTHING in this application contacts NBC.
 *     Not a page load, not the scheduler, not a command, not a poll, not a
 *     diagnostic — and not even a request that is otherwise perfectly formed.
 *  2. Every other gate refuses BEFORE the request, not after: a refusal that
 *     arrives after the request has left has already been charged for.
 *
 * Http::fake() with assertNothingSent() is the instrument throughout, so "zero"
 * is asserted against the HTTP layer itself rather than against a flag someone
 * remembered to check.
 */
beforeEach(function () {
    Cache::flush();

    // A catch-all that answers with a VALID Bakong success envelope. Laravel
    // resolves fake stubs in registration order, so a bare Http::fake() here
    // would shadow any per-test stub with an empty body — and an empty body is
    // indistinguishable from a malformed one, which is exactly the distinction
    // several of these tests exist to make.
    Http::fake(['*' => Http::response([
        'data' => null,
        'errorCode' => null,
        'responseCode' => 0,
        'responseMessage' => 'ok',
    ], 200)]);

    config()->set('bakong.enabled', true);
    config()->set('bakong.demo', false);
    config()->set('bakong.base_url', 'https://bakong.test');
    config()->set('bakong.daily_request_limit', 80);
    config()->set('bakong.verify_cooldown', 60);
    config()->set('bakong.max_verify_attempts', 8);
});

/** A token that is usable right now, so the no_token gate is not what blocks. */
function bakongUsableToken(): BakongToken
{
    $exp = base64_encode(json_encode(['exp' => now()->addDays(60)->timestamp]));
    $jwt = 'eyJ0eXAiOiJKV1QifQ.'.rtrim(strtr($exp, '+/', '-_'), '=').'.sig';

    return BakongToken::create([
        'email' => config('bakong.integrator.email'),
        'organization' => 'AMS Test',
        'project' => 'AMS',
        'token' => $jwt,
        'expires_at' => now()->addDays(60),
        'verified_at' => now(),
    ]);
}

$GLOBALS['bakongRowSeq'] = 0;

/** A row that IS an active direct-Bakong payment session. */
function bakongLiveRow(array $overrides = []): KhqrPayment
{
    $payload = '00020101021229190015ams_test@devb520459995303840540510.005802KH5903AMS6010Phnom Penh63040000';

    return KhqrPayment::create(array_merge([
        // Deterministic and collision-proof: these tests build several rows in
        // the same second, and a duplicate transaction_id would fail the unique
        // index in a way that looks like a gate bug.
        'transaction_id' => 'SUB-TEST-'.str_pad((string) (++$GLOBALS['bakongRowSeq']), 6, '0', STR_PAD_LEFT),
        'provider' => 'bakong',
        'subscription_id' => 1,
        'amount' => 10.00,
        'currency' => 'USD',
        'status' => 'qr_generated',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription'],
        'qr_payload' => $payload,
        'qr_md5' => md5($payload),
        'expires_at' => now()->addMinutes(6),
    ], $overrides));
}

function bakongVerify(KhqrPayment $row, int $grace = 0): \App\Services\Bakong\BakongResult
{
    return (new BakongProviderClient)->call(
        reason: BakongProviderClient::REASON_PAYMENT_VERIFICATION,
        endpoint: BakongProviderClient::EP_CHECK_MD5,
        payload: ['md5' => $row->qr_md5],
        row: $row,
        target: 'platform',
        sessionGrace: $grace,
    );
}

// ═══════════════════════════ 1. the master switch ═══════════════════════════

it('sends nothing at all while the master switch is off — whatever the reason', function () {
    bakongUsableToken();
    $row = bakongLiveRow();

    config()->set('bakong.enabled', false);

    expect(BakongProviderClient::featureEnabled())->toBeFalse();
    expect(BakongProviderClient::providerCallsPermitted())->toBeFalse();

    // Every reason the application has, including the ones with no payment row
    // behind them and the operator's own manual diagnostic.
    expect(bakongVerify($row)->blockedReason)->toBe(BakongProviderClient::BLOCK_DISABLED);

    foreach ([
        [BakongProviderClient::REASON_TOKEN_REQUEST, BakongProviderClient::EP_REQUEST_TOKEN],
        [BakongProviderClient::REASON_TOKEN_RENEW, BakongProviderClient::EP_RENEW_TOKEN],
        [BakongProviderClient::REASON_ACCOUNT_CHECK, BakongProviderClient::EP_CHECK_ACCOUNT],
        [BakongProviderClient::REASON_MANUAL_DIAGNOSTIC, BakongProviderClient::EP_CHECK_MD5],
    ] as [$reason, $endpoint]) {
        expect((new BakongProviderClient)->call($reason, $endpoint, ['x' => 1])->blockedReason)
            ->toBe(BakongProviderClient::BLOCK_DISABLED);
    }

    Http::assertNothingSent();
});

it('treats an unset base url as a second off switch', function () {
    bakongUsableToken();
    $row = bakongLiveRow();

    // NBC writes the root as {{baseUrl}} and never publishes it, so a
    // half-configured .env is the ordinary state of a machine mid-setup. The
    // client must refuse rather than construct a request against an empty host.
    config()->set('bakong.base_url', '');

    expect(BakongProviderClient::featureEnabled())->toBeFalse();
    expect(bakongVerify($row)->blockedReason)->toBe(BakongProviderClient::BLOCK_NOT_CONFIGURED);

    Http::assertNothingSent();
});

it('never transmits in demo mode, even though the feature counts as enabled', function () {
    bakongUsableToken();
    $row = bakongLiveRow();

    config()->set('bakong.demo', true);

    // Demo is a local simulation: the flows must run, but nothing may leave.
    expect(BakongProviderClient::featureEnabled())->toBeTrue();
    expect(BakongProviderClient::providerCallsPermitted())->toBeFalse();
    expect(bakongVerify($row)->blockedReason)->toBe(BakongProviderClient::BLOCK_DEMO);

    Http::assertNothingSent();
});

// ═══════════════════════ 2. a row is not a payment ═══════════════════════

it('never asks about a row that is not a live payment session', function () {
    bakongUsableToken();

    $cases = [
        'pending — the QR was never rendered to anyone' => ['status' => 'pending'],
        'terminal — already decided' => ['status' => 'expired'],
        'already paid' => ['status' => 'paid', 'paid_at' => now()],
        'elapsed — the QR is long dead' => ['expires_at' => now()->subHours(3)],
        'no md5 — there is no question that could be asked' => ['qr_md5' => null],
        'manual channel — settled in the landlord’s banking app' => ['channel' => 'manual'],
        'a KHQRPay row — a different gateway’s business' => ['provider' => 'khqrpay'],
    ];

    foreach ($cases as $label => $overrides) {
        $row = bakongLiveRow($overrides);

        expect(bakongVerify($row)->blockedReason)
            ->toBe(BakongProviderClient::BLOCK_NO_ACTIVE_PAYMENT, $label);
    }

    Http::assertNothingSent();
});

it('never asks about a row older than a day, whatever its expiry claims', function () {
    bakongUsableToken();

    $row = bakongLiveRow(['expires_at' => now()->addMinutes(5)]);
    KhqrPayment::whereKey($row->id)->update(['created_at' => now()->subDays(2)]);

    expect(bakongVerify($row->fresh())->blockedReason)
        ->toBe(BakongProviderClient::BLOCK_NO_ACTIVE_PAYMENT);

    Http::assertNothingSent();
});

// ═══════════════════════ 3. unaccountable requests ═══════════════════════

it('refuses a request nobody can account for', function () {
    bakongUsableToken();
    $row = bakongLiveRow();
    $client = new BakongProviderClient;

    // An unknown reason, an endpoint that is not in the published document, an
    // unknown budget, a row-bound reason with no row, and a row whose money
    // settles somewhere other than the budget being charged.
    expect($client->call('whatever', BakongProviderClient::EP_CHECK_MD5, [], $row)->blockedReason)
        ->toBe(BakongProviderClient::BLOCK_INVALID_REQUEST);

    expect($client->call(BakongProviderClient::REASON_ACCOUNT_CHECK, '/v1/not_a_real_endpoint')->blockedReason)
        ->toBe(BakongProviderClient::BLOCK_INVALID_REQUEST);

    expect($client->call(BakongProviderClient::REASON_ACCOUNT_CHECK, BakongProviderClient::EP_CHECK_ACCOUNT, [], null, 'unknown')->blockedReason)
        ->toBe(BakongProviderClient::BLOCK_INVALID_REQUEST);

    expect($client->call(BakongProviderClient::REASON_PAYMENT_VERIFICATION, BakongProviderClient::EP_CHECK_MD5)->blockedReason)
        ->toBe(BakongProviderClient::BLOCK_INVALID_REQUEST);

    expect($client->call(BakongProviderClient::REASON_PAYMENT_VERIFICATION, BakongProviderClient::EP_CHECK_MD5, [], $row, 'merchant')->blockedReason)
        ->toBe(BakongProviderClient::BLOCK_INVALID_REQUEST);

    Http::assertNothingSent();
});

// ═══════════════════════════ 4. no credential ═══════════════════════════

it('does not offer a token it can already see is dead', function () {
    $row = bakongLiveRow();

    // No token row at all.
    expect(bakongVerify($row)->blockedReason)->toBe(BakongProviderClient::BLOCK_NO_TOKEN);

    // An expired one is ABSENT, not sent anyway: Bakong charges a 401 exactly
    // like a sale, so sending it spends the allowance to be told what we knew.
    BakongToken::create([
        'email' => config('bakong.integrator.email'),
        'token' => 'eyJ0eXAiOiJKV1QifQ.e30.sig',
        'expires_at' => now()->subDay(),
        'verified_at' => now()->subDays(90),
    ]);

    expect(bakongVerify($row->fresh())->blockedReason)->toBe(BakongProviderClient::BLOCK_NO_TOKEN);

    // An unverified registration is not a credential either.
    BakongToken::query()->update(['expires_at' => now()->addDays(30), 'verified_at' => null]);

    expect(bakongVerify($row->fresh())->blockedReason)->toBe(BakongProviderClient::BLOCK_NO_TOKEN);

    Http::assertNothingSent();
});

it('does not need a token to ask for one', function () {
    // Requiring a token to obtain a token would deadlock first-time setup, so
    // the three token endpoints are exempt from that gate. They still pass
    // every other gate, which is why this one is allowed through to the fake.
    $result = (new BakongProviderClient)->call(
        BakongProviderClient::REASON_TOKEN_REQUEST,
        BakongProviderClient::EP_REQUEST_TOKEN,
        ['email' => 'a@b.test', 'organization' => 'AMS', 'project' => 'AMS'],
    );

    expect($result->wasBlocked())->toBeFalse()
        ->and($result->succeeded())->toBeTrue();

    Http::assertSentCount(1);
});

// ═══════════════════════ 5. every refusal is recorded ═══════════════════════

it('records every refused attempt, and never counts one against the allowance', function () {
    $row = bakongLiveRow();

    config()->set('bakong.enabled', false);
    bakongVerify($row);

    $blocked = BakongApiCall::where('allowed', false)->get();

    expect($blocked)->toHaveCount(1)
        ->and($blocked->first()->blocked_reason)->toBe(BakongProviderClient::BLOCK_DISABLED)
        ->and($blocked->first()->reason)->toBe(BakongProviderClient::REASON_PAYMENT_VERIFICATION)
        ->and($blocked->first()->endpoint)->toBe(BakongProviderClient::EP_CHECK_MD5)
        ->and($blocked->first()->khqr_payment_id)->toBe($row->id)
        // A call that was blocked cost Bakong nothing. Counting it would let a
        // burst of correctly-refused polls lock out the payment that matters.
        ->and(BakongApiCall::spentOn('platform'))->toBe(0);

    Http::assertNothingSent();
});

it('never writes a token, header or request body into the ledger', function () {
    bakongUsableToken();
    $row = bakongLiveRow();

    bakongVerify($row);

    $columns = array_keys(BakongApiCall::first()->getAttributes());

    expect($columns)->not->toContain('token')
        ->and($columns)->not->toContain('authorization')
        ->and($columns)->not->toContain('payload')
        ->and($columns)->not->toContain('request_body');
});

// ═══════════════ a base url that is not a url is not configuration ═══════════

it('refuses a base url that is not a URL, instead of reporting all-green', function () {
    bakongUsableToken();
    $row = bakongLiveRow();

    // This is not hypothetical. An operator pasted their ACCESS TOKEN into
    // BAKONG_API_BASE_URL; the value was present, the token had separately
    // imported fine, and every check passed — so diagnostics reported "this
    // installation can take a Bakong payment" with no endpoint configured at
    // all. A false green on a payment integration is worse than a red one.
    foreach ([
        'a pasted JWT' => 'eyJhbGciOiJIUzI1NiJ9.eyJkYXRhIjp7fX0.signature',
        'a bare host' => 'api-bakong.nbc.gov.kh',
        'a stray word' => 'todo',
        'the wrong scheme' => 'ftp://api-bakong.nbc.gov.kh',
    ] as $label => $value) {
        config()->set('bakong.base_url', $value);

        expect(BakongProviderClient::baseUrl())->toBeNull($label)
            ->and(BakongProviderClient::featureEnabled())->toBeFalse($label)
            ->and(bakongVerify($row)->blockedReason)
            ->toBe(BakongProviderClient::BLOCK_NOT_CONFIGURED, $label);
    }

    Http::assertNothingSent();
});

it('never echoes a base url it could not parse', function () {
    // The likeliest reason this value is malformed is that a credential was
    // pasted into it, and a diagnostics table that helpfully prints it puts that
    // credential into a terminal scrollback, a screenshot and a support thread.
    $secret = 'eyJhbGciOiJIUzI1NiJ9.eyJkYXRhIjp7ImlkIjoic2VjcmV0In19.signature';
    config()->set('bakong.base_url', $secret);

    expect(BakongProviderClient::baseUrlForDisplay())
        ->not->toContain($secret)
        ->not->toContain('eyJ')
        ->toContain('rotate');

    // A VALID url is not a secret and must still be readable.
    config()->set('bakong.base_url', 'https://api-bakong.example/');
    expect(BakongProviderClient::baseUrlForDisplay())->toBe('https://api-bakong.example');
});

it('accepts a well-formed base url with or without a trailing slash', function () {
    foreach (['https://api-bakong.example', 'https://api-bakong.example/'] as $value) {
        config()->set('bakong.base_url', $value);
        expect(BakongProviderClient::baseUrl())->toBe('https://api-bakong.example');
    }
});
