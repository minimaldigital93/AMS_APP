<?php

use App\Models\User;

/**
 * The users table is one global login namespace: Auth::attempt() looks a phone
 * up globally, so the same phone in two accounts means the second user can
 * never log in. These tests pin the global uniqueness enforced by validation
 * (and by the users_phone_unique index underneath).
 */
it('rejects creating a team member with a phone used by another account', function () {
    $adminA = makeAdmin();
    auth()->login($adminA);
    $memberA = makeSupervisor(['account_id' => $adminA->id, 'phone' => '099111222']);
    auth()->logout();

    $adminB = makeAdmin();

    $this->actingAs($adminB)
        ->post(route('admin.users.store'), [
            'name' => 'Clash',
            'phone' => '099111222',
            'password' => 'SuperSecret9!',
            'role' => 'supervisor',
        ])
        ->assertSessionHasErrors('phone');
});

it('rejects creating a tenant whose login phone is used by another account', function () {
    $adminA = makeAdmin();
    auth()->login($adminA);
    makeSupervisor(['account_id' => $adminA->id, 'phone' => '099333444']);
    auth()->logout();

    $adminB = makeAdmin();
    auth()->login($adminB);
    $room = makeApartment(null, ['status' => 'available']);
    auth()->logout();

    $this->actingAs($adminB)
        ->post(route('admin.tenants.store'), [
            'apartment_id' => $room->id,
            'name' => 'Clash Tenant',
            'phone' => '099333444',
            'move_in_date' => now()->toDateString(),
            'status' => 'active',
        ])
        ->assertSessionHasErrors('phone');
});

it('rejects signing up with a phone that belongs to another accounts member', function () {
    $adminA = makeAdmin();
    auth()->login($adminA);
    makeSupervisor(['account_id' => $adminA->id, 'phone' => '099555666']);
    auth()->logout();

    $plan = \App\Models\Plan::first() ?? \App\Models\Plan::create([
        'slug' => 'starter', 'name' => 'Starter', 'price_usd' => 9, 'billing_period_days' => 30,
    ]);

    $this->post(route('subscribe.store'), [
        'name' => 'New Owner',
        'phone' => '099555666',
        'password' => 'SuperSecret9!',
        'password_confirmation' => 'SuperSecret9!',
        'plan' => $plan->slug,
    ])->assertSessionHasErrors('phone');
});

it('still lets a failed signup re-register with the same phone', function () {
    seedRoles();
    // A reusable owner row: account_id = id, no live subscription (abandoned signup).
    $ghost = User::factory()->create(['phone' => '099777888', 'status' => 'inactive']);
    $ghost->forceFill(['account_id' => $ghost->id])->save();

    $plan = \App\Models\Plan::create([
        'slug' => 'retry', 'name' => 'Retry', 'price_usd' => 9, 'billing_period_days' => 30, 'trial_days' => 7,
    ]);

    $this->post(route('subscribe.store'), [
        'name' => 'Second Try',
        'phone' => '099777888',
        'password' => 'SuperSecret9!',
        'password_confirmation' => 'SuperSecret9!',
        'plan' => $plan->slug,
        'start_trial' => 1,
    ])->assertSessionHasNoErrors();

    // The existing row was taken over — no duplicate user was minted.
    expect(User::where('phone', '099777888')->count())->toBe(1);
});
