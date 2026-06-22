<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Property;

/** A throwaway object that exposes the supervisor-scope trait helpers. */
function supervisorScopeProbe(): object
{
    return new class
    {
        use \App\Http\Controllers\Concerns\ScopesToSupervisorProperties;

        public function ids(): array
        {
            return $this->supervisorApartmentIds();
        }

        public function canAccess(Apartments $apt): bool
        {
            return $this->supervisorCanAccessApartment($apt);
        }
    };
}

it('scopes a supervisor to rooms in their assigned properties only', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);

    // Property A — assigned to the supervisor.
    $propA = Property::create(['name' => 'A']);
    $floorA = Floors::create(['property_id' => $propA->id, 'floor_name' => 'A1']);
    $roomA = Apartments::create(['floor_id' => $floorA->id, 'apartment_number' => 'A101', 'monthly_rent' => 0, 'status' => 'available']);

    // Property B — NOT assigned.
    $propB = Property::create(['name' => 'B']);
    $floorB = Floors::create(['property_id' => $propB->id, 'floor_name' => 'B1']);
    $roomB = Apartments::create(['floor_id' => $floorB->id, 'apartment_number' => 'B101', 'monthly_rent' => 0, 'status' => 'available']);

    $sup = makeSupervisor(['account_id' => $admin->id]);
    $propA->update(['supervisor_id' => $sup->id]);

    $this->actingAs($sup);
    $probe = supervisorScopeProbe();

    expect($probe->ids())->toBe([$roomA->id]);
    expect($probe->canAccess($roomA->fresh()))->toBeTrue();
    expect($probe->canAccess($roomB->fresh()))->toBeFalse();
});

it('lets an admin on a supervisor route see all rooms', function () {
    $admin = makeAdmin();
    $this->actingAs($admin);

    $prop = Property::create(['name' => 'A']);
    $floor = Floors::create(['property_id' => $prop->id, 'floor_name' => 'A1']);
    $room = Apartments::create(['floor_id' => $floor->id, 'apartment_number' => 'A101', 'monthly_rent' => 0, 'status' => 'available']);

    $probe = supervisorScopeProbe();

    expect($probe->ids())->toContain($room->id);
    expect($probe->canAccess($room->fresh()))->toBeTrue();
});
