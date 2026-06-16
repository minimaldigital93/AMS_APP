<?php

use App\Models\PlatformPaymentSetting;
use App\Models\User;
use App\Services\RevenueExpense\KhqrCredentials;

function makeSuperadminUser(): User
{
    seedRoles();
    $user = User::factory()->create(['name' => 'Platform Owner']);
    $user->assignRole('superadmin');

    return $user;
}

it('lets the superadmin save platform payment settings with an encrypted secret', function () {
    $superadmin = makeSuperadminUser();

    $this->actingAs($superadmin)
        ->get(route('superadmin.settings.payment'))
        ->assertOk();

    $this->actingAs($superadmin)
        ->put(route('superadmin.settings.payment.update'), [
            'khqrpay_profile_id' => 'platform-profile',
            'khqrpay_secret' => 'super-secret-key',
            'currency' => 'USD',
        ])
        ->assertRedirect(route('superadmin.settings.payment'));

    $row = PlatformPaymentSetting::current();
    expect($row->khqrpay_profile_id)->toBe('platform-profile');
    expect($row->currency)->toBe('USD');
    expect($row->khqrpay_secret)->toBe('super-secret-key'); // decrypts via cast
    // Raw DB value must NOT be plaintext.
    $raw = \DB::table('platform_payment_settings')->value('khqrpay_secret');
    expect($raw)->not->toBe('super-secret-key');
});

it('keeps the existing secret when the field is left blank on update', function () {
    $superadmin = makeSuperadminUser();
    PlatformPaymentSetting::create(['khqrpay_secret' => 'keep-me', 'currency' => 'USD']);

    $this->actingAs($superadmin)
        ->put(route('superadmin.settings.payment.update'), [
            'khqrpay_secret' => '',
            'currency' => 'USD',
        ])
        ->assertRedirect();

    expect(PlatformPaymentSetting::current()->khqrpay_secret)->toBe('keep-me');
    expect(PlatformPaymentSetting::count())->toBe(1); // still a singleton
});

it('blocks non-superadmins from the platform payment settings', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->get(route('superadmin.settings.payment'))
        ->assertForbidden();
});

it('platform credentials come only from the superadmin settings, never .env', function () {
    // Even with .env/config values present, the platform flow must ignore them.
    config()->set('services.khqrpay.profile_id', 'env-profile');
    config()->set('services.khqrpay.secret', 'env-secret');
    config()->set('services.khqrpay.currency', 'USD');

    // No DB row → nothing configured (no .env fallback).
    $creds = KhqrCredentials::platform();
    expect($creds->profileId)->toBe('');
    expect($creds->secret)->toBe('');

    // Values come straight from the DB row; a blank secret stays blank.
    PlatformPaymentSetting::create([
        'khqrpay_profile_id' => 'db-profile',
        'currency' => 'KHR',
    ]);

    $creds = KhqrCredentials::platform();
    expect($creds->profileId)->toBe('db-profile');
    expect($creds->secret)->toBe('');
    expect($creds->currency)->toBe('KHR');
});
