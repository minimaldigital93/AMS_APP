<?php

use App\Models\User;

/**
 * Phase 12 (V1–V3): login-page language toggle for guests, remember-me,
 * and sticky password-reset flashes (the message contains the new password,
 * so it must not auto-dismiss).
 */
it('lets a guest switch the login language', function () {
    $this->post(route('language.switch'), ['locale' => 'km'])->assertRedirect();
    expect(session('locale'))->toBe('km');

    $this->get(route('login'))->assertOk()->assertSee('ខ្មែរ');
});

it('rejects unsupported locales', function () {
    $this->post(route('language.switch'), ['locale' => 'fr'])->assertRedirect();
    expect(session('locale'))->toBeNull();
});

it('shows remember-me on the login form', function () {
    $this->get(route('login'))->assertOk()->assertSee('name="remember"', false);
});

it('flashes a team-member password reset as sticky', function () {
    $admin = makeAdmin();
    $staff = User::factory()->create(['status' => 'active', 'account_id' => $admin->id]);
    $staff->assignRole('supervisor');

    $this->actingAs($admin)
        ->post(route('admin.users.reset-password', $staff))
        ->assertSessionHas('success_sticky')
        ->assertSessionMissing('success');
});
