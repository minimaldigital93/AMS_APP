<?php

use App\Models\BakongToken;
use App\Services\Bakong\BakongTokenService;

/**
 * BAKONG_EMAIL is two different things at once, and only one of them is checked
 * by anything.
 *
 * Locally it is just a lookup key: store() stamps the configured string onto the
 * row and current() reads the row back by that same string, so the two agree
 * however either is spelled. A typo is therefore INVISIBLE — payments mint,
 * verify and settle exactly as they should.
 *
 * Upstream it is the whole of renew_token's payload. So the same typo that
 * changes nothing today is the reason the token cannot be renewed in ~90 days,
 * at which point payments stop for a reason nobody will connect to a missing
 * letter. This test pins the one cheap moment the discrepancy is visible.
 */
function jwtWith(array $claims): string
{
    $seg = fn (array $a) => rtrim(strtr(base64_encode(json_encode($a)), '+/', '-_'), '=');

    return $seg(['typ' => 'JWT', 'alg' => 'HS256']).'.'.$seg($claims).'.sig';
}

it('reads the integrator email out of the token, wherever the claim sits', function () {
    // The claim name is not documented, so the reader looks for an email-shaped
    // leaf rather than a key it has assumed.
    expect(BakongToken::emailFromJwt(jwtWith(['email' => 'a@b.com', 'exp' => time() + 99])))->toBe('a@b.com')
        ->and(BakongToken::emailFromJwt(jwtWith(['data' => ['user' => 'nested@b.com']])))->toBe('nested@b.com');
});

it('says "unknown" rather than "mismatch" when the token carries no email', function () {
    config()->set('bakong.integrator.email', 'someone@example.com');

    BakongToken::create([
        'email' => 'someone@example.com',
        'token' => jwtWith(['exp' => time() + 9999]), // no email claim at all
        'verified_at' => now(),
    ]);

    // A check that cannot substantiate a mismatch must not report one. Crying
    // wolf here trains the operator to ignore the one time it is real.
    expect(app(BakongTokenService::class)->status()['email_matches'])->toBeNull();
});

it('flags a one-letter difference between .env and the token', function () {
    config()->set('bakong.integrator.email', 'minimaldigial93@gmail.com'); // missing a 't'

    BakongToken::create([
        'email' => 'minimaldigial93@gmail.com', // stamped from .env, so it AGREES locally
        'token' => jwtWith(['email' => 'minimaldigital93@gmail.com', 'exp' => time() + 9999]),
        'verified_at' => now(),
    ]);

    $status = app(BakongTokenService::class)->status();

    // Usable — which is precisely why this needs saying out loud.
    expect($status['usable'])->toBeTrue()
        ->and($status['email_matches'])->toBeFalse()
        ->and($status['token_email'])->toBe('minimaldigital93@gmail.com');

    $this->artisan('bakong:token status')
        ->expectsOutputToContain('does not match')
        ->expectsOutputToContain('minimaldigital93@gmail.com')
        ->assertSuccessful();
});

it('is quiet when they agree, and spends nothing either way', function () {
    Illuminate\Support\Facades\Http::fake();
    config()->set('bakong.integrator.email', 'right@gmail.com');

    BakongToken::create([
        'email' => 'right@gmail.com',
        'token' => jwtWith(['email' => 'right@gmail.com', 'exp' => time() + 9999]),
        'verified_at' => now(),
    ]);

    expect(app(BakongTokenService::class)->status()['email_matches'])->toBeTrue();

    $this->artisan('bakong:token status')->assertSuccessful();

    Illuminate\Support\Facades\Http::assertNothingSent();
});
