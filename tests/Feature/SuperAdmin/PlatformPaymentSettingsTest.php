<?php

use App\Models\BakongApiCall;
use App\Models\BakongToken;
use App\Models\PlatformPaymentSetting;
use App\Models\User;
use App\Services\Bakong\BakongPlatformIdentity;
use Illuminate\Support\Facades\Http;

/**
 * Superadmin → Payment Settings, after khqr.cc.
 *
 * The page answers two questions that used to live in different places: WHERE
 * subscription money lands, and HOW MUCH of today's Bakong allowance is left.
 * Both matter here because the second is the likeliest reason anyone opened the
 * page at all.
 */
function makeSuperadminUser(): User
{
    seedRoles();
    $user = User::factory()->create(['name' => 'Platform Owner']);
    $user->assignRole('superadmin');

    return $user;
}

it('lets the superadmin save the Bakong payout identity', function () {
    $superadmin = makeSuperadminUser();

    $this->actingAs($superadmin)
        ->get(route('superadmin.settings.payment'))
        ->assertOk();

    $this->actingAs($superadmin)
        ->put(route('superadmin.settings.payment.update'), [
            'bakong_account_id' => 'platform@aclb',
            'merchant_name' => 'AMS Platform',
            'merchant_city' => 'Phnom Penh',
            'currency' => 'USD',
        ])
        ->assertRedirect(route('superadmin.settings.payment'));

    $row = PlatformPaymentSetting::current();

    expect($row->bakong_account_id)->toBe('platform@aclb')
        ->and($row->merchant_name)->toBe('AMS Platform')
        ->and($row->currency)->toBe('USD')
        ->and(PlatformPaymentSetting::count())->toBe(1); // still a singleton
});

it('offers no khqr.cc credential fields at all', function () {
    $superadmin = makeSuperadminUser();

    $page = $this->actingAs($superadmin)->get(route('superadmin.settings.payment'))->assertOk();

    // Fields that configure nothing are how an operator ends up carefully
    // filling in a credential that cannot be used. The columns behind them were
    // dropped too — a retired credential left in the database is a credential
    // nobody rotates and nobody misses when it leaks.
    $page->assertDontSee('khqrpay_profile_id')
        ->assertDontSee('khqrpay_secret')
        ->assertDontSee('khqr.cc');

    expect(\Schema::hasColumn('platform_payment_settings', 'khqrpay_secret'))->toBeFalse()
        ->and(\Schema::hasColumn('merchant_payment_settings', 'khqrpay_secret'))->toBeFalse();
});

it('rejects a merchant name or city too long for the EMV field', function () {
    $superadmin = makeSuperadminUser();

    // Exceeding these makes the QR MALFORMED rather than merely long, because
    // EMV prefixes each value with its own two-digit byte length. Rejected here
    // so the operator is told, instead of being silently truncated at build
    // time into a QR that scans as something else.
    $this->actingAs($superadmin)
        ->put(route('superadmin.settings.payment.update'), [
            'bakong_account_id' => 'platform@aclb',
            'merchant_name' => str_repeat('A', 26),
            'merchant_city' => str_repeat('B', 16),
            'currency' => 'USD',
        ])
        ->assertSessionHasErrors(['merchant_name', 'merchant_city']);
});

it('falls back to .env only when the saved field is blank', function () {
    config()->set('bakong.account_id', 'env@aclb');
    config()->set('bakong.merchant_name', 'Env Merchant');

    // No row at all — a fresh install, a CI run, a local demo.
    expect(BakongPlatformIdentity::current()->accountId)->toBe('env@aclb')
        ->and(BakongPlatformIdentity::current()->source())->toContain('.env');

    // A blank column falls THROUGH to .env rather than overriding it with
    // emptiness: the saved row may predate these fields entirely.
    PlatformPaymentSetting::create(['bakong_account_id' => '', 'currency' => 'USD']);
    expect(BakongPlatformIdentity::current()->accountId)->toBe('env@aclb');

    // A saved value wins, because the person who needs to change a payout
    // account is not the person with shell access.
    PlatformPaymentSetting::current()->update(['bakong_account_id' => 'db@aclb']);
    expect(BakongPlatformIdentity::current()->accountId)->toBe('db@aclb')
        ->and(BakongPlatformIdentity::current()->source())->toBe('Payment Settings');
});

it('blocks non-superadmins from the platform payment settings', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->get(route('superadmin.settings.payment'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson(route('superadmin.settings.payment.usage'))
        ->assertForbidden();
});

// ───────────────────────── the allowance meter ─────────────────────────

it('shows the allowance meter without spending a Bakong request', function () {
    Http::fake();
    $superadmin = makeSuperadminUser();

    config()->set('bakong.daily_request_limit', 80);
    BakongToken::create([
        'email' => 'integrator@ams.test',
        'token' => 'eyJ0eXAiOiJKV1QifQ.e30.sig',
        'expires_at' => now()->addDays(60),
        'verified_at' => now(),
    ]);

    foreach (range(1, 6) as $i) {
        BakongApiCall::create([
            'called_on' => now()->toDateString(),
            'endpoint' => '/v1/check_transaction_by_md5',
            'reason' => 'verify_payment',
            'target' => 'platform',
            'allowed' => true,
        ]);
    }

    $this->actingAs($superadmin)
        ->get(route('superadmin.settings.payment'))
        ->assertOk()
        ->assertSee(__('messages.bakong_usage_title'));

    $body = $this->actingAs($superadmin)
        ->getJson(route('superadmin.settings.payment.usage'))
        ->assertOk()
        ->json();

    expect($body['spent'])->toBe(6)
        ->and($body['limit'])->toBe(80)
        ->and($body['remaining'])->toBe(74)
        ->and($body['percent'])->toBe(8)
        ->and($body['state'])->toBe('ok')
        ->and($body['by_reason'])->toBe(['verify_payment' => 6])
        ->and($body['token']['usable'])->toBeTrue();

    // THE WHOLE POINT. A report that spent the allowance it reports on would be
    // worse than no report — and the moment anyone opens this page is the
    // moment the allowance is already under pressure.
    Http::assertNothingSent();
});

it('reports Bakong\'s own refusal separately from our ceiling', function () {
    Http::fake();
    $superadmin = makeSuperadminUser();
    config()->set('bakong.daily_request_limit', 80);

    // Six of eighty spent, and the day is nonetheless over. This is not a
    // contrived case: NBC meters the TOKEN, so anything else holding the same
    // credential — a second deployment, a hosted-checkout provider it was
    // pasted into — spends from the same allowance invisibly. It is exactly
    // what errorCode 17 said on this installation while khqr.cc shared it.
    app(\App\Services\Bakong\BakongQuotaLedger::class)
        ->markUpstreamExhausted('platform', 'Daily request limit of 100 exceeded');

    $body = $this->actingAs($superadmin)
        ->getJson(route('superadmin.settings.payment.usage'))
        ->assertOk()
        ->json();

    expect($body['spent'])->toBe(0)
        // The bar's arithmetic says "plenty left"; the verdict says otherwise,
        // and the verdict is what the page must lead with.
        ->and($body['state'])->toBe('exhausted')
        ->and($body['upstream_exhausted'])->not->toBeNull()
        ->and($body['upstream_exhausted']['why'])->toContain('Daily request limit');

    $this->actingAs($superadmin)
        ->get(route('superadmin.settings.payment'))
        ->assertOk()
        ->assertSee(__('messages.bakong_usage_upstream_title'));

    Http::assertNothingSent();
});

it('translates the remaining allowance into checkouts, not requests', function () {
    $superadmin = makeSuperadminUser();

    // "74 requests left" means nothing to someone deciding whether today's
    // signups will go through. A checkout costs one verification poll per
    // cooldown for as long as the QR lives, capped by max_verify_attempts —
    // minting it costs nothing, because the payload is built locally.
    config()->set([
        'bakong.daily_request_limit' => 80,
        'bakong.qr_ttl' => 6,
        'bakong.verify_cooldown' => 60,
        'bakong.max_verify_attempts' => 8,
    ]);

    $body = $this->actingAs($superadmin)
        ->getJson(route('superadmin.settings.payment.usage'))
        ->assertOk()
        ->json();

    expect($body['calls_per_checkout'])->toBe(6)     // 6 min ÷ 60 s
        ->and($body['checkouts_left'])->toBe(13);    // 80 ÷ 6
});
