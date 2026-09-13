<?php

use App\Models\KhqrPayment;
use App\Models\PlatformPaymentSetting;
use App\Services\Payment\KhqrProviderClient;
use App\Services\RevenueExpense\KhqrCredentials;
use App\Services\RevenueExpense\KhqrPaymentService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * THE PROVIDER REQUEST AUDIT.
 *
 * Every test here counts requests at the HTTP layer (Http::fake records every
 * outbound call the process makes) or, for the multi-process races, at the
 * provider closure itself — never at a flag someone remembered to set.
 *
 * The production configuration is the baseline (KHQRPAY_DAILY_BUDGET=60,
 * KHQRPAY_VERIFY_COOLDOWN=60, KHQRPAY_QR_TTL=10, reconcile off), so each test
 * states only the way it differs.
 *
 * Sibling files: KhqrZeroRequestTest (the zero-request guarantee),
 * KhqrQuotaGuardTest (how a refusal is read), KhqrQuotaBoundTest (what bounds
 * the reconcile window and the preflight).
 */
beforeEach(function () {
    Cache::flush();

    config()->set('services.khqrpay.enabled', true);
    config()->set('services.khqrpay.demo', false);
    config()->set('services.khqrpay.base_url', 'https://khqr.cc');
    config()->set('services.khqrpay.daily_budget', 60);
    config()->set('services.khqrpay.verify_cooldown', 60);
    config()->set('services.khqrpay.qr_ttl', 10);
    config()->set('services.khqrpay.max_verify_attempts', 20);
    config()->set('services.khqrpay.rate_limit_backoff', 5);
    config()->set('services.khqrpay.failure_backoff', 15);
    config()->set('services.khqrpay.reconcile_enabled', false);
    config()->set('services.khqrpay.reconcile_grace', 60);
    config()->set('services.khqrpay.handoff_preflight', true);

    PlatformPaymentSetting::create([
        'khqrpay_profile_id' => 'AUDIT-PROFILE',
        'khqrpay_secret' => 'audit-platform-secret',
        'currency' => 'USD',
    ]);

    auditGateway(Http::response(['responseCode' => 0, 'data' => ['status' => 'PENDING']], 200));

    $this->service = new KhqrPaymentService;
});

/** Replace (not merge) the fake gateway: Http::fake() stubs merge and the first match wins. */
function auditGateway($verify, $handoff = null): void
{
    Http::swap(new Factory);
    Http::fake([
        'khqr.cc/api/payment/request/*' => $handoff ?? Http::response('<html>KHQR payment</html>', 200),
        'khqr.cc/*' => $verify,
    ]);
}

/** A live platform session exactly as a subscription checkout leaves it. */
function auditRow(string $transactionId, array $overrides = []): KhqrPayment
{
    return KhqrPayment::create(array_merge([
        'transaction_id' => $transactionId,
        'subscription_id' => 1,
        'amount' => 24,
        'currency' => 'USD',
        'status' => 'qr_generated',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription'],
        'expires_at' => now()->addMinutes(10),
    ], $overrides));
}

function auditCreds(): KhqrCredentials
{
    return KhqrCredentials::platform();
}

/** A provider closure that records nothing itself — Http::fake does the counting. */
function auditVerifyCall(KhqrPayment $row): Closure
{
    return fn () => Http::asForm()->post('https://khqr.cc/api/AUDIT-PROFILE/payment-gateway/v1/payments/check-transv2-khqrcc', [
        'transaction_id' => $row->transaction_id,
    ]);
}

/** Every log record written from here on, for inspection. */
function auditCaptureLogs(): ArrayObject
{
    $logs = new ArrayObject;
    Log::listen(function (MessageLogged $event) use ($logs) {
        $logs->append(['level' => $event->level, 'message' => $event->message, 'context' => $event->context]);
    });

    return $logs;
}

function auditSeedSpend(string $target, int $spent): void
{
    Cache::put('khqr:calls:'.now()->format('Y-m-d').':'.$target, $spent, now()->addDay());
}

// ═════════════════════════════════ A. KHQR disabled → zero requests

it('A: sends nothing for any reason, row or surface while KHQR_PAY_ENABLED is false', function () {
    config()->set('services.khqrpay.enabled', false);
    $row = auditRow('A-1');
    $client = new KhqrProviderClient;

    // Straight at the gateway, for every reason it knows.
    foreach (KhqrProviderClient::REASONS as $reason) {
        $result = $client->call($reason, 'platform', in_array($reason, ['checkout_preflight', 'manual_diagnostic'], true) ? null : $row, auditVerifyCall($row), auditCreds());
        expect($result->blockedReason)->toBe(KhqrProviderClient::BLOCK_DISABLED);
    }

    // And through every surface that can reach it.
    expect($this->service->verifyOutcome($row))->toBe(KhqrPaymentService::VERIFY_REFUSED);
    $this->getJson(route('subscribe.checkout.status', $row->public_token))->assertOk();
    $this->service->platformCheckoutFault();
    $this->service->platformDiagnostics(live: true);
    $this->artisan('khqr:reconcile')->expectsOutputToContain('No provider requests were made.')->assertSuccessful();
    $this->artisan('khqr:diagnose', ['--live' => true])->assertExitCode(1);

    Http::assertNothingSent();
});

// ═════════════════════════════════ B. active payment → exactly one request

it('B: spends exactly one request on a genuinely active session, and logs who spent it', function () {
    $logs = auditCaptureLogs();
    $row = auditRow('B-1');

    expect($this->service->verifyOutcome($row))->toBe(KhqrPaymentService::VERIFY_UNPAID);

    Http::assertSentCount(1);

    $line = collect($logs)->firstWhere('message', 'KHQR provider request');
    expect($line)->not->toBeNull()
        ->and($line['context'])->toMatchArray([
            'reason' => 'payment_verification',
            'target' => 'platform',
            'transaction_id' => 'B-1',
            'profile' => 'AUDIT-PROFILE',
            'spent_today' => 1,
        ]);

    // Nothing that signs a request may ever reach the log.
    expect(json_encode($logs->getArrayCopy()))->not->toContain('audit-platform-secret')
        ->and(json_encode($logs->getArrayCopy()))->not->toContain(sha1('audit-platform-secretB-1'));
});

// ═════════════════════════════════ C. cooldown

it('C: allows one request per transaction per cooldown window, however it is asked', function () {
    $row = auditRow('C-1');

    $this->service->verifyOutcome($row);                     // request 1 → allowed
    $this->service->verifyOutcome($row);                     // served locally
    Cache::forget('khqr:verify:outcome:C-1');                // even with the local verdict gone —
    (new KhqrPaymentService)->verifyOutcome($row->fresh());  // another worker, no shared memory
    Http::assertSentCount(1);

    $this->travel(61)->seconds();

    $this->service->verifyOutcome($row->fresh());            // request 4 → allowed again
    Http::assertSentCount(2);
    expect(KhqrPaymentService::providerCallsOn('platform'))->toBe(2);
});

it('C: counts nothing against the budget for a request the cooldown refused', function () {
    $row = auditRow('C-2');
    $client = new KhqrProviderClient;

    $first = $client->call('payment_verification', 'platform', $row, auditVerifyCall($row), auditCreds());
    $second = $client->call('payment_verification', 'platform', $row, auditVerifyCall($row), auditCreds());

    expect($first->wasBlocked())->toBeFalse()
        ->and($second->blockedReason)->toBe(KhqrProviderClient::BLOCK_COOLDOWN)
        ->and(KhqrPaymentService::providerCallsOn('platform'))->toBe(1)
        ->and(KhqrProviderClient::attemptsFor($row))->toBe(1);
    Http::assertSentCount(1);
});

// ═════════════════════════════════ D. concurrent verification

it('D: lets only one of two overlapping verifications reach the provider', function () {
    $row = auditRow('D-1');
    $depth = 0;
    $overlapping = null;

    // While the first request is still in flight, a second tab / PHP-FPM worker
    // asks about the same transaction. The old check-then-act cooldown had
    // written nothing yet at this point, so the second request went out too.
    Http::swap(new Factory);
    Http::fake(function () use (&$depth, &$overlapping, $row) {
        if ($depth++ === 0) {
            $overlapping = (new KhqrPaymentService)->verifyOutcome($row->fresh());
        }

        return Http::response(['responseCode' => 0, 'data' => ['status' => 'PENDING']], 200);
    });

    expect($this->service->verifyOutcome($row))->toBe(KhqrPaymentService::VERIFY_UNPAID);

    Http::assertSentCount(1);
    expect($overlapping)->toBe(KhqrPaymentService::VERIFY_REFUSED);
});

it('D: keeps the in-flight guard even with the cooldown switched off', function () {
    config()->set('services.khqrpay.verify_cooldown', 0);
    $row = auditRow('D-2');
    $depth = 0;

    Http::swap(new Factory);
    Http::fake(function () use (&$depth, $row) {
        if ($depth++ === 0) {
            (new KhqrPaymentService)->verifyOutcome($row->fresh());
        }

        return Http::response(['responseCode' => 0, 'data' => ['status' => 'PENDING']], 200);
    });

    $this->service->verifyOutcome($row);
    Http::assertSentCount(1);

    // …and hands the slot back once the request is over.
    $this->service->verifyOutcome($row->fresh());
    Http::assertSentCount(2);
});

it('D: makes exactly one request when several processes verify the same transaction at the same instant', function () {
    if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
        $this->markTestSkipped('pcntl/posix are needed to race real processes.');
    }

    config()->set('services.khqrpay.daily_budget', 0);
    $row = auditRow('D-FORK-1');
    $creds = auditCreds();

    $hits = auditRaceInForkedWorkers(8, function (string $hitsFile) use ($row, $creds) {
        (new KhqrProviderClient)->call('payment_verification', 'platform', $row, function () use ($hitsFile) {
            file_put_contents($hitsFile, "hit\n", FILE_APPEND | LOCK_EX);
            usleep(300_000); // a slow gateway — the others arrive while this is in flight

            return auditProviderResponse();
        }, $creds);
    });

    expect($hits)->toBe(1);
});

// ═════════════════════════════════ E. expired payment

it('E: never asks about an expired session', function () {
    $client = new KhqrProviderClient;

    $terminal = auditRow('E-1', ['status' => 'expired']);
    $elapsed = auditRow('E-2', ['expires_at' => now()->subHours(3)]);        // open, but long dead
    $ancient = auditRow('E-3', ['expires_at' => now()->addMinutes(5)]);       // bad expiry on an old row
    KhqrPayment::whereKey($ancient->id)->update(['created_at' => now()->subDays(2)]);

    foreach ([$terminal, $elapsed, $ancient->fresh()] as $row) {
        expect($client->call('payment_verification', 'platform', $row, auditVerifyCall($row), auditCreds())->blockedReason)
            ->toBe(KhqrProviderClient::BLOCK_NO_ACTIVE_PAYMENT);
        $this->service->verifyOutcome($row);
        $this->getJson(route('subscribe.checkout.status', $row->public_token))->assertOk();
    }

    Http::assertNothingSent();
});

// ═════════════════════════════════ F. already paid

it('F: never asks about a paid session — including one the webhook just settled', function () {
    $paid = auditRow('F-1', ['status' => 'paid', 'paid_at' => now()]);

    expect($this->service->verifyOutcome($paid))->toBe(KhqrPaymentService::VERIFY_PAID);
    $this->getJson(route('subscribe.checkout.status', $paid->public_token))->assertOk()->assertJson(['paid' => true]);

    // A row the webhook finalized between two polls: the next poll reads the row.
    $settled = auditRow('F-2');
    $settled->transitionTo(\App\Enums\PaymentStatus::Paid);
    $settled->forceFill(['paid_at' => now()])->save();
    $this->getJson(route('subscribe.checkout.status', $settled->public_token))->assertOk()->assertJson(['paid' => true]);

    Http::assertNothingSent();
});

// ═════════════════════════════════ G. reconcile disabled

it('G: makes no request from khqr:reconcile while KHQRPAY_RECONCILE_ENABLED is false, even with live sessions open', function () {
    auditRow('G-1');
    auditRow('G-2', ['expires_at' => now()->subMinutes(2)]);

    $this->artisan('khqr:reconcile')
        ->expectsOutputToContain('No provider requests were made.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

// ═════════════════════════════════ H. budget exhausted

it('H: sends nothing once the day\'s budget is spent — for any reason', function () {
    auditSeedSpend('platform', 60);
    $row = auditRow('H-1');
    $client = new KhqrProviderClient;

    expect($this->service->verifyOutcome($row))->toBe(KhqrPaymentService::VERIFY_REFUSED);
    expect($client->call('manual_diagnostic', 'platform', null, auditVerifyCall($row), auditCreds())->blockedReason)
        ->toBe(KhqrProviderClient::BLOCK_BUDGET);
    $this->service->platformCheckoutFault();   // fails open, without probing
    $this->service->platformDiagnostics(live: true);

    Http::assertNothingSent();
    expect(KhqrPaymentService::providerCallsOn('platform'))->toBe(60);
});

// ═════════════════════════════════ I. budget concurrency

it('I: does not overshoot the last budget slot when a second request arrives while the first is in flight', function () {
    auditSeedSpend('platform', 59);
    $first = auditRow('I-1');
    $second = auditRow('I-2');
    $depth = 0;

    Http::swap(new Factory);
    Http::fake(function () use (&$depth, $second) {
        if ($depth++ === 0) {
            (new KhqrPaymentService)->verifyOutcome($second);
        }

        return Http::response(['responseCode' => 0, 'data' => ['status' => 'PENDING']], 200);
    });

    $this->service->verifyOutcome($first);

    Http::assertSentCount(1);
    expect(KhqrPaymentService::providerCallsOn('platform'))->toBe(60);
});

it('I: refuses rather than overshoots when the budget ledger cannot be locked', function () {
    // The old reservation caught ANY exception — a lock that timed out under
    // contention included — then counted the call and let it through.
    Cache::extend('audit-broken-lock', fn () => Cache::repository(new class extends ArrayStore
    {
        public function lock($name, $seconds = 0, $owner = null)
        {
            throw new RuntimeException('lock backend unavailable');
        }
    }));
    config()->set('cache.stores.audit-broken-lock', ['driver' => 'audit-broken-lock']);
    Cache::setDefaultDriver('audit-broken-lock');

    $row = auditRow('I-3');

    expect((new KhqrProviderClient)->call('payment_verification', 'platform', $row, auditVerifyCall($row), auditCreds())->blockedReason)
        ->toBe('budget_unavailable');
    Http::assertNothingSent();
});

it('I: never exceeds the budget when many processes race for the last slots', function () {
    if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
        $this->markTestSkipped('pcntl/posix are needed to race real processes.');
    }

    $rows = collect(range(1, 8))->map(fn (int $i) => auditRow("I-FORK-{$i}"))->all();
    $creds = auditCreds();

    $hits = auditRaceInForkedWorkers(8, function (string $hitsFile, int $worker) use ($rows, $creds) {
        (new KhqrProviderClient)->call('payment_verification', 'platform', $rows[$worker], function () use ($hitsFile) {
            file_put_contents($hitsFile, "hit\n", FILE_APPEND | LOCK_EX);
            usleep(100_000);

            return auditProviderResponse();
        }, $creds);
    }, beforeFork: fn () => auditSeedSpend('platform', 58));

    expect($hits)->toBeLessThanOrEqual(2)->toBeGreaterThanOrEqual(1)
        ->and(KhqrPaymentService::providerCallsOn('platform'))->toBeLessThanOrEqual(60);
});

// ═════════════════════════════════ J. provider 429

it('J: backs the whole credential off after a 429 — no request for any row, poll or checkout until it lapses', function () {
    auditGateway(Http::response(['responseCode' => 429, 'responseMessage' => 'Too many requests'], 429));
    $a = auditRow('J-1');
    $b = auditRow('J-2');

    expect($this->service->verifyOutcome($a))->toBe(KhqrPaymentService::VERIFY_REFUSED);
    Http::assertSentCount(1);

    $this->service->verifyOutcome($b);
    $this->getJson(route('subscribe.checkout.status', $b->public_token))->assertOk()->assertJson(['gateway_error' => true]);
    expect($this->service->platformCheckoutFault())->toBe(__('messages.subscription_gateway_unavailable'));
    Http::assertSentCount(1);

    $this->travel(6)->minutes();
    auditGateway(Http::response(['responseCode' => 0, 'data' => ['status' => 'PENDING']], 200));
    $this->service->verifyOutcome(auditRow('J-3'));
    Http::assertSentCount(1); // swap() reset the recorder: this is the one new request
});

// ═════════════════════════════════ K. provider 422 "Bakong Token Required"

it('K: reads a 422 "Bakong Token Required" as a safe refusal and does not retry it into a storm', function () {
    $logs = auditCaptureLogs();
    auditGateway(Http::response([
        'responseCode' => 1,
        'responseMessage' => 'Bakong Token Required: No active official Bakong OpenAPI token configured.',
    ], 422));
    $row = auditRow('K-1');

    expect($this->service->verifyOutcome($row))->toBe(KhqrPaymentService::VERIFY_REFUSED);
    Http::assertSentCount(1);

    // Ten minutes of a customer's tab polling every 10s, a second checkout on
    // the same token, and three signups trying to start a checkout.
    $other = auditRow('K-2');
    for ($tick = 0; $tick < 60; $tick++) {
        $this->getJson(route('subscribe.checkout.status', $row->public_token))->assertOk();
        $this->service->verifyOutcome($other->fresh());
        if ($tick % 20 === 0) {
            expect($this->service->platformCheckoutFault())->toBe(__('messages.subscription_gateway_unavailable'));
        }
        $this->travel(10)->seconds();
    }

    Http::assertSentCount(1);
    expect($row->fresh()->isOpen())->toBeTrue(); // a refusal never closes a row

    // Logged once, in the gateway's words, with nothing that signs a request.
    $engaged = collect($logs)->where('message', 'KHQR provider backoff engaged');
    expect($engaged)->toHaveCount(1)
        ->and($engaged->first()['context']['http_status'])->toBe(422)
        ->and($engaged->first()['context']['message'])->toContain('Bakong Token Required');
    expect(json_encode($logs->getArrayCopy()))->not->toContain('audit-platform-secret');
});

it('K: shows the backoff in the free offline report, so the auto-opened popup needs no probe', function () {
    auditGateway(Http::response(['responseCode' => 1, 'responseMessage' => 'Bakong Token Required'], 422));
    $this->service->verifyOutcome(auditRow('K-3'));
    Http::assertSentCount(1);

    seedRoles();
    $this->actingAs(makeAdmin());
    $report = $this->getJson(route('admin.billing.diagnostics'))->assertOk()->json();

    $backoff = collect($report['checks'])->firstWhere('key', 'backoff');
    expect($report['live'])->toBeFalse()
        ->and($backoff['state'])->toBe('fail')
        ->and($backoff['detail'])->toContain('Bakong Token Required');
    Http::assertSentCount(1);
});

it('K: never lets the billing page\'s auto-opened popup probe live', function () {
    seedRoles();
    $this->actingAs(makeAdmin());

    $page = $this->withSession(['khqr_fault' => true, 'error' => 'refused'])->get(route('admin.billing.index'));

    $page->assertOk();
    // Opening reads the offline report; only the labelled button sends live=1.
    $page->assertSee('this.run(false)', false);
    $page->assertSee('x-on:click="run(true)"', false);
    $page->assertSee(__('messages.khqr_diag_recheck'));
    Http::assertNothingSent();
});

it('K: reads a malformed 2xx as a refusal, never as unpaid', function () {
    auditGateway(Http::response('<html>Service temporarily unavailable</html>', 200));
    $row = auditRow('K-4', ['expires_at' => now()->subSeconds(5)]);

    // Just past its deadline: an UNPAID here would expire a row whose money may
    // have landed; an unreadable answer is not that verdict.
    $after = $this->service->pollAndAdvance($row);

    expect($after->isOpen())->toBeTrue()
        ->and($this->service->lastPollRefused())->toBeTrue();
});

it('K: does not retry a timeout immediately', function () {
    Http::swap(new Factory);
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out for https://khqr.cc/api/payment/request/AUDIT-PROFILE?transaction_id=x&hash=deadbeefcafe'));
    $logs = auditCaptureLogs();
    $row = auditRow('K-5');

    expect($this->service->verifyOutcome($row))->toBe(KhqrPaymentService::VERIFY_REFUSED);
    Cache::forget('khqr:verify:outcome:K-5');
    $this->service->verifyOutcome($row->fresh());

    expect(Http::recorded())->toHaveCount(0); // a thrown fake records nothing…
    expect(KhqrPaymentService::providerCallsOn('platform'))->toBe(1); // …but the budget saw exactly one attempt
    expect(json_encode($logs->getArrayCopy()))->not->toContain('deadbeefcafe');
});

// ═════════════════════════════════ L. browser polling

it('L: many browser polls from several tabs cost one provider request per cooldown window', function () {
    $row = auditRow('L-1');
    $url = route('subscribe.checkout.status', $row->public_token);

    // Two tabs, polling every 10s, for 50 seconds.
    for ($tick = 0; $tick < 5; $tick++) {
        $this->getJson($url)->assertOk()->assertJson(['paid' => false, 'gateway_error' => false]);
        $this->getJson($url)->assertOk()->assertJson(['paid' => false, 'gateway_error' => false]);
        $this->travel(10)->seconds();
    }
    Http::assertSentCount(1);

    $this->travel(11)->seconds(); // 61s after the first request
    $this->getJson($url)->assertOk();
    Http::assertSentCount(2);
});

it('L: does not warn the customer about a poll that merely landed inside the cooldown', function () {
    $row = auditRow('L-2');

    // Another worker holds the slot and has not answered yet: this poll has
    // nothing new to say, which is not a gateway error.
    Cache::add('khqr:provider:verify-slot:L-2', time(), 60);

    $this->getJson(route('subscribe.checkout.status', $row->public_token))
        ->assertOk()
        ->assertJson(['paid' => false, 'gateway_error' => false]);
    Http::assertNothingSent();
});

// ═════════════════════════════════ what may never be asked at all

it('refuses a request it cannot account for: unknown reason, unknown target, missing row, blank credentials', function () {
    $client = new KhqrProviderClient;
    $row = auditRow('X-1');
    // The column is NOT NULL today; the rule must not depend on that staying so.
    $orphanTarget = (new KhqrPayment)->forceFill(array_merge(auditRow('X-2')->getAttributes(), ['settlement_target' => null]));
    $blank = new KhqrCredentials('', '', 'https://khqr.cc', 'USD');

    expect($client->call('because', 'platform', $row, auditVerifyCall($row), auditCreds())->blockedReason)->toBe('invalid_request')
        ->and($client->call('payment_verification', 'unknown', $row, auditVerifyCall($row), auditCreds())->blockedReason)->toBe('invalid_request')
        ->and($client->call('payment_verification', 'platform', $orphanTarget, auditVerifyCall($row), auditCreds())->blockedReason)->toBe('invalid_request')
        ->and($client->call('payment_verification', 'merchant', $row, auditVerifyCall($row), auditCreds())->blockedReason)->toBe('invalid_request')
        ->and($client->call('payment_verification', 'platform', null, auditVerifyCall($row), auditCreds())->blockedReason)->toBe('invalid_request')
        ->and($client->call('payment_verification', 'platform', $row, auditVerifyCall($row), $blank)->blockedReason)->toBe('invalid_credentials')
        ->and($client->call('payment_verification', 'platform', $row, auditVerifyCall($row), null)->blockedReason)->toBe('invalid_credentials');

    Http::assertNothingSent();
    expect(KhqrPaymentService::providerCallsOn())->toBe(0);
});

it('never treats a bare database row as a payment session', function () {
    $never = [
        'no subscription behind it' => auditRow('R-1', ['subscription_id' => null]),
        'never minted (pending)' => auditRow('R-2', ['status' => 'pending']),
        'manual channel' => auditRow('R-3', ['channel' => 'manual']),
        'stamped paid' => auditRow('R-4', ['paid_at' => now()]),
        'rent row with no QR shown' => auditRow('R-5', ['settlement_target' => 'merchant', 'subscription_id' => null, 'rental_id' => null]),
    ];

    foreach ($never as $why => $row) {
        expect($row->isActiveKhqrSession(60))->toBeFalse("active although: {$why}");
        $this->service->verifyOutcome($row);
    }

    Http::assertNothingSent();
});

it('mints a transaction at most once', function () {
    $row = auditRow('M-1', ['status' => 'pending', 'settlement_target' => 'platform']);
    $client = new KhqrProviderClient;

    $first = $client->call('payment_creation', 'platform', $row, auditVerifyCall($row), auditCreds());
    $again = $client->call('payment_creation', 'platform', $row, auditVerifyCall($row), auditCreds());
    $minted = $client->call('payment_creation', 'platform', auditRow('M-2'), auditVerifyCall($row), auditCreds());

    expect($first->wasBlocked())->toBeFalse()
        ->and($again->blockedReason)->toBe(KhqrProviderClient::BLOCK_COOLDOWN)
        ->and($minted->blockedReason)->toBe(KhqrProviderClient::BLOCK_NO_ACTIVE_PAYMENT); // already qr_generated
    Http::assertSentCount(1);
});

it('reports what spent the allowance, by reason', function () {
    auditRow('U-1');
    $this->service->verifyOutcome(KhqrPayment::where('transaction_id', 'U-1')->first());
    auditGateway(Http::response(['responseCode' => 1, 'responseMessage' => 'Transaction Not Found'], 404));
    $this->service->platformCheckoutFault();

    expect(KhqrProviderClient::callsByReasonOn('platform'))->toMatchArray([
        'payment_verification' => 1,
        'checkout_preflight' => 2,
        'manual_diagnostic' => 0,
    ]);

    $this->artisan('khqr:usage')->expectsOutputToContain('Today by reason')->assertSuccessful();
});

// ═════════════════════════════════ helpers for real-process races

function auditProviderResponse(): Response
{
    return new Response(new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], '{"responseCode":0,"data":{"status":"PENDING"}}'));
}

/**
 * Run $work in $workers forked PHP processes that all start at the same instant
 * against ONE shared cache (a file store, as a database or redis store would be
 * shared by PHP-FPM workers), and return how many of them reached the provider.
 *
 * Children never touch the database and end with SIGKILL, so nothing of the
 * parent test process (open transaction, output buffers, shutdown handlers) is
 * disturbed.
 */
function auditRaceInForkedWorkers(int $workers, Closure $work, ?Closure $beforeFork = null): int
{
    $dir = sys_get_temp_dir().'/khqr-race-'.bin2hex(random_bytes(6));
    mkdir($dir.'/cache', 0777, true);
    $hitsFile = $dir.'/hits';
    touch($hitsFile);
    // The test still reads the shared cache after the race, so clean up when the
    // parent process ends (children are SIGKILLed and never run this). Plain PHP:
    // the application and its facades are gone by then.
    register_shutdown_function(function () use ($dir) {
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($entries as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }
        @rmdir($dir);
    });

    config()->set('cache.stores.khqr-race', ['driver' => 'file', 'path' => $dir.'/cache', 'lock_path' => $dir.'/cache']);
    Cache::setDefaultDriver('khqr-race');
    config()->set('logging.default', 'null');

    if ($beforeFork !== null) {
        $beforeFork();
    }

    $startAt = microtime(true) + 0.3;
    $pids = [];

    for ($i = 0; $i < $workers; $i++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('fork failed');
        }

        if ($pid === 0) {
            try {
                while (microtime(true) < $startAt) {
                    usleep(500);
                }
                $work($hitsFile, $i);
            } catch (\Throwable $e) {
                file_put_contents($dir.'/errors', $e->getMessage()."\n", FILE_APPEND | LOCK_EX);
            }
            posix_kill(posix_getpid(), SIGKILL);
        }

        $pids[] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $errors = is_file($dir.'/errors') ? trim((string) file_get_contents($dir.'/errors')) : '';
    expect($errors)->toBe('', 'a raced worker threw');

    return count(array_filter(explode("\n", (string) file_get_contents($hitsFile))));
}
