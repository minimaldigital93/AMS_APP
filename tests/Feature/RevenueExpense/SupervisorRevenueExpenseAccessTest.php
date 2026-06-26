<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Property;

/**
 * A supervisor may only act on rentals/apartments under one of their assigned
 * properties (properties.supervisor_id). The revenue/expense endpoints that take
 * a rental_id / apartment from the request must 403 when the target lives in an
 * unassigned property — the account global scope alone is not enough.
 */
function supervisorAccessFixture(): array
{
    $admin = makeAdmin();
    auth()->login($admin); // stamp account_id on the rows we create below
    makeFiscalPeriod($admin); // open period so the fiscal.period gate passes

    // Property A — assigned to the supervisor.
    $propA = Property::create(['name' => 'A']);
    $floorA = Floors::create(['property_id' => $propA->id, 'floor_name' => 'A1']);
    $roomA = Apartments::create(['floor_id' => $floorA->id, 'apartment_number' => 'A101', 'monthly_rent' => 500, 'status' => 'occupied']);
    $rentalA = makeRental(makeTenant($roomA), $roomA);

    // Property B — NOT assigned to the supervisor.
    $propB = Property::create(['name' => 'B']);
    $floorB = Floors::create(['property_id' => $propB->id, 'floor_name' => 'B1']);
    $roomB = Apartments::create(['floor_id' => $floorB->id, 'apartment_number' => 'B101', 'monthly_rent' => 500, 'status' => 'occupied']);
    $rentalB = makeRental(makeTenant($roomB), $roomB);

    $sup = makeSupervisor(['account_id' => $admin->id]);
    $propA->update(['supervisor_id' => $sup->id]);

    return compact('admin', 'sup', 'roomA', 'roomB', 'rentalA', 'rentalB');
}

it('lets a supervisor view a receipt for a rental in an assigned property', function () {
    $f = supervisorAccessFixture();
    $this->actingAs($f['sup']);

    $this->get(route('supervisor.revenue_expense.print_receipt', ['rental' => $f['rentalA']->id, 'embed' => 1]))
        ->assertOk();
});

it('forbids a supervisor from viewing a receipt for a rental in an unassigned property', function () {
    $f = supervisorAccessFixture();
    $this->actingAs($f['sup']);

    $this->get(route('supervisor.revenue_expense.print_receipt', ['rental' => $f['rentalB']->id, 'embed' => 1]))
        ->assertForbidden();
});

it('forbids a supervisor from clearing charges on a rental in an unassigned property', function () {
    $f = supervisorAccessFixture();
    $this->actingAs($f['sup']);

    $this->delete(route('supervisor.revenue_expense.clear_charges', ['rental' => $f['rentalB']->id]))
        ->assertForbidden();
});

it('lets an admin previewing the supervisor panel reach any property', function () {
    $f = supervisorAccessFixture();
    $this->actingAs($f['admin']); // admin/superadmin are not property-scoped

    $this->get(route('supervisor.revenue_expense.print_receipt', ['rental' => $f['rentalB']->id, 'embed' => 1]))
        ->assertOk();
});
