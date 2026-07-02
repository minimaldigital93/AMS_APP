<?php

use App\Models\Apartments;
use App\Models\Floors;
use App\Models\Payments;
use App\Models\Property;

/**
 * Supervisor bulk rent recording: the posted rows keep their full shape
 * (rental_id, amount, late_fee, selected) all the way into recordBulkRent(),
 * with rows outside the supervisor's assigned properties dropped. This used to
 * be broken — the rows were run through intval() and every one was skipped, so
 * the endpoint always answered "no apartments selected".
 */
function bulkIncomeFixture(): array
{
    $admin = makeAdmin();
    auth()->login($admin); // stamp account_id on the rows created below
    makeFiscalPeriod($admin);

    // Property A — assigned to the supervisor.
    $propA = Property::create(['name' => 'A']);
    $floorA = Floors::create(['property_id' => $propA->id, 'floor_name' => 'A1']);
    $roomA = Apartments::create(['floor_id' => $floorA->id, 'apartment_number' => 'A101', 'monthly_rent' => 500, 'status' => 'occupied']);
    $rentalA = makeRental(makeTenant($roomA), $roomA);

    // Property B — NOT assigned to the supervisor.
    $propB = Property::create(['name' => 'B']);
    $floorB = Floors::create(['property_id' => $propB->id, 'floor_name' => 'B1']);
    $roomB = Apartments::create(['floor_id' => $floorB->id, 'apartment_number' => 'B101', 'monthly_rent' => 700, 'status' => 'occupied']);
    $rentalB = makeRental(makeTenant($roomB), $roomB);

    $sup = makeSupervisor(['account_id' => $admin->id]);
    $propA->update(['supervisor_id' => $sup->id]);

    return compact('admin', 'sup', 'rentalA', 'rentalB');
}

it('records bulk rent for selected rentals in assigned properties', function () {
    $f = bulkIncomeFixture();

    $this->actingAs($f['sup'])
        ->post(route('supervisor.revenue_expense.store_income_bulk'), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'apartments' => [
                ['rental_id' => $f['rentalA']->id, 'amount' => 500, 'late_fee' => 0, 'selected' => 1],
            ],
        ])
        ->assertSessionHas('success');

    expect(Payments::where('rental_id', $f['rentalA']->id)->where('payment_type', 'rent')->count())->toBe(1);
});

it('drops bulk rows for rentals outside the supervisors assigned properties', function () {
    $f = bulkIncomeFixture();

    $this->actingAs($f['sup'])
        ->post(route('supervisor.revenue_expense.store_income_bulk'), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'apartments' => [
                ['rental_id' => $f['rentalA']->id, 'amount' => 500, 'selected' => 1],
                ['rental_id' => $f['rentalB']->id, 'amount' => 700, 'selected' => 1],
            ],
        ])
        ->assertSessionHas('success');

    expect(Payments::where('rental_id', $f['rentalA']->id)->count())->toBe(1)
        ->and(Payments::where('rental_id', $f['rentalB']->id)->count())->toBe(0);
});

it('reports no apartments selected when every row is outside the assigned set', function () {
    $f = bulkIncomeFixture();

    $this->actingAs($f['sup'])
        ->post(route('supervisor.revenue_expense.store_income_bulk'), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'apartments' => [
                ['rental_id' => $f['rentalB']->id, 'amount' => 700, 'selected' => 1],
            ],
        ])
        ->assertSessionHas('error');

    expect(Payments::count())->toBe(0);
});
