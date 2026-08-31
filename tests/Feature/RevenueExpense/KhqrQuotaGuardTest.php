<?php

use App\Models\KhqrPayment;
use App\Models\PlatformPaymentSetting;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Bakong meters the upstream token per calendar day, and a REFUSED request
 * costs the allowance exactly as much as one that answers. Two failure modes
 * follow, and both used to be invisible to this app:
 *
 *  - it kept calling a gateway that had nothing left to give, spending the rest
 *    of the day discovering that it was out;
 *  - it read every one of those refusals as "the payer has not paid", which is
 *    the reading that expires a QR the payer may already have paid.
 */
beforeEach(function () {
    // The array store lives for the whole process, so the verify cooldown and
    // the daily call counter would otherwise carry over between tests.
    Cache::flush();

    config()->set('services.khqrpay.base_url', 'https://khqr.cc');
    config()->set('services.khqrpay.demo', false);
    config()->set('services.khqrpay.daily_budget', 0);
    config()->set('services.khqrpay.verify_cooldown', 0);

    PlatformPaymentSetting::create([
        'khqrpay_profile_id' => 'profile123',
        'khqrpay_secret' => 'test-secret',
        'currency' => 'USD',
    ]);

    $this->service = new KhqrPaymentService;
});

function quotaRow(string $transactionId, array $overrides = []): KhqrPayment
{
    return KhqrPayment::create(array_merge([
        'transaction_id' => $transactionId,
        'subscription_id' => null,
        'amount' => 24,
        'currency' => 'USD',
        'status' => 'qr_generated',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'provider_ref' => 'deadbeef',
        'checkout_payload' => ['type' => 'subscription'],
        'expires_at' => now()->addMinutes(10),
    ], $overrides));
}

// ---------------------------------------------------------------- 1. budget

it('stops calling the gateway once the day\'s budget for that target is spent', function () {
    config()->set('services.khqrpay.daily_budget', 1);

    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 0, 'responseMessage' => 'Success',
        'data' => ['status' => 'PENDING'],
    ], 200)]);

    // First verify spends the single allowed call and gets a real answer.
    expect($this->service->verifyOutcome(quotaRow('BUDGET-1')))
        ->toBe(KhqrPaymentService::VERIFY_UNPAID);
    Http::assertSentCount(1);

    // The allowance is now gone. The second row must not reach the gateway at
    // all — and must NOT come back "unpaid", which is what would expire it.
    expect($this->service->verifyOutcome(quotaRow('BUDGET-2')))
        ->toBe(KhqrPaymentService::VERIFY_REFUSED);
    Http::assertSentCount(1);
});

it('budgets each settlement target separately', function () {
    config()->set('services.khqrpay.daily_budget', 1);

    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 0, 'responseMessage' => 'Success',
        'data' => ['status' => 'PENDING'],
    ], 200)]);

    $this->service->verifyOutcome(quotaRow('BUDGET-P', ['settlement_target' => 'platform']));
    Http::assertSentCount(1);

    // The SaaS operator's token being spent must not lock out a landlord who
    // signs with their own — they are different tokens with different limits.
    expect(KhqrPaymentService::providerCallsOn('platform'))->toBe(1);
    expect(KhqrPaymentService::providerCallsOn('merchant'))->toBe(0);
});

it('leaves the budget off by default so an untouched deployment is unchanged', function () {
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 0, 'responseMessage' => 'Success',
        'data' => ['status' => 'PENDING'],
    ], 200)]);

    foreach (range(1, 5) as $i) {
        $this->service->verifyOutcome(quotaRow("NOBUDGET-{$i}"));
    }

    Http::assertSentCount(5);
});

// ------------------------------------------------- 2. refused ≠ unpaid

it('reads a rate-limited verify as refused, never as unpaid', function () {
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 429, 'responseMessage' => 'Rate limit exceeded',
    ], 429)]);

    expect($this->service->verifyOutcome(quotaRow('LIMIT-1')))
        ->toBe(KhqrPaymentService::VERIFY_REFUSED);
});

it('backs off every open transaction on a credential after one 429, even with no daily budget set', function () {
    config()->set('services.khqrpay.rate_limit_backoff', 5);

    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 429, 'responseMessage' => 'Rate limit exceeded',
    ], 429)]);

    // First row actually calls the gateway and eats the 429.
    expect($this->service->verifyOutcome(quotaRow('LIMIT-A')))
        ->toBe(KhqrPaymentService::VERIFY_REFUSED);
    Http::assertSentCount(1);

    // A second, unrelated open row on the SAME credential must not spend
    // another call discovering the same thing — it is refused locally.
    expect($this->service->verifyOutcome(quotaRow('LIMIT-B')))
        ->toBe(KhqrPaymentService::VERIFY_REFUSED);
    Http::assertSentCount(1);
});

it('clears the 429 backoff once it expires, letting verify() reach the gateway again', function () {
    config()->set('services.khqrpay.rate_limit_backoff', 5);

    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 429, 'responseMessage' => 'Rate limit exceeded',
    ], 429)]);

    $this->service->verifyOutcome(quotaRow('LIMIT-C'));
    Http::assertSentCount(1);

    // Still inside the backoff window — a second row spends no call.
    $this->service->verifyOutcome(quotaRow('LIMIT-C2'));
    Http::assertSentCount(1);

    $this->travel(6)->minutes();

    // Backoff has lapsed: the next row reaches the gateway again.
    $this->service->verifyOutcome(quotaRow('LIMIT-D'));
    Http::assertSentCount(2);
});

it('reads a quota-worded refusal on a 200 as refused', function () {
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 7, 'responseMessage' => 'Daily limit reached for this token',
    ], 200)]);

    expect($this->service->verifyOutcome(quotaRow('LIMIT-2')))
        ->toBe(KhqrPaymentService::VERIFY_REFUSED);
});

it('still reads a plain transaction-not-found as unpaid', function () {
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 1, 'responseMessage' => 'Transaction Not Found',
    ], 200)]);

    // The everyday pre-payment answer. Turning THIS into a refusal would stop
    // every QR from ever expiring.
    expect($this->service->verifyOutcome(quotaRow('NOTFOUND-1')))
        ->toBe(KhqrPaymentService::VERIFY_UNPAID);
});

it('keeps verify() true only for a confirmed payment', function () {
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 0, 'responseMessage' => 'Success',
        'data' => ['status' => 'PAID', 'amount' => 24, 'currency' => 'USD'],
    ], 200)]);

    expect($this->service->verify(quotaRow('PAID-1')))->toBeTrue();
});

it('does not expire an elapsed QR while the gateway is refusing to answer', function () {
    $row = quotaRow('STALE-1', ['expires_at' => now()->subMinutes(5)]);

    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 429, 'responseMessage' => 'Rate limit exceeded',
    ], 429)]);

    $after = $this->service->pollAndAdvance($row);

    // Expiry is terminal: verify() short-circuits on a closed row, so expiring
    // here on a refusal would mean never looking again — even after the gateway
    // recovers, and even if the money had already landed.
    expect($after->isOpen())->toBeTrue()
        ->and($after->status)->not->toBe('expired')
        ->and($this->service->lastPollRefused())->toBeTrue();
});

it('does expire an elapsed QR once the gateway conclusively says unpaid', function () {
    $row = quotaRow('STALE-2', ['expires_at' => now()->subMinutes(5)]);

    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 1, 'responseMessage' => 'Transaction Not Found',
    ], 200)]);

    $after = $this->service->pollAndAdvance($row);

    expect($after->status)->toBe('expired')
        ->and($this->service->lastPollRefused())->toBeFalse();
});

it('leaves a stale row open in the reconcile safety net when the gateway refuses', function () {
    quotaRow('RECON-1', ['expires_at' => now()->subMinutes(5)]);

    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 429, 'responseMessage' => 'Rate limit exceeded',
    ], 429)]);

    $this->artisan('khqr:reconcile')->assertSuccessful();

    expect(KhqrPayment::where('transaction_id', 'RECON-1')->first()->isOpen())->toBeTrue();
});

// --------------------------------------------- 3. preflight sees a limit

it('blocks the handoff when the profile answers 429', function () {
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 429, 'responseMessage' => 'Rate limit exceeded',
    ], 429)]);

    // Until the classifier learned about limits this fell through to "unknown"
    // and failed open, so the customer was handed to khqr.cc and read the same
    // refusal as raw JSON on someone else's domain.
    expect($this->service->platformCheckoutFault())->not->toBeNull();
});

it('blocks the handoff when the profile names a spent quota', function () {
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 9, 'responseMessage' => 'API quota exceeded for today',
    ], 200)]);

    expect($this->service->platformCheckoutFault())->not->toBeNull();
});

it('does not mistake an amount limit on the throwaway probe for a spent quota', function () {
    Http::fake([
        // The handoff probe asks for 0.01; a gateway objecting to THAT is
        // describing the probe, not the profile. Blocking on it would refuse
        // checkout on a perfectly healthy account — the exact false alarm this
        // guard is written to fail open on.
        'khqr.cc/api/payment/request/*' => Http::response('<html><form>pay</form></html>', 200),
        'khqr.cc/*' => Http::response([
            'responseCode' => 3, 'responseMessage' => 'Amount is below the minimum limit',
        ], 200),
    ]);

    expect($this->service->platformCheckoutFault())->toBeNull();
});

// ------------------------------------------- diagnostics say what to DO

/** Pull one check out of the report by key. */
function diagCheck(array $report, string $key): array
{
    $found = collect($report['checks'])->firstWhere('key', $key);
    expect($found)->not->toBeNull("no '{$key}' check in the report");

    return $found;
}

it('routes a missing-token refusal to the khqr.cc dashboard, naming the profile', function () {
    Http::fake([
        'khqr.cc/api/payment/request/*' => Http::response([
            'responseCode' => 1,
            'responseMessage' => 'Bakong Token Required: No active official Bakong OpenAPI token configured.',
        ], 422),
        'khqr.cc/*' => Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404),
    ]);

    $handoff = diagCheck($this->service->platformDiagnostics(), 'handoff');

    expect($handoff['state'])->toBe('fail')
        // Nothing on our side can clear this one, so the remedy must not send
        // the reader to our own settings page to hunt for a field.
        ->and($handoff['remedy'])->toContain('khqr.cc')
        ->and($handoff['remedy'])->toContain('profile123')
        // A verbatim sentence for khqr.cc support, quoting their own words back.
        ->and($handoff['copy'])->toContain('profile123')
        ->and($handoff['copy'])->toContain('Bakong Token Required');
});

it('routes a spent allowance to waiting, not to a settings change', function () {
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 429, 'responseMessage' => 'Rate limit exceeded',
    ], 429)]);

    $profile = diagCheck($this->service->platformDiagnostics(), 'profile');

    // An allowance clears itself at midnight; there is nothing to fix and no
    // one to email, so it must not offer a support sentence.
    expect($profile['state'])->toBe('fail')
        ->and($profile['remedy'])->toContain('resets')
        ->and($profile['copy'])->toBeNull();
});

it('routes a bad secret to our own payment settings page', function () {
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 1, 'responseMessage' => 'Invalid Security Hash',
    ], 401)]);

    $profile = diagCheck($this->service->platformDiagnostics(), 'profile');

    expect($profile['state'])->toBe('fail')
        ->and($profile['remedy'])->toContain('Payment Settings')
        ->and($profile['copy'])->toBeNull();
});

it('gives a healthy check no remedy and no support sentence', function () {
    Http::fake([
        'khqr.cc/api/payment/request/*' => Http::response('<html><form>pay</form></html>', 200),
        'khqr.cc/*' => Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404),
    ]);

    $profile = diagCheck($this->service->platformDiagnostics(), 'profile');

    expect($profile['state'])->toBe('ok')
        ->and($profile['remedy'])->toBeNull()
        ->and($profile['copy'])->toBeNull();
});

it('reports the day\'s spend against the budget, and fails the check once it is gone', function () {
    config()->set('services.khqrpay.daily_budget', 2);

    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 0, 'responseMessage' => 'Success', 'data' => ['status' => 'PENDING'],
    ], 200)]);

    $this->service->verifyOutcome(quotaRow('USAGE-1'));
    $this->service->verifyOutcome(quotaRow('USAGE-2'));

    // The probes below spend nothing extra against the counter — only
    // queryProviderOutcome() is metered — so the ceiling is exactly reached.
    $usage = diagCheck($this->service->platformDiagnostics(), 'usage');

    expect($usage['state'])->toBe('fail')
        ->and($usage['detail'])->toContain('2')
        ->and($usage['remedy'])->not->toBeNull();
});

it('warns when no daily ceiling is set at all', function () {
    Http::fake(['khqr.cc/*' => Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404)]);

    $usage = diagCheck($this->service->platformDiagnostics(), 'usage');

    expect($usage['state'])->toBe('warn')
        ->and($usage['remedy'])->toContain('KHQRPAY_DAILY_BUDGET');
});

it('offers the webhook URL as a copyable value, since nothing can read it back', function () {
    Http::fake(['khqr.cc/*' => Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404)]);

    $webhook = diagCheck($this->service->platformDiagnostics(), 'webhook');

    expect($webhook['state'])->toBe('info')
        ->and($webhook['copy'])->toBe(route('khqr.callback'));
});
