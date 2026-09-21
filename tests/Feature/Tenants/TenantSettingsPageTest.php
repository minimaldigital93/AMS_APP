<?php

use App\Models\User;

/**
 * Until this page existed, a tenant on a phone had no way to log out or
 * switch language: the topbar hamburger is suppressed on phones ($useBottomNav
 * in layouts.tenant), so the off-canvas sidebar — and its logout button — were
 * unreachable there, and the tenant panel had no language switcher on any
 * device. tenant.settings fixes both by posting to the existing global
 * `language.switch` / `logout` routes.
 */
function makeTenantUser(): User
{
    seedRoles();
    $admin = makeAdmin();
    $user = User::factory()->create(['name' => 'Tenant User']);
    $user->assignRole('tenant');
    $user->forceFill(['account_id' => $admin->id])->save();

    return $user;
}

it('lets a tenant reach the settings page with a language toggle and a logout button', function () {
    $tenant = makeTenantUser();

    $response = $this->actingAs($tenant)->get(route('tenant.settings'));

    $response->assertOk();
    $response->assertSee(route('language.switch'), false);
    $response->assertSee(route('logout'), false);
});

it('is reachable from both the mobile bottom nav and the desktop sidebar', function () {
    $tenant = makeTenantUser();

    $response = $this->actingAs($tenant)->get(route('tenant.dashboard'));

    $response->assertOk();
    $response->assertSee(route('tenant.settings'), false);
});

it('lets the tenant switch the app to Khmer from the settings page', function () {
    $tenant = makeTenantUser();

    $this->actingAs($tenant)
        ->post(route('language.switch'), ['locale' => 'km'])
        ->assertRedirect();

    expect(session('locale'))->toBe('km');

    $this->actingAs($tenant)
        ->get(route('tenant.settings'))
        ->assertOk()
        ->assertSee('ខ្មែរ');
});

it('logs the tenant out from the settings page action', function () {
    $tenant = makeTenantUser();

    $this->actingAs($tenant)
        ->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();
});

it('blocks non-tenant roles from the tenant settings page', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->get(route('tenant.settings'))
        ->assertForbidden();
});
