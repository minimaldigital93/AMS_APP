<?php

use App\Models\KhqrPayment;
use App\Models\Plan;
use App\Models\PlatformPaymentSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\KhqrProviderClient;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * THE ZERO-REQUEST GUARANTEE.
 *
 * Bakong meters the upstream token per CALENDAR DAY — roughly 100 requests on
 * this account — and charges a refused request exactly like a paid one. The
 * allowance was going missing overnight with nobody touching the app, and the
 * cause was never a bug in any single call site: it was the sum of a scheduler,
 * three browser pollers, two preflight probes and a diagnostics page, each
 * individually looking reasonable, all of them reasoning from the same wrong
 * premise — that a KHQR row in the database is a payment worth asking about.
 *
 * Two rules came out of it, and this file is the proof of both:
 *
 *  1. With KHQR_PAY_ENABLED=false, NOTHING in this application contacts khqr.cc
 *     or Bakong. Not a page load, not the scheduler, not a command, not a poll,
 *     not a diagnostic.
 *  2. With it true, a request needs a LIVE PAYMENT SESSION behind it. An old
 *     row, an expired QR, a never-minted QR and a manual bank transfer are all
 *     records, and none of them is a payment.
 *
 * Http::fake() with assertNothingSent() is the instrument throughout: it records
 * every outbound request the process makes, so "zero" is asserted against the
 * HTTP layer itself rather than against a flag someone remembered to check.
 *
 * Sibling files: KhqrQuotaGuardTest pins how a refusal is READ,
 * KhqrQuotaBoundTest pins what BOUNDS the spend once requests are allowed.
 */
beforeEach(function () {
    // The array store lives for the whole process, so the cooldown, the call
    // counter, the attempt cap and both preflight verdicts carry over.
    Cache::flush();

    seedRoles();

    config()->set('services.khqrpay.base_url', 'https://khqr.cc');
    config()->set('services.khqrpay.demo', false);
    config()->set('services.khqrpay.daily_budget', 0);
    config()->set('services.khqrpay.verify_cooldown', 0);
    config()->set('services.khqrpay.max_verify_attempts', 0);
    config()->set('services.khqrpay.handoff_preflight', true);
    config()->set('services.khqrpay.reconcile_grace', 60);
    config()->set('services.khqrpay.reconcile_enabled', true);
    config()->set('services.khqrpay.enabled', true);

    PlatformPaymentSetting::create([
        'khqrpay_profile_id' => 'PROFILE-1',
        'khqrpay_secret' => 'platform-secret',
        'currency' => 'USD',
    ]);

    // Record every outbound request without answering any of them usefully: a
    // test that asserts nothing was sent must not depend on the fake's body.
    Http::fake(['khqr.cc/*' => Http::response([
        'responseCode' => 0, 'responseMessage' => 'Success',
        'data' => ['status' => 'PENDING'],
    ], 200)]);

    $this->service = new KhqrPaymentService;
});

/** Switch the whole feature off, the way production .env does. */
function khqrOff(): void
{
    config()->set('services.khqrpay.enabled', false);
    config()->set('services.khqrpay.demo', false);
}

/**
 * A platform subscription row. Defaults to a LIVE session — minted QR, expiry in
 * the future — so each test only states the way it differs.
 */
function sessionRow(string $transactionId, array $overrides = []): KhqrPayment
{
    return KhqrPayment::create(array_merge([
        'transaction_id' => $transactionId,
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

// ═════════════════════════════════ 1. KHQR disabled

it('makes no provider request from any KHQR surface while the feature is off', function () {
    khqrOff();
    $row = sessionRow('OFF-1');

    // Every surface that can reach the gateway, in one sweep: verification,
    // the browser poll, the checkout preflight, the diagnostics report (even
    // asked for live), the reconcile safety net and the diagnose command.
    expect($this->service->verifyOutcome($row))->toBe(KhqrPaymentService::VERIFY_REFUSED);
    $this->service->pollAndAdvance($row);
    $this->service->platformCheckoutFault();
    $this->service->platformDiagnostics(live: true);
    $this->artisan('khqr:reconcile')->assertSuccessful();
    // Exit 1 is correct and deliberate — KHQR cannot take a payment while it is
    // switched off. What this test is about is that saying so cost nothing.
    $this->artisan('khqr:diagnose', ['--live' => true])->assertExitCode(1);

    Http::assertNothingSent();
});

it('refuses rather than reports unpaid while the feature is off, so nothing is written out of the books', function () {
    khqrOff();
    $row = sessionRow('OFF-2');

    // The distinction the whole money rule rests on. A flag is not evidence
    // about a payer's money: answering "unpaid" here would let the reconcile net
    // expire every open row the moment KHQR was switched off, and any payment
    // that had landed would be written out of the books with no way back.
    expect($this->service->verifyOutcome($row))->toBe(KhqrPaymentService::VERIFY_REFUSED);

    $after = $this->service->pollAndAdvance($row)->refresh();
    expect($after->isOpen())->toBeTrue()
        ->and($after->status)->not->toBe('expired')
        ->and($this->service->lastPollRefused())->toBeTrue();
});

it('keeps the customer on our own page instead of handing them to a gateway we have switched off', function () {
    khqrOff();
    Plan::create(['slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24, 'billing_period_days' => 30, 'is_active' => true]);

    $response = $this->post(route('subscribe.store'), [
        'name' => 'New Owner',
        'phone' => '0999000222',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toContain('khqr.cc');
    $response->assertSessionHas('error', __('messages.khqr_payment_disabled'));

    // Refused before anything was minted — no orphan signup, no orphan QR.
    expect(User::where('phone', '0999000222')->exists())->toBeFalse()
        ->and(KhqrPayment::count())->toBe(0);

    Http::assertNothingSent();
});

it('mints no session at all while the feature is off', function () {
    khqrOff();
    $plan = Plan::create(['slug' => 'basic', 'name' => 'Basic', 'price_usd' => 12, 'billing_period_days' => 30, 'is_active' => true]);
    $owner = makeAdmin();
    $subscription = Subscription::create(['account_id' => $owner->id, 'plan_id' => $plan->id, 'status' => 'pending']);

    // A session that can never be confirmed is worse than no session: it would
    // sit open, show a QR nobody is watching, and be swept by every safety net
    // that looks for open rows.
    expect(fn () => $this->service->createSubscriptionQr($subscription, 12.0, $plan, 'monthly'))
        ->toThrow(\App\Exceptions\KhqrPlatformCredentialsMissingException::class);

    expect(KhqrPayment::count())->toBe(0);
    Http::assertNothingSent();
});

// ═════════════════════════════════ 2. enabled, but nothing active

it('spends nothing on a reconcile run with no KHQR sessions at all', function () {
    $this->artisan('khqr:reconcile')->assertSuccessful();

    Http::assertNothingSent();
});

it('never asks about a row whose QR was never minted', function () {
    // `pending` is an open status, and it was being verified. It means the mint
    // never completed, so no session was ever opened at khqr.cc — these requests
    // asked Bakong about rows that only ever existed in this database.
    $row = sessionRow('PENDING-1', ['status' => 'pending', 'expires_at' => now()->addMinutes(10)]);

    expect($row->isActiveKhqrSession())->toBeFalse();
    expect($this->service->verifyOutcome($row))->toBe(KhqrPaymentService::VERIFY_REFUSED);

    $this->artisan('khqr:reconcile')->assertSuccessful();

    Http::assertNothingSent();
});

it('never asks about a manual-channel row, which the gateway has never heard of', function () {
    $row = sessionRow('MANUAL-1', ['channel' => 'manual', 'settlement_target' => 'merchant']);

    expect($row->isActiveKhqrSession())->toBeFalse();
    $this->service->verifyOutcome($row);
    $this->artisan('khqr:reconcile')->assertSuccessful();

    Http::assertNothingSent();
});

it('never asks about an expired KHQR session', function () {
    // Past its own expiry AND past the reconcile grace, so no rescue is owed.
    $row = sessionRow('DEAD-1', ['expires_at' => now()->subHours(5)]);

    expect($row->isActiveKhqrSession())->toBeFalse()
        ->and($row->isActiveKhqrSession(60))->toBeFalse();

    expect($this->service->verifyOutcome($row))->toBe(KhqrPaymentService::VERIFY_REFUSED);
    $this->service->pollAndAdvance($row);
    $this->artisan('khqr:reconcile')->assertSuccessful();

    Http::assertNothingSent();
});

it('never asks about a terminal row, however recently it died', function () {
    foreach (['expired', 'failed', 'cancelled', 'paid'] as $i => $status) {
        $row = sessionRow('TERMINAL-'.$i, ['status' => $status]);
        expect($row->isActiveKhqrSession())->toBeFalse();
        $this->service->verifyOutcome($row);
    }

    Http::assertNothingSent();
});

it('closes long-abandoned rows without consulting the gateway', function () {
    sessionRow('ABANDONED-1', ['expires_at' => now()->subDays(3)]);

    $this->artisan('khqr:expire-abandoned', ['--force' => true])->assertSuccessful();

    expect(KhqrPayment::where('transaction_id', 'ABANDONED-1')->value('status'))->toBe('expired');
    Http::assertNothingSent();
});

it('keeps the scheduler from even running the reconcile entry while the feature is off', function () {
    khqrOff();

    // Gate one of four. The command gates itself too, and the service under it,
    // and KhqrProviderClient under that — but a scheduled entry whose only
    // purpose is to call a metered provider should not be reached at all. The
    // entry stays visible in `schedule:list` on purpose: a commented-out
    // schedule line is how an off switch becomes a permanent disappearance.
    $reconcile = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command ?? '', 'khqr:reconcile'));

    expect($reconcile)->not->toBeNull()
        ->and($reconcile->filtersPass(app()))->toBeFalse();

    config()->set('services.khqrpay.enabled', true);
    expect($reconcile->filtersPass(app()))->toBeTrue();

    // ...and the reconcile switch is a second, independent skip.
    config()->set('services.khqrpay.reconcile_enabled', false);
    expect($reconcile->filtersPass(app()))->toBeFalse();
});

// ═════════════════════════════════ 3. the one path that IS allowed

it('does spend a request on a live session, which is the whole point', function () {
    $row = sessionRow('LIVE-1');

    expect($row->isActiveKhqrSession())->toBeTrue();
    $this->service->verifyOutcome($row);

    Http::assertSentCount(1);
});

it('still rescues a payment that landed in the last seconds, exactly once per session', function () {
    // The deadline case: the QR has just elapsed and the money may already be
    // in. Expiring the row would shut the webhook out of it (finalize() refuses
    // a closed row), so the rescue is kept — and bounded to ONE call, where it
    // used to be one per poll for as long as a tab stayed open.
    $row = sessionRow('DEADLINE-1', ['expires_at' => now()->subMinutes(2)]);

    // A refusing gateway: the row must stay open (a refusal is not a verdict),
    // which is what leaves the latch as the only bound on the next poll.
    // Http::fake() MERGES stubs and the FIRST match wins, so beforeEach's
    // healthy stub would otherwise answer here — swap the factory to really
    // replace it.
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(['khqr.cc/*' => Http::response('Bad Gateway', 502)]);

    $this->service->pollAndAdvance($row);
    Http::assertSentCount(1);

    Cache::forget('khqr:verify:outcome:DEADLINE-1'); // the cooldown, not the latch
    $this->service->pollAndAdvance($row->refresh());

    // Still one: the second poll found the latch spent and the session dead.
    Http::assertSentCount(1);
    expect($row->refresh()->isOpen())->toBeTrue();
});

it('caps how many live calls one payment session can ever cost', function () {
    config()->set('services.khqrpay.max_verify_attempts', 3);
    $row = sessionRow('CAPPED-1');

    for ($i = 0; $i < 8; $i++) {
        Cache::forget('khqr:verify:outcome:CAPPED-1'); // defeat the cooldown
        $this->service->verifyOutcome($row);
    }

    Http::assertSentCount(3);
    expect(KhqrProviderClient::attemptsFor($row))->toBe(3);
});

// ═════════════════════════════════ 4. disabled mid-flight

it('stops asking about a session that was live when the feature was switched off', function () {
    $row = sessionRow('MIDFLIGHT-1');

    $this->service->verifyOutcome($row);
    Http::assertSentCount(1);

    khqrOff();
    Cache::forget('khqr:verify:outcome:MIDFLIGHT-1');

    expect($this->service->verifyOutcome($row))->toBe(KhqrPaymentService::VERIFY_REFUSED);
    $this->artisan('khqr:reconcile')->assertSuccessful();

    // Still one — the live session earned its call before the switch, and
    // nothing after it.
    Http::assertSentCount(1);
});

// ═════════════════════════════════ 5. diagnostics

it('reports configuration without contacting anyone unless asked', function () {
    $report = $this->service->platformDiagnostics();

    expect($report['live'])->toBeFalse();
    expect(collect($report['checks'])->pluck('key'))->toContain('feature', 'credentials', 'usage', 'webhook');
    Http::assertNothingSent();
});

it('says so, rather than probing, when the feature is off', function () {
    khqrOff();

    $report = $this->service->platformDiagnostics(live: true);

    expect($report['live'])->toBeFalse()
        ->and($report['healthy'])->toBeFalse();

    $feature = collect($report['checks'])->firstWhere('key', 'feature');
    expect($feature['state'])->toBe('warn')
        ->and($feature['detail'])->toBe(__('messages.khqr_diag_feature_off'));

    Http::assertNothingSent();
});

it('does not let a bare GET of the diagnostics route spend a Bakong request', function () {
    $this->actingAs(makeAdmin());

    // Two metered requests must not be spendable by loading a URL — a bookmark,
    // a second tab, an uptime check. The popup's own fetch sends ?live=1.
    $report = $this->getJson(route('admin.billing.diagnostics'))->assertOk()->json();

    expect($report['live'])->toBeFalse();
    Http::assertNothingSent();
});

it('runs khqr:diagnose offline by default', function () {
    $this->artisan('khqr:diagnose')->assertSuccessful();

    Http::assertNothingSent();
});

// ═════════════════════════════════ 6. ordinary AMS usage

it('makes no provider request while doing ordinary money work with KHQR off', function () {
    khqrOff();

    $admin = makeAdmin();
    $period = makeFiscalPeriod($admin);
    $apartment = makeApartment(null, ['apartment_number' => 'Z-1', 'monthly_rent' => 500]);
    $tenant = makeTenant($apartment);
    $rental = makeRental($tenant, $apartment, ['rent_amount' => 500]);
    $rental->forceFill(['account_id' => $admin->id])->save();

    $this->actingAs($admin);

    // The screens that say what money is owed, and a cash collection through
    // the ordinary (non-KHQR) path.
    $this->get(route('admin.dashboard'))->assertOk();
    $this->get(route('admin.revenue_expense.index'))->assertOk();
    $this->get(route('admin.revenue_expense.record_income'))->assertOk();
    $this->get(route('admin.billing.index'))->assertOk();

    $this->post(route('admin.revenue_expense.checkout', $rental->id), [
        'pay_rent' => 1,
        'rent_amount' => 500,
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
        'billing_month' => now()->month,
        'billing_year' => now()->year,
    ]);

    expect($period->fresh())->not->toBeNull();
    Http::assertNothingSent();
});

it('still books a webhook that arrives after the feature was switched off', function () {
    // Inbound, so it costs nothing and must keep working: money that already
    // landed has to reach the books whatever the outbound switch says.
    khqrOff();

    $row = sessionRow('WEBHOOK-1');

    expect($this->service->verifyOutcome($row))->toBe(KhqrPaymentService::VERIFY_REFUSED);

    // The signature check is local arithmetic — no request, and still valid.
    $payload = [
        'transaction_id' => 'WEBHOOK-1',
        'amount' => '24.00',
        'status' => 'SUCCESS',
        'req_time' => now()->format('YmdHis'),
    ];
    $payload['hash'] = hash('sha256', 'platform-secret'.$payload['req_time'].$payload['transaction_id'].$payload['amount'].'SUCCESS');

    expect($this->service->isValidCallbackFor($row, $payload))->toBeTrue();
    Http::assertNothingSent();
});
