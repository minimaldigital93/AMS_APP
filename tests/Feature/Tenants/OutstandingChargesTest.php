<?php

use App\Models\Payments;
use App\Models\Utilities;

/**
 * A tenant's outstanding debt = unpaid rent months + unpaid utility charges,
 * both carried forward until settled (no stored "debt" rows). This is what the
 * tenant detail page's "Outstanding" figure and unpaid-charges list read.
 */
beforeEach(function () {
    seedRoles();
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);
});

it('sums unpaid rent months and unpaid utilities into one total', function () {
    $tenant = makeTenant();
    $rental = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-05-01', // May, Jun, Jul(current) = 3 rent months
        'rent_amount' => 500,
    ]);

    // No rent paid → all 3 months unpaid = 1500.
    // One unpaid utility (30) + one already-paid utility (should be ignored).
    Utilities::create([
        'tenant_id' => $tenant->id, 'rental_id' => $rental->id,
        'utility_type' => 'water', 'meter_reading_in' => 0, 'meter_reading_out' => 0,
        'charge_amount' => 30, 'billing_month' => 6, 'billing_year' => 2026,
        'paid_status' => false, 'paid_at' => null,
    ]);
    Utilities::create([
        'tenant_id' => $tenant->id, 'rental_id' => $rental->id,
        'utility_type' => 'electricity', 'meter_reading_in' => 0, 'meter_reading_out' => 0,
        'charge_amount' => 99, 'billing_month' => 5, 'billing_year' => 2026,
        'paid_status' => true, 'paid_at' => '2026-05-10',
    ]);

    $out = $tenant->outstandingCharges();

    expect($out['rent_due'])->toBe(1500.0)
        ->and($out['utilities_due'])->toBe(30.0)
        ->and($out['total_due'])->toBe(1530.0)
        ->and($out['unpaid_utilities'])->toHaveCount(1)
        ->and($out['unpaid_utilities']->first()->type)->toBe('water');
});

it('drops a rent month once its rent is paid', function () {
    $tenant = makeTenant();
    $rental = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-06-01', // Jun, Jul = 2 months
        'rent_amount' => 400,
    ]);

    Payments::create([
        'rental_id' => $rental->id, 'amount' => 400,
        'due_date' => '2026-06-05', 'paid_at' => '2026-06-05',
        'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_type' => 'rent',
    ]);

    $out = $tenant->outstandingCharges();

    // June settled, July still owed.
    expect($out['rent_due'])->toBe(400.0)
        ->and($out['total_due'])->toBe(400.0);
});

it('reports zero when rent and utilities are all settled', function () {
    $tenant = makeTenant();
    $rental = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-07-01', // current month only
        'rent_amount' => 300,
    ]);

    Payments::create([
        'rental_id' => $rental->id, 'amount' => 300,
        'due_date' => '2026-07-05', 'paid_at' => '2026-07-05',
        'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_type' => 'rent',
    ]);
    Utilities::create([
        'tenant_id' => $tenant->id, 'rental_id' => $rental->id,
        'utility_type' => 'water', 'meter_reading_in' => 0, 'meter_reading_out' => 0,
        'charge_amount' => 20, 'billing_month' => 7, 'billing_year' => 2026,
        'paid_status' => true, 'paid_at' => '2026-07-06',
    ]);

    $out = $tenant->outstandingCharges();

    expect($out['total_due'])->toBe(0.0)
        ->and($out['unpaid_utilities'])->toBeEmpty();
});
