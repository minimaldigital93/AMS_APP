<?php

use App\Models\MonthlyPeriod;
use App\Models\Payments;
use App\Models\Utilities;
use App\Services\FiscalPeriod\MonthClosePreflight;

/**
 * The pre-close check surfaces tenants who still owe rent or utilities for the
 * month being closed, so closing is an informed decision (warn, never block).
 */
beforeEach(function () {
    seedRoles();
    $this->admin = makeAdmin();
    $this->actingAs($this->admin);

    $this->month = MonthlyPeriod::create([
        'fiscal_period_id' => makeFiscalPeriod($this->admin)->id,
        'user_id' => $this->admin->id,
        'name' => 'January 2026', 'month_number' => 1, 'year' => 2026,
        'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
        'opening_balance' => 0, 'closing_balance' => 0,
        'total_income' => 0, 'total_expenses' => 0, 'net_income' => 0,
        'status' => 'open',
    ]);
});

function rentalActiveInJan(array $overrides = []): App\Models\Rentals
{
    $tenant = makeTenant();

    return makeRental($tenant, $tenant->apartment, array_merge([
        'start_date' => '2025-12-01', // active during Jan 2026
        'rent_amount' => 500,
    ], $overrides));
}

it('flags a rental with no rent payment for the month', function () {
    rentalActiveInJan();

    $result = app(MonthClosePreflight::class)->unpaidFor($this->month);

    expect($result['has_unpaid'])->toBeTrue()
        ->and($result['rent_count'])->toBe(1)
        ->and($result['rent'][0]->status)->toBe('unpaid')
        ->and($result['rent'][0]->shortfall)->toBe(500.0);
});

it('does not flag a rental whose rent is fully paid within the month', function () {
    $rental = rentalActiveInJan();
    Payments::create([
        'rental_id' => $rental->id, 'amount' => 500,
        'due_date' => '2026-01-05', 'paid_at' => '2026-01-05',
        'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_type' => 'rent',
    ]);

    $result = app(MonthClosePreflight::class)->unpaidFor($this->month);

    expect($result['rent_count'])->toBe(0)
        ->and($result['has_unpaid'])->toBeFalse();
});

it('flags a partial rent payment with the remaining shortfall', function () {
    $rental = rentalActiveInJan();
    Payments::create([
        'rental_id' => $rental->id, 'amount' => 200,
        'due_date' => '2026-01-05', 'paid_at' => '2026-01-05',
        'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_type' => 'rent',
    ]);

    $result = app(MonthClosePreflight::class)->unpaidFor($this->month);

    expect($result['rent_count'])->toBe(1)
        ->and($result['rent'][0]->status)->toBe('partial')
        ->and($result['rent'][0]->shortfall)->toBe(300.0);
});

it('ignores a rent payment that landed in a different month', function () {
    $rental = rentalActiveInJan();
    Payments::create([
        'rental_id' => $rental->id, 'amount' => 500,
        'due_date' => '2026-02-05', 'paid_at' => '2026-02-05', // February, not January
        'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_type' => 'rent',
    ]);

    $result = app(MonthClosePreflight::class)->unpaidFor($this->month);

    expect($result['rent_count'])->toBe(1); // still owes for January
});

it('excludes a rental that had not started yet', function () {
    rentalActiveInJan(['start_date' => '2026-03-01']); // starts after January

    $result = app(MonthClosePreflight::class)->unpaidFor($this->month);

    expect($result['rent_count'])->toBe(0);
});

it('flags unpaid utility charges billed for the month', function () {
    $rental = rentalActiveInJan();
    // Pay the rent so only the utility remains outstanding.
    Payments::create([
        'rental_id' => $rental->id, 'amount' => 500,
        'due_date' => '2026-01-05', 'paid_at' => '2026-01-05',
        'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_type' => 'rent',
    ]);
    Utilities::create([
        'tenant_id' => $rental->tenant_id, 'rental_id' => $rental->id,
        'utility_type' => 'water', 'meter_reading_in' => 0, 'meter_reading_out' => 0,
        'charge_amount' => 30, 'billing_month' => 1, 'billing_year' => 2026,
        'paid_status' => false, 'paid_at' => null,
    ]);

    $result = app(MonthClosePreflight::class)->unpaidFor($this->month);

    expect($result['rent_count'])->toBe(0)
        ->and($result['utilities_count'])->toBe(1)
        ->and($result['utilities_outstanding'])->toBe(30.0)
        ->and($result['has_unpaid'])->toBeTrue();
});

it('reports nothing unpaid when everything is settled', function () {
    $rental = rentalActiveInJan();
    Payments::create([
        'rental_id' => $rental->id, 'amount' => 500,
        'due_date' => '2026-01-05', 'paid_at' => '2026-01-05',
        'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_type' => 'rent',
    ]);

    $result = app(MonthClosePreflight::class)->unpaidFor($this->month);

    expect($result['has_unpaid'])->toBeFalse()
        ->and($result['total_count'])->toBe(0);
});

/**
 * Rent owed is DERIVED (BillingCycleService), never `rentals.rent_amount`.
 * Reading the column raw made the preflight report a phantom shortfall on
 * every fully-paid prorated move-in month — the one month where the two
 * figures differ — and told the admin the books weren't ready to close.
 */
it('uses the prorated move-in rent, not the full monthly rent', function () {
    settings(['billing_cycle_day' => '2']);

    // Moved in Jan 8 on a $300 room, collection day 2: Jan 8 → Feb 2 is
    // 25 days, so January owes $241.94 — not $300.
    $tenant = makeTenant();
    $rental = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-01-08',
        'rent_amount' => 300,
    ]);

    $due = app(App\Services\Billing\BillingCycleService::class)
        ->periodFor($rental, 1, 2026)->amount;
    expect($due)->toBe(241.94);

    // Paid in full at the figure the collection page billed.
    Payments::create([
        'rental_id' => $rental->id, 'amount' => $due,
        'due_date' => '2026-01-31', 'paid_at' => '2026-01-31',
        'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_type' => 'rent',
    ]);

    $result = app(MonthClosePreflight::class)->unpaidFor($this->month);

    expect($result['rent_count'])->toBe(0)
        ->and($result['has_unpaid'])->toBeFalse();
});

it('still measures a prorated month short when it is genuinely underpaid', function () {
    settings(['billing_cycle_day' => '2']);

    $tenant = makeTenant();
    $rental = makeRental($tenant, $tenant->apartment, [
        'start_date' => '2026-01-08',
        'rent_amount' => 300,
    ]);

    Payments::create([
        'rental_id' => $rental->id, 'amount' => 100,
        'due_date' => '2026-01-31', 'paid_at' => '2026-01-31',
        'payment_method' => 'cash', 'payment_status' => 'paid', 'payment_type' => 'rent',
    ]);

    $result = app(MonthClosePreflight::class)->unpaidFor($this->month);

    // Short against the PRORATED figure (241.94 - 100), not the full 300.
    expect($result['rent_count'])->toBe(1)
        ->and($result['rent'][0]->rent)->toBe(241.94)
        ->and($result['rent'][0]->shortfall)->toBe(141.94)
        ->and($result['rent'][0]->status)->toBe('partial');
});
