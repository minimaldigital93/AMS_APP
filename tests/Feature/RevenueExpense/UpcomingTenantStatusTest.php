<?php

use Carbon\Carbon;

/**
 * A tenant whose tenancy starts in a later month must not be billed as
 * overdue/pending in the months before their move-in — the rent-collection
 * page should surface them as "upcoming" and keep their rent out of the
 * current month's expected/pending tallies.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-07-27');
    $this->admin = makeAdmin();
    auth()->login($this->admin);
    $this->period = makeFiscalPeriod($this->admin, [
        'opening_date' => '2026-01-01',
        'closing_date' => '2026-12-31',
    ]);
    $this->apartment = makeApartment(null, ['monthly_rent' => 500]);
    $this->tenant = makeTenant($this->apartment);
    // Moves in next month — nothing is owed for July.
    $this->rental = makeRental($this->tenant, $this->apartment, [
        'rent_amount' => 500,
        'start_date' => '2026-08-01',
    ]);
    auth()->logout();
});

afterEach(fn () => Carbon::setTestNow());

function upcomingBill($response, $rentalId): ?array
{
    return collect($response->viewData('tenantBills')->items())
        ->firstWhere(fn ($b) => $b['rental']->id === $rentalId);
}

it('marks a not-yet-started tenant as upcoming, not overdue, in the current month', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 7, 'year' => 2026]));

    $response->assertOk();
    $bill = upcomingBill($response, $this->rental->id);

    expect($bill)->not->toBeNull()
        ->and($bill['status'])->not->toBe('overdue')
        ->and($bill['is_upcoming'] ?? false)->toBeTrue()
        ->and($bill['overdue_days'])->toBe(0);

    // Their rent isn't part of THIS month's collectable expectation.
    expect($response->viewData('overdueCount'))->toBe(0);
});

it('bills the tenant normally once their move-in month arrives', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.revenue_expense.record_income', ['month' => 8, 'year' => 2026]));

    $response->assertOk();
    $bill = upcomingBill($response, $this->rental->id);

    // August is their first month — due date is start_date + 1 month, so not
    // yet overdue at the start of August, but no longer flagged "upcoming".
    expect($bill['is_upcoming'] ?? false)->toBeFalse()
        ->and($bill['is_first_month'])->toBeTrue();
});
