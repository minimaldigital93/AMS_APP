<?php

use App\Models\PlatformPaymentSetting;
use App\Models\User;
use App\Services\Bakong\BakongRuntimeConfig;

/**
 * The Bakong operating config moved out of .env and onto the settings page.
 *
 * The risk of that move is not the happy path — it is the EXISTING install. A
 * row saved before these columns existed has null in every one of them, and if
 * null were read as a value that row would switch payments off, set the daily
 * ceiling to zero and un-register the integrator, all on deploy, all silently.
 * So null means "not set here, read .env" and that is what most of this pins.
 */
function superadminFor(): User
{
    seedRoles();
    $u = User::factory()->create();
    $u->assignRole('superadmin');

    return $u;
}

beforeEach(function () {
    BakongRuntimeConfig::forgetEnvDefaults();
});

it('leaves .env in force when no settings row exists at all', function () {
    config(['bakong.daily_request_limit' => 80, 'bakong.enabled' => true]);

    BakongRuntimeConfig::apply();

    expect(config('bakong.daily_request_limit'))->toBe(80)
        ->and(config('bakong.enabled'))->toBeTrue();
});

it('does not read a null column as false or zero', function () {
    // THE UPGRADE CASE. This row is what every existing installation has the
    // moment the migration runs: saved long ago, opinionated about nothing new.
    PlatformPaymentSetting::create(['bakong_account_id' => 'platform@aclb', 'currency' => 'USD']);

    config(['bakong.enabled' => true, 'bakong.daily_request_limit' => 80]);

    BakongRuntimeConfig::apply();

    // If this ever flips, deploying the migration turns off payments for
    // everyone who had them on.
    expect(config('bakong.enabled'))->toBeTrue()
        ->and(config('bakong.daily_request_limit'))->toBe(80)
        ->and(BakongRuntimeConfig::overrides())->toBe([]);
});

it('lets a saved value outrank .env', function () {
    PlatformPaymentSetting::create([
        'currency' => 'USD',
        'bakong_enabled' => false,
        'bakong_daily_request_limit' => 25,
        'bakong_email' => 'saved@example.com',
    ]);

    config(['bakong.enabled' => true, 'bakong.daily_request_limit' => 80, 'bakong.integrator.email' => 'env@example.com']);

    BakongRuntimeConfig::apply();

    // A saved FALSE is an opinion and must win — it is the operator switching
    // payments off from the page, which is the whole point of the move.
    expect(config('bakong.enabled'))->toBeFalse()
        ->and(config('bakong.daily_request_limit'))->toBe(25)
        ->and(config('bakong.integrator.email'))->toBe('saved@example.com');
});

it('treats a cleared text field as "stop overriding", not as blank identity', function () {
    PlatformPaymentSetting::create(['currency' => 'USD', 'bakong_email' => '']);
    config(['bakong.integrator.email' => 'env@example.com']);

    BakongRuntimeConfig::apply();

    // Configuring the integrator as nobody would break the token lookup and
    // send an empty email to NBC on renewal.
    expect(config('bakong.integrator.email'))->toBe('env@example.com');
});

it('remembers what .env said after the override has replaced it', function () {
    config(['bakong.daily_request_limit' => 80]);
    PlatformPaymentSetting::create(['currency' => 'USD', 'bakong_daily_request_limit' => 25]);

    BakongRuntimeConfig::apply();

    // The form shows this as the placeholder, so "empty" reads as "inherits
    // 80" instead of as "off".
    expect(config('bakong.daily_request_limit'))->toBe(25)
        ->and(BakongRuntimeConfig::envDefaults()['bakong.daily_request_limit'])->toBe(80);
});

it('survives the database being unreachable rather than taking the app down', function () {
    // apply() runs in boot(), before the request is anything at all.
    Schema::drop('platform_payment_settings');
    config(['bakong.enabled' => true]);

    BakongRuntimeConfig::apply();

    expect(config('bakong.enabled'))->toBeTrue();
});

// ───────────────────────────── through the page ─────────────────────────────

it('saves the whole operating config from the settings form', function () {
    $this->actingAs(superadminFor())
        ->put(route('superadmin.settings.payment.update'), [
            'currency' => 'USD',
            'bakong_account_id' => 'platform@aclb',
            'bakong_enabled' => '1',
            'bakong_email' => 'integrator@ams.test',
            'bakong_organization' => 'MinimalDigital',
            'bakong_project' => 'AMS',
            'bakong_daily_request_limit' => 60,
            'bakong_verify_cooldown' => 90,
            'bakong_qr_ttl' => 10,
            'bakong_max_verify_attempts' => 6,
            'bakong_reconcile_enabled' => '0',
        ])
        ->assertRedirect(route('superadmin.settings.payment'));

    $row = PlatformPaymentSetting::current();

    expect($row->bakong_enabled)->toBeTrue()
        ->and($row->bakong_email)->toBe('integrator@ams.test')
        ->and($row->bakong_daily_request_limit)->toBe(60)
        ->and($row->bakong_verify_cooldown)->toBe(90)
        ->and($row->bakong_reconcile_enabled)->toBeFalse()
        ->and(PlatformPaymentSetting::count())->toBe(1);
});

it('refuses a local ceiling above NBC\'s own, which could not bind', function () {
    config(['bakong.upstream_daily_limit' => 100]);

    $this->actingAs(superadminFor())
        ->put(route('superadmin.settings.payment.update'), [
            'currency' => 'USD',
            'bakong_daily_request_limit' => 500,
        ])
        ->assertSessionHasErrors('bakong_daily_request_limit');
});

it('refuses a cooldown the checkout poll would outrun', function () {
    // Below the page's own 10-second poll the cooldown absorbs nothing and
    // every poll becomes a metered request — the guard stops guarding.
    $this->actingAs(superadminFor())
        ->put(route('superadmin.settings.payment.update'), [
            'currency' => 'USD',
            'bakong_verify_cooldown' => 5,
        ])
        ->assertSessionHasErrors('bakong_verify_cooldown');
});

it('refuses an integrator email that is not an address', function () {
    $this->actingAs(superadminFor())
        ->put(route('superadmin.settings.payment.update'), [
            'currency' => 'USD',
            'bakong_email' => 'not-an-address',
        ])
        ->assertSessionHasErrors('bakong_email');
});

it('never offers the base URL as a form field', function () {
    $page = $this->actingAs(superadminFor())
        ->get(route('superadmin.settings.payment'))
        ->assertOk();

    // base_url is the host this app POSTs a bearer token to. A form that can
    // repoint it turns a borrowed superadmin session into credential theft, and
    // the value never changes anyway — so unlike the token, it stays in .env.
    $page->assertDontSee('name="bakong_api_base_url"', false)
        ->assertDontSee('name="base_url"', false);
});

it('offers the token as a WRITE-ONLY field', function () {
    // The token IS a field now, by request. What makes that acceptable is not
    // the field's presence but its shape: masked, never pre-filled, and
    // excluded from flashed input. This asserts the shape, because a later
    // edit that "helpfully" repopulates it would look like a convenience and
    // be a credential disclosure. BakongTokenFieldTest pins the behaviour.
    $html = $this->actingAs(superadminFor())
        ->get(route('superadmin.settings.payment'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('name="bakong_token"')
        ->and($html)->toContain('type="password" name="bakong_token" value=""')
        ->and($html)->toContain('autocomplete="off"');
});

it('puts every operationally meaningful bakong key on the page', function () {
    // The claim this feature makes is "Bakong is configured in Payment
    // Settings". This asserts the claim against config/bakong.php itself, so
    // adding a new key without a field fails here rather than being discovered
    // by an operator who cannot change it.
    $exempt = [
        // The host we POST a bearer token to. A form that can repoint it turns
        // a borrowed superadmin session into credential theft.
        'base_url',
        // A "pretend the payment succeeded" switch; hard-disabled in
        // production and not something to put on a production screen.
        'demo', 'demo_settle_after',
        // HTTP plumbing.
        'connect_timeout', 'timeout',
        // Unbuilt feature; two of its four values are URLs.
        'deeplink.enabled', 'deeplink.app_icon_url', 'deeplink.app_name', 'deeplink.callback',
        // The payout identity — on the page already, under its own names.
        'account_id', 'merchant_name', 'merchant_city', 'currency',
    ];

    $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
        $out = [];
        foreach ($a as $k => $v) {
            $key = $prefix ? "$prefix.$k" : $k;
            $out = is_array($v) ? array_merge($out, $flatten($v, $key)) : array_merge($out, [$key]);
        }

        return $out;
    };

    $mapped = array_values(\App\Services\Bakong\BakongRuntimeConfig::overridableKeys());

    $missing = [];
    foreach ($flatten(config('bakong')) as $key) {
        if (in_array($key, $exempt, true)) {
            continue;
        }
        if (! in_array("bakong.$key", $mapped, true)) {
            $missing[] = $key;
        }
    }

    expect($missing)->toBe([]);
});
