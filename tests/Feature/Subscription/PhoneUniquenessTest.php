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

/** A completed owner = registered AND subscribed (live subscription). Their phone is reserved. */
it('rejects a phone already held by a completed (active) account owner', function () {
    $owner = User::factory()->create(['phone' => '0999111000', 'status' => 'active']);
    $owner->forceFill(['account_id' => $owner->id])->save();
    $owner->assignRole('admin');
    Subscription::create([
        'account_id' => $owner->id, 'plan_id' => $this->plan->id,
        'status' => 'active', 'expires_at' => now()->addMonth(),
    ]);

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

/**
 * The reported bug: an owner whose row is NOT 'inactive' but who never became a
 * live customer (legacy default, or a subscription that lapsed/expired) must
 * still be free to re-register — only a *current* successful customer is blocked.
 */
it('lets an owner with no live subscription re-register on the same phone, reusing the row', function () {
    $lapsed = User::factory()->create(['name' => 'Lapsed', 'phone' => '0999111555', 'status' => 'active']);
    $lapsed->forceFill(['account_id' => $lapsed->id])->save();
    $lapsed->assignRole('admin');
    // Paid once, then the subscription expired — no longer a live customer.
    Subscription::create([
        'account_id' => $lapsed->id, 'plan_id' => $this->plan->id,
        'status' => 'active', 'expires_at' => now()->subDay(),
    ]);

    $response = $this->post(route('subscribe.store'), [
        'name' => 'Comeback',
        'phone' => '0999111555',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    $response->assertSessionHasNoErrors();
    // Same row taken over (reset to inactive until they pay) — not duplicated.
    expect(User::where('phone', '0999111555')->count())->toBe(1);
    $user = User::where('phone', '0999111555')->first();
    expect($user->id)->toBe($lapsed->id);
    expect($user->name)->toBe('Comeback');
    expect($user->status)->toBe('inactive');
});

/**
 * POLICY REVERSED by the 2026-07 DB audit (D2): the users table is one global
 * login namespace — Auth::attempt() looks phones up globally, so a phone held
 * by ANY user row (including another account's supervisor/tenant login) is
 * taken. Previously sub-user phones were considered free for signup.
 */
it('rejects a phone that belongs to another account\'s sub-user', function () {
    $admin = User::factory()->create(['phone' => '0999111333', 'status' => 'active']);
    $admin->forceFill(['account_id' => $admin->id])->save();
    $admin->assignRole('admin');

    // A supervisor of that admin holds the number — not an owner, but they log in with it.
    $supervisor = User::factory()->create(['phone' => '0999111444', 'status' => 'active', 'account_id' => $admin->id]);
    $supervisor->assignRole('supervisor');

    $response = $this->post(route('subscribe.store'), [
        'name' => 'New Owner',
        'phone' => '0999111444',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'plan' => 'pro',
    ]);

    $response->assertSessionHasErrors('phone');
    // No owner row was minted on that phone.
    expect(User::whereColumn('account_id', 'id')->where('phone', '0999111444')->exists())->toBeFalse();
});
