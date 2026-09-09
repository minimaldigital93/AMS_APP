<?php

use App\Models\User;

/**
 * The account owner's row is the locked one on team management (its user id IS
 * the account id every other row hangs off, so authorizeTeamMember() 403s it
 * and _row/_card render it with the padlock instead of controls). It heads the
 * roster whatever the owner is called — sorting it by name alongside the
 * co-admins buried the padlock under any team member earlier in the alphabet.
 */
it('lists the account owner first even when co-admins sort earlier by name', function () {
    $owner = makeAdmin(['name' => 'Zed Owner', 'phone' => '0730000001']);

    $coAdmin = User::factory()->create([
        'name' => 'Aaron Co Admin',
        'phone' => '0730000002',
        'account_id' => $owner->id,
    ]);
    $coAdmin->assignRole('admin');

    $supervisor = User::factory()->create([
        'name' => 'Abby Supervisor',
        'phone' => '0730000003',
        'account_id' => $owner->id,
    ]);
    $supervisor->assignRole('supervisor');

    $users = $this->actingAs($owner)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->viewData('users');

    expect($users->first()->getKey())->toBe($owner->getKey())
        // the rest of the ordering is untouched: admins, then supervisors, by name
        ->and($users->pluck('name')->all())
        ->toBe(['Zed Owner', 'Aaron Co Admin', 'Abby Supervisor']);
});
