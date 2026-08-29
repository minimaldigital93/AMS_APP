<?php

use App\Models\KhqrPayment;
use App\Models\PlatformPaymentSetting;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * What BOUNDS the metered Bakong allowance — as opposed to KhqrQuotaGuardTest,
 * which pins how a refusal is READ.
 *
 * The allowance was going missing overnight with nobody touching the app. Two
 * leaks, and neither showed up as a bug anywhere:
 *
 *  1. khqr:reconcile re-verified every open API row created in the last DAY.
 *     A QR lives ten minutes, so that is 288 live calls per abandoned checkout,
 *     all of them asking about a QR nobody can pay any more. And since the
 *     profile has no Bakong token, every answer was a refusal — which correctly
 *     never closes the row, so nothing ever took it back out of scope. The
 *     allowance was gone by ~02:30, before anyone was awake.
 *  2. The two checkout preflight probes were not counted against the daily
 *     ceiling and their fault verdict was never cached, so a broken profile
 *     charged two calls for every visit to the billing page — the guard
 *     outspending the payments it guards.
 */
beforeEach(function () {
    // The array store lives for the whole process, so the verify cooldown, the
    // call counter and both preflight verdicts carry over between tests.
    Cache::flush();

    config()->set('services.khqrpay.base_url', 'https://khqr.cc');
    config()->set('services.khqrpay.demo', false);
    config()->set('services.khqrpay.daily_budget', 0);
    config()->set('services.khqrpay.verify_cooldown', 0);
    config()->set('services.khqrpay.handoff_preflight', true);
    config()->set('services.khqrpay.reconcile_grace', 60);
    config()->set('services.khqrpay.reconcile_enabled', true);

    PlatformPaymentSetting::create([
        'khqrpay_profile_id' => 'PROFILE-1',
        'khqrpay_secret' => 'platform-secret',
        'currency' => 'USD',
    ]);

    $this->service = new KhqrPaymentService;
});

function boundRow(string $transactionId, array $overrides = []): KhqrPayment
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

/** The gateway this account actually has: answers, but refuses to transact. */
function refusingGateway(): void
{
    Http::fake(['khqr.cc/*' => Http::response('Bad Gateway', 502)]);
}

/*
|--------------------------------------------------------------------------
| 1. The reconcile window is the quota bound
|--------------------------------------------------------------------------
*/

it('does not spend a request on a QR that died long before the grace window', function () {
    // The shape that drained the allowance: a refused row from earlier today.
    // It was inside the old 24-hour window, so it was re-asked every five
    // minutes for a whole day about a QR that stopped being payable in ten.
    boundRow('DEAD-1', ['expires_at' => now()->subHours(6)]);
    refusingGateway();

    $this->artisan('khqr:reconcile')->assertSuccessful();

    Http::assertNothingSent();
})->group('quota');

it('still verifies a QR that died inside the grace window, so a late payment is not lost', function () {
    // The grace window is not decoration: a payment can land in the last
    // seconds before expiry and its webhook can still fail, and then this run
    // is the only thing that will ever find it. Skipping expired rows outright
    // would have been cheaper and would have dropped exactly those payments.
    boundRow('LATE-1', ['expires_at' => now()->subMinutes(5)]);
    refusingGateway();

    $this->artisan('khqr:reconcile')->assertSuccessful();

    Http::assertSentCount(1);
})->group('quota');

it('still verifies a QR that has not expired yet', function () {
    boundRow('LIVE-1', ['expires_at' => now()->addMinutes(5)]);
    refusingGateway();

    $this->artisan('khqr:reconcile')->assertSuccessful();

    Http::assertSentCount(1);
})->group('quota');

it('keeps refusing to expire a row the gateway would not rule on', function () {
    // The window bounds the SPEND. It must not have quietly bought that by
    // closing rows on no evidence — which is the one thing this command exists
    // not to do.
    boundRow('OPEN-1', ['expires_at' => now()->subMinutes(5)]);
    refusingGateway();

    $this->artisan('khqr:reconcile')->assertSuccessful();

    expect(KhqrPayment::where('transaction_id', 'OPEN-1')->first()->isOpen())->toBeTrue();
})->group('quota');

it('judges a legacy row with no expires_at on its created_at instead', function () {
    // Rows minted before expires_at existed must not fall through the window
    // clause and get re-verified forever on the created_at branch.
    boundRow('LEGACY-OLD', ['expires_at' => null]);
    // created_at is not mass-assignable, so age the row after the fact.
    KhqrPayment::where('transaction_id', 'LEGACY-OLD')->update(['created_at' => now()->subHours(6)]);
    refusingGateway();

    $this->artisan('khqr:reconcile')->assertSuccessful();

    Http::assertNothingSent();
})->group('quota');

it('reports rows that have fallen out of the window instead of hiding them', function () {
    // An open row nobody is verifying any more is also an open row nobody is
    // LOOKING at — which is how two rows sat in qr_generated for 73 days.
    boundRow('STRANDED-1', ['expires_at' => now()->subHours(6)]);
    refusingGateway();

    $this->artisan('khqr:reconcile')
        ->expectsOutputToContain('past the reconcile window')
        ->assertSuccessful();
})->group('quota');

/*
|--------------------------------------------------------------------------
| 2. The preflight probes are metered too
|--------------------------------------------------------------------------
*/

it('counts both checkout preflight probes against the daily budget', function () {
    config()->set('services.khqrpay.daily_budget', 10);
    Http::fake([
        'khqr.cc/api/payment/request/*' => Http::response('<html>KHQR payment</html>', 200),
        'khqr.cc/*' => Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404),
    ]);

    expect($this->service->platformCheckoutFault())->toBeNull();

    Http::assertSentCount(2);
    // Uncounted until 2026-08: khqr:usage under-reported every checkout attempt
    // by two, and the ceiling meant to protect the allowance could be sailed
    // straight past by the probes.
    expect(KhqrPaymentService::providerCallsOn('platform'))->toBe(2);
})->group('quota');

it('caches a refusal briefly so a broken profile is not re-probed on every click', function () {
    refusingGateway();

    expect($this->service->platformCheckoutFault())->toBe(__('messages.subscription_gateway_unavailable'));
    Http::assertSentCount(1); // probe 1 faulted; probe 2 never ran

    // The same answer, at no further cost. Before this, a profile that had been
    // faulting since June charged two calls to every visitor of the billing page.
    expect($this->service->platformCheckoutFault())->toBe(__('messages.subscription_gateway_unavailable'));
    Http::assertSentCount(1);
})->group('quota');

it('stops probing once the allowance is spent, and lets the customer through anyway', function () {
    // The realistic shape: the day's calls went on polling and reconcile, and
    // now a customer arrives at checkout with nothing left in the tank.
    config()->set('services.khqrpay.daily_budget', 1);
    Http::fake(['khqr.cc/*' => Http::response(
        ['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 200,
    )]);

    $this->service->verifyOutcome(boundRow('SPEND-1'));
    Http::assertSentCount(1);

    // Never spend the last of a metered allowance on a health check — and never
    // turn a customer away because the health check could not run. Fails OPEN,
    // like every other unknown verdict in the preflight.
    expect($this->service->platformCheckoutFault())->toBeNull();
    Http::assertSentCount(1);
})->group('quota');

/*
|--------------------------------------------------------------------------
| 3. Diagnostics stays usable when the allowance is gone
|--------------------------------------------------------------------------
*/

it('skips the live probes in diagnostics once the allowance is spent', function () {
    config()->set('services.khqrpay.daily_budget', 1);
    Http::fake(['khqr.cc/*' => Http::response(
        ['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 200,
    )]);

    $this->service->verifyOutcome(boundRow('SPEND-2')); // spends the allowance
    Http::assertSentCount(1);

    $report = $this->service->platformDiagnostics();

    // The usage line above them has already stated the finding; re-confirming it
    // with two more metered calls spends the reserve exactly when it matters.
    Http::assertSentCount(1);
    $probes = collect($report['checks'])->whereIn('key', ['profile', 'handoff']);
    expect($probes)->toHaveCount(2);
    expect($probes->every(fn ($c) => $c['detail'] === __('messages.khqr_diag_probe_skipped')))->toBeTrue();
    expect($report['healthy'])->toBeFalse();
})->group('quota');

it('lets a fresh diagnostics run clear the cached refusal so the next click re-probes', function () {
    refusingGateway();

    $this->service->platformCheckoutFault();
    Http::assertSentCount(1);
    $this->service->platformCheckoutFault(); // cached
    Http::assertSentCount(1);

    // Whoever is reading the report is mid-fix. Making them wait out a cache to
    // learn whether the fix took is precisely what the fault cache must not do.
    $this->service->platformDiagnostics();
    $sentAfterReport = 2; // profile probe faulted, handoff probe still reported

    $this->service->platformCheckoutFault();
    expect(Http::recorded()->count())->toBeGreaterThan($sentAfterReport);
})->group('quota');

/*
|--------------------------------------------------------------------------
| 4. Clearing the backlog costs nothing
|--------------------------------------------------------------------------
*/

it('expires long-abandoned rows without asking the gateway', function () {
    boundRow('ABANDONED-1', ['expires_at' => now()->subDays(3)]);
    Http::fake();

    $this->artisan('khqr:expire-abandoned --force')->assertSuccessful();

    expect(KhqrPayment::where('transaction_id', 'ABANDONED-1')->first()->status)->toBe('expired');
    // Safe to run when the allowance is already gone — which is exactly when
    // the backlog it clears has built up.
    Http::assertNothingSent();
})->group('quota');

it('leaves a recently expired row alone, since reconcile may still rescue it', function () {
    boundRow('RECENT-1', ['expires_at' => now()->subMinutes(30)]);

    $this->artisan('khqr:expire-abandoned --force')->assertSuccessful();

    expect(KhqrPayment::where('transaction_id', 'RECENT-1')->first()->isOpen())->toBeTrue();
})->group('quota');

it('never touches a manual-channel row, which is waiting on the landlord', function () {
    boundRow('MANUAL-1', ['channel' => 'manual', 'expires_at' => now()->subDays(3)]);

    $this->artisan('khqr:expire-abandoned --force')->assertSuccessful();

    expect(KhqrPayment::where('transaction_id', 'MANUAL-1')->first()->isOpen())->toBeTrue();
})->group('quota');

it('changes nothing on a dry run', function () {
    boundRow('DRY-1', ['expires_at' => now()->subDays(3)]);

    $this->artisan('khqr:expire-abandoned --dry-run')->assertSuccessful();

    expect(KhqrPayment::where('transaction_id', 'DRY-1')->first()->isOpen())->toBeTrue();
})->group('quota');
