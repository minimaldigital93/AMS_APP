<?php

use App\Models\BakongApiCall;
use App\Models\BakongToken;
use App\Services\Bakong\BakongProviderClient;
use App\Services\Bakong\BakongTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Token management, and the one rule behind all of it: DO NOT SPEND METERED
 * REQUESTS ON TOKEN HOUSEKEEPING.
 *
 * A Bakong token states its own expiry in its JWT and lasts roughly 93 days.
 * Asking the API when it expires, or renewing on a timer, would pay for
 * information already in hand — and would do it on the same allowance the
 * payments depend on. Four requests a year is the target; one per operation is
 * the failure mode.
 */
beforeEach(function () {
    Cache::flush();

    config()->set('bakong.enabled', true);
    config()->set('bakong.demo', false);
    config()->set('bakong.base_url', 'https://bakong.test');
    config()->set('bakong.integrator.email', 'integrator@ams.test');
    config()->set('bakong.integrator.organization', 'AMS Test');
    config()->set('bakong.integrator.project', 'AMS');
    config()->set('bakong.token_renew_days', 7);
    config()->set('bakong.daily_request_limit', 80);
});

function bakongJwt(int $daysToExpiry): string
{
    $payload = rtrim(strtr(base64_encode(json_encode([
        'exp' => now()->addDays($daysToExpiry)->timestamp,
        'iat' => now()->timestamp,
    ])), '+/', '-_'), '=');

    return 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.'.$payload.'.signature';
}

function bakongOk(array $data = []): void
{
    Http::fake(['bakong.test/*' => Http::response([
        'data' => $data ?: null,
        'errorCode' => null,
        'responseCode' => 0,
        'responseMessage' => 'ok',
    ], 200)]);
}

// ═══════════════════════════ issuance ═══════════════════════════

it('registers the integrator and records the pending registration before any code arrives', function () {
    bakongOk();

    $result = app(BakongTokenService::class)->requestCode();

    expect($result['ok'])->toBeTrue();

    $row = BakongToken::current();

    // The row exists BEFORE the code is exchanged, so a half-finished setup is
    // visible in the database rather than living only in whoever ran the
    // command's memory.
    expect($row)->not->toBeNull()
        ->and($row->verified_at)->toBeNull()
        ->and($row->token)->toBeNull()
        ->and($row->organization)->toBe('AMS Test');

    Http::assertSent(fn ($r) => $r->url() === 'https://bakong.test/v1/request_token'
        && $r['email'] === 'integrator@ams.test'
        && $r['organization'] === 'AMS Test'
        && $r['project'] === 'AMS');
});

it('refuses to register without an integrator identity, and spends nothing finding out', function () {
    Http::fake();
    config()->set('bakong.integrator.organization', '');

    $result = app(BakongTokenService::class)->requestCode();

    expect($result['ok'])->toBeFalse();
    Http::assertNothingSent();
});

it('rejects a malformed code locally rather than paying to be told', function () {
    bakongOk(['token' => bakongJwt(90)]);

    // The document constrains the code to exactly 20 characters. A pasted code
    // with stray whitespace, or half of one, is the commonest mistake there is
    // — and checking it here costs nothing while asking costs a request.
    foreach (['', 'too-short', str_repeat('x', 19), str_repeat('x', 21)] as $bad) {
        expect(app(BakongTokenService::class)->verifyCode($bad)['ok'])->toBeFalse();
    }

    Http::assertNothingSent();

    // Trimming is applied before the length check, so a clean code surrounded
    // by whitespace is accepted rather than rejected on a technicality.
    expect(app(BakongTokenService::class)->verifyCode('  '.str_repeat('a', 20).' ')['ok'])->toBeTrue();
});

it('stores the token encrypted and reads its expiry out of the JWT, without asking', function () {
    bakongOk(['token' => bakongJwt(93)]);

    expect(app(BakongTokenService::class)->verifyCode(str_repeat('a', 20))['ok'])->toBeTrue();

    $row = BakongToken::current();

    expect($row->verified_at)->not->toBeNull()
        ->and($row->isUsable())->toBeTrue()
        // Decoded locally from the token itself — no second request was made to
        // discover it.
        ->and(now()->diffInDays($row->expires_at))->toBeGreaterThan(90);

    // The column holds ciphertext: a database dump must not hand anyone a live
    // credential.
    $raw = DB::table('bakong_tokens')->where('email', 'integrator@ams.test')->value('token');
    expect($raw)->not->toContain('eyJ0eXAi')
        ->and($row->token)->toStartWith('eyJ0eXAi');

    // Exactly one request for the whole exchange.
    Http::assertSentCount(1);
});

it('does not store an empty token from a success envelope', function () {
    // A success envelope with no token in it is not a success: storing the
    // empty string would make every later request fail the no_token gate while
    // the row claimed to be verified.
    bakongOk(['token' => '']);

    expect(app(BakongTokenService::class)->verifyCode(str_repeat('a', 20))['ok'])->toBeFalse()
        ->and(BakongToken::current()?->isUsable())->not->toBeTrue();
});

// ═══════════════════════════ renewal ═══════════════════════════

it('does not renew a token that is not due, and makes no request to decide that', function () {
    BakongToken::create([
        'email' => 'integrator@ams.test',
        'token' => bakongJwt(60),
        'expires_at' => now()->addDays(60),
        'verified_at' => now(),
    ]);

    Http::fake();

    $result = app(BakongTokenService::class)->renewIfDue();

    expect($result['ok'])->toBeTrue();

    // THE POINT OF THE WHOLE SERVICE: a token lasting ~93 days needs renewing
    // about four times a year, so the scheduler's standing cost must be zero
    // requests on every other run.
    Http::assertNothingSent();
});

it('renews inside the window, and the renewal needs no old token', function () {
    BakongToken::create([
        'email' => 'integrator@ams.test',
        'token' => bakongJwt(3),
        'expires_at' => now()->addDays(3),
        'verified_at' => now()->subDays(90),
    ]);

    bakongOk(['token' => bakongJwt(93)]);

    expect(app(BakongTokenService::class)->renewIfDue()['ok'])->toBeTrue();

    $row = BakongToken::current()->fresh();

    expect($row->renewed_at)->not->toBeNull()
        ->and(now()->diffInDays($row->expires_at))->toBeGreaterThan(90);

    // renew_token takes only the email — the OLD token is not required and is
    // not sent, which matters because the commonest reason to renew is that the
    // old one is already dead.
    Http::assertSent(fn ($r) => $r->url() === 'https://bakong.test/v1/renew_token'
        && $r['email'] === 'integrator@ams.test'
        && ! isset($r['token'])
        && $r->hasHeader('Authorization') === false);
});

it('leaves a token with no readable expiry alone rather than renewing on a guess', function () {
    BakongToken::create([
        'email' => 'integrator@ams.test',
        'token' => 'not-a-jwt',
        'expires_at' => null,
        'verified_at' => now(),
    ]);

    Http::fake();

    expect(app(BakongTokenService::class)->renewIfDue()['ok'])->toBeTrue();

    // Renewing on a schedule we cannot justify would be a standing metered
    // request. A genuinely dead token surfaces as a 401 that trips the failure
    // backoff instead.
    Http::assertNothingSent();
});

it('clears the failure backoff once a fresh token is stored', function () {
    $client = new BakongProviderClient;
    $client->ledger()->backOff('platform', 15, 'HTTP 401 Unauthorized');

    expect($client->ledger()->activeBackoff('platform'))->not->toBeNull();

    bakongOk(['token' => bakongJwt(93)]);
    app(BakongTokenService::class)->verifyCode(str_repeat('a', 20));

    // An operator who has just fixed the credential should not wait out a
    // backoff earned by the dead one.
    expect($client->ledger()->activeBackoff('platform'))->toBeNull();
});

// ═══════════════════════ gates apply to token traffic too ═══════════════════

it('refuses token requests when the master switch is off', function () {
    Http::fake();
    config()->set('bakong.enabled', false);

    foreach (['requestCode', 'renew'] as $method) {
        $result = app(BakongTokenService::class)->{$method}();
        expect($result['ok'])->toBeFalse()
            ->and($result['blocked'])->toBe(BakongProviderClient::BLOCK_DISABLED);
    }

    Http::assertNothingSent();
});

it('charges token traffic to the same daily allowance as everything else', function () {
    bakongOk(['token' => bakongJwt(93)]);

    app(BakongTokenService::class)->requestCode();

    // A token endpoint is not exempt from the ceiling: if the day's allowance
    // is gone, renewing tomorrow is better than failing a payment today.
    expect(BakongApiCall::spentOn('platform'))->toBe(1)
        ->and(BakongApiCall::spentByReasonOn('platform'))
        ->toBe([BakongProviderClient::REASON_TOKEN_REQUEST => 1]);
});

it('never exposes the token in status output', function () {
    BakongToken::create([
        'email' => 'integrator@ams.test',
        'token' => bakongJwt(90),
        'expires_at' => now()->addDays(90),
        'verified_at' => now(),
    ]);

    $status = app(BakongTokenService::class)->status();

    expect($status['usable'])->toBeTrue()
        ->and($status['fingerprint'])->toHaveLength(12)
        ->and(json_encode($status))->not->toContain('eyJ0eXAi');
});

// ═══════════════════════════ the command ═══════════════════════════

it('reports token status without making a request', function () {
    Http::fake();

    $this->artisan('bakong:token status')
        ->expectsOutputToContain('Bakong access token')
        ->assertExitCode(0);

    Http::assertNothingSent();
});

it('refuses the spending actions while Bakong is switched off', function () {
    Http::fake();
    config()->set('bakong.enabled', false);

    $this->artisan('bakong:token request --force')->assertExitCode(1);
    $this->artisan('bakong:token renew --force')->assertExitCode(1);

    Http::assertNothingSent();
});

it('runs the scheduler-facing renewal without prompting', function () {
    Http::fake();

    // --if-due is what the scheduler runs. It must never prompt and must make
    // no request when nothing is due.
    $this->artisan('bakong:token renew --if-due')->assertExitCode(0);

    Http::assertNothingSent();
});
