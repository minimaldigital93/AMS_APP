<?php

use App\Models\AuditLog;
use App\Models\BakongToken;
use App\Models\PlatformPaymentSetting;
use App\Models\User;

/**
 * The access token is the one CREDENTIAL on a page of identity.
 *
 * The account id, merchant name and city are printed inside every QR a customer
 * scans — they are not secrets, and the form renders them back. The token is a
 * bearer credential for money, so the field is WRITE-ONLY, and most of what is
 * pinned here is the ways a value leaks out of a Laravel form rather than the
 * way it goes in.
 */
function tokenFieldSuperadmin(): User
{
    seedRoles();
    $u = User::factory()->create();
    $u->assignRole('superadmin');

    return $u;
}

function freshJwt(int $daysValid = 60, string $email = 'integrator@ams.test'): string
{
    $seg = fn (array $a) => rtrim(strtr(base64_encode(json_encode($a)), '+/', '-_'), '=');

    return $seg(['typ' => 'JWT']).'.'.$seg(['email' => $email, 'exp' => now()->addDays($daysValid)->timestamp]).'.sig';
}

beforeEach(function () {
    config(['bakong.integrator.email' => 'integrator@ams.test']);
});

it('stores a pasted token, encrypted, without ever echoing it', function () {
    $jwt = freshJwt();

    $this->actingAs(tokenFieldSuperadmin())
        ->put(route('superadmin.settings.payment.update'), [
            'currency' => 'USD',
            'bakong_token' => $jwt,
        ])
        ->assertRedirect(route('superadmin.settings.payment'))
        ->assertSessionHasNoErrors();

    $row = BakongToken::current();

    expect($row)->not->toBeNull()
        ->and($row->token)->toBe($jwt)
        ->and($row->verified_at)->not->toBeNull()
        ->and($row->expires_at)->not->toBeNull();

    // Encrypted AT REST: the raw column must not contain the token, or a
    // database dump hands it over. (The model decrypts on read, above.)
    $raw = DB::table('bakong_tokens')->value('token');
    expect($raw)->not->toContain($jwt);
});

it('never renders the token back into the page', function () {
    $jwt = freshJwt();
    $admin = tokenFieldSuperadmin();

    $this->actingAs($admin)->put(route('superadmin.settings.payment.update'), [
        'currency' => 'USD', 'bakong_token' => $jwt,
    ]);

    // What the operator gets instead is a fingerprint and an expiry: enough to
    // tell two tokens apart and to see when this one dies.
    $this->actingAs($admin)
        ->get(route('superadmin.settings.payment'))
        ->assertOk()
        ->assertDontSee($jwt)
        ->assertSee(__('messages.bakong_token_stored'));
});

it('does not flash the token back after a validation error elsewhere on the form', function () {
    $jwt = freshJwt();

    // THE LEAK THIS CLOSES. A failed validation flashes the whole request body
    // into the session so the form can repopulate — and the session outlives
    // the request. A token that reaches old() is a token the page can render.
    $this->actingAs(tokenFieldSuperadmin())
        ->put(route('superadmin.settings.payment.update'), [
            'currency' => 'NOPE',          // fails
            'bakong_token' => $jwt,        // must not survive into old()
        ])
        ->assertSessionHasErrors('currency');

    expect(session()->getOldInput('bakong_token'))->toBeNull();
});

it('leaves the stored token alone when the field is left blank', function () {
    $jwt = freshJwt();
    $admin = tokenFieldSuperadmin();

    $this->actingAs($admin)->put(route('superadmin.settings.payment.update'), [
        'currency' => 'USD', 'bakong_token' => $jwt,
    ]);

    // Saving any other field must not require re-pasting the credential —
    // otherwise every edit is an occasion to handle it again.
    $this->actingAs($admin)->put(route('superadmin.settings.payment.update'), [
        'currency' => 'USD', 'bakong_account_id' => 'platform@aclb', 'bakong_token' => '',
    ])->assertSessionHasNoErrors();

    expect(BakongToken::current()->token)->toBe($jwt)
        ->and(BakongToken::count())->toBe(1)
        ->and(PlatformPaymentSetting::current()->bakong_account_id)->toBe('platform@aclb');
});

it('refuses a malformed or expired token with the importer\'s own words', function () {
    $admin = tokenFieldSuperadmin();

    $this->actingAs($admin)->put(route('superadmin.settings.payment.update'), [
        'currency' => 'USD', 'bakong_token' => 'not-a-jwt',
    ])->assertSessionHasErrors('bakong_token');

    // An expired token stored would leave a row claiming to be verified while
    // every request is refused for no_token — the confusing state the CLI
    // importer already refuses, and it must refuse identically here.
    $this->actingAs($admin)->put(route('superadmin.settings.payment.update'), [
        'currency' => 'USD', 'bakong_token' => freshJwt(-1),
    ])->assertSessionHasErrors('bakong_token');

    expect(BakongToken::count())->toBe(0);
});

it('keys the token to the email saved in the SAME request', function () {
    // Order matters: importToken() stamps the row with the configured
    // integrator email. Importing before the settings save would key the token
    // to the OLD address and then look it up by the new one — a token that
    // exists and can never be found.
    $this->actingAs(tokenFieldSuperadmin())->put(route('superadmin.settings.payment.update'), [
        'currency' => 'USD',
        'bakong_email' => 'newly-saved@ams.test',
        'bakong_token' => freshJwt(),
    ])->assertSessionHasNoErrors();

    expect(BakongToken::query()->value('email'))->toBe('newly-saved@ams.test');
});

it('audits that the token changed, and its fingerprint, never its value', function () {
    $jwt = freshJwt();

    $this->actingAs(tokenFieldSuperadmin())->put(route('superadmin.settings.payment.update'), [
        'currency' => 'USD', 'bakong_token' => $jwt,
    ]);

    $log = AuditLog::where('action', 'bakong.token.imported')->first();

    expect($log)->not->toBeNull()
        ->and(json_encode($log->context))->not->toContain($jwt)
        ->and($log->context['fingerprint'])->not->toBeEmpty();
});

it('still refuses a non-superadmin', function () {
    $this->actingAs(makeAdmin())
        ->put(route('superadmin.settings.payment.update'), [
            'currency' => 'USD', 'bakong_token' => freshJwt(),
        ])
        ->assertForbidden();

    expect(BakongToken::count())->toBe(0);
});
