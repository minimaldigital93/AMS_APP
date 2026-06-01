<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\User;

/** Promote a fresh admin to own its account and act as them. */
function makeAccountAdmin(): User
{
    $admin = makeAdmin();
    $admin->forceFill(['account_id' => $admin->id])->save();

    return $admin;
}

it('isolates floors and apartments between two accounts', function () {
    $a = makeAccountAdmin();
    $b = makeAccountAdmin();

    // Account A data
    $this->actingAs($a);
    $floorA = Floors::create(['floor_name' => 'A-1']);
    Apartments::create(['floor_id' => $floorA->id, 'apartment_number' => 'A-101', 'monthly_rent' => 500, 'status' => 'available']);
    Apartments::create(['floor_id' => $floorA->id, 'apartment_number' => 'A-102', 'monthly_rent' => 500, 'status' => 'available']);

    // Account B data
    $this->actingAs($b);
    $floorB = Floors::create(['floor_name' => 'B-1']);
    Apartments::create(['floor_id' => $floorB->id, 'apartment_number' => 'B-101', 'monthly_rent' => 500, 'status' => 'available']);

    // A only sees A's
    $this->actingAs($a);
    expect(Floors::count())->toBe(1);
    expect(Apartments::count())->toBe(2);
    expect(Floors::first()->floor_name)->toBe('A-1');

    // B only sees B's
    $this->actingAs($b);
    expect(Floors::count())->toBe(1);
    expect(Apartments::count())->toBe(1);
    expect(Apartments::first()->apartment_number)->toBe('B-101');
});

it('auto-stamps account_id from the acting user', function () {
    $admin = makeAccountAdmin();
    $this->actingAs($admin);

    $floor = Floors::create(['floor_name' => 'X']);

    expect($floor->fresh()->account_id)->toBe($admin->id);
});

it('lets the superadmin read across accounts with withoutAccountScope', function () {
    $a = makeAccountAdmin();
    $b = makeAccountAdmin();

    $this->actingAs($a);
    Floors::create(['floor_name' => 'A-1']);
    $this->actingAs($b);
    Floors::create(['floor_name' => 'B-1']);

    // Even while acting as B, the unscoped query sees both accounts' floors.
    expect(Floors::withoutAccountScope()->count())->toBe(2);
    expect(Floors::count())->toBe(1);
});
