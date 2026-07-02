<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Property;
use App\Models\Tenants;
use App\Models\User;
use App\Services\Property\PropertyContext;

/**
 * Property → Floor → Apartment tree owned by $admin's account, for exercising
 * the user-list property filter (no tenant row — tests attach their own users).
 */
function makeUserFilterTree(User $admin, string $name, ?User $supervisor = null): array
{
    $property = new Property(['name' => $name, 'supervisor_id' => $supervisor?->id]);
    $property->account_id = $admin->id;
    $property->save();

    $floor = Floors::create(['floor_name' => $name.' F1', 'property_id' => $property->id]);
    $apartment = Apartments::create([
        'floor_id' => $floor->id,
        'apartment_number' => $name.'-101',
        'monthly_rent' => 500,
        'status' => 'available',
    ]);

    return compact('property', 'floor', 'apartment');
}

/** A supervisor/tenant user on $admin's team, optionally renting $apartment. */
function makeTeamMember(User $admin, string $role, string $name, ?Apartments $apartment = null): User
{
    $user = $role === 'supervisor'
        ? makeSupervisor(['name' => $name])
        : tap(User::factory()->create(['name' => $name]))->assignRole('tenant');
    $user->forceFill(['account_id' => $admin->id])->save();

    if ($apartment !== null) {
        Tenants::create([
            'user_id' => $user->id,
            'apartment_id' => $apartment->id,
            'name' => $name,
            'phone' => '555-'.\Illuminate\Support\Str::random(6),
            'status' => 'active',
            'deposit' => 0,
        ]);
    }

    return $user;
}

it('filters the user list to the active property', function () {
    $admin = makeAdmin();
    $supervisorAlpha = makeTeamMember($admin, 'supervisor', 'Alpha Supervisor');
    $supervisorBravo = makeTeamMember($admin, 'supervisor', 'Bravo Supervisor');
    $alpha = makeUserFilterTree($admin, 'Alpha', $supervisorAlpha);
    $bravo = makeUserFilterTree($admin, 'Bravo', $supervisorBravo);
    makeTeamMember($admin, 'tenant', 'Alpha Renter', $alpha['apartment']);
    makeTeamMember($admin, 'tenant', 'Bravo Renter', $bravo['apartment']);
    makeTeamMember($admin, 'supervisor', 'Unassigned Supervisor');

    $this->actingAs($admin);
    session([PropertyContext::SESSION_KEY => $alpha['property']->id]);

    // Assert on the listed users, not the raw page (the notification bell also
    // mentions new tenants by name).
    $response = $this->get(route('admin.users.index'))->assertOk();
    $names = $response->viewData('users')->pluck('name');

    expect($names)->toContain('Test Admin')             // account-level: visible under every property
        ->toContain('Alpha Supervisor')
        ->toContain('Alpha Renter')
        ->toContain('Unassigned Supervisor')            // no property yet: visible everywhere
        ->not->toContain('Bravo Supervisor')
        ->not->toContain('Bravo Renter');
});

it('shows every team member under "All properties"', function () {
    $admin = makeAdmin();
    $supervisorAlpha = makeTeamMember($admin, 'supervisor', 'Alpha Supervisor');
    $supervisorBravo = makeTeamMember($admin, 'supervisor', 'Bravo Supervisor');
    $alpha = makeUserFilterTree($admin, 'Alpha', $supervisorAlpha);
    $bravo = makeUserFilterTree($admin, 'Bravo', $supervisorBravo);
    makeTeamMember($admin, 'tenant', 'Alpha Renter', $alpha['apartment']);
    makeTeamMember($admin, 'tenant', 'Bravo Renter', $bravo['apartment']);

    $this->actingAs($admin);
    session([PropertyContext::SESSION_KEY => PropertyContext::ALL_PROPERTIES]);

    $response = $this->get(route('admin.users.index'))->assertOk();
    $names = $response->viewData('users')->pluck('name');

    expect($names)->toContain('Alpha Supervisor')
        ->toContain('Alpha Renter')
        ->toContain('Bravo Supervisor')
        ->toContain('Bravo Renter');
});

it('never leaks another account\'s users regardless of property context', function () {
    $admin = makeAdmin();
    makeUserFilterTree($admin, 'Mine');

    $other = makeAdmin(['name' => 'Other Admin']);
    $foreignSupervisor = makeTeamMember($other, 'supervisor', 'Foreign Supervisor');
    makeUserFilterTree($other, 'Theirs', $foreignSupervisor);

    $this->actingAs($admin);

    $response = $this->get(route('admin.users.index'))->assertOk();

    expect($response->viewData('users')->pluck('name'))->not->toContain('Foreign Supervisor');
});
