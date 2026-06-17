<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

beforeEach(function () {
    seedRoles();
    config(['services.khqrpay.demo' => true]);
    $this->plan = Plan::create([
        'slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24,
        'max_floors' => 4, 'max_apartments' => 200, 'billing_period_days' => 30, 'is_active' => true,
    ]);
});

/** A completed owner = registered AND subscribed. Their phone is reserved. */
it('rejects a phone already held by a completed (active) account owner', function () {
    $owner = User::factory()->create(['phone' => '0999111000', 'status' => 'active']);
    $owner->forceFill(['account_id' => $owner->id])->save();
    $owner->assignRole('admin');

    $response = $this->post(route('subscribe.store'), [
        'name' => 'Impostor',
        'phone' => '0999111000',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    $response->assertSessionHasErrors('phone');
    // No second owner was minted on that phone.
    expect(User::where('phone', '0999111000')->count())->toBe(1);
});

/** An abandoned, never-paid signup stays inactive — its phone is free to reuse. */
it('lets an abandoned (inactive) signup re-register on the same phone, reusing the row', function () {
    $abandoned = User::factory()->create(['name' => 'Old Attempt', 'phone' => '0999111222', 'status' => 'inactive']);
    $abandoned->forceFill(['account_id' => $abandoned->id])->save();
    Subscription::create(['account_id' => $abandoned->id, 'plan_id' => $this->plan->id, 'status' => 'pending']);

    $response = $this->post(route('subscribe.store'), [
        'name' => 'Fresh Attempt',
        'phone' => '0999111222',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    $response->assertSessionHasNoErrors();
    // Same row taken over — not duplicated — and its details refreshed.
    expect(User::where('phone', '0999111222')->count())->toBe(1);
    $user = User::where('phone', '0999111222')->first();
    expect($user->id)->toBe($abandoned->id);
    expect($user->name)->toBe('Fresh Attempt');
    // And exactly one (still pending) subscription — the prior one was reused.
    expect(Subscription::where('account_id', $abandoned->id)->count())->toBe(1);
});

/** A number used only by another account's tenant/supervisor is not an owner — free to register. */
it('lets a phone used only by another account\'s sub-user register as a new owner', function () {
    $admin = User::factory()->create(['phone' => '0999111333', 'status' => 'active']);
    $admin->forceFill(['account_id' => $admin->id])->save();
    $admin->assignRole('admin');

    // A supervisor of that admin reuses the number — active, but NOT an owner.
    $supervisor = User::factory()->create(['phone' => '0999111444', 'status' => 'active', 'account_id' => $admin->id]);
    $supervisor->assignRole('supervisor');

    $response = $this->post(route('subscribe.store'), [
        'name' => 'New Owner',
        'phone' => '0999111444',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    $response->assertSessionHasNoErrors();
    // A brand-new owner exists on that phone (account_id == id), alongside the supervisor.
    expect(User::whereColumn('account_id', 'id')->where('phone', '0999111444')->exists())->toBeTrue();
});
