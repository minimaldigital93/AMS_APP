<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Signup TAKES OVER the owner row it matches on phone — new password, status
 * reset to `inactive`. That is right for an abandoned never-paid attempt and
 * catastrophic for a real account: it resets a customer's login and, once the
 * attacker pays, hands them the account's data.
 *
 * The line is `subscriptions.started_at` — set by both finalizeSubscription()
 * and startTrial(), so it means "this account was ever activated". A lapsed
 * owner never needs to re-register anyway: expiry only flips
 * subscriptions.status, never users.status, so they sign in and renew.
 */
beforeEach(function () {
    seedRoles();
    config(['services.khqrpay.demo' => true]);
    $this->plan = Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24,
        'billing_period_days' => 30, 'trial_days' => 14, 'is_active' => true,
    ]);
});

/** An account owner with a subscription in the given state. */
function ownerWithSubscription(string $phone, array $subscription): User
{
    $user = User::factory()->create([
        'phone' => $phone,
        'status' => 'active',
        'password' => Hash::make('their-real-password'),
    ]);
    $user->forceFill(['account_id' => $user->id])->save();
    $user->assignRole('admin');

    Subscription::create($subscription + [
        'account_id' => $user->id,
        'plan_id' => Plan::where('slug', 'pro')->value('id'),
    ]);

    return $user;
}

it('refuses to re-register the phone of an account whose subscription has lapsed', function () {
    $owner = ownerWithSubscription('0977000111', [
        'status' => 'expired',
        'started_at' => now()->subMonths(6),
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->post(route('subscribe.store'), [
        'name' => 'Impostor',
        'phone' => '0977000111',
        'password' => 'attacker-password',
        'password_confirmation' => 'attacker-password',
        'plan' => 'pro',
    ]);

    $response->assertSessionHasErrors('phone');

    // Their login is untouched — password, name, status and admin role intact,
    // so they can still sign in and renew from the billing page.
    $owner->refresh();
    expect(Hash::check('their-real-password', $owner->password))->toBeTrue();
    expect($owner->name)->not->toBe('Impostor');
    expect($owner->status)->toBe('active');
    expect($owner->hasRole('admin'))->toBeTrue();
});

it('refuses the phone of an account that used its free trial and let it lapse', function () {
    // startTrial() stamps started_at too — a trialed account is a real account
    // with real data, even though no money ever changed hands.
    ownerWithSubscription('0977000222', [
        'status' => 'expired',
        'started_at' => now()->subDays(20),
        'trial_started_at' => now()->subDays(20),
        'expires_at' => now()->subDays(6),
    ]);

    $this->post(route('subscribe.store'), [
        'name' => 'Impostor',
        'phone' => '0977000222',
        'password' => 'attacker-password',
        'password_confirmation' => 'attacker-password',
        'plan' => 'pro',
    ])->assertSessionHasErrors('phone');
});

it('still lets an abandoned never-paid signup finish registering on the same phone', function () {
    // The case the takeover exists for: the row never activated, holds no data,
    // and the users_phone_unique index would reject a duplicate anyway.
    $abandoned = User::factory()->create(['phone' => '0977000333', 'status' => 'inactive']);
    $abandoned->forceFill(['account_id' => $abandoned->id])->save();
    Subscription::create([
        'account_id' => $abandoned->id,
        'plan_id' => $this->plan->id,
        'status' => 'pending',   // never paid
        'started_at' => null,    // never activated
    ]);

    $this->post(route('subscribe.store'), [
        'name' => 'Second Try',
        'phone' => '0977000333',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ])->assertSessionHasNoErrors();

    // Taken over, not duplicated.
    expect(User::where('phone', '0977000333')->count())->toBe(1);
    expect($abandoned->fresh()->name)->toBe('Second Try');
});

it('refuses the phone of a live subscriber', function () {
    ownerWithSubscription('0977000444', [
        'status' => 'active',
        'started_at' => now()->subDays(3),
        'expires_at' => now()->addDays(27),
    ]);

    $this->post(route('subscribe.store'), [
        'name' => 'Impostor',
        'phone' => '0977000444',
        'password' => 'attacker-password',
        'password_confirmation' => 'attacker-password',
        'plan' => 'pro',
    ])->assertSessionHasErrors('phone');
});
