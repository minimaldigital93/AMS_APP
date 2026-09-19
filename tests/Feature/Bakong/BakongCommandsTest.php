<?php

use App\Models\BakongApiCall;
use App\Models\BakongToken;
use App\Models\KhqrPayment;
use App\Services\Bakong\BakongProviderClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The operator commands, and the rule they all share: A REPORT MUST NOT SPEND
 * THE ALLOWANCE IT IS REPORTING ON.
 *
 * Anyone running these is running them because something is already wrong,
 * which is exactly when the day's requests are scarcest. So `bakong:usage` is
 * entirely offline and `bakong:diagnose` is offline unless explicitly asked for
 * the one live check — and both say so in their own output.
 */
beforeEach(function () {
    Cache::flush();

    config()->set('bakong.enabled', true);
    config()->set('bakong.demo', false);
    config()->set('bakong.base_url', 'https://bakong.test');
    config()->set('bakong.account_id', 'ams_test@devb');
    config()->set('bakong.integrator.email', 'integrator@ams.test');
    config()->set('bakong.integrator.organization', 'AMS');
    config()->set('bakong.integrator.project', 'AMS');
    config()->set('bakong.daily_request_limit', 80);
    config()->set('bakong.reconcile_enabled', true);
    config()->set('bakong.reconcile_grace', 30);
});

function bakongLiveToken(): BakongToken
{
    return BakongToken::create([
        'email' => 'integrator@ams.test',
        'token' => 'eyJ0eXAiOiJKV1QifQ.e30.sig',
        'expires_at' => now()->addDays(60),
        'verified_at' => now(),
    ]);
}

/** A real subscription, so finalize() has something to activate. */
function bakongCmdSubscription(): \App\Models\Subscription
{
    seedRoles();

    $owner = \App\Models\User::factory()->create(['status' => 'inactive']);
    $owner->forceFill(['account_id' => $owner->id])->save();

    $plan = \App\Models\Plan::create([
        'name' => 'Basic', 'slug' => 'basic-'.$owner->id, 'price_usd' => 10,
        'billing_period_days' => 30, 'max_rooms' => 10, 'max_staff' => 2, 'is_active' => true,
    ]);

    return \App\Models\Subscription::create([
        'account_id' => $owner->id, 'plan_id' => $plan->id,
        'status' => 'pending', 'billing_cycle' => 'monthly',
    ]);
}

function bakongOpenRow(array $overrides = []): KhqrPayment
{
    static $n = 0;
    $payload = '00020101021229170013ams_test@devb5204599953038405405 10.005802KH5903AMS6304ABCD';

    return KhqrPayment::create(array_merge([
        'transaction_id' => 'SUB-CMD-'.(++$n),
        'provider' => 'bakong',
        'subscription_id' => bakongCmdSubscription()->id,
        'amount' => 10.00,
        'currency' => 'USD',
        'status' => 'qr_generated',
        'settlement_target' => 'platform',
        'channel' => 'api',
        'checkout_payload' => ['type' => 'subscription'],
        'qr_payload' => $payload,
        'qr_md5' => md5($payload.$n),
        'expires_at' => now()->addMinutes(6),
    ], $overrides));
}

// ═══════════════════════════ bakong:usage ═══════════════════════════

it('reports spend and refusals without making a request', function () {
    Http::fake();

    BakongApiCall::create(['called_on' => now(), 'endpoint' => '/v1/check_transaction_by_md5', 'reason' => 'payment_verification', 'target' => 'platform', 'allowed' => true]);
    BakongApiCall::create(['called_on' => now(), 'endpoint' => '/v1/check_transaction_by_md5', 'reason' => 'payment_verification', 'target' => 'platform', 'allowed' => false, 'blocked_reason' => 'verify_cooldown']);

    $this->artisan('bakong:usage --days=2')
        ->expectsOutputToContain('Bakong Open API usage')
        ->expectsOutputToContain('This report made no Bakong request.')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

it('shows the ceiling it is protecting', function () {
    Http::fake();

    // The margin under Bakong's ~100 is the point: hitting our own limit is a
    // local event we can see, not an upstream refusal.
    $this->artisan('bakong:usage')->expectsOutputToContain('80')->assertExitCode(0);

    Http::assertNothingSent();
});

// ═══════════════════════════ bakong:diagnose ═══════════════════════════

it('diagnoses the setup offline by default', function () {
    Http::fake();
    bakongLiveToken();

    $this->artisan('bakong:diagnose')
        ->expectsOutputToContain('Bakong Open API diagnostics')
        ->expectsOutputToContain('no Bakong request was made')
        ->assertExitCode(0);

    // Anyone running this is running it because something is already wrong,
    // which is exactly when the allowance is scarcest.
    Http::assertNothingSent();
});

it('names the specific thing that is missing, not just "broken"', function () {
    Http::fake();
    config()->set('bakong.account_id', '');

    // "Bakong refused" hides several different jobs done in different places by
    // different people. A report that cannot say which one is not much of a
    // report.
    $this->artisan('bakong:diagnose')
        ->expectsOutputToContain('BAKONG_ACCOUNT_ID')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('tells an unregistered installation how to get a token', function () {
    Http::fake();

    $this->artisan('bakong:diagnose')
        ->expectsOutputToContain('bakong:token request')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('spends exactly one request on the live check, and only when asked', function () {
    bakongLiveToken();

    Http::fake(['bakong.test/*' => Http::response([
        'responseCode' => 0, 'responseMessage' => 'Account ID exists', 'errorCode' => null, 'data' => null,
    ], 200)]);

    $this->artisan('bakong:diagnose --live')->assertExitCode(0);

    // check_bakong_account is the cheapest question that proves the token is
    // accepted AND the payout account exists.
    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/check_bakong_account')
        && $r['accountId'] === 'ams_test@devb');
});

it('separates "token rejected" from "account unknown" on the live check', function () {
    bakongLiveToken();

    // responseCode 1 here means the account does not exist — which is still a
    // SUCCESSFUL authentication, and a different problem with a different fix.
    // A payout account Bakong does not know collects nothing.
    Http::fake(['bakong.test/*' => Http::response([
        'responseCode' => 1, 'responseMessage' => 'Account ID not found', 'errorCode' => 11, 'data' => null,
    ], 200)]);

    $this->artisan('bakong:diagnose --live')
        ->expectsOutputToContain('does not know account')
        ->assertExitCode(1);
});

// ═══════════════════════════ bakong:reconcile ═══════════════════════════

it('does nothing at all while the net is switched off', function () {
    Http::fake();
    bakongLiveToken();
    bakongOpenRow();

    config()->set('bakong.reconcile_enabled', false);

    // It ships off: Bakong sends no webhook, so there is no delivery failure for
    // the net to rescue — which makes it far less valuable than the KHQRPay
    // version and exactly as expensive.
    $this->artisan('bakong:reconcile')
        ->expectsOutputToContain('BAKONG_RECONCILE_ENABLED is false')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

it('gates itself on the master switch rather than trusting the scheduler', function () {
    Http::fake();
    bakongLiveToken();
    bakongOpenRow();

    config()->set('bakong.enabled', false);

    // A command run by hand, by a forgotten cron or by a deploy script never
    // goes through the scheduler at all.
    $this->artisan('bakong:reconcile')->assertExitCode(0);

    Http::assertNothingSent();
});

it('ignores rows outside its window, and KHQRPay rows entirely', function () {
    Http::fake(['bakong.test/*' => Http::response(['responseCode' => 1, 'errorCode' => 1], 200)]);
    bakongLiveToken();

    bakongOpenRow(['status' => 'paid', 'paid_at' => now()]);
    bakongOpenRow(['status' => 'expired']);
    bakongOpenRow(['qr_md5' => null]);
    bakongOpenRow(['provider' => 'khqrpay']);
    bakongOpenRow(['channel' => 'manual']);
    // Long past even the grace: the window is what bounds the net, because
    // "ask again later" has no exit when the gateway never answers.
    bakongOpenRow(['expires_at' => now()->subHours(4)]);

    $this->artisan('bakong:reconcile')
        ->expectsOutputToContain('No open Bakong payments')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

it('confirms a payment that landed after the payer closed the page', function () {
    bakongLiveToken();
    $row = bakongOpenRow();

    Http::fake(['bakong.test/*' => Http::response([
        'responseCode' => 0,
        'responseMessage' => 'Getting transaction successfully.',
        'data' => ['hash' => str_repeat('b', 64), 'currency' => 'USD', 'amount' => 10.00],
    ], 200)]);

    $this->artisan('bakong:reconcile')->assertExitCode(0);

    // The case the net exists for: their money arrived and nothing was watching.
    expect($row->fresh()->status)->toBe('paid');
});

it('leaves a row OPEN when the gateway refuses, never expiring it on a guess', function () {
    bakongLiveToken();
    $row = bakongOpenRow();

    Http::fake(['bakong.test/*' => Http::response(['responseCode' => 1, 'responseMessage' => 'Internal server error'], 500)]);

    $this->artisan('bakong:reconcile')->assertExitCode(0);

    // Expiry is terminal, so expiring here means the net never looks at this QR
    // again even after the gateway recovers — and with no webhook, nothing else
    // ever will either.
    expect($row->fresh()->isOpen())->toBeTrue();
});

it('reports what it would do without calling anyone', function () {
    Http::fake();
    bakongLiveToken();
    bakongOpenRow();

    $this->artisan('bakong:reconcile --dry-run')
        ->expectsOutputToContain('no Bakong request was made')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

it('charges its calls to the same ledger as everything else', function () {
    bakongLiveToken();
    bakongOpenRow();

    Http::fake(['bakong.test/*' => Http::response(['responseCode' => 1, 'errorCode' => 1], 200)]);

    $this->artisan('bakong:reconcile')->assertExitCode(0);

    expect(BakongApiCall::spentByReasonOn('platform'))
        ->toBe([BakongProviderClient::REASON_PAYMENT_VERIFICATION => 1]);
});
