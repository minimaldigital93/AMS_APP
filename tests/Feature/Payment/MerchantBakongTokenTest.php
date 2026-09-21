<?php

use App\Models\MerchantPaymentSetting;
use App\Services\Bakong\MerchantBakongCredentials;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * The landlord's OWN Bakong credential.
 *
 * It is handled the way the superadmin's platform token is, and for the same
 * reason: it is the one real credential on a page that is otherwise payment
 * instructions printed on every QR a tenant scans. So it is write-only —
 * encrypted at rest, never rendered back, blank-means-keep — and importing it
 * is entirely offline.
 */
function jwtWithExpiry(?Carbon $expiry, string $email = 'landlord@example.test'): string
{
    $b64 = fn (array $p) => rtrim(strtr(base64_encode(json_encode($p)), '+/', '-_'), '=');

    $claims = ['email' => $email];
    if ($expiry) {
        $claims['exp'] = $expiry->timestamp;
    }

    return $b64(['alg' => 'HS256', 'typ' => 'JWT']).'.'.$b64($claims).'.signature';
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-21 09:00:00');
    Http::preventStrayRequests();
    Http::fake();
});
afterEach(fn () => Carbon::setTestNow());

it('imports a token without contacting anyone', function () {
    $admin = makeAdmin();
    $token = jwtWithExpiry(now()->addDays(90));

    $result = app(MerchantBakongCredentials::class)->import($admin->id, $token);

    expect($result['ok'])->toBeTrue();

    // Offline is the whole point: the moment you need this is the moment the
    // allowance is under pressure.
    Http::assertNothingSent();
});

it('stores the token encrypted, never in the clear', function () {
    $admin = makeAdmin();
    $token = jwtWithExpiry(now()->addDays(90));

    app(MerchantBakongCredentials::class)->import($admin->id, $token);

    $raw = DB::table('merchant_payment_settings')->where('account_id', $admin->id)->value('bakong_token');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toContain($token)
        ->and(MerchantPaymentSetting::forAccount($admin->id)->bakong_token)->toBe($token);
});

it('reads the expiry out of the JWT rather than asking for it', function () {
    $admin = makeAdmin();
    $expiry = now()->addDays(30);

    app(MerchantBakongCredentials::class)->import($admin->id, jwtWithExpiry($expiry));

    $status = app(MerchantBakongCredentials::class)->statusFor($admin->id);

    expect($status['configured'])->toBeTrue()
        ->and($status['expires_at']->timestamp)->toBe($expiry->timestamp)
        ->and($status['expired'])->toBeFalse()
        ->and($status['fingerprint'])->toHaveLength(12);
});

/**
 * The expiry has to survive the database.
 *
 * expiryFromJwt() returned a UTC Carbon; Eloquent writes a datetime by
 * formatting whatever timezone it carries into a naive string and reads it back
 * in the app timezone (Asia/Phnom_Penh). So the instant came back 7 hours early
 * and every token — platform and merchant alike — was believed dead 7 hours
 * before NBC would have stopped accepting it, with every request in that window
 * refusing for no_token.
 */
it('keeps the expiry instant through a database round trip', function () {
    $admin = makeAdmin();
    $expiry = now()->addDays(30);

    app(MerchantBakongCredentials::class)->import($admin->id, jwtWithExpiry($expiry));

    $reread = MerchantPaymentSetting::forAccount($admin->id)->fresh()->bakong_token_expires_at;

    expect($reread->timestamp)->toBe($expiry->timestamp);
});

it('refuses a token that is already dead', function () {
    $admin = makeAdmin();

    $result = app(MerchantBakongCredentials::class)->import($admin->id, jwtWithExpiry(now()->subDay()));

    expect($result['ok'])->toBeFalse()
        ->and(MerchantPaymentSetting::forAccount($admin->id)?->bakong_token)->toBeNull();
});

it('refuses anything that is not shaped like a token', function () {
    $admin = makeAdmin();
    $svc = app(MerchantBakongCredentials::class);

    expect($svc->import($admin->id, 'not-a-jwt')['ok'])->toBeFalse()
        ->and($svc->import($admin->id, '')['ok'])->toBeFalse();
});

/**
 * Three different "no credential" states, one answer: refuse BEFORE spending.
 * A refused Bakong request is metered exactly like a successful one, so the
 * gate has to be local.
 */
it('hands out no token when disabled, absent or expired', function () {
    $admin = makeAdmin();
    $svc = app(MerchantBakongCredentials::class);

    // Absent.
    expect($svc->tokenFor($admin->id))->toBeNull();

    $svc->import($admin->id, jwtWithExpiry(now()->addDays(30)));

    // Present but not switched on.
    expect($svc->tokenFor($admin->id))->toBeNull();

    MerchantPaymentSetting::forAccount($admin->id)->forceFill(['bakong_enabled' => true])->save();
    expect($svc->tokenFor($admin->id))->not->toBeNull();

    // Switched on but dead.
    MerchantPaymentSetting::forAccount($admin->id)
        ->forceFill(['bakong_token_expires_at' => now()->subDay()])->save();
    expect($svc->tokenFor($admin->id))->toBeNull();
});

it('never renders the token back into the settings page', function () {
    $admin = makeAdmin();
    $token = jwtWithExpiry(now()->addDays(90));
    app(MerchantBakongCredentials::class)->import($admin->id, $token);

    $response = test()->actingAs($admin)->get(route('admin.settings.payment'));

    $response->assertOk()
        ->assertDontSee($token)
        // Only the fingerprint identifies it.
        ->assertSee(app(MerchantBakongCredentials::class)->statusFor($admin->id)['fingerprint']);
});

it('keeps the stored token when the field is left blank', function () {
    $admin = makeAdmin();
    $token = jwtWithExpiry(now()->addDays(90));
    app(MerchantBakongCredentials::class)->import($admin->id, $token);

    test()->actingAs($admin)->put(route('admin.settings.payment.update'), [
        'currency' => 'USD',
        'bank_name' => 'Changed Bank',
        'bakong_token' => '',
    ])->assertRedirect();

    expect(MerchantPaymentSetting::forAccount($admin->id)->bakong_token)->toBe($token)
        ->and(MerchantPaymentSetting::forAccount($admin->id)->bank_name)->toBe('Changed Bank');
});

it('removes the credential only when explicitly asked', function () {
    $admin = makeAdmin();
    app(MerchantBakongCredentials::class)->import($admin->id, jwtWithExpiry(now()->addDays(90)));
    MerchantPaymentSetting::forAccount($admin->id)->forceFill(['bakong_enabled' => true])->save();

    test()->actingAs($admin)
        ->delete(route('admin.settings.payment.forget_token'))
        ->assertRedirect();

    $status = app(MerchantBakongCredentials::class)->statusFor($admin->id);

    expect($status['configured'])->toBeFalse()
        ->and($status['enabled'])->toBeFalse()
        // The payout identity is untouched — that is not a credential.
        ->and(MerchantPaymentSetting::forAccount($admin->id))->not->toBeNull();
});

it('keeps one landlord credential away from another', function () {
    $a = makeAdmin();
    $b = makeAdmin();
    $svc = app(MerchantBakongCredentials::class);

    $svc->import($a->id, jwtWithExpiry(now()->addDays(90), 'a@example.test'));
    MerchantPaymentSetting::forAccount($a->id)->forceFill(['bakong_enabled' => true])->save();

    expect($svc->tokenFor($b->id))->toBeNull()
        ->and($svc->statusFor($b->id)['configured'])->toBeFalse();
});
